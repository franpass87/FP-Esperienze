# FP Esperienze – Admin Component Library

The design tokens (`tokens.css`) and structural scaffolding (`base.css`) power a
set of reusable components that keep the FP Esperienze admin screens visually
cohesive and accessible. This document lists the canonical components, explains
how to consume them from PHP, and highlights the key accessibility affordances
built into each pattern.

> **Note:** All CSS selectors are scoped by the `fp-esperienze-admin-screen`
> body class so the styles only apply on plugin screens.

## PHP helper

`FP\Esperienze\Admin\UI\AdminComponents` exposes rendering helpers that output
semantic markup with the correct classes and ARIA wiring.

```php
use FP\Esperienze\Admin\UI\AdminComponents;

AdminComponents::pageHeader([
    'title'   => __( 'FP Esperienze Dashboard', 'fp-esperienze' ),
    'lead'    => __( 'Monitor bookings, vouchers, and operational alerts.', 'fp-esperienze' ),
    'meta'    => [
        [ 'label' => __( 'Last sync', 'fp-esperienze' ), 'value' => $last_sync_label ],
    ],
    'actions' => [
        [
            'label'   => __( 'Create experience', 'fp-esperienze' ),
            'url'     => admin_url( 'post-new.php?post_type=product' ),
            'variant' => 'primary',
        ],
        [
            'label' => __( 'Export data', 'fp-esperienze' ),
            'tag'   => 'button',
            'type'  => 'submit',
        ],
    ],
]);
```

Each helper returns `void` and immediately echoes markup so it can be used
directly within existing admin page callbacks.

## Skip link

* **Class:** `.fp-admin-skip-link`
* **PHP:** `AdminComponents::skipLink()`
* **Features:**
  - Provides a keyboard-accessible jump target bound to the primary `.fp-admin-page` container.
  - Uses design token colours for high-contrast focus styling across light/dark admin schemes.
  - Should be rendered before the main wrapper with `id="fp-admin-main-content" tabindex="-1"` to receive focus.

```php
AdminComponents::skipLink();

echo '<div class="wrap fp-admin-page" id="fp-admin-main-content" tabindex="-1">';
// Page content...
echo '</div>';
```

## Page header

* **Class:** `.fp-admin-page__header`
* **PHP:** `AdminComponents::pageHeader()`
* **Features:**
  - Groups the `h1`, supporting lead text, meta chips, and actions into a
    responsive flex container.
  - Automatically generates unique IDs for lead copy to wire `aria-describedby`
    relationships when actions are rendered.
  - Accepts inline meta entries to surface contextual data (last sync, owner,
    locale, etc.).

```html
<header class="fp-admin-page__header">
  <div class="fp-admin-page__heading">
    <h1 class="fp-admin-page__title">FP Esperienze Dashboard</h1>
    <p class="fp-admin-page__lead">Monitor bookings, vouchers, and alerts.</p>
    <div class="fp-admin-page__meta">
      <span class="fp-admin-page__meta-item">
        <span class="fp-admin-helper-text">Last sync</span>
        5 minutes ago
      </span>
    </div>
  </div>
  <div class="fp-admin-page__actions" role="group" aria-label="Page actions">
    <a class="button button-primary" href="#">Create experience</a>
    <button type="button" class="button button-secondary">Export CSV</button>
  </div>
</header>
```

## Toolbar

* **Class:** `.fp-admin-toolbar`
* **PHP:** `AdminComponents::toolbar()`
* **Features:**
  - Uses `role="toolbar"` and an accessible label for assistive technologies.
  - Supports sticky positioning via the `sticky` argument to keep bulk actions
    visible during scrolling.
  - Splits items into primary (`start`) and secondary (`end`) groups so that
    filters stay aligned left while bulk actions sit on the right.

```php
AdminComponents::toolbar([
    'title'       => __( 'Bookings', 'fp-esperienze' ),
    'description' => __( 'Filter and export your reservations.', 'fp-esperienze' ),
    'start'       => [ function () { submit_button( __( 'Apply', 'fp-esperienze' ), 'secondary', 'filter_action', false ); } ],
    'end'         => [
        [
            'label'   => __( 'Export CSV', 'fp-esperienze' ),
            'url'     => admin_url( 'admin.php?page=fp-esperienze-bookings&export=csv' ),
            'variant' => 'primary',
        ],
    ],
    'sticky'      => true,
]);
```

