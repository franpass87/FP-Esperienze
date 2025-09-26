# Admin Screen Inventory

This document captures the current structure of the FP Esperienze admin experience as implemented today. Screen hooks follow the WordPress convention `{$parent}_page_{$slug}` for submenu pages and `toplevel_page_{$slug}` for the main entry.

## Primary Menu (`FP Esperienze`)

| Menu label | Current slug (`page=`) | Screen hook | Capability | Source | Notes & dependencies |
| --- | --- | --- | --- | --- | --- |
| FP Esperienze (Dashboard) | `fp-esperienze` | `toplevel_page_fp-esperienze` | `CapabilityManager::MANAGE_FP_ESPERIENZE` | `MenuManager::addAdminMenu() → dashboardPage()` | Inline cards for stats and widgets; pulls booking stats, recent bookings, dependency status. Heavy inline CSS inside template. |
| Bookings | `fp-esperienze-bookings` | `fp-esperienze_page_fp-esperienze-bookings` | `CapabilityManager::MANAGE_FP_ESPERIENZE` | `MenuManager::bookingsPage()` | Uses list view + calendar toggle with inline JS. Relies on `select2`, `fullcalendar`, `fp-admin-bookings.js`, localized `fpEsperienzeAdmin` data. Performs AJAX for reschedule/cancel, exports CSV. |
| Meeting Points | `fp-esperienze-meeting-points` | same pattern | `CapabilityManager::MANAGE_FP_ESPERIENZE` | `MenuManager::meetingPointsPage()` | CRUD form + table rendered manually. Uses inline JS for delete confirmation. No dedicated styles beyond inline. |
| Extras & Add-ons | `fp-esperienze-addons` | `fp-esperienze_page_fp-esperienze-addons` | `CapabilityManager::MANAGE_FP_ESPERIENZE` | `MenuManager::extrasPage()` | Form + table with modal (ThickBox) for edit. Depends on `thickbox`, localized strings, inline jQuery to open modal. Legacy slug `fp-esperienze-extras` redirects via `MenuRegistry`. |
| Gift Vouchers | `fp-esperienze-gift-vouchers` | `fp-esperienze_page_fp-esperienze-gift-vouchers` | `CapabilityManager::MANAGE_FP_ESPERIENZE` | `MenuManager::vouchersPage()` | Refit with AdminComponents filters/cards, bulk toolbar, badge statuses. Uses `fp-admin-vouchers.js` for confirmations, copy link, and bulk guard. Legacy slug `fp-esperienze-vouchers` mapped for back-compat. |
| Availability & Closures | `fp-esperienze-availability` | `fp-esperienze_page_fp-esperienze-availability` | `CapabilityManager::MANAGE_FP_ESPERIENZE` | `MenuManager::closuresPage()` | Refit with AdminComponents cards, form rows, and an accessible table. Uses `assets/js/admin-closures.js` for guarded deletes and reuses slug alias `fp-esperienze-closures`. |
| Reports | `fp-esperienze-reports` | same pattern | `CapabilityManager::MANAGE_FP_ESPERIENZE` | `MenuManager::reportsPage()` & `templates/admin/reports.php` | Analytics dashboard using cards and charts. Depends on AJAX (`fp_get_kpi_data`, etc.), inline Chart.js embed, custom grid styles. |
| Performance | `fp-esperienze-performance` | same pattern | `manage_options` | `MenuManager::performancePage()` / `PerformanceSettings::renderPage()` | Settings form for caching & asset optimisation. Uses `CacheManager` stats, inline tables. Includes POST handlers for cache actions. |
| Settings | `fp-esperienze-settings` | same pattern | `CapabilityManager::MANAGE_FP_ESPERIENZE` | `MenuManager::settingsPage()` | Multi-tab form covering general, booking, branding, gift vouchers, notifications, integrations, webhooks, auto-translate. Heavy inline HTML/CSS, multiple option saves in one handler, mixed nonce usage. Auto Translate tab submits via `options.php`. |
| Developer Toolkit | `fp-esperienze-developer-tools` | `fp-esperienze_page_fp-esperienze-developer-tools` | `CapabilityManager::MANAGE_FP_ESPERIENZE` | `MenuManager::integrationToolkitPage()` | Developer-focused tools (webhook tester, API clients). Enqueues `assets/js/integration-toolkit.js`. Contains multiple panels with inline markup and AJAX triggers. Aliased from `fp-esperienze-integration-toolkit`. |

