# Manual Test: Events API Prefetch Optimization

## Goal
Verify that the Events REST endpoint returns identical event data while performing fewer database queries by prefetching products and orders.

## Prerequisites
- WordPress site with WooCommerce installed
- FP Esperienze plugin activated
- Query Monitor plugin enabled (or `SAVEQUERIES` set in `wp-config.php`)
- Several bookings across multiple products and orders

## Test Steps
1. Navigate to `/wp-json/fp-esperienze/v1/events?start=2024-01-01&end=2024-12-31` as an authenticated user.
2. Record the total number of database queries reported by Query Monitor.
3. Verify that the JSON response lists all expected bookings with correct titles, dates, customer info and totals.
4. For comparison, switch to a build without this prefetch optimization (or use git to checkout the previous commit) and repeat steps 1-3.

## Expected Results
- Event data is identical between both builds.
- Query count with the optimized build is significantly lower (only one product and one order query per unique ID, instead of per booking).
