# Manual Test: Mobile Push Notifications

## Configuration
1. Create a Firebase project (or reuse an existing one) and enable **Cloud Messaging**.
2. In the Firebase console, open **Project settings → Cloud Messaging** and copy the following values:
   - **Server key** (Legacy server key)
   - **Project ID**
3. In WordPress, store the credentials in the option `fp_esperienze_mobile_notifications` as an associative array:
   ```bash
   wp option update fp_esperienze_mobile_notifications '{"provider":"fcm","server_key":"YOUR_SERVER_KEY","project_id":"YOUR_PROJECT_ID"}'
   ```
   Replace `YOUR_SERVER_KEY` and `YOUR_PROJECT_ID` with the values collected from Firebase.
4. Ensure WordPress can reach `https://fcm.googleapis.com/` (no firewall restrictions).
5. Enable `WP_DEBUG_LOG` to capture push delivery logs in case of failures.

## Manual Test: Delivery to a Real Device
1. Authenticate as a mobile user and register a real device token via
   ```bash
   curl -X POST \
     -H 'Content-Type: application/json' \
     -H 'Authorization: Bearer <MOBILE_JWT>' \
     -d '{"token":"<DEVICE_TOKEN>","platform":"android"}' \
     https://example.com/wp-json/fp-esperienze/v2/mobile/notifications/register
   ```
   Replace `<MOBILE_JWT>` with a valid mobile access token and `<DEVICE_TOKEN>` with the device token from the Firebase SDK (Android/iOS).
2. As a staff user, trigger a notification:
   ```bash
   curl -X POST \
     -H 'Content-Type: application/json' \
     -H 'Authorization: Bearer <STAFF_JWT>' \
     -d '{"recipient_id":123,"title":"Test push","message":"Body text","data":{"booking_id":456,"url":"https://example.com"},"priority":"high"}' \
     https://example.com/wp-json/fp-esperienze/v2/mobile/notifications/send
   ```
   Update `recipient_id`, `booking_id` and the URL to match existing data.
3. Confirm the API returns `{"success": true, "message": "Notification sent successfully"}` and that the device receives the push notification with the provided title, body, data payload and high priority.
4. Inspect the WordPress debug log for lines starting with `FP Esperienze Push:` if the device does **not** receive the notification. The log entries include the token, HTTP status, or Firebase error code.
5. Repeat the request with an invalid/expired token and verify that the response is a `WP_Error` (HTTP 410 or 500) and that the token is removed from the user meta `_push_notification_tokens`.
6. Confirm that a user with no tokens causes a `push_no_tokens` error and that the response propagates to the REST client.
