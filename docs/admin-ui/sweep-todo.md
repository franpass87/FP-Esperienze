# Final Sweep TODO

Questa lista tiene traccia delle schermate admin che non hanno ancora adottato completamente i design token, i componenti riutilizzabili e la nuova IA. Verrà aggiornata dopo ogni batch del "FINAL SWEEP".

| Priorità | Schermata | Slug / Screen hook | Tipo | Dipendenze CSS/JS | Capability |
| --- | --- | --- | --- | --- | --- |
| 1 | Reports | `admin.php?page=fp-esperienze-reports` / `fp-esperienze_page_fp-esperienze-reports` | Dashboard analytics | Inline grid CSS, Chart.js embed, AJAX KPI endpoints | `CapabilityManager::MANAGE_FP_ESPERIENZE` |
| 2 | Performance | `admin.php?page=fp-esperienze-performance` / `fp-esperienze_page_fp-esperienze-performance` | Settings + maintenance tools | Inline tables, AJAX cache purge, `fp-performance.js` | `manage_options` |
| 3 | Settings tabs (General, Booking, Branding, Gift, Notifications, Integrations, Webhooks, Auto-translate) | `admin.php?page=fp-esperienze-settings` / `fp-esperienze_page_fp-esperienze-settings` | Settings multi-tab | Inline HTML, Settings API hooks, shared `fp-admin-settings.js` | `CapabilityManager::MANAGE_FP_ESPERIENZE` |
| 4 | Developer Toolkit | `admin.php?page=fp-esperienze-developer-tools` / `fp-esperienze_page_fp-esperienze-developer-tools` | Tool panels | Inline cards, `integration-toolkit.js`, AJAX testers | `CapabilityManager::MANAGE_FP_ESPERIENZE` |
| 5 | Notifications & Alerts | `admin.php?page=fp-esperienze-notifications` / `fp-esperienze_page_fp-esperienze-notifications` | Settings form | Inline markup, cron scheduling, AJAX test send | `manage_woocommerce` |
| 6 | Localization Guide | `admin.php?page=fp-esperienze-localization` / `fp-esperienze_page_fp-esperienze-localization` | Static guide | Inline typography, external links, remote images | `CapabilityManager::MANAGE_FP_ESPERIENZE` |
| 7 | Setup Wizard | `admin.php?page=fp-esperienze-setup-wizard` / `fp-esperienze_page_fp-esperienze-setup-wizard` | Onboarding wizard | Media library, color picker, `fp-setup-tour.js`, inline CSS | `manage_woocommerce` |
| 8 | Feature Demo (debug) | `admin.php?page=fp-esperienze-demo` / `fp-esperienze_page_fp-esperienze-demo` | Debug & AJAX demo | Inline scripts, AJAX security tests | `manage_options` |
| 9 | Onboarding dashboard widget | `wp_dashboard_setup` / `fp_esperienze_onboarding_widget` | Dashboard widget | Inline CSS | `CapabilityManager::MANAGE_FP_ESPERIENZE` |
| 10 | Admin notices (Operational alerts, onboarding) | Hooks: `admin_notices`, `network_admin_notices` | Notices | Custom HTML, inline styles | varie (cap specifiche) |
