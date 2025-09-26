# FP Esperienze

A WordPress + WooCommerce plugin for experience booking management by Francesco Passeri.

## What's New in 1.1.0

- **Automated upgrades** – the new upgrade manager executes pending schema and filesystem migrations, refreshes cron schedules, and surfaces errors to administrators.
- **Runtime observability** – a development-only runtime logger captures notices and exceptions in a persistent log with an optional overlay so issues are spotted before production deployments.
- **Hardened booking flows** – REST and mobile endpoints now normalise extras, sanitise payloads, and rely on cached metadata to reduce repeated database calls during peak usage.
- **Continuous testing** – the repository ships with a PHPUnit suite, WordPress bootstrap shim, and GitHub Actions workflow that exercise booking, logging, and helper components.

## Installation

### Prerequisites
- WordPress >= 6.5
- WooCommerce >= 8.0
- PHP >= 8.1
- Composer (for dependency management)

### Steps

1. **Clone or download the plugin:**
   ```bash
   git clone https://github.com/franpass87/FP-Esperienze.git
   ```

2. **Install composer dependencies:**
   ```bash
   cd FP-Esperienze
   composer install --no-dev --optimize-autoloader
   ```

3. **Prepare translations (required only when building a release ZIP):**
   - The repository stores the editable `.po` sources; compiled `.mo` files are generated at runtime inside `wp-content/languages/plugins/`
   - When producing a distributable package, run `wp i18n make-mo languages` (or the `msgfmt` commands below) and include the generated `.mo` files in the release artifact
     ```bash
     msgfmt languages/fp-esperienze-en_US.po -o languages/fp-esperienze-en_US.mo
     msgfmt languages/fp-esperienze-it_IT.po -o languages/fp-esperienze-it_IT.mo
     ```
   - Repeat the command for any additional locales you maintain so the compiled `.mo` files ship with the packaged plugin

4. **Upload to WordPress:**
   - Copy the plugin folder to `/wp-content/plugins/`
   - Or upload as a ZIP file through WordPress admin

5. **Activate the plugin:**
   - Go to WordPress Admin > Plugins
   - Find "FP Esperienze" and click "Activate"

**Note:** If you see an error about missing dependencies, make sure you have run `composer install --no-dev` in the plugin directory.

## Release Artifacts

- The distributable package for this release is generated at `dist/fp-esperienze-1.1.0.zip`. Upload this ZIP to WordPress or distribute it through your deployment pipeline.
- A SHA-256 checksum is stored alongside the archive (`dist/fp-esperienze-1.1.0.zip.sha256`). Verify the checksum after transfer to ensure the package has not been tampered with.
- Regenerate the archive by running `./tools/build-plugin-zip.sh` or the manual steps outlined in [UPGRADE.md](UPGRADE.md) if you modify the codebase.

### Trusted Proxy Configuration

If your WordPress installation sits behind a reverse proxy or load balancer, add its IP address to the list of trusted proxies so the rate limiter can read the original client address from trusted headers. When a request originates from a proxy contained in this list FP Esperienze, by default, inspects the following headers in order: `CF-Connecting-IP`, `Client-IP`, `X-Cluster-Client-IP`, `X-Real-IP`, `X-Forwarded-For`, `X-Forwarded`, `Forwarded-For`, and `Forwarded`.

```php
// In wp-config.php or a custom plugin.
add_filter( 'fp_trusted_proxies', function() {
    return [ '203.0.113.1', '198.51.100.0' ];
} );
```

You can customize the header preference order with the `fp_trusted_proxy_headers` filter or adjust the final resolved address with `fp_resolved_client_ip` should your infrastructure use bespoke headers.

Only requests that originate from a trusted proxy will have their forwarding headers processed. Otherwise the plugin falls back to `$_SERVER['REMOTE_ADDR']` (or `wp_get_ip_address()` when available).

### Production Readiness CLI Check

When WP-CLI is available you can confirm that all required components are correctly configured before deploying to production with:

```bash
wp fp-esperienze production-check
```

Add `--format=json` if you want to consume the report in automation pipelines.

### WordPress Site Health Coverage

Visit **Tools → Site Health** to review automated diagnostics tailored for FP Esperienze. The plugin now contributes dedicated tests that surface:

- Dependency and filesystem prerequisites (WooCommerce, Composer autoloaders, writable directories).
- Onboarding checklist completion so operators can quickly identify pending setup tasks.
- Operational alert posture covering digest channels, cron scheduling, and the last dispatch status.
- Production readiness signals reused from the REST API and WP-CLI validator, including warnings for missing tables or REST endpoints.

These checks complement the CLI and REST tooling by giving administrators an at-a-glance dashboard directly inside WordPress.

### Guided Onboarding Toolkit

- **Interactive checklist** – the setup wizard now surfaces the required configuration tasks (meeting points, experiences, schedules, payment gateways, and emails) with live completion status.
- **Demo data seeding** – click *Create demo data* in the wizard to generate a sample meeting point, experience, and recurring schedules to explore the booking flow immediately.
- **Guided tour overlay** – launch the built-in tour to learn where to publish experiences, manage schedules, and preview the storefront without leaving the wizard.
- **Persistent reminders** – lightweight notices highlight outstanding onboarding tasks on FP Esperienze admin pages until everything is configured, with a “remind me later” snooze for busy operators.
- **Operational alerts** – configure automated booking digests (email and Slack) with thresholds from the Operational Alerts admin page.
- **Integration toolkit** – share copy-ready widget snippets (embed, auto-height script, CSS tokens) with partners directly from the new Integration Toolkit admin page.

