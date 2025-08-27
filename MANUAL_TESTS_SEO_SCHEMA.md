# Manual Test Guide - SEO Schema Enhancement

## Pre-requisites
- WordPress site with WooCommerce active
- FP Esperienze plugin installed and activated
- At least one Experience product created with complete data
- Browser developer tools access for testing schema validation
- Schema validation tools (e.g., Google Rich Results Test, Schema.org validator)

## Test Data Setup

Before testing, ensure you have an Experience product with:
- Product title and description
- Product excerpt (for meta description)
- Featured image and gallery images
- Duration: 120 minutes
- Adult price: €45.00
- Child price: €25.00
- Meeting point assigned
- Product categories or tags (test with "tour" tag for Trip schema)
- FAQ data in `_fp_exp_faq` meta field:
  ```json
  [
    {"question": "What should I bring?", "answer": "Comfortable walking shoes and a camera."},
    {"question": "Is this suitable for children?", "answer": "Yes, children over 3 years old are welcome."},
    {"question": "What happens in case of bad weather?", "answer": "Tours run rain or shine, but may be cancelled in extreme conditions."}
  ]
  ```

## Test Cases

### Test 1: SEO Settings Admin Page

#### 1.1 Access SEO Settings
1. Login to WordPress admin
2. Navigate to **FP Esperienze > SEO**
3. Verify SEO settings page loads correctly

**Expected Result**: SEO settings page displays with toggles for:
- Enhanced Schema.org
- FAQ Schema
- Breadcrumb Schema  
- Open Graph Tags
- Twitter Cards

#### 1.2 Toggle Settings
1. Test each checkbox toggle
2. Save settings
3. Verify settings are preserved after page reload

**Expected Result**: All toggles work correctly and settings persist.

### Test 2: Enhanced Schema.org Markup

#### 2.1 Product Schema (Default)
1. Create experience without "tour" category/tag
2. Ensure no schedules are defined
3. View single experience page
4. View page source and check for JSON-LD

**Expected Result**: 
- Product schema with enhanced offers
- Location data from meeting point
- Proper pricing structure

#### 2.2 Event Schema (Guided + Scheduled)
1. Create experience with schedules defined in database
2. Add duration metadata
3. View single experience page  
4. Check JSON-LD schema

**Expected Result**:
- Event schema type
- startDate field with calculated next occurrence
- duration in ISO 8601 format (PT120M)
- eventStatus and eventAttendanceMode

#### 2.3 Trip Schema (Tour Type)
1. Add "tour" or "trip" to product categories/tags
2. View single experience page
3. Check JSON-LD schema

**Expected Result**:
- Trip schema type
- Duration if available
- Location and offers data

### Test 3: FAQ Schema Validation

#### 3.1 FAQ Schema Present
1. Ensure experience has FAQ data
2. Enable FAQ schema in settings
3. View single experience page
4. Check for FAQPage JSON-LD

**Expected Result**:
- Separate FAQPage schema block
- mainEntity array with Question objects
- Proper acceptedAnswer structure

#### 3.2 FAQ Schema Disabled
1. Disable FAQ schema in settings
2. View single experience page
3. Verify no FAQPage schema is output

**Expected Result**: No FAQPage schema in page source.

### Test 4: Breadcrumb Schema

#### 4.1 Breadcrumb Structure
1. Enable breadcrumb schema
2. View single experience page
3. Check BreadcrumbList JSON-LD

**Expected Result**:
- BreadcrumbList schema
- Home → Shop → Experience structure
- Proper position numbering

### Test 5: Open Graph Meta Tags

#### 5.1 OG Tags Present
1. Enable Open Graph tags
2. View single experience page
3. Check `<head>` section for og: meta tags

**Expected Result**:
- og:type = "product"
- og:title with experience name
- og:description with excerpt/description
- og:image with featured image
- og:url with canonical URL
- product:price:amount and product:price:currency

#### 5.2 OG Tags Disabled
1. Disable Open Graph tags
2. View page source
3. Verify no og: meta tags

**Expected Result**: No Open Graph meta tags in head.

### Test 6: Twitter Cards

#### 6.1 Twitter Card Tags
1. Enable Twitter Cards
2. View single experience page
3. Check for twitter: meta tags

**Expected Result**:
- twitter:card = "summary_large_image"
- twitter:title
- twitter:description  
- twitter:image and twitter:image:alt

#### 6.2 Twitter Cards Disabled
1. Disable Twitter Cards
2. Check page source

**Expected Result**: No Twitter Card meta tags.

### Test 7: Schema Validation

#### 7.1 Google Rich Results Test
1. Copy experience page URL
2. Open [Google Rich Results Test](https://search.google.com/test/rich-results)
3. Test the URL
4. Check for validation errors

**Expected Result**: 
- Valid schema detected
- No critical errors
- Appropriate rich result preview

#### 7.2 Schema.org Validator
1. View page source and copy JSON-LD
2. Open [Schema.org Validator](https://validator.schema.org/)
3. Paste and validate each schema block

**Expected Result**: All schemas validate without errors.

### Test 8: Multiple Schema Blocks

#### 8.1 All Features Enabled
1. Enable all SEO features
2. Create experience with FAQ data and meeting point
3. View page source
4. Count JSON-LD script blocks

**Expected Result**: 
- Enhanced schema (Product/Event/Trip)
- FAQPage schema  
- BreadcrumbList schema
- All in separate script blocks

### Test 9: SEO Performance

#### 9.1 Page Load Impact
1. Use browser dev tools Network tab
2. Load experience page with all SEO features
3. Check for performance impact

**Expected Result**: 
- No significant page load delay
- All meta tags in head
- Schemas in footer

### Test 10: Mobile Experience

#### 10.1 Mobile Meta Tags
1. View experience page on mobile device
2. Check social sharing functionality
3. Verify responsive meta viewport

**Expected Result**: 
- Proper mobile display
- Social sharing works with OG/Twitter tags

## Validation Checklist

After completing tests, verify:

- [ ] SEO settings page accessible and functional
- [ ] Enhanced schema selects correct type (Product/Event/Trip)
- [ ] Location data includes meeting point information  
- [ ] Pricing data is accurate and complete
- [ ] FAQ schema only appears when FAQ data exists
- [ ] Breadcrumb schema has correct navigation structure
- [ ] Open Graph tags enhance social sharing
- [ ] Twitter Cards display properly
- [ ] All schemas validate without errors
- [ ] No fake or artificial ratings in markup
- [ ] Performance impact is minimal
- [ ] Settings toggles work correctly

## Expected Schema Examples

### Event Schema Example
```json
{
  "@context": "https://schema.org",
  "@type": "Event",
  "name": "Cooking Class in Tuscany",
  "description": "Learn traditional Tuscan cooking...",
  "startDate": "2024-12-30T09:00:00+01:00",
  "duration": "PT120M",
  "location": {
    "@type": "Place",
    "name": "Tuscan Cooking School",
    "address": {
      "@type": "PostalAddress", 
      "streetAddress": "Via Roma 123, Florence"
    }
  },
  "offers": [
    {
      "@type": "Offer",
      "name": "Adult Price",
      "price": 45.00,
      "priceCurrency": "EUR"
    }
  ]
}
```

### FAQPage Schema Example  
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
    }
  ]
}
```