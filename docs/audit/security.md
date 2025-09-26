# FP Esperienze Security Hardening (Phase 4)

## Scope
- Mobile REST booking creation and related helpers.
- Core booking manager persistence pipeline for extras payloads.

## Findings
1. **Unsanitized extras payload in mobile bookings:** The mobile `createMobileBooking` endpoint trusted the client-provided extras array and forwarded it directly to the booking layer. Attackers could inject unexpected structures, excessive quantities, or reference unknown extras which were later normalised with best-effort logic, risking corrupted totals and polluted booking metadata.
2. **Participant payload leniency:** Mobile participant data accepted arbitrary strings and negative values that were coerced during persistence. This created an opportunity for confusing audit logs and rate limits tied to participant counts.
3. **Booking manager trusting upstream extras arrays:** `BookingManager::createCustomerBooking()` (and the WooCommerce order conversion path) received extras arrays without normalising them before price calculations or persistence, relying entirely on later `saveBookingExtras()` cleanup.

## Remediation
- Added strict sanitisation for mobile booking participants and extras. Unknown structures now trigger explicit REST errors and extras are hydrated with canonical metadata from `ExtraManager` before booking creation.
- Hardened validation to reject tampered extras payloads early in the request lifecycle.
- Centralised extras sanitisation inside `BookingManager` so every booking creation path (REST + WooCommerce order conversion) normalises selections, caps list length, and prevents forged metadata from influencing totals or saved records.

## Verification
- `php -l includes/REST/MobileAPIManager.php`
- `php -l includes/Booking/BookingManager.php`

The security fixes remove trust in user-controlled payloads for extras/participants while preserving backwards compatibility for legitimate clients.
