# Upgrade Guide

This document explains how to upgrade FP Esperienze between major releases. Always back up your database and `wp-content/uploads/` before applying updates to a production site.

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
   - Upload the packaged ZIP found in `dist/fp-esperienze-1.1.0.zip` to WordPress > Plugins > Add New > Upload Plugin.
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
