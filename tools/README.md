# FP Esperienze Diagnostic Tools

This directory contains diagnostic tools to help identify and resolve plugin activation issues.

## Tools Available

### 1. Activation Test (`activation-test.php`)
**Purpose**: Quick web-based test to check if plugin activation will succeed.

**Usage**: 
- Access via browser: `/wp-content/plugins/fp-esperienze/tools/activation-test.php`
- Must be logged in as administrator
- Provides immediate feedback on potential issues

**What it tests**:
- Environment requirements (PHP, WordPress, WooCommerce versions)
- Autoloader functionality
- Database connectivity and table creation
- File permissions
- Core class loading

### 2. Activation Diagnostic (`activation-diagnostic.php`)
**Purpose**: Comprehensive command-line diagnostic tool.

**Usage**:
- Run via WP-CLI with an administrator context:
  ```bash
  wp eval-file wp-content/plugins/fp-esperienze/tools/activation-diagnostic.php --user=<admin>
  ```
- Or access the file from the browser while logged in as an administrator
- Anonymous access is blocked for security reasons

**What it tests**:
- All environment requirements
- File permissions and directory creation
- Database readiness and table creation capability
- PHP extension availability
- Plugin conflicts
- Debug settings

**Notes**:
- OPcache is used for passive syntax checks. When OPcache is disabled, lint the reported file manually with `php -l`.
- Additional standalone tools (`status-report.php`, `test-php-syntax.php`) live in the plugin root and follow the same administrator-only access rules.

## Common Issues and Solutions

### Issue: "Plugin activation failed"
1. Run the activation test first
2. Check error logs in `wp-content/fp-esperienze-activation-errors.log`
3. Verify WooCommerce is active and updated
4. Check file permissions on wp-content directory

### Issue: "Class not found" errors
1. Verify all plugin files are present
2. Check file permissions (files should be readable)
3. Consider running `composer install --no-dev` in plugin directory
4. Check PHP error logs for syntax errors

### Issue: Database errors
1. Verify database connection
2. Check if user has CREATE TABLE permissions
3. Ensure adequate database space
4. Check for conflicting table names

### Issue: Permission errors
1. Ensure wp-content directory is writable
2. Check PHP user permissions
3. Verify disk space availability
4. Check for filesystem restrictions

## Error Logging

The plugin creates several log files for debugging:

- `wp-content/fp-esperienze-activation-errors.log` - Activation-specific errors
- `wp-content/fp-esperienze-errors.log` - General plugin errors
- `wp-content/fp-esperienze-recovery-info.json` - Recovery instructions

## Enabling Debug Mode

Add to `wp-config.php` for better error reporting:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

## Getting Support

If diagnostic tools don't resolve the issue:

1. Run both diagnostic tools and save the output
2. Check all generated log files
3. Include your server environment details (PHP version, WordPress version, etc.)
4. Provide any error messages from WordPress admin