# FP Esperienze – Code Map

This document maps the main components that ship with the FP Esperienze plugin and how they interact with WordPress/WooCommerce.

## Plugin bootstrap
- **`fp-esperienze.php`** – Defines plugin constants, performs PHP/WP version gating, and loads Composer (or a fallback PSR-4 autoloader). Registers activation/deactivation hooks and boots the core `FP\Esperienze\Core\Plugin` singleton on `plugins_loaded`.
- Handles admin notices when dependencies (Composer or WooCommerce) are missing and writes initialization errors to `wp-content/fp-esperienze-errors.log`.
- Registers WP-CLI commands (`fp-esperienze`, `fp-esperienze production-check`, `fp-esperienze onboarding`, `fp-esperienze operations`, `fp-esperienze qa`).

## Autoloading & dependencies
- Composer configuration lives in `composer.json`; runtime autoload falls back to a PSR-4 loader targeting `includes/` when `vendor/autoload.php` is not present.
- Required runtime packages: `dompdf/dompdf` and `chillerlan/php-qrcode` for PDF/QR generation.

## Activation & installer
- `FP\Esperienze\Core\Installer` wires activation/deactivation logic: creates/updates custom tables (`fp_bookings`, `fp_schedules`, `fp_overrides`, `fp_vouchers`, `fp_staff_*`, `fp_push_tokens`, etc.), ensures directories (`wp-content/fp-private/fp-esperienze-ics`) exist, seeds options, flushes rewrites, and schedules cron tasks.
- Deactivation clears all plugin-specific cron events.

## Core services (`includes/Core`)
- `Plugin` – Central orchestrator. Sets up feature modules, enqueues assets, registers cron intervals, bootstraps admin/frontend/public components, and handles fallback admin notices on initialization failure.
- `Installer` – Activation routines (see above).
- `SecurityEnhancer` – CSP headers, AJAX/REST rate limiting hooks, security logging via `fp_esperienze_security_event`.
- `PerformanceOptimizer` & `PerformanceMonitor` – Adds DB indexes, caches queries, schedules weekly DB maintenance (`fp_esperienze_db_optimization`), and surfaces debug metrics.
- `CacheManager` – Wraps transients/options for availability caching and pre-building (cron: `fp_esperienze_prebuild_availability`).
- `CapabilityManager` – Defines granular capabilities used by admin screens and REST controllers.
- `AnalyticsTracker`, `AssetOptimizer`, `UXEnhancer`, `SiteHealth`, `QueryMonitor`, `ErrorRecovery`, `FeatureTester`, `TranslationCompiler`, `TranslationLogger`, `TranslationQueue` (custom post type `fp_es_translation_job` + cron `fp_es_process_translation_queue`), `WebhookManager` (retry cron `fp_esperienze_retry_webhook`), and `RateLimiter` (transient-driven limits).

## Data layer (`includes/Data`)
- Booking domain managers: `BookingManager`, `ScheduleManager`, `OverrideManager`, `StaffScheduleManager`, `HoldManager`, `DynamicPricingManager/DynamicPricingHooks`, `ExtraManager`, `MeetingPointManager`, `VoucherManager`, `NotificationManager`, `Availability`, `ICSGenerator`, `DataManager`.
- Responsibilities cover CRUD around bookings, availability calculation, email notifications, voucher issuance, meeting points, and translation queue updates.
- Some classes interact directly with `$wpdb` for custom tables.

## Booking / commerce integration (`includes/Booking`, `includes/ProductType`)
- `BookingManager` and `Cart_Hooks` integrate WooCommerce order flows, manage holds, attach booking metadata, and trigger notifications.
- `ProductType\Experience` registers the custom WooCommerce product type (`experience`) and associated admin panels; `WC_Product_Experience` custom product class implements price/availability logic.

## Admin area (`includes/Admin`)
- Modular admin controllers: `MenuManager` sets up menu pages; `SetupWizard`, `OnboardingNotice`, `OnboardingDashboardWidget`, `OperationalAlerts`, `PerformanceSettings`, `SystemStatus`, `SEOSettings`, `ReportsManager`, `AdvancedAnalytics`, `FeatureDemoPage`, `DependencyChecker`, `OnboardingHelper`.
- Settings sub-namespace (`includes/Admin/Settings`) contains `AutoTranslateSettings`, `BrandingSettingsView`, `TranslationHelp`, and service classes that coordinate onboarding/translation configuration workflows.
- Provide settings UIs, dashboards, analytics pages, manual cache controls, dependency checks, and onboarding flows.
- Several classes expose AJAX endpoints (e.g., `FeatureDemoPage`, `ReportsManager`) and rely on `CapabilityManager` for permission checks.

