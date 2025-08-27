# Manual Tests for ICS Calendar and Staff Notifications

This document provides comprehensive manual testing procedures for the ICS calendar integration and staff notification features.

## Prerequisites

1. WordPress site with WooCommerce installed
2. FP Esperienze plugin activated
3. At least one Experience product created
4. HMAC secret generated (Settings > Gift Vouchers > Regenerate Secret)
5. Test email account for receiving notifications

## Test 1: Staff Notification Settings Configuration

### Objective
Verify that staff notification settings can be configured correctly.

### Steps
1. Log in to WordPress admin
2. Navigate to **FP Esperienze > Settings**
3. Click on the **"Notifications"** tab
4. Enable **"Send email notifications to staff when new bookings are made"**
5. In the **"Staff Email Addresses"** field, enter:
   ```
   admin@example.com
   manager@example.com
   staff@example.com
   ```
6. Enable **"Attach ICS calendar files to order completion emails"**
7. Click **"Save Notification Settings"**

### Expected Results
- Settings save successfully with confirmation message
- Invalid email addresses show error messages
- Valid emails are preserved in the textarea

---

## Test 2: ICS Calendar Attachment to Customer Emails

### Objective
Verify that ICS calendar files are attached to order completion emails.

### Steps
1. Create a new Experience product with:
   - Title: "Test Experience Tour"
   - Meeting point configured
   - Schedule with available slots
2. Add the experience to cart as a customer
3. Fill in booking details (date, time, participants)
4. Complete the checkout process
5. As admin, change order status to **"Completed"**
6. Check the customer's email

### Expected Results
- Customer receives order completion email
- Email contains ICS calendar attachment (`.ics` file)
- ICS file contains:
  - Correct event title with participant count
  - Correct date and time (in proper timezone)
  - Meeting point as location (if configured)
  - Booking details in description
  - Unique UID based on booking ID

### Calendar Import Test
1. Download the ICS attachment
2. Import into Google Calendar:
   - Go to Google Calendar
   - Click "+" next to "Other calendars"
   - Select "Import"
   - Upload the ICS file
3. Import into Apple Calendar:
   - Double-click the ICS file
   - Confirm import in Calendar app
4. Import into Outlook:
   - Open Outlook
   - File > Open & Export > Import/Export
   - Select the ICS file

### Expected Results for Calendar Import
- Event appears correctly in all calendar applications
- Date, time, and timezone are accurate
- Event title includes experience name and participant count
- Location shows meeting point details
- Description contains booking information
- Event can be edited/deleted in calendar apps

---

## Test 3: Staff Email Notifications

### Objective
Verify that staff members receive detailed notifications for new bookings.

### Steps
1. Ensure staff notifications are enabled (Test 1)
2. Create a test booking following Test 2 steps 1-4
3. Change order status to **"Processing"** or **"Completed"**
4. Check configured staff email addresses

### Expected Results
Staff email should contain:

**Subject**: `[Site Name] New Booking: Test Experience Tour`

**Content**:
- Experience name and details
- Booking date and time (formatted according to WordPress settings)
- Number of participants (adults/children)
- Meeting point information (if configured)
- Booking ID and Order ID
- Customer information:
  - Name
  - Email address
  - Phone number (if provided)
  - Customer notes (if any)
- Direct links to:
  - View Bookings admin page
  - View Order in WooCommerce

**Email Format**:
- HTML formatted with proper styling
- Clear sections for booking and customer details
- Professional appearance matching site branding

---

## Test 4: REST API Endpoints

### Objective
Test all public ICS calendar REST API endpoints.

### Test 4.1: Product Calendar Endpoint

**Endpoint**: `/wp-json/fp-esperienze/v1/ics/product/{product_id}`

1. Get the ID of an Experience product
2. Visit: `https://yoursite.com/wp-json/fp-esperienze/v1/ics/product/123`
3. Replace `123` with actual product ID

**Expected Results**:
- Returns ICS calendar content with `text/calendar` content type
- Contains events for next 30 days of available slots
- Each event shows:
  - Experience name
  - Available spots count
  - Correct date/time
  - Status: TENTATIVE
- File downloads as `{product-name}.ics`

### Test 4.2: User Bookings Endpoint

**Endpoint**: `/wp-json/fp-esperienze/v1/ics/user/{user_id}`

1. Log in as a user with confirmed bookings
2. Get the user ID from WordPress admin
3. Visit: `https://yoursite.com/wp-json/fp-esperienze/v1/ics/user/456`
4. Replace `456` with actual user ID

**Expected Results**:
- Requires authentication (returns 401 if not logged in)
- Users can only access their own bookings
- Admins can access any user's bookings
- Returns ICS with confirmed future bookings only
- Contains no PII in descriptions
- File downloads as `my-bookings.ics`

### Test 4.3: Single Booking Endpoint

**Endpoint**: `/wp-json/fp-esperienze/v1/ics/booking/{booking_id}?token={token}`

