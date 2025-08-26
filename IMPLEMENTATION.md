# Schedules and Overrides Implementation Documentation

## Overview

This implementation adds comprehensive schedule and override management to the FP Esperienze WordPress plugin, enabling dynamic availability calculation based on real database data instead of mock slots.

## Features Implemented

### 1. Database Schema Updates

**fp_schedules table** - Updated to include:
- `duration_min` - Experience duration in minutes
- `lang` - Language code (e.g., 'en', 'it')
- `meeting_point_id` - Reference to meeting point
- `price_adult` - Adult pricing per schedule
- `price_child` - Child pricing per schedule

**fp_overrides table** - Updated to include:
- `is_closed` - Boolean flag for closure
- `capacity_override` - Override capacity for specific dates
- `price_override_json` - JSON object with adult/child price overrides
- `reason` - Text explanation for the override

### 2. Data Management Classes

#### ScheduleManager (`includes/Data/ScheduleManager.php`)
Provides CRUD operations for schedules:
- `getSchedules($product_id)` - Get all schedules for a product
- `getSchedulesForDay($product_id, $day_of_week)` - Get schedules for specific day
- `createSchedule($data)` - Create new schedule
- `updateSchedule($id, $data)` - Update existing schedule
- `deleteSchedule($id)` - Delete schedule
- `getSchedule($id)` - Get single schedule by ID

#### OverrideManager (`includes/Data/OverrideManager.php`)
Manages date-specific overrides:
- `getOverride($product_id, $date)` - Get override for specific date
- `getOverrides($product_id)` - Get all overrides for product
- `saveOverride($data)` - Create or update override
- `deleteOverride($product_id, $date)` - Delete override
- `getGlobalClosures()` - Get all closure dates across products
- `createGlobalClosure($date, $reason)` - Close all products on date
- `removeGlobalClosure($date)` - Remove global closure

#### Availability (`includes/Data/Availability.php`)
Real-time availability calculation:
- `forDay($product_id, $date)` - Calculate availability slots for date
- `isSlotAvailable($product_id, $date, $time, $spots)` - Check slot availability
- `getMeetingPoint($meeting_point_id)` - Get meeting point details

### 3. Admin Interface Enhancements

#### Product Edit Page
Enhanced Experience product tab with:
- **Schedules section**: Add/edit/remove weekly recurring schedules
- **Date Overrides section**: Add/edit/remove date-specific overrides
- Dynamic JavaScript for adding/removing rows
- Proper field validation and nonce security

#### Global Closures Page
New admin page at **FP Esperienze > Closures**:
- Add global closures affecting all experience products
- View existing closures with product details
- Remove individual closures
- Form validation and security checks

### 4. REST API Integration

Updated **AvailabilityAPI** (`includes/REST/AvailabilityAPI.php`):
- Replaced dummy slot generation with real database queries
- Integration with ScheduleManager and OverrideManager
- WordPress timezone support for accurate time calculations
- Proper error handling for invalid dates/products

### 5. Frontend Assets

#### JavaScript (`assets/js/admin.js`)
- Dynamic schedule/override row management
- Add/remove functionality for schedules and overrides
- Form validation and user experience improvements

#### CSS (`assets/css/admin.css`)
- Responsive layout for schedule/override forms
- Professional styling matching WordPress admin theme
- Mobile-friendly responsive design

## Usage

### Creating Schedules

1. Edit an Experience product in WooCommerce
2. Go to the "Experience" tab
3. In the "Schedules" section, click "Add Schedule"
4. Configure:
   - **Day**: Select day of week
   - **Start Time**: Time in H:i format
   - **Duration**: Minutes (e.g., 60, 90, 120)
   - **Capacity**: Maximum participants
   - **Language**: Language code (en, it, es, etc.)
   - **Meeting Point**: Select from available meeting points
   - **Adult Price**: Price per adult
   - **Child Price**: Price per child

### Creating Overrides

1. In the same Experience tab, find "Date Overrides" section
2. Click "Add Override"
3. Configure:
   - **Date**: Specific date (YYYY-MM-DD)
   - **Closed**: Check to close completely
   - **Capacity Override**: Override schedule capacity
   - **Adult/Child Price**: Override schedule prices
   - **Reason**: Optional explanation

### Global Closures

1. Go to **WP Admin > FP Esperienze > Closures**
2. Add closure date and reason
3. This automatically creates overrides for all experience products

### API Usage

Get availability for a product:
```
GET /wp-json/fp-exp/v1/availability?product_id=123&date=2024-12-25
```

Response example:
```json
{
  "product_id": 123,
  "date": "2024-12-25",
  "slots": [
    {
      "schedule_id": 1,
      "start_time": "09:00",
      "end_time": "10:00",
      "capacity": 10,
      "booked": 3,
      "available": 7,
      "is_available": true,
      "adult_price": 50.00,
      "child_price": 25.00,
      "languages": "en",
      "meeting_point_id": 1
    }
  ],
  "total_slots": 1
}
```

## Timezone Handling

All time calculations use WordPress timezone (`wp_timezone()`):
- Schedule times are stored in database as TIME fields
- Date comparisons use WordPress timezone
- API responses maintain timezone consistency

## Database Performance

- Indexed columns for optimal query performance
- Efficient queries using appropriate WHERE clauses
- Minimal database calls per availability calculation

## Security

- All user inputs are sanitized and validated
- Nonce verification for form submissions
- Capability checks for admin functions
- SQL injection protection using prepared statements

## Hooks and Filters

The implementation follows WordPress standards and provides hooks for extensibility:

### Actions
- None added (following minimal change principle)

### Filters
- None added (following minimal change principle)

## Error Handling

- Graceful fallbacks for missing data
- Proper error responses in REST API
- Database error handling with rollback support
- User-friendly error messages in admin interface

## Testing

See `MANUAL_TESTS.md` for comprehensive test scenarios covering:
- Normal day with schedules
- Closures (global and product-specific)
- Price and capacity overrides
- API functionality
- Admin interface

## Migration

The implementation is designed to be backwards compatible:
- Existing data remains intact
- New fields have sensible defaults
- Database schema updates via dbDelta
- No breaking changes to existing functionality

## Performance Considerations

- Database queries are optimized with proper indexing
- Availability calculation is efficient for typical loads
- Consider caching for high-traffic scenarios
- Monitor query performance with many schedules

## Future Enhancements

Potential areas for expansion:
- Schedule templates for easy duplication
- Bulk operations for schedules/overrides
- Advanced pricing rules
- Integration with booking system
- Email notifications for closures
- Calendar view for schedules

This implementation provides a solid foundation for experience scheduling while maintaining code quality and WordPress standards.