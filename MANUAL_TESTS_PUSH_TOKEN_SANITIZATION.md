# Manual Test: Push Token Sanitization

## Allowed Characters
- Letters A-Z, a-z
- Numbers 0-9
- Colon (:)
- Dash (-)
- Period (.)
- Underscore (_)

## Test Steps
1. Obtain a valid authentication token for a mobile user.
2. Acquire a real iOS push notification token from a device.
3. Send a `POST` request to `/wp-json/fp-esperienze/v2/mobile/notifications/register` with:
   - `token` set to the iOS token
   - `platform` set to `ios`
   - Authorization header set to `Bearer YOUR_AUTH_TOKEN`
4. Confirm the response is `{"success": true, "message": "Push token registered successfully"}`.
5. Verify the token is stored in user meta (`_push_notification_token`).
6. Repeat steps 2-5 using an Android device and `platform` set to `android`.
7. Optionally send a token containing disallowed characters and confirm they are stripped in storage.
