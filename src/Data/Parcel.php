<?php

declare(strict_types=1);

namespace Beziworld\InPost\Data;

use InvalidArgumentException;

/**
 * A parcel described by a template (locker sizes A, B, C) or by dimensions and weight.
 */
final readonly class Parcel
{
    private const array TEMPLATES = ['small', 'medium', 'large', 'xlarge', 'letter_a', 'letter_b', 'letter_c'];

    private function __construct(
        public ?string $template,
        public ?int $length,
        public ?int $width,
        public ?int $height,
        public ?float $weight,
        public ?string $id,
        public bool $nonStandard,
    ) {}

    /**
     * Template parcel: "small" (A, 8 cm), "medium" (B, 19 cm), "large" (C, 41 cm), "xlarge" (c2c only) or letter_a/b/c.
     */
    public static function template(string $template, ?string $id = null): self
    {
        if (! in_array($template, self::TEMPLATES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown InPost parcel template "%s".', $template));
        }

        return new self($template, null, null, null, null, $id, false);
    }

    /**
     * Parcel with dimensions in millimetres and weight in kilograms.
     */
    public static function dimensions(int $length, int $width, int $height, float $weight, ?string $id = null, bool $nonStandard = false): self
    {
        if ($length <= 0 || $width <= 0 || $height <= 0 || $weight <= 0) {
            throw new InvalidArgumentException('Parcel dimensions and weight must be positive.');
        }

        return new self(null, $length, $width, $height, $weight, $id, $nonStandard);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $parcel = $this->template !== null
            ? ['template' => $this->template]
            : [
                'dimensions' => ['length' => $this->length, 'width' => $this->width, 'height' => $this->height, 'unit' => 'mm'],
                'weight' => ['amount' => $this->weight, 'unit' => 'kg'],
            ];

        if ($this->id !== null) {
            $parcel['id'] = $this->id;
        }

        if ($this->nonStandard) {
            $parcel['is_non_standard'] = true;
        }

        return $parcel;
    }
}