## Uninstall

Removing the plugin via WordPress will drop all custom database tables beginning with `fp_` and delete any options or transients with the `fp_esperienze_` prefix. To preserve this data during uninstall, define the following constant in your `wp-config.php` before removing the plugin:

```php
define('FP_ESPERIENZE_PRESERVE_DATA', true);
```

## Features

- **Experience Product Type**: Custom WooCommerce product type for bookable experiences
- **Schedule Builder**: Simplified schedule management with inheritance from product defaults
- **Booking Management**: Complete booking system with slots, schedules, and capacity management
- **Meeting Points**: GPS-enabled meeting points for experiences
- **Extras**: Additional services and add-ons
- **Dynamic Pricing**: Advanced pricing rules with seasonal, weekend/weekday, early-bird, and group discounts
- **Gift Vouchers**: Complete gift system with PDF generation, QR codes, and email delivery
- **Voucher Redemption**: Cart/checkout voucher redemption with HMAC validation (Phase 2)
- **Vouchers**: PDF vouchers with QR codes (legacy system)
- **REST API**: Real-time availability checking
- **Frontend Templates**: GetYourGuide-style single experience pages
- **Admin Dashboard**: Comprehensive management interface
- **Advanced Reports**: KPI analytics, charts, UTM tracking, and webhook integrations
- **SEO Enhancement**: Comprehensive structured data, social meta tags, and search optimization

## SEO & Structured Data

The plugin includes comprehensive SEO enhancements to improve search engine visibility and social media sharing of experience pages.

### Enhanced Schema.org Markup

Automatic structured data generation with intelligent schema type selection:

#### Event Schema (Guided Experiences with Schedules)
For experiences with defined schedules and specific times:

```json
{
  "@context": "https://schema.org",
  "@type": "Event",
  "name": "Cooking Class in Tuscany",
  "description": "Learn traditional Tuscan cooking techniques...",
  "startDate": "2024-12-30T09:00:00+01:00",
  "duration": "PT120M",
  "eventStatus": "https://schema.org/EventScheduled",
  "eventAttendanceMode": "https://schema.org/OfflineEventAttendanceMode",
  "location": {
    "@type": "Place",
    "name": "Tuscan Cooking School",
    "address": {
      "@type": "PostalAddress",
      "streetAddress": "Via Roma 123, Florence, Italy"
    },
    "geo": {
      "@type": "GeoCoordinates",
      "latitude": 43.7696,
      "longitude": 11.2558
    }
  },
  "offers": [
    {
      "@type": "Offer",
      "name": "Adult Price",
      "price": 45.00,
      "priceCurrency": "EUR",
      "availability": "https://schema.org/InStock",
      "validFrom": "2024-12-01T00:00:00Z"
    },
    {
      "@type": "Offer", 
      "name": "Child Price",
      "price": 25.00,
      "priceCurrency": "EUR",
      "availability": "https://schema.org/InStock",
      "validFrom": "2024-12-01T00:00:00Z"
    }
  ]
}
```

#### Trip Schema (Tour Experiences)
For tour-style experiences identified by categories or tags:

```json
{
  "@context": "https://schema.org",
  "@type": "Trip",
  "name": "Florence Historical Walking Tour",
  "description": "Discover the rich history of Florence...",
  "duration": "PT180M",
  "location": {
    "@type": "Place",
    "name": "Piazza della Signoria",
    "address": {
      "@type": "PostalAddress",
      "streetAddress": "Piazza della Signoria, Florence, Italy"
    }
  },
  "offers": [
    {
      "@type": "Offer",
      "price": 35.00,
      "priceCurrency": "EUR",
      "availability": "https://schema.org/InStock"
    }
  ]
}
```

#### Product Schema (Standard Experiences)
For general experiences without specific schedules:

```json
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": "Wine Tasting Experience",
  "description": "Sample the finest Tuscan wines...",
  "brand": {
    "@type": "Brand",
    "name": "FP Esperienze"
  },
  "offers": [
    {
      "@type": "Offer",
      "price": 60.00,
      "priceCurrency": "EUR",
      "availability": "https://schema.org/InStock"
    }
  ]
}
```

### FAQ Schema Markup

When FAQ data is available, automatic FAQPage schema generation:

```json
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "What should I bring?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Comfortable walking shoes and a camera."
      }
    },
    {
      "@type": "Question", 
      "name": "Is this suitable for children?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Yes, children over 3 years old are welcome."
      }
    }
  ]
}
```

### Breadcrumb Navigation Schema

Structured navigation hierarchy for better search understanding:

