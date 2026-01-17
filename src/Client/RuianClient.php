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

/**
 * RUIAN API Client
 *
 * Client for Czech Address Registry (RUIAN) API provided by fnx.io
 *
 * @see https://ruian.fnx.io/
 */
class RuianClient
{
    private const BASE_URL = 'https://ruian.fnx.io/api/v1/ruian';

    private Cache $cache;

    public function __construct(
        private readonly string $apiKey,
        Storage $storage,
        private readonly bool $cacheEnabled = true,
        private readonly int $cacheTtl = 86400,
    ) {
        $this->cache = new Cache($storage, 'NksHub.Ruian');
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
        $data = $this->request('validate', $params);
        return ValidateResult::fromArray($data);
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
     * @return Region[]
     */
    public function getRegions(): array
    {
        $data = $this->request('build/regions', []);
        return array_map(
            fn(array $item) => Region::fromArray($item),
            $data['data'] ?? [],
        );
    }

    /**
     * Get municipalities (obce) for a region
     *
     * @return Municipality[]
     */
    public function getMunicipalities(string $regionId): array
    {
        $data = $this->request('build/municipalities', ['regionId' => $regionId]);
        return array_map(
            fn(array $item) => Municipality::fromArray($item),
            $data['data'] ?? [],
        );
    }

    /**
     * Get streets for a municipality
     *
     * @return Street[]
     */
    public function getStreets(int $municipalityId): array
    {
        $data = $this->request('build/streets', ['municipalityId' => $municipalityId]);
        return array_map(
            fn(array $item) => Street::fromArray($item),
            $data['data'] ?? [],
        );
    }

    /**
     * Get places (address points) for a street
     *
     * @return Place[]
     */
    public function getPlaces(int $municipalityId, string $streetName): array
    {
        $data = $this->request('build/places', [
            'municipalityId' => $municipalityId,
            'streetName' => $streetName,
        ]);
        return array_map(
            fn(array $item) => Place::fromArray($item),
            $data['data'] ?? [],
        );
    }

    /**
     * Clear cache
     */
    public function clearCache(): void
    {
        $this->cache->clean([Cache::All => true]);
    }

    /**
     * Make API request
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function request(string $endpoint, array $params): array
    {
        $cacheKey = $this->getCacheKey($endpoint, $params);

        if ($this->cacheEnabled) {
            $cached = $this->cache->load($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $url = $this->buildUrl($endpoint, $params);
        $response = $this->httpGet($url);
        $data = $this->parseResponse($response);

        if ($this->cacheEnabled) {
            $this->cache->save($cacheKey, $data, [
                Cache::Expire => $this->cacheTtl,
            ]);
        }

        return $data;
    }

    private function buildUrl(string $endpoint, array $params): string
    {
        $params['apiKey'] = $this->apiKey;
        return self::BASE_URL . '/' . $endpoint . '?' . http_build_query($params);
    }

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
                'timeout' => 30,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            throw new RuianApiException('Failed to connect to RUIAN API');
        }

        // Get HTTP status code from headers
        $code = 500;
        if (isset($http_response_header[0])) {
            preg_match('/HTTP\/\d+\.?\d*\s+(\d+)/', $http_response_header[0], $matches);
            $code = (int) ($matches[1] ?? 500);
        }

        return ['code' => $code, 'body' => $body];
    }

    /**
     * @param array{code: int, body: string} $response
     * @return array<string, mixed>
     */
    private function parseResponse(array $response): array
    {
        $code = $response['code'];
        $body = $response['body'];

        if ($code === 401) {
            throw new RuianAuthException('Invalid API key');
        }

        if ($code === 429) {
            throw new RuianRateLimitException('Rate limit exceeded (1000 requests/hour)');
        }

        if ($code === 422) {
            throw new RuianApiException('Missing required parameters');
        }

        if ($code >= 500) {
            throw new RuianApiException('RUIAN API server error');
        }

        if ($code !== 200) {
            throw new RuianApiException('Unexpected HTTP status: ' . $code);
        }

        try {
            return Json::decode($body, forceArrays: true);
        } catch (\Throwable $e) {
            throw new RuianApiException('Invalid JSON response: ' . $e->getMessage());
        }
    }
}
