# Manual Tests – Rate Limiting

## Bookings API
1. Send 30 quick `GET` requests to `/wp-json/fp-exp/v1/bookings` while authenticated.
2. Send one additional request and verify it returns HTTP `429` with a `rate_limit_exceeded` message.
3. Confirm the response includes `X-RateLimit-Limit` and `X-RateLimit-Remaining` headers.

## ICS API
1. Request `/wp-json/fp-esperienze/v1/ics/product/123?days=1` more than 30 times within a minute.
2. The 31st request should respond with HTTP `429` and the same rate limit headers.
3. After waiting one minute, requests should succeed again.
