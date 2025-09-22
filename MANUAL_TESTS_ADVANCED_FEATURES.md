# Manual Tests - Complete Advanced Features Implementation

## Overview
This document provides comprehensive testing procedures for the newly implemented advanced features in FP Esperienze plugin.

## New Features Implemented

### 1. Advanced Analytics Dashboard
- Conversion funnel analysis
- Revenue attribution by marketing channel
- ROI calculator for campaigns
- Data export capabilities (CSV/Excel)
- Customer journey mapping

### 2. Enhanced Email Marketing System
- Brevo integration with fallback to WordPress email
- Automated workflow sequences
- Abandoned cart recovery
- Review request automation
- Upselling campaigns
- Real-time email system testing

### 3. Mobile API Support
- Enhanced REST API endpoints for mobile apps
- QR code generation and scanning for check-ins
- Push notification support
- Offline mode synchronization
- Staff management features
- Mobile authentication system

### 4. AI-Powered Features
- Dynamic pricing based on demand/seasonality
- Recommendation engine (collaborative/content-based)
- Sentiment analysis for reviews
- Predictive analytics and forecasting
- Trending experience identification

## Testing Procedures

### 1. Advanced Analytics Dashboard Testing

#### Test 1.1: Conversion Funnel Analysis
**Prerequisites:**
- Admin user with 'view_reports' capability
- Sample booking data in database

**Steps:**
1. Navigate to WP Admin → FP Esperienze → Reports
2. Click on "Advanced Analytics" tab
3. Select date range (last 30 days)
4. Click "Load Conversion Funnel"

**Expected Results:**
- Funnel displays 5 steps: Website Visits → Product Views → Add to Cart → Checkout Start → Purchase Complete
- Each step shows count, conversion rate, and drop-off numbers
- Overall conversion rate calculated correctly
- Total revenue and AOV displayed

**AJAX Test:**
```javascript
// Test in browser console
jQuery.post(ajaxurl, {
    action: 'fp_get_conversion_funnel',
    nonce: fp_admin_nonce,
    date_from: '2024-01-01',
    date_to: '2024-12-31'
}).done(function(response) {
    console.log('Funnel Data:', response.data);
});
```

#### Test 1.2: Attribution Report
**Steps:**
1. In Advanced Analytics, click "Attribution Report" tab
2. Select date range
3. Click "Generate Report"

**Expected Results:**
- Shows revenue breakdown by UTM source/medium
- Displays order count and percentage for each channel
- Shows average order value per channel
- Top campaigns listed for each channel

#### Test 1.3: ROI Analysis
**Steps:**
1. Click "ROI Analysis" tab
2. Select date range
3. Click "Calculate ROI"

**Expected Results:**
- Shows ROI percentage for each marketing channel
- Displays ROAS (Return on Ad Spend)
- Shows cost per acquisition
- Total profit/loss summary

#### Test 1.4: Data Export
**Steps:**
1. Select any report (Funnel, Attribution, or ROI)
2. Click "Export Data" button
3. Choose format (CSV or Excel)
4. Click "Download"

**Expected Results:**
- File downloads automatically
- Contains all report data in structured format
- Filename includes report type and date range

### 2. Enhanced Email Marketing Testing

#### Test 2.1: Brevo Configuration
**Prerequisites:**
- Valid Brevo API key
- Brevo contact lists created

**Steps:**
1. Navigate to WP Admin → FP Esperienze → Settings → Integrations
2. Enter Brevo API key
3. Select "Brevo" as email marketing system
4. Enter contact list IDs for Italian and English
5. Click "Test Connection"

**Expected Results:**
- Green success message: "Brevo connection successful"
- Lists validation passes
- Settings saved successfully

#### Test 2.2: Email System Testing
**Steps:**
1. In Integrations settings, scroll to "Email Marketing Test"
2. Enter test email address
3. Select system type (Brevo/WordPress/Auto)
4. Click "Send Test Email"

**Expected Results:**
- Success message displayed
- Test email received at specified address
- Email contains booking confirmation template
- Shows which system was used (Brevo or WordPress)

