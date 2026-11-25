# checko-api — REST API над checko.ru

Тестовое задание: REST API на PHP, отдающее данные с https://checko.ru/ — в стиле ООП,
без фреймворка.

## Стек

- PHP 8.x, PSR-4 автозагрузка, Composer — без фреймворка.
- Guzzle — HTTP-клиент к checko (таймауты, ретраи).
- Valkey + PSR-16 — кэш ответов checko на 15 минут (см. [ADR-0002](docs/adr/0002-cache-policy.md)).
- swagger-php — генерация OpenAPI (`openapi.yaml`).
- Docker Compose — PHP-FPM, nginx, Valkey, Redis Commander.

## Быстрый старт

```bash
cp .env.example .env       # вписать TOKEN — ключ checko API
make up                    # поднять контейнеры
make composer-install      # установить зависимости
```

- API: http://localhost:8080/api/v1/
- Valkey UI: http://localhost:8081/

`make run` — shell в php-cli, `make down` — остановить, `make down-clear` — с удалением данных.

## API

Базовый префикс — `/api/v1/`. Внешний API принимает параметры в query string, ключ checko
берётся из окружения сервера и клиенту не передаётся. Собственной аутентификации нет —
осознанное решение для тестового задания (см. [`docs/ACCEPTANCE.md`](docs/ACCEPTANCE.md)).

| Запрос | Описание |
|--------|----------|
| `GET /api/v1/company?inn=7707083893` | Данные организации по ИНН |
| `GET /api/v1/contracts?inn=7707083893&law=44&role=supplier&sort=-date` | Контракты |

Полный список ресурсов и параметров — в OpenAPI-спецификации (`openapi.yaml`), генерируемой
из аннотаций swagger-php.

```bash
curl -s 'http://localhost:8080/api/v1/company?inn=7707083893' | jq
```

### Ошибки

Единый формат: `{ "error": { "type", "message", "code" } }`.

| Исключение | HTTP |
|------------|------|
| `InvalidInputException` | 422 |
| `AuthException` | 401 |
| `ResourceNotFoundException` | 404 |
| `RateLimitException` | 429 |
| `CheckoApiException` | 502 |
| `TimeoutException` | 504 |

## Архитектура

Ядро из четырёх слоёв (паттерн Strategy) + HTTP-слой:

1. **Transport** — обёртка над Guzzle: таймауты, ретраи, единая точка внешних запросов.
2. **Resources** — по классу-стратегии на эндпоинт checko (`getPath`, `buildParams`,
   `mapResponse`).
3. **Facade / Client** — конвейер: валидация → параметры → Transport → кэш → DTO.
4. **DTO** — immutable-объекты с валидацией.
5. **API layer** — роутер + контроллеры `/api/v1/`.

Подробнее: [`docs/adr/0001-wrap-checko-api.md`](docs/adr/0001-wrap-checko-api.md).

## Документация

- [`docs/README.md`](docs/README.md) — индекс документации.
- [`docs/original-task.md`](docs/original-task.md) — исходное задание.
- [`docs/GLOSSARY.md`](docs/GLOSSARY.md) — термины проекта.
- [`docs/checko-api-errors.md`](docs/checko-api-errors.md) — контракт checko и распознавание ошибок.
- [`docs/ACCEPTANCE.md`](docs/ACCEPTANCE.md) — карта «требование ТЗ → реализация».
