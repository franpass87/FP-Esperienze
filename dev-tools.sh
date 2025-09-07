#!/bin/bash

# FP Esperienze Development Helper Script
# Provides development tools and code quality checks

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the plugin directory
if [ ! -f "fp-esperienze.php" ]; then
    print_error "This script must be run from the FP Esperienze plugin directory"
    exit 1
fi

# Function to install development dependencies
install_dev_deps() {
    print_status "Installing development dependencies..."
    
    if [ ! -f "composer.json" ]; then
        print_error "composer.json not found"
        exit 1
    fi
    
    # Merge dev dependencies into main composer.json
    if [ -f "composer-dev.json" ]; then
        print_status "Merging development dependencies..."
        
        # Create backup
        cp composer.json composer.json.backup
        
        # Simple merge (in production, you'd use jq for proper JSON merging)
        cat composer.json | head -n -1 > composer.json.tmp
        echo ',' >> composer.json.tmp
        tail -n +2 composer-dev.json | head -n -1 >> composer.json.tmp
        echo '}' >> composer.json.tmp
        mv composer.json.tmp composer.json
        
        print_success "Development dependencies merged"
    fi
    
    composer install --dev
    print_success "Development dependencies installed"
}

# Function to run PHPStan analysis
run_phpstan() {
    print_status "Running PHPStan static analysis..."
    
    if [ ! -f "vendor/bin/phpstan" ]; then
        print_warning "PHPStan not installed. Installing development dependencies..."
        install_dev_deps
    fi
    
    vendor/bin/phpstan analyse --no-progress 2>/dev/null || {
        print_error "PHPStan analysis failed"
        return 1
    }
    
    print_success "PHPStan analysis passed"
}

# Function to run PHPCS code style check
run_phpcs() {
    print_status "Running PHPCS code style check..."
    
    if [ ! -f "vendor/bin/phpcs" ]; then
        print_warning "PHPCS not installed. Installing development dependencies..."
        install_dev_deps
    fi
    
    vendor/bin/phpcs --report=summary 2>/dev/null || {
        print_error "PHPCS found code style issues"
        print_status "Run 'bash dev-tools.sh fix-style' to auto-fix issues"
        return 1
    }
    
    print_success "Code style check passed"
}

# Function to fix code style issues
fix_code_style() {
    print_status "Fixing code style issues with PHPCBF..."
    
    if [ ! -f "vendor/bin/phpcbf" ]; then
        print_warning "PHPCBF not installed. Installing development dependencies..."
        install_dev_deps
    fi
    
    vendor/bin/phpcbf --report=summary 2>/dev/null || true
    print_success "Code style fixes applied"
}

