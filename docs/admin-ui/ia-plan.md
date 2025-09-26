# Information Architecture & Menu Plan

## Objectives
- Align admin navigation with operator workflows (monitor → operate → configure) to reduce page hunting.
- Normalize menu labels/capabilities while preserving backward compatibility for existing slugs and hooks.
- Prepare a registry-based menu builder that will enable consistent ordering, separators, and alias redirects in later phases.

## Proposed Navigation Themes
1. **Overview & Monitoring** – surfaces the dashboard overview, booking KPIs, and status reports.
2. **Operations** – day-to-day tasks for staff (bookings, availability, inventory assets, vouchers).
3. **Configuration & Extensions** – longer tail settings, automation, developer tooling, and help references.

Pseudo separators will be rendered by the future `MenuRegistry` (phase [8]) using inert submenu entries so the IA reads as grouped inside the WP admin sidebar.

## Menu Mapping (Before → After)

| Current Label | Current Slug | Proposed Label | Proposed Slug | Capability | Target Screen / Action | Back-Compat Plan |
| --- | --- | --- | --- | --- | --- | --- |
| FP Esperienze (Dashboard) | `fp-esperienze` | **Overview** | `fp-esperienze` *(unchanged)* | `CapabilityManager::MANAGE_FP_ESPERIENZE` | Dashboard cards + quick actions | Keep slug/hook to avoid breaking entry URLs. |
| Bookings | `fp-esperienze-bookings` | **Bookings** | `fp-esperienze-bookings` *(unchanged)* | `CapabilityManager::MANAGE_FP_ESPERIENZE` | Booking list + calendar toggle | Keep slug; move under “Operations” separator. |
| Meeting Points | `fp-esperienze-meeting-points` | Meeting Points | `fp-esperienze-meeting-points` *(unchanged)* | `CapabilityManager::MANAGE_FP_ESPERIENZE` | Meeting point CRUD screen | Reorder after Availability to emphasize inventory flow. |
| Extras | `fp-esperienze-extras` | Extras & Add-ons | `fp-esperienze-addons` | `CapabilityManager::MANAGE_FP_ESPERIENZE` | Extras CRUD (table + modal) | Register alias redirect from `fp-esperienze-extras` using `$_GET['page']` shim; legacy hook remains for third parties. |
| Vouchers | `fp-esperienze-vouchers` | Gift Vouchers | `fp-esperienze-gift-vouchers` | `CapabilityManager::MANAGE_FP_ESPERIENZE` | Voucher management + actions | Introduce alias for `fp-esperienze-vouchers`; keep existing AJAX endpoints untouched. |
| Closures | `fp-esperienze-closures` | Availability & Closures | `fp-esperienze-availability` | `CapabilityManager::MANAGE_FP_ESPERIENZE` | Availability calendar + closure editor | Add redirect for `fp-esperienze-closures`; maintain screen hook compatibility by mapping both slugs to the same callback. |
| Reports | `fp-esperienze-reports` | Reports & Insights | `fp-esperienze-reports` *(unchanged)* | `CapabilityManager::MANAGE_FP_ESPERIENZE` | KPI dashboards | Reposition into Overview/Monitoring block following Dashboard. |
| Performance | `fp-esperienze-performance` | Performance Tools | `fp-esperienze-performance` *(unchanged)* | `manage_options` | Cache & optimisation utilities | Ensure capability check stays `manage_options`; move into Configuration block. |
| Settings | `fp-esperienze-settings` | Settings | `fp-esperienze-settings` *(unchanged)* | `CapabilityManager::MANAGE_FP_ESPERIENZE` | Multi-tab primary settings | Will be reorganized into logical tabs in phase [5]/[6]; slug stays. |
| Integration Toolkit | `fp-esperienze-integration-toolkit` | Developer Toolkit | `fp-esperienze-developer-tools` | `CapabilityManager::MANAGE_FP_ESPERIENZE` | API/webhook helpers | Provide alias for `fp-esperienze-integration-toolkit` so saved bookmarks keep working. |
| Operational Alerts | `fp-esperienze-operational-alerts` | Notifications & Alerts | `fp-esperienze-notifications` | `manage_woocommerce` | Digest + Slack alert settings | Add legacy slug alias; ensure capability remains `manage_woocommerce` for store managers. |
| Translation Help | `fp-esperienze-translation-help` | Localization Guide | `fp-esperienze-localization` | `CapabilityManager::MANAGE_FP_ESPERIENZE` | Static helper/documentation | Alias old slug to new; content to be migrated into new help template later. |
| Setup Wizard *(conditional)* | `fp-esperienze-setup-wizard` | Getting Started (Setup Wizard) | `fp-esperienze-setup-wizard` *(unchanged)* | `manage_woocommerce` | Onboarding flow | Only exposed when onboarding incomplete; keep slug for compatibility. |
| System Status *(conditional)* | `fp-esperienze-system-status` | Status & Troubleshooting | `fp-esperienze-status` | `manage_options` | Health report & fixes | Register alias `fp-esperienze-system-status` and update internal links. |
| Feature Demo *(debug)* | `fp-esperienze-demo` | Feature Demo *(debug)* | `fp-esperienze-demo` *(unchanged)* | `manage_options` | Developer sandbox | Hide behind capability + debug check as today. |
| SEO (unused filter entry) | *(not visible)* | SEO & Tracking | `fp-esperienze-seo` | `manage_options` | SEO schema & tracking settings | Activate existing filtered entry and bind to Settings controller; add alias for eventual historical slugs if discovered. |

