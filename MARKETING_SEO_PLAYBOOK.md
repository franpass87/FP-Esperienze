# FP Esperienze Marketing & SEO Playbook

This playbook helps marketing teams get the most out of FP Esperienze without touching code. It turns the built-in schema, tracking, and social metadata into ready-to-run campaigns.

## 1. Launch Checklist per Experience

| Task | Owner | Notes |
|------|-------|-------|
| Add hero images (1200×800+) to the Experience gallery | Content | Surfaces in OpenGraph tags and the widget carousel |
| Populate “What’s included / excluded” fields | Content | Powers FAQ markup and enriches search snippets |
| Assign the correct meeting point | Ops | Required for Event/Trip structured data and Google Maps deep links |
| Enable at least one payment method | Ops | Checklist item must be ✅ to publish |
| Configure Google Analytics 4 ID in Setup Wizard | Marketing | Enables funnel events & conversions |
| Add campaign UTM defaults (`?utm_source=` etc.) | Marketing | Use WooCommerce > Settings > Tracking tab |

## 2. Seasonal Campaign Recipes

### Flash Tour Drop (48 hours)
- Duplicate the experience, switch the product status to “Scheduled”, and add a fixed-date schedule via the Schedule Builder.
- Enable Brevo integration and select a dedicated contact list for the flash sale.
- Use the new CLI report to confirm daily conversions:
  ```bash
  wp fp-esperienze onboarding daily-report --days=2
  ```
- Update the widget embed on partner sites with `?theme=dark` to visually differentiate the limited offer.

### Evergreen SEO Refresh (Quarterly)
1. Export reviews from the Reports screen to capture fresh testimonial quotes.
2. Refresh the long description and “What’s Included” bullet points.
3. Verify Event schema is still valid using the `SEO` admin tab → Rich Results checker shortcut.
4. Regenerate social preview (Admin → SEO → Social Cards) to align with the updated copy.

## 3. Structured Data Game Plan

| Scenario | Schema Type | Action |
|----------|-------------|--------|
| Recurring departures | `Event` | Ensure schedules are active and capacity set; schema renders automatically |
| Open-dated voucher | `Product` + `Offer` | Highlight gift experience content and attach default price |
| Multi-day trip | `Trip` | Tag the product with `multi-day` category to force Trip schema |

Use [Google Rich Results Test](https://search.google.com/test/rich-results) weekly on your top 5 experiences. No manual JSON-LD editing is required—the plugin assembles the graph using meeting points, schedules, and pricing.

## 4. Conversion Tracking Toolkit

| Metric | Where to Find | Tips |
|--------|---------------|------|
| Booking revenue by day | `wp fp-esperienze onboarding daily-report --days=7` | Pipe output into Slack via scheduled cron |
| Funnel analytics | Google Analytics 4 (Enhanced eCommerce) | Events: `view_item`, `add_to_cart`, `purchase` are automatically tagged |
| Paid media performance | Google Ads + Meta Pixel integrations | Configure in Setup Wizard step 2 |
| Email engagement | Brevo lists | Use the “Flash Tour” automation template for time-boxed drops |

## 5. Partner / OTA Enablement Kit

Share these assets with distribution partners:

- **Widget Embed Snippets** – see `WIDGET_INTEGRATION_GUIDE.md` for iframe templates with auto-resize and return URLs.
- **Style Tokens** – override CSS variables from the widget using a parent stylesheet (`--fp-color-primary`, `--fp-font-family`).
- **Messaging Hooks** – listen for `fp_widget_booking_success` events to trigger thank-you modals or upsell flows on partner sites.

## 6. Reporting Cadence

| Frequency | Action |
|-----------|--------|
| Daily | `wp fp-esperienze onboarding daily-report` to monitor bookings & revenue |
| Weekly | Review “Reports → Availability Heatmap” for load factor anomalies |
| Monthly | Export “Reports → Conversion Breakdown” and share with finance |
| Quarterly | Run the Guided Onboarding checklist to ensure new staff followed all prerequisites |

## 7. Asset Templates

- **Thank-you Page Copy:** “Grazie per aver prenotato *{experience_name}*. Riceverai un’email con i dettagli del punto d’incontro in {booking_language}.”
- **Social Caption Framework:** `[{city}] {experience_type} · {duration} · {price}` + CTA “Prenota ora → {widget_url}``
- **Email Reminder Subject:** `Domani alle {slot_time}: ci vediamo per {experience_name}!`

Keep this playbook in your internal knowledge base and adapt it per market. The new onboarding CLI commands allow you to automate data pulls, while the wizard’s checklist guarantees operations and marketing stay aligned.