## Frontend (`includes/Frontend` + templates/assets)
- `Shortcodes` registers: `[fp_exp_archive]`, `[wcefp_experiences]`, `[fp_event_archive]`.
- `Templates` loads custom single/archive templates, while `SEOManager` injects schema/meta tags and `WidgetCheckoutHandler` drives iframe checkout callbacks.
- Public templates live in `templates/` (`single-experience.php`, `voucher-form.php`).
- Assets served from `assets/css` & `assets/js`; `AssetOptimizer` handles versioned URLs, defers selected scripts, and registers Gutenberg block scripts.

## Blocks & widgets (`includes/Blocks`)
- `ArchiveBlock` registers a Gutenberg block for listing experiences and shares data with the REST layer.

## REST API (`includes/REST`)
- Controllers register routes under the `fp-exp/v1` namespace:
  - `AvailabilityAPI` (`/availability`) – public availability queries with rate limiting and caching.
  - `BookingsAPI` (`/bookings`, `/bookings/calendar`) – authenticated booking listings filtered by date/status.
  - `BookingsController` – admin booking management endpoints (create/update/cancel/etc.).
  - `ICSAPI` – generates ICS feeds for schedules/staff.
  - `WidgetAPI` – iframe/embed endpoints (`/widget/iframe/{id}`, `/widget/data/{id}`) with origin validation and HTML responses.
  - `SecurePDFAPI` – gated voucher PDF download.
  - `SystemStatusAPI` – exposes health diagnostics.
  - `MobileAPIManager` – handles mobile push APIs (push tokens, notifications).

## AI & integrations (`includes/AI`, `includes/Integrations`)
- `AI\AIFeaturesManager` schedules daily analysis (`fp_daily_ai_analysis`).
- Integrations: `BrevoManager`, `EmailMarketingManager` (cron hooks `fp_check_abandoned_carts`, `fp_send_upselling_emails`, `fp_send_pre_experience_email`, `fp_send_review_request`, etc.), `GooglePlacesManager`, `GoogleBusinessProfileManager`, `MetaCAPIManager` (cron `fp_send_meta_event`), `TrackingManager` (analytics, consent mode), plus WPML hooks and marketing automation.

## CLI tooling (`includes/CLI`)
- Commands provide onboarding automation, translation management, quality assurance runs, operations, and production-readiness checks via WP-CLI.

## Cron jobs summary
- `fp_es_process_translation_queue` (hourly) – translation queue processing.
- `fp_esperienze_prebuild_availability` (hourly) – availability cache builder.
- `fp_esperienze_cleanup_holds` (custom 5-minute interval) – remove expired holds.
- `fp_cleanup_push_tokens` (daily) – purge stale mobile push tokens.
- `fp_esperienze_db_optimization` (weekly) – DB maintenance & index verification.
- `fp_esperienze_retry_webhook` (5-minute or scheduled by `WebhookManager`) – retries failed webhooks.
- `fp_daily_ai_analysis` (daily) – AI analytics.
- Email marketing hooks: `fp_check_abandoned_carts`, `fp_send_upselling_emails`, `fp_send_pre_experience_email`, `fp_send_review_request`.
- Voucher delivery: `fp_esperienze_send_gift_voucher`.
- Meta conversion push: `fp_send_meta_event`.

## Options, settings & transients (selection)
- Installer sets `fp_esperienze_version`, `fp_esperienze_setup_complete`, capability flags, optimized index version, and caching options such as `fp_esperienze_availability_cache_index`.
- Admin pages persist settings for performance caching, SEO defaults, onboarding progress, integration credentials (`fp_esperienze_integrations`), etc.
- `RateLimiter` relies on transients prefixed with `fp_rate_limit_`.

## Assets & localization
- CSS/JS assets under `assets/` with handles managed by `AssetOptimizer`; Gutenberg/editor assets served via `enqueueBlockAssets()`.
- Translations compiled via `TranslationCompiler::ensureMoFiles()` and stored in `languages/`.

## Logging & diagnostics
- `Core\Log` provides structured logging to `wp-content/fp-esperienze.log` and fallback to `error_log`.
- `QueryMonitor`, `PerformanceMonitor`, and `SecurityEnhancer` emit debug output when `WP_DEBUG` is enabled.
- `docs/` folder (this audit) will accumulate phase reports.
