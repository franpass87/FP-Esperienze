# Changelog

All notable changes to FP Esperienze will be documented in this file. The format roughly follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2025-09-26
### Added
- Automated upgrade manager that queues database and filesystem migrations, refreshes cron schedules, and surfaces failures to administrators.
- Development-only runtime logger with persistent storage and an optional overlay to help developers capture notices before they reach production users.
- Comprehensive PHPUnit suite with a WordPress bootstrap shim and GitHub Actions workflow that exercises booking flows, service bootstrapping, helpers, and runtime logging.

### Changed
- Core bootstrap refactored around the reusable `ServiceBooter`, centralising hook registration and lifecycle guards across admin, REST, CLI, and background tasks.
- ICS storage provisioning extracted into the installer for reuse by upgrades, ensuring multisite-aware directories even when WP_Filesystem credentials are unavailable.
- Extras, meeting point, and booking flows now normalise and cache metadata to reduce redundant database work during high-traffic scenarios.
- REST and mobile booking endpoints tightened with canonical extras validation, sanitised participant payloads, and consistent security checks.

### Fixed
- Hardened filesystem writes with an automatic fallback when the WordPress filesystem API cannot initialize.
- Prevented malformed extras payloads from reaching the booking manager via the mobile REST endpoint.
- Ensured booking upgrade routines reschedule cron events and flush rewrites after version bumps.

## [1.0.0] - 2024-01-15
### Added
- Initial public release of FP Esperienze with WooCommerce experience products, booking management, vouchers, integrations, and REST APIs.
