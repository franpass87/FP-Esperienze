# Manual Test Checklist - Advanced Reports Dashboard

## Environment Setup

**Prerequisites:**
- WordPress >=6.5 with WooCommerce >=8.0
- FP Esperienze plugin activated
- Sample experience products created
- Test bookings with various dates, meeting points, and languages
- User with `manage_fp_esperienze` capability

## Test Cases

### 1. Reports Menu Access

**Objective**: Verify Reports menu appears and is accessible

**Steps**:
1. Navigate to **WP Admin → FP Esperienze**
2. Check submenu items
3. Click on **Reports**

**Expected Results**:
- [ ] "Reports" submenu appears under FP Esperienze
- [ ] Reports page loads successfully
- [ ] User with correct capabilities can access
- [ ] Users without capability get permission denied

### 2. KPI Dashboard Display

**Objective**: Test KPI widgets show correct data

**Steps**:
1. Navigate to Reports page
2. Check all KPI cards in dashboard:
   - Total Revenue
   - Seats Sold  
   - Total Bookings
   - Average Booking Value

**Expected Results**:
- [ ] All KPI cards display loading state initially
- [ ] KPI values load via AJAX
- [ ] Revenue shows currency formatting (€)
- [ ] Numbers are properly formatted
- [ ] Values reflect actual booking data

### 3. Date Range Filtering

**Objective**: Test date range filters work correctly

**Steps**:
1. Set "Date From" to 30 days ago
2. Set "Date To" to today
3. Click "Update Reports"
4. Change date range to last 7 days
5. Click "Update Reports" again

**Expected Results**:
- [ ] Default date range is last 30 days
- [ ] Date inputs accept valid dates
- [ ] KPI data updates when filter changes
- [ ] Charts update with new date range
- [ ] Loading indicator shows during updates

### 4. Product Filtering

**Objective**: Test filtering by specific experience

**Steps**:
1. Select specific experience from dropdown
2. Click "Update Reports"
3. Verify data shows only for selected experience
4. Reset to "All Experiences"

**Expected Results**:
- [ ] Product dropdown populated with experience products
- [ ] Data filters correctly to selected product
- [ ] KPIs show product-specific metrics
- [ ] Charts reflect filtered data

### 5. Meeting Point Filtering

**Objective**: Test filtering by meeting point

**Steps**:
1. Select specific meeting point from dropdown
2. Click "Update Reports"
3. Check that data shows bookings for that meeting point only

**Expected Results**:
- [ ] Meeting point dropdown populated correctly
- [ ] Data filters to selected meeting point
- [ ] Load factors show correct meeting point data

### 6. Charts Functionality

**Objective**: Test revenue/seats trend charts

**Steps**:
1. Check default chart view (Daily)
2. Click "Weekly" period button
3. Click "Monthly" period button
4. Verify chart updates with different time periods

**Expected Results**:
- [ ] Chart.js loads from CDN successfully
- [ ] Default period is "Daily"
- [ ] Period buttons change chart grouping
- [ ] Chart shows revenue and seats data
- [ ] Dual y-axis (revenue left, seats right)
- [ ] Chart is responsive and interactive

### 7. Top 10 Experiences

**Objective**: Test top experiences list

**Steps**:
1. Check "Top 10 Experiences" section
2. Verify experiences are ranked by revenue
3. Apply filters and see if ranking updates

**Expected Results**:
- [ ] Top experiences list shows up to 10 items
- [ ] Items ranked by revenue (highest first)
- [ ] Shows product names and revenue amounts
- [ ] Updates when filters change
- [ ] "No data" message when no bookings

### 8. UTM Conversion Tracking

**Objective**: Test traffic source conversion display

**Steps**:
1. Check "Traffic Source Conversions" section
2. Look for UTM source data
3. Verify Direct traffic is included

**Expected Results**:
- [ ] UTM conversions section displays
- [ ] Shows sources, orders, revenue, avg value
- [ ] Includes "Direct" traffic category
- [ ] Data formatted correctly
- [ ] Shows placeholder data if no UTM data exists

