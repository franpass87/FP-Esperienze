# Compatibility Audit — Phase 6

## Summary
- Introduced a shared `fp_esperienze_wp_date()` helper that modernises date rendering to `wp_date()` while providing a safe fallback for legacy WordPress installs.
- Replaced direct `date_i18n()` usages across booking, voucher, admin, and REST layers with the new helper to ensure PHP 8.3 compatibility and consistent timezone handling.
- Adjusted the private ICS storage path to automatically segregate multisite blog content without breaking existing single-site installations, and hardened REST delivery against missing directories.

## Verification
- Manual review of replaced date formatting calls to confirm correct formatting tokens and sanitisation.
- Confirmed multisite directory logic retains the original path for the primary site and automatically provisions per-site subdirectories for secondary blogs.
- Exercised the ICS REST handler to ensure it gracefully recreates storage paths and rejects traversal attempts after the path normalisation changes.
