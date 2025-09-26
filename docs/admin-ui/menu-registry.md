# Menu Registry Overview

The `FP\Esperienze\Admin\MenuRegistry` centralises configuration for the plugin's admin
menus. Each request the registry collects canonical submenu definitions, group
separators, and legacy slug aliases before `MenuManager::addAdminMenu()` renders the
tree.

## Canonical configuration

- `setTopLevel()` declares the top-level container (title, capability, icon, position).
- `registerPage()` stores submenu entries with order, capability, callback, optional
  `load_actions`, and any legacy aliases.
- `registerSeparator()` injects inert headings ("Operations", "Configuration") to
  group related pages without providing clickable targets.

`MenuManager::addAdminMenu()` iterates the ordered entries, creates the WordPress menu
pages, and hooks any `load-{$hook}` callbacks for screen options or contextual help.
Hidden pages (e.g., alias stubs) are removed immediately after registration.

## Legacy slug handling

`registerPage()` accepts an `aliases` array. Each alias becomes a hidden submenu entry
that triggers `MenuRegistry::redirectAlias()` so bookmarked `page=` URLs redirect to
the canonical slug while still firing legacy `load-{$hook}` actions for third-party
integrations.

Current alias map:

| Legacy slug | Canonical slug |
| --- | --- |
| `fp-esperienze-extras` | `fp-esperienze-addons` |
| `fp-esperienze-vouchers` | `fp-esperienze-gift-vouchers` |
| `fp-esperienze-closures` | `fp-esperienze-availability` |
| `fp-esperienze-integration-toolkit` | `fp-esperienze-developer-tools` |
| `fp-esperienze-operational-alerts` | `fp-esperienze-notifications` |
| `fp-esperienze-translation-help` | `fp-esperienze-localization` |
| `fp-esperienze-system-status` | `fp-esperienze-status` |

## External registrations

Supporting classes (System Status, Operational Alerts, Translation Help, Setup
Wizard, Feature Demo) now call `MenuRegistry::instance()->registerPage()` inside their
constructors. This keeps their rendering logic intact while delegating menu
registration to a single orchestrator and ensuring capability checks and order are
consistent with the IA plan.
