<?php

declare(strict_types=1);

namespace App\Dto;

use App\Exceptions\ApiException;

/**
 * Один immutable DTO организации.
 *
 * Вложенные блоки оставлены массивами с английскими ключами: так данных не
 * теряем, но не заводим по классу на каждую справочную пару.
 */
final readonly class ResponseCompanyDto implements DtoInterface
{
    /**
     * @param array<string, mixed>|null $status
     * @param array<string, mixed>|null $region
     * @param array<string, mixed>|null $legalForm
     * @param array<string, mixed>|null $ownershipForm
     * @param array<string, mixed>|null $okogu
     * @param array<string, mixed>|null $okato
     * @param array<string, mixed>|null $oktmo
     * @param array<string, mixed>|null $address
     * @param array<string, mixed>|null $primaryOkved
     * @param list<array<string, mixed>> $additionalOkveds
     * @param array<string, mixed>|null $capital
     * @param list<array<string, mixed>> $heads
     * @param array<string, mixed>|null $founders
     * @param array<string, mixed>|null $contacts
     * @param list<array<string, mixed>> $licenses
     * @param list<array<string, mixed>> $branches
     * @param list<array<string, mixed>> $representations
     * @param array<string, mixed>|null $registryHolder
     * @param array<string, bool> $flags
     * @param array<string, mixed> $additional
     */
    public function __construct(
        public string $ogrn,
        public string $inn,
        public ?string $kpp,
        public ?string $okpo,
        public ?string $registrationDate,
        public ?string $ogrnDate,
        public ?string $actualityDate,
        public string $nameShort,
        public ?string $nameFull,
        public ?string $nameEnglish,
        public ?array $status,
        public ?array $region,
        public ?array $legalForm,
        public ?array $ownershipForm,
        public ?array $okogu,
        public ?array $okato,
        public ?array $oktmo,
        public ?array $address,
        public ?array $primaryOkved,
        public array $additionalOkveds,
        public ?array $capital,
        public array $heads,
        public ?array $founders,
        public ?array $contacts,
        public array $licenses,
        public array $branches,
        public array $representations,
        public ?array $registryHolder,
        public array $flags,
        public array $additional,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $ogrn = self::text($data['ОГРН'] ?? null);
        $inn = self::text($data['ИНН'] ?? null);
        $name = self::text($data['НаимСокр'] ?? null);

        if ($ogrn === null || preg_match('/^\d{13}$|^\d{15}$/', $ogrn) !== 1) {
            throw ApiException::api('checko returned an invalid OGRN.');
        }

        if ($inn === null || preg_match('/^\d{10}$|^\d{12}$/', $inn) !== 1) {
            throw ApiException::api('checko returned an invalid INN.');
        }

        if ($name === null) {
            throw ApiException::api('checko response has no company name.');
        }

        $handled = [
            'ОГРН', 'ИНН', 'КПП', 'ОКПО', 'ДатаРег', 'ДатаОГРН', 'ДатаВып',
            'НаимСокр', 'НаимПолн', 'НаимАнгл', 'Статус', 'Регион', 'ЮрАдрес',
            'ОКВЭД', 'ОКВЭДДоп', 'ОКОПФ', 'ОКФС', 'ОКОГУ', 'ОКАТО', 'ОКТМО',
            'УстКап', 'Руковод', 'Учред', 'Контакты', 'Лиценз', 'Подразд',
            'ДержРеестрАО', 'НедобПост', 'ДисквЛица', 'МассРуковод', 'МассУчред',
            'НелегалФин', 'Санкции', 'СанкцУчр',
        ];

        $subdivisions = self::arr($data['Подразд'] ?? null);

        return new self(
            ogrn: $ogrn,
            inn: $inn,
            kpp: self::text($data['КПП'] ?? null),
            okpo: self::text($data['ОКПО'] ?? null),
            registrationDate: self::text($data['ДатаРег'] ?? null),
            ogrnDate: self::text($data['ДатаОГРН'] ?? null),
            actualityDate: self::text($data['ДатаВып'] ?? null),
            nameShort: $name,
            nameFull: self::text($data['НаимПолн'] ?? null),
            nameEnglish: self::text($data['НаимАнгл'] ?? null),
            status: self::codeName($data['Статус'] ?? null),
            region: self::codeName($data['Регион'] ?? null),
            legalForm: self::codeName($data['ОКОПФ'] ?? null),
            ownershipForm: self::codeName($data['ОКФС'] ?? null),
            okogu: self::codeName($data['ОКОГУ'] ?? null),
            okato: self::codeName($data['ОКАТО'] ?? null),
            oktmo: self::codeName($data['ОКТМО'] ?? null),
            address: self::address($data['ЮрАдрес'] ?? null),
            primaryOkved: self::okved($data['ОКВЭД'] ?? null),
            additionalOkveds: self::map($data['ОКВЭДДоп'] ?? null, self::okved(...)),
            capital: self::capital($data['УстКап'] ?? null),
            heads: self::map($data['Руковод'] ?? null, self::head(...)),
            founders: self::founders($data['Учред'] ?? null),
            contacts: self::contacts($data['Контакты'] ?? null),
            licenses: self::map($data['Лиценз'] ?? null, self::license(...)),
            branches: self::map($subdivisions['Филиал'] ?? null, self::subdivision(...)),
            representations: self::map($subdivisions['Представ'] ?? null, self::subdivision(...)),
            registryHolder: self::registryHolder($data['ДержРеестрАО'] ?? null),
            flags: [
                'unreliablePost' => (bool) ($data['НедобПост'] ?? false),
                'disqualifiedPersons' => (bool) ($data['ДисквЛица'] ?? false),
                'massHeads' => (bool) ($data['МассРуковод'] ?? false),
                'massFounders' => (bool) ($data['МассУчред'] ?? false),
                'illegalFinancing' => (bool) ($data['НелегалФин'] ?? false),
                'sanctions' => (bool) ($data['Санкции'] ?? false),
                'founderSanctions' => (bool) ($data['СанкцУчр'] ?? false),
            ],
            additional: array_diff_key($data, array_flip($handled)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @return array{code: string, name: string}|null */
    private static function codeName(mixed $data): ?array
    {
        $data = self::arr($data);
        $code = self::text($data['Код'] ?? null);
        $name = self::text($data['Наим'] ?? null);

        return $code === null && $name === null ? null : ['code' => $code ?? '', 'name' => $name ?? ''];
    }

    /** @return array<string, mixed>|null */
    private static function address(mixed $data): ?array
    {
        $data = self::arr($data);
        if ($data === []) {
            return null;
        }

        return [
            'settlement' => self::text($data['НасПункт'] ?? null),
            'address' => self::text($data['АдресРФ'] ?? null),
            'garId' => self::text($data['ИдГАР'] ?? null),
            'unreliable' => (bool) ($data['Недост'] ?? false),
            'massAddresses' => self::textList($data['МассАдрес'] ?? null),
        ];
    }

    /** @return array{code: string, name: string, version: string|null}|null */
    private static function okved(mixed $data): ?array
    {
        $base = self::codeName($data);

        return $base === null
            ? null
            : $base + ['version' => self::text(self::arr($data)['Версия'] ?? null)];
    }

    /** @return array<string, mixed>|null */
    private static function capital(mixed $data): ?array
    {
        $data = self::arr($data);
        if ($data === []) {
            return null;
        }

        return [
            'type' => self::text($data['Тип'] ?? null),
            'amount' => isset($data['Сумма']) ? (float) $data['Сумма'] : null,
        ];
    }

    /** @return array<string, mixed>|null */
    private static function head(mixed $data): ?array
    {
        $data = self::arr($data);
        $name = self::text($data['ФИО'] ?? null);
        if ($name === null) {
            return null;
        }

        return [
            'name' => $name,
            'inn' => self::text($data['ИНН'] ?? null),
            'positionType' => self::text($data['ВидДолжн'] ?? null),
            'position' => self::text($data['НаимДолжн'] ?? null),
            'unreliable' => (bool) ($data['Недост'] ?? false),
            'massHead' => (bool) ($data['МассРуковод'] ?? false),
            'disqualified' => (bool) ($data['ДисквЛицо'] ?? false),
            'recordDate' => self::text($data['ДатаЗаписи'] ?? null),
        ];
    }

    /** @return array<string, mixed>|null */
    private static function founders(mixed $data): ?array
    {
        $data = self::arr($data);
        if ($data === []) {
            return null;
        }

        return [
            'individuals' => self::listOfArrays($data['ФЛ'] ?? null),
            'russianCompanies' => self::listOfArrays($data['РосОрг'] ?? null),
            'foreignCompanies' => self::listOfArrays($data['ИнОрг'] ?? null),
            'funds' => self::listOfArrays($data['ПИФ'] ?? null),
            'state' => self::listOfArrays($data['РФ'] ?? null),
        ];
    }

    /** @return array<string, mixed>|null */
    private static function contacts(mixed $data): ?array
    {
        $data = self::arr($data);
        if ($data === []) {
            return null;
        }

        return [
            'phones' => self::textList($data['Тел'] ?? null),
            'emails' => self::textList($data['Емэйл'] ?? null),
            'website' => self::text($data['ВебСайт'] ?? null),
            'vk' => self::text($data['ВК'] ?? null),
            'max' => self::text($data['MAX'] ?? null),
        ];
    }

    /** @return array<string, mixed>|null */
    private static function license(mixed $data): ?array
    {
        $data = self::arr($data);
        if ($data === []) {
            return null;
        }

        return [
            'number' => self::text($data['Номер'] ?? null),
            'date' => self::text($data['Дата'] ?? null),
            'startDate' => self::text($data['ДатаНач'] ?? null),
            'endDate' => self::text($data['ДатаОконч'] ?? null),
            'issuer' => self::text($data['ЛицОрг'] ?? null),
            'activities' => self::textList($data['ВидДеят'] ?? null),
        ];
    }

    /** @return array<string, mixed>|null */
    private static function subdivision(mixed $data): ?array
    {
        $data = self::arr($data);
        if ($data === []) {
            return null;
        }

        return [
            'name' => self::text($data['НаимПолн'] ?? null),
            'kpp' => self::text($data['КПП'] ?? null),
            'address' => self::text($data['Адрес'] ?? null),
            'country' => self::text($data['Страна'] ?? null),
            'foreignAddress' => self::text($data['ИнАдрес'] ?? null),
        ];
    }

    /** @return array{ogrn: string|null, inn: string|null, name: string|null}|null */
    private static function registryHolder(mixed $data): ?array
    {
        $data = self::arr($data);
        if ($data === []) {
            return null;
        }

        return [
            'ogrn' => self::text($data['ОГРН'] ?? null),
            'inn' => self::text($data['ИНН'] ?? null),
            'name' => self::text($data['НаимПолн'] ?? null),
        ];
    }

    /** @param callable(array<string, mixed>): (array<string, mixed>|null) $map */
    private static function map(mixed $rows, callable $map): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            if (is_array($row) && ($mapped = $map($row)) !== null) {
                $result[] = $mapped;
            }
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private static function arr(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private static function text(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @return list<string> */
    private static function textList(mixed $value): array
    {
        return is_array($value) ? array_values(array_map('strval', array_filter($value, 'is_scalar'))) : [];
    }

    /** @return list<array<string, mixed>> */
    private static function listOfArrays(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }
}
