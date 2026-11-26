# Prompt dla agenta — projekt #2: store/UX (API Platform)

Budujemy projekt 2 systemu monitoringu Symfony Messenger. Nie znasz poprzedniej sesji — ten dokument jest samodzielny. Projekt 1 (biblioteka `sick-doggy/messenger-monitoring-bundle` dla deweloperów) już istnieje i wysyła raporty po HTTP; Twoim zadaniem jest serwis, który je przechowa i pokaże.

## Cel

Aplikacja Symfony 6.4+ oparta o **API Platform** (PHP 8.2+), dwa zadania:
1. **API zapisowe** — przyjmuje dane cyklu życia wiadomości i je składa.
2. **UX przeglądania** — wewnętrzna aplikacja do przeglądania cykli (wiadomości → przebiegi → statusy), filtrowanie po polach.

Nazwa projektu: `messenger-monitoring-store` (sugerowana).

## Co otrzymujesz po HTTP (kontrakt ingestów — zamknięty)

Każda zmiana stanu pojedynczego wystąpienia wiadomości to osobny POST `application/json`:

`POST /api/lifecycles` (nazwa do dostosowania w procesie designu — kontrakt obowiązuje wartości):

```json
{
  "occurrenceId": "8b4e8ee1-2952-49aa-bddc-3a909b933185",
  "className": "App\\Message\\SendInvoice",
  "monitoringId": "INV-123",
  "processId": "ORD-99",
  "name": "Wystawienie faktury",
  "tags": ["billing", "invoices"],
  "statuses": [
    {
      "stage": "sent",
      "occurredAt": "2026-11-17T20:41:03.123+01:00",
      "application": "billing",
      "environment": "prod",
      "busName": "messenger.bus.default",
      "transportName": null,
      "handlerName": null,
      "errorClass": null,
      "errorMessage": null,
      "payload": "{\"invoiceId\":\"INV-123\"}"
    }
  ]
}
```

W nagłówku requesta jest `X-Api-Key: <token>` (opcjonalne — konfigurowane w bibliotece).

## Reguły semantyczne (kluczowe — nie naruszać)

1. **Model danych**: nagłówek (`MessageOccurrence`/`MonitoredMessage` — jedno wystąpienie wiadomości) + kolekcja stanów (z datą):
   - nagłówek: `id` = `occurrenceId`, `className`, `monitoringId` (nullable, indeksowany), `processId` (nullable, indeksowany), `name` (nullable), `tags` (kolekcja/JSON string[]).
   - stan: `stage`, `occurredAt` (z mikrosekundami — format RFC3339_EXTENDED), `application`, `environment`, `busName`, `transportName`, `handlerName`, `errorClass`, `errorMessage`, `payload` (JSON string, nullable, TEXT).
2. **Jedno occurrenceId = wiele POST-ów** (po jednym za zmianę stanu) — upsert nagłówka (utwórz jeśli nie istnieje; className + monitoringId/processId/name/tags przepisane przy pierwszym trafieniu; kolejne null/puste nie nadpisują istniejących).
3. **Etapy** (enum string): `sent`, `received`, `handled`, `send-failed`, `failed-retry`, `failed-final`. Ten sam etap MOŻE wystąpić wielokrotnie w jednym wystąpieniu — to są kolejne przebiegi (próby retry). Nie ma osobnej encji "próby"; sekwencję przebiegów odtwarzamy z kolejności zdarzeń po `occurredAt`.
4. **Idempotencja**: klucz wpisu stanu = (`occurrenceId`, `stage`, `occurredAt`, `application`). Duplikat POST-a → ignoruj (nie zduplikuj wpisu).
5. **Pola producenta** (application, environment) i konsumenta (transport/handler) przychodzą z różnych serwisów — ta sama sprawa widziana z kilku aplikacji to NORMALNY przypadek (`processId` + `application` rozdzielają).
6. Serwer nie może odrzucić poprawnego payloadu z powodu pól nieznanych (forward-compatible: biblioteka może dodawać nowe pola np. `userName`, `labels`).