### 9. Load Factors Table

**Objective**: Test load factor calculations and display

**Steps**:
1. Check "Load Factors by Experience" section
2. Verify table shows capacity vs actual bookings
3. Check load factor percentages and visual bars

**Expected Results**:
- [ ] Load factors table displays correctly
- [ ] Shows product, date, time, capacity, sold, load factor
- [ ] Load factor percentages calculated correctly
- [ ] Visual progress bars show capacity utilization
- [ ] Different colors for low/medium/high load factors

### 10. Data Export - CSV

**Objective**: Test CSV export functionality

**Steps**:
1. Apply desired filters (date range, product, etc.)
2. In "Export Report Data" section, select "CSV"
3. Click "Export Data"
4. Check downloaded file

**Expected Results**:
- [ ] Export form appears with CSV/JSON options
- [ ] CSV export downloads file successfully
- [ ] File named with timestamp
- [ ] CSV contains summary, top experiences, UTM data
- [ ] Data respects applied filters
- [ ] CSV properly formatted with headers

### 11. Data Export - JSON

**Objective**: Test JSON export functionality

**Steps**:
1. Apply filters
2. Select "JSON" format
3. Click "Export Data"
4. Verify JSON structure

**Expected Results**:
- [ ] JSON export downloads successfully
- [ ] Valid JSON format
- [ ] Contains same data as CSV but in JSON structure
- [ ] Includes metadata (generation time, date range)
- [ ] Proper data nesting and structure

### 12. Security & Permissions

**Objective**: Test security measures and PII protection

**Steps**:
1. Test with user without `manage_fp_esperienze` capability
2. Check AJAX endpoints require proper capabilities
3. Verify nonce validation on export forms

**Expected Results**:
- [ ] Unauthorized users cannot access reports page
- [ ] AJAX calls return permission errors for unauthorized users
- [ ] Export requires valid nonce
- [ ] No sensitive data exposed in public areas

### 13. Performance & Loading

**Objective**: Test performance with large datasets

**Steps**:
1. Test with date range containing many bookings
2. Check loading times and responsiveness
3. Verify AJAX calls don't timeout

**Expected Results**:
- [ ] Page loads within reasonable time (<3 seconds)
- [ ] AJAX calls complete within 10 seconds
- [ ] Loading indicators show during data fetching
- [ ] No JavaScript errors in console
- [ ] Charts render smoothly

### 14. Responsive Design

**Objective**: Test mobile/tablet compatibility

**Steps**:
1. View reports page on mobile device/small screen
2. Check KPI cards layout
3. Verify charts are readable
4. Test filter forms usability

**Expected Results**:
- [ ] KPI cards stack properly on small screens
- [ ] Charts remain interactive and readable
- [ ] Filter forms are usable on mobile
- [ ] Tables scroll horizontally if needed
- [ ] No layout breaking on various screen sizes

### 15. Error Handling

**Objective**: Test graceful error handling

**Steps**:
1. Disconnect from internet and test AJAX calls
2. Test with invalid date ranges
3. Test export with no data

**Expected Results**:
- [ ] AJAX failures show appropriate error messages
- [ ] Invalid filters handled gracefully
- [ ] Export with no data returns meaningful file
- [ ] JavaScript errors don't break the page
- [ ] Fallback content shows when API fails

## Test Results Summary

**Date Tested**: ________________
**Tester**: ____________________
**Browser**: ___________________
**WordPress Version**: __________
**Plugin Version**: _____________

**Overall Status**: ⬜ Pass ⬜ Fail ⬜ Needs Review

**Issues Found**:
- 
- 
- 

**Performance Notes**:
- KPI load time: _____ seconds
- Chart render time: _____ seconds
- Export generation time: _____ seconds

**Browser Compatibility**:
- [ ] Chrome
- [ ] Firefox  
- [ ] Safari
- [ ] Edge
- [ ] Mobile Safari
- [ ] Mobile Chrome

## Notes

**Additional Observations**:
- 
- 
- 

**Recommendations**:
- 
- 
- 