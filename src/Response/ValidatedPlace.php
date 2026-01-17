<?php

declare(strict_types=1);

namespace NksHub\NetteRuian\Response;

/**
 * Validated place (address) DTO
 */
final readonly class ValidatedPlace
{
    public function __construct(
        public float $confidence,
        public ?string $regionId,
        public ?string $regionName,
        public int $municipalityId,
        public string $municipalityName,
        public ?int $municipalityPartId,
        public ?string $municipalityPartName,
        public ?string $streetName,
        public ?string $cp,
        public ?string $co,
        public ?string $ce,
        public int $zip,
        public int $ruianId,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
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

        if ($this->streetName !== null) {
            $parts[] = "{$this->streetName} {$this->getFormattedNumber()}";
        } else {
            $houseNumber = $this->getFormattedNumber();
            if ($houseNumber !== '') {
                $parts[] = $this->municipalityPartName ?? $this->municipalityName;
                $parts[] = $houseNumber;
            }
        }

        $parts[] = $this->municipalityName;
        $parts[] = (string) $this->zip;

        return implode(', ', array_filter($parts));
    }

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
