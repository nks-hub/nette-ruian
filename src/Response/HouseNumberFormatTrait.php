<?php

declare(strict_types=1);

namespace NksHub\NetteRuian\Response;

/**
 * Shared house number formatting for address DTOs
 *
 * Requires properties: ?string $cp, ?string $co, ?string $ce
 */
trait HouseNumberFormatTrait
{
    /**
     * Get formatted house number
     */
    public function getFormattedNumber(): string
    {
        $parts = array_filter([
            $this->cp,
            $this->co,
            $this->ce !== null ? "ev.{$this->ce}" : null,
        ]);

        return implode('/', $parts);
    }
}
