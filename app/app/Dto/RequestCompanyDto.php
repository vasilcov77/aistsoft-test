<?php

declare(strict_types=1);

namespace App\Dto;

use App\Exceptions\ApiException;

/**
 * Вход API: ровно один реквизит — ИНН (10/12 цифр) или ОГРН (13/15 цифр).
 */
final readonly class RequestCompanyDto
{
    private function __construct(
        public ?string $inn,
        public ?string $ogrn,
    ) {
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        $inn = self::clean($input['inn'] ?? null);
        $ogrn = self::clean($input['ogrn'] ?? null);

        if (($inn === null) === ($ogrn === null)) {
            throw ApiException::invalidInput('Provide exactly one of "inn" or "ogrn".');
        }

        if ($inn !== null && preg_match('/^\d{10}$|^\d{12}$/', $inn) !== 1) {
            throw ApiException::invalidInput('"inn" must contain 10 or 12 digits.');
        }

        if ($ogrn !== null && preg_match('/^\d{13}$|^\d{15}$/', $ogrn) !== 1) {
            throw ApiException::invalidInput('"ogrn" must contain 13 or 15 digits.');
        }

        return new self($inn, $ogrn);
    }

    /** @return array<string, string> */
    public function toParams(): array
    {
        return $this->inn !== null ? ['inn' => $this->inn] : ['ogrn' => $this->ogrn];
    }

    private static function clean(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
