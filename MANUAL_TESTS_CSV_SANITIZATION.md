# Manual Test: CSV Export Sanitization

## Objective
Verify that CSV exports do not allow spreadsheet formula execution by sanitizing fields beginning with `=`, `+`, `-`, or `@`.

## Steps
1. Create or edit data so that at least one field (e.g., product name or notes) starts with `=2+2`, `+SUM(A1)`, `-1+1`, and `@HYPERLINK(...)`.
2. From **FP Esperienze → Reports**, export the report as CSV.
3. From **FP Esperienze → Bookings**, export the bookings list as CSV using the **Export** button.
4. Open the downloaded CSV files in spreadsheet software.

## Expected Results
- Cells that started with `=`, `+`, `-`, or `@` appear with a leading apostrophe (`'`).
- The values are displayed as plain text and are not interpreted as formulas.
