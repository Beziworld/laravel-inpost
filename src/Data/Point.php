<?php

declare(strict_types=1);

namespace Beziworld\InPost\Data;

/**
 * Parcel locker or pickup point (Paczkomat, PaczkoPunkt).
 */
final readonly class Point
{
    /**
     * @param list<string> $types
     * @param list<string> $functions
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $name,
        public array $types,
        public string $status,
        public ?float $latitude,
        public ?float $longitude,
        public ?string $street,
        public ?string $buildingNumber,
        public ?string $postCode,
        public ?string $city,
        public ?string $description,
        public ?string $openingHours,
        public array $functions,
        public bool $paymentAvailable,
        public bool $open24h,
        public ?int $distance,
        public ?string $imageUrl,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $location = is_array($data['location'] ?? null) ? $data['location'] : [];
        $address = is_array($data['address_details'] ?? null) ? $data['address_details'] : [];
        $string = static fn(array $source, string $key): ?string => is_string($source[$key] ?? null) && $source[$key] !== '' ? $source[$key] : null;

        return new self(
            name: $string($data, 'name') ?? '',
            types: array_values(array_filter(is_array($data['type'] ?? null) ? $data['type'] : [], is_string(...))),
            status: $string($data, 'status') ?? 'Unknown',
            latitude: is_numeric($location['latitude'] ?? null) ? (float) $location['latitude'] : null,
            longitude: is_numeric($location['longitude'] ?? null) ? (float) $location['longitude'] : null,
            street: $string($address, 'street'),
            buildingNumber: $string($address, 'building_number'),
            postCode: $string($address, 'post_code'),
            city: $string($address, 'city'),
            description: $string($data, 'location_description'),
            openingHours: $string($data, 'opening_hours'),
            functions: array_values(array_filter(is_array($data['functions'] ?? null) ? $data['functions'] : [], is_string(...))),
            paymentAvailable: ($data['payment_available'] ?? false) === true,
            open24h: ($data['location_247'] ?? false) === true,
            distance: is_numeric($data['distance'] ?? null) ? (int) $data['distance'] : null,
            imageUrl: $string($data, 'image_url'),
            raw: $data,
        );
    }

    public function isOperating(): bool
    {
        return $this->status === 'Operating';
    }

    /**
     * Address in one line, e.g. "Marszałkowska 1, 00-001 Warszawa".
     */
    public function address(): string
    {
        return trim(trim(($this->street ?? '') . ' ' . ($this->buildingNumber ?? '')) . ', ' . trim(($this->postCode ?? '') . ' ' . ($this->city ?? '')), ', ');
    }
}
