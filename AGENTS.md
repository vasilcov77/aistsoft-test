# agents.md

Контекстный файл для ИИ-ассистентов, работающих с репозитрием. Читается перед началом работы:
он задаёт общее представление о проекте, стек, архитектуру и конвенции, чтобы изменения были
последовательными.

## Что это за проект

Тестовое задание: **REST API на PHP для получения данных с checko.ru**, оформленное в стиле
ООП **без использования фреймворка**. Цель комиссии — оценить понимание ООП, читабельность и
чистоту кода, логику обработки ошибок и взаимодействие с целевым ресурсом.

Исходный текст задания: [`docs/original-task.md`](docs/original-task.md).

## Стек и зависимости

- **PHP** (без фреймворка), **PSR-4** автозагрузка, **Composer**.
- **Guzzle** — HTTP-клиент для внешних запросов.
- **PSR-7** (`guzzlehttp/psr7`) — HTTP-запрос и ответ в API-слое.
- **vlucas/phpdotenv** — загрузка `.env` при локальном запуске (в Docker — через `env_file`).
- **OpenAPI-спецификация** — `app/openapi.yaml`, поддерживается вручную (без аннотаций в коде).
- **Valkey** (форк Redis) — кэш ответов checko, доступ через абстракцию **PSR-16**.
- Токен checko — в переменной среды (`env`), проверка наличия при старте (fail fast).
- **Docker** — окружение разработки: сборка и запуск всего стека (PHP, Valkey) через
  `docker-compose.yml`. Назначение сервисов — в комментарии к файлу.
- **Makefile** — основные команды разработки (установка зависимостей, запуск, проверки). Удобен для воспроизводимого старта проекта.
- **Демонстрационная страница** — HTML + JavaScript + Tailwind CSS + PHP, фронтенд, наглядно
  показывающий работу API в браузере.

## Архитектура (ядро — 4 слоя, паттерн Strategy; сверху HTTP-слой)

1. **Transport** — точка доступа с ресурсами, обёртка над Guzzle. Отвечает за HTTP-транспорт,
   таймауты и ретраи. Работает с абстракцией `ResourceInterface`, не зная о домене.
2. **Resources (стратегии)** — по одной реализации на эндпоинт checko. Реализуют
   `ResourceInterface`: `getPath()`, `buildParams(array)`, `mapResponse(array): DTO`.
3. **Facade / Client** — конвейер: вход → параметры → Transport → кэш → ответ. Управляет
   ретраями и переводит транспортные ошибки в доменные исключения.
4. **DTO** — immutable-объекты данных с внутренней валидацией (отклоняют плохие входные данные).
5. **API layer (HTTP, над ядром)** — роутер + контроллеры под префиксом `/api/v1/`,
   отдающие JSON.

Раскладка каталогов повторяет Laravel-скелет (самого фреймворка нет): код в `app/app/`
(PSR-4 `App\`), `bootstrap/app.php`, `config/*.php` (+ хелперы `env()`/`config()`),
`routes/web.php` и `routes/api.php`, `public/index.php`, `resources/views/`, `storage/`.

Базовый адрес checko API: `https://api.checko.ru/v2/`. Все запросы — `POST`, тело
`application/json`, ключ `key` в теле. Ресурсы: `/company`, `/entrepreneur`, `/person`,
`/finances`, `/reliability`, `/timeline`, `/legal-cases`, `/enforcements`, `/contracts`,
`/fedresurs`, `/bankruptcy-messages`, `/inspections`, `/bank`.

## Ключевые решения (ADR)

- [`docs/adr/0001-wrap-checko-api.md`](docs/adr/0001-wrap-checko-api.md) — архитектура и
  обоснование.
- [`docs/adr/0002-cache-policy.md`](docs/adr/0002-cache-policy.md) — кэш: TTL 1 час,
  ключ без API-ключа, fail-open.
- [`docs/checko-api-errors.md`](docs/checko-api-errors.md) — формат ответа checko, таблица
  распознавания ошибок, лимиты и оплата.
- [`docs/GLOSSARY.md`](docs/GLOSSARY.md) — принятые термины (единый язык).
- [`docs/ACCEPTANCE.md`](docs/ACCEPTANCE.md) — карта «требование ТЗ → реализация».
- [`docs/README.md`](docs/README.md) — индекс документации.

## Конвенции и правила

- **При написании кода использовать скилл ponytail.** Минимальное изменение, которое
  полностью решает задачу; избегать over-engineering и лишнего раздувания кода.
- **ООП без фреймворка.** Никаких Laravel/Symfony/Slim — только чистый PHP + Guzzle.
- **Один ресурс = один класс-стратегия.** Добавить эндпоинт checko = добавить класс,
  реализующий `ResourceInterface`, плюс DTO. Transport и Facade не меняются (открыт/закрыт).
- **Валидация — в DTO.** Объект отклоняет некорректные данные при создании.
- **Ретраи — только на таймаут/соединение** (`ConnectException`), 3 попытки с экспоненциальной
  задержкой. 5xx от checko не ретраим: они не тарифицируются, но повтор обычно бесполезен и
  только задерживает ответ.
- **Кэш через PSR-16.** Не привязывать домен к Valkey напрямую — держать абстракцию.
  TTL — 1 час, ключ без API-ключа, ошибки не кэшируются, Valkey недоступен → fail-open.
- **Логирование — минимальное:** факт запроса/результата, без углубления в детали API.
- **Своей аутентификации нет.** Осознанно: ключ checko хранится только на сервере и
  клиенту не передаётся; этого достаточно для тестового задания.
- **Ошибки → HTTP** через единый `ExceptionHandler` и `ApiException` с фабриками
  (`invalidInput`, `auth`, `notFound`, `api`, `rateLimit`, `timeout`). В ответе
  `error.type` сохраняет имена: `InvalidInputException`→422, `AuthException`→401,
  `ResourceNotFoundException`→404, `CheckoApiException`→502, `RateLimitException`→429,
  `TimeoutException`→504. Формат: `{ "error": { "type", "message", "code" } }`.

## Как расширять

1. Новый ресурс checko → новый класс-стратегия (`implements ResourceInterface`) + DTO.
2. Новый тип ошибки → новое исключение + маппинг в `ExceptionHandler`.
3. Изменение транспорта (ретраи/таймауты) → правка только в слое Transport.

## Документация API

Swagger/OpenAPI: спецификация `app/openapi.yaml` поддерживается вручную; интерактивная Swagger UI — `/docs/`.

## Окружение разработки и демо-страница

- **Запуск проекта** — через Docker (`docker-compose.yml`): поднимает PHP, Valkey.
  Основные команды — в [`Makefile`](Makefile) (`make up`, `make run`, `make composer-install`,
  `make down` и т.д.).
- **Демо-страница** — отдельный веб-интерфейс (HTML + JS + Tailwind CSS + PHP), который
  обращается к `/api/v1/` и показывает результаты запросов к checko в браузере. Не часть ядра API,
  а демонстрационный слой поверх него.
