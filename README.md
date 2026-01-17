# Nette RUIAN

Nette extension for [RUIAN API](https://ruian.fnx.io/) - Czech Address Registry (Registr územní identifikace, adres a nemovitostí).

## Requirements

- PHP 8.1+
- Nette 3.1+

## Installation

```bash
composer require nks-hub/nette-ruian
```

## Configuration

Register extension in your `config.neon`:

```neon
extensions:
    ruian: NksHub\NetteRuian\DI\RuianExtension

ruian:
    apiKey: 'your-api-key-here'
    cache:
        enabled: true      # Enable caching (default: true)
        ttl: 86400         # Cache TTL in seconds (default: 86400 = 24 hours)
```

### Getting API Key

Request your free API key at [ruian.fnx.io](https://ruian.fnx.io/). Free tier allows 1000 requests per hour.

## Usage

### Inject RuianClient

```php
use NksHub\NetteRuian\Client\RuianClient;

class AddressPresenter extends Nette\Application\UI\Presenter
{
    public function __construct(
        private RuianClient $ruianClient,
    ) {
        parent::__construct();
    }
}
```

### Validate Address

```php
use NksHub\NetteRuian\Response\ValidateResult;

// Validate by address components
$result = $this->ruianClient->validate([
    'municipalityName' => 'Praha',
    'street' => 'Kaprova',
    'cp' => '14',
]);

if ($result->isMatch()) {
    echo "Exact match found!";
    echo $result->place->getFormattedAddress();
} elseif ($result->isPossible()) {
    echo "Possible match with confidence: " . $result->place->confidence;
}

// Validate by RUIAN ID directly
$result = $this->ruianClient->validateByRuianId(21692912);
```

### Address Builder (Progressive Selection)

Build address step by step using cascading selectors:

```php
// Step 1: Get all regions (kraje)
$regions = $this->ruianClient->getRegions();
// Returns: Region[] with regionId, regionName

// Step 2: Get municipalities in a region
$municipalities = $this->ruianClient->getMunicipalities('CZ010');
// Returns: Municipality[] with municipalityId, municipalityName

// Step 3: Get streets in a municipality
$streets = $this->ruianClient->getStreets(554782);
// Returns: Street[] with streetName or streetLessPartName

// Step 4: Get address points on a street
$places = $this->ruianClient->getPlaces(554782, 'Kaprova');
// Returns: Place[] with cp, co, ce, zip, placeId
```

### Municipality Autocomplete (Typeahead)

Search municipalities by name for autocomplete/typeahead functionality:

```php
// Search municipalities starting with "Pra"
$results = $this->ruianClient->searchMunicipalities('Pra', 10);
// Returns: Municipality[] matching the query, max 10 results

// Results prioritize:
// 1. Names starting with query (Praha, Prachatice, ...)
// 2. Names containing query (Nová Praha, ...)

// Get all municipalities (cached for 7 days)
$allMunicipalities = $this->ruianClient->getAllMunicipalities();
// Returns: Municipality[] - all ~6300 Czech municipalities
```

### Combined Queries

Convenience methods for common use cases:

```php
// Find address by components (simpler than validate())
$result = $this->ruianClient->findAddress(
    municipalityName: 'Praha',
    street: 'Kaprova',
    cp: '14',
);

// Validate and get all places on the matched street
$data = $this->ruianClient->validateWithPlaces([
    'municipalityName' => 'Praha',
    'street' => 'Kaprova',
]);
// Returns: ['result' => ValidateResult, 'places' => Place[]]

// Get complete address hierarchy for a municipality
$hierarchy = $this->ruianClient->getAddressHierarchy(554782);
// Returns: ['region' => Region, 'municipality' => Municipality, 'streets' => Street[]]
```

### Response DTOs

#### ValidateResult

```php
$result->status;     // 'MATCH', 'POSSIBLE', 'NOT_FOUND', 'ERROR'
$result->message;    // Error message (if any)
$result->place;      // ValidatedPlace object (if found)

$result->isMatch();     // Exact match
$result->isPossible();  // Fuzzy match
$result->isFound();     // Match or possible
$result->isNotFound();  // No match
$result->isError();     // API error
```

#### ValidatedPlace

```php
$place->confidence;           // Match confidence (0.0 - 1.0)
$place->regionId;             // Region code (e.g., 'CZ010')
$place->regionName;           // Region name
$place->municipalityId;       // Municipality RUIAN ID
$place->municipalityName;     // Municipality name
$place->municipalityPartId;   // Municipality part RUIAN ID
$place->municipalityPartName; // Municipality part name
$place->streetName;           // Street name
$place->cp;                   // Descriptive number (cislo popisne)
$place->co;                   // Orientation number (cislo orientacni)
$place->ce;                   // Evidence number (cislo evidencni)
$place->zip;                  // Postal code
$place->ruianId;              // RUIAN address point ID

$place->getFormattedAddress();  // Full formatted address
$place->getFormattedNumber();   // Formatted house number (cp/co or ev.ce)
```

### Validation Parameters

| Parameter | Description |
|-----------|-------------|
| `municipalityName` | Municipality name |
| `municipalityId` | RUIAN municipality ID |
| `municipalityPartName` | Municipality part name |
| `municipalityPartId` | RUIAN municipality part ID |
| `zip` | Postal code |
| `street` | Street or municipality part name |
| `cp` | Descriptive number (cislo popisne) |
| `co` | Orientation number (cislo orientacni) |
| `ce` | Evidence number (cislo evidencni) |
| `ruianId` | Direct RUIAN address ID lookup |

### Caching

Caching is enabled by default to reduce API calls. Cache is stored using Nette's caching system.

```php
// Clear cache manually
$this->ruianClient->clearCache();
```

To disable caching:

```neon
ruian:
    apiKey: 'your-api-key'
    cache:
        enabled: false
```

### Exception Handling

```php
use NksHub\NetteRuian\Exception\RuianApiException;
use NksHub\NetteRuian\Exception\RuianAuthException;
use NksHub\NetteRuian\Exception\RuianRateLimitException;

try {
    $result = $this->ruianClient->validate([...]);
} catch (RuianAuthException $e) {
    // Invalid API key (HTTP 401)
} catch (RuianRateLimitException $e) {
    // Rate limit exceeded (HTTP 429)
} catch (RuianApiException $e) {
    // Other API errors
}
```

## API Rate Limits

- Free tier: 1000 requests/hour
- No SLA guarantees

## Credits

This package is a Nette wrapper for the [RUIAN API](https://ruian.fnx.io/) provided by [fnx.io](https://fnx.io/).

**Disclaimer:** This package is not affiliated with fnx.io or the Czech government. The RUIAN data is provided by the Czech Office for Surveying, Mapping and Cadastre (CUZK).

## License

MIT License. See [LICENSE](LICENSE) for details.
