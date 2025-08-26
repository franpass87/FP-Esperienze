# Milestone A Integration Plan

## Overview

This document outlines the integration of all "Milestone A — MVP" pull requests into a common integration branch `integration/milestone-a`.

## Pull Requests to Integrate

- **PR #4**: A1 Implement booking widget (copilot/fix-a0f8894c-8416-4552-a291-6847ac8e4347)
- **PR #5**: A2 Implement schedules and overrides (copilot/fix-4459b0bf-1885-4cdf-9fe9-2b34207e418c) **[REFERENCE PR]**
- **PR #6**: A3 Implement Meeting Points (copilot/fix-29e510bf-0ec2-4623-89e8-1628ff1b8e2a)
- **PR #7**: A4 Implement extras (copilot/fix-274f75c5-f989-495a-a3f9-a287aa1b5f58)
- **PR #8**: A5 Implement booking management (copilot/fix-01512394-cf06-4b45-87ba-7b7344d9a94c)

## Conflict Resolution Rules

### 1. Reference Files from PR #5 (Schedules/Overrides)

The following files from PR #5 are considered the **canonical reference** and must be preserved:

- `includes/Core/Installer.php` - Enhanced database schema with extended fields
- `includes/Booking/Availability.php` - Reference availability checking methods
- `includes/Data/DataManager.php` - Core data management functionality

**Action**: All other PRs must adapt their changes to work with these reference implementations.

### 2. Template Hook-Based Architecture 

**Problem**: PRs #4, #6, and #7 make direct modifications to `templates/single-experience.php`

**Solution**: Replace all direct template modifications with hook-based approach:

```php
<!-- Instead of direct HTML in template -->
<?php
/**
 * Hook: fp_exp/single/booking_widget
 * @hooked A1 Booking Widget display - 10
 */
do_action('fp_exp/single/booking_widget', $product);
?>
```

**Required Hooks**:
- `fp_exp/single/before_booking` - Content before booking widget
- `fp_exp/single/booking_widget` - Main booking widget (PR #4)
- `fp_exp/single/meeting_point` - Meeting point display (PR #6) 
- `fp_exp/single/extras` - Extras selection (PR #7)
- `fp_exp/single/after_booking` - Content after booking widget

### 3. Public API Method Signatures

**Rule**: Maintain all documented public method signatures from the reference PR.

**Critical Methods to Preserve**:
- `Availability::for_day(int $product_id, string $date): array`
- `Availability::check_slot(int $product_id, string $date, string $time, int $participants): bool`
- `Availability::get_capacity(int $product_id, string $date, string $time): int`

## Database Schema Integration

The integrated schema combines enhancements from all PRs:

### Meeting Points Table (from PR #6)
```sql
- Added: place_id varchar(255) DEFAULT NULL
- Changed: instructions → note (field rename)
```

### Extras Table (from PR #7)  
```sql
- Added: pricing_type enum('per_person', 'per_booking')
- Added: tax_class varchar(50) DEFAULT ''
- Added: is_active tinyint(1) NOT NULL DEFAULT 1
```

### Product Extras Association Table (from PR #7)
```sql
- New table: fp_product_extras (product_id, extra_id relationship)
```

### Schedules Table (from PR #5 - Reference)
```sql
- Enhanced: duration_min, lang, meeting_point_id, price_adult, price_child
```

### Overrides Table (from PR #5 - Reference)  
```sql
- Enhanced: capacity_override, price_override_json fields
```

## Integration Steps

### Step 1: Create Integration Branch
```bash
git checkout main
git checkout -b integration/milestone-a
git push origin integration/milestone-a
```

### Step 2: Change PR Base Branches
For each milestone PR:
1. Change base branch from `main` to `integration/milestone-a`  
2. Execute "Update branch" to sync with integration branch

### Step 3: Resolve Conflicts in Order

**3.1 Merge PR #5 (Schedules/Overrides) First**
- This is the reference PR - merge without conflicts

**3.2 Merge PR #6 (Meeting Points)**
- Conflicts: `includes/Core/Installer.php`
- Resolution: Keep PR #5 schedule/override enhancements, add PR #6 meeting point enhancements
- Template: Replace direct modifications with `do_action('fp_exp/single/meeting_point', $product)`

**3.3 Merge PR #7 (Extras)**
- Conflicts: `includes/Core/Installer.php`  
- Resolution: Keep existing changes, add extras table enhancements and new association table
- Template: Replace direct modifications with `do_action('fp_exp/single/extras', $product)`

**3.4 Merge PR #4 (Booking Widget)**  
- Conflicts: `templates/single-experience.php`
- Resolution: Replace direct widget HTML with `do_action('fp_exp/single/booking_widget', $product)`
- Dependencies: Update to depend on PR #5 for availability data

**3.5 Merge PR #8 (Booking Management)**
- Conflicts: `includes/Core/Installer.php` (bookings table)
- Resolution: Ensure booking table schema is compatible with other enhancements
- Dependencies: Depends on all previous PRs

## PR Description Updates

Each PR description will be updated with:

### Dependencies Section  
```markdown
## Dependencies

- Depends on #5 (Schedules/Overrides) - Core availability and database schema
- [Additional dependencies as needed]

## Issues Fixed

- Fixes #[original_issue_number]
```

## Hook Implementation Examples

### PR #4 (Booking Widget)
Instead of direct template modification, create:
```php
// In PR #4 main class
add_action('fp_exp/single/booking_widget', [$this, 'render_booking_widget'], 10);

public function render_booking_widget($product) {
    // Render booking widget HTML here
}
```

### PR #6 (Meeting Points)  
```php
// In PR #6 main class
add_action('fp_exp/single/meeting_point', [$this, 'render_meeting_point'], 10);

public function render_meeting_point($product) {
    // Render meeting point section here
}
```

### PR #7 (Extras)
```php  
// In PR #7 main class
add_action('fp_exp/single/extras', [$this, 'render_extras_selection'], 10);

public function render_extras_selection($product) {
    // Render extras selection interface here
}
```

## Final Integration Verification

1. **Database Schema**: All tables created with combined enhancements
2. **Template System**: Uses hooks instead of direct modifications
3. **API Compatibility**: All public method signatures preserved
4. **Functionality**: Each feature works independently and together
5. **Dependencies**: Clear dependency chain established

## Manual Actions Required

Due to GitHub API limitations, the following actions need manual completion:

1. **Create integration/milestone-a branch** on GitHub from main
2. **Change base branch** for PRs #4, #5, #6, #7, #8 to `integration/milestone-a`
3. **Execute "Update branch"** for each PR to sync with integration branch
4. **Resolve merge conflicts** following the rules above
5. **Update PR descriptions** with dependency information

## Expected Outcome

After integration:
- Single integration branch with all Milestone A features
- Hook-based architecture preventing template conflicts
- Reference implementations maintained from PR #5
- Clear dependency chain: PR #5 → PR #6 → PR #7 → PR #4 → PR #8
- All features working together seamlessly