```json
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {
      "@type": "ListItem",
      "position": 1,
      "name": "Home",
      "item": "https://example.com"
    },
    {
      "@type": "ListItem",
      "position": 2,
      "name": "Experiences",
      "item": "https://example.com/shop"
    },
    {
      "@type": "ListItem",
      "position": 3,
      "name": "Cooking Class in Tuscany",
      "item": "https://example.com/experience/cooking-class"
    }
  ]
}
```

### Social Media Meta Tags

#### Open Graph Tags
Enhanced Facebook and LinkedIn sharing:

```html
<meta property="og:type" content="product" />
<meta property="og:title" content="Cooking Class in Tuscany" />
<meta property="og:description" content="Learn traditional Tuscan cooking..." />
<meta property="og:image" content="https://example.com/image.jpg" />
<meta property="og:url" content="https://example.com/experience/cooking-class" />
<meta property="product:price:amount" content="45.00" />
<meta property="product:price:currency" content="EUR" />
```

#### Twitter Cards
Optimized Twitter sharing:

```html
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="Cooking Class in Tuscany" />
<meta name="twitter:description" content="Learn traditional Tuscan cooking..." />
<meta name="twitter:image" content="https://example.com/image.jpg" />
```

### SEO Settings Configuration

Navigate to **FP Esperienze > SEO** to configure:

- **Enhanced Schema.org**: Enable Event/Trip/Product schema selection
- **FAQ Schema**: Auto-generate FAQPage markup when FAQ data exists
- **Breadcrumb Schema**: Add navigation breadcrumbs
- **Open Graph Tags**: Enable Facebook/LinkedIn meta tags
- **Twitter Cards**: Enable Twitter sharing optimization

### Schema Type Selection Logic

The plugin intelligently selects the appropriate schema type:

1. **Trip Schema**: Applied when experience has "tour" or "trip" in categories/tags
2. **Event Schema**: Applied when experience has defined schedules and duration
3. **Product Schema**: Default fallback for general experiences

### Validation and Quality

- **No Fake Ratings**: Schema markup excludes artificial review data
- **Rich Results Compatible**: Tested with Google Rich Results Test
- **Schema.org Compliant**: Validates against official schema.org standards
- **Performance Optimized**: Minimal impact on page load times

### Meeting Point Integration

Location data automatically sourced from assigned meeting points:
- Geographic coordinates for precise location
- Full address information
- Integration with Google Places reviews (when available)

### Dynamic Pricing Integration

Offer data reflects current pricing:
- Adult and child pricing tiers
- Currency information
- Availability status
- Valid date ranges

## Advanced Reports & Analytics

The plugin includes a comprehensive reporting system for analyzing booking performance and revenue metrics.

### KPI Dashboard

Real-time key performance indicators including:
- **Total Revenue**: Aggregated booking revenue with currency formatting
- **Seats Sold**: Total participants (adults + children) across all bookings
- **Total Bookings**: Count of confirmed and completed bookings
- **Average Booking Value**: Revenue per booking calculation
- **Load Factors**: Capacity utilization by experience, meeting point, and time slot

### Interactive Charts

Visual analytics with Chart.js integration:
- **Revenue & Seats Trends**: Dual-axis charts showing revenue and seat sales over time
- **Period Views**: Switch between daily, weekly, and monthly groupings
- **Top 10 Experiences**: Revenue-ranked experience performance list
- **UTM Source Conversions**: Traffic source analysis with conversion metrics

### Data Export

Flexible export options for detailed analysis:
- **CSV Export**: Spreadsheet-compatible format with summary data, top experiences, UTM conversions, and load factors
- **JSON Export**: Machine-readable format for integration with external analytics tools
- **Filtered Exports**: Apply date range, product, meeting point, and language filters
- **Timestamped Files**: Automatic filename generation with export timestamp

### UTM Campaign Tracking

Conversion analysis from order metadata:
- **Source Attribution**: Track bookings by UTM source (Google, Facebook, Direct, etc.)
- **Revenue by Source**: Calculate total revenue and average order value per traffic source
- **Conversion Rates**: Analyze which marketing channels drive the most valuable bookings

### Webhook Integrations

Real-time event notifications for external systems:
- **Booking Events**: Configurable webhooks for new bookings, cancellations, and reschedules
- **Retry Logic**: Exponential backoff retry policy (up to 5 attempts)
- **Security**: HMAC-SHA256 payload signing with configurable secrets
- **GDPR Compliance**: Optional PII exclusion for privacy protection
- **Event Deduplication**: Unique event IDs prevent duplicate processing

### Access & Security

- **Capability-Based Access**: Requires `manage_fp_esperienze` capability
- **AJAX Security**: Nonce validation for all dynamic requests  
- **PII Protection**: Configurable personal information hiding in reports and webhooks
- **Data Sanitization**: All inputs sanitized and validated

### Technical Features

- **Real-time Updates**: AJAX-powered dashboard updates without page refresh
- **Responsive Design**: Mobile-friendly interface with CSS Grid layout
- **Performance Optimized**: Efficient database queries with proper indexing
- **CDN Integration**: Chart.js loaded from CDN for better performance
- **Cache Friendly**: Designed to work with WordPress object caching

## Dynamic Pricing

The plugin includes a sophisticated dynamic pricing system that allows you to create flexible pricing rules based on various conditions. Rules are applied in a specific priority order to ensure predictable pricing.

