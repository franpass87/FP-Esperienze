# Manual Test Cases for Reschedule and Refund Feature

This document provides step-by-step manual test cases to verify the reschedule and cancellation functionality with rules validation.

## Prerequisites

Before running these tests, ensure:
1. WordPress site with WooCommerce and FP Esperienze plugin is active
2. At least one Experience product is created and configured
3. At least one confirmed booking exists for testing
4. Admin user has `manage_fp_esperienze` capability

## Test Case 1: Product Cancellation Rules Configuration

### Purpose
Verify that cancellation rules can be configured in the Experience product settings.

### Steps to Test

1. **Navigate to Product Admin**:
   - Go to WooCommerce > Products
   - Edit an existing Experience product or create a new one
   - Go to the "Experience" tab

2. **Configure Basic Settings**:
   - Set "Booking Cutoff (minutes)": 120 (2 hours)
   - Save and verify the field is saved

3. **Configure Cancellation Rules**:
   - Set "Free Cancellation Until (minutes)": 1440 (24 hours)
   - Set "Cancellation Fee (%)": 20
   - Set "No-Show Policy": "Partial refund (use cancellation fee %)"
   - Save the product

4. **Verify Configuration**:
   - Reload the product edit page
   - Confirm all cancellation rule fields retain their values

### Expected Results
- ✅ All cancellation rule fields are visible in the Experience tab
- ✅ Field values are saved and persist after page reload
- ✅ No PHP errors in admin area
- ✅ Fields have appropriate help text and validation

---

## Test Case 2: Successful Booking Reschedule

### Purpose
Verify that admin can successfully reschedule a booking to a new date/time slot with available capacity.

### Steps to Test

1. **Create Test Booking**:
   - Go to the product frontend and create a booking for tomorrow with 2 adults
   - Complete the order and ensure booking status is "confirmed"
   - Note the booking ID and current date/time

2. **Navigate to Bookings Admin**:
   - Go to FP Esperienze > Bookings
   - Find the test booking in the list
   - Verify it shows "Reschedule" and "Cancel" buttons

3. **Initiate Reschedule**:
   - Click the "Reschedule" button for the test booking
   - Verify the reschedule modal opens
   - Confirm current booking details are displayed

4. **Select New Date**:
   - Choose a date 3 days in the future
   - Verify time slots load automatically
   - Select an available time slot
   - Add admin notes: "Rescheduled at customer request"

5. **Complete Reschedule**:
   - Click "Reschedule Booking"
   - Verify success message appears
   - Confirm page reloads and booking shows new date/time

6. **Verify Email Notification**:
   - Check if reschedule email was sent to customer
   - Verify email contains old and new date/time information

### Expected Results
- ✅ Reschedule modal opens with correct data
- ✅ New date selector shows available dates
- ✅ Time slots load dynamically when date changes
- ✅ Only available time slots are shown
- ✅ Booking is updated with new date/time
- ✅ Admin notes are saved
- ✅ Customer receives reschedule notification email
- ✅ Cache is invalidated for both old and new slots

---

## Test Case 3: Reschedule Denied - Cutoff Time

### Purpose
Verify that reschedule is denied when the new slot is within the cutoff time.

### Steps to Test

1. **Configure Short Cutoff**:
   - Edit the Experience product
   - Set "Booking Cutoff (minutes)" to 60 (1 hour)
   - Save the product

2. **Attempt Invalid Reschedule**:
   - Go to FP Esperienze > Bookings
   - Try to reschedule a booking to a slot that starts in 30 minutes
   - Click "Reschedule Booking"

### Expected Results
- ✅ Error message appears: "This time slot is too close to departure. Please book at least 60 minutes in advance."
- ✅ Booking is not modified
- ✅ Modal remains open to allow correction

---

## Test Case 4: Reschedule Denied - No Capacity

### Purpose
Verify that reschedule is denied when the new slot doesn't have enough capacity.

### Steps to Test

1. **Create Full Capacity Scenario**:
   - Create multiple bookings for the same time slot until capacity is nearly full
   - Leave only 1 spot available

2. **Attempt Reschedule with Too Many Participants**:
   - Try to reschedule a booking with 2 adults to the nearly-full slot
   - Complete the reschedule form

### Expected Results
- ✅ Error message appears: "Not enough capacity. Only 1 spots available."
- ✅ Booking is not modified
- ✅ Modal remains open to allow selection of different slot

---

## Test Case 5: Free Cancellation

### Purpose
Verify that bookings can be cancelled for free within the free cancellation period.

### Steps to Test

1. **Configure Cancellation Rules**:
   - Set "Free Cancellation Until (minutes)": 1440 (24 hours)
   - Set "Cancellation Fee (%)": 25
   - Save the product

2. **Create Future Booking**:
   - Create a booking for 48 hours in the future
   - Ensure booking is confirmed

