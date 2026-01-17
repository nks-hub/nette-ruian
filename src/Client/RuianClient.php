<?php

declare(strict_types=1);

namespace NksHub\NetteRuian\Client;

use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\Utils\Json;
use NksHub\NetteRuian\Exception\RuianApiException;
use NksHub\NetteRuian\Exception\RuianAuthException;
use NksHub\NetteRuian\Exception\RuianRateLimitException;
use NksHub\NetteRuian\Response\Municipality;
use NksHub\NetteRuian\Response\Place;
use NksHub\NetteRuian\Response\Region;
use NksHub\NetteRuian\Response\Street;
use NksHub\NetteRuian\Response\ValidateResult;
use SensitiveParameter;

/**
 * RUIAN API Client
 *
 * Client for Czech Address Registry (RUIAN) API provided by fnx.io
 *
 * @see https://ruian.fnx.io/
 */
final readonly class RuianClient
{
    private const string BASE_URL = 'https://ruian.fnx.io/api/v1/ruian';
    private const string CACHE_NAMESPACE = 'NksHub.Ruian';
    private const string CACHE_KEY_ALL_MUNICIPALITIES = 'all_municipalities';

    private const int HTTP_OK = 200;
    private const int HTTP_UNAUTHORIZED = 401;
    private const int HTTP_UNPROCESSABLE = 422;
    private const int HTTP_RATE_LIMIT = 429;
    private const int HTTP_SERVER_ERROR = 500;

    private const int MUNICIPALITIES_CACHE_MULTIPLIER = 7;
    private const int MIN_SEARCH_LENGTH = 2;
    private const int HTTP_TIMEOUT = 30;

    private Cache $cache;

    public function __construct(
        #[SensitiveParameter]
        private string $apiKey,
        Storage $storage,
        private bool $cacheEnabled = true,
        private int $cacheTtl = 86400,
    ) {
        $this->cache = new Cache($storage, self::CACHE_NAMESPACE);
    }

    /**
     * Validate address and find matching entry in RUIAN
     *
     * @param array<string, mixed> $params Address parameters:
     *   - municipalityName: string - Municipality name
     *   - municipalityId: int - RUIAN municipality ID
     *   - municipalityPartName: string - Municipality part name
     *   - municipalityPartId: int - RUIAN municipality part ID
     *   - zip: int|string - Postal code
     *   - street: string - Street name
     *   - cp: string - Descriptive number (číslo popisné)
     *   - co: string - Orientation number (číslo orientační)
     *   - ce: string - Evidence number (číslo evidenční)
     *   - ruianId: int - Direct RUIAN address ID lookup
     */
    public function validate(array $params): ValidateResult
    {
        return ValidateResult::fromArray($this->request('validate', $params));
    }

    /**
     * Validate address by RUIAN ID
     */
    public function validateByRuianId(int $ruianId): ValidateResult
    {
        return $this->validate(['ruianId' => $ruianId]);
    }

    /**
     * Get all regions (kraje)
     *
     * @return list<Region>
     */
    public function getRegions(): array
    {
        $data = $this->request('build/regions', []);
        return array_map(
            static fn(array $item): Region => Region::fromArray($item),
            $data['data'] ?? [],
        );
    }

    /**
     * Get municipalities (obce) for a region
     *
     * @return list<Municipality>
     */
    public function getMunicipalities(string $regionId): array
    {
        $data = $this->request('build/municipalities', ['regionId' => $regionId]);
        return array_map(
            static fn(array $item): Municipality => Municipality::fromArray($item),
            $data['data'] ?? [],
        );
    }

    /**
     * Get streets for a municipality
     *
     * @return list<Street>
     */
    public function getStreets(int $municipalityId): array
    {
        $data = $this->request('build/streets', ['municipalityId' => $municipalityId]);
        return array_map(
            static fn(array $item): Street => Street::fromArray($item),
            $data['data'] ?? [],
        );
    }

    /**
     * Get places (address points) for a street
     *
     * @return list<Place>
     */
    public function getPlaces(int $municipalityId, string $streetName): array
    {
        $data = $this->request('build/places', [
            'municipalityId' => $municipalityId,
            'streetName' => $streetName,
        ]);
        return array_map(
            static fn(array $item): Place => Place::fromArray($item),
            $data['data'] ?? [],
        );
    }

    /**
     * Get all municipalities from all regions (for autocomplete)
     *
     * Results are cached for longer period (7 days by default) since municipality list rarely changes.
     *
     * @return list<Municipality>
     */
    public function getAllMunicipalities(): array
    {
        if ($this->cacheEnabled) {
            /** @var list<Municipality>|null $cached */
            $cached = $this->cache->load(self::CACHE_KEY_ALL_MUNICIPALITIES);
            if ($cached !== null) {
                return $cached;
            }
        }

        $municipalities = [];
        foreach ($this->getRegions() as $region) {
            $municipalities = [...$municipalities, ...$this->getMunicipalities($region->regionId)];
        }

        usort(
            $municipalities,
            static fn(Municipality $a, Municipality $b): int => strcmp($a->municipalityName, $b->municipalityName),
        );

        if ($this->cacheEnabled) {
            $this->cache->save(self::CACHE_KEY_ALL_MUNICIPALITIES, $municipalities, [
                Cache::Expire => $this->cacheTtl * self::MUNICIPALITIES_CACHE_MULTIPLIER,
            ]);
        }

        return $municipalities;
    }

    /**
     * Search municipalities by name prefix (for autocomplete/typeahead)
     *
     * @param positive-int $limit Maximum results to return
     * @return list<Municipality>
     */
    public function searchMunicipalities(string $query, int $limit = 10): array
    {
        $query = trim($query);
        if (mb_strlen($query) < self::MIN_SEARCH_LENGTH) {
            return [];
        }

        $queryLower = mb_strtolower($query);
        $startsWithResults = [];
        $containsResults = [];

        foreach ($this->getAllMunicipalities() as $municipality) {
            $nameLower = mb_strtolower($municipality->municipalityName);

            if (str_starts_with($nameLower, $queryLower)) {
                $startsWithResults[] = $municipality;
                if (count($startsWithResults) >= $limit) {
                    break;
                }
            } elseif (str_contains($nameLower, $queryLower)) {
                $containsResults[] = $municipality;
            }
        }

        return array_slice([...$startsWithResults, ...$containsResults], 0, $limit);
    }

    /**
     * Find full address by components (combined query)
     *
     * Convenience method that validates and returns full address details.
     */
    public function findAddress(
        string $municipalityName,
        ?string $street = null,
        ?string $cp = null,
        ?string $co = null,
        int|string|null $zip = null,
    ): ValidateResult {
        $params = ['municipalityName' => $municipalityName];

        if ($street !== null) {
            $params['street'] = $street;
        }
        if ($cp !== null) {
            $params['cp'] = $cp;
        }
        if ($co !== null) {
            $params['co'] = $co;
        }
        if ($zip !== null) {
            $params['zip'] = $zip;
        }

        return $this->validate($params);
    }

    /**
     * Get complete address hierarchy (region -> municipality -> streets)
     *
     * Returns all streets for a municipality with region context.
     *
     * @return array{region: Region|null, municipality: Municipality|null, streets: list<Street>}
     */
    public function getAddressHierarchy(int $municipalityId): array
    {
        $validateResult = $this->validate(['municipalityId' => $municipalityId]);

        $region = null;
        $municipality = null;

        if ($validateResult->place !== null) {
            $place = $validateResult->place;

            if ($place->regionId !== null && $place->regionName !== null) {
                $region = new Region($place->regionId, $place->regionName);
            }

            $municipality = new Municipality($place->municipalityId, $place->municipalityName);
        }

        return [
            'region' => $region,
            'municipality' => $municipality,
            'streets' => $this->getStreets($municipalityId),
        ];
    }

    /**
     * Validate and get full address details including all place options on the street
     *
     * @param array<string, mixed> $params
     * @return array{result: ValidateResult, places: list<Place>}
     */
    public function validateWithPlaces(array $params): array
    {
        $result = $this->validate($params);
        $places = [];

        if ($result->place?->streetName !== null) {
            $places = $this->getPlaces(
                $result->place->municipalityId,
                $result->place->streetName,
            );
        }

        return [
            'result' => $result,
            'places' => $places,
        ];
    }

    /**
     * Clear cache
     */
    public function clearCache(): void
    {
        $this->cache->clean([Cache::All => true]);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function request(string $endpoint, array $params): array
    {
        $cacheKey = $this->getCacheKey($endpoint, $params);

        if ($this->cacheEnabled) {
            /** @var array<string, mixed>|null $cached */
            $cached = $this->cache->load($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $response = $this->httpGet($this->buildUrl($endpoint, $params));
        $data = $this->parseResponse($response);

        if ($this->cacheEnabled) {
            $this->cache->save($cacheKey, $data, [
                Cache::Expire => $this->cacheTtl,
            ]);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildUrl(string $endpoint, array $params): string
    {
        return self::BASE_URL . '/' . $endpoint . '?' . http_build_query([
            ...$params,
            'apiKey' => $this->apiKey,
        ]);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function getCacheKey(string $endpoint, array $params): string
    {
        ksort($params);
        return md5($endpoint . '|' . serialize($params));
    }

    /**
     * @return array{code: int, body: string}
     */
    private function httpGet(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Accept: application/json',
                    'User-Agent: nks-hub/nette-ruian',
                ],
                'timeout' => self::HTTP_TIMEOUT,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            throw RuianApiException::connectionFailed();
        }

        $code = self::HTTP_SERVER_ERROR;
        if (isset($http_response_header[0])) {
            preg_match('/HTTP\/[\d.]+\s+(\d+)/', $http_response_header[0], $matches);
            $code = (int) ($matches[1] ?? self::HTTP_SERVER_ERROR);
        }

        return ['code' => $code, 'body' => $body];
    }

    /**
     * @param array{code: int, body: string} $response
     * @return array<string, mixed>
     */
    private function parseResponse(array $response): array
    {
        ['code' => $code, 'body' => $body] = $response;

        match ($code) {
            self::HTTP_UNAUTHORIZED => throw RuianAuthException::invalidApiKey(),
            self::HTTP_RATE_LIMIT => throw RuianRateLimitException::exceeded(),
            self::HTTP_UNPROCESSABLE => throw RuianApiException::missingParameters(),
            self::HTTP_OK => null,
            default => $code >= self::HTTP_SERVER_ERROR
                ? throw RuianApiException::serverError()
                : throw RuianApiException::unexpectedStatus($code),
        };

        try {
            /** @var array<string, mixed> */
            return Json::decode($body, forceArrays: true);
        } catch (\Throwable $e) {
            throw RuianApiException::invalidJson($e->getMessage());
        }
    }
}
