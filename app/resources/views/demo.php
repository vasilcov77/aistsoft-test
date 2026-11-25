<?php

declare(strict_types=1);

/** Демо-страница. Данные берутся только из /api/v1. */
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>checko / досье — тестовый стенд API</title>
    <meta name="description" content="Демонстрационный интерфейс для проверки REST API над checko.ru.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Golos Text"', 'system-ui', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
                    },
                    colors: {
                        ink: '#081211', panel: '#0d1a18', panel2: '#11211e', line: '#203a34',
                        paper: '#ece7d9', muted: '#91a69e', brass: '#d6a84b', mint: '#82c09a', stamp: '#d95c4a',
                    },
                },
            },
        };
    </script>
    <link rel="stylesheet" href="/assets/demo.css">
</head>
<body class="min-h-screen bg-ink font-sans text-paper antialiased">
    <div class="page-grid" aria-hidden="true"></div>

    <header class="relative z-10 border-b border-line/80">
        <div class="mx-auto flex max-w-[1400px] flex-col gap-5 px-5 py-6 sm:px-8 lg:flex-row lg:items-end lg:justify-between">
            <a class="group inline-flex items-baseline gap-3" href="/demo" aria-label="Ч checko/досье REST API TEST BENCH — на демо-страницу">
                <span class="brand-mark">Ч</span>
                <span class="flex flex-col">
                    <span class="wordmark"><span class="text-muted">checko</span><span class="text-brass">/</span>досье</span>
                    <span class="mt-1 font-mono text-[11px] tracking-[0.16em] text-muted">REST API TEST BENCH</span>
                </span>
            </a>
            <div class="flex flex-wrap items-center gap-3 font-mono text-[11px]">
                <a class="chip-link" href="/docs/" target="_blank" rel="noopener">Swagger UI</a>
                <a class="chip-link" href="/api/v1/health" target="_blank" rel="noopener">/api/v1/health</a>
                <span id="health-pill" class="health-pill" data-state="pending" aria-live="polite">
                    <span class="health-dot"></span><span id="health-text">проверяю API</span>
                </span>
            </div>
        </div>
    </header>

    <main class="relative z-10 mx-auto grid max-w-[1400px] gap-6 px-5 py-6 sm:px-8 lg:grid-cols-[minmax(300px,380px)_minmax(0,1fr)] lg:gap-8 lg:py-8">
        <section class="query-panel" aria-labelledby="query-title">
            <div class="flex items-start justify-between gap-4 border-b border-line pb-4">
                <div>
                    <p class="section-kicker">Проверка контрагента</p>
                    <h1 id="query-title" class="mt-1 text-2xl font-semibold leading-tight">Запрос в ЕГРЮЛ</h1>
                </div>
                <span class="serial">v1.0</span>
            </div>

            <form id="search-form" class="mt-5" novalidate>
                <fieldset>
                    <legend class="sr-only">Тип реквизита</legend>
                    <div class="segmented" role="tablist" aria-label="Тип реквизита">
                        <button type="button" class="segment is-active" data-mode="inn" role="tab" aria-selected="true">ИНН</button>
                        <button type="button" class="segment" data-mode="ogrn" role="tab" aria-selected="false">ОГРН</button>
                    </div>
                </fieldset>

                <label for="identifier" class="mt-5 block text-sm font-medium text-paper">Реквизит организации</label>
                <div class="input-wrap mt-2">
                    <span id="input-prefix" class="input-prefix font-mono">ИНН</span>
                    <input id="identifier" name="identifier" type="text" inputmode="numeric" autocomplete="off"
                           spellcheck="false" maxlength="15" class="identifier-input font-mono"
                           placeholder="7707083893" aria-describedby="identifier-help">
                </div>
                <p id="identifier-help" class="mt-2 min-h-5 text-xs leading-5 text-muted">Введите 10 или 12 цифр ИНН.</p>
                <p id="client-error" class="mt-1 hidden text-xs text-stamp" role="alert"></p>

                <button id="submit-button" type="submit" class="submit-button mt-4 w-full">
                    <span id="submit-label">Получить досье</span>
                    <span id="submit-spinner" class="hidden h-4 w-4 animate-spin rounded-full border-2 border-ink/30 border-t-ink" aria-hidden="true"></span>
                </button>
            </form>

            <div class="mt-6 border-t border-line pt-5">
                <p class="section-kicker">Примеры</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" class="example" data-mode="inn" data-value="7707083893">Сбербанк</button>
                    <button type="button" class="example" data-mode="inn" data-value="7736050003">Газпром</button>
                    <button type="button" class="example" data-mode="inn" data-value="0000000000">Не найдено</button>
                    <button type="button" class="example" data-mode="inn" data-value="12345">Ошибка ввода</button>
                </div>
            </div>

            <div class="mt-6 border-t border-line pt-5">
                <p class="section-kicker">Текущий запрос</p>
                <div class="request-line mt-3">
                    <span class="method">GET</span>
                    <code id="request-url" class="min-w-0 flex-1 truncate">/api/v1/company?inn=</code>
                </div>
            </div>

            <p class="mt-6 border-t border-line pt-4 text-xs leading-5 text-muted">
                Демо-страница ходит только в собственный API. Ключ checko хранится на сервере.
            </p>
        </section>

        <section class="result-panel" aria-labelledby="result-title" aria-live="polite">
            <div class="result-topline">
                <div class="flex items-center gap-3">
                    <span class="section-kicker">Результат</span>
                    <span id="response-meta" class="response-meta hidden"></span>
                </div>
                <div id="view-switch" class="segmented hidden" role="tablist" aria-label="Формат результата">
                    <button type="button" class="segment is-active" data-view="card" role="tab" aria-selected="true">Карточка</button>
                    <button type="button" class="segment" data-view="json" role="tab" aria-selected="false">JSON</button>
                </div>
            </div>

            <div id="empty-state" class="empty-state">
                <div class="empty-stamp" aria-hidden="true">ДОСЬЕ</div>
                <h2 id="result-title" class="mt-8 text-2xl font-semibold">Введите реквизит организации</h2>
                <p class="mt-3 max-w-md text-sm leading-6 text-muted">
                    Данные придут из checko.ru через серверный API. Можно посмотреть карточку или исходный JSON
                    и проверить ошибки валидации, лимиты и недоступность внешнего сервиса.
                </p>
                <div class="mt-8 grid gap-3 text-left sm:grid-cols-3">
                    <div class="empty-note"><span class="note-mark"></span><span>Реквизит проверяется до запроса</span></div>
                    <div class="empty-note"><span class="note-mark"></span><span>Ответ кэшируется на 1 час</span></div>
                    <div class="empty-note"><span class="note-mark"></span><span>Ошибки приходят в едином формате</span></div>
                </div>
            </div>

            <div id="loading-state" class="loading-state hidden" aria-hidden="true">
                <div class="skeleton skeleton-title"></div>
                <div class="skeleton skeleton-short"></div>
                <div class="mt-8 grid gap-3 sm:grid-cols-2">
                    <div class="skeleton skeleton-box"></div><div class="skeleton skeleton-box"></div>
                    <div class="skeleton skeleton-box"></div><div class="skeleton skeleton-box"></div>
                </div>
                <p class="mt-8 font-mono text-xs text-muted">запрос к checko.ru…</p>
            </div>

            <div id="error-state" class="error-state hidden" role="alert">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="section-kicker text-stamp">Запрос не выполнен</p>
                        <p id="error-code" class="mt-3 font-mono text-6xl font-semibold leading-none text-stamp">422</p>
                    </div>
                    <span id="error-type" class="serial serial-error">InvalidInputException</span>
                </div>
                <h2 id="error-message" class="mt-6 max-w-2xl text-xl font-semibold leading-8">Сообщение</h2>
                <p id="error-hint" class="mt-3 max-w-2xl text-sm leading-6 text-muted"></p>
                <button type="button" class="example mt-6" data-action="retry">Повторить запрос</button>
            </div>

            <div id="result-state" class="hidden">
                <div id="card-view"></div>
                <div id="json-view" class="hidden">
                    <div class="json-toolbar">
                        <span class="font-mono text-xs text-muted">application/json</span>
                        <button id="copy-json" type="button" class="toolbar-button">Скопировать</button>
                    </div>
                    <pre id="json-code" class="json-code"><code></code></pre>
                </div>
            </div>
        </section>
    </main>

    <footer class="relative z-10 mx-auto flex max-w-[1400px] flex-col gap-2 border-t border-line/70 px-5 py-5 text-xs text-muted sm:flex-row sm:items-center sm:justify-between sm:px-8">
        <span>checko / досье — демонстрационный слой поверх REST API.</span>
        <span class="font-mono"><?= date('Y') ?> · PHP · Tailwind CSS · vanilla JS</span>
    </footer>

    <script src="/assets/demo.js" defer></script>
</body>
</html>
