# Manual Testing Documentation for Meeting Points CRUD

## Prerequisites
- WordPress installation with WooCommerce active
- FP Esperienze plugin activated
- Admin access to WordPress dashboard

## Test Scenarios

### 1. Database Schema Verification
**Objective**: Verify that database tables are created with correct schema

**Steps**:
1. Activate the plugin (or re-activate if already active)
2. Check database tables using phpMyAdmin or SQL query
3. Verify `fp_meeting_points` table has fields:
   - `id` (bigint, primary key)
   - `name` (varchar 255, not null)
   - `address` (text, not null) 
   - `latitude` (decimal 10,8)
   - `longitude` (decimal 11,8)
   - `place_id` (varchar 255)
   - `note` (text)
   - `created_at` (datetime)
   - `updated_at` (datetime)
4. Verify `fp_schedules` table has `meeting_point_id` field (bigint)

**Expected Result**: Tables exist with correct schema

### 2. Admin Interface Access
**Objective**: Test admin menu and interface accessibility

**Steps**:
1. Login to WordPress admin
2. Navigate to "FP Esperienze" menu in sidebar
3. Click on "Meeting Points" submenu
4. Verify the meeting points list page loads

**Expected Result**: 
- Meeting Points page displays correctly
- Shows "Add New" button
- Shows empty list message if no meeting points exist
- No PHP errors or warnings

### 3. Create New Meeting Point
**Objective**: Test adding a new meeting point

**Steps**:
1. On Meeting Points page, click "Add New"
2. Fill out the form:
   - **Name**: "Colosseum Main Entrance"
   - **Address**: "Piazza del Colosseo, 1, 00184 Roma RM, Italy"
   - **Latitude**: 41.8902
   - **Longitude**: 12.4922
   - **Google Places ID**: ChIJrRMgU7ZhLxMRxAOFkC7I8Sg
   - **Notes**: "Meet at the main entrance gate. Look for guide with red umbrella."
3. Click "Add Meeting Point"

**Expected Result**:
- Success message displayed
- Redirected to meeting points list
- New meeting point appears in list
- All data saved correctly

### 4. Edit Meeting Point
**Objective**: Test editing existing meeting point

**Steps**:
1. From meeting points list, click "Edit" on a meeting point
2. Modify some fields (e.g., change notes)
3. Click "Update Meeting Point"

**Expected Result**:
- Edit form pre-populated with existing data
- Changes saved successfully
- Success message displayed
- Updated data visible in list

### 5. Delete Meeting Point
**Objective**: Test deletion with usage checking

**Steps**:
1. Create a meeting point that's not in use
2. Click "Delete" from the list
3. Confirm deletion
4. Try to delete a meeting point that's assigned to a product

**Expected Result**:
- Unused meeting point deletes successfully
- In-use meeting point cannot be deleted (delete link not shown or error displayed)
- Appropriate messages shown

### 6. Product Integration
**Objective**: Test meeting point assignment to products

**Steps**:
1. Go to Products > Add New
2. Select "Experience" as product type
3. In Experience Data tab, verify "Default Meeting Point" dropdown
4. Select a meeting point from dropdown
5. Save product
6. Edit product and verify meeting point selection persisted

**Expected Result**:
- Meeting points appear in dropdown
- Selection saves correctly
- Uses new metadata field name `_fp_exp_meeting_point_id`

### 7. Frontend Display
**Objective**: Test meeting point display on single experience page

**Steps**:
1. Create an experience product with meeting point assigned
2. View the product on frontend
3. Scroll to Meeting Point section
4. Test "Open in Google Maps" link

**Expected Result**:
- Meeting Point section displays correctly
- Shows meeting point name, address, and notes
- Map placeholder shows with coordinates (if available)
- Google Maps link opens correct location
- Responsive design works on mobile

### 8. Edge Cases and Validation
**Objective**: Test input validation and edge cases

**Steps**:
1. Try to create meeting point with empty required fields
2. Try to enter invalid coordinates (outside valid ranges)
3. Test with very long text in fields
4. Test with special characters and HTML in fields

**Expected Result**:
- Required field validation works
- Coordinate validation prevents invalid values
- Text properly sanitized and escaped
- No XSS vulnerabilities

### 9. Performance and Pagination
**Objective**: Test with multiple meeting points

**Steps**:
1. Create 25+ meeting points
2. Test pagination on list page
3. Test performance of dropdown loading

**Expected Result**:
- Pagination works correctly
- Performance remains acceptable
- All meeting points accessible

### 10. CSS and Styling
**Objective**: Verify styling looks professional

**Steps**:
1. Check admin interface styling
2. Check frontend meeting point section styling
3. Test responsive design on different screen sizes
4. Verify no CSS conflicts with theme

**Expected Result**:
- Clean, professional appearance
- Consistent with WordPress admin styling
- Mobile-friendly responsive design
- No visual conflicts

## Potential Issues to Watch For

### Database Issues
- Foreign key constraints if implemented
- Character encoding issues with special characters
- Performance with large datasets

### Security Issues
- Nonce verification working
- Capability checks preventing unauthorized access
- Input sanitization preventing XSS/SQL injection

### Integration Issues
- Conflicts with other plugins
- Theme compatibility
- WooCommerce version compatibility

### Frontend Issues
- JavaScript conflicts
- CSS conflicts with themes
- Mobile responsiveness
- Map integration readiness for future Google Maps implementation

## Error Scenarios to Test

1. **Database connection issues**: Simulate DB errors
2. **Invalid permissions**: Test with non-admin users
3. **Missing dependencies**: Test with WooCommerce deactivated
4. **Corrupted data**: Test with malformed database records
5. **Network issues**: Test Google Maps links with various scenarios

## Test Data Examples

### Meeting Point 1
- Name: "Trevi Fountain Meeting Point"
- Address: "Piazza di Trevi, 00187 Roma RM, Italy"
- Latitude: 41.9009
- Longitude: 12.4833
- Place ID: ChIJrRMgU7ZhLxMRxAOFkC7I8Sg
- Notes: "Meet at the right side of the fountain, near the gelato shop."

### Meeting Point 2  
- Name: "Vatican Museums Entrance"
- Address: "Viale Vaticano, 00165 Roma RM, Italy"
- Latitude: 41.9044
- Longitude: 12.4539
- Place ID: ChIJ6eFLuv5YLxMR9w02kZUHFNc
- Notes: "Meet 15 minutes before start time. Entrance is on Viale Vaticano."

## Success Criteria

- All CRUD operations work without errors
- Admin interface is user-friendly and professional
- Frontend display is attractive and functional
- Database schema is correct and performant
- Security measures are in place and effective
- Integration with WooCommerce products works seamlessly
- Code follows WordPress coding standards
- No conflicts with common themes/plugins