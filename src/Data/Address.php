<?php

declare(strict_types=1);

namespace Beziworld\InPost\Data;

/**
 * Postal address of a sender or receiver.
 */
final readonly class Address
{
    public function __construct(
        public string $street,
        public string $buildingNumber,
        public string $city,
        public string $postCode,
        public string $countryCode = 'PL',
    ) {}

    /**
     * @return array{street: string, building_number: string, city: string, post_code: string, country_code: string}
     */
    public function toArray(): array
    {
        return [
            'street' => $this->street,
            'building_number' => $this->buildingNumber,
            'city' => $this->city,
            'post_code' => $this->postCode,
            'country_code' => strtoupper($this->countryCode),
        ];
    }
}
