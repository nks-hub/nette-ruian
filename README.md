# Nette RUIAN

[![Latest Stable Version](https://poser.pugx.org/nks-hub/nette-ruian/v)](https://packagist.org/packages/nks-hub/nette-ruian)
[![Total Downloads](https://poser.pugx.org/nks-hub/nette-ruian/downloads)](https://packagist.org/packages/nks-hub/nette-ruian)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](https://php.net/)
[![Nette Version](https://img.shields.io/badge/nette-%5E3.1-blue.svg)](https://nette.org/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Nette DI extension and PHP client for the [RUIAN API](https://ruian.fnx.io/), the Czech address registry (Registr územní identifikace, adres a nemovitostí).

## Features

- ✅ **Address validation** - Validate by address components or directly by RUIAN ID, with match confidence
- 🏗️ **Address builder** - Progressive selection region → municipality → street → address point
- 🔎 **Municipality autocomplete** - Typeahead search across all ~6300 Czech municipalities
- 🧭 **Combined queries** - Find address, validate with places, full address hierarchy in one call
- 📦 **Typed DTOs** - `ValidateResult`, `ValidatedPlace`, `Region`, `Municipality`, `Street`, `Place`
- 💾 **Caching** - Responses stored in Nette Caching, TTL configurable (default 24 hours)
- 🚨 **Typed exceptions** - Separate exceptions for invalid API key, rate limit and other API errors
- 🎯 **Nette integration** - DI extension with auto-registration via `composer.json`

## Requirements

- PHP 8.2+
- Nette 3.1+
- RUIAN API key from [ruian.fnx.io](https://ruian.fnx.io/)

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

### Getting an API key

Request a free API key at [ruian.fnx.io](https://ruian.fnx.io/). Limits are listed under [API rate limits](#api-rate-limits).

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

### Validate an address

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

### Address builder

Each step takes an ID returned by the previous one:

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

### Municipality autocomplete

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

### Combined queries

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

### Validation parameters

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

Responses are cached in Nette Caching by default.

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

### Exception handling

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

## API rate limits

- Free tier: 1000 requests/hour
- No SLA guarantees

## Testing

```bash
php tests/HouseNumberFormatTest.php
```

## Contributing

Contributions are welcome! For major changes, please open an issue first.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'feat: description'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Support

- 📧 **Email:** dev@nks-hub.cz
- 🐛 **Bug reports:** [GitHub Issues](https://github.com/nks-hub/nette-ruian/issues)
- 📖 **RUIAN API:** [ruian.fnx.io](https://ruian.fnx.io/)

## License

MIT License — see [LICENSE](LICENSE) for details.

---

<p align="center">
  Made with ❤️ by <a href="https://github.com/nks-hub">NKS Hub</a>
</p>
