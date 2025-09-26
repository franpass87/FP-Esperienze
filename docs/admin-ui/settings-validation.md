# Settings Validation & Messaging

## Overview
Phase 6 formalises how FP Esperienze settings screens validate, sanitise, and surface feedback. Every tab now funnels through the WordPress Settings API helpers so success and error notices respect localisation, accessibility, and multisite contexts.

## Input handling
- **Checkboxes & toggles:** Normalised with `rest_sanitize_boolean()` before casting to booleans across General, Booking, Integrations, Notifications, Webhook, SEO, and Gift services.
- **Text & URLs:** Continue to rely on `sanitize_text_field()`, `sanitize_email()`, `sanitize_textarea_field()`, `esc_url_raw()`, and `sanitize_hex_color()` to constrain values.
- **Arrays:** Target-language selections filter through whitelisted language codes via `AutoTranslateSettings::sanitizeLanguages()`.
- **Secrets:** Gift secret regeneration now guards against `random_bytes()` failures and surfaces a friendly error when entropy is unavailable.

## Security guards
- **Capabilities:** `MenuManager::handleSettingsSubmission()` and `PerformanceSettings` actions enforce `CapabilityManager::canManageFPEsperienze()` before persisting options.
- **Nonces:** Settings submissions verify `fp_settings_nonce`; performance tools use bespoke `check_admin_referer()` calls per action; Auto Translate cache clearing retains its dedicated nonce.
- **Multisite/WP-CLI safety:** Reliance on core helpers means notices render correctly whether requests arrive via classic admin, network contexts, or CLI invocations.

## Feedback & notices
- `add_settings_error()` collects success and error messages for every settings update, and the affected screens call `settings_errors()` to output WordPress-standard notices.
- Performance maintenance buttons now push their status into `settings_errors()` instead of echoing markup directly, preventing duplicate notices or missing translations.
- Validation errors (e.g., malformed staff emails) render inline via the Settings API so administrators can see and correct issues without leaving the page.

## Contributor tips
- When adding new settings fields, wire them into a tab service that sanitises `wp_unslash( $_POST )` input and returns a `SettingsUpdateResult` with explicit success/error strings.
- Prefer reusing `rest_sanitize_boolean()` for checkbox inputs to avoid inconsistent `'on'`, `'1'`, or `'true'` state handling.
- Always register the option via `register_setting()` with the same sanitisation callback used inside services when leveraging `options.php` submissions.
