# Manual Tests: Reschedule and Refund Functionality

## Overview
This document outlines the manual testing procedures for the reschedule and refund functionality implemented in FP Esperienze.

## Prerequisites
1. WordPress site with WooCommerce and FP Esperienze plugin activated
2. At least one Experience product configured with:
   - Schedules (available time slots)
   - Cancellation policy settings
   - Cutoff time settings
3. At least one confirmed booking in the system

## Test 1: Product Cancellation Policy Configuration

### Objective
Verify that cancellation policy fields are properly saved and displayed in the Experience product admin.

### Steps
1. Navigate to **Products > Add New** (or edit existing experience)
2. Set **Product Type** to "Experience"
3. Go to the **Experience** tab
4. Locate the **Cancellation Policy** section
5. Configure the following fields:
   - **Booking Cutoff Time**: 120 minutes
   - **Free Cancellation Until**: 1440 minutes (24 hours)
   - **Cancellation Fee**: 25%
   - **No-Show Policy**: "Partial Refund"
6. Save the product

### Expected Results
- All cancellation policy fields should be visible and editable
- Values should be saved correctly and persist after page reload
- No JavaScript errors in browser console

## Test 2: Successful Booking Reschedule

### Objective
Test the reschedule functionality when all conditions are met.

### Prerequisites
- Create a booking for tomorrow or later
- Ensure the experience has available slots for reschedule

### Steps
1. Navigate to **FP Esperienze > Bookings**
2. Find a confirmed booking that's not in the past
3. Click the **Reschedule** button for that booking
4. In the reschedule modal:
   - Select a new date (at least 2+ hours from now to respect cutoff)
   - Wait for time slots to load
   - Select an available time slot
5. Click **Reschedule**

### Expected Results
- Modal opens correctly showing current booking details
- Date picker only allows future dates
- Time slots load correctly for selected date
- Reschedule completes successfully with confirmation message
- Booking shows updated date/time in the list
- Customer receives confirmation email (if email is configured)

## Test 3: Reschedule Denied - Cutoff Time Violation

### Objective
Verify that reschedule is denied when cutoff time is violated.

### Steps
1. Navigate to **FP Esperienze > Bookings**
2. Try to reschedule a booking to a time slot that's within the cutoff period (e.g., within 2 hours if cutoff is 120 minutes)
3. Attempt to complete the reschedule

### Expected Results
- Error message: "This time slot is too close to departure. Please select at least X minutes in advance."
- Reschedule operation fails
- Original booking remains unchanged

## Test 4: Reschedule Denied - Insufficient Capacity

### Objective
Test reschedule denial when target slot doesn't have enough capacity.

### Prerequisites
- Target time slot should be fully booked or have insufficient capacity

### Steps
1. Navigate to **FP Esperienze > Bookings**
2. Try to reschedule a booking with multiple participants to a slot with insufficient capacity
3. Attempt to complete the reschedule

### Expected Results
- Error message: "Selected time slot is not available or does not have enough capacity."
- Reschedule operation fails
- Original booking remains unchanged

## Test 5: Cancellation with Free Cancellation Period

### Objective
Test cancellation within the free cancellation period.

### Prerequisites
- Booking scheduled more than 24 hours from now (assuming 1440 minutes free cancellation)

### Steps
1. Navigate to **FP Esperienze > Bookings**
2. Click **Cancel** for an eligible booking
3. In the cancel modal:
   - Enter a cancellation reason
   - Review refund information
4. Click **Confirm Cancellation**

### Expected Results
- Modal shows "Full refund - Free cancellation period"
- Refund amount shows 100% of booking value
- Cancellation completes successfully
- Booking status changes to "Cancelled"
- Capacity is updated for the original slot

## Test 6: Cancellation with Fee

### Objective
Test cancellation outside free period but before experience starts.

### Prerequisites
- Booking scheduled within 24 hours but more than cutoff time
- Product configured with cancellation fee (e.g., 25%)

### Steps
1. Navigate to **FP Esperienze > Bookings**
2. Click **Cancel** for an eligible booking
3. Review refund calculation in modal
4. Confirm cancellation

### Expected Results
- Modal shows "Refund with 25% cancellation fee"
- Refund amount shows 75% of booking value
- Cancellation completes successfully with fee applied

## Test 7: No-Show Cancellation

### Objective
Test cancellation after experience start time (no-show scenario).

### Prerequisites
- Booking in the past or current time
- Product configured with specific no-show policy

### Steps
1. Navigate to **FP Esperienze > Bookings**
2. Try to cancel a past booking
3. Review refund calculation

### Expected Results (varies by no-show policy):
- **No Refund**: 0% refund, message "No refund - No-show policy"
- **Partial Refund**: 50% refund, message "Partial refund - No-show policy"  
- **Full Refund**: 100% refund, message "Full refund - No-show policy"

## Test 8: UI/UX Verification

### Objective
Verify the user interface works correctly.

### Steps
1. Navigate to **FP Esperienze > Bookings**
2. Verify the Actions column appears in the bookings table
3. Test modal interactions:
   - Click reschedule button - modal opens
   - Click cancel button - modal opens
   - Click "Cancel" in modal - modal closes
   - Click outside modal - modal should close
4. Test responsive behavior on different screen sizes

### Expected Results
- All buttons are properly styled and accessible
- Modals are centered and responsive
- Form validation works correctly
- Loading states are shown when appropriate
- Error messages are clear and helpful

## Test 9: Permissions Testing

### Objective
Verify that only authorized users can reschedule/cancel bookings.

### Steps
1. Log in with different user roles (subscriber, customer, shop manager, admin)
2. Try to access the bookings page
3. Try to perform reschedule/cancel operations

### Expected Results
- Only users with proper capabilities can access booking management
- AJAX requests should be blocked for unauthorized users
- Proper error messages for insufficient permissions

## Expected Hook/Filter Usage

The following WordPress hooks should be triggered during testing:

### Actions Triggered
- `fp_esperienze_booking_rescheduled` - When booking is rescheduled
- `fp_esperienze_booking_cancelled` - When booking is cancelled
- `fp_esperienze_reschedule_email_sent` - When reschedule email is sent

### Filters Available
- `fp_esperienze_reschedule_email_content` - Filter reschedule email content
- `fp_esperienze_refund_calculation` - Filter refund calculation

## Performance Considerations

During testing, verify:
- Page load times remain acceptable
- AJAX requests complete within reasonable time
- Database queries are optimized
- No memory leaks in JavaScript
- Proper caching invalidation when bookings change

## Common Issues to Watch For

1. **Time Zone Issues**: Ensure cutoff calculations respect WordPress timezone settings
2. **Race Conditions**: Multiple users trying to book/reschedule same slot simultaneously
3. **Email Delivery**: Reschedule confirmation emails should be sent reliably
4. **Capacity Updates**: Slot capacity should update correctly when bookings change
5. **Validation Bypass**: Ensure server-side validation cannot be bypassed

## Test Data Cleanup

After testing:
1. Remove test bookings created during testing
2. Reset any modified product settings
3. Clear any email logs or test emails
4. Verify no test data affects production functionality