<?php

declare(strict_types=1);

namespace App\Transport;

use App\Dto\DtoInterface;

/**
 * Стратегия одного эндпоинта checko.
 */
interface ResourceInterface
{
    public function getPath(): string;

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function buildParams(array $input): array;

    /**
     * @param array<string, mixed> $data
     */
    public function mapResponse(array $data): DtoInterface;
}