### Pricing Rule Types

#### Seasonal Pricing
Set different prices for specific date ranges:
- **Date Range**: Define start and end dates for seasonal periods
- **Adjustments**: Apply percentage or fixed amount changes to adult and child prices
- **Example**: Summer season (June-August) with +20% adult pricing and +15% child pricing

#### Weekend/Weekday Pricing
Apply different rates for weekends vs weekdays:
- **Weekend Override**: Higher prices for Saturday and Sunday bookings
- **Weekday Override**: Lower prices for Monday through Friday bookings
- **Example**: +10% for weekend bookings

#### Early Bird Discounts
Reward customers who book in advance:
- **Days Before**: Minimum number of days between purchase and experience date
- **Discount**: Percentage or fixed amount reduction
- **Example**: -15% discount for bookings made 7+ days in advance

#### Group Discounts
Tiered discounts based on party size:
- **Minimum Participants**: Threshold for discount eligibility
- **Separate Config**: Different discounts for adults and children
- **Multiple Tiers**: Create various group size thresholds (e.g., 4+, 8+, 12+)
- **Example**: -5% for 4+ people, -10% for 8+ people

### Rule Priority and Composition

Dynamic pricing rules are applied in a specific order to ensure consistent pricing:

1. **Base Price** - Starting adult/child prices
2. **Seasonal** - Date range adjustments
3. **Weekend/Weekday** - Day of week overrides
4. **Early Bird** - Advance booking discounts
5. **Group** - Party size discounts

Each rule modifies the price from the previous step, creating compound effects.

### Admin Interface

#### Product Configuration
- **Dynamic Pricing Tab**: Dedicated tab in product edit screen
- **Rule Management**: Add, edit, and remove pricing rules with drag-and-drop priority
- **Active/Inactive**: Toggle rules on/off without deletion
- **Priority Settings**: Control the order of rule application

#### Preview Calculator
- **Test Scenarios**: Input test dates, quantities, and purchase dates
- **Real-time Calculation**: See exactly how rules will be applied
- **Price Breakdown**: Detailed breakdown showing each rule's impact
- **Visual Feedback**: Clear display of base vs final prices

### Cart Integration

#### Price Breakdown Display
In the shopping cart, customers see:
- Applied pricing rules and their effects
- Clear breakdown of price adjustments
- Rule names and percentage/amount changes
- Total price changes from base to final

#### Compatibility
- **Tax Integration**: Works seamlessly with WooCommerce tax settings
- **Coupon Compatibility**: Stacks properly with WooCommerce coupons
- **Voucher System**: No double discounts with gift vouchers
- **Multi-currency**: Supports WooCommerce currency plugins

### Example Pricing Scenario

**Base Price**: Adult €100, Child €80  
**Booking**: Summer weekend (July 14), party of 6, booked 10 days in advance

1. **Base**: Adult €100.00, Child €80.00
2. **Seasonal (+20%)**: Adult €120.00, Child €92.00  
3. **Weekend (+10%)**: Adult €132.00, Child €101.20
4. **Early Bird (-15%)**: Adult €112.20, Child €86.02
5. **Group 4+ (-5%)**: Adult €106.59, Child €81.72

**Total for 4 adults + 2 children**: €590.00

### Technical Implementation

The dynamic pricing system integrates with existing WooCommerce pricing through filter hooks:
- `fp_esperienze_adult_price` - Modifies adult base price
- `fp_esperienze_child_price` - Modifies child base price
- Tax calculations use WooCommerce's native `wc_get_price_to_display()` method
- Database table `fp_dynamic_pricing_rules` stores all pricing rules

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

## Integrations Settings

Configure third-party integrations through **FP Esperienze → Settings → Integrations**.

### Google Analytics 4

- **Measurement ID**: Your GA4 Measurement ID (format: G-XXXXXXXXXX)
- **Enhanced eCommerce**: Enable purchase event tracking and conversion data for better analytics (recommended)

### Google Ads

- **Conversion ID**: Your Google Ads Conversion ID (format: AW-XXXXXXXXXX)
- Configure conversion actions in your Google Ads dashboard to track bookings and purchases

### Meta Pixel (Facebook)

- **Pixel ID**: Your Meta (Facebook) Pixel ID number
- **Conversions API**: Enable server-side tracking and event deduplication (placeholder for future implementation)

### Brevo (Email Marketing)

- **API Key v3**: Your Brevo API key for email list management
- **List ID (Italian)**: Brevo list ID for Italian customers
- **List ID (English)**: Brevo list ID for English customers

#### Subscription Flow by Language

When an order reaches "processing" or "completed" status with experience products:

1. **Customer Data Extraction**: Name, email, and language are retrieved from the order
2. **Contact Creation/Update**: Customer is created or updated in Brevo via `POST /v3/contacts`
3. **List Subscription**: Customer is automatically added to the appropriate language list:
   - Italian customers → List ID (Italian)
   - English customers → List ID (English)
4. **Language Detection**: Order language is determined from experience items or site locale
5. **Error Handling**: Failed API calls are logged without exposing sensitive customer data

The integration activates only when API key and at least one list ID are configured.

### Google Places API

