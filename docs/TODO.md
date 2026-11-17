# TODO — uwagi, poprawki, roadmap

Stan na 2026-11-18. Plik roboczy roadmapy; gdy pozycja zniknie — była zrobiona albo celowo odrzucona.

## Do zrobienia / poprawki techniczne biblioteki (wykaz z review 2026-11-17)

3. **edge `sync://` transport** — wiadomości obsłużone synchronicznie przez ten transport mają i `SentStamp`, i `HandledStamp` w jednym dispatchu; obecne `syncStage()` raportuje `sent` zamiast `handled`. Poprawić rozstrzyganie: `SentStamp` && brak `HandledStamp` → sent; oba/lub brak SentStamp → handled. Do pokrycia testem.
4. **limit długości `errorMessage`** (np. ~4KB z markerem truncate) — exception message od Doctrine może być olbrzymi (query + params), oszczędza DB projektu #2.
5. **opcja: `symfony/http-client` → `http-client-contracts` + suggest** — nie wymuszać konkretnego komponentu u konsumentów (obecna wersja wymusza; oba warianty idiomatyczne — decyzja).
6. **usunięcie zmergowanych branchy** (`feature/autoregisterbus`, `feature/monitoring-payload`) — lokalnie i na GitHubie.

## Testy / jakość (największa dziura)

7. **phpunit + `tests/`** — kandydaci: `MonitoringIdResolver` (atrybut na meta/property, priorytet metody, fail-safe, cache), `NameResolver`/`TagsResolver`, `PayloadResolver` (groups default, missing serializer = exception, normalizacja-błąd = null), `StatusEntry::toArray` (komplet pól, metody `with*` zachowują pola), `MonitoringMiddleware` (fake stack: nowy wyst. / istniejący stamp / wyjątek), `MessageLifecycleSubscriber` (eventy workera).
8. **CI GitHub Actions** — `.github/workflows/ci.yml`: PHP 8.2, composer install, `composer phpstan`, `vendor/bin/phpunit`; potem protection na main (require status check).
9. **CONTRIBUTING.md** — workflow zespołowy (branche, commit naming, PHPStan L8, docker demo) — GitHub pokazuje go przy PR.

## Roadmap funkcji (backlog)

10. **Własny logger developera** (option B) — alias-klucz `SickDoggy\MessengerMonitoring\MonitoringLoggerInterface` jako punkt wpinania; konstruktory typują PSR-3 (marker tylko klucz); fallback na `@logger` CompilerPassem; scenariusze: kanał Monologa / klasa PSR-3 dewelopera / domyślny. Branch: `feature/monitoring-logger`.
11. **Zalogowany użytkownik** — `UserStamp` (userIdentifier w transporcie, jak MonitoringStamp) + `SecurityUserResolver` (TokenStorage, IGNORE_ON_INVALID w Extension); pole `userName` w nagłówku kontraktu (upsert store'a); config `track_user` (default true). Konsument sam nie wie usera — stamp jest jedyną drogą. Branch: `feature/monitoring-user`.
12. **Flex recipe** — repon Sick-Doggy/recipes: struktura `sick-doggy/messenger-monitoring-bundle/0.1/config/packages/messenger_monitoring.yaml` + manifest (copy-from-recipe + env MONITORING_APP_NAME/ENDPOINT_URL + conflict flex<2.12); index.json w target-brunch; consumer: `extra.symfony.repositories`. (Flex ≥2.12 rejestruje symfony-bundle sam.)
13. **Projekt #2 — store/API Platform** — kontrakt gotowy: `POST /api/lifecycles` (upsert header po occurrenceId + merge statuses; dedup occurrenceId+stage+occurredAt+application); spec OpenAPI ze API Platform jako współdzielona dokumentacja; model: MonitoredMessage (nagłówek) + statuses kolekcja; pola: monitoringId, name, tags, payload, application/environment, userName(jak 11 wejdzie).

## Premiera

14. **Packagist**: Submit `https://github.com/Sick-Doggy/messenger-monitoring-bundle`, konto `sick-doggy` (nie luktomczuk — username nie do zmiany), webhook GitHub→Packagist z Service hooks, tag `v0.1.0` po pozycjach 1–9, test `composer require sick-doggy/messenger-monitoring-bundle` w czystej aplikacji.

## Zrobione

- (2026-11-17) 1. occurredAt → RFC3339_EXTENDED (mikrosekundy; demo mock potwierdza .654+00:00)
- (2026-11-17) 2. LICENSE (MIT)
