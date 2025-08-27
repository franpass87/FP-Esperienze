# Manual Test Checklist: Setup Wizard & System Status

## Setup Wizard Tests

### Test 1: Initial Setup Wizard Access
**Objective**: Verify setup wizard appears on first plugin activation

**Steps**:
1. Deactivate FP Esperienze plugin (if active)
2. Delete option `fp_esperienze_setup_complete` from database
3. Reactivate FP Esperienze plugin
4. Navigate to WordPress admin

**Expected Results**:
- [ ] User is automatically redirected to Setup Wizard
- [ ] Setup Wizard page displays with step 1 (Basic Settings)
- [ ] Progress bar shows 3 steps with step 1 active
- [ ] Setup Wizard menu item appears in FP Esperienze menu

### Test 2: Step 1 - Basic Settings
**Objective**: Test basic settings configuration

**Steps**:
1. Navigate to Setup Wizard step 1
2. Configure the following:
   - Currency: EUR
   - Timezone: Europe/Rome
   - Default Duration: 90 minutes
   - Default Capacity: 15 people
   - Default Language: Italian
3. Click "Next"

**Expected Results**:
- [ ] All form fields display correctly
- [ ] Currency dropdown shows WooCommerce currencies
- [ ] Timezone dropdown shows available timezones
- [ ] Number fields accept valid values
- [ ] Language dropdown shows English/Italian options
- [ ] Settings are saved when clicking "Next"
- [ ] User proceeds to step 2

### Test 3: Step 2 - Integrations
**Objective**: Test integrations configuration

**Steps**:
1. Navigate to Setup Wizard step 2
2. Configure sample integrations:
   - GA4 Measurement ID: G-TEST123456
   - Enable Enhanced eCommerce: checked
   - Google Ads Conversion ID: AW-TEST789
   - Meta Pixel ID: 123456789
   - Brevo API Key: test-api-key
   - Brevo List ID (IT): 1
   - Brevo List ID (EN): 2
   - Google Places API Key: test-places-key
3. Click "Next"

**Expected Results**:
- [ ] All integration fields display correctly
- [ ] Fields accept text input properly
- [ ] Checkbox for GA4 eCommerce works
- [ ] Settings are saved when clicking "Next"
- [ ] User proceeds to step 3

### Test 4: Step 3 - Brand Settings
**Objective**: Test brand/PDF configuration

**Steps**:
1. Navigate to Setup Wizard step 3
2. Configure brand settings:
   - Upload a logo image using media uploader
   - Set brand color: #ff6b35
   - Set voucher terms: "Custom voucher terms text"
3. Click "Finish Setup"

**Expected Results**:
- [ ] Logo upload button opens media uploader
- [ ] Selected logo appears in preview
- [ ] Color picker works for brand color
- [ ] Textarea accepts voucher terms
- [ ] "Finish Setup" completes the wizard
- [ ] User redirected to dashboard with success message

### Test 5: Skip Functionality
**Objective**: Test skipping wizard steps

**Steps**:
1. Reset setup completion flag
2. Navigate to Setup Wizard
3. Click "Skip" on each step
4. Verify completion

**Expected Results**:
- [ ] "Skip" button appears on all steps
- [ ] Skipping saves minimal default values
- [ ] Can skip through entire wizard
- [ ] Setup completion is marked when finished

### Test 6: Setup Completion State
**Objective**: Verify wizard behavior after completion

**Steps**:
1. Complete setup wizard
2. Try to access setup wizard URL directly
3. Check FP Esperienze menu

**Expected Results**:
- [ ] Setup Wizard menu item disappears after completion
- [ ] Direct access to wizard redirects to dashboard
- [ ] Dashboard shows completion message
- [ ] All configured settings are preserved

## System Status Tests

### Test 7: System Status Page Access
**Objective**: Verify system status page functionality

**Steps**:
1. Navigate to FP Esperienze → System Status
2. Review all sections

**Expected Results**:
- [ ] System Status page loads correctly
- [ ] All sections display (System Info, Checks, Database, Integrations)
- [ ] Page has professional styling
- [ ] Status icons display correctly

### Test 8: System Information Section
**Objective**: Test system version checks

**Steps**:
1. Check System Information section
2. Verify version compatibility indicators

**Expected Results**:
- [ ] WordPress version displays with compatibility status
- [ ] WooCommerce version displays with compatibility status
- [ ] PHP version displays with compatibility status
- [ ] Plugin version displays correctly
- [ ] WordPress timezone displays correctly
- [ ] Green checkmarks for compatible versions
- [ ] Red X for incompatible versions (if any)

### Test 9: System Checks Section
**Objective**: Test automated system health checks

**Steps**:
1. Review System Checks section
2. Check each system requirement

**Expected Results**:
- [ ] Database tables check shows status
- [ ] WordPress Cron check displays correctly
- [ ] Remote requests test executes
- [ ] File permissions check works
- [ ] PHP extensions check shows results
- [ ] "Fix" buttons appear for failed checks
- [ ] Status indicators (green/yellow/red) display correctly