- **API Key**: Google Places API key for retrieving reviews and location data
- **Display Reviews**: Show Google reviews on Meeting Point pages
- **Reviews Limit**: Maximum number of reviews to display (1-10)
- **Cache TTL**: How long to cache Google Places data (5-1440 minutes)

#### Recensioni Meeting Point (Places API New)

When enabled, the plugin displays Google reviews for meeting points on single experience pages:

**Features:**
- **Rating Display**: Shows average rating with star visualization and total review count
- **Individual Reviews**: Displays reviewer name (partial for privacy), rating, relative time, and text excerpt
- **Performance**: Server-side caching with configurable TTL (5-1440 minutes)
- **Policy Compliance**: Uses only Places API (New), no permanent storage beyond cache
- **Error Handling**: Graceful fallbacks on API errors or quota limits
- **Privacy**: Author names are partially masked (e.g., "John D." instead of full name)

**Requirements:**
- Valid Google Places API key with Places API (New) enabled
- Meeting points must have `place_id` configured
- Reviews integration enabled in settings

**Data Source:** Uses Place Details (New) API with fields:
- `rating` - Average rating
- `userRatingCount` - Total number of reviews  
- `reviews.authorAttribution.displayName` - Reviewer name
- `reviews.rating` - Individual review rating
- `reviews.text.text` - Review text content
- `reviews.relativePublishTimeDescription` - Relative time

**Display Behavior:**
- Shows in "Reviews" section after Meeting Point information
- Includes Google disclosure: "Reviews via Google" with Maps profile link
- Responsive design adapts to mobile devices
- Fallback to rating-only display if individual reviews unavailable

**Cache Management:**
- Cache key format: `fp_gplaces_[md5_hash_of_place_id]`
- Automatic cache invalidation after TTL expiry
- Respects API rate limits through caching

### Google Business Profile API (Optional)

- **Coming Soon**: OAuth integration for Google Business Profile management
- **Requirements**: Must be verified owner of the Google Business Profile

All integration settings are stored securely and can be configured independently. Empty fields will disable the respective integrations.

## Frontend Tracking

The plugin includes comprehensive tracking for Google Analytics 4 and Meta Pixel with automatic event detection and proper eCommerce data structure.

### GA4 Enhanced eCommerce Events

When GA4 integration is enabled and configured, the following events are automatically tracked:

#### view_item
Triggered when a user loads a single experience page:
```javascript
{
  "event": "view_item",
  "ecommerce": {
    "currency": "EUR",
    "value": 50.00,
    "items": [{
      "item_id": "123",
      "item_name": "Cooking Class in Tuscany",
      "item_category": "Experience",
      "price": 50.00,
      "quantity": 1,
      "slot_start": null,
      "meeting_point_id": null,
      "lang": ["English", "Italian"]
    }]
  }
}
```

#### select_item
Triggered when a user selects a time slot:
```javascript
{
  "event": "select_item",
  "item_list_name": "Available Time Slots",
  "items": [{
    "item_id": "123",
    "item_name": "Cooking Class in Tuscany",
    "item_category": "Experience",
    "price": 50.00,
    "quantity": 1,
    "slot_start": "10:00",
    "meeting_point_id": "1",
    "lang": "English"
  }]
}
```

#### add_to_cart
Triggered when experience is added to cart:
```javascript
{
  "event": "add_to_cart",
  "ecommerce": {
    "currency": "EUR",
    "value": 50.00,
    "items": [{
      "item_id": "123",
      "item_name": "Cooking Class in Tuscany",
      "item_category": "Experience",
      "price": 50.00,
      "quantity": 1,
      "slot_start": "2024-12-15 10:00",
      "meeting_point_id": "1",
      "lang": "English"
    }]
  }
}
```

#### begin_checkout, add_payment_info, purchase
Standard WooCommerce funnel events with experience-specific data including slot times, meeting points, and language selections.

### Meta Pixel Events

When Meta Pixel integration is enabled, the following events are tracked:

- **AddToCart**: When experience is added to cart
- **InitiateCheckout**: When checkout process begins
- **Purchase**: When order is completed

All Meta Pixel events include a UUID v4 `event_id` for deduplication with Conversions API (when implemented).

### Event Parameters

**Custom Parameters for Experiences:**
- `slot_start`: Selected time slot (null on initial view_item)
- `meeting_point_id`: Selected meeting point ID (null on initial view_item)
- `lang`: Selected language or available languages array

**Standard eCommerce Parameters:**
- `item_id`: Product ID
- `item_name`: Product name
- `item_category`: Always "Experience"
- `price`: Base adult price
- `quantity`: Always 1 for experiences
- `currency`: Site currency
- `value`: Total value

### Implementation Details

- **Conditional Loading**: Scripts only load on experience pages, cart, and checkout
- **No GTM Dependency**: Uses native `dataLayer.push()` and `fbq()` calls
- **Settings Integration**: Respects enable/disable toggles in admin settings
- **Performance Optimized**: Tracking code only loads when integrations are configured and enabled

## Consent Mode v2 Integration

FP Esperienze supports Consent Mode v2 for privacy compliance. When enabled, GA4 and Meta Pixel events only fire if marketing consent is granted.

