<?php

declare(strict_types=1);

namespace NksHub\NetteRuian\Response;

/**
 * Place (Address point) DTO
 */
final class Place
{
    public function __construct(
        public readonly ?string $cp,
        public readonly ?string $co,
        public readonly ?string $ce,
        public readonly int $zip,
        public readonly int $placeId,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            cp: isset($data['placeCp']) ? (string) $data['placeCp'] : null,
            co: isset($data['placeCo']) ? (string) $data['placeCo'] : null,
            ce: isset($data['placeCe']) ? (string) $data['placeCe'] : null,
            zip: (int) $data['placeZip'],
            placeId: (int) $data['placeId'],
        );
    }

    /**
     * Get formatted house number
     */
    public function getFormattedNumber(): string
    {
        $parts = [];

        if ($this->cp !== null) {
            $parts[] = $this->cp;
        }

        if ($this->co !== null) {
            $parts[] = $this->co;
        }

        if ($this->ce !== null) {
            $parts[] = 'ev.' . $this->ce;
        }

        return implode('/', $parts);
    }
}
