# Copilot Instructions for FP Esperienze

Goal: WordPress + WooCommerce plugin named "FP Esperienze" (author: Francesco Passeri), product type "experience" con: slot ricorrenti, meeting point, extra, voucher regalo (PDF+QR), shortcode archivio, REST availability, template single stile GetYourGuide, pagine admin (Dashboard, Prenotazioni, Meeting Point, Extra, Voucher, Chiusure, Impostazioni), integrazioni GA4/Ads/Meta/Brevo/Google Places (reviews).

Constraints & Stack:
- PHP >= 8.1, WP >= 6.5, WooCommerce >= 8
- PSR-4 namespace FP\Esperienze\
- Struttura cartelle già pianificata (includes/Core, ProductType, Admin, Booking, REST, Frontend, Data, PDF, Integrations, templates, assets).
- Sicurezza: sanitize/escape, nonce, capabilities.
- DB: tabelle personalizzate per meeting_points, extras, schedules, overrides, bookings, vouchers.
- Text-domain: fp-esperienze; slug: fp-esperienze.

Definition of Done per ogni feature:
- Codice + docstring essenziale + hook/filters elencati nel PR.
- Test manuali descritti nel PR (passi riproducibili).
- Niente dipendenze non necessarie.

PR style:
- Branch feature/*, PR piccoli e auto-contenuti, messaggi commit chiari.
