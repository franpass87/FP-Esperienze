# Upgrade & Migration Audit — Phase 9

## Summary
- Introduced an `UpgradeManager` that compares the stored plugin version with `FP_ESPERIENZE_VERSION`, executes schema and filesystem migrations, and captures upgrade errors for administrators.
- Extracted the private ICS directory provisioning into `Installer::ensurePrivateStorageDirectory()` with a native filesystem fallback so upgrades harden storage without requiring WP_Filesystem credentials.
- Bumped the plugin to version 1.1.0 and wired upgrade hooks to reschedule cron tasks, refresh capabilities, and flag a post-upgrade rewrite flush to keep routing current.

## Migration Coverage
- Push-token, booking extras, staff attendance, and staff assignment tables are created on demand for legacy installs and rechecked during upgrades.
- Booking schema updates (nullable order references, participants, totals, indexes) and event-support columns are re-applied idempotently for pre-1.1.0 databases.
- Default options, performance indexes, translation cron scheduling, and the protected ICS storage directory are verified each time an upgrade runs.

## Risks & Follow-ups
- Monitor the new `fp_esperienze_upgrade_error` option for unexpected WP_Filesystem failures on hosts that disallow direct writes.
- Phase 10 should update the public changelog/readme and produce the distributable zip now that automated upgrades are in place.
