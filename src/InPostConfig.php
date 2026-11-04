<?php

declare(strict_types=1);

namespace Beziworld\InPost;

/**
 * Immutable view of the "inpost" configuration file.
 *
 * Credentials are optional: points, tracking and statuses are public
 * endpoints, so the client works for them without a token.
 */
final readonly class InPostConfig
{
    public const string PRODUCTION_URL = 'https://api-shipx-pl.easypack24.net';

    public const string SANDBOX_URL = 'https://sandbox-api-shipx-pl.easypack24.net';

    public function __construct(
        #[\SensitiveParameter]
        public ?string $token = null,
        public ?string $organizationId = null,
        public bool $sandbox = true,
        public int $timeout = 20,
        public ?string $geowidgetToken = null,
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            token: self::string($config['token'] ?? null),
            organizationId: self::string($config['organization_id'] ?? null),
            sandbox: (bool) ($config['sandbox'] ?? true),
            timeout: is_numeric($config['timeout'] ?? null) && (int) $config['timeout'] > 0 ? (int) $config['timeout'] : 20,
            geowidgetToken: self::string($config['geowidget_token'] ?? null),
        );
    }

    public function apiUrl(): string
    {
        return $this->sandbox ? self::SANDBOX_URL : self::PRODUCTION_URL;
    }

    private static function string(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
