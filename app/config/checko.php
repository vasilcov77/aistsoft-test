<?php

declare(strict_types=1);

return [
    'base_url' => rtrim((string) env('CHECKO_BASE_URL', 'https://api.checko.ru/v2'), '/'),
    'token' => trim((string) env('TOKEN', '')),
    'timeout' => max(1, (int) env('CHECKO_TIMEOUT', 10)),
    'retries' => max(1, (int) env('CHECKO_RETRIES', 3)),
];