## API Platform: zasoby (pierwszy szkic)

- **ingest** `POST /api/lifecycles` — DTO write-only + **Persister** (nie kontroler; projekt stosuje wzor: DTO + Provider/Persister), format **plain JSON** (wyłączyć JSON-LD/Hydra dla endpointu ingestu) i autoryzacja `X-Api-Key` (token z env), idempotentny upsert (409/201/200 OK z jednostajną semantyką — POST jako "append").
- **kolekcja** `GET /api/monitored-messages` — lista nagłówków: filtry `className`, `monitoringId`, `processId`, `application`, `environment`, zakresy `occurredAt[after/before]`, sort `-occurredAt[0]` (ostatnie najpierw). Pola pochodne z Providera: `currentStage`, `attempts` (liczba failed-retry + 1), `finishedAt`.
- **zasób pojedynczy** `GET /api/monitored-messages/{occurrenceId}` — pełny cykl, statuses posortowane po `occurredAt ASC`.
- **kolekcja stanów** `GET /api/monitored-messages/{id}/statuses` — sub-kolekcja do UX.

UX (wewnętrzny podgląd): lista wiadomości z filtrem per proces/aplikacja/etap; widok pojedynczego — oś czasu stanów z payloadem i błędami; widok "sprawy" po processId.

## Kontekst techniczny

- PHP >= 8.2, Symfony 6.4, API Platform, Doctrine ORM + SQLite w dev/postgres w prod (wybór jest Twój, najlepiej zgodny z hostem docelowym).
- Biblioteka jest soft-safe: jej błędy nie psują aplikacji — dlatego API powinno przyjmować i ignorować duplikaty bez szału.
- `occurredAt` ma mikrosekundy — baza przechowuje z dokładnością mikro (nie typowy `TIMESTAMP` — TIMESTAMP(6)/polecam `DATETIME(6)`).
- Testy: przynajmniej kontraktowe (ingest po schematu, idempotencja, filtrowanie) i PHPStan level 8.
- Autoryzacja: zwykły API key w nagłówku; CORS nie jest potrzebny (serwis-to-serwis, ingest nie wystawia UI).

## Skąd wziąć realne payload-y

W projekcie 1 (bibliotece) jest działające demo, które można odpalić i podjąć na te API (zamiast mocka) po zmianie `MONITORING_ENDPOINT_URL`:

```bash
cd demo
docker compose run --rm composer install
docker compose up -d mock-store  # albo wskaż endpoint Twojego store
docker compose run --rm demo php bin/console messenger:setup-transports
docker compose run --rm demo php bin/console demo:send INV-1   # sent (payload w stanie)
docker compose up -d consumer                                 # received/handled per POST
```

Wcześniej `demo:process ORD-99` pokaże łańcuch procesu (dwie klasy, jeden processId) — dobre case'i do testów scalania.

## Pułapki (zachowane w kodzie biblioteki — trzymaj się ich)

- `occurredAt` mikrosekundy — sekwencjanie stanów w jednym przebiegu.
- `payload` to JSON **string** (nie obiekt) — zapisz as-is, UX renderuje formatted.
- Nie zakładaj, że `sent` istnieje per przebieg — przy pojedynczym przebiegu może nie być (klient zaczął middleware wcześniej), a w retry w ogóle nie pojawia się.
- Wiadomość może być obsłużona synchronicznie (sync://) — wtedy przyjdzie tylko `handled` bez `received`.

## Oczekiwany efekt

Działający ingest (POST, idempotencja, upsert) + `GET` do przeglądu + wystawiona spec **OpenAPI** z API Platform (ta spec staje się dokumentacją kontraktu współdzieloną z library — po powrotach do projektu 1). PHP 8.2, PHPStan level 8 zero errors.