### Configuration

Navigate to **FP Esperienze → Settings → Integrations → Consent Mode v2**:

1. **Enable Consent Mode**: Toggle to activate consent checking
2. **Consent Cookie Name**: Name of cookie storing consent status (default: `marketing_consent`)
3. **Consent JavaScript Function**: Optional function path returning boolean consent status

### CMP Integration Methods

#### Method 1: Cookie-Based (Recommended)

Configure your CMP to set a cookie with the marketing consent status:

```javascript
// Example: Set cookie when user grants marketing consent
document.cookie = "marketing_consent=true; path=/; max-age=31536000";

// Example: Set cookie when user denies marketing consent  
document.cookie = "marketing_consent=false; path=/; max-age=31536000";
```

**Supported cookie values for granted consent:**
- `"true"` (recommended)
- `"1"`
- `"granted"`

#### Method 2: JavaScript Function

Configure your CMP to expose a function that returns the consent status:

```javascript
// Example: CMP exposes consent status via function
window.myCMP = {
    getMarketingConsent: function() {
        return userHasGrantedMarketingConsent; // boolean
    }
};
```

Then configure the function path in settings: `window.myCMP.getMarketingConsent`

### Public API

FP Esperienze exposes a global function for checking consent status:

```javascript
// Check current consent status
const hasConsent = window.fpExpGetConsent(); // returns boolean

// Example: Update UI based on consent
if (window.fpExpGetConsent()) {
    console.log('Marketing consent granted');
} else {
    console.log('Marketing consent denied');
}
```

### Behavior

- **Consent Mode Disabled**: All tracking fires normally (default behavior)
- **Consent Mode Enabled + Consent Granted**: All tracking fires normally
- **Consent Mode Enabled + Consent Denied**: GA4 and Meta Pixel events are blocked
- **No Consent Data**: Defaults to denied (safe default)

Events are logged to browser console for debugging with the status "blocked due to consent" when consent is denied.

### Testing

To test consent mode functionality:

1. Enable Consent Mode in settings
2. Set cookie name (e.g., `test_consent`)
3. In browser console:
   ```javascript
   // Test denied consent
   document.cookie = "test_consent=false; path=/";
   // Trigger an event (add to cart, etc.) - should be blocked
   
   // Test granted consent
   document.cookie = "test_consent=true; path=/";
   // Trigger an event - should fire normally
   ```

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

Monitor onboarding progress and operational alerts (requires an authenticated user with FP Esperienze management rights):
```
GET /wp-json/fp-exp/v1/system-status
```

The payload includes checklist completion metrics, production readiness flags, and the scheduling state of the daily digest so external monitoring tools can flag regressions instantly.

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

- `_fp_exp_langs`: Comma-separated languages
- `_fp_exp_adult_price`: Adult pricing
- `_fp_exp_child_price`: Child pricing
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

### Experience Schedules

Experience schedules define the recurring weekly time slots when an experience is available for booking. The plugin offers two ways to manage schedules: the intuitive **Schedule Builder** (recommended) and **Advanced Mode** for detailed control.

#### Schedule Builder (Recommended)

The Schedule Builder provides a simplified interface for creating recurring time slots with inheritance from product defaults.

**Key Features:**
- **Weekly Programming**: Create time slots by selecting days + start time
- **Inheritance System**: Override fields inherit from product defaults when empty
- **Override Toggle**: Show/hide advanced settings as needed
- **Multiple Days**: Apply same time slot to multiple days at once
- **Validation**: Built-in time format and required field validation

**Product Defaults (Inherited Values):**
- **Default Duration**: Base duration in minutes (e.g., 90)
- **Default Max Capacity**: Base capacity for all schedules (e.g., 12)
- **Default Language**: Base language code (e.g., 'it', 'en')
- **Default Meeting Point**: Default meeting location
- **Default Child Price**: Base child pricing

**Creating Time Slots:**

1. **Basic Setup**: Set start time and select days
2. **Inheritance**: Leave override fields empty to inherit product defaults
3. **Overrides**: Use "Show advanced overrides" to specify different values
4. **Multiple Slots**: Add multiple time slots for different times/conditions

**Example Usage:**
```
Time Slot 1: 09:00, Mon/Wed/Fri (inherits all defaults)
Time Slot 2: 14:30, Tue/Thu/Sat/Sun (120min duration, €35 adult price, other inherited)
```

This creates 7 individual schedule records with optimized inheritance.

#### Advanced Mode (Legacy)

Toggle "Show Advanced Mode" to access individual schedule row editing for fine-grained control.

#### Schedule Fields

**Day of Week** *(Required)*
- Which day of the week this schedule applies to (Sunday = 0, Monday = 1, etc.)
- Each experience can have multiple schedules for different days

**Start Time** *(Required)*
- When the experience starts in 24-hour format (HH:MM)
- Example: `09:00` for 9:00 AM, `14:30` for 2:30 PM
- Used to calculate booking availability slots

**Duration (minutes)** *(Override Optional)*
- How long the experience lasts in minutes
- **Inheritance**: Uses product default duration if not specified
- Must be greater than 0 when specified
- Example: `120` for a 2-hour experience

