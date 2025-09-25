# FP Esperienze QA Automation

The `wp fp-esperienze qa` command turns the manual smoke tests into repeatable
checks that can run after deployments, inside CI pipelines, or on staging sites
before stakeholders sign off.

## Available Checks

| ID                       | Description |
|--------------------------|-------------|
| `experience_product_type` | Verifies WooCommerce has the `experience` product type registered so booking creation works. |
| `onboarding_checklist`    | Ensures the onboarding helper reports that core prerequisites (meeting points, products, schedules, payments, emails) are complete. |
| `demo_content`            | Confirms that the demo meeting point, product, and schedule seed is present for training walkthroughs. |
| `digest_schedule`         | Validates that at least one operational digest channel is configured and cron has a future run scheduled. |
| `rest_routes`             | Checks that the public REST API routes powering the widget and partner integrations are registered. |

Each check returns one of three severities:

- `PASS` – everything looks good.
- `WARNING` – not a blocker, but worth reviewing before launch.
- `FAIL` – must be resolved before promoting to production.

## Usage Examples

```bash
# Run every check and exit with code 0/1 depending on pass/fail
wp fp-esperienze qa run

# Generate machine-readable output for dashboards or alerting
wp fp-esperienze qa run --format=json > qa-report.json

# Execute a subset of checks (comma separated IDs)
wp fp-esperienze qa run --only=experience_product_type,rest_routes

# List the checks with their descriptions
wp fp-esperienze qa list
```

## CI Integration Tips

1. **WordPress bootstrap** – ensure the command runs within a WordPress
   environment with WooCommerce active (e.g., `wp eval-file wp-load.php`).
2. **Schedule verification** – the digest check uses `wp_next_scheduled()`. In
   containers where WP-Cron is disabled, schedule a manual event before running
   the command or ignore the check via `--only`.
3. **Baseline demo data** – seed the optional demo content in staging to keep
   the checklist and REST checks deterministic.
4. **Alerting** – parse the JSON output and raise alerts when the `overall_status`
   is `warning` or `fail`.
5. **Continuous monitoring** – poll `wp-json/fp-exp/v1/system-status` with an
   authenticated request to combine the QA signal with onboarding progress,
   production readiness, and digest scheduling details.
6. **Site Health dashboard** – surface the same insights in WordPress itself via
   Tools → Site Health, which now lists FP Esperienze tests for dependencies,
   onboarding progress, operational alerts, and production readiness.

Automating the manual QA list reduces human error and gives instant feedback on
whether the plugin is ready for merchants and partners.
