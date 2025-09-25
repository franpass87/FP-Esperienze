# Integration Toolkit

The **FP Esperienze → Integration Toolkit** admin page centralises the snippets needed to embed the booking widget on partner websites, landing pages, and headless front-ends.

## Sections

### Quick Embed
- Copy an iframe wrapper with the correct REST endpoint for your site (pre-filled with the newest experience ID when available).
- Paste into WordPress, Webflow, Squarespace, HubSpot, or any CMS that accepts raw HTML.
- Append `?theme=dark` or `?return_url=https://partner.com/thanks` to control theming and post-booking redirects.

### Auto-Height Integration
- Provides the `postMessage` listener used by the widget to announce its height.
- Keeps embeds responsive when content expands (e.g., extras or translated text).
- Emits events:
  - `fp_widget_ready`
  - `fp_widget_height_change`
  - `fp_widget_checkout`
  - `fp_widget_booking_success`

### Theme Tokens
- Copy CSS variables (`--fp-esperienze-widget-*`) to align partner pages with your brand.
- Drop them into a global stylesheet or CMS design settings.

## Copy Buttons

Each snippet includes a "Copy" button powered by the `assets/js/integration-toolkit.js` helper. The script supports both the Clipboard API and a fallback method, ensuring the buttons work on older browsers used inside admin panels.

## Related Resources

- Full recipes live in [`WIDGET_INTEGRATION_GUIDE.md`](WIDGET_INTEGRATION_GUIDE.md) for teams that prefer documentation-first workflows.
- For automated smoke tests and digest monitoring combine the Integration Toolkit with the WP-CLI commands documented in [`ONBOARDING_AUTOMATION.md`](ONBOARDING_AUTOMATION.md) and the new `wp fp-esperienze operations health-check` task.
