# Manual Tests: WPML/Polylang i18n Compatibility

## Test Environment Setup

### Prerequisites
- WordPress installation with FP Esperienze plugin
- WPML or Polylang plugin installed (test with both)
- At least 2 languages configured (e.g., Italian, English)
- Some experience products created

## Test Cases

### Test 1: Plugin Detection and Settings

**Steps:**
1. Navigate to **FP Esperienze > Settings > General**
2. Check if multilingual plugin is detected correctly
3. Verify settings interface shows correct plugin status

**Expected Results:**
- ✅ With WPML: "WPML detected and active" message
- ✅ With Polylang: "Polylang detected and active" message  
- ✅ Without multilingual plugin: "No multilingual plugin detected" message
- ✅ Archive page dropdown is visible and functional

### Test 2: Archive Page URL Translation

**Steps:**
1. Create a page called "Experiences" (or "Esperienze" in Italian)
2. Set it as archive page in **FP Esperienze > Settings > General**
3. Translate the page to another language (WPML/Polylang)
4. Visit the archive page in different languages

**Expected Results:**
- ✅ Archive URLs respect translated page slugs
- ✅ Language switcher works on archive pages
- ✅ Content displays in correct language

### Test 3: Experience Language Filtering

**Steps:**
1. Create experience products in multiple languages
2. Add the shortcode `[fp_exp_archive]` to a page
3. Switch between languages and check the archive

**Expected Results:**
- ✅ Only experiences in current language are displayed
- ✅ Empty state when no experiences exist in a language
- ✅ Pagination works correctly per language

### Test 4: Meeting Point Translation (WPML)

**Steps (WPML only):**
1. Create meeting points with name and address
2. Go to **WPML > String Translation**
3. Look for "meeting_point_name_X" and "meeting_point_address_X" strings
4. Translate the strings
5. Switch language and view meeting points

**Expected Results:**
- ✅ Meeting point strings appear in String Translation
- ✅ Translated names/addresses display correctly
- ✅ place_id remains the same across languages

### Test 5: Meeting Point Language Management (Polylang)

**Steps (Polylang only):**
1. Create meeting points in default language
2. Switch to another language
3. Create/edit meeting points
4. Check experience product meeting point assignments

**Expected Results:**
- ✅ Meeting points can be managed per language
- ✅ Each language version can have its own meeting points
- ✅ Google Places integration works in all languages

### Test 6: Archive Block Language Filtering

**Steps:**
1. Create a page with the Experience Archive block
2. Configure block settings (filters, columns, etc.)
3. View page in different languages

**Expected Results:**
- ✅ Block respects current language
- ✅ Filters work correctly per language
- ✅ No JavaScript errors in browser console

### Test 7: Shortcode with Filters

**Steps:**
1. Use shortcode: `[fp_exp_archive filters="mp,lang,duration" posts_per_page="6"]`
2. Test language filter functionality
3. Test meeting point filter
4. Check pagination

**Expected Results:**
- ✅ Language filter shows available languages
- ✅ Meeting point filter shows translated names (WPML) or language-specific (Polylang)
- ✅ All filters work together correctly
- ✅ Pagination maintains filter state

### Test 8: Performance and Caching

**Steps:**
1. Enable WordPress object caching if available
2. Load archive pages multiple times
3. Check query count and load times
4. Switch languages and repeat

**Expected Results:**
- ✅ No significant performance degradation
- ✅ Database queries are reasonable
- ✅ Language switching is fast

## Troubleshooting

### Common Issues

**Archive shows wrong language experiences:**
- Check if WPML/Polylang is properly configured
- Verify experience products have language assigned
- Clear any caching plugins

**Meeting points not translating (WPML):**
- Go to WPML > String Translation
- Search for "meeting_point_name" strings
- Complete the translations

**Settings page errors:**
- Check PHP error logs
- Verify all plugin dependencies are active
- Clear any object cache

### Debug Information

To get debug info, add this to a test page:

```php
<?php
// Debug i18n status
$i18n_active = \FP\Esperienze\Core\I18nManager::isMultilingualActive();
$active_plugin = \FP\Esperienze\Core\I18nManager::getActivePlugin();
$current_lang = \FP\Esperienze\Core\I18nManager::getCurrentLanguage();
$available_langs = \FP\Esperienze\Core\I18nManager::getAvailableLanguages();

echo "Multilingual Active: " . ($i18n_active ? 'Yes' : 'No') . "<br>";
echo "Active Plugin: " . ($active_plugin ?: 'None') . "<br>";
echo "Current Language: " . ($current_lang ?: 'None') . "<br>";
echo "Available Languages: " . implode(', ', $available_langs) . "<br>";
?>
```

## Success Criteria

All tests pass with:
- ✅ No PHP errors or warnings
- ✅ Correct language filtering in all contexts
- ✅ Proper meeting point translation/management
- ✅ Functional admin interface
- ✅ Good performance characteristics
- ✅ Backward compatibility maintained