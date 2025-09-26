# Phase 10 – A11Y & UI Polish

## Keyboard and focus enhancements
- Added a reusable `AdminComponents::skipLink()` helper and bound it to the dashboard, bookings, and system status screens so keyboard users can jump directly to the main content container.
- Replaced faint focus outlines with a high-contrast twin ring that adapts to the active admin color scheme (including dark mode) and respects the shared token scale.
- Extended tab navigation and toolbar links with `:focus-visible` treatments that keep the currently targeted action visible without relying on hover-only feedback.

## Messaging and form feedback
- Reworked admin notices to use semantic heading, message, and action regions with `aria-live` support so assistive tech announces state changes consistently.
- Refreshed notice styling with accessible contrast, left borders for state emphasis, and neutral body copy to avoid low-contrast tints.
- Promoted form error copy to assertive alerts and ensured `aria-describedby` hooks point at the generated message for inline validation summaries.

## Visual rhythm and tokens
- Normalised helper text and badge colours to the accessible `--fp-admin-color-text-subtle` scale, ensuring subtext remains readable against white and muted panels.
- Introduced skip-link colour tokens and updated the focus ring palette to guarantee AA contrast across both default and dark admin schemes.

## Contributor notes
- Run `php scripts/build-admin-styles.php` after editing any file inside `assets/src/admin/` to regenerate the compiled `assets/css/admin.css` bundle.
- When adding new screens, call `AdminComponents::skipLink()` before rendering the main `.fp-admin-page` container and assign the `id="fp-admin-main-content"`/`tabindex="-1"` attributes so the skip target receives focus correctly.
