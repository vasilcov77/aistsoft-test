<?php

declare(strict_types=1);

namespace App\Client;

use App\Dto\DtoInterface;
use App\Transport\HttpTransport;
use App\Transport\ResourceInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Конвейер: валидация → параметры → кэш → checko → DTO → кэш.
 */
final class CheckoClient
{
    public function __construct(
        private readonly HttpTransport $transport,
        private readonly ResourceInterface $resource,
        private readonly CacheInterface $cache,
        private readonly int $cacheTtl,
    ) {
    }

    /** @param array<string, mixed> $input */
    public function fetch(array $input): DtoInterface
    {
        $params = $this->resource->buildParams($input);
        $key = $this->cacheKey($params);

        $cached = $this->cache->get($key);
        if (is_array($cached)) {
            return $this->resource->mapResponse($cached);
        }

        $data = $this->transport->request($this->resource, $params);
        $dto = $this->resource->mapResponse($data);

        // Кэшируем сырой data-блок только после успешного маппинга.
        $this->cache->set($key, $data, $this->cacheTtl);

        return $dto;
    }

    /** @param array<string, mixed> $params */
    private function cacheKey(array $params): string
    {
        ksort($params);

        return sprintf(
            'checko:v2:%s:%s',
            $this->resource->getPath(),
            hash('sha256', (string) json_encode($params, JSON_UNESCAPED_UNICODE)),
        );
    }
}