1. Create a booking and note the booking ID
2. Find the access token in order notes (automatically added)
3. Visit: `https://yoursite.com/wp-json/fp-esperienze/v1/ics/booking/789?token=abc123`
4. Replace with actual booking ID and token

**Expected Results**:
- Public endpoint (no authentication required)
- Requires valid token (returns 403 if invalid)
- Returns ICS for single booking
- Contains full booking details
- File downloads as `booking-{id}.ics`

---

## Test 5: Error Handling

### Objective
Verify proper error handling for various scenarios.

### Test Cases

1. **Invalid Product ID**:
   - Visit: `/wp-json/fp-esperienze/v1/ics/product/99999`
   - Expected: 404 error with "Product not found" message

2. **Non-Experience Product**:
   - Visit endpoint with regular WooCommerce product ID
   - Expected: 404 error with "not an experience" message

3. **Invalid Booking Token**:
   - Visit booking endpoint with wrong token
   - Expected: 403 error with "Invalid access token" message

4. **Non-existent User**:
   - Visit user endpoint with invalid user ID
   - Expected: 404 error with "User not found" message

5. **Unauthorized User Access**:
   - Try to access another user's bookings while logged in
   - Expected: 403 error with "insufficient permissions" message

---

## Test 6: Security and Privacy

### Objective
Ensure no sensitive information is exposed through public endpoints.

### Security Checks

1. **Public Product Calendar**:
   - Should not contain customer names
   - Should not contain personal information
   - Should only show available slots count

2. **User Bookings Calendar**:
   - Should only be accessible to booking owner or admin
   - Should not expose other users' information

3. **Single Booking Access**:
   - Token should be cryptographically secure
   - Invalid tokens should be rejected
   - Tokens should not be guessable

4. **File Storage**:
   - Temporary ICS files should be stored securely
   - Direct access to ICS directory should be blocked
   - Files should be cleaned up after email sending

---

## Test 7: Performance and Compatibility

### Objective
Verify performance and compatibility across different environments.

### Performance Tests

1. **Large Product Calendar**:
   - Test with product having many daily slots
   - Verify response time remains reasonable
   - Check memory usage during generation

2. **Multiple Concurrent Requests**:
   - Access endpoints simultaneously from multiple browsers
   - Verify no race conditions or errors

### Compatibility Tests

1. **Calendar Applications**:
   - Test import in Google Calendar (web and mobile)
   - Test import in Apple Calendar (macOS and iOS)
   - Test import in Outlook (desktop and web)
   - Test import in Thunderbird

2. **Email Clients**:
   - Test ICS attachments in Gmail
   - Test ICS attachments in Outlook
   - Test ICS attachments in Apple Mail
   - Test ICS attachments in mobile email apps

3. **Timezone Handling**:
   - Test with different WordPress timezone settings
   - Verify events appear at correct local times
   - Test daylight saving time transitions

---

## Test 8: Integration Testing

### Objective
Verify integration with existing FP Esperienze features.

### Integration Points

1. **Booking Creation**:
   - Verify notifications trigger when bookings are created via orders
   - Test with gift voucher redemptions
   - Test with different order statuses

2. **Meeting Points**:
   - Verify meeting point information appears in ICS location field
   - Test with bookings that have no meeting point

3. **Multi-language Support**:
   - Test with different WordPress languages
   - Verify email content uses correct language
   - Check ICS content formatting with non-ASCII characters

4. **Caching Compatibility**:
   - Test with WordPress caching plugins enabled
   - Verify REST endpoints bypass cache appropriately
   - Check email sending with object caching

---

## Troubleshooting Common Issues

### Issue: No ICS Attachment in Email
**Solutions**:
1. Check if ICS attachments are enabled in settings
2. Verify order contains experience products
3. Check email server supports attachments
4. Look for errors in WordPress debug log

### Issue: Staff Notifications Not Sent
**Solutions**:
1. Verify staff notifications are enabled
2. Check staff email addresses are valid
3. Test WordPress wp_mail() function works
4. Check for SMTP configuration issues

### Issue: REST API Returns 404
**Solutions**:
1. Check WordPress permalink structure
2. Verify REST API is enabled
3. Clear any caching
4. Check for conflicting plugins

### Issue: Calendar Import Fails
**Solutions**:
1. Verify ICS content follows standard format
2. Check for special characters in event data
3. Try different calendar applications
4. Validate timezone handling

---

## Success Criteria

All tests are considered successful when:

✅ **Settings Configuration**:
- Notification settings save correctly
- Email validation works properly
- Settings persist between page loads

✅ **Customer Experience**:
- ICS files attach to order emails
- Calendar imports work in major applications
- Event details are accurate and complete

✅ **Staff Notifications**:
- Emails sent to all configured addresses
- Content includes all necessary booking information
- Links to admin pages work correctly

✅ **Public API**:
- All endpoints return correct data
- Error handling works properly
- Security measures prevent unauthorized access

✅ **Performance**:
- Response times are reasonable
- No memory leaks or errors
- Concurrent access works correctly

✅ **Compatibility**:
- Works with major calendar applications
- Compatible with different email clients
- Proper timezone handling across environments