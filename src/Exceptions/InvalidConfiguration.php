<?php

declare(strict_types=1);

namespace Beziworld\InPost\Exceptions;

/**
 * Raised when an operation needs credentials that are not configured.
 */
final class InvalidConfiguration extends InPostException
{
    public static function missing(string $key, string $env): self
    {
        return new self(sprintf('InPost "%s" is not configured. Set %s in the environment.', $key, $env));
    }
}