**Max Capacity** *(Override Optional)*
- Maximum number of participants for this specific schedule
- **Inheritance**: Uses product default capacity if not specified
- Must be at least 1 when specified
- Can be different for each schedule (e.g., different group sizes for morning vs evening)

**Language** *(Override Optional)*
- Language code for this schedule (e.g., `en`, `it`, `es`)
- **Inheritance**: Uses product default language if not specified
- Useful for multilingual experiences with different schedules per language

**Meeting Point** *(Override Optional)*
- The meeting point location for this schedule
- **Inheritance**: Uses product default meeting point if not specified
- Can be different for each schedule if experiences meet at different locations

**Adult Price** *(Override Optional)*
- Price per adult participant for this specific schedule
- **Inheritance**: Uses standard WooCommerce product price if not specified
- Overrides the default product price when specified

**Child Price** *(Override Optional)*
- Price per child participant for this specific schedule
- **Inheritance**: Uses product default child price if not specified
- Leave empty if no child pricing or to use default pricing

#### Schedule Validation

The system validates schedule data when saving:
- **Time Format**: Must be in HH:MM format (e.g., `09:00`, `14:30`)
- **Duration**: Must be greater than 0 minutes (when specified)
- **Capacity**: Must be at least 1 participant (when specified)
- **Invalid Schedules**: Automatically discarded with admin notice
- **Validation Feedback**: Clear error messages for any validation issues

#### How Schedules Work

1. **Recurring Availability**: Schedules create recurring weekly time slots
2. **Inheritance Logic**: Empty override fields inherit from product defaults via ScheduleHelper
3. **Effective Values**: System calculates final values (override → default → fallback)
4. **Booking Slots**: System generates bookable slots using effective values
5. **Capacity Management**: Each schedule has independent capacity tracking
6. **Override Support**: Date-specific overrides can modify or disable schedule slots
7. **Multi-language**: Different schedules can serve different languages

#### Database Migration (Optional)

The plugin includes optional database migration to optimize storage:

**Feature Flag**: Set `FP_ESPERIENZE_ENABLE_SCHEDULE_NULL_MIGRATION = true` to enable

**Benefits**:
- Removes redundant data by setting inherited values to NULL
- Cleaner database with explicit inheritance
- Backward compatible with existing schedules

**Migration Process**:
- Alters schedule table columns to allow NULL values
- Sets values to NULL where they match product defaults
- Maintains all functionality while reducing data redundancy

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

## Translation

FP Esperienze is fully internationalized and ready for translation.

### Text Domain

All translatable strings use the text domain: `fp-esperienze`

### Translation Files

- **POT Template**: `languages/fp-esperienze.pot` - Contains all translatable strings
- **Language Files**: Place translation files in `languages/` directory
  - Example: `languages/fp-esperienze-it_IT.po` for Italian
  - Example: `languages/fp-esperienze-es_ES.po` for Spanish

### Translation Process

1. **For translators**: Use the `fp-esperienze.pot` file as the template
2. **For developers**: Run the following command to regenerate the POT file after adding new strings:

```bash
# From plugin root directory
find . -name "*.php" -not -path "./vendor/*" | xargs xgettext \
  --from-code=UTF-8 \
  --keyword=__ \
  --keyword=_e \
  --keyword=_x:1,2c \
  --keyword=_ex:1,2c \
  --keyword=_n:1,2 \
  --keyword=_nx:1,2,4c \
  --keyword=_n_noop:1,2 \
  --keyword=_nx_noop:1,2,3c \
  --keyword=esc_attr__ \
  --keyword=esc_attr_e \
  --keyword=esc_attr_x:1,2c \
  --keyword=esc_html__ \
  --keyword=esc_html_e \
  --keyword=esc_html_x:1,2c \
  --package-name="FP Esperienze" \
  --package-version="1.0.0" \
  --default-domain=fp-esperienze \
  --output=languages/fp-esperienze.pot \
  --add-comments=translators \
  --force-po
```

3. **Before packaging**: Compile each locale's `.po` file into a `.mo` so `load_plugin_textdomain()` can load it from your distributable ZIP:

```bash
msgfmt languages/fp-esperienze-en_US.po -o languages/fp-esperienze-en_US.mo
msgfmt languages/fp-esperienze-it_IT.po -o languages/fp-esperienze-it_IT.mo
# Repeat for any other locales in languages/
```

During development the plugin automatically compiles any missing or out-of-date
`.mo` files into `wp-content/languages/plugins/`, so translations work even when
the repository only contains the `.po` sources. When preparing a release ZIP,
run the commands above (or `wp i18n make-mo languages`) and include the
generated `.mo` files in the packaged plugin.

### Automatic Translation Endpoint

Only configure trusted translation endpoints for the automatic translator. The
plugin validates the endpoint with `wp_http_validate_url()` and falls back to
the default LibreTranslate URL if the provided value is invalid. Requests use a
10 second timeout and limit the response size to 1 MB to avoid hanging on slow
or malicious hosts.

To confirm fast failure behaviour, test with an unreachable endpoint (for
example `https://127.0.0.1:9/translate`) and ensure the request fails
promptly.

### WP-CLI Translation Command

