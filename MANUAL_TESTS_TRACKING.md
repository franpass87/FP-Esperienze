# Manual Tests - Frontend Tracking (GA4 & Meta Pixel)

## Test Environment Setup

1. **Enable Integrations**: Go to **FP Esperienze → Settings → Integrations**
2. **Configure GA4**: 
   - Set Measurement ID: `G-TEST123456`
   - Enable Enhanced eCommerce: ✓
3. **Configure Meta Pixel**:
   - Set Pixel ID: `123456789012345`
4. **Save Settings**
5. **Open Browser DevTools**: Console and Network tabs

## Test Cases

### 1. Script Loading Verification

**Objective**: Verify tracking scripts load only on appropriate pages

**Steps**:
1. Navigate to experience product page
2. Check Network tab for:
   - `tracking.js` loaded
   - `gtag/js?id=G-TEST123456` loaded
   - `fbevents.js` loaded
3. Navigate to regular WP page (non-WooCommerce)
4. Verify tracking scripts NOT loaded
5. Navigate to cart/checkout pages
6. Verify tracking scripts ARE loaded

**Expected Results**:
- Scripts load only on experience pages, cart, checkout
- No 404 errors for tracking assets
- Console shows tracking system initialization

### 2. GA4 view_item Event

**Objective**: Test view_item event on experience page load

**Steps**:
1. Open experience product page
2. Open DevTools Console
3. Check for dataLayer event:
```javascript
// Should appear in console
dataLayer.push({
  event: 'view_item',
  ecommerce: {
    currency: 'EUR',
    value: [price],
    items: [{
      item_id: '[product_id]',
      item_name: '[product_name]',
      item_category: 'Experience',
      price: [price],
      quantity: 1,
      slot_start: null,
      meeting_point_id: null,
      lang: ['English', 'Italian'] // available languages
    }]
  }
});
```

**Expected Results**:
- Event fires on page load
- All parameters populated correctly
- slot_start and meeting_point_id are null initially
- lang contains available languages array

### 3. GA4 select_item Event

**Objective**: Test select_item event when slot is selected

**Steps**:
1. On experience page, select a date with availability
2. Click on a time slot 
3. Check Console for dataLayer event:
```javascript
dataLayer.push({
  event: 'select_item',
  item_list_name: 'Available Time Slots',
  items: [{
    item_id: '[product_id]',
    item_name: '[product_name]',
    item_category: 'Experience',
    price: [slot_price],
    quantity: 1,
    slot_start: '[selected_time]',
    meeting_point_id: '[meeting_point_id]',
    lang: '[selected_language]'
  }]
});
```

**Expected Results**:
- Event fires when slot selected
- slot_start shows selected time
- meeting_point_id populated
- lang shows selected language

### 4. GA4 add_to_cart Event

**Objective**: Test add_to_cart tracking

**Steps**:
1. Select date, time slot, quantities
2. Click "Add to Cart"
3. On cart page, check Console for:
```javascript
// GA4 Event
dataLayer.push({
  event: 'add_to_cart',
  ecommerce: {
    currency: 'EUR',
    value: [total_value],
    items: [{
      item_id: '[product_id]',
      item_name: '[product_name]',
      item_category: 'Experience',
      price: [price],
      quantity: 1,
      slot_start: '[selected_slot]',
      meeting_point_id: '[meeting_point_id]',
      lang: '[language]'
    }]
  }
});
```

**Expected Results**:
- Event fires on cart page after add to cart
- Complete slot and location data included
- Total value calculated correctly

### 5. GA4 begin_checkout Event

**Objective**: Test checkout initiation tracking

**Steps**:
1. From cart, click "Proceed to Checkout"
2. On checkout page, check Console for:
```javascript
dataLayer.push({
  event: 'begin_checkout',
  ecommerce: {
    currency: 'EUR',
    value: [cart_total],
    items: [/* cart items with experience data */]
  }
});
```

**Expected Results**:
- Event fires on checkout page load
- All cart items included with experience metadata

### 6. GA4 add_payment_info Event

