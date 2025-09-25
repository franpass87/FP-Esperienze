# FP Esperienze Onboarding Automation Guide

Use these recipes to validate FP Esperienze installations and keep critical workflows monitored without manual testing.

## 0.1. In-app reminders for pending setup

Administrators with the `manage_woocommerce` capability now see a persistent FP Esperienze onboarding notice on the WordPress dashboard and plugin pages whenever checklist items remain unfinished. Use the buttons to relaunch the setup wizard or jump to the dashboard widget, or click **Remind me later** to snooze the alert for a week while you finish other tasks.

## 0. Built-in Operational Alerts

1. Navigate to **FP Esperienze → Operational Alerts** in the WordPress admin.
2. Enable the email digest and/or provide a Slack webhook URL.
3. Set the booking threshold, lookback window, and preferred send hour (site timezone).
4. Save the form and click **Send digest now** to confirm delivery.

The plugin automatically schedules a daily event based on the selected hour. Digests include booking totals, participant counts, revenue, and a warning when the minimum booking threshold is not met.

## 1. Daily Booking Digest (Cron)

```bash
# /etc/cron.d/fp-esperienze
0 7 * * * www-data cd /var/www/html && wp fp-esperienze onboarding send-digest --channel=all --days=1
```

The digest honours the configured channels. If neither email nor Slack is enabled the command exits with a warning so your scheduler can flag the issue.

## 2. CI Smoke Test Before Deploying Themes

```yaml
# .github/workflows/fp-onboarding.yml
name: FP Esperienze onboarding audit
on:
  workflow_dispatch:
  push:
    branches: [ main ]
    paths:
      - 'wp-content/themes/**'

jobs:
  checklist:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
      - name: Install WP-CLI
        run: curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x wp-cli.phar && sudo mv wp-cli.phar /usr/local/bin/wp
      - name: Run onboarding checklist
        run: wp fp-esperienze onboarding checklist --format=json
```

Parse the JSON output to enforce minimum completion thresholds (e.g., fail the pipeline if payment gateways are disabled).

## 3. Demo Content Refresh for Training Environments

```bash
wp site empty --yes
wp fp-esperienze onboarding seed-data
wp option update fp_esperienze_notifications '{"staff_notifications_enabled":1,"staff_emails":"academy@example.com"}'
```

Pair this with `wp theme activate` commands to rehydrate a sandbox for onboarding new staff.

## 4. Slack Notification on Drops

```bash
#!/usr/bin/env bash
set -euo pipefail
REPORT=$(wp fp-esperienze onboarding daily-report --days=1 --format=json)
BOOKINGS=$(echo "$REPORT" | jq '.overall.total_bookings')
if [ "$BOOKINGS" -lt 1 ]; then
  curl -X POST -H 'Content-type: application/json' \
    --data "{\"text\":\"⚠️ Nessuna prenotazione registrata nelle ultime 24h. Verificare campagne e disponibilità.\"}" \
    https://hooks.slack.com/services/T0000/B0000/XXXX
fi
```

Automate this via cron to catch regressions or payment issues early.

## 5. QA Shortcuts

- `wp fp-esperienze onboarding checklist` before shipping new product data.
- `wp fp-esperienze onboarding seed-data` on staging to populate the Schedule Builder with realistic content.
- `wp fp-esperienze production-check` to confirm system prerequisites are still satisfied.
- `wp fp-esperienze operations health-check` to capture digest status, cron readiness, and pending onboarding tasks.

Combine these commands with your existing deployment scripts to replace repetitive manual smoke tests from the legacy checklist documents.

## 6. Operations Health Monitor

```bash
# Collect a JSON report for dashboards or alerting
wp fp-esperienze operations health-check --format=json > /var/log/fp-esperienze-health.json
```

Parse the summary counters to trigger alerts (for example, fail the job when `fail` > 0 or notify the support team when `warn` increases between runs).
