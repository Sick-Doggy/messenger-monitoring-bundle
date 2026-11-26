# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Co to jest

`SickDoggy/MessengerMonitoring` (composer `sick-doggy/messenger-monitoring-bundle`) — Symfony 6.4 bundle
monitorujący **cykl życia wiadomości Messenger**: producent włącza monitoring implementując marker
`SickDoggy\MessengerMonitoring\MonitoredMessage` w swojej klasie message. Dane cyklu życia (`StatusEntry` —
każda zmiana stanu = jeden POST) raportujemy po HTTP do zewnętrznego store'a (osobny projekt, API Platform).

Kontrakt HTTP i założenia: **`docs/ARCHITECTURE.md`** — tam są wszystkie decyzje projektowe (model danych store'a,
idempotencja, etapy). Nie zmieniają kształtu payloadu bez aktualizacji tego dokumentu.

## Komendy

```bash
composer install
composer phpstan                # PHPStan level 8 — WYMAGANE zero errors przed każdym commitem
composer validate --strict

# demo end-to-end (mock store wypisuje payload do konsoli):
cd demo
docker compose run --rm composer install                 # lub: composer install lokalnie
docker compose up -d mock-store && docker compose logs -f mock-store
docker compose run --rm demo php bin/console messenger:setup-transports
docker compose run --rm demo php bin/console demo:send INV-101          # readonly message
docker compose run --rm demo php bin/console demo:process ORD-1         # MUTOWALNA message + retry
docker compose run --rm demo php bin/console demo:send INV-FAIL         # 3 retry → failed-final
docker compose up -d consumer                # worker (NIE `compose run` — Ctrl+C nie propaguje sygnałów)
docker compose stop consumer
```

Mock store (`demo/mock-store/router.php`) — tylko podglądanie POST; w cli-server (`php -S`) loguj przez `error_log()`, NIE przez STDERR (niezdefiniowane w SAPI cli-server).

## Architektura (strumień danych)

1. **Marker** `MonitoredMessage` włącza monitoring wiadomości (bez konfiguracji).
2. **`RegisterBusMiddlewarePass`** (CompilerPass, `TYPE_BEFORE_REMOVING` — po `MessengerPass` (ten idzie w
   `BEFORE_OPTIMIZATION`)) wstrzykuje `MonitoringMiddleware` do stacków busów rozpoznawanych po tagu
   `messenger.bus` (id busa = klucz z `buses:` w configu — prefiks `messenger.bus.*` NIE jest gwarantowany;
   tag + fallback po prefiksie). Deweloper NIE konfiguruje middleware ręcznie
   (opt-out: `auto_register_bus_middleware: false`). Tag `messenger.middleware` **nie istnieje jako mechanizm**
   w Symfony 6.4 — nie używać do rejestracji (stacki buduje `MessengerPass` z parametru `$busId.'.middleware'`;
   stack = `IteratorArgument` z raw `Reference`, nie `ServiceClosureArgument`).
3. **Middleware (producent)**: dokleja `MonitoringStamp` (korelacyjny `occurrenceId`, serializuje się w transporcie,
   guard: stamp istnieje → strona konsumpcji) i raportuje stany `sent` / `handled` (sync, brak `SentStamp`) / `send-failed`.
4. **`MessageLifecycleSubscriber`** (konsumpcja): eventy `WorkerMessage{Received,Handled,Failed}Event`;
   `willRetry()` rozdziela `failed-retry` / `failed-final`.
5. **`StatusChangePublisher`** → **`StoreLifecycleReporter`** → **`Client\StoreClient`** (`src/Client/`): przy każdej zmianie statusu POST stanu, upsert nagłówka automatycznie na HTTP 404 (przepływ w kontrakcie, ARCHITECTURE.md). Reporter jest failure-safe (błąd POST → warning, nigdy wyjątek). `api_key` wymagany.

## Konwenty (trzymać się ich przy nowych funkcjach)

- **Soft dependencies**: usługi opcjonalne (serializer, security) wstrzykuj z `Reference('id', IGNORE_ON_INVALID_REFERENCE)`
  w Extension (kolejność ładowania rozszerzeń NIE gwarantuje `hasDefinition`), konstruktor `?Type = null`;
  brak → feature `null`/warning, nigdy wyjątek w runtime. Wyjątki tylko dla błędów konfiguracji opt-in (jak `#[Payload]`
  bez serializera → `Exception\PayloadAttributeException`, fail-fast). Klasy soft-dep mają `require-dev` (dla PHPStan)
  + wpisy w `suggest` — nie `require`.
- **Atrybuty deklaratywne**: pary (atrybut + resolver) w `src/Attribute/`, każde rozwiązanie obok siebie;
  resolver z cache per klasa. Istniejące: `MonitoringId`, `Name`, `Tags`, `Payload` (+ `MessageContextFactory` łączące pierwsze trzy).
- **Modele vs usługi**: DTO w `src/Report/Model/` (namespace `Report\Model`; `MessageContext`, `MessageLifecycle`,
  `MessageStage`, `StatusEntry`); usługi (HttpReporter, LifecycleReporter, StatusChangePublisher) bezpośrednio w `Report\`.
- **Autowiring `ContainerInterface` nie działa ot tak** — wymaga aliasu do `service_container`; nie wstrzykuj kontenera, wstrzykuj konkretny serwis przez Extension.
- **Prywatne usługi są usuwane/inlined po kompilacji** — lazy `container->get()` z kodu nie działa; wszystko przez DI.
- `StatusEntry::withContext()` może zniknąć pola przy kopiowaniu — przy dodawaniu pól uaktualnij WSZYSTKIE metody
  `with*` i `toArray()`.

## Stan branchy/wydania

`main` = stabilny feature set (auto-register middleware, payload). Kolejne prace: GitHub Actions CI, phpunit (`tests/`
jeszcze nie istnieje; phpunit w require-dev), publikacja Packagist (tag `v0.1.0`), projekt #2 store/API Platform
(kontrakt w docs/ARCHITECTURE.md), włączenie tracku zalogowanego użytkownika (`UserStamp` + TokenStorage — szkic w backlogu).
Commity: `feat:`/`fix:`/`chore:`/`docs:`, prace przez `feature/*`, autor luktomczuk <luktomczuk@gmail.com>.
