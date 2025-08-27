# Manual Test Guide - Single Experience GetYourGuide Style

## Pre-requisites
- WordPress site with WooCommerce active
- FP Esperienze plugin installed and activated
- At least one Experience product created with complete data
- A meeting point configured
- Browser developer tools access for testing GA4 events
- Mobile device or browser dev tools for responsive testing

## Test Data Setup

Before testing, ensure you have an Experience product with:
- Product title and description
- Product excerpt (for subtitle)
- Featured image and gallery images
- Duration: 120 minutes
- Languages: "English, Italian, Spanish"
- Adult price: €45.00
- Child price: €25.00
- Meeting point assigned
- At least one extra configured
- FAQ data in `_fp_exp_faq` meta field:
  ```json
  [
    {"question": "What should I bring?", "answer": "Comfortable walking shoes and a camera."},
    {"question": "Is this suitable for children?", "answer": "Yes, children over 3 years old are welcome."}
  ]
  ```

## Test 1: Hero Section and Layout

### 1.1 Hero Section Display
1. Navigate to the single experience product page
2. Verify hero section displays:
   - Product title as H1
   - Subtitle from excerpt
   - Featured image on left, content on right
   - Gallery thumbnails overlaid on image (if available)
   - "From €45.00 per person" pricing

**Expected Result**: Hero section displays in GetYourGuide style with proper layout.

### 1.2 Mobile Hero Layout
1. Switch to mobile view (≤768px width)
2. Verify hero layout stacks vertically
3. Check image displays full width
4. Verify title and pricing remain readable

**Expected Result**: Hero section is responsive and readable on mobile.

## Test 2: Trust/USP Bar

### 2.1 Trust Elements Display
1. Below hero, verify trust bar shows:
   - Duration: "⏰ Duration: 120 minutes"
   - Languages: "🗣️ Languages: English, Italian, Spanish" (as chips)
   - Cancellation: "✅ Cancellation: Free cancellation up to 24 hours"
   - Booking: "📱 Booking: Instant confirmation"

**Expected Result**: All trust elements display with proper icons and formatting.

### 2.2 Language Chips
1. Verify languages display as individual chips
2. Check styling matches design (gray background, rounded)
3. Test responsive behavior on mobile

**Expected Result**: Language chips display correctly and are responsive.

## Test 3: Booking Widget

### 3.1 Sticky Behavior (Desktop)
1. Scroll down the page
2. Verify booking widget remains sticky in sidebar
3. Check it doesn't overlap with footer

**Expected Result**: Widget stays positioned correctly while scrolling.

### 3.2 Date Selection
1. Click date picker in booking widget
2. Verify minimum date is today
3. Select a future date
4. Verify "Loading available times..." appears

**Expected Result**: Date picker works and triggers availability loading.

### 3.3 Time Slot Selection
1. After selecting date, verify time slots load
2. Click on an available time slot
3. Verify slot becomes selected (highlighted)
4. Check GA4 event fires (see Test 8)

**Expected Result**: Time slots load and selection works properly.

### 3.4 Social Proof (Low Availability)
1. Find or create a slot with ≤5 available spots
2. Select that time slot
3. Verify social proof message appears: "Only X spots left!"
4. Test with 1 spot: "Only 1 spot left!"

**Expected Result**: Social proof shows only when availability is low.

### 3.5 Participant Selection
1. Use quantity controls to adjust adult count
2. Adjust child count (if child pricing exists)
3. Verify total price updates in real-time
4. Test max capacity limits

**Expected Result**: Quantity controls work and pricing updates correctly.

### 3.6 Extras Selection
1. Toggle optional extras on/off
2. Adjust quantities for extras with max_quantity > 1
3. Verify required extras are pre-selected and non-removable
4. Check pricing calculations for per-person vs per-booking extras

**Expected Result**: Extras selection works with proper price calculations.

### 3.7 Form Validation
1. Try to add to cart without selecting date - button should be disabled
2. Try without selecting time slot - button should be disabled
3. Try with 0 participants - button should be disabled
4. Verify help text updates based on missing requirements

**Expected Result**: Form validation prevents incomplete bookings.

## Test 4: Content Sections

### 4.1 Description Section
1. Verify product description displays properly
2. Check HTML formatting is preserved
3. Verify heading hierarchy (H2 for "About This Experience")

**Expected Result**: Description section displays with proper formatting.

### 4.2 Inclusions Grid
1. Verify "What's Included" section displays
2. Verify "What's Not Included" section displays
3. Check two-column layout on desktop
4. Test single-column on mobile
5. Verify green checkmarks for included items
6. Verify red X marks for excluded items

**Expected Result**: Inclusions display in proper grid with correct icons.

### 4.3 Meeting Point Section
1. Verify meeting point name and address display
2. Check "Open in Google Maps" link works
3. Verify map placeholder displays
4. Test meeting instructions if available

**Expected Result**: Meeting point section displays all information correctly.

## Test 5: FAQ Accordion

### 5.1 FAQ Display
1. Verify FAQ section appears if `_fp_exp_faq` data exists
2. Check all FAQ items display as accordion
3. Verify proper ARIA attributes (aria-expanded, role=tablist, etc.)

**Expected Result**: FAQ section displays with proper accessibility attributes.