## Conditional / Ancillary Submenus

| Menu label | Slug | Screen hook | Capability | Source | Conditions & notes |
| --- | --- | --- | --- | --- | --- |
| Setup Wizard | `fp-esperienze-setup-wizard` | `fp-esperienze_page_fp-esperienze-setup-wizard` | `manage_woocommerce` | `MenuRegistry` entry populated by `SetupWizard` when onboarding incomplete | Added only when onboarding not complete. Enqueues media, color picker, `fp-setup-tour` JS with localized tour steps. Inline CSS for wizard layout. |
| Status & Troubleshooting | `fp-esperienze-status` | `fp-esperienze_page_fp-esperienze-status` | `manage_options` | `SystemStatus` via `MenuRegistry` | Health report with multiple sections rendered manually, inline styles, action links that trigger fixes via query args. Accepts legacy slug `fp-esperienze-system-status`. |
| Notifications & Alerts | `fp-esperienze-notifications` | `fp-esperienze_page_fp-esperienze-notifications` | `manage_woocommerce` | `OperationalAlerts` via `MenuRegistry` | Settings form for digest emails/Slack alerts. Uses `settings_errors()`, manual form layout. Schedules cron events. Legacy alias `fp-esperienze-operational-alerts`. |
| Localization Guide | `fp-esperienze-localization` | `fp-esperienze_page_fp-esperienze-localization` | `CapabilityManager::MANAGE_FP_ESPERIENZE` | `TranslationHelp` via `MenuRegistry` | Static documentation page with instructions and external links. Inline image embeds from remote URL. Legacy alias `fp-esperienze-translation-help`. |
| Feature Demo *(debug only)* | `fp-esperienze-demo` | `fp-esperienze_page_fp-esperienze-demo` | `manage_options` | `FeatureDemoPage::init()` via `MenuRegistry` | Registered only when `WP_DEBUG` true via `Plugin::shouldExposeFeatureDemo()`. Demonstrates AJAX handlers (`fp_test_security`, etc.) with inline scripts. |
| Performance / SEO filters | `fp-esperienze-performance`, `fp-esperienze-seo` | Filter `fp_esperienze_admin_menu_pages` | `manage_options` | `PerformanceSettings::addMenuPage()`, `SEOSettings::addMenuPage()` | Currently redundant with direct `add_submenu_page` calls. `SEOSettings` registers settings but menu entry not exposed in UI because filter result is unused. |

## Other Admin Touchpoints

- **Dashboard Widget:** `OnboardingDashboardWidget` adds a welcome panel on the WP dashboard with quick actions and inline styles.
- **Admin Notices:** `OnboardingNotice`, `SetupWizard::maybeRenderWizardNotice()`, `OperationalAlerts` result messages, and composer dependency notice (`fp_esperienze_display_composer_notice()`) render custom notices with varying markup.
- **AJAX Endpoints:** Booking actions (`fp_reschedule_booking`, etc.), voucher actions, webhook tests, analytics data, integration toolkit tests. Many expect localized strings via `fpEsperienzeAdmin`.
- **Screen Hooks without menu:** Some AJAX-only flows (e.g., `fp_search_experience_products`) power select2 fields across multiple screens.

## Asset Loading Summary

- `MenuManager::enqueueAdminScripts()` loads shared dependencies (`jquery`, `thickbox`, `select2`, `product-search.js`) on any screen whose hook contains `fp-esperienze`.
- Additional conditional loads:
  - Bookings screen: `moment`, `fullcalendar`, `admin-bookings.js`.
  - Developer Toolkit: `integration-toolkit.js`.
  - Setup Wizard: media library, color picker, `fp-setup-tour.js`, inline wizard styles.
- Many screens embed significant inline `<style>` and `<script>` blocks instead of dedicated assets.
