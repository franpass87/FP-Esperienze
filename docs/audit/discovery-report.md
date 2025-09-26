# Discovery Report – Phase 1

## Scope & methodology
- Reviewed plugin bootstrap (`fp-esperienze.php`), core services, admin/front-end modules, REST controllers, cron orchestration, and data layer under `includes/`.
- Generated `docs/code-map.md` to describe structure and entry points.
- Skimmed templates, assets, and activation/installation flows to identify risk areas for subsequent phases.

## Key architecture notes
- Central orchestrator `FP\Esperienze\Core\Plugin` wires core services, admin/front-end hooks, cron jobs, and REST registration during `plugins_loaded`/`init` sequences.
- Activation is handled by `FP\Esperienze\Core\Installer`, which requires Composer dependencies, creates/updates custom tables, seeds options, and schedules cron tasks.
- REST surface is extensive (`fp-exp/v1` + `fp-esperienze/v1`) covering availability, bookings, ICS feeds, widget rendering, PDF delivery, system status, and mobile integrations.
- Custom WooCommerce product type `experience` drives booking logic; multiple managers under `includes/Data` interact with custom tables and WooCommerce orders.

## Initial findings & risks
- **Composer dependency gating** – Activation hard-stops via `wp_die()` when `vendor/autoload.php` is missing, yet the runtime contains a fallback autoloader. Packaging must include vendor files or the plugin becomes un-installable from wp-admin (`fp-esperienze.php`). We should validate installer behavior in environments with restricted filesystem access.
- **Public REST endpoints** – Several routes use `permission_callback => '__return_true'` (e.g., `AvailabilityAPI`, `WidgetAPI`, `ICSAPI`). While rate limiting/origin checks exist, we need to verify that exposed payloads do not leak private order/customer data and that origin validation covers all embed scenarios.
- **Cron surface area** – The plugin schedules many events (`fp_esperienze_cleanup_holds`, `fp_cleanup_push_tokens`, `fp_esperienze_prebuild_availability`, marketing emails, AI analysis, webhook retries). We must confirm duplicate scheduling is prevented, add logging/locking if necessary, and ensure deactivation clears every hook.
- **Direct SQL operations** – Classes such as `PerformanceOptimizer`, `BookingManager`, `OverrideManager`, and `ICSAPI` run manual queries/`ALTER TABLE` statements. Some use `$wpdb->query("ALTER TABLE {$table} ...")` without prepared statements (table names sourced internally). We should audit for SQL injection vectors and wrap schema changes in capability/permission checks.
- **CSP implementation gaps** – `SecurityEnhancer::addSecurityHeaders()` only outputs a CSP meta tag when `fp_esperienze_enable_csp` filter returns true, but the default directives still allow `'unsafe-inline'` and `'unsafe-eval'`. Need to evaluate whether this weak policy provides value or should be hardened/disabled to avoid false sense of security.
- **Template/output escaping** – Initial spot-check of `templates/single-experience.php` shows use of `wp_kses_post`/`esc_html`, but the template is large and mixes dynamic data extensively. A full escaping audit is required to avoid stored XSS from product metadata, FAQs, or meeting point descriptions.
- **File I/O & WP_Filesystem usage** – Multiple components (Installer, `fp_esperienze_write_file`, `ICSAPI::serveICSFile`, `AssetOptimizer`) depend on `WP_Filesystem`. Need to confirm graceful failures when credentials are unavailable and ensure error surfaces to admins.
- **Rate limiter coverage** – `SecurityEnhancer`/`RateLimiter` hook into AJAX/REST flows, but we should double-check naming collisions and transient cleanup to avoid DOS due to stale rate-limit keys.
- **Testing/CI gap** – PHPUnit/PHPCS/PHPStan config files exist, but no automation is currently recorded. Later phases must validate that composer scripts run cleanly and integrate CI.

## Recommended next steps
1. Proceed with Phase 2 (linters) to establish a clean baseline and surface coding standard issues automatically.
2. Prepare targeted security review for public REST routes and AJAX handlers, focusing on capability checks, nonce verification, and data sanitization.
3. Map cron hooks to their scheduling logic to verify they are idempotent and properly cleaned up on deactivation.
4. Catalogue direct SQL queries for conversion to `$wpdb->prepare()` where possible and plan migrations/locking for schema changes.
5. Plan a comprehensive escaping audit for templates/admin output prior to release.

This report will be expanded and refined as remediation phases progress.
