# demo

Minimalna aplikacja Symfony 6.4 korzystająca z `messenger-monitoring`.
Store jest symulowany prostym serwerem HTTP, który wypisuje **każdy przychodzący
request na konsolę** — dzięki temu widać dokładnie, co biblioteka wysyła.

Biblioteka jest podłączona przez `repositories` typu `path` na `../` — zmiany
w `src/` biblioteki są widoczne w demie natychmiast (symlink).

## Uruchomienie (Docker)

```bash
cd demo

# 1. zależności (path-repo na ../)
docker compose run --rm composer install

# 2. mock store w tle; requesty patrz: docker compose logs -f mock-store
docker compose up -d mock-store

# 3. schema transportu Doctrine (kolejka w SQLite)
docker compose run --rm demo php bin/console messenger:setup-transports

# 4. wysyłka (dispatcher) — w mocku zobaczysz migawkę z etapem "sent"
docker compose run --rm demo php bin/console demo:send INV-101
docker compose run --rm demo php bin/console demo:send INV-RETRY
docker compose run --rm demo php bin/console demo:send INV-FAIL

# 5. worker w tle (usługa consumer) — migawki "received", "failed-retry", "handled"/"failed-final"
docker compose up -d consumer
docker compose logs -f consumer        # podgląd logów workera
docker compose stop consumer           # zatrzymanie (SIGTERM — worker domyka się elegancko)
# Uwaga: `docker compose run consumer ...` z Ctrl+C nie propaguje sygnałów — używaj up/stop.

# stop
docker compose down
```

## Uruchomienie lokalnie (PHP 8.2)

```bash
cd demo/mock-store && php -S 127.0.0.1:9988 router.php

cd demo
composer install
php bin/console messenger:setup-transports
php bin/console demo:send INV-101
php bin/console messenger:consume async -vv
```

## Co zobaczysz w mocku

- `INV-101` — migawka producenta `[sent]`, potem z workera `[received, handled]`.
- `INV-RETRY` — `[sent]`, `[received, failed-retry]`, `[received, failed-retry]`,
  `[received, handled]` — handler pada przez pierwsze 2 próby, 3. się udaje
  (store scala te migawki w jedno wystąpienie z wieloma przebiegami).
- `INV-FAIL` — handler pada zawsze: 3 retry → `[received, failed-final]`.
- Każdy stan ma `application`/`environment` (demo-app dev, w Dockerze ustawiane w compose).

## Śledź jedno wystąpienie

Wszystkie migawki tego samego message'a mają wspólne `occurrenceId` — to jest klucz,
po którym przyszły store (API Platform) złoży pełny cykl życia.
