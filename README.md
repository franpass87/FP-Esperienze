# FP Esperienze

A WordPress + WooCommerce plugin for experience booking management by Francesco Passeri.

## Features

- **Experience Product Type**: Custom WooCommerce product type for bookable experiences
- **Booking Management**: Complete booking system with slots, schedules, and capacity management
- **Meeting Points**: GPS-enabled meeting points for experiences
- **Extras**: Additional services and add-ons
- **Gift Vouchers**: Complete gift system with PDF generation, QR codes, and email delivery
- **Voucher Redemption**: Cart/checkout voucher redemption with HMAC validation (Phase 2)
- **Vouchers**: PDF vouchers with QR codes (legacy system)
- **REST API**: Real-time availability checking
- **Frontend Templates**: GetYourGuide-style single experience pages
- **Admin Dashboard**: Comprehensive management interface

## Requirements

- PHP >= 8.1
- WordPress >= 6.5
- WooCommerce >= 8.0

### Dependencies
- `dompdf/dompdf` ^2.0 - PDF generation for vouchers
- `chillerlan/php-qrcode` ^4.3 - QR code generation for vouchers

## Installation

1. Upload the plugin files to `/wp-content/plugins/fp-esperienze/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin settings under 'FP Esperienze' in the admin menu

## Usage

### Creating Experiences

1. Go to Products > Add New
2. Select "Experience" as the product type
3. Fill in the experience details:
   - Duration (minutes)
   - Maximum capacity
   - Adult and child prices
   - Available languages
   - Default meeting point

### Using the Archive Shortcode

Display experiences on any page using:
```
[fp_exp_archive]
```

#### Basic Options:
```
[fp_exp_archive posts_per_page="12" columns="3" orderby="date" order="DESC"]
```

#### With Filters:
```
[fp_exp_archive filters="mp,lang,duration,date" per_page="9" order_by="price"]
```

#### Available Parameters:

- `posts_per_page` / `per_page`: Number of experiences to display (default: 12)
- `columns`: Number of columns in grid layout (1-4, default: 3)
- `orderby` / `order_by`: Sort field - `date`, `name`, `price`, `duration` (default: date)
- `order`: Sort direction - `ASC` or `DESC` (default: DESC)
- `filters`: Comma-separated list of enabled filters:
  - `mp`: Meeting Point filter
  - `lang`: Language filter  
  - `duration`: Duration range filter
  - `date`: Date availability filter

#### Filter Examples:

**Language Filter:**
Shows experiences available in specific languages based on `_fp_exp_langs` meta field.

**Meeting Point Filter:**
Filters by meeting point using select dropdown from available meeting points.

**Duration Filter:**
Range-based filtering:
- "Up to 1.5 hours" (≤90 min)
- "1.5 - 3 hours" (91-180 min)  
- "More than 3 hours" (>180 min)

**Date Availability Filter:**
Shows only experiences with available slots on the selected date using real-time availability data with 5-minute caching.

## Archivio (Shortcode + Block)

### Shortcode Usage

The `[fp_exp_archive]` shortcode provides a complete experience archive with optional filtering capabilities:

```php
// Basic usage
[fp_exp_archive]

// With all filters enabled
[fp_exp_archive filters="mp,lang,duration,date" per_page="12" order_by="name" order="ASC"]

// Meeting point and language filters only
[fp_exp_archive filters="mp,lang" columns="2"]
```

### Gutenberg Block

The "Experience Archive" block is available in the Widgets category and provides the same functionality as the shortcode with a visual interface:

1. Add the "Experience Archive" block to any page/post
2. Configure display settings in the Inspector:
   - Posts per page (1-50)
   - Columns (1-4)
   - Order by (Date, Name, Price, Duration)
   - Sort direction (ASC/DESC)
3. Enable desired filters:
   - Language Filter
   - Meeting Point Filter
   - Duration Filter
   - Date Availability Filter

### Features

- **Responsive Grid Layout**: Automatically adapts to screen size
- **Lazy Loading**: Images load as they come into view
- **Real-time Filtering**: AJAX-powered filters with URL state
- **Availability Caching**: Date-based availability cached for 5 minutes
- **Pagination**: Automatic pagination for large result sets
- **Analytics Integration**: Tracks filter usage and item selections
- **Accessibility**: Proper ARIA labels and screen reader support
- **Mobile Optimized**: Touch-friendly filters and responsive design

### Performance

- **Caching**: Availability queries cached with transients (5-minute TTL)
- **Lazy Loading**: Images use `loading="lazy"` attribute
- **Optimized Queries**: Prevents N+1 database queries
- **Skeleton Loading**: Shows loading states during filter operations

### Analytics Tracking

The archive automatically pushes events to `dataLayer` for Google Analytics:

```javascript
// Item selection tracking
dataLayer.push({
  event: 'select_item',
  items: [{
    item_id: '123',
    item_name: 'Experience Name',
    item_category: 'experience'
  }]
});

