# Manual Tests - Pricing and Taxes Feature

## Overview
Test comprehensive pricing and tax functionality including WooCommerce tax classes, multi-currency support, and compatibility with coupons and vouchers.

## Prerequisites
- WordPress with WooCommerce installed
- FP Esperienze plugin activated
- Tax settings configured in WooCommerce
- Multiple tax classes created (e.g., "Standard", "Reduced Rate", "Zero Rate")
- Multi-currency plugin installed (e.g., WooCommerce Multi-Currency)

## Test Cases

### Tax Class Configuration Tests

#### Test 1: Adult/Child Tax Class Configuration
**Steps:**
1. Go to WordPress Admin → Products
2. Create/Edit an Experience product
3. Navigate to Experience tab
4. Configure Adult Price: €50.00
5. Set Adult Tax Class: "Standard" (20% tax)
6. Configure Child Price: €30.00
7. Set Child Tax Class: "Reduced Rate" (10% tax)
8. Save product

**Expected Results:**
- [ ] Adult Tax Class dropdown shows all available tax classes
- [ ] Child Tax Class dropdown shows all available tax classes
- [ ] Settings are saved correctly
- [ ] Tax classes are properly applied to pricing

#### Test 2: Extra Items Tax Class Respect
**Steps:**
1. Go to WordPress Admin → FP Esperienze → Extras
2. Create extra "Lunch" with price €15.00 and tax class "Standard"
3. Create extra "Museum Entry" with price €10.00 and tax class "Reduced Rate"
4. Associate both extras with an experience product
5. Add experience to cart with both extras

**Expected Results:**
- [ ] Extra prices respect their configured tax classes
- [ ] Tax calculation is applied correctly per extra
- [ ] Cart shows proper tax amounts for each component

### Multi-Currency Support Tests

#### Test 3: Alternative Currency Display
**Steps:**
1. Install and configure WooCommerce Multi-Currency
2. Add USD as alternative currency with conversion rate
3. Switch site currency to USD
4. View experience product pricing
5. Add to cart and proceed to checkout

**Expected Results:**
- [ ] Adult/child prices display in USD with proper conversion
- [ ] Extra prices display in USD with proper conversion
- [ ] Tax calculations work correctly in alternative currency
- [ ] Checkout total matches expected USD amounts

#### Test 4: Currency Switching with Tax
**Steps:**
1. Configure experience with €50 adult price (20% tax)
2. Set EUR to USD conversion rate (1.1)
3. Switch currency from EUR to USD
4. Add experience to cart
5. Check tax calculations

**Expected Results:**
- [ ] Base price converts: €50 → $55
- [ ] Tax calculation uses correct currency
- [ ] Final price including tax is properly converted
- [ ] No rounding errors in calculations

### Tax Included/Excluded Scenarios

#### Test 5: Tax Included in Prices
**Steps:**
1. Go to WooCommerce → Settings → Tax
2. Set "Prices entered with tax" to "Yes"
3. Configure experience: Adult €60 (includes 20% tax), Child €36 (includes 10% tax)
4. Add 2 adults + 1 child to cart
5. Check cart calculations

**Expected Results:**
- [ ] Adult net price: €50 (€60 ÷ 1.20)
- [ ] Child net price: €32.73 (€36 ÷ 1.10)
- [ ] Tax amounts calculated correctly
- [ ] Total matches: €156 with proper tax breakdown

#### Test 6: Tax Excluded from Prices
**Steps:**
1. Set "Prices entered with tax" to "No"
2. Configure experience: Adult €50 (+ 20% tax), Child €30 (+ 10% tax)
3. Add 2 adults + 1 child to cart
4. Check cart calculations

**Expected Results:**
- [ ] Adult price with tax: €60 (€50 × 1.20)
- [ ] Child price with tax: €33 (€30 × 1.10)
- [ ] Tax amounts shown separately
- [ ] Total: €153 with €23 tax

### Coupon Integration Tests

#### Test 7: WooCommerce Coupon with Extras
**Steps:**
1. Create WooCommerce coupon: "SAVE20" - 20% discount
2. Add experience (€100) + extras (€30) to cart
3. Apply coupon "SAVE20"
4. Check discount application order

**Expected Results:**
- [ ] Coupon applies to base experience price
- [ ] Extras prices remain unchanged OR follow coupon settings
- [ ] Tax calculations updated after discount
- [ ] Final total calculation is correct

#### Test 8: Coupon + Voucher Compatibility
**Steps:**
1. Create experience (€100) + extras (€30)
2. Apply WooCommerce coupon "SAVE10" (10% off)
3. Try to apply FP Esperienze voucher (full discount)
4. Check behavior

**Expected Results:**
- [ ] Either vouchers are mutually exclusive (preferred)
- [ ] Or proper calculation order is maintained
- [ ] Clear messaging about voucher compatibility
- [ ] No double discounting occurs

