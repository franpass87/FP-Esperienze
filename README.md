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