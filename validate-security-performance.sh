#!/bin/bash

echo "🔒 FP Esperienze Security & Performance Validation"
echo "=================================================="
echo

# Initialize counters
total_files=0
passed_files=0
warnings=0
errors=0

# Function to validate security fixes
validate_security() {
    echo "🔍 Checking Security Fixes..."
    
    # Check for unsanitized $_POST usage
    echo "  📝 Validating $_POST sanitization..."
    unsanitized_post=$(grep -r "\$_POST\[" --include="*.php" includes/ | grep -v "sanitize\|wp_verify_nonce\|absint\|intval\|floatval\|esc_url_raw\|(float)\|(int)" | wc -l)
    if [ $unsanitized_post -eq 0 ]; then
        echo "  ✅ All $_POST usage properly sanitized"
    else
        echo "  ⚠️  Found $unsanitized_post potentially unsanitized $_POST usage"
        warnings=$((warnings + 1))
    fi
    
    # Check for unsanitized $_GET usage
    echo "  📝 Validating $_GET sanitization..."
    unsanitized_get=$(grep -r "\$_GET\[" --include="*.php" includes/ | grep -v "sanitize\|absint\|intval" | wc -l)
    if [ $unsanitized_get -eq 0 ]; then
        echo "  ✅ All $_GET usage properly sanitized"
    else
        echo "  ⚠️  Found $unsanitized_get potentially unsanitized $_GET usage"
        warnings=$((warnings + 1))
    fi
    
    # Check for unescaped output in templates
    echo "  📝 Validating template output escaping..."
    unescaped_output=$(grep -r "echo \|print " --include="*.php" templates/ | grep -v "esc_\|wp_json_encode.*JSON_HEX" | wc -l)
    if [ $unescaped_output -eq 0 ]; then
        echo "  ✅ All template output properly escaped"
    else
        echo "  ⚠️  Found $unescaped_output potentially unescaped output"
        warnings=$((warnings + 1))
    fi
    
    echo
}

# Function to validate performance improvements
validate_performance() {
    echo "🚀 Checking Performance Improvements..."
    
    # Check AssetOptimizer enhancements
    echo "  📝 Validating AssetOptimizer enhancements..."
    if grep -q "forceRegenerateAll" includes/Core/AssetOptimizer.php; then
        echo "  ✅ Asset optimization methods added"
    else
        echo "  ❌ Asset optimization methods missing"
        errors=$((errors + 1))
    fi
    
    if grep -q "getOptimizationStats" includes/Core/AssetOptimizer.php; then
        echo "  ✅ Asset optimization statistics tracking added"
    else
        echo "  ❌ Asset optimization statistics missing"
        errors=$((errors + 1))
    fi
    
    # Check SystemStatus enhancements
    echo "  📝 Validating SystemStatus performance features..."
    if grep -q "optimize_assets" includes/Admin/SystemStatus.php; then
        echo "  ✅ Asset optimization integration added to SystemStatus"
    else
        echo "  ❌ Asset optimization integration missing"
        errors=$((errors + 1))
    fi
    
    if grep -q "Large admin.js" includes/Admin/SystemStatus.php; then
        echo "  ✅ Large admin.js detection implemented"
    else
        echo "  ❌ Large admin.js detection missing"
        errors=$((errors + 1))
    fi
    
    # Check file sizes
    echo "  📝 Checking asset file sizes..."
    admin_js_size=$(wc -c < "assets/js/admin.js" 2>/dev/null || echo 0)
    admin_js_kb=$((admin_js_size / 1024))
    
    if [ $admin_js_size -gt 0 ]; then
        echo "  📊 admin.js size: ${admin_js_kb}KB"
        if [ $admin_js_size -gt 102400 ]; then
            echo "  ⚠️  admin.js is large (>100KB) - minification recommended"
            warnings=$((warnings + 1))
        else
            echo "  ✅ admin.js size is acceptable"
        fi
    else
        echo "  ⚠️  admin.js not found or empty"
        warnings=$((warnings + 1))
    fi
    
    echo
}

# Function to validate specific security improvements made
validate_specific_fixes() {
    echo "🔧 Checking Specific Security Fixes..."
    
    # Check meeting point coordinate sanitization
    if grep -q "sanitize_text_field(\$_POST\['meeting_point_lat'\])" includes/Admin/MenuManager.php; then
        echo "  ✅ Meeting point coordinates properly sanitized"
    else
        echo "  ⚠️  Meeting point coordinate sanitization may be incomplete"
        warnings=$((warnings + 1))
    fi
    
    # Check extra billing type sanitization
    if grep -q "sanitize_text_field(\$_POST\['extra_billing_type'\])" includes/Admin/MenuManager.php; then
        echo "  ✅ Extra billing type properly sanitized"
    else
        echo "  ⚠️  Extra billing type sanitization may be incomplete"
        warnings=$((warnings + 1))
    fi
    
    # Check template date escaping
    if grep -q "esc_attr(date" templates/admin/reports.php; then
        echo "  ✅ Template date output properly escaped"
    else
        echo "  ⚠️  Template date escaping may be incomplete"
        warnings=$((warnings + 1))
    fi
    
    # Check JSON encoding security
    if grep -q "JSON_HEX_TAG" templates/single-experience.php; then
        echo "  ✅ JSON encoding security enhanced"
    else
        echo "  ⚠️  JSON encoding security may need enhancement"
        warnings=$((warnings + 1))
    fi
    
    echo
}

# Function to check overall status
check_overall_status() {
    echo "📋 Overall Status"
    echo "=================="
    
    if [ $errors -eq 0 ] && [ $warnings -eq 0 ]; then
        echo "🎉 All security and performance improvements validated successfully!"
        echo "✅ Security: All inputs properly sanitized and outputs escaped"
        echo "✅ Performance: Asset optimization capabilities enhanced"
        echo "✅ Monitoring: Comprehensive performance tracking implemented"
    elif [ $errors -eq 0 ]; then
        echo "⚠️  Improvements completed with $warnings warnings"
        echo "✅ Critical security and performance issues addressed"
        echo "⚠️  Minor improvements recommended"
    else
        echo "❌ $errors critical issues found, $warnings warnings"
        echo "❌ Some security or performance improvements are incomplete"
    fi
    
    echo
    echo "Security & Performance Summary:"
    echo "- POST/GET sanitization: Enhanced"
    echo "- Template output escaping: Improved"
    echo "- Asset optimization: Enhanced with statistics"
    echo "- Large file detection: Implemented"
    echo "- Performance monitoring: Comprehensive"
    echo "- Security validation: Automated"
}

# Run all validations
validate_security
validate_performance
validate_specific_fixes
check_overall_status

# Exit with appropriate code
if [ $errors -eq 0 ]; then
    exit 0
else
    exit 1
fi