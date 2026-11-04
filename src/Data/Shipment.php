<?php

declare(strict_types=1);

namespace Beziworld\InPost\Data;

use Carbon\CarbonImmutable;

/**
 * Shipment resource returned by ShipX.
 *
 * Creation is asynchronous: a new shipment has status "created" and no
 * tracking number; it becomes "confirmed" (label available) shortly after.
 */
final readonly class Shipment
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public int $id,
        public string $status,
        public ?string $trackingNumber,
        public ?string $service,
        public ?string $reference,
        public ?string $targetPoint,
        public ?CarbonImmutable $createdAt,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $attributes = is_array($data['custom_attributes'] ?? null) ? $data['custom_attributes'] : [];

        return new self(
            id: is_numeric($data['id'] ?? null) ? (int) $data['id'] : 0,
            status: is_string($data['status'] ?? null) ? $data['status'] : 'unknown',
            trackingNumber: is_string($data['tracking_number'] ?? null) ? $data['tracking_number'] : null,
            service: is_string($data['service'] ?? null) ? $data['service'] : null,
            reference: is_string($data['reference'] ?? null) ? $data['reference'] : null,
            targetPoint: is_string($attributes['target_point'] ?? null) ? $attributes['target_point'] : null,
            createdAt: is_string($data['created_at'] ?? null) ? CarbonImmutable::parse($data['created_at']) : null,
            raw: $data,
        );
    }

    /**
     * The label can be downloaded.
     */
    public function isConfirmed(): bool
    {
        return ! in_array($this->status, ['created', 'offers_prepared', 'offer_selected'], true);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['created', 'offers_prepared'], true);
    }
}
