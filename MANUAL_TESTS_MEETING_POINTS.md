# Meeting Points CRUD - Manual Tests

## Test Environment Setup

1. Activate the FP Esperienze plugin in WordPress admin
2. Ensure WooCommerce is active
3. Verify database tables are created (run `/tmp/test-schema.php` if needed)

## Test 1: Admin Interface - Add Meeting Point

### Steps:
1. Go to **WP Admin > FP Esperienze > Meeting Points**
2. Click "Add New" button
3. Fill in the form:
   - **Name**: "Colosseum Main Entrance"
   - **Address**: "Piazza del Colosseo, 1, 00184 Roma RM, Italy"
   - **Latitude**: 41.8902
   - **Longitude**: 12.4922
   - **Google Place ID**: ChIJrRMgU7ZhLxMRxAOFkC7I8Sg
   - **Note**: "Meet at the main entrance arch. Look for the guide with a red umbrella."
4. Click "Add Meeting Point"

### Expected Results:
- Success message appears
- Meeting point is added to the list
- Form is cleared for next entry

## Test 2: Admin Interface - Edit Meeting Point

### Steps:
1. From the meeting points list, click "Edit" on the previously created meeting point
2. Update the **Note** field to: "Meet at the main entrance arch. Look for the guide with a red umbrella. Please arrive 15 minutes early."
3. Update the **Name** to: "Colosseum Main Entrance (VIP)"
4. Click "Update Meeting Point"

### Expected Results:
- Success message appears
- Redirected to meeting points list
- Updated information is displayed

## Test 3: Admin Interface - Meeting Points List

### Steps:
1. Go to **FP Esperienze > Meeting Points** 
2. Verify the list shows:
   - Meeting point name
   - Truncated address
   - Coordinates (if set)
   - Edit/Delete actions

### Expected Results:
- List displays correctly
- All fields show appropriate data
- Actions are available

## Test 4: Admin Interface - Delete Protection

### Steps:
1. Create a test Experience product
2. Set the meeting point as default for that product
3. Try to delete the meeting point from the admin interface

### Expected Results:
- Error message appears: "Cannot delete meeting point. It may be in use..."
- Meeting point remains in the list

## Test 5: Product Integration - Experience Product

### Steps:
1. Go to **Products > Add New**
2. Select "Experience" as product type
3. Go to "Experience" tab
4. In "Default Meeting Point" dropdown, verify:
   - "Select a meeting point" is first option
   - All created meeting points appear
5. Select a meeting point and save the product

### Expected Results:
- Dropdown populates correctly
- Selection is saved properly
- Metadata `_fp_exp_meeting_point_id` is stored

## Test 6: Frontend Display - Single Experience Template

### Steps:
1. View the Experience product on frontend
2. Scroll to find the "Meeting Point" section

### Expected Results:
- Meeting Point section appears before Reviews
- Shows meeting point name as heading
- Displays full address
- Shows instructions/note if available
- Shows coordinates if available
- "Open in Google Maps" link appears (if coordinates set)
- Map placeholder displays with coordinates or "not available" message

## Test 7: Google Maps Integration

### Steps:
1. On a product with meeting point coordinates set
2. Click "Open in Google Maps" link

### Expected Results:
- Opens Google Maps in new tab
- Shows correct location based on coordinates
- URL format: `https://www.google.com/maps?q=LAT,LNG`

## Test 8: Schedule Integration

### Steps:
1. Edit an Experience product
2. Add a schedule with a specific meeting point
3. Save the product
4. Try to delete that meeting point from admin

### Expected Results:
- Meeting point appears in schedule dropdown
- Selection is saved properly
- Cannot delete meeting point that's used in schedules

## Test 9: Data Validation

### Steps:
1. Try to create meeting point with empty name
2. Try to create meeting point with empty address
3. Create meeting point with only name and address (optional fields empty)

### Expected Results:
- Error for missing required fields (name, address)
- Success when only optional fields are empty
- Proper error messages display

## Test 10: Responsive Design

### Steps:
1. Test admin interface on mobile/tablet
2. Test frontend meeting point section on mobile/tablet

### Expected Results:
- Admin forms are responsive
- Frontend section displays properly
- No horizontal scrolling issues

## Test 11: Database Consistency

### Steps:
1. Run `/tmp/test-meeting-points.php` to test CRUD operations
2. Verify all operations work correctly

### Expected Results:
- All tests pass
- No PHP errors
- Database operations work as expected

## Test 12: Security Verification

### Steps:
1. Verify nonce fields are present in forms
2. Check that non-admin users cannot access meeting points admin
3. Verify data sanitization on save

### Expected Results:
- Nonce protection active
- Proper capability checks
- XSS protection in place

## Cleanup After Testing

1. Delete test meeting points
2. Delete test products
3. Verify no orphaned data remains

## Known Limitations

1. Map placeholder is static (future enhancement: integrate with actual map API)
2. Google Places API integration is ready but requires API key configuration
3. Bulk operations not implemented (future enhancement)

## Success Criteria

✅ All CRUD operations work correctly
✅ Admin interface is user-friendly and secure
✅ Product integration works seamlessly
✅ Frontend display is professional and informative
✅ Google Maps integration functions properly
✅ Data validation prevents invalid entries
✅ Meeting points in use cannot be deleted
✅ Responsive design works on all devices