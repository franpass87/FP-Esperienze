# Integration Conflicts Analysis and Resolution

## Summary of Detected Conflicts

Based on analysis of all milestone PRs, here are the specific conflicts that would occur during integration and their resolutions:

## 1. Database Schema Conflicts (includes/Core/Installer.php)

### Conflict Sources:
- **PR #5 (Schedules/Overrides)**: Enhanced schedules and overrides tables  
- **PR #6 (Meeting Points)**: Enhanced meeting points table with place_id and note fields
- **PR #7 (Extras)**: Enhanced extras table + new product_extras association table
- **PR #8 (Booking Management)**: Potential bookings table enhancements

### Resolution Strategy:
**KEEP PR #5 as reference base**, then merge enhancements from other PRs:

```sql
-- From PR #5 (Reference - Keep all)
fp_schedules: +duration_min, +lang, +meeting_point_id, +price_adult, +price_child
fp_overrides: +capacity_override, +price_override_json

-- From PR #6 (Add to reference)  
fp_meeting_points: +place_id, instructions→note (rename)

-- From PR #7 (Add to reference)
fp_extras: +pricing_type, +tax_class, +is_active  
fp_product_extras: NEW TABLE (product_id, extra_id)

-- From PR #8 (Verify compatibility)
fp_bookings: Ensure compatible with other table changes
```

## 2. Template System Conflicts (templates/single-experience.php)

### Conflict Sources:
All PRs modify the same template file with overlapping sections:

**PR #4 (Booking Widget)**: Lines 140-200+ - Complete booking widget HTML
```php
<!-- Date Picker -->
<div class="fp-form-field">
    <label for="fp-date-picker"><?php _e('Select Date', 'fp-esperienze'); ?></label>
    <input type="date" id="fp-date-picker" class="fp-date-input" min="<?php echo date('Y-m-d'); ?>" />
</div>
<!-- + 50+ more lines of widget HTML -->
```

**PR #6 (Meeting Points)**: Lines 116-160 - Meeting point section
```php
<section class="fp-experience-meeting-point">
    <h2><?php _e('Meeting Point', 'fp-esperienze'); ?></h2>
    <div class="fp-meeting-point-info">
        <!-- Meeting point details HTML -->
    </div>
</section>
```

**PR #7 (Extras)**: Lines 160-200 - Extras selection interface  
```php
<div class="fp-extras-selection">
    <h3><?php _e('Add Extras', 'fp-esperienze'); ?></h3>
    <!-- Extras selection HTML -->
</div>
```

### Resolution Strategy:
**Replace ALL direct template modifications with hook-based approach:**

```php
<!-- BEFORE (Conflicting direct modifications) -->
<div class="fp-booking-widget">
    <!-- Direct HTML from PR #4 -->
</div>
<section class="fp-experience-meeting-point">  
    <!-- Direct HTML from PR #6 -->
</section>
<div class="fp-extras-selection">
    <!-- Direct HTML from PR #7 -->
</div>

<!-- AFTER (Hook-based integration) -->
<?php do_action('fp_exp/single/booking_widget', $product); ?>
<?php do_action('fp_exp/single/meeting_point', $product); ?>  
<?php do_action('fp_exp/single/extras', $product); ?>
```

## 3. Method Signature Conflicts

### Potential Conflict:
Multiple PRs might implement different versions of availability checking.

### Resolution Strategy:
**Enforce PR #5 (Schedules/Overrides) as canonical reference:**

```php
// CANONICAL SIGNATURES from PR #5 (Must be preserved)
class Availability {
    public function for_day(int $product_id, string $date): array
    public function check_slot(int $product_id, string $date, string $time, int $participants): bool  
    public function get_capacity(int $product_id, string $date, string $time): int
}
```

**All other PRs must adapt to use these signatures.**

## 4. Specific File-by-File Conflict Resolution

### includes/Core/Installer.php
**Resolution**: Merge all database enhancements while keeping PR #5 as base structure.

**Changes Required:**
1. Keep PR #5 schedules table enhancements
2. Keep PR #5 overrides table enhancements  
3. Add PR #6 meeting points enhancements (place_id, note)
4. Add PR #7 extras enhancements (pricing_type, tax_class, is_active)
5. Add PR #7 new product_extras table
6. Verify PR #8 bookings table compatibility

### templates/single-experience.php
**Resolution**: Use hook-based template from integration branch.

**Changes Required:**
1. PR #4: Move booking widget HTML to hook handler
2. PR #6: Move meeting point HTML to hook handler  
3. PR #7: Move extras HTML to hook handler
4. Update each PR to register appropriate hooks

### includes/ProductType/Experience.php
**Potential Conflicts**: Field additions from multiple PRs

**Resolution**: Merge all product meta fields, ensure no duplicates.

## 5. Integration Order Dependencies

Based on analysis, the optimal integration order is:

1. **PR #5 (Schedules/Overrides)** - FIRST (Reference implementation)
2. **PR #6 (Meeting Points)** - Database schema extends PR #5
3. **PR #7 (Extras)** - Database schema extends previous  
4. **PR #4 (Booking Widget)** - Depends on availability from PR #5
5. **PR #8 (Booking Management)** - LAST (Depends on all previous)

## 6. Required PR Updates

### PR #4 (Booking Widget)  
**Changes needed:**
- Replace direct template modifications with hook handlers
- Update to use Availability class from PR #5
- Add dependency: "Depends on #5"

### PR #6 (Meeting Points)
**Changes needed:**  
- Replace direct template modifications with hook handlers
- Ensure Installer.php merges with PR #5 schema
- Add dependency: "Depends on #5"

### PR #7 (Extras)
**Changes needed:**
- Replace direct template modifications with hook handlers  
- Ensure Installer.php merges with previous PR schemas
- Add dependency: "Depends on #5, #6"

### PR #8 (Booking Management)
**Changes needed:**
- Verify database compatibility with all previous schemas
- Add dependencies: "Depends on #5, #6, #7, #4"

## 7. Testing Strategy

After integration, verify:

1. **Database Schema**: All tables created correctly with combined fields
2. **Template Rendering**: All hooks render content in correct locations  
3. **Feature Independence**: Each feature works when others are disabled
4. **Feature Integration**: Features work together (e.g., booking widget shows meeting point)
5. **API Compatibility**: Public method signatures preserved across all PRs

## 8. Manual Actions Checklist

Due to GitHub API limitations, manual actions required:

- [ ] Create `integration/milestone-a` branch from main on GitHub
- [ ] Change base branch for PR #4 from main to integration/milestone-a  
- [ ] Change base branch for PR #5 from main to integration/milestone-a
- [ ] Change base branch for PR #6 from main to integration/milestone-a
- [ ] Change base branch for PR #7 from main to integration/milestone-a
- [ ] Change base branch for PR #8 from main to integration/milestone-a
- [ ] Execute "Update branch" for each PR in dependency order (5→6→7→4→8)
- [ ] Resolve conflicts following rules above
- [ ] Update each PR description with Dependencies section
- [ ] Final integration testing

## 9. Expected Final State

Post-integration `integration/milestone-a` branch will contain:

✅ **Unified Database Schema**: All table enhancements from all PRs  
✅ **Hook-Based Template**: No direct template conflicts  
✅ **Reference APIs**: Canonical method signatures preserved  
✅ **Clear Dependencies**: Explicit dependency chain documented  
✅ **Working MVP**: All Milestone A features functional together

The integration approach prevents conflicts by establishing clear architectural patterns and conflict resolution rules.