# FP Esperienze - Pricing and Taxes Implementation

## Overview
This document outlines the pricing and tax system implementation for FP Esperienze, including WooCommerce integration, multi-currency support, and coupon compatibility.

## Features Implemented

### 1. Tax Class Support
- **Adult Tax Class**: Configurable tax class for adult pricing
- **Child Tax Class**: Configurable tax class for child pricing  
- **Extra Tax Classes**: Tax classes for extra items (already existing)
- **WooCommerce Integration**: Uses `wc_get_price_to_display()` for proper tax calculation

### 2. Multi-Currency Support
- **Automatic Conversion**: Uses WooCommerce's price display functions
- **Currency Awareness**: Respects active currency from multi-currency plugins
- **Tax Calculation**: Proper tax handling in alternative currencies

### 3. Coupon Compatibility
- **Conflict Prevention**: Full vouchers cannot stack with WooCommerce coupons
- **Value Vouchers**: Can be used alongside WooCommerce coupons
- **Automatic Cleanup**: Removes conflicting vouchers when coupons are applied

## Technical Implementation

### Database Changes
New meta fields added for experience products:

### Class Updates

#### WC_Product_Experience
New methods added:
- `get_adult_tax_class()`: Returns adult tax class
- `get_child_tax_class()`: Returns child tax class
- `get_adult_price_with_tax()`: Tax-aware adult price (deprecated - not used)
- `get_child_price_with_tax()`: Tax-aware child price (deprecated - not used)

#### Cart_Hooks
Updated price calculation methods:
- `getExperiencePriceWithTax()`: Tax-aware price calculation for adult/child
- `getExtraPriceWithTax()`: Tax-aware price calculation for extras
- `checkCouponVoucherConflict()`: Handles coupon-voucher conflicts

### Hook Integration

#### New Hooks Added
- `woocommerce_applied_coupon`: Checks for voucher conflicts when coupons are applied

#### Action Hooks Fired
- `fp_esperienze_voucher_applied`: When voucher is successfully applied
- `fp_esperienze_voucher_removed`: When voucher is removed
- `fp_esperienze_voucher_redeemed`: When voucher is redeemed on order completion
- `fp_esperienze_voucher_rollback`: When voucher redemption is rolled back

#### Filter Hooks Available

##### Price Filtering
```php
// Filter adult base price before tax calculation
apply_filters('fp_esperienze_adult_price', $price, $product_id);

// Filter child base price before tax calculation  
apply_filters('fp_esperienze_child_price', $price, $product_id);

// Filter extra base price before tax calculation
apply_filters('fp_esperienze_extra_price', $price, $extra_id);
```

##### Tax-Aware Price Filtering
```php
// Filter final adult price including tax
apply_filters('fp_esperienze_adult_price_with_tax', $total, $base_price, $tax_class, $quantity, $product_id);

// Filter final child price including tax
apply_filters('fp_esperienze_child_price_with_tax', $total, $base_price, $tax_class, $quantity, $product_id);

// Filter final extra price including tax
apply_filters('fp_esperienze_extra_price_with_tax', $total, $base_price, $tax_class, $quantity, $participants, $extra);
```

##### Cart and Voucher Filtering
```php
// Filter voucher validation results
apply_filters('fp_esperienze_voucher_validation', $validation, $code, $product_id);

// Filter voucher discount amount
apply_filters('fp_esperienze_voucher_discount_amount', $discount, $voucher, $cart_item);

// Filter final cart item price
apply_filters('fp_esperienze_cart_item_price', $total, $cart_item, $base_total, $extras_total);
```

## Pricing Calculation Flow

### 1. Base Price Calculation
```
For each participant type (adult/child):
1. Get base price from product meta
2. Apply price filter (fp_esperienze_{type}_price)
3. Create temporary WC_Product_Simple with tax class
4. Use wc_get_price_to_display() for tax-aware price
5. Multiply by quantity
6. Apply final price filter (fp_esperienze_{type}_price_with_tax)
```

### 2. Extra Price Calculation
```
For each extra:
1. Get base price from extra object
2. Apply price filter (fp_esperienze_extra_price)
3. Create temporary WC_Product_Simple with extra's tax class
4. Use wc_get_price_to_display() for tax-aware price
5. Apply billing type logic (per_person vs per_booking)
6. Apply final price filter (fp_esperienze_extra_price_with_tax)
```

### 3. Voucher Application
```
If voucher applied:
1. Check for WooCommerce coupon conflicts
2. For full vouchers: discount = base_total
3. For value vouchers: discount = min(base_total, voucher_amount)
4. Apply voucher discount filter
5. Subtract discount from base_total
```

### 4. Final Price Setting
```
1. total_price = base_total + extras_total
2. Apply final cart item price filter
3. Set price on WC_Product using set_price()
```

## Coupon Integration Rules

### Full Vouchers
- **Cannot stack** with WooCommerce coupons
- **Automatic removal** when WooCommerce coupon is applied
- **Prevention** when trying to apply with existing coupons
- **User notification** when conflicts are resolved

### Value Vouchers
- **Can stack** with WooCommerce coupons
- **Apply to base price only** (not extras)
- **Respect coupon discount order** (WooCommerce first, then voucher)

## Multi-Currency Considerations

### Automatic Handling
- Uses `wc_get_price_to_display()` which handles currency conversion
- Works with popular multi-currency plugins
- Maintains tax calculation accuracy across currencies

### Plugin Compatibility
Tested with:
- WooCommerce Multi-Currency
- WPML Currency (recommended for testing)
- Currency Switcher for WooCommerce

## Configuration Guide

### Setting Up Tax Classes
1. Go to WooCommerce → Settings → Tax
2. Create tax classes as needed (e.g., "Standard", "Reduced", "Zero")
3. Configure tax rates for each class
4. Set experience products to use appropriate tax classes

### Multi-Currency Setup
1. Install supported multi-currency plugin
2. Configure currencies and conversion rates
3. Test experience pricing in different currencies
4. Verify tax calculations remain accurate

## Testing Checklist

### Basic Functionality
- [ ] Tax classes save correctly for adult/child prices
- [ ] Price calculations include proper tax amounts
- [ ] Multi-currency conversion works correctly
- [ ] Extra items respect their tax classes

### Coupon Integration
- [ ] Full vouchers block WooCommerce coupons
- [ ] WooCommerce coupons remove conflicting full vouchers
- [ ] Value vouchers work alongside WooCommerce coupons
- [ ] User receives appropriate notifications

### Edge Cases
- [ ] Zero tax rate handling
- [ ] Missing tax class configuration
- [ ] High precision currency calculations
- [ ] Performance with multiple items

## Troubleshooting

### Common Issues

#### Prices Not Including Tax
- Check WooCommerce tax settings
- Verify tax classes are configured
- Ensure customer location is set for tax calculation

#### Currency Conversion Issues
- Verify multi-currency plugin is active
- Check conversion rates are set
- Test with different currencies

#### Coupon Conflicts
- Check for custom modifications to WooCommerce coupons
- Verify hook priorities are correct
- Test voucher application order

### Debug Information
Enable WooCommerce logging and check for:
- Tax calculation errors
- Currency conversion issues
- Hook execution order problems

## Future Enhancements

### Planned Features
- Advanced tax exemption handling
- Bulk tax class updates
- Enhanced reporting for tax calculations
- Additional currency conversion options

### Filter Extensions
Consider adding filters for:
- Tax calculation override
- Currency-specific pricing rules
- Advanced coupon compatibility rules