# Function to validate JavaScript
validate_js() {
    print_status "Validating JavaScript files..."
    
    # Check if Node.js is available
    if ! command -v node &> /dev/null; then
        print_warning "Node.js not available. Skipping JavaScript validation."
        return 0
    fi
    
    # Simple syntax check
    for file in assets/js/*.js assets/js/modules/*.js; do
        if [ -f "$file" ]; then
            node -c "$file" || {
                print_error "Syntax error in $file"
                return 1
            }
        fi
    done
    
    print_success "JavaScript validation passed"
}

# Function to minify assets
minify_assets() {
    print_status "Minifying assets..."
    
    # Create minified directories
    mkdir -p assets/js/min assets/css/min
    
    # Minify JavaScript modules (simple concatenation for now)
    if [ -d "assets/js/modules" ]; then
        cat assets/js/modules/*.js > assets/js/min/modules.min.js
        print_success "JavaScript modules minified"
    fi
    
    # In a real scenario, you'd use proper minification tools
    print_success "Asset minification completed"
}

# Function to run all tests
run_all_tests() {
    print_status "Running all quality checks..."
    
    local failed=0
    
    # Run PHP standards check first
    print_status "Running PHP standards compliance check..."
    if [ -f "php-standards-checker.php" ]; then
        php php-standards-checker.php || failed=1
    else
        print_warning "PHP standards checker not found"
    fi
    
    # Try to run PHPStan if available
    if [ -f "vendor/bin/phpstan" ]; then
        run_phpstan || failed=1
    else
        print_warning "PHPStan not available, skipping static analysis"
    fi
    
    # Try to run PHPCS if available  
    if [ -f "vendor/bin/phpcs" ]; then
        run_phpcs || failed=1
    else
        print_warning "PHPCS not available, skipping code style check"
    fi
    
    # Run JavaScript validation
    validate_js || failed=1
    
    if [ $failed -eq 0 ]; then
        print_success "All quality checks passed!"
    else
        print_error "Some quality checks failed"
        exit 1
    fi
}

# Function to generate performance report
generate_performance_report() {
    print_status "Generating performance report..."
    
    # Check file sizes
    echo "Asset Sizes:"
    echo "============"
    
    for file in assets/js/*.js assets/css/*.css; do
        if [ -f "$file" ]; then
            size=$(wc -c < "$file" | tr -d ' ')
            echo "$(basename "$file"): ${size} bytes"
        fi
    done
    
    echo ""
    echo "Module Sizes:"
    echo "============="
    
    for file in assets/js/modules/*.js; do
        if [ -f "$file" ]; then
            size=$(wc -c < "$file" | tr -d ' ')
            lines=$(wc -l < "$file" | tr -d ' ')
            echo "$(basename "$file"): ${size} bytes (${lines} lines)"
        fi
    done
    
    print_success "Performance report generated"
}

# Function to check for security issues
security_check() {
    print_status "Running basic security checks..."
    
    # Check for common security issues
    local issues=0
    
    # Check for eval() usage
    if grep -r "eval(" includes/ 2>/dev/null; then
        print_warning "eval() usage found - review for security"
        issues=$((issues + 1))
    fi
    
    # Check for $_GET/$_POST without sanitization
    if grep -r "\$_\(GET\|POST\)" includes/ | grep -v "sanitize\|wp_verify_nonce" 2>/dev/null; then
        print_warning "Unsanitized input detected - review for security"
        issues=$((issues + 1))
    fi
    
    # Check for SQL queries without preparation
    if grep -r "SELECT\|INSERT\|UPDATE\|DELETE" includes/ | grep -v "prepare\|wpdb" 2>/dev/null; then
        print_warning "Direct SQL queries detected - review for injection"
        issues=$((issues + 1))
    fi
    
    if [ $issues -eq 0 ]; then
        print_success "No obvious security issues found"
    else
        print_warning "$issues potential security issues found"
    fi
}

# Function to show help
show_help() {
    echo "FP Esperienze Development Tools"
    echo "==============================="
    echo ""
    echo "Usage: bash dev-tools.sh [command]"
    echo ""
    echo "Available commands:"
    echo "  install-dev    Install development dependencies"
    echo "  phpstan        Run PHPStan static analysis"
    echo "  phpcs          Run PHPCS code style check"
    echo "  fix-style      Fix code style issues with PHPCBF"
    echo "  js-check       Validate JavaScript syntax"
    echo "  minify         Minify assets"
    echo "  standards      Run PHP standards compliance check"
    echo "  test           Run all quality checks"
    echo "  performance    Generate performance report"
    echo "  security       Run basic security checks"
    echo "  help           Show this help message"
    echo ""
    echo "Examples:"
    echo "  bash dev-tools.sh test          # Run all checks"
    echo "  bash dev-tools.sh phpstan       # Run only PHPStan"
    echo "  bash dev-tools.sh fix-style     # Fix code style issues"
}

# Main command handling
case "${1:-help}" in
    "install-dev")
        install_dev_deps
        ;;
    "phpstan")
        run_phpstan
        ;;
    "phpcs")
        run_phpcs
        ;;
    "fix-style")
        fix_code_style
        ;;
    "js-check")
        validate_js
        ;;
    "minify")
        minify_assets
        ;;
    "standards")
        if [ -f "php-standards-checker.php" ]; then
            php php-standards-checker.php
        else
            print_error "PHP standards checker not found"
            exit 1
        fi
        ;;
    "test")
        run_all_tests
        ;;
    "performance")
        generate_performance_report
        ;;
    "security")
        security_check
        ;;
    "help"|*)
        show_help
        ;;
esac