# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

ValoraNL is a real estate intelligence platform for Nuevo León, Mexico. It estimates property market values using a comparables-based engine with Ross-Heidecke depreciation, negotiation factors, and confidence scoring. Built on CodeIgniter 4 with MySQL.

## Commands

### Run all tests
```bash
vendor/bin/phpunit
```

### Run a single test file
```bash
vendor/bin/phpunit tests/unit/ValuationMathTest.php
```

### Run a single test method
```bash
vendor/bin/phpunit --filter testConservationMultiplierMatchesSpecification
```

### Run tests with coverage
```bash
vendor/bin/phpunit --coverage-html build/logs/html
```

### Start local server (Laragon or built-in)
```bash
php spark serve
```

## Architecture

### Valuation Pipeline (core business logic)

The valuation flow is: **Controller → Service → Math Library**, with Config providing tunable parameters.

1. **`Home::estimate()`** validates form input (municipality, colony, m², bedrooms, etc.)
2. **`ValuationService::estimate()`** orchestrates the full valuation:
   - Normalizes input and computes adjustment factors (Ross-Heidecke × negotiation × equipment)
   - Searches for comparables in cascading geographic scope: colonia → municipio → municipio ampliado → estado
   - Removes outliers via IQR, computes weighted median PPU (price per m²), applies size adjustment
   - Builds confidence score based on comparable count, dispersion, and location scope
   - Falls back to synthetic estimate (base PPU $18,000/m²) when no comparables exist
3. **`ValuationMath`** provides pure math functions: Ross-Heidecke factor, age depreciation, conservation multipliers, conservation inference from age
4. **`Config\Valuation`** holds all tunable parameters: conservation multipliers (1-9), depreciation impact, useful life years, negotiation factor, age-to-conservation mapping

### Key Data Flow

- `ListingModel` wraps the `listings` table (normalized property data with price, area, location, features)
- `SourceModel` wraps `sources` (data origins)
- `ListingViewService` prepares data for the home page (listing cards, market stats, market tags like "Oportunidad")

### Database

MySQL with raw SQL (no CI4 migrations). Schema defined in `contexto/CONTEXTO_DB.md`. Main tables: `sources`, `listings`, `listing_price_history`, `valuations`, `valuation_comparables`.

## Conventions

- **PHP 8.1+** with strict typing, readonly constructor promotion, named arguments, match expressions
- **No migrations** — database changes are raw SQL
- **Frontend**: Bootstrap 5, jQuery, Select2, Flatpickr, Bootstrap-Table, FontAwesome
- **Tests**: PHPUnit 10, extend `CIUnitTestCase`. Test files go in `tests/unit/`, `tests/database/`, or `tests/session/`
- All monetary values are in MXN. Geographic focus is exclusively Nuevo León state
- The `contexto/` directory contains master documentation for AI assistants (domain context, DB schema)
