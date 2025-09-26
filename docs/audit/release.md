# Documentation & Release — Phase 10

## Summary
- Refreshed `README.md` with 1.1.0 highlights, documented the distributable artifact, and linked checksum verification guidance.
- Added a formal `CHANGELOG.md` and `UPGRADE.md` describing user-facing changes and the recommended migration plan.
- Produced a distributable ZIP at `dist/fp-esperienze-1.1.0.zip` with production dependencies and compiled translations, plus a SHA-256 checksum for verification.

## Release Checklist
- [x] `composer install` (with dev dependencies) and `vendor/bin/phpunit` executed locally.
- [x] Production build generated with `composer install --no-dev --optimize-autoloader`.
- [x] Translations compiled to `.mo` files before packaging.
- [x] Archive checksum recorded in `dist/fp-esperienze-1.1.0.zip.sha256`.
- [x] `.codex-state.json` advanced to phase 10 with audit notes cleared.

## Next Steps
- Publish the 1.1.0 release and distribute the ZIP to staging/production environments.
- Monitor support channels for upgrade reports, especially around filesystem permission failures surfaced by the upgrade manager.
