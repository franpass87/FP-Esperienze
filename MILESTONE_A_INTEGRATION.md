# Milestone A Integration Branch

## Branch: integration/milestone-a

This integration branch consolidates all Milestone A PRs for the FP Esperienze plugin MVP.

## PRs and Dependencies

### PR #4 (A1) - feature/booking-widget
- **Branch:** `copilot/fix-a0f8894c-8416-4552-a291-6847ac8e4347`
- **Files:** Cart_Hooks.php, Plugin.php, frontend assets, templates
- **Installer.php:** ❌ Does NOT modify
- **Dependencies:** 
  - Depends on PR #6 (meeting-points)

### PR #5 (A3) - feature/schedules-overrides
- **Branch:** `copilot/fix-4459b0bf-1885-4cdf-9fe9-2b34207e418c`
- **Files:** composer.lock only
- **Installer.php:** ❌ Does NOT modify
- **Dependencies:** None

### PR #6 (A2) - feature/meeting-points
- **Branch:** `copilot/fix-29e510bf-0ec2-4623-89e8-1628ff1b8e2a`
- **Files:** Installer.php, MeetingPoint.php, MeetingPointsManager.php, templates
- **Installer.php:** ✅ Modifies (meeting_points table: adds place_id; schedules table: adds meeting_point_id)
- **Dependencies:** None (base infrastructure)

### PR #7 (A5) - feature/extras
- **Branch:** `copilot/fix-274f75c5-f989-495a-a3f9-a287aa1b5f58`
- **Files:** Installer.php, ExtraManager.php, Experience.php product type
- **Installer.php:** ✅ Modifies (extras table: adds pricing_type, tax_class, is_active; creates fp_product_extras table)
- **Dependencies:** None

### PR #8 (A4) - feature/bookings-admin
- **Branch:** `copilot/fix-01512394-cf06-4b45-87ba-7b7344d9a94c`
- **Files:** composer.lock only
- **Installer.php:** ❌ Does NOT modify
- **Dependencies:** 
  - Depends on PR #4 (booking-widget)

## Merge Order

1. A2 (PR #6 - meeting-points) - Base infrastructure
2. A5 (PR #7 - extras) - Independent database changes
3. A1 (PR #4 - booking-widget) - Depends on meeting points
4. A3 (PR #5 - schedules-overrides) - Independent
5. A4 (PR #8 - bookings-admin) - Depends on booking widget

## Manual Steps Required

1. Change base branch of all PRs from `main` to `integration/milestone-a`
2. Add Dependencies section to each PR description as documented above
3. Follow merge order for integration