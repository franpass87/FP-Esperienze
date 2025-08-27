# Manual Tests for Booking Management Feature

## Pre-requisites
1. WordPress site with WooCommerce installed
2. FP Esperienze plugin activated 
3. At least one experience product created
4. Experience product configured with schedules and meeting points

## Test 1: Order Processing - Booking Creation

### Setup
1. Create an experience product with the following:
   - Adult price: €50
   - Child price: €25
   - Schedules configured for multiple days
   - Meeting point assigned

### Test Steps
1. Add experience product to cart
2. Fill booking form with:
   - Select a future time slot
   - Adults: 2
   - Children: 1
   - Meeting point selection
   - Any extras if available
3. Complete checkout process
4. Go to WooCommerce Orders
5. Change order status to "Processing"

### Expected Results
- New entry created in `wp_fp_bookings` table
- Booking entry should contain:
  - `order_id`: Order ID
  - `order_item_id`: Order item ID  
  - `product_id`: Experience product ID
  - `booking_date`: Selected date
  - `booking_time`: Selected time
  - `adults`: 2
  - `children`: 1
  - `meeting_point_id`: Selected meeting point
  - `status`: "confirmed"
  - `admin_notes`: "Created from order #[ORDER_ID]"

### SQL Verification
```sql
SELECT * FROM wp_fp_bookings WHERE order_id = [ORDER_ID];
```

## Test 2: Refund Handling

### Test Steps
1. Use order from Test 1
2. Go to WooCommerce Orders
3. Issue partial or full refund for the experience item
4. Check booking status

### Expected Results
- Booking status updated to "refunded"
- `updated_at` timestamp updated

### SQL Verification
```sql
SELECT * FROM wp_fp_bookings WHERE order_id = [ORDER_ID];
```

## Test 3: Order Cancellation

### Test Steps
1. Create another order with experience product
2. Change order status to "Cancelled"

### Expected Results
- All bookings for that order have status "cancelled"

## Test 4: Admin Bookings List

### Test Steps
1. Go to WP Admin → FP Esperienze → Bookings
2. Verify list view shows all bookings
3. Test filters:
   - Filter by status (confirmed, cancelled, refunded)
   - Filter by product
   - Filter by date range
4. Test "Clear" button
5. Verify data displayed:
   - Booking ID
   - Order ID (linked to order edit page)
   - Product name
   - Date & time formatted correctly
   - Participant count
   - Status with color coding
   - Meeting point name
   - Creation date

### Expected Results
- All filters work correctly
- Data displays accurately
- Links function properly
- Status colors display correctly:
  - Green for confirmed
  - Red for cancelled
  - Yellow for refunded

## Test 5: CSV Export

### Test Steps
1. In Admin Bookings page
2. Apply some filters (optional)
3. Click "Export CSV"

### Expected Results
- CSV file downloads with filename: `bookings-YYYY-MM-DD-HH-MM-SS.csv`
- CSV contains all filtered bookings
- Headers match the expected columns
- Data exports correctly with proper encoding

## Test 6: Calendar View

### Test Steps
1. In Admin Bookings page
2. Click "Calendar View" button
3. Wait for FullCalendar to load
4. Navigate between months
5. Click on booking events

### Expected Results
- FullCalendar loads successfully from CDN
- Bookings display as events on calendar
- Events color-coded by status
- Event click shows booking details popup
- Month navigation works
- Events show correct titles with participant count

## Test 7: Calendar Data API

### Test Steps
1. Test REST endpoint directly:
   `GET /wp-json/fp-exp/v1/bookings/calendar?start=2024-01-01&end=2024-01-31`

### Expected Results
- Returns JSON array of FullCalendar event objects
- Each event contains:
  - `id`: booking ID
  - `title`: product name with participant count
  - `start`: booking datetime in ISO format
  - `end`: calculated end time
  - `color`: status-based color
  - `extendedProps`: booking details

## Test 8: Error Handling

### Test Steps
1. Test with malformed order item meta
2. Test with missing required booking data
3. Test with deleted products
4. Test with invalid dates

### Expected Results
- No fatal errors
- Appropriate error logging
- Graceful fallbacks for missing data
- Admin notices for failures

## Test 9: Duplicate Prevention

### Test Steps
1. Complete an order (triggers booking creation)
2. Manually change order status to processing again
3. Check for duplicate bookings

### Expected Results
- No duplicate bookings created
- `bookingExistsForOrderItem()` prevents duplicates

## Test 10: Performance

### Test Steps
1. Create 100+ bookings
2. Test admin list performance
3. Test calendar rendering with many events
4. Test CSV export with large dataset

### Expected Results
- Pages load in reasonable time
- No memory issues
- CSV export completes successfully

## Database Schema Verification

Check that the `wp_fp_bookings` table exists with correct structure:

```sql
DESCRIBE wp_fp_bookings;
```

Expected columns:
- `id` (primary key)
- `order_id` 
- `order_item_id`
- `product_id`
- `booking_date`
- `booking_time`
- `adults`
- `children`
- `meeting_point_id`
- `status`
- `customer_notes`
- `admin_notes`
- `created_at`
- `updated_at`

## Hook Documentation

### WooCommerce Hooks Used
- `woocommerce_order_status_processing`: Creates bookings
- `woocommerce_order_status_completed`: Creates bookings  
- `woocommerce_order_refunded`: Handles refunds
- `woocommerce_order_status_cancelled`: Cancels bookings
- `woocommerce_order_status_refunded`: Cancels bookings

### REST API Endpoints
- `GET /wp-json/fp-exp/v1/bookings`: General bookings API
- `GET /wp-json/fp-exp/v1/bookings/calendar`: Calendar-specific format

### Admin Filters Available
- `fp_esperienze_booking_statuses`: Modify available booking statuses
- `fp_esperienze_csv_headers`: Customize CSV export headers
- `fp_esperienze_csv_data`: Modify CSV export data

## Security Checklist

- [ ] Admin pages check `manage_options` capability
- [ ] REST endpoints verify user permissions
- [ ] All user input is sanitized
- [ ] Nonce verification on form submissions
- [ ] SQL queries use prepared statements
- [ ] No direct database access from frontend