# Documentation & Release — Phase 10

## Summary
- Refreshed `README.md` with 1.1.0 highlights, documented the distributable artifact, and linked checksum verification guidance.
- Added a formal `CHANGELOG.md` and `UPGRADE.md` describing user-facing changes and the recommended migration plan.
- Produced a distributable ZIP via `tools/build-plugin-zip.sh --slug fp-esperienze --out-dir dist` (saved in the ignored `dist/` directory) with production dependencies and compiled translations, più un checksum SHA-256 per la verifica.

## Release Checklist
- [x] `composer install` (with dev dependencies) and `vendor/bin/phpunit` executed locally.
- [x] Production build generated with `composer install --no-dev --optimize-autoloader`.
- [x] Translations compiled to `.mo` files before packaging.
- [x] Archive checksum salvato localmente accanto allo ZIP in `dist/` (entrambi ignorati dal versionamento).
- [x] `.codex-state.json` advanced to phase 10 with audit notes cleared.

## Next Steps
- Publish the 1.1.0 release and distribute the ZIP to staging/production environments.
- Monitor support channels for upgrade reports, especially around filesystem permission failures surfaced by the upgrade manager.
