# Architecture: statsbestmanufacturers

## Purpose

A PrestaShop statistics module that ranks product manufacturers by sales volume and revenue, surfacing which brands/suppliers drive the most business.

## Directory Structure

```
statsbestmanufacturers.php   - Module class (ModuleGrid subclass); all business logic
upgrade/                     - Migration scripts for version upgrades
tests/                       - PHPUnit test stubs and PHPStan bootstrap
translations/                - Locale string overrides
```

## Key Design Decisions

- **ModuleGrid inheritance**: Leverages PrestaShop's built-in grid with sorting, pagination, and CSV export.
- **Single-file module**: All logic in the main module class per PrestaShop conventions.

## Extension Points

- Override `getData()` to modify the SQL ranking query.
- Add columns in the constructor's `$columns` array.

## Dependency Flow

```
statsbestmanufacturers (ModuleGrid)
  └─> hookDisplayAdminStatsModules() — renders the ranking widget
  └─> getData()                      — runs manufacturer ranking SQL
        └─> Db::getInstance()        — PrestaShop database abstraction
```