**AJAX Test:**
```javascript
jQuery.post(ajaxurl, {
    action: 'fp_test_email_system',
    nonce: fp_admin_nonce,
    test_email: 'test@example.com',
    system_type: 'auto'
}).done(function(response) {
    console.log('Email test result:', response.data);
});
```

#### Test 2.3: Automated Workflow Testing
**Prerequisites:**
- Create test booking that reaches "completed" status

**Steps:**
1. Create a booking and mark it as completed
2. Check logs for scheduled email events
3. Verify emails are sent at appropriate times

**Expected Results:**
- Review request email scheduled for 2 days after completion
- Upselling email scheduled for 7 days after completion
- Pre-experience email scheduled for 1 day before booking date

#### Test 2.4: Abandoned Cart Recovery
**Prerequisites:**
- Experience product in cart
- User session active

**Steps:**
1. Add experience to cart
2. Leave cart inactive for 1+ hours
3. Trigger abandoned cart process: `do_action('fp_check_abandoned_carts')`

**Expected Results:**
- Abandoned cart email sent to customer
- Cart recovery link generated
- Cart data removed from tracking after email sent

### 3. Mobile API Testing

#### Test 3.1: Mobile Authentication
**API Endpoint:** `POST /wp-json/fp-esperienze/v2/mobile/auth/login`

**Request:**
```json
{
    "username": "testuser",
    "password": "testpass",
    "device_info": "iPhone 15 Pro"
}
```

**Expected Response:**
```json
{
    "success": true,
    "data": {
        "token": "base64_encoded_jwt_token",
        "user": {
            "id": 123,
            "username": "testuser",
            "email": "test@example.com",
            "display_name": "Test User",
            "roles": ["customer"],
            "capabilities": {
                "can_manage_bookings": false,
                "can_check_in_customers": false,
                "can_view_reports": false
            }
        }
    }
}
```

#### Test 3.2: Mobile Experience Listing
**API Endpoint:** `GET /wp-json/fp-esperienze/v2/mobile/experiences`

**Parameters:**
- `page=1`
- `per_page=20`
- `category=tours`

**Expected Response:**
```json
{
    "success": true,
    "data": {
        "experiences": [
            {
                "id": 123,
                "name": "City Walking Tour",
                "description": "Explore the historic center...",
                "price": 25.00,
                "currency": "EUR",
                "images": [...],
                "rating": 4.5,
                "review_count": 42,
                "duration": "2 hours",
                "location": "Historic Center",
                "available_dates": [...]
            }
        ],
        "pagination": {
            "page": 1,
            "per_page": 20,
            "total": 156,
            "has_more": true
        }
    }
}
```

**Availability verification steps:**

1. Configure the planner with known schedules for a test product (recurring or fixed dates).
2. Call `GET /wp-json/fp-esperienze/v2/mobile/experiences` and ensure every element in `available_dates`:
   - Lists only the planner dates (no empty days).
   - Reports slot `start_time`/`end_time` pairs that match the planner entries.
   - Shows `remaining_capacity` equal to the sum of the slot `available` counts.
   - Exposes `prices.adult_from` and `prices.child_from` matching the configured schedule prices.
3. Call `GET /wp-json/fp-esperienze/v2/mobile/experiences/{id}` for the same product and confirm the `available_dates` payload mirrors the list endpoint (dates, capacities and prices all aligned with the planner).
4. Create at least two meeting points, assign only one of them to the product schedules, and call `GET /wp-json/fp-esperienze/v2/mobile/experiences/{id}`. Verify that the `meeting_points` array only contains the associated entry. Remove the association (or assign a different meeting point) and repeat the call to confirm the endpoint falls back to the global meeting point catalog when no schedule links exist.

#### Test 3.3: QR Code Generation and Scanning
**Generate QR Code:**
`GET /wp-json/fp-esperienze/v2/mobile/qr/generate/123`

**Headers:**
```
Authorization: Bearer {mobile_token}
```

**Expected Response:**
```json
{
    "success": true,
    "data": {
        "qr_data": "base64_encoded_booking_data",
        "qr_image": "data:image/svg+xml;base64,...",
        "booking_id": 123,
        "expires_at": "2024-01-02 15:30:00"
    }
}
```

**Scan QR Code (Staff Only):**
`POST /wp-json/fp-esperienze/v2/mobile/qr/scan`

