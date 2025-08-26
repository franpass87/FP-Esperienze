# FP Esperienze Hooks and Filters

This document describes the available hooks and filters in the FP Esperienze plugin.

## Actions (Hooks)

### Booking Management

#### `fp_esperienze_booking_created`
Fired when a new booking is created.

**Parameters:**
- `$booking_id` (int): The ID of the newly created booking
- `$order_id` (int): The WooCommerce order ID
- `$order_item_id` (int): The WooCommerce order item ID

**Example:**
```php
add_action('fp_esperienze_booking_created', function($booking_id, $order_id, $order_item_id) {
    // Send confirmation email
    // Update external systems
    // Log booking creation
    error_log("New booking created: $booking_id for order $order_id");
});
```

#### `fp_esperienze_booking_status_updated`
Fired when a booking status is updated (e.g., refund, cancellation).

**Parameters:**
- `$booking_id` (int): The booking ID
- `$new_status` (string): The new booking status
- `$old_status` (string): The previous booking status

**Example:**
```php
add_action('fp_esperienze_booking_status_updated', function($booking_id, $new_status, $old_status) {
    if ($new_status === 'cancelled') {
        // Send cancellation notification
        // Update availability
        // Process refund if needed
    }
});
```

## Filters

### Future Enhancement Opportunities

The following filters could be added in future updates:

#### `fp_esperienze_booking_data`
Filter booking data before saving to database.

#### `fp_esperienze_booking_statuses`
Filter available booking statuses.

#### `fp_esperienze_calendar_events`
Filter calendar event data before sending to frontend.

## Database Schema

### `wp_fp_bookings` Table Structure

```sql
CREATE TABLE wp_fp_bookings (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    order_id bigint(20) unsigned NOT NULL,
    order_item_id bigint(20) unsigned NOT NULL,
    product_id bigint(20) unsigned NOT NULL,
    booking_date date NOT NULL,
    booking_time time NOT NULL,
    adults int(11) NOT NULL DEFAULT 0,
    children int(11) NOT NULL DEFAULT 0,
    meeting_point_id bigint(20) unsigned DEFAULT NULL,
    status varchar(20) NOT NULL DEFAULT 'confirmed',
    customer_notes text DEFAULT NULL,
    admin_notes text DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY order_id (order_id),
    KEY product_id (product_id),
    KEY booking_date (booking_date),
    KEY status (status)
);
```

### Booking Statuses

- `pending`: Booking is awaiting confirmation
- `confirmed`: Booking is confirmed and active
- `cancelled`: Booking has been cancelled
- `refunded`: Booking has been refunded

## API Methods

### BookingManager Class

#### `getBookings(array $args = []): array`
Retrieve bookings with filtering options.

**Parameters:**
- `status` (string): Filter by booking status
- `product_id` (int): Filter by experience product ID
- `date_from` (string): Start date for date range filter (Y-m-d format)
- `date_to` (string): End date for date range filter (Y-m-d format)
- `limit` (int): Maximum number of results (default: 50)
- `offset` (int): Number of results to skip (default: 0)
- `orderby` (string): Field to order by (default: 'created_at')
- `order` (string): Sort direction 'ASC' or 'DESC' (default: 'DESC')

#### `getBookingCount(array $args = []): int`
Get total count of bookings matching criteria.

**Parameters:**
Same filtering options as `getBookings()`.

## Usage Examples

### Creating Custom Booking Reports

```php
// Get all confirmed bookings for this month
$booking_manager = new \FP\Esperienze\Booking\BookingManager();
$bookings = $booking_manager->getBookings([
    'status' => 'confirmed',
    'date_from' => date('Y-m-01'),
    'date_to' => date('Y-m-t'),
    'limit' => -1
]);

foreach ($bookings as $booking) {
    // Process booking data
    echo "Booking #{$booking->id} on {$booking->booking_date}\n";
}
```

### Adding Custom Booking Validation

```php
add_action('fp_esperienze_booking_created', function($booking_id, $order_id, $order_item_id) {
    global $wpdb;
    
    // Get booking details
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}fp_bookings WHERE id = %d",
        $booking_id
    ));
    
    // Custom validation logic
    if ($booking->adults + $booking->children > 10) {
        // Handle large group booking
        update_booking_notes($booking_id, 'Large group - requires special handling');
    }
});
```

### Integrating with External Systems

```php
add_action('fp_esperienze_booking_status_updated', function($booking_id, $new_status, $old_status) {
    if ($new_status === 'confirmed' && $old_status === 'pending') {
        // Sync with external calendar system
        sync_external_calendar($booking_id);
        
        // Send to CRM
        update_crm_booking($booking_id, $new_status);
    }
});
```