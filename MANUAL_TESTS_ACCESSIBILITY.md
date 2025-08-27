# FP Esperienze - Accessibility Manual Tests

This document provides comprehensive manual testing procedures for accessibility compliance, focusing on screen reader support and keyboard navigation.

## Prerequisites

### Testing Tools
- **Screen Readers**: NVDA (Windows), JAWS (Windows), VoiceOver (macOS)
- **Browsers**: Chrome, Firefox, Safari, Edge
- **Keyboard**: Standard keyboard for navigation testing
- **Color Contrast Tools**: WebAIM Contrast Checker, Chrome DevTools

### Test Environment Setup
1. Install a screen reader (NVDA recommended for free testing)
2. Disable mouse/trackpad to force keyboard navigation
3. Use incognito/private browsing to avoid cached content
4. Test with WordPress site containing FP Esperienze plugin

---

## Test Suite 1: Screen Reader Testing

### 1.1 Experience Archive Page (`[fp_exp_archive]` shortcode)

**Test Steps:**
1. Navigate to a page with the archive shortcode
2. Start screen reader (NVDA: Insert + Space)
3. Use screen reader navigation (Arrow keys, H for headings)

**Expected Results:**
- [ ] Page title and main heading are announced
- [ ] Filter form is identified with proper labels
- [ ] Each experience card is announced with title, price, and description  
- [ ] Images have appropriate alt text
- [ ] Pagination links are announced with context
- [ ] No content is skipped or repeated unexpectedly

**Screen Reader Commands to Test:**
- `H` - Navigate by headings (should find proper hierarchy)
- `F` - Navigate by form controls (should find filter fields)
- `L` - Navigate by links (should find experience links, pagination)
- `R` - Navigate by regions/landmarks
- `Insert + F7` - List all links (should show meaningful link text)

### 1.2 Single Experience Page

**Test Steps:**
1. Navigate to a single experience product page
2. Use screen reader to navigate through all sections

**Expected Results:**
- [ ] H1 title is announced first
- [ ] Hero section content is accessible
- [ ] Trust bar items are properly announced
- [ ] FAQ accordion buttons announce expanded/collapsed state
- [ ] Booking widget is identified as a form region
- [ ] Time slot selection is announced as radio group
- [ ] Quantity controls announce current values
- [ ] Error messages are announced immediately
- [ ] Loading states are announced
- [ ] Form validation feedback is announced

**Critical Elements to Verify:**
- [ ] Time slots: "Time slots, radio group, 5 of 8 items" style announcement
- [ ] Quantity controls: "Adults quantity, 2" style announcement  
- [ ] FAQ: "FAQ item 1, button collapsed" / "expanded" announcements
- [ ] Error messages appear in aria-live regions

### 1.3 Booking Widget Interaction

**Test Steps:**
1. Navigate to booking widget on single experience page
2. Interact with each form control using screen reader

**Expected Results:**
- [ ] Date picker is properly labeled and announced
- [ ] Time slots are announced as radio group with availability status
- [ ] Participant quantity controls announce values and labels
- [ ] Extra services (if present) are properly labeled
- [ ] Total price updates are announced
- [ ] Add to cart button state changes are announced
- [ ] Loading states during AJAX calls are announced

---

## Test Suite 2: Keyboard Navigation Testing

### 2.1 Navigation Flow

**Test Steps:**
1. Use only keyboard (Tab, Shift+Tab, Arrow keys, Enter, Space)
2. Navigate through entire experience page

**Expected Results:**
- [ ] All interactive elements are reachable via keyboard
- [ ] Tab order follows logical reading flow
- [ ] Focus indicators are clearly visible (outline or background change)
- [ ] No keyboard traps (ability to navigate away from any element)
- [ ] Skip links work if present

**Key Navigation Patterns:**
- `Tab` / `Shift+Tab` - Move between focusable elements
- `Enter` / `Space` - Activate buttons and links
- `Arrow keys` - Navigate within components (time slots, FAQ)
- `Esc` - Close modals or return to parent navigation

### 2.2 Time Slot Selection

**Test Steps:**
1. Navigate to time slots using Tab key
2. Use arrow keys to move between slots
3. Press Enter or Space to select

**Expected Results:**
- [ ] First time slot receives focus when tabbing to group
- [ ] Arrow keys move focus between available slots
- [ ] Arrow keys skip unavailable slots or announce them as disabled
- [ ] Enter/Space selects the focused slot
- [ ] Selection is visually and audibly confirmed
- [ ] Selected slot updates booking summary

**Specific Tests:**
- [ ] Right/Down arrows move to next slot
- [ ] Left/Up arrows move to previous slot  
- [ ] Arrow navigation wraps around (first ↔ last)
- [ ] Unavailable slots are focusable but announced as unavailable

### 2.3 FAQ Accordion

