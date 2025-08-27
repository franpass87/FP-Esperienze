# Manual Tests - Voucher Admin Panel

## Test Environment Setup
1. WordPress site with WooCommerce installed
2. FP Esperienze plugin activated
3. At least one experience product configured
4. Some test vouchers created (different statuses)
5. Admin user with `manage_woocommerce` capability

## Test Cases

### 1. Access Control
**Objective**: Verify proper access control for voucher admin

**Steps**:
1. Log in as admin user
2. Navigate to FP Esperienze > Vouchers
3. Verify page loads successfully
4. Log out and log in as subscriber/customer role
5. Try to access admin.php?page=fp-esperienze-vouchers

**Expected Results**:
- Admin can access the page
- Non-admin users cannot access the page
- Proper error message shown for unauthorized access

### 2. Voucher Listing Display
**Objective**: Verify voucher listing shows all required information

**Steps**:
1. Navigate to FP Esperienze > Vouchers
2. Verify table columns: Checkbox, Code, Product, Recipient, Value, Status, Expires, Created, Actions
3. Check that vouchers display with proper information:
   - Voucher code
   - Product name with order link (if applicable)
   - Recipient name and email
   - Value (Full Experience or monetary amount)
   - Status with color coding
   - Expiration date
   - Creation date

**Expected Results**:
- All columns display correctly
- Order links work (if order exists)
- Status colors match:
  - Active: Green (#46b450)
  - Redeemed: Blue (#00a0d2)
  - Expired: Red (#dc3232)
  - Void: Gray (#666)

### 3. Enhanced Filters
**Objective**: Test new filtering capabilities

**Steps**:
1. Test Status filter:
   - Select "Active" and verify only active vouchers shown
   - Select "Redeemed" and verify only redeemed vouchers shown
   - Select "Expired" and verify only expired vouchers shown
   - Select "Void" and verify only void vouchers shown

2. Test Product filter:
   - Select specific product and verify only vouchers for that product shown

3. Test Date range filters:
   - Set "From date" and verify only vouchers created after that date shown
   - Set "To date" and verify only vouchers created before that date shown
   - Set both dates and verify vouchers within range shown

4. Test Search functionality:
   - Search by voucher code
   - Search by recipient name
   - Search by recipient email

5. Test filter combinations:
   - Apply multiple filters simultaneously
   - Use "Clear" button to reset all filters

**Expected Results**:
- Each filter works correctly in isolation
- Filters work correctly in combination
- Search finds vouchers by code, name, or email
- Clear button resets all filters
- URL parameters preserved for sharing/bookmarking

### 4. Individual Actions
**Objective**: Test individual voucher actions

**Prerequisites**: 
- Have vouchers with different statuses
- Have vouchers with and without PDF files

#### 4.1 Download PDF
**Steps**:
1. Find voucher with existing PDF
2. Click "Download PDF" button
3. Verify PDF downloads correctly

**Expected Results**:
- PDF downloads with correct filename (voucher-CODE.pdf)
- PDF contains voucher information and QR code

#### 4.2 Copy PDF Link
**Steps**:
1. Find voucher with existing PDF
2. Click "Copy Link" button
3. Paste link in new browser tab

**Expected Results**:
- Success message shows "PDF link copied to clipboard!"
- Pasted link downloads the same PDF

#### 4.3 Resend Email (Active Vouchers)
**Steps**:
1. Find active voucher
2. Click "Resend" button
3. Confirm action in dialog
4. Check email recipient's inbox

**Expected Results**:
- Confirmation dialog appears
- Success notice shows after action
- Email sent to recipient with PDF attachment
- Action logged in order notes (if order exists)

#### 4.4 Extend Expiration (Active Vouchers)
**Steps**:
1. Find active voucher
2. Note current expiration date
3. Enter extension months (e.g., 6)
4. Click "Extend" button
5. Confirm action in dialog
6. Refresh page and verify new expiration date

**Expected Results**:
- Confirmation dialog appears
- Success notice shows new expiration date
- Voucher expiration date updated correctly
- Action logged in order notes

#### 4.5 Void Voucher (Active Vouchers)
**Steps**:
1. Find active voucher
2. Click "Void" button
3. Confirm action in dialog
4. Verify voucher status changes to "Void"

**Expected Results**:
- Confirmation dialog appears
- Success notice shows
- Voucher status changes to "Void" with gray color
- Action buttons disappear for voided voucher
- Action logged in order notes

### 5. Bulk Actions
**Objective**: Test bulk action functionality

#### 5.1 Bulk Selection
**Steps**:
1. Check individual voucher checkboxes
2. Use "Select All" checkbox
3. Verify selection state

**Expected Results**:
- Individual checkboxes work
- "Select All" selects/deselects all vouchers
- Selection state maintained when scrolling

#### 5.2 Bulk Void
**Steps**:
1. Select multiple active vouchers
2. Choose "Void" from bulk actions dropdown
3. Click "Apply"
4. Confirm action in dialog

**Expected Results**:
- Confirmation dialog shows number of selected vouchers
- All selected vouchers status changes to "Void"
- Success notice shows number processed
- Actions logged for each voucher

#### 5.3 Bulk Resend
**Steps**:
1. Select multiple active vouchers
2. Choose "Resend emails" from bulk actions dropdown
3. Click "Apply"
4. Confirm action in dialog
5. Check recipients' email inboxes

**Expected Results**:
- Confirmation dialog appears
- Emails sent to all recipients
- Success notice shows number processed
- Actions logged for each voucher

#### 5.4 Bulk Extend
**Steps**:
1. Select multiple active vouchers
2. Choose "Extend expiration" from bulk actions dropdown
3. Set extension months (e.g., 12)
4. Click "Apply"
5. Confirm action in dialog

**Expected Results**:
- Extension months input field appears
- Confirmation dialog shows extension period
- All selected vouchers expiration dates updated
- Success notice shows number processed
- Actions logged for each voucher

### 6. Error Handling
**Objective**: Test error conditions and edge cases

#### 6.1 Missing PDF Resend
**Steps**:
1. Find voucher without PDF file
2. Try to resend email

**Expected Results**:
- New PDF generated automatically
- Email sent successfully
- PDF path updated in database

#### 6.2 Invalid Actions
**Steps**:
1. Try bulk action without selecting vouchers
2. Try extend with 0 or negative months
3. Try actions on already voided vouchers

**Expected Results**:
- Appropriate error messages displayed
- No actions performed on invalid requests

#### 6.3 Network/Database Errors
**Steps**:
1. Temporarily disable email functionality
2. Try resend action
3. Check error handling

**Expected Results**:
- Error messages displayed appropriately
- User informed of failures
- Partial failures handled gracefully in bulk actions

### 7. Audit Logging
**Objective**: Verify action logging functionality

**Steps**:
1. Perform various voucher actions
2. Check order notes for associated orders
3. Check server error logs

**Expected Results**:
- Actions logged in order notes with user information
- System logs contain detailed action information
- Log entries include: voucher ID, action, user, timestamp, description

### 8. UI/UX Validation
**Objective**: Test user interface and experience

#### 8.1 Responsive Design
**Steps**:
1. View page on different screen sizes
2. Test mobile/tablet layouts

**Expected Results**:
- Table remains usable on smaller screens
- Buttons and forms accessible on mobile

#### 8.2 Accessibility
**Steps**:
1. Test keyboard navigation
2. Check screen reader compatibility
3. Verify color contrast

**Expected Results**:
- All interactive elements keyboard accessible
- Proper ARIA labels and roles
- Sufficient color contrast for status indicators

#### 8.3 JavaScript Functionality
**Steps**:
1. Test with JavaScript disabled
2. Test copy-to-clipboard functionality
3. Test bulk action UI enhancements

**Expected Results**:
- Basic functionality works without JavaScript
- Copy functionality gracefully degrades
- Bulk action confirmations work properly

### 9. Performance Testing
**Objective**: Test performance with large datasets

**Steps**:
1. Create 100+ test vouchers
2. Test page load time
3. Test filtering performance
4. Test bulk actions on large selections

**Expected Results**:
- Page loads within reasonable time
- Filters respond quickly
- Bulk actions handle large selections efficiently

### 10. Integration Testing
**Objective**: Test integration with other plugin features

**Steps**:
1. Create new voucher through order
2. Redeem voucher in frontend
3. Verify admin panel reflects changes
4. Test order refund scenarios

**Expected Results**:
- New vouchers appear in admin panel
- Status updates reflect in real-time
- Order integration works seamlessly

## Test Data Requirements

### Sample Vouchers Needed:
- Active voucher with PDF
- Active voucher without PDF
- Redeemed voucher
- Expired voucher
- Void voucher
- Vouchers from different products
- Vouchers from different time periods
- Vouchers with and without associated orders

### Test User Roles:
- Administrator
- Shop Manager  
- Customer
- Subscriber

## Bug Reporting Template

**Bug Title**: [Brief description]

**Environment**:
- WordPress version:
- WooCommerce version:
- FP Esperienze version:
- Browser:

**Steps to Reproduce**:
1. 
2. 
3. 

**Expected Result**:

**Actual Result**:

**Screenshots**: [If applicable]

**Console Errors**: [If any]

**Additional Notes**: