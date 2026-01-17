<?php

declare(strict_types=1);

namespace NksHub\NetteRuian\Response;

/**
 * Validated place (address) DTO
 */
final class ValidatedPlace
{
    public function __construct(
        public readonly float $confidence,
        public readonly ?string $regionId,
        public readonly ?string $regionName,
        public readonly int $municipalityId,
        public readonly string $municipalityName,
        public readonly ?int $municipalityPartId,
        public readonly ?string $municipalityPartName,
        public readonly ?string $streetName,
        public readonly ?string $cp,
        public readonly ?string $co,
        public readonly ?string $ce,
        public readonly int $zip,
        public readonly int $ruianId,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            confidence: (float) ($data['confidence'] ?? 0),
            regionId: $data['regionId'] ?? null,
            regionName: $data['regionName'] ?? null,
            municipalityId: (int) $data['municipalityId'],
            municipalityName: (string) $data['municipalityName'],
            municipalityPartId: isset($data['municipalityPartId']) ? (int) $data['municipalityPartId'] : null,
            municipalityPartName: $data['municipalityPartName'] ?? null,
            streetName: $data['streetName'] ?? null,
            cp: isset($data['cp']) ? (string) $data['cp'] : null,
            co: isset($data['co']) ? (string) $data['co'] : null,
            ce: isset($data['ce']) ? (string) $data['ce'] : null,
            zip: (int) $data['zip'],
            ruianId: (int) ($data['ruianId'] ?? $data['id'] ?? 0),
        );
    }

    /**
     * Get formatted full address
     */
    public function getFormattedAddress(): string
    {
        $parts = [];

        // Street and number
        if ($this->streetName !== null) {
            $parts[] = $this->streetName . ' ' . $this->getFormattedNumber();
        } else {
            $houseNumber = $this->getFormattedNumber();
            if ($houseNumber !== '') {
                $parts[] = $this->municipalityPartName ?? $this->municipalityName;
                $parts[] = $houseNumber;
            }
        }

        // Municipality
        $parts[] = $this->municipalityName;

        // ZIP
        $parts[] = (string) $this->zip;

        return implode(', ', array_filter($parts));
    }

    /**
     * Get formatted house number
     */
    public function getFormattedNumber(): string
    {
        $numbers = [];

        if ($this->cp !== null) {
            $numbers[] = $this->cp;
        }

        if ($this->co !== null) {
            $numbers[] = $this->co;
        }

        if ($this->ce !== null) {
            $numbers[] = 'ev.' . $this->ce;
        }

        return implode('/', $numbers);
    }
}
