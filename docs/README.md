# FP Esperienze - Booking Widget

## Overview

This WordPress + WooCommerce plugin provides a booking widget for experience products with GetYourGuide-style functionality.

## Features

- **Interactive Booking Widget**: Step-by-step booking process (Date → Slots → Quantity → Cart)
- **REST API**: Real-time availability checking via `/wp-json/fp-exp/v1/availability`
- **Capacity Management**: Shows remaining spots and "Last X spots" warnings
- **Cutoff Validation**: Prevents booking within 2 hours of start time
- **Responsive Design**: Mobile-friendly interface
- **WooCommerce Integration**: Seamless add-to-cart functionality

## Installation

1. Upload the plugin files to `/wp-content/plugins/fp-esperienze/`
2. Activate the plugin through the WordPress admin
3. Ensure WooCommerce is installed and active

## Usage

### For Experience Products

1. Mark a product as an experience by:
   - Adding it to the "experience" product category, OR
   - Setting the custom field `_is_experience` to "yes"

2. The booking widget will automatically appear on single product pages for experience products

### REST API Endpoint

**GET** `/wp-json/fp-exp/v1/availability`

**Parameters:**
- `product_id` (required): The WooCommerce product ID
- `date` (required): Date in YYYY-MM-DD format

**Response:**
```json
{
  "product_id": 123,
  "date": "2024-01-15",
  "slots": [
    {
      "time": "09:00",
      "capacity": 20,
      "booked": 5,
      "capacity_left": 15,
      "available": true,
      "cutoff_passed": false
    }
  ]
}
```

## Booking Flow

1. **Date Selection**: User picks a date using the datepicker
2. **API Call**: Widget fetches availability via REST API
3. **Slot Selection**: Available time slots are displayed with capacity info
4. **Quantity Selection**: User selects adult/child quantities
5. **Validation**: Server validates capacity and cutoff times
6. **Add to Cart**: Experience is added to WooCommerce cart with booking details

## Validation Rules

- **Capacity**: Total quantity cannot exceed remaining slot capacity
- **Cutoff Time**: Bookings not allowed within 2 hours of start time
- **Date Validation**: Past dates are not allowed
- **Minimum Quantity**: At least 1 adult required

## Customization

### Hooks and Filters

- `woocommerce_single_product_summary`: Booking widget display (priority 25)
- `wc_get_template`: Template override for experience products

### Template Override

Create `templates/single-experience.php` in your theme to customize the experience product layout.

### Styling

The widget uses CSS classes prefixed with `fp-` for easy customization:
- `.fp-booking-widget`: Main widget container
- `.fp-booking-step`: Individual booking steps
- `.fp-slot-item`: Time slot buttons
- `.fp-quantity-controls`: Quantity selection interface

## Technical Details

- **Namespace**: `FP\Esperienze\`
- **Text Domain**: `fp-esperienze`
- **Minimum PHP**: 8.1
- **Minimum WordPress**: 6.5
- **Minimum WooCommerce**: 8.0

## Security

- Nonce validation for AJAX requests
- Input sanitization and validation
- Capability checks for admin functions
- XSS protection with proper escaping

## Browser Support

- Modern browsers (Chrome, Firefox, Safari, Edge)
- Mobile responsive design
- Progressive enhancement for JavaScript features