## Card / panel

* **Class:** `.fp-admin-card`
* **PHP:** `AdminComponents::openCard()` + `AdminComponents::closeCard()`
* **Features:**
  - Provides surface, border, radius, and elevation with consistent padding.
  - Optional `muted` modifier for secondary panels.
  - Header/footer slots for actions, metrics, and supporting meta.

```php
AdminComponents::openCard([
    'title' => __( 'Recent vouchers', 'fp-esperienze' ),
]);

// ... render table or content ...

AdminComponents::closeCard();
```

## Form row

* **Class:** `.fp-admin-form__row`
* **PHP:** `AdminComponents::formRow()`
* **Features:**
  - Handles `aria-describedby` connections for helper text and errors.
  - Adds a required marker when `required => true`.
  - Wraps arbitrary controls (strings or callables) so existing form helpers can
    be reused.

```php
AdminComponents::formRow([
    'label'       => __( 'Default duration', 'fp-esperienze' ),
    'for'         => 'fp_default_duration',
    'required'    => true,
    'description' => __( 'Displayed on booking forms and emails.', 'fp-esperienze' ),
    'error'       => $errors['fp_default_duration'] ?? '',
], function () use ( $settings ) {
    printf(
        '<input type="number" id="fp_default_duration" name="fp_default_duration" value="%s" min="1" class="small-text" />',
        esc_attr( $settings['fp_default_duration'] ?? '' )
    );
});
```

## Notice

* **Class:** `.fp-admin-notice`
* **PHP:** `AdminComponents::notice()`
* **Features:**
  - Maps `type` (`info`, `success`, `warning`, `danger`) to colour tokens with high-contrast left borders.
  - Chooses `role="alert"` automatically for destructive/warning variants and exposes `aria-live` to announce changes.
  - Emits discrete heading/message regions with IDs so helper text and follow-up actions stay connected for screen readers.

```php
AdminComponents::notice([
    'type'    => 'warning',
    'title'   => __( 'WooCommerce connection lost', 'fp-esperienze' ),
    'message' => __( 'Reconnect WooCommerce to keep booking statuses in sync.', 'fp-esperienze' ),
    'actions' => [
        [
            'label'   => __( 'Reconnect', 'fp-esperienze' ),
            'url'     => admin_url( 'admin.php?page=fp-esperienze-settings&tab=integrations' ),
            'variant' => 'primary',
        ],
    ],
]);
```

## Tab navigation

* **Class:** `.fp-admin-tab-nav`
* **PHP:** `AdminComponents::tabNav()`
* **Features:**
  - Renders `<nav>` with an accessible label and `aria-current="page"` on the
    active tab.
  - Supports counts/badges via the `count` argument.
  - Works responsively by allowing horizontal scrolling on narrow viewports.

```php
AdminComponents::tabNav([
    [
        'label'   => __( 'Upcoming', 'fp-esperienze' ),
        'href'    => admin_url( 'admin.php?page=fp-esperienze-bookings&view=upcoming' ),
        'current' => true,
    ],
    [
        'label' => __( 'Completed', 'fp-esperienze' ),
        'href'  => admin_url( 'admin.php?page=fp-esperienze-bookings&view=completed' ),
        'count' => number_format_i18n( $completed_count ),
    ],
]);
```

## Utility objects

The component stylesheet also ships reusable objects that complement the core
components:

| Utility                     | Purpose |
|-----------------------------|---------|
| `.fp-admin-badge`           | Inline status tags (with `--success`, `--warning`, `--danger`). |
| `.fp-admin-empty-state`     | Dashed container for empty results with icon slot. |
| `.fp-admin-inline-list`     | Horizontal list for meta pairs or quick filters. |
| `.fp-admin-chip-group`      | Small pill list for filters/taxonomies. |

Use these utilities inside cards or toolbars for consistent spacing and focus
treatment.