### Test 10: Database Information Section
**Objective**: Test database table reporting

**Steps**:
1. Review Database Information section
2. Check table status and record counts

**Expected Results**:
- [ ] All FP Esperienze tables listed
- [ ] Record counts display for existing tables
- [ ] Missing tables marked as "Table missing"
- [ ] Table names match expected schema

### Test 11: Integration Status Section
**Objective**: Test integration configuration status

**Steps**:
1. Review Integration Status section
2. Configure one integration in Settings
3. Return to System Status

**Expected Results**:
- [ ] Each integration shows configuration status
- [ ] "Not configured" shows for empty integrations
- [ ] "Configured" shows for completed integrations
- [ ] "Configure" button links to settings page
- [ ] "Documentation" button links to external docs
- [ ] Status updates after configuration changes

### Test 12: Fix Actions
**Objective**: Test system repair functionality

**Steps**:
1. Temporarily rename a database table (simulate missing table)
2. Visit System Status page
3. Click "Create Tables" fix button
4. Verify table recreation

**Expected Results**:
- [ ] Missing tables detected correctly
- [ ] "Create Tables" button appears
- [ ] Fix action executes successfully
- [ ] Success message displays
- [ ] Table status updates to "OK"
- [ ] Record counts display correctly

## Integration Tests

### Test 13: Settings Page Integration
**Objective**: Verify setup wizard integrates with existing settings

**Steps**:
1. Complete setup wizard with specific values
2. Navigate to FP Esperienze → Settings
3. Verify wizard values appear in settings

**Expected Results**:
- [ ] Basic settings values match wizard input
- [ ] Integration settings match wizard input
- [ ] Brand settings match wizard input
- [ ] Settings page functions normally
- [ ] Changes in settings persist

### Test 14: Dashboard Integration
**Objective**: Test dashboard completion message

**Steps**:
1. Complete setup wizard
2. Check dashboard page

**Expected Results**:
- [ ] Success message displays on dashboard
- [ ] Message is dismissible
- [ ] Dashboard functions normally
- [ ] Quick actions work correctly

### Test 15: Plugin Reactivation
**Objective**: Test behavior on plugin reactivation

**Steps**:
1. Complete setup wizard
2. Deactivate plugin
3. Reactivate plugin
4. Check for setup wizard redirect

**Expected Results**:
- [ ] No redirect to setup wizard on reactivation
- [ ] Setup completion flag persists
- [ ] All settings preserved
- [ ] Plugin functions normally

## Browser Compatibility Tests

### Test 16: Cross-Browser Testing
**Objective**: Verify wizard works in different browsers

**Browsers to test**: Chrome, Firefox, Safari, Edge

**Expected Results**:
- [ ] Wizard displays correctly in all browsers
- [ ] Form interactions work in all browsers
- [ ] Color picker functions in all browsers
- [ ] Media uploader works in all browsers
- [ ] Navigation between steps works

## Accessibility Tests

### Test 17: Keyboard Navigation
**Objective**: Test keyboard accessibility

**Steps**:
1. Navigate wizard using only keyboard
2. Use Tab, Enter, Space keys
3. Test screen reader compatibility

**Expected Results**:
- [ ] All form elements are keyboard accessible
- [ ] Tab order is logical
- [ ] Enter key submits forms
- [ ] Focus indicators visible
- [ ] Labels associated with form fields

## Performance Tests

### Test 18: Page Load Performance
**Objective**: Verify reasonable load times

**Steps**:
1. Measure wizard page load time
2. Measure system status page load time
3. Test with large databases

**Expected Results**:
- [ ] Setup wizard loads in under 2 seconds
- [ ] System status loads in under 3 seconds
- [ ] Database checks complete in reasonable time
- [ ] No significant performance impact

## Error Handling Tests

### Test 19: Invalid Input Handling
**Objective**: Test form validation

**Steps**:
1. Submit forms with invalid data
2. Test with missing required fields
3. Test with malformed API keys

**Expected Results**:
- [ ] Invalid input rejected gracefully
- [ ] Error messages display clearly
- [ ] Form retains valid input after errors
- [ ] No PHP errors or warnings

### Test 20: Network Error Handling
**Objective**: Test remote request failures

**Steps**:
1. Disable internet connection
2. Run system status checks
3. Verify graceful failure handling

**Expected Results**:
- [ ] Remote request failures handled gracefully
- [ ] Error messages informative but not alarming
- [ ] Page remains functional
- [ ] Other checks continue to work

## Test Environment Setup

### Prerequisites
- WordPress 6.5+ with WooCommerce 8.0+
- FP Esperienze plugin activated
- Admin user account
- Clean test environment

### Test Data Reset
Before each test session:
1. Delete option `fp_esperienze_setup_complete`
2. Clear transient `fp_esperienze_activation_redirect`
3. Reset integration settings if needed
4. Ensure all database tables exist

### Success Criteria
- All manual tests pass without errors
- UI elements display correctly
- Data persistence works properly
- No PHP warnings or errors
- Performance remains acceptable
- Accessibility standards met