**Request:**
```json
{
    "qr_data": "base64_encoded_booking_data"
}
```

**Expected Response:**
```json
{
    "success": true,
    "data": {
        "booking": {
            "id": 123,
            "booking_number": "EXP-2024-001",
            "customer_name": "John Doe",
            "experience_name": "City Tour",
            "booking_date": "2024-01-02",
            "participants": 2,
            "status": "confirmed",
            "checked_in": false
        },
        "can_check_in": true
    }
}
```

#### Test 3.4: Push Notification Registration
**API Endpoint:** `POST /wp-json/fp-esperienze/v2/mobile/notifications/register`

**Request:**
```json
{
    "token": "firebase_fcm_token_here",
    "platform": "ios"
}
```

**Expected Response:**
```json
{
    "success": true,
    "data": {
        "message": "Push token registered successfully"
    }
}
```

### 4. AI Features Testing

#### Test 4.1: Dynamic Pricing
**Prerequisites:**
- AI settings enabled with dynamic pricing
- Experience products with base prices

**Steps:**
1. Navigate to WP Admin → FP Esperienze → Settings → AI Features
2. Enable "Dynamic Pricing"
3. Set sensitivity to 0.2 (20%)
4. Save settings
5. Create some test bookings for an experience
6. Check product price changes

**Expected Results:**
- Products with high demand show price increases
- Seasonal adjustments applied based on month
- Price changes logged in product meta
- Original price preserved in `_original_price` meta

**Test Dynamic Pricing Calculation:**
```php
// Test in WordPress admin or via WP-CLI
$product = wc_get_product(123);
$ai_manager = new \FP\Esperienze\AI\AIFeaturesManager();
$dynamic_price = $ai_manager->calculateDynamicPrice($product, 100.00);
echo "Original: €100.00, Dynamic: €{$dynamic_price}";
```

#### Test 4.2: Recommendation Engine
**API Endpoint:** `POST /wp-json/wp/v2/fp_get_recommendations`

**Request:**
```json
{
    "action": "fp_get_recommendations",
    "nonce": "wp_nonce_here",
    "product_id": 123,
    "type": "hybrid"
}
```

**Expected Response:**
```json
{
    "success": true,
    "data": {
        "recommendations": [
            {
                "id": 456,
                "name": "Wine Tasting Tour",
                "price": "65.00",
                "image": "https://example.com/wine-tour.jpg",
                "score": 0.85,
                "type": "hybrid",
                "reason": "Recommended for you"
            }
        ]
    }
}
```

#### Test 4.3: Sentiment Analysis
**Prerequisites:**
- Enable sentiment analysis in AI settings

**Steps:**
1. Navigate to an experience product page
2. Add a review with positive text: "This was an amazing experience! Absolutely fantastic guide and beautiful locations."
3. Submit review
4. Check comment meta for sentiment data

**Expected Results:**
- Comment meta includes:
  - `_sentiment_score`: ~0.8-1.0 (positive)
  - `_sentiment_label`: "positive"
  - `_sentiment_confidence`: 0.2-1.0
- Product meta updated with aggregated sentiment

**Test Sentiment Analysis:**
```php
// Test sentiment analysis
$ai_manager = new \FP\Esperienze\AI\AIFeaturesManager();
$result = $ai_manager->analyzeCommentSentiment("This was absolutely amazing and wonderful!");
var_dump($result);
// Expected: ['score' => 1.0, 'label' => 'positive', 'confidence' => 0.2]
```

#### Test 4.4: Predictive Analytics
**Steps:**
1. Trigger daily AI analysis: `do_action('fp_daily_ai_analysis')`
2. Check stored options for forecasts:
   - `get_option('fp_esperienze_demand_forecast')`
   - `get_option('fp_esperienze_churn_analysis')`
   - `get_option('fp_esperienze_revenue_forecast')`
   - `get_option('fp_esperienze_trending_experiences')`

**Expected Results:**
- Demand forecast shows next 7 days predictions
- Churn analysis identifies at-risk customers
- Revenue forecast predicts next 3 months
- Trending experiences list high-growth products

### 5. Integration Testing