// Filter usage tracking  
dataLayer.push({
  event: 'filter_experience',
  filter_type: 'fp_lang',
  filter_value: 'English'
});
```

### REST API

Check availability:
```
GET /wp-json/fp-exp/v1/availability?product_id=123&date=2024-12-01
```

## Frontend (Single)

The plugin provides a GetYourGuide-style single experience template with the following features:

### Template Structure

1. **Hero Section**: Product title, subtitle (excerpt), image gallery, and pricing
2. **Trust/USP Bar**: Duration, languages, cancellation policy, and instant confirmation
3. **Sticky Booking Widget**: Date picker, time slots, participant selection, extras, and pricing
4. **Content Sections**: Description, what's included/excluded, meeting point, FAQ, reviews
5. **Social Proof**: Shows "Only X spots left" when availability is low (≤5 spots)

### Gift Experience Feature

The plugin includes a comprehensive gift voucher system allowing customers to purchase experiences as gifts:

#### Frontend Gift Form
- Toggle-enabled gift form on experience product pages
- Required fields: recipient name and email
- Optional fields: sender name, personal message, send date
- Form validation with immediate feedback

#### Voucher Generation
- Automatic voucher creation when gift orders are completed
- Unique 10-12 character alphanumeric codes
- PDF generation with customizable branding and QR codes
- HMAC-SHA256 signed QR codes for security

#### Email Delivery
- Immediate or scheduled email delivery to recipients
- Professional email templates with voucher details
- PDF attachment with QR code for redemption
- Confirmation emails sent to purchasers

#### Admin Management
- Voucher management interface with filtering and search
- Configurable expiration periods (default 12 months)
- Customizable PDF branding (logo, colors, terms)
- Voucher status tracking (active, redeemed, expired, void)

### Voucher Administration (Phase 3)

A complete admin interface for managing gift vouchers with advanced filtering, bulk actions, and audit logging:

#### Enhanced Voucher Listing
- **Comprehensive Filters**: Status, product, date range, and text search
- **Detailed Information**: Voucher code, product, recipient details, value type, status, expiration, and creation dates
- **Order Integration**: Direct links to associated orders and customer information
- **Real-time Status**: Color-coded status indicators with expiration warnings

#### Individual Actions
- **Download PDF**: Direct access to voucher PDFs with proper headers
- **Copy PDF Link**: One-click clipboard copying of PDF download URLs  
- **Resend Email**: Re-send voucher emails to recipients with PDF regeneration if needed
- **Extend Expiration**: Flexible voucher expiration extension (1-60 months)
- **Void Voucher**: Cancel vouchers with confirmation dialogs

#### Bulk Operations
- **Multi-select**: Checkbox-based selection with "select all" functionality
- **Bulk Void**: Cancel multiple vouchers simultaneously
- **Bulk Resend**: Re-send emails for multiple vouchers at once
- **Bulk Extend**: Extend expiration for multiple vouchers with custom duration

#### Security & Audit
- **Permission Control**: Requires `manage_woocommerce` capability
- **Action Logging**: All actions logged with user information and timestamps
- **Order Integration**: Voucher actions recorded in associated order notes
- **Confirmation Dialogs**: Prevent accidental destructive actions

#### User Experience
- **Responsive Design**: Mobile-friendly table layout
- **JavaScript Enhancements**: Enhanced interactions with graceful degradation
- **Real-time Feedback**: Success/error notifications for all actions
- **Pagination**: Efficient handling of large voucher datasets

#### Access
Available at **FP Esperienze → Vouchers** in the WordPress admin panel.

#### Configuration
Gift voucher settings are available in **FP Esperienze → Settings → Gift Vouchers**:
- Default expiration period in months
- PDF logo and brand color
- Email sender details
- Terms and conditions text
- HMAC security key management

#### Testing
Use the included test script `test-gift-voucher.php` to verify:
- Class loading and dependencies
- Database table structure
- QR code generation and verification
- Settings configuration
- Upload directory permissions

### Voucher Redemption (Phase 2)

The plugin includes a comprehensive voucher redemption system allowing customers to redeem gift vouchers during the booking process:

#### Frontend Redemption Form
- "Have a voucher?" input field in the booking widget
- Real-time voucher validation with clear success/error messaging
- Support for voucher code entry with automatic formatting
- Mobile-responsive design with proper accessibility

#### Voucher Validation
- HMAC SHA256 signature verification for QR code security
- Product compatibility checking (voucher tied to specific experiences)
- Expiration date validation with automatic status updates
- Active/redeemed/expired status verification

#### Discount Application
Two voucher types are supported:
- **Full Vouchers** (`TYPE=full`): Make the entire experience free while preserving extra charges
- **Value Vouchers** (`TYPE=value`): Apply discount up to the voucher amount on base price only

#### Cart Integration
- Session-based voucher storage for cart persistence
- Real-time price calculation with applied discounts
- Clear voucher status display in cart item details
- Automatic voucher removal if cart changes make it incompatible

#### Order Processing
- Automatic voucher redemption when orders are marked as "Completed"
- Voucher rollback to "Active" status on order cancellation or refund
- Order metadata tracking with voucher codes and IDs
- Order notes for audit trail of voucher usage

#### Security Features
- AJAX requests protected with WordPress nonces
- Input sanitization and validation
- HMAC signature verification for QR code payloads
- Protection against voucher manipulation attempts

#### Testing
Use the manual test suite in `MANUAL_TESTS_VOUCHER_REDEMPTION.md` to verify:
- Frontend voucher application flow
- Cart and checkout integration
- Order processing and status changes
- Security and validation features
- Mobile responsiveness and browser compatibility

### Features

- **Responsive Design**: Mobile-first approach with sticky booking widget
- **Accessibility**: Proper heading hierarchy (H1→H2), ARIA labels, focus states
- **SEO**: Schema.org JSON-LD markup for Product structured data
- **Analytics**: GA4 dataLayer events for `view_item` and `select_item`
- **Internationalization**: All text strings use `fp-esperienze` text domain

### Meta Fields

The template supports the following meta fields:

- `_fp_exp_duration` or `_experience_duration`: Duration in minutes
- `_fp_exp_langs` or `_experience_languages`: Comma-separated languages
- `_fp_exp_adult_price` or `_experience_adult_price`: Adult pricing
- `_fp_exp_child_price` or `_experience_child_price`: Child pricing
- `_fp_exp_meeting_point_id`: Meeting point ID
- `_fp_exp_faq`: JSON array of FAQ items `[{"question": "...", "answer": "..."}]`
- `_fp_exp_included`: What's included (newline-separated)
- `_fp_exp_excluded`: What's not included (newline-separated)

### Asset Loading

CSS and JavaScript are conditionally loaded only on:
- Single experience product pages
- Shop/category pages (for archive functionality)
- Pages containing the `[fp_exp_archive]` shortcode

## Development

This plugin follows PSR-4 autoloading with the namespace `FP\Esperienze\`.

### File Structure

```
fp-esperienze/
├── fp-esperienze.php          # Main plugin file
├── includes/
│   ├── Core/                  # Core functionality
│   ├── ProductType/           # Experience product type
│   ├── Admin/                 # Admin interface
│   ├── Frontend/              # Frontend functionality
│   ├── REST/                  # REST API endpoints
│   ├── Booking/               # Booking management
│   └── Data/                  # Data management
├── templates/                 # Template overrides
├── assets/                    # CSS and JavaScript
└── languages/                 # Translations
```

### Database Tables

The plugin creates the following custom tables:
- `fp_meeting_points` - Meeting point locations
- `fp_extras` - Additional services
- `fp_schedules` - Weekly recurring schedules
- `fp_overrides` - Date-specific overrides
- `fp_bookings` - Customer bookings
- `fp_vouchers` - Legacy voucher system (backwards compatibility)
- `fp_exp_vouchers` - Gift voucher system with recipient data and status tracking

### Hooks and Filters

The plugin provides several action hooks and filters for customization:

#### Voucher Redemption Hooks

**Actions:**
- `fp_esperienze_voucher_applied` - Fired when a voucher is successfully applied to cart
- `fp_esperienze_voucher_removed` - Fired when a voucher is removed from cart
- `fp_esperienze_voucher_redeemed` - Fired when a voucher is marked as redeemed
- `fp_esperienze_voucher_rollback` - Fired when a voucher redemption is rolled back

**Filters:**
- `fp_esperienze_voucher_validation` - Modify voucher validation results
- `fp_esperienze_voucher_discount_amount` - Customize discount calculation
- `fp_esperienze_voucher_apply_to_extras` - Control whether vouchers affect extras pricing

#### Cart and Pricing Hooks

**Actions:**
- `fp_esperienze_before_price_calculation` - Before experience price calculation
- `fp_esperienze_after_price_calculation` - After experience price calculation

**Filters:**
- `fp_esperienze_cart_item_price` - Modify calculated cart item price
- `fp_esperienze_experience_base_price` - Modify base experience price
- `fp_esperienze_extra_item_price` - Modify individual extra pricing

#### Order Processing Hooks

**Actions:**
- `fp_esperienze_order_completed` - When experience order is completed
- `fp_esperienze_order_cancelled` - When experience order is cancelled

#### Example Usage

```php
// Customize voucher validation
add_filter('fp_esperienze_voucher_validation', function($validation, $voucher_code, $product_id) {
    // Add custom validation logic
    if (custom_validation_check($voucher_code)) {
        $validation['success'] = false;
        $validation['message'] = 'Custom validation failed';
    }
    return $validation;
}, 10, 3);

// Modify voucher discount amount
add_filter('fp_esperienze_voucher_discount_amount', function($amount, $voucher, $cart_item) {
    // Apply additional business logic
    if (is_special_customer()) {
        $amount *= 1.1; // 10% bonus discount
    }
    return $amount;
}, 10, 3);

// Track voucher redemption
add_action('fp_esperienze_voucher_redeemed', function($voucher_code, $order_id) {
    // Send notification or update external system
    wp_mail('admin@example.com', 'Voucher Redeemed', "Voucher $voucher_code used in order $order_id");
}, 10, 2);
```

## Author

**Francesco Passeri**

## License

GPL v2 or later