### 5.2 FAQ Interaction
1. Click on a FAQ question
2. Verify answer expands with animation
3. Click another question - verify previous closes
4. Test keyboard navigation (Tab, Enter, Space)
5. Verify screen reader compatibility

**Expected Result**: FAQ accordion works with keyboard and screen readers.

## Test 6: Reviews Section

### 6.1 Reviews Placeholder
1. Verify reviews section displays
2. Check disclaimer about future Google integration
3. Verify placeholder structure is in place

**Expected Result**: Reviews section shows placeholder with disclaimer.

## Test 7: Accessibility

### 7.1 Heading Hierarchy
1. Use browser accessibility tools or screen reader
2. Verify H1 for product title
3. Verify H2 for all section headings
4. Check proper nesting (no H1→H3 jumps)

**Expected Result**: Proper heading hierarchy throughout page.

### 7.2 Focus States
1. Use Tab key to navigate through page
2. Verify all interactive elements have visible focus
3. Test booking widget controls
4. Test FAQ accordion with keyboard

**Expected Result**: All interactive elements are keyboard accessible.

### 7.3 ARIA Labels
1. Verify time slots have proper role="radio" and aria-checked
2. Check quantity controls have descriptive aria-labels
3. Verify form fields have proper labels and descriptions
4. Test social proof has role="alert" and aria-live="polite"

**Expected Result**: ARIA attributes provide proper accessibility context.

## Test 8: GA4 Integration

### 8.1 View Item Event
1. Open browser developer tools
2. Navigate to experience product page
3. Check console/network for dataLayer.push with 'view_item' event
4. Verify event structure includes product ID, name, price

**Expected Result**: GA4 view_item event fires on page load.

### 8.2 Select Item Event
1. Keep developer tools open
2. Select a time slot in booking widget
3. Check for dataLayer.push with 'select_item' event
4. Verify event includes slot time as item_variant

**Expected Result**: GA4 select_item event fires on slot selection.

## Test 9: Schema.org Markup

### 9.1 JSON-LD Validation
1. View page source
2. Find JSON-LD script tag
3. Copy JSON content to schema.org validator
4. Verify Product schema with required fields:
   - name, description, brand, offers, image
   - price, priceCurrency, availability

**Expected Result**: Valid Product schema.org markup.

## Test 10: Mobile Experience

### 10.1 Responsive Layout
1. Test on mobile device or browser dev tools (≤480px)
2. Verify booking widget moves above content
3. Check sticky notice appears at bottom
4. Test sticky notice interaction

**Expected Result**: Mobile layout works with sticky booking notice.

### 10.2 Touch Interactions
1. Test quantity controls with touch
2. Test FAQ accordion with touch
3. Test time slot selection
4. Verify all elements are properly sized for touch

**Expected Result**: All touch interactions work smoothly.

## Test 11: Performance

### 11.1 Asset Loading
1. Check that CSS/JS only loads on experience product pages
2. Verify no assets load on regular product pages
3. Test booking-widget.js only loads on single experience pages

**Expected Result**: Conditional asset loading works correctly.

### 11.2 Load Times
1. Test page load speed
2. Check for any console errors
3. Verify smooth animations and transitions

**Expected Result**: Page loads efficiently without errors.

## Test Results Template

### Browser Testing
- [ ] Chrome (Desktop)
- [ ] Firefox (Desktop)
- [ ] Safari (Desktop)
- [ ] Chrome Mobile
- [ ] Safari Mobile
- [ ] Edge

### Test 1: Hero Section
- [ ] 1.1 Hero Section Display
- [ ] 1.2 Mobile Hero Layout

### Test 2: Trust/USP Bar
- [ ] 2.1 Trust Elements Display
- [ ] 2.2 Language Chips

### Test 3: Booking Widget
- [ ] 3.1 Sticky Behavior
- [ ] 3.2 Date Selection
- [ ] 3.3 Time Slot Selection
- [ ] 3.4 Social Proof
- [ ] 3.5 Participant Selection
- [ ] 3.6 Extras Selection
- [ ] 3.7 Form Validation

### Test 4: Content Sections
- [ ] 4.1 Description Section
- [ ] 4.2 Inclusions Grid
- [ ] 4.3 Meeting Point Section

### Test 5: FAQ Accordion
- [ ] 5.1 FAQ Display
- [ ] 5.2 FAQ Interaction

### Test 6: Reviews Section
- [ ] 6.1 Reviews Placeholder

### Test 7: Accessibility
- [ ] 7.1 Heading Hierarchy
- [ ] 7.2 Focus States
- [ ] 7.3 ARIA Labels

### Test 8: GA4 Integration
- [ ] 8.1 View Item Event
- [ ] 8.2 Select Item Event

### Test 9: Schema.org Markup
- [ ] 9.1 JSON-LD Validation

### Test 10: Mobile Experience
- [ ] 10.1 Responsive Layout
- [ ] 10.2 Touch Interactions

### Test 11: Performance
- [ ] 11.1 Asset Loading
- [ ] 11.2 Load Times

## Notes
- Test with JavaScript enabled/disabled
- Verify graceful degradation
- Check for any console errors during testing
- Test with different WordPress themes
- Verify compatibility with common WooCommerce extensions