#### Test 5.1: Email Marketing + AI Integration
**Steps:**
1. Configure Brevo integration
2. Enable AI recommendations
3. Create completed booking
4. Wait for upselling email (or trigger manually)
5. Check email content for AI-recommended experiences

**Expected Results:**
- Upselling email contains personalized recommendations
- Recommendations based on customer purchase history
- Email uses appropriate template (Brevo or WordPress)

#### Test 5.2: Mobile API + Analytics Integration
**Steps:**
1. Use mobile API to create bookings
2. Check if bookings appear in analytics dashboard
3. Verify attribution data is captured
4. Test conversion funnel with mobile-generated data

**Expected Results:**
- Mobile bookings tracked in analytics
- Attribution data properly stored
- Conversion funnel includes mobile conversions
- Customer journey shows mobile touchpoints

#### Test 5.3: QR Code + Staff Management
**Steps:**
1. Generate QR code for booking via mobile API
2. Use staff mobile app to scan QR code
3. Process check-in via mobile API
4. Verify check-in recorded in admin dashboard

**Expected Results:**
- QR code contains valid booking data
- Staff can scan and view booking details
- Check-in updates booking status
- Timestamp and staff member recorded
- Customer receives check-in notification

### 6. Performance Testing

#### Test 6.1: Analytics Performance
**Steps:**
1. Generate large dataset (1000+ bookings)
2. Run conversion funnel analysis
3. Check response times and memory usage
4. Test concurrent requests

**Expected Results:**
- Response time < 3 seconds for 30-day analysis
- Memory usage reasonable (< 128MB peak)
- No PHP timeouts or errors
- Concurrent requests handled properly

#### Test 6.2: AI Features Performance
**Steps:**
1. Test dynamic pricing on 100+ products
2. Run sentiment analysis on 500+ reviews
3. Generate recommendations for multiple users
4. Monitor server resources

**Expected Results:**
- Dynamic pricing calculations complete quickly
- Sentiment analysis processes efficiently
- Recommendations generated without timeout
- No significant server load impact

#### Test 6.3: Mobile API Performance
**Steps:**
1. Test mobile API endpoints with large datasets
2. Generate multiple QR codes simultaneously
3. Process bulk offline actions
4. Monitor response times

**Expected Results:**
- API responses < 2 seconds for most endpoints
- QR code generation efficient
- Bulk operations handled properly
- Mobile app receives timely responses

## Error Handling Testing

### Test Error Scenarios
1. **Invalid API credentials** - Test with wrong Brevo API key
2. **Network failures** - Simulate connection issues
3. **Invalid data** - Send malformed requests to APIs
4. **Permission errors** - Test with insufficient user capabilities
5. **Rate limiting** - Send rapid API requests
6. **Large datasets** - Test with extreme data volumes

### Expected Error Responses
- Proper HTTP status codes (400, 401, 403, 500)
- Descriptive error messages
- Graceful fallbacks (WordPress email when Brevo fails)
- No PHP fatal errors or warnings
- User-friendly error messages in admin interface

## Security Testing

### Test Security Measures
1. **Nonce validation** - Test all AJAX endpoints
2. **Capability checks** - Test with different user roles
3. **Data sanitization** - Send malicious input
4. **SQL injection** - Test database queries
5. **XSS prevention** - Test output escaping

### Expected Security Results
- All AJAX requests require valid nonces
- Capability checks prevent unauthorized access
- User input properly sanitized
- No SQL injection vulnerabilities
- Output properly escaped

## Documentation and Support

### Admin Interface Help
- Hover tooltips for complex features
- Help text for AI settings
- Clear error messages
- Progress indicators for long operations

### Developer Documentation
- Filter hooks for customization
- Action hooks for extensions
- Code examples for API integration
- Performance optimization guidelines

## Conclusion

These advanced features transform FP Esperienze into an enterprise-level experience management platform with:

- **Professional Analytics** comparable to dedicated attribution platforms
- **Intelligent Automation** reducing manual marketing tasks
- **Mobile-First Architecture** supporting modern app experiences  
- **AI-Powered Optimization** improving revenue and customer satisfaction

All features are designed with WordPress best practices, security standards, and performance optimization in mind.