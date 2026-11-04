<?php

declare(strict_types=1);

namespace Beziworld\InPost\Data;

use Carbon\CarbonImmutable;

/**
 * Tracking information of a parcel; events are ordered from the newest.
 */
final readonly class Tracking
{
    /**
     * @param list<array{status: string, originStatus: ?string, agency: ?string, location: ?string, at: ?CarbonImmutable}> $events
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $trackingNumber,
        public string $status,
        public ?string $service,
        public ?string $targetPoint,
        public array $events,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $events = [];

        foreach (is_array($data['tracking_details'] ?? null) ? $data['tracking_details'] : [] as $event) {
            if (is_array($event)) {
                $events[] = [
                    'status' => is_string($event['status'] ?? null) ? $event['status'] : '',
                    'originStatus' => is_string($event['origin_status'] ?? null) ? $event['origin_status'] : null,
                    'agency' => is_string($event['agency'] ?? null) ? $event['agency'] : null,
                    'location' => is_string($event['location'] ?? null) ? $event['location'] : null,
                    'at' => is_string($event['datetime'] ?? null) ? CarbonImmutable::parse($event['datetime']) : null,
                ];
            }
        }

        $attributes = is_array($data['custom_attributes'] ?? null) ? $data['custom_attributes'] : [];

        return new self(
            trackingNumber: is_string($data['tracking_number'] ?? null) ? $data['tracking_number'] : '',
            status: is_string($data['status'] ?? null) ? $data['status'] : 'unknown',
            service: is_string($data['service'] ?? null) ? $data['service'] : null,
            targetPoint: is_string($attributes['target_machine_id'] ?? null) ? $attributes['target_machine_id'] : null,
            events: $events,
            raw: $data,
        );
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isReadyToPickup(): bool
    {
        return $this->status === 'ready_to_pickup';
    }
}
