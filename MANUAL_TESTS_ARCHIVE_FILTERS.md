# Manual Tests for Archive Filters Feature

## Test Cases

### 1. Basic Shortcode Test
- **Test**: `[fp_exp_archive]`
- **Expected**: Shows experience archive with default settings (12 items, 3 columns, date order DESC)
- **Verify**: Grid layout displays correctly, experiences shown without filters

### 2. Filter Parameters Test
- **Test**: `[fp_exp_archive filters="mp,lang,duration,date" per_page="6"]`
- **Expected**: Shows filter form with all 4 filters enabled, displays 6 experiences per page
- **Verify**: 
  - Meeting Point dropdown populated from database
  - Language dropdown shows unique languages from experiences
  - Duration filter has 3 predefined ranges
  - Date picker with min date = today

### 3. Ordering Test
- **Test**: `[fp_exp_archive order_by="price" order="ASC"]`
- **Expected**: Experiences sorted by adult price ascending
- **Verify**: Prices increase from left to right, top to bottom

### 4. Column Layout Test
- **Test**: `[fp_exp_archive columns="2"]` vs `[fp_exp_archive columns="4"]`
- **Expected**: Grid adjusts column count accordingly
- **Verify**: CSS grid-template-columns changes appropriately

### 5. Filter Functionality Tests

#### Language Filter
- **Action**: Select a language from dropdown and submit
- **Expected**: Only experiences with that language in `_fp_exp_langs` meta field shown
- **Verify**: URL updates with `?fp_lang=SelectedLanguage`, results filtered correctly

#### Meeting Point Filter
- **Action**: Select a meeting point and submit
- **Expected**: Only experiences with that meeting point ID shown
- **Verify**: URL updates with `?fp_mp=123`, results filtered by `_fp_exp_meeting_point_id`

#### Duration Filter
- **Action**: Select "Up to 1.5 hours" (<=90)
- **Expected**: Only experiences with duration ≤ 90 minutes shown
- **Verify**: URL updates with `?fp_duration=<=90`, meta query filters correctly

#### Date Availability Filter
- **Action**: Select tomorrow's date
- **Expected**: Only experiences with available slots tomorrow shown
- **Verify**: 
  - URL updates with `?fp_date=YYYY-MM-DD`
  - Uses `Availability::forDay()` to check real availability
  - Results cached for 5 minutes

### 6. Pagination Test
- **Action**: Create more than 12 experiences, navigate pages
- **Expected**: Pagination controls appear, clicking changes page
- **Verify**: URL updates with `?paged=2`, new experiences load

### 7. Combined Filters Test
- **Action**: Apply language + meeting point + duration filters
- **Expected**: Results match ALL criteria (AND logic)
- **Verify**: Multiple meta_query conditions applied correctly

### 8. Mobile Responsive Test
- **Action**: View on mobile device/narrow browser
- **Expected**: 
  - Filters stack vertically
  - Grid becomes single column
  - Pagination adapts to smaller screen
- **Verify**: CSS media queries work correctly

### 9. Lazy Loading Test
- **Action**: Scroll down to experience cards below fold
- **Expected**: Images load as they come into viewport
- **Verify**: `loading="lazy"` attribute present on images

### 10. Analytics Tracking Test
- **Action**: Click "Dettagli" button on experience card
- **Expected**: `dataLayer.push` event fired with correct data
- **Verify**: Browser console shows dataLayer event with:
  ```javascript
  {
    event: 'select_item',
    items: [{
      item_id: '123',
      item_name: 'Experience Name', 
      item_category: 'experience'
    }]
  }
  ```

### 11. Gutenberg Block Test
- **Action**: Add "Experience Archive" block in editor
- **Expected**: Block appears in Widgets category with controls
- **Verify**:
  - Inspector shows all settings (posts per page, columns, order, filters)
  - Preview shows block representation
  - Frontend renders same as shortcode
  - Block attributes save/load correctly

### 12. Performance Test
- **Action**: Apply date filter multiple times for same date
- **Expected**: First query hits database, subsequent queries use cache
- **Verify**: Check transient cache `fp_exp_available_products_YYYY-MM-DD` exists for 5 minutes

### 13. Error Handling Test
- **Action**: Apply filters with no matching results
- **Expected**: "No experiences found" message displayed
- **Verify**: Graceful handling of empty results

### 14. URL State Test
- **Action**: Apply filters, copy URL, open in new tab
- **Expected**: Same filtered state loads
- **Verify**: Filter form shows selected values, results match

### 15. Clear Filters Test
- **Action**: Apply multiple filters, click "Clear" button
- **Expected**: All filters reset, full results shown
- **Verify**: URL returns to base state without filter parameters

## Browser Compatibility
Test in:
- Chrome (latest)
- Firefox (latest) 
- Safari (latest)
- Edge (latest)
- Mobile browsers (iOS Safari, Android Chrome)

## Accessibility Test
- **Action**: Navigate using keyboard only
- **Expected**: All filters focusable and usable with keyboard
- **Verify**: ARIA labels present, screen reader friendly

## Expected File Changes
- ✅ `includes/Frontend/Shortcodes.php` - Extended shortcode functionality
- ✅ `includes/Blocks/ArchiveBlock.php` - New Gutenberg block
- ✅ `includes/Core/Plugin.php` - Block registration
- ✅ `assets/css/frontend.css` - Filter and responsive styles
- ✅ `assets/js/frontend.js` - Filter functionality and analytics
- ✅ `assets/js/archive-block.js` - Gutenberg block editor script
- ✅ `README.md` - Updated documentation