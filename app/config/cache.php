<?php

declare(strict_types=1);

[$host, $port] = array_pad(explode(':', (string) env('REDIS_ADDR', 'valkey:6379'), 2), 2, '6379');

return [
    'ttl' => max(1, (int) env('CACHE_TTL', 3600)),
    'redis' => [
        'host' => $host,
        'port' => (int) $port,
        'password' => trim((string) env('REDIS_PASSWORD', '')) ?: null,
    ],
];
