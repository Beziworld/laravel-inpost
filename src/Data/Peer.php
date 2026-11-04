<?php

declare(strict_types=1);

namespace Beziworld\InPost\Data;

/**
 * Sender or receiver of a shipment.
 *
 * Locker deliveries need the phone and e-mail of the receiver (the pickup
 * code is sent to them); courier deliveries need a name or company and an
 * address.
 */
final readonly class Peer
{
    public function __construct(
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $companyName = null,
        public ?string $name = null,
        public ?Address $address = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'company_name' => $this->companyName,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone !== null ? preg_replace('/[^\d+]/', '', $this->phone) : null,
            'address' => $this->address?->toArray(),
        ], static fn(mixed $value): bool => $value !== null);
    }
}
