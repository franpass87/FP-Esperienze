# Manual Tests – Auto Translation

## Endpoint Validation
1. Navigate to **Settings > Auto Translation**.
2. Enter an invalid endpoint like `not-a-url` and save.
3. Trigger a translation (e.g., translate a booking name).
4. Confirm the plugin falls back to the default `https://libretranslate.de/translate` and returns a result.

## Fast Failure on Unreachable Endpoint
1. In **Settings > Auto Translation**, set the endpoint to `https://127.0.0.1:9/translate`.
2. Trigger a translation request.
3. Verify the request fails within 10 seconds and the original text is returned.
4. Check logs for an `AutoTranslator request error` entry.

> Only configure trusted translation endpoints to avoid security risks.
