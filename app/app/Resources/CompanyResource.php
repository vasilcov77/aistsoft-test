<?php

declare(strict_types=1);

namespace App\Resources;

use App\Dto\RequestCompanyDto;
use App\Dto\ResponseCompanyDto;
use App\Transport\ResourceInterface;

/**
 * checko /company — организация по ИНН или ОГРН.
 */
final class CompanyResource implements ResourceInterface
{
    public function getPath(): string
    {
        return 'company';
    }

    public function buildParams(array $input): array
    {
        return RequestCompanyDto::fromArray($input)->toParams();
    }

    public function mapResponse(array $data): ResponseCompanyDto
    {
        return ResponseCompanyDto::fromArray($data);
    }
}
