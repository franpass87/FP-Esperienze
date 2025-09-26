# Phase 8 – Test & CI Automation

## Overview
- Introduced a PHPUnit-based test harness with a lightweight WordPress shim so core services can be validated without a full WP environment.
- Added focused unit coverage for the ServiceBooter, date helper wrapper, and RuntimeLogger overlay/logging flows to guard recent refactors.
- Generated an executable phpunit.xml.dist with code coverage configuration targeting the plugin runtime files.

## Commands
- `composer test` now runs phpstan, phpcs, and the new phpunit suite.
- `vendor/bin/phpunit` executes the isolated test suite; `phpdbg -qrr vendor/bin/phpunit --coverage-text=docs/coverage/phpunit.txt` exports coverage snapshots.

## Continuous Integration
- Added `.github/workflows/test-suite.yml` to run the PHPUnit suite on PHP 8.1 and 8.2 against WordPress 6.3 and 6.4 matrices.
- Jobs install Composer dependencies, expose the selected WP version via an environment variable, and surface failures directly in GitHub PRs.

## Coverage Summary
- Initial text coverage report stored at `docs/coverage/phpunit.txt` after running the suite via phpdbg.
- Coverage currently focuses on bootstrap orchestration and logging utilities; future phases should expand integration coverage around booking and REST flows once shared WP fixtures are available.
