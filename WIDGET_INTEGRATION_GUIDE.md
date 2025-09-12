# Widget Integration Guide

## Quick Setup

### Step 1: Find Your Experience ID
1. Go to **Products > All Products** in WordPress admin
2. Find your experience product and note the ID in the URL or edit screen

### Step 2: Generate Widget Code
Use this template to embed your booking widget anywhere:

```html
<iframe 
    src="https://YOURSITE.COM/wp-json/fp-exp/v1/widget/iframe/EXPERIENCE_ID"
    width="100%" 
    height="600" 
    frameborder="0"
    title="Experience Booking Widget">
</iframe>
```

**Replace:**
- `YOURSITE.COM` with your WordPress site URL
- `EXPERIENCE_ID` with your experience product ID

### Step 3: Customization Options

Add these parameters to the iframe URL:

- **Theme**: `?theme=dark` for dark mode
- **Return URL**: `?return_url=https://example.com/thanks` to redirect after booking
- **Combined**: `?theme=dark&return_url=https://example.com/thanks`

## Examples

### Basic Widget
```html
<iframe src="https://mysite.com/wp-json/fp-exp/v1/widget/iframe/123" 
        width="100%" height="600" frameborder="0"></iframe>
```

### Dark Theme with Return URL
```html
<iframe src="https://mysite.com/wp-json/fp-exp/v1/widget/iframe/123?theme=dark&return_url=https://partner.com/success" 
        width="400" height="600" frameborder="0"></iframe>
```

### JavaScript Integration
```html
<script>
// Listen for widget events
window.addEventListener('message', function(event) {
    if (event.data.type === 'fp_widget_checkout') {
        // Open checkout in popup
        window.open(event.data.url, 'checkout', 'width=800,height=600');
    }
    
    if (event.data.type === 'fp_widget_booking_success') {
        alert('Booking successful! Order ID: ' + event.data.order_id);
    }
});
</script>

<iframe src="https://mysite.com/wp-json/fp-exp/v1/widget/iframe/123" 
        width="100%" height="600" frameborder="0"></iframe>
```

## Testing

1. **Test the widget URL directly**: Visit `https://yoursite.com/wp-json/fp-exp/v1/widget/iframe/123` in your browser
2. **Check the demo**: Open `widget-demo.html` in the plugin folder
3. **Verify checkout flow**: Ensure users can complete bookings through the widget

## Troubleshooting

### Widget doesn't load
- Check that the experience ID exists and is published
- Verify the experience product type is set to "experience"
- Check browser console for JavaScript errors

### Checkout issues
- Ensure WooCommerce is properly configured
- Verify cart and checkout pages are working
- Check that the experience has valid schedules and pricing

### Cross-domain problems
- Test with different browsers
- Check for browser security restrictions
- Verify CORS headers are being sent

## Security Notes

- All payment processing happens on your main WordPress site
- Widget only handles display and selection - not sensitive data
- Return URLs are validated for security
- All input is sanitized and validated

## Demo

See `widget-demo.html` for a complete working example with all features demonstrated.