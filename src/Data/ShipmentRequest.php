<?php

declare(strict_types=1);

namespace Beziworld\InPost\Data;

use Beziworld\InPost\Enums\Service;
use InvalidArgumentException;

/**
 * Input of POST /v1/organizations/{id}/shipments (simplified mode).
 *
 * Cash on delivery and insurance amounts are decimal PLN, e.g. 129.99.
 */
final readonly class ShipmentRequest
{
    /**
     * @param list<Parcel> $parcels
     * @param list<string> $additionalServices e.g. "sms", "email", "saturday".
     */
    public function __construct(
        public Service $service,
        public Peer $receiver,
        public array $parcels,
        public ?string $targetPoint = null,
        public ?string $sendingMethod = null,
        public ?string $dropoffPoint = null,
        public ?Peer $sender = null,
        public ?float $codAmount = null,
        public ?float $insuranceAmount = null,
        public ?string $reference = null,
        public ?string $comments = null,
        public array $additionalServices = [],
        public ?string $externalCustomerId = null,
        public string $currency = 'PLN',
    ) {
        if ($parcels === []) {
            throw new InvalidArgumentException('A shipment needs at least one parcel.');
        }

        if ($service->isLocker() && ($targetPoint === null || $targetPoint === '')) {
            throw new InvalidArgumentException('Locker shipments require a target point (e.g. "KRA010").');
        }

        if ($reference !== null && (mb_strlen($reference) < 3 || mb_strlen($reference) > 100)) {
            throw new InvalidArgumentException('Reference must contain between 3 and 100 characters.');
        }
    }

    /**
     * Shipment to a parcel locker; the receiver needs an e-mail and a phone number.
     */
    public static function toLocker(string $targetPoint, string $email, string $phone, string $template = 'small', ?string $reference = null, ?float $codAmount = null): self
    {
        return new self(
            service: Service::LockerStandard,
            receiver: new Peer(email: $email, phone: $phone),
            parcels: [Parcel::template($template)],
            targetPoint: $targetPoint,
            codAmount: $codAmount,
            insuranceAmount: $codAmount,
            reference: $reference,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $customAttributes = array_filter([
            'target_point' => $this->targetPoint,
            'sending_method' => $this->sendingMethod,
            'dropoff_point' => $this->dropoffPoint,
        ], static fn(?string $value): bool => $value !== null && $value !== '');

        $shipment = [
            'service' => $this->service->value,
            'receiver' => $this->receiver->toArray(),
            'parcels' => array_map(static fn(Parcel $parcel): array => $parcel->toArray(), $this->parcels),
        ];

        $optional = [
            'sender' => $this->sender?->toArray(),
            'custom_attributes' => $customAttributes === [] ? null : $customAttributes,
            'cod' => $this->codAmount !== null ? ['amount' => round($this->codAmount, 2), 'currency' => $this->currency] : null,
            'insurance' => $this->insuranceAmount !== null ? ['amount' => round($this->insuranceAmount, 2), 'currency' => $this->currency] : null,
            'reference' => $this->reference,
            'comments' => $this->comments,
            'additional_services' => $this->additionalServices === [] ? null : $this->additionalServices,
            'external_customer_id' => $this->externalCustomerId,
        ];

        return $shipment + array_filter($optional, static fn(mixed $value): bool => $value !== null);
    }
}