**Test Steps:**
1. Navigate to FAQ section using Tab
2. Use arrow keys to navigate between FAQ items
3. Press Enter/Space to expand/collapse

**Expected Results:**
- [ ] Arrow keys move between FAQ question buttons
- [ ] Enter/Space toggles expanded/collapsed state
- [ ] Screen reader announces state changes
- [ ] Focus remains on the question button when toggling
- [ ] Content is properly associated with headers

### 2.4 Quantity Controls

**Test Steps:**
1. Navigate to participant quantity section
2. Test +/- buttons and direct input

**Expected Results:**
- [ ] Plus/minus buttons are keyboard accessible
- [ ] Number inputs accept direct keyboard entry
- [ ] Tab order moves logically through quantity controls
- [ ] Changes update total price and are announced
- [ ] Min/max limits are enforced and announced

---

## Test Suite 3: Color Contrast and Visual Accessibility

### 3.1 Color Contrast Testing

**Test Steps:**
1. Use WebAIM Contrast Checker or browser dev tools
2. Test all text/background combinations

**Required Ratios (WCAG AA):**
- [ ] Normal text: 4.5:1 minimum
- [ ] Large text (18pt+): 3:1 minimum  
- [ ] UI components: 3:1 minimum

**Critical Elements to Test:**
- [ ] Body text (#555 on white background)
- [ ] Orange brand text (#b24a25 on white background)
- [ ] Button text on orange background (#ff6b35)
- [ ] Placeholder text and help text
- [ ] Error message text  
- [ ] Link text in all states (normal, hover, visited)

### 3.2 Visual Focus Indicators

**Test Steps:**
1. Navigate page using only keyboard
2. Verify focus indicators are visible

**Expected Results:**
- [ ] All focusable elements show clear focus indication
- [ ] Focus indicators have sufficient contrast (3:1 minimum)
- [ ] Focus indicators are not hidden by other elements
- [ ] Custom focus styles match the design while remaining accessible

---

## Test Suite 4: Dynamic Content and AJAX

### 4.1 Time Slot Loading

**Test Steps:**
1. Select a date in the booking widget
2. Observe loading behavior and announcements

**Expected Results:**
- [ ] Loading state is announced: "Loading available times"
- [ ] Content updates are announced when complete
- [ ] Errors are announced in aria-live regions
- [ ] Focus management is appropriate during loading

### 4.2 Form Validation

**Test Steps:**
1. Try to submit booking form with missing information
2. Verify error handling

**Expected Results:**
- [ ] Validation errors are announced immediately
- [ ] Error messages use aria-live="assertive" for urgent feedback
- [ ] Focus moves to first invalid field when possible
- [ ] Error messages are associated with relevant form fields

---

## Test Suite 5: Mobile Accessibility

### 5.1 Mobile Screen Reader Testing

**Test Steps:**
1. Test on iOS with VoiceOver or Android with TalkBack
2. Navigate through booking widget

**Expected Results:**
- [ ] All functionality available on mobile
- [ ] Touch gestures work with screen reader enabled
- [ ] Sticky booking widget accessibility is maintained
- [ ] No content is hidden or inaccessible on small screens

### 5.2 Mobile Keyboard Testing

**Test Steps:**
1. Connect external keyboard to mobile device
2. Test keyboard navigation

**Expected Results:**
- [ ] All keyboard shortcuts work on mobile
- [ ] Virtual keyboard doesn't obscure content
- [ ] Focus management works with virtual keyboard

---

## Test Results Documentation

### Pass Criteria
- ✅ **Pass**: Feature works correctly with screen reader/keyboard
- ⚠️ **Partial**: Feature mostly works but has minor issues  
- ❌ **Fail**: Feature doesn't work or has major accessibility barriers

### Issue Reporting Template

**Issue Found:**
- **Test**: [Test name and section]
- **Browser/SR**: [Browser and screen reader combination]
- **Expected**: [What should happen]
- **Actual**: [What actually happened]
- **Severity**: [Critical/High/Medium/Low]
- **Steps to Reproduce**: [Detailed steps]

### Common Issues to Watch For

1. **Screen Reader Issues:**
   - Content not announced
   - Incorrect announcements
   - Missing labels or context
   - Redundant or verbose announcements

2. **Keyboard Navigation Issues:**
   - Elements not reachable
   - Incorrect tab order
   - Focus traps
   - Missing visual focus indicators

3. **Dynamic Content Issues:**
   - Loading states not announced
   - Content changes not announced
   - Error messages not announced
   - Focus lost during updates

---

## Automated Testing Supplement

While this manual testing is comprehensive, also run the automated accessibility tests:

```bash
# Run the plugin's accessibility validation
python3 accessibility-test.py

# Additional tools (if available)
# - axe-core browser extension
# - WAVE Web Accessibility Evaluator
# - Lighthouse accessibility audit
```

Remember that automated tests catch only ~30% of accessibility issues - manual testing is essential for comprehensive coverage.