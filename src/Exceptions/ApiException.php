<?php

declare(strict_types=1);

namespace Beziworld\InPost\Exceptions;

use Throwable;

/**
 * Raised when ShipX rejects a request or cannot be reached.
 *
 * error() returns the ShipX error key (e.g. "validation_failed",
 * "invalid_action", "token_invalid"), details() the field level errors.
 */
final class ApiException extends InPostException
{
    /**
     * @param array<array-key, mixed> $details
     */
    public function __construct(
        string $message,
        private readonly ?int $httpStatus = null,
        private readonly ?string $error = null,
        private readonly array $details = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    public static function fromResponse(array $payload, int $httpStatus): self
    {
        $error = is_string($payload['error'] ?? null) ? $payload['error'] : null;
        $description = $payload['message'] ?? $payload['description'] ?? null;
        $details = is_array($payload['details'] ?? null) ? $payload['details'] : [];

        $fields = [];

        foreach ($details as $field => $messages) {
            $fields[] = $field . ': ' . (is_array($messages) ? implode(', ', array_filter($messages, is_string(...))) : (is_string($messages) ? $messages : ''));
        }

        $message = sprintf(
            'InPost error%s: %s%s',
            $error !== null ? ' ' . $error : '',
            is_string($description) && $description !== '' ? $description : sprintf('request failed with HTTP status %d', $httpStatus),
            $fields === [] ? '' : ' (' . implode('; ', $fields) . ')',
        );

        return new self($message, $httpStatus, $error, $details);
    }

    public static function transport(Throwable $previous): self
    {
        return new self('Could not connect to InPost: ' . $previous->getMessage(), previous: $previous);
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function details(): array
    {
        return $this->details;
    }
}
