# Changelog

## 1.0.2 - 2026-11-06

### Fixed
- The code used typed class constants (PHP 8.3 syntax) while `composer.json` and the README promised PHP 8.2. The types are removed, so the library runs on PHP 8.2 as documented.
- `getAddressHierarchy()` always returned `null` for region and municipality, because the validate endpoint rejects a query by municipality ID alone. It now finds the region through the region and municipality listings.
- `getAllMunicipalities()` and `searchMunicipalities()` sorted names by bytes, so names starting with Č, Ř, Š or Ž ended up after Z. They now use Czech order (`Collator` from ext-intl, with a built-in fallback).
- PHPStan error "Unsafe usage of new static()": `RuianException::create()` now returns `self` (it used to build the subclass with a mismatched constructor).

### Added
- Offline test suite (Nette Tester) with a replaceable HTTP transport (optional `httpGet` constructor argument).
- `ext-intl` listed under `suggest`.