## Ordering & Separators

1. Overview *(top-level landing)*
2. Reports & Insights *(monitoring block)*
3. — **Operations** *(separator label)*
4. Bookings
5. Availability & Closures
6. Meeting Points
7. Extras & Add-ons
8. Gift Vouchers
9. — **Configuration** *(separator label)*
10. Settings
11. Notifications & Alerts
12. SEO & Tracking
13. Performance Tools
14. Status & Troubleshooting
15. Developer Toolkit
16. Localization Guide
17. Getting Started (Setup Wizard) *(conditional – appended near end when visible)*
18. Feature Demo *(debug – appended after configuration block when enabled)*

Separators will be implemented as zero-capability inert entries injected by `MenuRegistry`, ensuring screen order remains deterministic while respecting capability checks.

## Capability Review
- Maintain `CapabilityManager::MANAGE_FP_ESPERIENZE` for core operational pages so team leads and staff roles keep access.
- Keep `manage_woocommerce` for onboarding wizard and notifications since they alter commerce-wide behaviour.
- Reserve `manage_options` for technical/system pages (Performance, Status, SEO) aligning with WP conventions.

## Backward Compatibility Plan
- `MenuRegistry` (phase [8]) will map legacy slugs to new canonical slugs before WordPress resolves the page callback. Example:
  ```php
  $aliases = [
    'fp-esperienze-extras' => 'fp-esperienze-addons',
    'fp-esperienze-vouchers' => 'fp-esperienze-gift-vouchers',
    'fp-esperienze-closures' => 'fp-esperienze-availability',
    'fp-esperienze-integration-toolkit' => 'fp-esperienze-developer-tools',
    'fp-esperienze-operational-alerts' => 'fp-esperienze-notifications',
    'fp-esperienze-translation-help' => 'fp-esperienze-localization',
    'fp-esperienze-system-status' => 'fp-esperienze-status',
  ];
  ```
- Both the new slug and legacy slug will share the same screen hook callback so third-party extensions hooking into `current_screen->id` continue to operate.
- Internal links, AJAX nonces, and documentation will be updated in later phases to point to canonical slugs; shims remain until at least one major release cycle post-launch.

## Next Steps
- Phase [3]: establish design tokens and base stylesheet to support the refit of updated menu labels and screen layouts.
- Begin drafting redirect/helper utilities so the slug aliasing is ready once the centralized menu registry is implemented.
