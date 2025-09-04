# Development Workflow - FP Esperienze

## Overview

This project uses a two-file composer setup to separate production and development dependencies:

- **`composer.json`** - Contains only production dependencies (clean for deployment)
- **`composer-dev.json`** - Contains development-only dependencies (PHPStan, PHPCS, etc.)

## Setup for Development

### 1. Initial Setup
```bash
# Clone the repository
git clone https://github.com/franpass87/FP-Esperienze.git
cd FP-Esperienze

# Install production dependencies
composer install --no-dev --optimize-autoloader
```

### 2. Development Environment Setup
```bash
# Install development dependencies using the helper script
bash dev-tools.sh install-dev
```

This script will:
- Create a backup of `composer.json`
- Merge `composer-dev.json` into `composer.json`
- Install all dependencies (production + development)

## Development Commands

### Code Quality Checks
```bash
# Run all quality checks
bash dev-tools.sh test

# Run individual checks
bash dev-tools.sh phpstan      # Static analysis
bash dev-tools.sh phpcs        # Code style check
bash dev-tools.sh js-check     # JavaScript validation
bash dev-tools.sh security     # Security scan
```

### Code Fixes
```bash
# Fix code style issues automatically
bash dev-tools.sh fix-style

# Generate performance report
bash dev-tools.sh performance
```

### Asset Management
```bash
# Minify assets for production
bash dev-tools.sh minify
```

## Important Notes

### Before Committing
1. **Never commit `composer.json` with development dependencies**
2. Always restore the clean production `composer.json` before committing
3. The `dev-tools.sh install-dev` command creates a backup, restore it if needed:
   ```bash
   cp composer.json.backup composer.json
   ```

### File Structure
- `composer.json` - Production dependencies only
- `composer-dev.json` - Development dependencies
- `composer.lock` - Locked to production dependencies
- `composer.json.backup` - Created by dev-tools.sh (temporary)

### CI/CD
The CI/CD pipeline expects:
- A clean `composer.json` with production dependencies only
- A valid `composer.lock` file matching the production dependencies
- `composer validate --no-check-publish` should pass

## Troubleshooting

### "Lock file is not up to date" Error
This happens when:
1. Development dependencies were committed to `composer.json`
2. The lock file doesn't match the current `composer.json`

**Solution:**
```bash
# Restore clean composer.json (production only)
git checkout HEAD -- composer.json

# Verify fix
composer validate --no-check-publish
```

### Development Dependencies Not Found
If PHPStan, PHPCS, or other dev tools are missing:
```bash
bash dev-tools.sh install-dev
```

## Best Practices

1. **Always use the dev-tools.sh script** for development setup
2. **Keep composer.json clean** for production deployment
3. **Run quality checks** before committing code
4. **Use semantic versioning** for releases
5. **Document any new development tools** in `composer-dev.json`

## Support

For issues with the development workflow, check:
1. This documentation
2. The `dev-tools.sh help` command
3. The project's GitHub issues