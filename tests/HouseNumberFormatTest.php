<?php

declare(strict_types=1);

namespace NksHub\NetteRuian\Tests;

use NksHub\NetteRuian\Response\Place;
use NksHub\NetteRuian\Response\ValidatedPlace;

require __DIR__ . '/../src/Response/HouseNumberFormatTrait.php';
require __DIR__ . '/../src/Response/Place.php';
require __DIR__ . '/../src/Response/ValidatedPlace.php';

/**
 * Test that HouseNumberFormatTrait works identically in both Place and ValidatedPlace.
 *
 * Run: php tests/HouseNumberFormatTest.php
 */

$pass = 0;
$fail = 0;

function assert_eq(mixed $expected, mixed $actual, string $label): void
{
    global $pass, $fail;
    if ($expected === $actual) {
        $pass++;
    } else {
        $fail++;
        echo "FAIL: {$label}\n  Expected: " . var_export($expected, true) . "\n  Got:      " . var_export($actual, true) . "\n";
    }
}

// --- Place::getFormattedNumber via trait ---

$place1 = new Place(cp: '123', co: '4', ce: null, zip: 10000, placeId: 1);
assert_eq('123/4', $place1->getFormattedNumber(), 'Place: cp+co');

$place2 = new Place(cp: '123', co: null, ce: null, zip: 10000, placeId: 2);
assert_eq('123', $place2->getFormattedNumber(), 'Place: cp only');

$place3 = new Place(cp: null, co: null, ce: '42', zip: 10000, placeId: 3);
assert_eq('ev.42', $place3->getFormattedNumber(), 'Place: ce only');

$place4 = new Place(cp: null, co: null, ce: null, zip: 10000, placeId: 4);
assert_eq('', $place4->getFormattedNumber(), 'Place: all null');

$place5 = new Place(cp: '100', co: '5', ce: '7', zip: 10000, placeId: 5);
assert_eq('100/5/ev.7', $place5->getFormattedNumber(), 'Place: all three');

// --- ValidatedPlace::getFormattedNumber via trait ---

$vp1 = new ValidatedPlace(
    confidence: 1.0, regionId: 'CZ01', regionName: 'Praha',
    municipalityId: 1, municipalityName: 'Praha', municipalityPartId: null,
    municipalityPartName: null, streetName: null,
    cp: '123', co: '4', ce: null, zip: 10000, ruianId: 1,
);
assert_eq('123/4', $vp1->getFormattedNumber(), 'ValidatedPlace: cp+co');

$vp2 = new ValidatedPlace(
    confidence: 1.0, regionId: 'CZ01', regionName: 'Praha',
    municipalityId: 1, municipalityName: 'Praha', municipalityPartId: null,
    municipalityPartName: null, streetName: null,
    cp: null, co: null, ce: '42', zip: 10000, ruianId: 2,
);
assert_eq('ev.42', $vp2->getFormattedNumber(), 'ValidatedPlace: ce only');

$vp3 = new ValidatedPlace(
    confidence: 1.0, regionId: 'CZ01', regionName: 'Praha',
    municipalityId: 1, municipalityName: 'Praha', municipalityPartId: null,
    municipalityPartName: null, streetName: null,
    cp: null, co: null, ce: null, zip: 10000, ruianId: 3,
);
assert_eq('', $vp3->getFormattedNumber(), 'ValidatedPlace: all null');

// --- Verify both classes produce identical output ---

$placeCheck = new Place(cp: '200', co: '10', ce: null, zip: 15000, placeId: 99);
$vpCheck = new ValidatedPlace(
    confidence: 0.9, regionId: null, regionName: null,
    municipalityId: 5, municipalityName: 'Brno', municipalityPartId: null,
    municipalityPartName: null, streetName: null,
    cp: '200', co: '10', ce: null, zip: 15000, ruianId: 99,
);
assert_eq(
    $placeCheck->getFormattedNumber(),
    $vpCheck->getFormattedNumber(),
    'Place and ValidatedPlace produce identical output',
);

// --- Summary ---
echo "\n" . ($pass + $fail) . " tests: {$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
