# FP Esperienze - Iframe Widget Integration

This document describes the iframe widget feature that allows embedding FP Esperienze booking widgets on external websites.

## Overview

The iframe widget system provides a way to embed experience booking functionality on external websites while maintaining security by handling checkout on the main WordPress site.

## Features

- **Embeddable Widget**: Self-contained iframe that can be embedded on any website
- **Cross-Domain Security**: Checkout redirects to main site for secure payment processing
- **Responsive Design**: Automatically adapts to different screen sizes
- **Theme Support**: Light and dark theme options
- **Real-time Communication**: PostMessage API for iframe-parent communication
- **Return URL Support**: Redirect users back to original site after booking

## Quick Start

### 1. Basic Iframe Embedding

```html
<iframe 
    src="https://yoursite.com/wp-json/fp-exp/v1/widget/iframe/123"
    width="100%" 
    height="600" 
    frameborder="0"
    title="Experience Booking">
</iframe>
```

### 2. With Custom Options

```html
<iframe 
    src="https://yoursite.com/wp-json/fp-exp/v1/widget/iframe/123?theme=dark&return_url=https://example.com/thank-you"
    width="400" 
    height="600" 
    frameborder="0"
    title="Experience Booking">
</iframe>
```

## API Endpoints

### Widget Iframe
- **URL**: `/wp-json/fp-exp/v1/widget/iframe/{product_id}`
- **Method**: GET
- **Returns**: Complete HTML page for iframe embedding

**Parameters:**
- `theme` (optional): `light` or `dark` (default: `light`)
- `width` (optional): Widget width (default: `100%`)
- `height` (optional): Widget height (default: `600px`)
- `return_url` (optional): URL to redirect after successful booking

### Widget Data (JSON)
- **URL**: `/wp-json/fp-exp/v1/widget/data/{product_id}`
- **Method**: GET
- **Returns**: JSON data for custom widget implementations

## JavaScript Integration

```javascript
const allowedOrigins = ['https://partner.example', 'https://reseller.example'];

// Listen for widget events
window.addEventListener('message', function(event) {
    if (!allowedOrigins.includes(event.origin)) {
        return;
    }

    if (event.data.type === 'fp_widget_ready') {
        console.log('Widget loaded successfully');
    }

    if (event.data.type === 'fp_widget_checkout') {
        // Open checkout in popup or redirect
        window.open(event.data.url, 'checkout', 'width=800,height=600');
    }

    if (event.data.type === 'fp_widget_booking_success') {
        alert('Booking successful! Order ID: ' + event.data.order_id);
    }
});
```

## Booking Flow

1. **User Selection**: User selects experience options in iframe widget
2. **Checkout Trigger**: User clicks "Book Now" button
3. **Data Transfer**: Widget sends booking data via PostMessage or URL parameters
4. **Checkout Redirect**: User is redirected to main WordPress site checkout
5. **Payment Processing**: Standard WooCommerce checkout process
6. **Return Redirect**: User is redirected back to original site (if return_url provided)

## Security

- **Trusted Origin Allow-List**: Only approved origins/hostnames can load the iframe or request widget data. Configure the allow-list via the `fp_esperienze_widget_allowed_origins` filter.
- **CORS Headers**: Responses reflect the requesting origin when it is on the allow-list, preventing hostile embeds from reusing permissive headers.
- **Checkout Security**: All payment processing happens on main WordPress site
- **Data Validation**: All widget parameters are sanitized and validated
- **No Sensitive Data**: No payment information stored in iframe

### Allowing Trusted Domains

Add trusted partner domains to the allow-list using a small must-use plugin or your theme's `functions.php`:

```php
add_filter('fp_esperienze_widget_allowed_origins', function(array $origins): array {
    $origins[] = 'https://partner.example';
    $origins[] = 'https://reseller.example';

    return array_unique($origins);
});
```

Each origin should include the protocol (`https://`). Host-only values such as `partner.example` are normalised to `https://partner.example` automatically. Requests originating from domains outside of this list receive a `403` response and no permissive CORS or `frame-ancestors` headers.

## Styling

The widget includes built-in responsive CSS and supports light/dark themes. For advanced customization, use the JSON API endpoint to build custom widgets.

## Browser Support

- Modern browsers with iframe and PostMessage API support
- Mobile responsive design
- Fallback behavior for older browsers

## Demo

See `widget-demo.html` for a complete integration example.

## Files Added/Modified

### New Files
- `includes/REST/WidgetAPI.php` - REST API for widget functionality
- `includes/Frontend/WidgetCheckoutHandler.php` - Checkout integration
- `widget-demo.html` - Integration demo/documentation

### Modified Files
- `includes/Core/Plugin.php` - Added REST API initialization and widget components

## Requirements

- WordPress 6.5+
- WooCommerce 8.0+
- FP Esperienze plugin
- At least one configured experience product

## Troubleshooting

### Widget Not Loading
- Check product ID is valid and experience type
- Verify REST API endpoints are accessible
- Check for JavaScript console errors

### Checkout Issues
- Ensure WooCommerce is properly configured
- Check that experience has valid schedules and pricing
- Verify cart and checkout pages are working

### Cross-Domain Issues
- Verify CORS headers are being sent
- Check browser security settings
- Test with different domains/protocols