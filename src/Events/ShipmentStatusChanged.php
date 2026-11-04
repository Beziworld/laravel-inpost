<?php

declare(strict_types=1);

namespace Beziworld\InPost\Events;

/**
 * Dispatched for every webhook event from ShipX (shipment_confirmed, shipment_status_changed, ...).
 *
 * ShipX webhooks are not signed. When the change matters (e.g. marking an
 * order as delivered), confirm it with InPost::shipment($shipmentId).
 */
final readonly class ShipmentStatusChanged
{
    /**
     * @param array<array-key, mixed> $payload
     */
    public function __construct(
        public string $event,
        public int $shipmentId,
        public ?string $status,
        public ?string $trackingNumber,
        public array $payload = [],
    ) {}

    public function isConfirmed(): bool
    {
        return $this->event === 'shipment_confirmed';
    }
}