Queue all plugin content for translation via WP-CLI:

```bash
wp fp-esperienze translate
```

### WP-CLI Onboarding Commands

Automate onboarding and daily operations directly from the terminal:

```bash
# Display checklist progress in table or JSON form
wp fp-esperienze onboarding checklist --format=table

# Seed demo data (meeting point, experience product, schedules)
wp fp-esperienze onboarding seed-data

# Generate booking, participant, and revenue totals for the past week
wp fp-esperienze onboarding daily-report --days=7

# Dispatch the configured operational digest immediately
wp fp-esperienze onboarding send-digest --channel=all
```

The commands return non-zero exit codes on errors, making them suitable for CI pipelines or scheduled cron jobs.

### WP-CLI Operations Command

Run quick health checks before launching campaigns or pushing to production:

```bash
# Display operational readiness as a table
wp fp-esperienze operations health-check

# Produce JSON for monitoring dashboards
wp fp-esperienze operations health-check --format=json
```

The command validates WooCommerce availability, digest configuration, cron scheduling, and pending onboarding tasks in one pass.

### WP-CLI QA Automation Command

Convert the smoke tests from `MANUAL_TESTS.md` into automated gates that can
run in CI or pre-release hooks:

```bash
# Run the full automated checklist and exit non-zero on failures
wp fp-esperienze qa run

# Focus on specific checks (comma separated IDs)
wp fp-esperienze qa run --only=experience_product_type,rest_routes

# Produce machine readable output for dashboards
wp fp-esperienze qa run --format=json

# List the available checks and their descriptions
wp fp-esperienze qa list
```

The QA command inspects onboarding progress, demo content seeding, REST routes,
and operational digest scheduling, returning `WARNING` when action is
recommended and `FAIL` when release blockers are detected.

### JavaScript Localization

JavaScript strings are localized through the WordPress `wp_localize_script()` function. The following objects are available:

- `fp_booking_widget_i18n` - Booking widget error messages and status texts
- `fp_esperienze_params` - General plugin parameters and AJAX endpoints

### String Guidelines

- Use descriptive context with `_x()` function when needed
- Add translator comments with `/* translators: comment */` for complex strings
- Keep strings concise but descriptive
- Use proper capitalization and punctuation

### WPML/Polylang Compatibility

FP Esperienze is fully compatible with WPML and Polylang for multilingual websites.

#### Setup Instructions

**For WPML:**

1. Install and configure WPML
2. Go to **FP Esperienze > Settings > General** and set your archive page
3. Translate the archive page in WPML
4. Meeting point names and addresses will be automatically registered with WPML String Translation
5. Configure string translations in **WPML > String Translation**

**For Polylang:**

1. Install and configure Polylang  
2. Go to **FP Esperienze > Settings > General** and set your archive page
3. Translate the archive page in Polylang
4. Create experience products in each language
5. The plugin will automatically filter experiences by current language

#### Features

- **Language Filtering**: Archive shortcode/block automatically filters experiences by current language
- **Meeting Point Translation**: Names and addresses can be translated (WPML) or managed per language (Polylang)
- **URL Translation**: Archive page URLs respect translated page slugs
- **Shared Place IDs**: Google Places integration works across all language versions
- **Filter Labels**: All admin and frontend strings are translatable

#### Archive Shortcode

The `[fp_exp_archive]` shortcode automatically respects the current language:

```php
// Displays experiences in current language only
[fp_exp_archive posts_per_page="12" filters="mp,lang,duration"]
```

#### Gutenberg Block

The Experience Archive block also includes automatic language filtering when WPML/Polylang is detected.

## Accessibility

FP Esperienze follows WCAG 2.1 AA accessibility standards.

### Features

- **Color Contrast**: All text meets AA contrast standards (4.5:1 ratio minimum)
- **Keyboard Navigation**: Full keyboard support for all interactive elements
- **Screen Reader Support**: Proper ARIA labels, roles, and live regions
- **Focus Management**: Clear focus states and logical tab order
- **Semantic HTML**: Proper heading hierarchy and landmark regions

### Accessibility Checklist

- [x] Color contrast meets AA standards (4.5:1 ratio)
- [x] All interactive elements are keyboard accessible
- [x] Proper ARIA attributes on dynamic content
- [x] Clear focus states on all focusable elements
- [x] Logical heading hierarchy (H1 → H2 → H3)
- [x] Alternative text for images and icons
- [x] Form labels and descriptions
- [x] Error messages are announced to screen readers

### Testing Tools

- Use browser accessibility tools for automated testing
- Test with keyboard navigation (Tab, Enter, Arrow keys)
- Verify with screen readers (NVDA, JAWS, VoiceOver)
- Check color contrast with online tools

## Author

**Francesco Passeri**

## License

GPL v2 or later
## Build & Release (CI)
- Gli artefatti di build (zip) non sono versionati nel repository.
- La CI su Pull Request crea lo zip del plugin e lo pubblica come artifact scaricabile.
- Il push di un tag `v*` genera una GitHub Release con allegati zip e checksum SHA-256.
- Build locale: esegui `bash scripts/build-plugin-zip.sh` e recupera l'output in `dist/`.