3. **Check Cancellation Rules**:
   - Go to FP Esperienze > Bookings
   - Click "Cancel" button for the test booking
   - Verify the cancellation info modal shows free cancellation is available

4. **Cancel the Booking**:
   - Enter cancellation reason: "Customer request"
   - Click "Confirm Cancellation"
   - Verify success message

5. **Verify Cancellation**:
   - Confirm booking status changed to "cancelled"
   - Check admin notes include cancellation reason
   - Verify capacity is released for the original slot

### Expected Results
- ✅ Cancellation modal shows "Free cancellation available"
- ✅ Booking status changes to "cancelled"
- ✅ Admin notes include cancellation reason
- ✅ Capacity is released and available for new bookings
- ✅ Cache invalidation occurs for the cancelled slot

---

## Test Case 6: Paid Cancellation with Fee

### Purpose
Verify that cancellation fee is calculated correctly when outside free cancellation period.

### Steps to Test

1. **Create Near-Future Booking**:
   - Create a booking for 12 hours in the future (within 24-hour free cancellation window)
   - Alternatively, modify booking date in database to simulate time passage

2. **Attempt Cancellation**:
   - Go to FP Esperienze > Bookings
   - Click "Cancel" button
   - Verify cancellation info shows fee percentage

3. **Review Cancellation Terms**:
   - Modal should display: "Cancellation fee: 25%"
   - Confirm cancellation form is still available

4. **Cancel with Fee**:
   - Enter reason and confirm cancellation
   - Verify booking is cancelled

### Expected Results
- ✅ Cancellation modal shows correct fee percentage
- ✅ Admin can still cancel the booking
- ✅ Fee information is clearly displayed
- ✅ Booking status changes to "cancelled"

---

## Test Case 7: Cancel Non-Confirmed Booking

### Purpose
Verify that only confirmed bookings can be rescheduled or cancelled.

### Steps to Test

1. **Create Cancelled Booking**:
   - Manually update a booking status to "cancelled" in database
   - Or create a refunded order to generate cancelled booking

2. **Check Admin Interface**:
   - Go to FP Esperienze > Bookings
   - Find the cancelled booking
   - Verify no action buttons are shown

### Expected Results
- ✅ Cancelled bookings show "No actions available"
- ✅ Reschedule and Cancel buttons are not displayed
- ✅ Status is clearly marked as cancelled

---

## Test Case 8: AJAX Error Handling

### Purpose
Verify that AJAX errors are handled gracefully.

### Steps to Test

1. **Simulate Network Error**:
   - Open browser developer tools
   - Go to Network tab and enable "Offline" mode
   - Try to reschedule a booking

2. **Simulate Server Error**:
   - Temporarily rename the AJAX handler method in MenuManager.php
   - Try to reschedule a booking

### Expected Results
- ✅ User-friendly error messages are displayed
- ✅ No JavaScript console errors
- ✅ Modal remains open for retry
- ✅ System gracefully handles AJAX failures

---

## Test Case 9: Capacity Validation Integration

### Purpose
Verify that reschedule integrates correctly with existing capacity system.

### Steps to Test

1. **Check Real-Time Availability**:
   - Create booking for a slot with limited capacity
   - Try to reschedule another booking to same slot
   - Verify real-time capacity checking

2. **Verify Cache Invalidation**:
   - Complete a successful reschedule
   - Check that availability API reflects updated capacity
   - Verify frontend booking widget shows correct availability

### Expected Results
- ✅ Capacity checks use real database data
- ✅ Cache is properly invalidated after reschedule
- ✅ Frontend availability reflects backend changes
- ✅ No double-booking occurs

---

## Troubleshooting

### Common Issues

1. **Modal not opening**: Check JavaScript console for errors, verify admin.js is loaded
2. **Time slots not loading**: Verify AJAX endpoints are working, check network tab
3. **Reschedule fails silently**: Check PHP error logs, verify database permissions
4. **Email not sent**: Check WordPress mail configuration and SMTP settings

### Debug Steps

1. Enable WordPress debug mode: `define('WP_DEBUG', true);`
2. Check browser console for JavaScript errors
3. Monitor network requests in browser developer tools
4. Check WordPress error logs for PHP errors
5. Verify database table structure and data

### Performance Notes

- Time slot loading is cached for better performance
- Availability checks query the database in real-time
- Large numbers of bookings may require pagination
- Consider implementing background processing for email notifications

## Success Criteria

All test cases should pass with the following overall results:
- ✅ Cancellation rules can be configured per product
- ✅ Reschedule respects capacity and cutoff constraints
- ✅ Cancellation rules are properly validated
- ✅ Email notifications are sent for reschedules
- ✅ Admin interface is intuitive and error-free
- ✅ AJAX interactions work smoothly
- ✅ Database integrity is maintained
- ✅ Cache invalidation works correctly