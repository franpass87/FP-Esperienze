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