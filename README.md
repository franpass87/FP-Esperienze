# FP Esperienze

A WordPress + WooCommerce plugin for experience booking management by Francesco Passeri.

## Features

- **Experience Product Type**: Custom WooCommerce product type for bookable experiences
- **Booking Management**: Complete booking system with slots, schedules, and capacity management
- **Meeting Points**: GPS-enabled meeting points for experiences
- **Extras**: Additional services and add-ons
- **Vouchers**: PDF vouchers with QR codes
- **REST API**: Real-time availability checking
- **Frontend Templates**: GetYourGuide-style single experience pages
- **Admin Dashboard**: Comprehensive management interface

## Requirements

- PHP >= 8.1
- WordPress >= 6.5
- WooCommerce >= 8.0

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

With options:
```
[fp_exp_archive posts_per_page="12" columns="3" orderby="date" order="DESC"]
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
- `fp_vouchers` - PDF vouchers with QR codes

## Author

**Francesco Passeri**

## License

GPL v2 or later