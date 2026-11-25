# checko-api — REST API над checko.ru

Тестовое задание: REST API на PHP, отдающее данные с https://checko.ru/ — в стиле ООП,
без фреймворка.

## Стек

- PHP 8.x, PSR-4 автозагрузка, Composer — без фреймворка.
- Guzzle — HTTP-клиент к checko (таймауты, ретраи).
- PSR-7 (`guzzlehttp/psr7`) — HTTP-запрос и ответ без самописных реализаций.
- vlucas/phpdotenv — загрузка `.env` при локальном запуске.
- Tailwind CSS (CDN) + vanilla JS — демо-страница.
- Valkey + PSR-16 — кэш ответов checko на 1 час (см. [ADR-0002](docs/adr/0002-cache-policy.md)).
- OpenAPI-спецификация — `app/openapi.yaml`, поддерживается вручную.
- Docker Compose — PHP-FPM, nginx, Valkey, Redis Commander.

## Быстрый старт

```bash
cp .env.example .env       # вписать TOKEN — ключ checko API
make up                    # поднять контейнеры
make composer-install      # установить зависимости
```

`.env` подключается к PHP-контейнерам через `env_file`, поэтому `TOKEN` и `REDIS_ADDR`
доступны приложению без дублирования в `docker-compose.yml`. При запуске вне Docker файл
читается библиотекой `vlucas/phpdotenv`; переменные окружения имеют приоритет над `.env`.

- Демо-страница: http://localhost:8080/demo
- Swagger UI: http://localhost:8080/docs/
- OpenAPI-спецификация: http://localhost:8080/openapi.yaml
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
| `GET /api/v1/company?ogrn=1027700132195` | Данные организации по ОГРН |
| `GET /api/v1/health` | Проверка живости сервиса |

Нужно передать ровно один из параметров `inn` (10/12 цифр) или `ogrn` (13/15 цифр).
Остальные ресурсы checko добавляются по той же схеме: класс-стратегия + DTO.

Полный контракт — в OpenAPI-спецификации (`app/openapi.yaml`), поддерживаемой вручную.
Интерактивная документация с кнопкой Try it out — на `http://localhost:8080/docs/`.

```bash
curl -s 'http://localhost:8080/api/v1/company?inn=7707083893' | jq
```

Ответ — DTO с английскими ключами для смоделированных полей (`data.ogrn`, `data.nameShort`,
`data.status`, `data.address`, `data.heads`, ...). Блоки checko, для которых ещё нет
отдельных value-объектов, сохраняются в `data.additional` с исходными ключами; элементы
`data.founders` также сохраняют исходную структуру checko.

### Демо-страница

`GET /` отдаёт JSON-индекс с точками входа: демо, Swagger UI, OpenAPI и API.
`GET /demo` — тестовый интерфейс поверх API: поиск организации по ИНН/ОГРН, карточка с
реквизитами и риск-флагами, переключение на исходный JSON, копирование и скачивание ответа,
быстрые примеры, история запросов и наглядные состояния ошибок (422/401/404/429/502/504).
Страница собрана на PHP-шаблоне, Tailwind CSS и vanilla JS; данные запрашивает только у
собственного `/api/v1`, ключ checko в браузер не попадает.

### Ошибки

Единый формат: `{ "error": { "type", "message", "code" } }`.

| Тип ошибки | HTTP |
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

### Как читать код

Структура каталогов повторяет Laravel-скелет (без самого фреймворка):

```text
app/
├─ app/                  ← код приложения (PSR-4 префикс App\\)
│  ├─ Application.php
│  ├─ Cache/ Client/ Dto/ Exceptions/ Resources/ Support/ Transport/
│  └─ Http/
│     ├─ Controllers/
│     └─ Router.php, ExceptionHandler.php, Response/Response.php
├─ bootstrap/app.php     ← .env + config/*.php, сборка Application
├─ config/               ← настройки: app.php, checko.php, cache.php
├─ public/index.php      ← точка входа
├─ resources/views/      ← HTML-шаблоны (демо, Swagger UI)
├─ routes/               ← web.php, api.php
├─ storage/              ← логи
└─ tests/
```

Путь запроса:

```text
public/index.php                → точка входа
bootstrap/app.php               → .env + config/*.php, Application::create()
routes/web.php, routes/api.php  → регистрация маршрутов
Application                     → собирает Transport, Cache, Client, Router
ServerRequest (PSR-7)           → запрос
Http/Router                     → выбирает обработчик
Http/Controllers/CompanyController → принимает inn/ogrn
Client/CheckoClient             → кэш → Transport → DTO → кэш
Transport/HttpTransport         → POST в checko, ретраи, ошибки
Dto/ResponseCompanyDto::fromArray() → нормализует ответ checko в один DTO
Http/ExceptionHandler           → JSON-ошибки { error: { type, message, code } }
Http/Response/Response          → PSR-7 ответ и его отправка
```

Вся PHP-часть — 19 файлов в `app/app/`: один DTO, один класс ошибок, один
контроллер на API и один на HTML-страницы (демо и Swagger UI). Отдельных классов на каждую справочную
пару или каждый тип ошибки нет.

## Документация

- [`docs/README.md`](docs/README.md) — индекс документации.
- [`docs/original-task.md`](docs/original-task.md) — исходное задание.
- [`docs/GLOSSARY.md`](docs/GLOSSARY.md) — термины проекта.
- [`docs/checko-api-errors.md`](docs/checko-api-errors.md) — контракт checko и распознавание ошибок.
- [`docs/ACCEPTANCE.md`](docs/ACCEPTANCE.md) — карта «требование ТЗ → реализация».