### Voucher Value Calculation Tests

#### Test 9: Full Voucher with Tax
**Steps:**
1. Create experience: Adult €50 + €10 tax
2. Apply full discount voucher
3. Add extras: €20 + €4 tax
4. Check final pricing

**Expected Results:**
- [ ] Experience price becomes €0 (voucher covers full amount including tax)
- [ ] Extras still charged: €24 (including tax)
- [ ] Tax calculations remain accurate for extras
- [ ] Order total: €24

#### Test 10: Value Voucher Tax Handling
**Steps:**
1. Create experience: €100 + €20 tax = €120 total
2. Apply €50 value voucher
3. Check discount application

**Expected Results:**
- [ ] Voucher discount: €50 off the taxable amount
- [ ] Remaining experience price: €50 + €10 tax = €60
- [ ] OR voucher applies to total including tax (depends on implementation)
- [ ] Tax calculations remain consistent

### Edge Cases and Error Handling

#### Test 11: Zero Tax Rate Class
**Steps:**
1. Create tax class "Zero Rate" with 0% tax
2. Set child price to use "Zero Rate" tax class
3. Set adult price to use "Standard" tax class (20%)
4. Add 1 adult + 1 child to cart

**Expected Results:**
- [ ] Adult price includes 20% tax
- [ ] Child price has no tax added
- [ ] Cart totals are calculated correctly
- [ ] Tax breakdown shows proper amounts

#### Test 12: Missing Tax Class Configuration
**Steps:**
1. Create experience without setting tax classes (default to empty)
2. Add to cart
3. Check tax calculation

**Expected Results:**
- [ ] Uses WooCommerce default tax class behavior
- [ ] No errors or warnings displayed
- [ ] Pricing calculations complete successfully
- [ ] Fallback to standard tax class works

#### Test 13: High Precision Currency
**Steps:**
1. Configure currency with 3+ decimal places (e.g., JPY conversion)
2. Set experience prices
3. Add to cart with tax calculations

**Expected Results:**
- [ ] Rounding handled correctly
- [ ] No precision loss in calculations
- [ ] Final totals match expected values
- [ ] No JavaScript/PHP calculation discrepancies

### Performance and Integration Tests

#### Test 14: Cart Recalculation Performance
**Steps:**
1. Add multiple experience items to cart (5+)
2. Each with different tax classes and extras
3. Apply/remove coupons
4. Monitor page load times

**Expected Results:**
- [ ] Cart calculations complete within reasonable time (<2 seconds)
- [ ] No significant performance degradation
- [ ] Multiple tax calculations handled efficiently
- [ ] Browser remains responsive

#### Test 15: Checkout Integration
**Steps:**
1. Complete full checkout process with:
   - Experience with tax
   - Extras with different tax classes
   - Applied coupon
   - Alternative currency
2. Complete payment

**Expected Results:**
- [ ] Order totals match cart calculations
- [ ] Tax amounts properly recorded in order
- [ ] Payment gateway receives correct amount
- [ ] Order confirmation shows proper tax breakdown

## Test Results Summary

| Test | Status | Notes |
|------|---------|-------|
| Test 1 - Tax Class Config | ⏳ Pending | |
| Test 2 - Extra Tax Classes | ⏳ Pending | |
| Test 3 - Alternative Currency | ⏳ Pending | |
| Test 4 - Currency + Tax | ⏳ Pending | |
| Test 5 - Tax Included | ⏳ Pending | |
| Test 6 - Tax Excluded | ⏳ Pending | |
| Test 7 - Coupon + Extras | ⏳ Pending | |
| Test 8 - Coupon + Voucher | ⏳ Pending | |
| Test 9 - Full Voucher Tax | ⏳ Pending | |
| Test 10 - Value Voucher Tax | ⏳ Pending | |
| Test 11 - Zero Tax Rate | ⏳ Pending | |
| Test 12 - Missing Tax Config | ⏳ Pending | |
| Test 13 - High Precision | ⏳ Pending | |
| Test 14 - Performance | ⏳ Pending | |
| Test 15 - Checkout Integration | ⏳ Pending | |

## Environment Notes

### WooCommerce Tax Settings
- Tax calculations enabled: Yes
- Prices entered with tax: [Test both scenarios]
- Calculate tax based on: Customer location
- Default customer address: Shop base address

### Test Data Setup
```php
// Example tax classes to create
Standard Rate: 20%
Reduced Rate: 10%
Zero Rate: 0%
Luxury Rate: 25%
```

### Multi-Currency Setup
```php
// Example currency configuration
Base Currency: EUR
Alternative Currencies: USD (1.1), GBP (0.85), JPY (130)
```

## Known Issues and Limitations
- [ ] Document any discovered issues
- [ ] Note performance considerations
- [ ] Record compatibility issues with other plugins