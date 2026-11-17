# messenger-monitoring

Bundle Symfony 6.4 monitorujący cykl życia wiadomości Symfony Messenger.

## Jak to działa

Wiadomość włącza monitoring przez implementację interfejsu `SickDoggy\MessengerMonitoring\MonitoredMessage`:

```php
final class SendInvoiceNotification implements MonitoredMessage
{
    // ...
}
```

Bundle nasłuchuje eventów Messengera i raportuje cykl życia po HTTP do serwisu store (API Platform):

`sent` → `received` → `handled` | `failed-retry` | `failed-final`

Identyfikacja egzemplarza wiadomości: middleware w stacku busa dokleja `MonitoringStamp`
z korelacyjnym UUID (`occurrenceId`), który serializuje się razem z wiadomością w transporcie —
dzięki temu producent i konsument raportują jedno wystąpienie.

**Model raportowania**: przy każdej zmianie statusu biblioteka wysyła POST
(`StatusChangePublisher`) — nagłówek (`occurrenceId`, `className`) + kolekcja `statuses`
z jednym nowym wpisem. Store scala stany po `occurrenceId` (duplikaty po
`occurrenceId + stage + occurredAt + application` są ignorowane).

## Instalacja

```bash
composer require sick-doggy/messenger-monitoring-bundle
```

## Konfiguracja

```yaml
# config/packages/messenger_monitoring.yaml
messenger_monitoring:
    application: '%env(MONITORING_APP_NAME)%'     # wymagane: nazwa Twojej aplikacji
    endpoint: '%env(MONITORING_ENDPOINT_URL)%'    # wymagane: adres POST migawek w store
    environment: '%env(MONITORING_ENVIRONMENT)%'  # opcjonalne: prod/staging/dev
    api_key: '%env(MONITORING_API_KEY)%'          # opcjonalny
    timeout: '2.0'                                # sekundy, opcjonalny (domyślnie 2.0)
```

Nazwa aplikacji trafia do **każdego stanu** w kolekcji (`application` + opcjonalne `environment`),
dzięki czemu wystąpienie wysłane przez jedną aplikację i obsłużone przez inną
raportuje się z obu perspektyw.

## Rejestracja middleware (automatyczna)

NIE musisz nic dodawać do `messenger.yaml` — bundle sam wstrzykuje
`MonitoringMiddleware` do wszystkich busów (`messenger.bus.*`) przez CompilerPass.

Wyłączenie automatycznej rejestracji (i rejestracja ręczna w `messenger.yaml`):

```yaml
messenger_monitoring:
    auto_register_bus_middleware: false
    # framework:
    #     messenger:
    #         buses:
    #             messenger.bus.default:
    #                 middleware:
    #                     - SickDoggy\MessengerMonitoring\Middleware\MonitoringMiddleware
```

## Deklaratywne atrybuty (SickDoggy\MessengerMonitoring\Attribute\)

Na klasie wiadomości możesz użyć atrybutów (każde rozwiązanie = para: atrybut + resolver w `Attribute\`):

| Atrybut | Co robi | Przykład |
|---|---|---|
| `MonitoringId` | id biznesowe wystąpienia — nazwa **publicznego property lub metody** (metoda ma priorytet) | `#[MonitoringId('invoiceId')]` |
| `Name` | własna nazwa wystąpienia (w store/UX zamiast nazwy klasy) | `#[Name('Wystawienie faktury')]` |
| `Tags` | etykiety do filtrowania | `#[Tags(['billing', 'critical'])]` |

```php
use SickDoggy\MessengerMonitoring\Attribute\MonitoringId;
use SickDoggy\MessengerMonitoring\Attribute\Name;
use SickDoggy\MessengerMonitoring\Attribute\Tags;
use SickDoggy\MessengerMonitoring\MonitoredMessage;

#[MonitoringId('invoiceId')]
#[Name('Wystawienie faktury')]
#[Tags(['billing', 'critical'])]
final class SendInvoice implements MonitoredMessage { ... }
```

Wszystkie deklaratywne "rozwiązania" żyją w namespace `SickDoggy\MessengerMonitoring\Attribute\`,
każde jako para: atrybut + resolver.

Wartości trafiają do nagłówka payloadu (`monitoringId`, `name`, `tags`) i na nagłówek
w store z upsert-semantyką (pierwsze niepuste trafienie; null/pusta lista nie nadpisuje).

## Jakość kodu (wymagane)

Analiza statyczna **PHPStan poziom 8 jest wymagana** — kod przechodzi z **zerem błędów**,
bez wpisów `ignoreErrors` (ignory to dług, nie naprawa). Każda zmiana w `src/` musi
przejść analizę przed merge'em:

```bash
composer install
composer phpstan        # phpstan analyse, konfiguracja: phpstan.neon (level 8, phpVersion 8.2)
```

## Zasady

- Raportowanie nigdy nie psuje aplikacji: nieudany POST jest logowany (debug) i pomijany.
- Monitorowane są wiadomości przesyłane asynchronicznie; obsługa synchroniczna raportowana jako etap `handled` bez `received`.

## Kontrakt API

Kształt payloadu (`JsonPayload`) ma odzwierciedlać spec OpenAPI generowaną przez serwisy store (API Platform).
Kontrakt jest współdzielony przez spec, nie przez wspólny kod.
