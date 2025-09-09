# Manual Tests: Filesystem Failures

These tests verify graceful handling when filesystem operations fail.

## Translation Logger Unwritable
1. Set option `fp_lt_enable_log` to `1`.
2. Make the uploads directory read-only: `chmod -w wp-content/uploads`.
3. Trigger a log entry (e.g., call `TranslationLogger::log('test')`).
4. Confirm a `WP_Error` is returned and an error is logged.
5. Restore permissions: `chmod +w wp-content/uploads`.

## ICS Generation Unwritable
1. Make the ICS directory read-only: `chmod -w wp-content/uploads/fp-esperienze-ics`.
2. Attempt to generate an ICS file.
3. Ensure `WP_Error` is returned and plugin continues without fatal errors.
4. Restore permissions.

## Voucher PDF/QR Generation Unwritable
1. Make `wp-content/uploads/fp-esperienze` read-only.
2. Generate a voucher PDF.
3. Verify a `WP_Error` is returned and logged.
4. Restore permissions.

## Asset Optimizer Clear Failure
1. Make `assets/css` directory read-only.
2. Call `AssetOptimizer::clearMinified()`.
3. Confirm a `WP_Error` is returned when deletions fail.
4. Restore permissions.

These failures should not crash the site and should log descriptive messages via `error_log`.
