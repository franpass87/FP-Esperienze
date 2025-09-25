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

### Copy & Paste Recipes

#### WordPress Gutenberg (Custom HTML block)

```html
<!-- Add inside a "Custom HTML" block -->
<div class="fp-esperienze-widget-wrapper" style="max-width:680px;margin:0 auto;">
    <iframe
        src="https://yoursite.com/wp-json/fp-exp/v1/widget/iframe/123?theme=light"
        width="100%"
        height="680"
        loading="lazy"
        style="border:1px solid var(--wp--preset--color--light-gray,#dcdcde);border-radius:12px;"
        title="Book your experience"
        allow="payment"
    ></iframe>
</div>
```

#### Webflow / Squarespace embed

```html
<div data-widget="fp-esperienze" style="max-width:640px;margin:auto;">
  <iframe
    id="fp-esperienze-widget"
    src="https://yoursite.com/wp-json/fp-exp/v1/widget/iframe/123?theme=dark&return_url=https://partner.com/thanks"
    style="width:100%;height:720px;border:0;border-radius:16px;box-shadow:0 15px 45px rgba(0,0,0,0.1);"
    title="Experience booking"
  ></iframe>
</div>
<script>
window.addEventListener('message', function(event) {
  if (!event.data || event.data.type !== 'fp_widget_height_change') {
    return;
  }
  var frame = document.getElementById('fp-esperienze-widget');
  if (frame) {
    frame.style.height = event.data.height + 'px';
  }
});
</script>
```

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

### JavaScript Integration with Auto-Height
```html
<script>
// Listen for widget events and auto-resize iframe
window.addEventListener('message', function(event) {
    if (event.data.type === 'fp_widget_ready') {
        // Widget is loaded - auto-adjust height
        const iframe = document.getElementById('experience-widget');
        if (iframe && event.data.height) {
            iframe.style.height = event.data.height + 'px';
        }
    }
    
    if (event.data.type === 'fp_widget_height_change') {
        // Content height changed - auto-resize iframe
        const iframe = document.getElementById('experience-widget');
        if (iframe && event.data.height) {
            iframe.style.height = event.data.height + 'px';
        }
    }
    
    if (event.data.type === 'fp_widget_checkout') {
        // Open checkout in popup
        window.open(event.data.url, 'checkout', 'width=800,height=600');
    }
    
    if (event.data.type === 'fp_widget_booking_success') {
        alert('Booking successful! Order ID: ' + event.data.order_id);
    }
});
</script>

<iframe id="experience-widget"
        src="https://mysite.com/wp-json/fp-exp/v1/widget/iframe/123" 
        width="100%" height="600" frameborder="0"></iframe>
```

### Auto-Height Adaptation

The widget automatically sends height information to the parent page, allowing for dynamic iframe resizing:

- **Initial Load**: Widget sends `fp_widget_ready` event with content height
- **Dynamic Changes**: Widget monitors content changes and sends `fp_widget_height_change` events
- **Responsive**: Height adjusts automatically when content changes (mobile/desktop)

**Events sent to parent window:**
- `fp_widget_ready` - Widget loaded with initial height
- `fp_widget_height_change` - Content height changed
- `fp_widget_checkout` - User clicked book now
- `fp_widget_booking_success` - Booking completed successfully

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