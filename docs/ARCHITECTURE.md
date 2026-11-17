# Messenger Monitoring — założenia architektoniczne

Dokumentacja założeń wspólnych dla obu projektów (biblioteka + store). Data: 2026-11-16.

## Podział na projekty

Dwa osobne repozytoria, wiązane wyłącznie przez HTTP i kontrakt:

1. **messenger-monitoring** (to repo) — bundle dla deweloperów, Symfony 6.4. Instrumentacja cyklu życia wiadomościMessenger: middleware, stamp korelacyjny, event subscriber, reporter HTTP.
2. **store** (osobny projekt) — aplikacja oparta o **API Platform**: przechowywanie cyklu życia wiadomości + UX do przeglądania. Wystawia HTTP API do zapisu zdarzeń.

Biblioteka nie zależy od API Platform ani od kodu store'a. Kontrakt jest współdzielony przez **spec OpenAPI generowaną przez API Platform** (eksport wersjonowany przy release) — nie przez wspólny package z DTO.

## Koncepcja instrumentacji (biblioteka)

- Wiadomość włącza monitoring implementując marker `SickDoggy\MessengerMonitoring\MonitoredMessage` — bez konfiguracji per bus/transport.
- Główny mechanizm: **eventy Messenger**. Middleware pełni rolę pomocniczą (nadawanie korelacji).
- **Auto-rejestracja middleware**: bundle przez `RegisterBusMiddlewarePass` (CompilerPass, TYPE_BEFORE_REMOVING — po MessengerPass) wstrzykuje `MonitoringMiddleware` do prefiksu `messenger.bus.*`; deweloper nie konfiguruje middleware. Wyłączenie: `auto_register_bus_middleware: false`. (Tag `messenger.middleware` nie istnieje jako mechanizm w Symfony 6.4 — FrameworkExtension/MessengerPass budują stacky wyłącznie z parametru `$busId.'.middleware'`; dlatego rejestracja jest przez pass, nie przez tag. Stack busa = `IteratorArgument` surowych `Reference`.)
- **Korelacja**: `MonitoringStamp` z `occurrenceId` (UUID) doklejany w `MonitoringMiddleware` przy pierwszym przejściu przez bus (strona producenta). Stamp serializuje się w transporcie, więc producent i konsument raportują to samo wystąpienie. Guard: jeśli stamp istnieje (strona konsumpcji), middleware nie raportuje.
- **Pełny cykl, w tym strona producenta** (decyzja: tak).
- Raportowanie jest failure-safe: błąd POST trafia do loga (debug), nigdy nie psuje aplikacji.

## Cykl życia i etapy (enum `MessageStage`)

Model: wystąpienie → wiele przebiegów (retry) → etaty w przebiegu.

```
sent → received → handled (sukces — koniec życia)
               ↘ failed-retry  (koniec przebiegu, wiadomość wraca do kolejki)
               ↘ failed-final  (koniec życia — failure transport)
```

- `sent` — producent, przekazane do transportu (middleware; jest `SentStamp`)
- `send-failed` — wyjątek przy wysyłce (middleware)
- `received` — worker wyciągnął kopertę (WorkerMessageReceivedEvent)
- `handled` — handler zrealizowany (WorkerMessageHandledEvent) ; dla obsługi synchronicznej raportowane bez `received`
- `failed-retry` — niepowodzenie, nastąpi ponowna próba (WorkerMessageFailedEvent + willRetry)
- `failed-final` — niepowodzenie ostateczne (WorkerMessageFailedEvent + !willRetry)

**Retry = nowy przebieg tego samego schematu** (received → handled/failed ...). Numer przebiegu nie przenosimy — pochodna sekwencji zdarzeń.

## Model danych store'a

Nagłówek wpisu + kolekcja stanów z datami:

```
MonitoredMessage (nagłówek)
 ├─ id             = occurrenceId (korelacyjny z biblioteki)
 ├─ className
 ├─ monitoringId   = identyfikator biznesowy od dewelopera (nullable, indeks;filtr główny w UX)
 ├─ name           = własna nazwa wystąpienia (nullable; wyświetlana w UX zamiast className)
 ├─ tags[]         = etykiety dewelopera (kolekcja; do filtrowania w UX)
 ├─ application    = aplikacja-producent (origin; wyliczany przez store z pierwszego wpisu `sent`, indeksowany)
 └─ statuses[]     (kolekcja; ten sam stage może wystąpić wielokrotnie — retry)
      { stage, occurredAt, application?, environment?, busName?, transportName?, handlerName?, errorClass?, errorMessage? }
```

**Multi-aplikacyjność**: `application` jest przy **każdym stanie**, nie tylko w nagłówku — wystąpienie wysłane przez aplikację A i obsłużone przez B raportują tę samą kolekcję (store scala), a każdy stan wie, kto go raportował. Nagłówek trzyma `application` jako origin-producenta (indeks, filtr w UX). `environment` (opcjonalne) rozdziela prod/staging/dev.

Cykle nie są osobną encją — kolejne przebiegi wynikają z powtórzeń etapów; UX składa sekwencję po `occurredAt`. **`occurredAt` to RFC3339 z mikrosekundami (RFC3339_EXTENDED)** — stany mieszczące się w tej samej sekundzie muszą mieć rozróżniowalne czasy.

