<?php

declare(strict_types=1);

use Nette\Caching\Storages\MemoryStorage;
use NksHub\NetteRuian\Client\RuianClient;
use NksHub\NetteRuian\Exception\RuianException;
use NksHub\NetteRuian\Exception\RuianRateLimitException;
use NksHub\NetteRuian\Support\CzechCollator;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

/**
 * Client with a canned HTTP layer. $routes maps an endpoint path to a decoded body.
 *
 * @param array<string, mixed> $routes
 * @param list<string> $log requested URLs
 */
function fakeClient(array $routes, array &$log = []): RuianClient
{
    return new RuianClient(
        'test-key',
        new MemoryStorage(),
        cacheEnabled: false,
        httpGet: static function (string $url) use ($routes, &$log): array {
            $log[] = $url;
            $path = substr((string) parse_url($url, PHP_URL_PATH), strlen('/api/v1/ruian/'));
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $key = $path . (isset($query['regionId']) ? '?' . $query['regionId'] : '');

            return isset($routes[$key])
                ? ['code' => 200, 'body' => json_encode($routes[$key], JSON_THROW_ON_ERROR)]
                : ['code' => 422, 'body' => '{"status":"ERROR"}'];
        },
    );
}

$routes = [
    'build/regions' => ['data' => [
        ['regionId' => 'CZ010', 'regionName' => 'Hlavní město Praha'],
        ['regionId' => 'CZ064', 'regionName' => 'Jihomoravský kraj'],
    ]],
    'build/municipalities?CZ010' => ['data' => [
        ['municipalityId' => 554782, 'municipalityName' => 'Praha'],
    ]],
    'build/municipalities?CZ064' => ['data' => [
        ['municipalityId' => 582786, 'municipalityName' => 'Brno'],
        ['municipalityId' => 1, 'municipalityName' => 'Šlapanice'],
        ['municipalityId' => 2, 'municipalityName' => 'Zastávka'],
        ['municipalityId' => 3, 'municipalityName' => 'Řečkovice'],
        ['municipalityId' => 4, 'municipalityName' => 'Čebín'],
        ['municipalityId' => 5, 'municipalityName' => 'Hodonín'],
        ['municipalityId' => 6, 'municipalityName' => 'Chudčice'],
        ['municipalityId' => 7, 'municipalityName' => 'Židlochovice'],
        ['municipalityId' => 8, 'municipalityName' => 'Ivančice'],
    ]],
    'build/streets' => ['data' => [['streetName' => 'Kaprova']]],
];

// getAddressHierarchy fills region and municipality and never calls the validate endpoint
$log = [];
$hierarchy = fakeClient($routes, $log)->getAddressHierarchy(582786);
Assert::same('CZ064', $hierarchy['region']?->regionId);
Assert::same('Jihomoravský kraj', $hierarchy['region']?->regionName);
Assert::same('Brno', $hierarchy['municipality']?->municipalityName);
Assert::count(1, $hierarchy['streets']);
Assert::same([], array_filter($log, static fn(string $u): bool => str_contains($u, '/validate')));

// unknown municipality: nulls, no exception
$unknown = fakeClient($routes)->getAddressHierarchy(999);
Assert::null($unknown['region']);
Assert::null($unknown['municipality']);

// municipalities are sorted by Czech alphabet (Č after C, Ch after H, Ř after R, Š after S, Ž after Z)
$names = array_map(
    static fn($m): string => $m->municipalityName,
    fakeClient($routes)->getAllMunicipalities(),
);
Assert::same(
    ['Brno', 'Čebín', 'Hodonín', 'Chudčice', 'Ivančice', 'Praha', 'Řečkovice', 'Šlapanice', 'Zastávka', 'Židlochovice'],
    $names,
);

// search uses the same order
$found = fakeClient($routes)->searchMunicipalities('ice');
Assert::same(['Chudčice', 'Ivančice', 'Řečkovice', 'Šlapanice', 'Židlochovice'], array_map(static fn($m): string => $m->municipalityName, $found));

// the fallback comparison (no ext-intl) agrees on the same list
$shuffled = ['Židlochovice', 'Zastávka', 'Šlapanice', 'Řečkovice', 'Praha', 'Ivančice', 'Chudčice', 'Hodonín', 'Čebín', 'Brno'];
usort($shuffled, CzechCollator::compareFallback(...));
Assert::same($names, $shuffled);
Assert::same(0, CzechCollator::compareFallback('Brno', 'brno'));

// error mapping still works through the injected transport
$client = new RuianClient('k', new MemoryStorage(), httpGet: static fn(string $u): array => ['code' => 429, 'body' => '']);
Assert::exception(fn() => $client->getRegions(), RuianRateLimitException::class);

// RuianException::create returns the base exception and keeps the previous one
$e = RuianException::create('x', new RuntimeException('prev'));
Assert::same('prev', $e->getPrevious()?->getMessage());
