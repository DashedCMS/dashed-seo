# Changelog

## v4.11.0 - 2026-11-25

### Changed
- Automatisch oplossen zonder paneelsessie toetst superadmin met `is_superadmin`, en meldingen gaan naar iedereen met toegang tot het CMS (vereist dashed-core ^4.124).

## v4.10.1 - 2026-11-19

### Fixed
- Migratie PageSpeed-metingen liep op MySQL vast op een te lange indexnaam (max. 64 tekens). Indexen hebben nu een korte naam; een nieuwe migratie zet ze alsnog op sites waar de tabel zonder indexen achterbleef.

## v4.10.0 - 2026-11-19

### Added
- Zoekwoorden per pagina uit Google Search Console, met dalers en meldingen. Standaard uit.
- Prestatiemonitor via PageSpeed Insights (lab- en velddata) en de controle `poor_core_web_vitals`. Standaard uit.

### Fixed
- Externe-linkcontrole remt per host af en telt een 429 niet meer als kapotte link; GET na een mislukte HEAD.

## v4.9.0 - 2026-11-16

### Added
- Het taakcentrum (het tabje Bezig) toont nu de sitescan, het opstellen van voorstellen en het doorvoeren van fixes in een reeks.