**monitoringId (id biznesowe)**: deweloper deklaruje go na klasie wiadomości atrybutem `#[MonitoringId('nazwa')]` — nazwa jest rozwiązywana jako publiczna metoda (priorytet) lub publiczne property; błędna konfiguracja atrybutu → `null` (fail-safe). Korelacja: `occurrenceId` = tożsamość techniczna wystąpienia, `monitoringId` = referencja biznesowa do wyszukiwania (może się powtarzać między wystąpieniami).

**name i tags**: kolejne deklaratywne rozwiązania w `SickDoggy\MessengerMonitoring\Attribute\` (para atrybut+resolver): `#[Name('...')]` — własna nazwa wystąpienia (UX zamiast className), `#[Tags([...])]` — etykiety do filtrowania. Wszystkie trzy wartości składane są przez `MessageContextFactory` do `MessageContext` (className + monitoringId + name + tags) i przechodzą w nagłówku każdego POST-u. Store: `monitoring_id`, `name` (nullable, indeksowane), `tags` (kolekcja) — upsert: pierwsze niepuste trafienie, null/pusta lista nie nadpisuje.

**payload (opcjonalne, atrybut #[Payload(groups?: [...])])**: deweloper wyraża zgodę na przesłanie
zserializowanego message'a (Symfony Serializer → JSON string). Payload jest **przypisany do stanów wytworzonych
na stronie producenta** (sent/handled synchroniczna/send-failed) — pole `status_entry.payload` (TEXT/JSON, nullable).
Wymagania po stronie aplikacji: `symfony/serializer` + `symfony/property-access` i `framework: serializer: { enabled: true }`;
Brak serializera przy użyciu atrybutu → twarde `PayloadAttributeException` (błąd konfiguracji, fail-fast;
developer ma go wykryć w testach) — decyzja użytkownika 2026-11-17. Błąd normalizacji konkretnego payloadu →
failure-safe (log + null). Serializacja honoruje normalizers aplikacji i grupy.
## Kontrakt HTTP (ingest)

`POST /api/lifecycles` (nazwa zasobu do ustalenia w store), payload `MessageLifecycle::toArray()`:

Biblioteka wysyła **POST przy każdej zmianie statusu** (`StatusChangePublisher`) — payload to nagłówek (occurrenceId, className) + kolekcja `statuses` **z jednym wpisem** (nowym stanem). Scalenie całej kolekcji cyklu życia robi store po `occurrenceId`; dzięki temu każdy stan dolatuje w czasie rzeczywistym, a buforowanie nie jest potrzebne (crash procesu nie traci danych).

```json
{
  "occurrenceId": "uuid-v4",
  "className": "App\\Message\\SendInvoice",
  "monitoringId": "INV-RETRY",
  "name": "Wystawienie faktury",
  "tags": ["billing", "critical"],
  "statuses": [
    {
      "stage": "sent",
      "occurredAt": "2026-11-16T21:29:50.123+01:00",
      "application": "billing",
      "environment": "prod",
      "busName": "messenger.bus.default",
      "transportName": null,
      "handlerName": null,
      "errorClass": null,
      "errorMessage": null
    },
    {
      "stage": "failed-retry",
      "occurredAt": "2026-11-16T21:30:00.456+01:00",
      "application": "invoicing",
      "environment": "prod",
      "busName": "messenger.bus.default",
      "transportName": "async",
      "handlerName": "App\\Handler\\SendInvoiceHandler",
      "errorClass": "App\\Exception\\FlakyException",
      "errorMessage": "..."
    }
  ]
}
```

- Semantyka: **upsert nagłówka po `occurrenceId` + dopisywanie stanu do kolekcji** — payload ma jeden wpis, ale kontrakt (nagłówek + kolekcja) pozostaje zgodny z modelem danych.
- **Idempotencja**: klucz duplikatu = (occurrenceId + stage + occurredAt + application). Ponowny POST → zignorowany duplikat, nie podwojony wpis.
- Format: zwykły JSON (`application/json`), nie JSON-LD, dla endpointów ingestu.
- Uwierzytelnianie: nagłówek `X-Api-Key` (konfigurowalny w bibliotece).

## Zasoby / kolekcje po stronie store (do przeglądu przy projektowaniu)

- Kolekcja nagłówków `MonitoredMessage` (przegląd wiadomości w UX).
- Zasób nagłówka `MonitoredMessage` z sub-kolekcją `statuses` (podgląd pełnego cyklu; UX sortuje po `occurredAt`).
- Zasoby projektowane jako DTO + Provider/Persister, dokumentacja przez Swagger/OpenAPI z API Platform.

## Poza zakresem biblioteki (celowo)

Metryki transportowe: głębokość kolejek, czas oczekiwania w kolejce, agregaty. Monitoring dotyczy cyklu życia pojedynczych wiadomości.

## Konfiguracja biblioteki

```yaml
messenger_monitoring:
    application: '%env(MONITORING_APP_NAME)%'   # wymagane: nazwa aplikacji raportującej
    endpoint: '%env(MONITORING_ENDPOINT_URL)%'   # wymagane
    environment: '%env(MONITORING_ENVIRONMENT)%' # opcjonalne: prod/staging/dev
    api_key: '%env(MONITORING_API_KEY)%'         # opcjonalny
    timeout: '2.0'                               # opcjonalny, sekundy
```
