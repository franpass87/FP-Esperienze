# Upgrade Guide

This document explains how to upgrade FP Esperienze between major releases. Always back up your database and `wp-content/uploads/` before applying updates to a production site.

# Upgrading to 1.2.0

FP Esperienze 1.2.0 introduces the redesigned admin experience with reusable components, an information architecture refresh, and hardened settings flows. After updating:

1. **Regenerate compiled assets**
   - Run `php scripts/build-admin-styles.php` (or `composer build-admin-css`) to rebuild `assets/css/admin.css` if you maintain a fork with customisations.
   - When distributing a fork, include the rebuilt stylesheet in your release artifact.

2. **Review custom admin tweaks**
   - Notices, tabs, and form fields now inherit shared CSS custom properties. Override the tokens in `assets/src/admin/tokens.css` rather than targeting rendered classes to keep light/dark support.
   - The new skip link expects the main content container to expose `id="fp-admin-main-content"` and `tabindex="-1"`. Update bespoke screens to adopt that structure so keyboard users can jump past the menu.

3. **Verify menu hooks and slugs**
   - Legacy `page=` slugs remain valid through redirect shims, but update custom links or filters to the canonical slugs documented in `docs/admin-ui/ia-plan.md` for long-term compatibility.
   - Confirm custom capability checks still pass now that registry lookups centralise menu registration.

4. **Validate settings and bulk flows**
   - Run through each settings tab and primary list table to confirm notices render via `settings_errors()` and that bulk deletion confirms before execution.
   - If you enqueue bespoke scripts on admin pages, ensure they still run after the markup refactor introduced wrapper IDs and data attributes.

## Upgrading to 1.1.0

FP Esperienze 1.1.0 introduces an automated upgrade manager, multisite-aware ICS storage, and a refactored bootstrap. Follow these steps to upgrade safely:

1. **Verify prerequisites**
   - WordPress 6.5 or higher.
   - WooCommerce 8.0 or higher.
   - PHP 8.1 or higher with the DOM extension enabled.

2. **Create backups**
   - Export the WordPress database.
   - Back up `wp-content/uploads/` and any customised templates in your theme.

3. **Install the update**
   - Upload the packaged ZIP found in `dist/fp-esperienze-v1.1.0.zip` to WordPress > Plugins > Add New > Upload Plugin.
   - Alternatively, deploy the plugin directory via your preferred CI/CD pipeline.

4. **Let the upgrade manager run**
   - Upon activation, the plugin compares the stored version with `FP_ESPERIENZE_VERSION` and automatically executes any pending schema or filesystem migrations.
   - Administrators will see an admin notice if an upgrade fails. Check the `fp_esperienze_upgrade_error` option for details and fix filesystem permissions if necessary.

5. **Flush caches**
   - If you use object caching, clear it after the upgrade to ensure cached booking metadata reflects new indexes and cache keys.
   - Purge page caches/CDNs that serve booking or voucher endpoints.

6. **Validate critical flows**
   - Place a mobile booking through the REST endpoint and confirm extras validation succeeds.
   - Generate a voucher PDF and ensure the ICS file storage resides in `wp-content/fp-private/` with site-specific subdirectories on multisite installations.
   - Trigger the production readiness WP-CLI command: `wp fp-esperienze production-check`.

7. **Re-run automation**
   - Execute `composer install` followed by `vendor/bin/phpunit` in your development environment to confirm the bundled test suite passes.

Refer to the [CHANGELOG](CHANGELOG.md) for a complete list of user-facing changes.
