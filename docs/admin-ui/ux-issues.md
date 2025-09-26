# UX & A11y Issues (Current State)

This list captures notable usability, information architecture, and accessibility problems identified while reviewing the existing admin implementation. It will drive prioritisation in subsequent phases.

## Global Patterns

- **Fragmented styling:** Every screen prints large inline `<style>` blocks instead of leveraging reusable admin styles. This makes maintenance difficult and causes inconsistent spacing, typography, and colors across the suite.
- **Inline scripting & DOM coupling:** Behaviour is embedded inside page templates (e.g., inline `<script>` in Bookings, Extras, Vouchers, Closures). This prevents caching, complicates localisation, and hampers a11y reviews.
- **Missing WP affordances:** No screens expose Screen Options, contextual Help tabs, or WP List Table affordances. Bulk actions, pagination, and column toggles are implemented manually (or missing entirely).
- **Keyboard interaction gaps:** Modal dialogs (ThickBox usage for Extras, custom overlays for Vouchers/Closures) do not trap focus or announce context, making keyboard navigation and screen reader usage challenging.
- **Color & contrast drift:** Custom color choices (e.g., dashboard cards using brand orange on white) may not meet WCAG contrast requirements and diverge from WP core palette.
- **Capability mismatches:** Most pages use the broad `CapabilityManager::MANAGE_FP_ESPERIENZE`, but specialised tools (Integration Toolkit, developer diagnostics) are still exposed to managers rather than admin-only capabilities, which could confuse non-technical operators.
- **No responsive guidance:** Layouts rely on fixed grids or tables without responsive fallbacks; admin users on smaller laptops will encounter overflow and horizontal scrolling.

## Screen-Specific Notes

### Dashboard
- Hard-coded grid with inline styles; cards lack semantic grouping (`role`, headings levels). No quick filters or contextual links beyond static buttons.
- Dependency widget pulls HTML from `DependencyChecker::renderStatusWidget()` with inline styles and no ARIA labelling.

### Bookings
- Custom table lacks sortable headers, pagination, and bulk selection. Calendar toggle is purely visual with no ARIA state changes.
- Filter form uses raw `<select>` elements without labels for date inputs (placeholders only). Export link reuses GET params without nonce confirmation prompt.
- Reschedule/Cancel modals are injected inline with minimal focus management and rely on `alert`/`confirm` flows.

### Meeting Points
- Form and list share the same page without clear separation; success/error feedback relies on `admin_notices` added after redirects.
- Delete confirmation uses `confirm()` with concatenated strings; no nonce on GET delete link (relies on hidden form but triggered via JS only).

### Extras
- Edit modal depends on ThickBox but fields are plain HTML; no aria attributes or focus defaults when modal opens.
- Required markers use `*` text but not programmatically associated (no `aria-required`).

### Vouchers
- Complex interface with tabs implemented via inline JS toggling classes; not announced to assistive tech.
- Bulk actions rely on JS to gather selected IDs but there is no `<form>` submit fallback. Status badges use color-only differentiation.

### Closures
- Calendar view is a hand-rolled grid lacking accessible date navigation. Modal forms do not provide labels/field descriptions beyond placeholders.
- Time inputs rely on `type="datetime-local"` without timezone hints; recurrence settings require manual entry, lacking validation feedback.

### Reports
- Charts rendered inline (Chart.js) without table-based fallbacks; KPI cards show numbers without context for screen readers.
- Filter controls are nested inside `<div>`s with no form semantics, making submission unclear.

### Settings
- Massive monolithic form combining unrelated concerns; tabs are anchor-based but rendered as static nav without `aria-selected` or `role="tab"` semantics.
- Different tabs save data via mixed mechanisms (custom POST handler vs. `options.php`), leading to inconsistent messaging (some use `settings_errors`, others print custom notices).
- Inputs lack descriptions or use `<p class="description">` without associating `id`/`for` properly, which can break screen reader context.

### Integration Toolkit
- Highly technical copy exposed to capability holders meant for operations. Buttons trigger AJAX without feedback spinners; results area lacks semantic region for updates.

### Setup Wizard
- Stepper is purely visual; active/completed states indicated via color alone. Buttons are standard but layout uses inline CSS. No breadcrumbs or progress for screen readers.

### System Status
- Sections use custom `<div>` with headings but rely on inline CSS for status colors; icons are decorative without `aria-hidden`. Action links lack explicit button semantics.

### Operational Alerts
- Settings form mixes toggles and text inputs without aligning to WP settings API table markup. Success feedback relies on `settings_errors()` but there is no persistent summary of current schedule/last run besides plain text.

### Translation Help
- Embeds remote image without alternative textual summary beyond `alt` attribute; lacks list of downloadable assets for offline docs.

### Feature Demo (debug)
- Demo buttons produce dynamic notices inserted in-line but not announced. Input validation uses inline JS without server fallback.

## Technical Debt Observations

- Settings persistence and data sanitisation are scattered across page classes, complicating future refactors.
- There is no central registry describing menu hierarchy, leading to duplicated slugs/capabilities and unused filters (`fp_esperienze_admin_menu_pages`).
- Shared UI elements (cards, tables, filters, modals) are reimplemented per screen instead of using reusable components, inflating maintenance costs.
