# Admin UI Revamp Release Notes (v1.2.0)

## Overview
The FP Esperienze 1.2.0 release finalises the multi-phase admin redesign. Shared tokens, reusable components, and a centralised menu registry now underpin every backend screen, delivering a consistent, accessible, and fully localisable operator experience.

## Highlights
- **Design system** – `assets/src/admin/tokens.css` and `assets/src/admin/base.css` supply the spacing, colour, and typography primitives compiled into `assets/css/admin.css`.
- **Reusable components** – `includes/Admin/UI/AdminComponents.php` renders page headers, cards, tab navigation, notices, and form rows with skip links, focus outlines, and `aria-live` regions baked in.
- **Information architecture** – `includes/Admin/MenuRegistry.php` maps the new menu structure while preserving legacy slugs for bookmarked URLs and third-party integrations.
- **Refit screens** – Dashboard metrics, bookings management, extras bulk flows, and the system status report now consume the shared components for consistent layout, spacing, and messaging.
- **Settings & validation** – Settings services route through a shared controller that sanitises requests, funnels notices through the Settings API, and normalises checkbox handling.
- **Accessibility polish** – Focus rings, skip links, and notice contrast meet WCAG AA targets in both light and dark schemes.

## Packaging Checklist
- [x] Bump `FP_ESPERIENZE_VERSION` to `1.2.0` in `fp-esperienze.php`.
- [x] Regenerate admin styles via `php scripts/build-admin-styles.php`.
- [x] Genera localmente l'archivio di distribuzione (`bash scripts/build-plugin-zip.sh`) e il relativo checksum opzionale in `dist/` (entrambi ignorati dal versionamento).
- [x] Update `CHANGELOG.md`, `README.md`, and `UPGRADE.md` with 1.2.0 highlights and deployment guidance.

## Upgrade Reminders
- Recompile custom forks after overriding any tokens or component partials.
- Adopt the documented skip-link target (`#fp-admin-main-content`) on bespoke admin pages.
- Review `docs/admin-ui/ia-plan.md` for canonical menu slugs and capability mappings used by the registry.
