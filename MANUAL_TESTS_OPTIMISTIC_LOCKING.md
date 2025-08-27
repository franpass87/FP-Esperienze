# Manual Test: Optimistic Locking for Capacity Management

## Test Scenario: Two Users Competing for Last Available Spot

### Prerequisites
- FP Esperienze plugin installed and activated
- At least one experience product configured
- Product has limited capacity (e.g., 2 spots)
- Two browser sessions or incognito windows

### Test Setup
1. Access admin settings: `/wp-admin/admin.php?page=fp-esperienze-settings&tab=booking`
2. Enable "Capacity Holds" checkbox
3. Set "Hold Duration" to 15 minutes
4. Save settings
5. Configure an experience product with capacity of 2 spots
6. Create 1 existing booking to leave only 1 spot available

### Test Steps

#### Session A (Browser 1)
1. Navigate to experience product page
2. Select the time slot with only 1 spot remaining
3. Select 1 adult participant
4. Click "Add to Cart"
5. **Expected**: Hold created, message shows "Spots reserved for 15 minutes"
6. **Do NOT proceed to checkout yet**

#### Session B (Browser 2) 
1. Navigate to same experience product page
2. Select the same time slot
3. Select 1 adult participant  
4. Click "Add to Cart"
5. **Expected**: Error message "Only 0 spots available for this time slot. You selected 1 participants."

#### Session A (Continue)
1. Proceed to checkout
2. Complete payment
3. **Expected**: Booking created successfully, hold converted to booking

#### Session B (Retry after Session A completes)
1. Try adding to cart again
2. **Expected**: Still shows error (no available spots)

### Test Verification

#### Admin Verification
1. Go to Admin > FP Esperienze > Bookings
2. **Expected**: See the new booking from Session A
3. Go to Admin > FP Esperienze > Settings > Booking tab
4. **Expected**: 
   - Active Holds: 0
   - Expired Holds: 0 (should be cleaned up)

#### Database Verification
Check the following tables:
- `wp_fp_bookings`: Should contain the new booking
- `wp_fp_exp_holds`: Should be empty (hold converted/cleaned)

### Test Variations

#### Test 1: Hold Expiration
1. Session A adds to cart (creates hold)
2. Wait 16 minutes (hold expires)
3. Session B adds to cart
4. **Expected**: Session B succeeds (hold expired)

#### Test 2: Holds Disabled
1. Admin disables "Capacity Holds" in settings
2. Session A adds to cart
3. Session B adds to cart immediately
4. Session A completes checkout first
5. Session B completes checkout
6. **Expected**: Session B fails at checkout with capacity error

#### Test 3: Multiple Items
1. Session A adds 2 participants to cart (if capacity allows)
2. Session B tries to add 1 participant
3. **Expected**: Session B fails (insufficient capacity after hold)

### Expected Results Summary

**With Holds Enabled:**
- User gets immediate feedback when adding to cart
- Spots are temporarily reserved for 15 minutes
- Race conditions prevented during checkout
- Holds automatically expire and are cleaned up

**With Holds Disabled:**
- Users can add to cart even when no capacity
- Race conditions possible during checkout
- Atomic capacity check only at payment time

### Performance Test

Monitor the following during tests:
1. Database query count
2. Page load times
3. Cron job execution (every 5 minutes)
4. Hold cleanup efficiency

### Error Scenarios to Test

1. **Session timeout**: Add to cart, close browser, reopen - hold should expire
2. **Database error**: Simulate DB issues during hold creation
3. **Invalid data**: Try to create holds with invalid product/slot data
4. **Concurrent checkout**: Two users with holds try to checkout simultaneously

### Success Criteria

✅ Holds prevent overbooking during cart phase
✅ Proper error messages shown to users  
✅ Holds expire automatically after configured time
✅ Atomic conversion from hold to booking on payment
✅ Graceful fallback when holds disabled
✅ Admin interface shows accurate hold statistics
✅ Cleanup cron job removes expired holds
✅ Performance remains acceptable under load

### Troubleshooting

**If holds not working:**
1. Check if holds are enabled in settings
2. Verify database table `wp_fp_exp_holds` exists
3. Check WordPress cron is functioning
4. Review error logs for SQL errors

**If capacity still shows incorrect:**
1. Clear any caching plugins
2. Check if holds are properly excluded for current session
3. Verify availability calculation includes hold count

**If cleanup not working:**
1. Check if WP Cron is running
2. Manually trigger cleanup via admin interface
3. Verify cron schedule is registered correctly