**Objective**: Test payment info addition tracking

**Steps**:
1. On checkout page, fill payment details
2. Before submitting, check Console for add_payment_info event
3. Verify event structure matches begin_checkout

**Expected Results**:
- Event fires when payment form is processed
- Contains same item data as begin_checkout

### 7. GA4 purchase Event

**Objective**: Test purchase completion tracking

**Steps**:
1. Complete a test order
2. On order confirmation page, check Console for:
```javascript
dataLayer.push({
  event: 'purchase',
  ecommerce: {
    transaction_id: '[order_number]',
    currency: 'EUR',
    value: [order_total],
    items: [/* ordered items with experience data */]
  }
});
```

**Expected Results**:
- Event fires on order received page
- transaction_id matches order number
- All experience metadata preserved

### 8. Meta Pixel AddToCart Event

**Objective**: Test Meta Pixel cart tracking

**Steps**:
1. Add experience to cart
2. Check Console for:
```javascript
fbq('track', 'AddToCart', {
  value: [total_value],
  currency: 'EUR',
  content_ids: ['[product_id]'],
  content_type: 'product'
});
```

**Expected Results**:
- Event fires alongside GA4 add_to_cart
- Correct value and currency
- Product ID in content_ids

### 9. Meta Pixel InitiateCheckout Event

**Objective**: Test Meta Pixel checkout tracking

**Steps**:
1. Navigate to checkout
2. Check Console for InitiateCheckout event
3. Verify value and content_ids populated

**Expected Results**:
- Event fires on checkout page load
- Contains cart total and product IDs

### 10. Meta Pixel Purchase Event

**Objective**: Test Meta Pixel purchase tracking with event_id

**Steps**:
1. Complete order
2. Check Console for:
```javascript
fbq('track', 'Purchase', {
  value: [order_total],
  currency: 'EUR',
  content_ids: ['[product_id]'],
  content_type: 'product',
  event_id: '[unique_event_id]'
});
```

**Expected Results**:
- Event fires on order confirmation
- event_id present for CAPI deduplication
- Correct purchase value

### 11. Settings Disable Test

**Objective**: Verify tracking respects admin settings

**Steps**:
1. Disable GA4 Enhanced eCommerce in settings
2. Navigate to experience page
3. Verify no GA4 events fire but Meta Pixel still works
4. Disable Meta Pixel 
5. Verify no Meta events fire
6. Verify tracking.js doesn't load when both disabled

**Expected Results**:
- Settings properly control event firing
- Scripts don't load when integrations disabled
- No JavaScript errors when tracking disabled

### 12. Error Handling Test

**Objective**: Test graceful handling of missing data

**Steps**:
1. Navigate to experience page with invalid/missing:
   - Meeting point data
   - Language data
   - Pricing data
2. Verify events still fire with null values
3. Check for JavaScript errors

**Expected Results**:
- No JavaScript errors
- Events fire with null/fallback values
- Tracking continues to work

## Browser Compatibility

Test on:
- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Mobile Chrome
- Mobile Safari

## Performance Verification

**Check**:
- Page load times with tracking enabled vs disabled
- No blocking of page rendering
- Lazy loading of tracking scripts
- Minimal impact on Core Web Vitals

## Debugging Tips

**Console Commands**:
```javascript
// Check if tracking is loaded
window.FPTracking

// Verify settings
window.fpTrackingSettings

// Check dataLayer
window.dataLayer

// Check Meta Pixel
window.fbq

// Manual event trigger
$(document).trigger('fp_track_view_item', {...});
```

## Expected Console Output

With tracking enabled, you should see:
```
GA4 Event: view_item {ecommerce: {...}}
GA4 Event: select_item {ecommerce: {...}}
GA4 Event: add_to_cart {ecommerce: {...}}
Meta Pixel Event: AddToCart {...}
GA4 Event: begin_checkout {ecommerce: {...}}
Meta Pixel Event: InitiateCheckout {...}
GA4 Event: purchase {ecommerce: {...}}
Meta Pixel Event: Purchase {...}
```