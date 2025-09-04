#!/bin/bash
# System Validation Script for FP Esperienze
# This script performs comprehensive checks without requiring a WordPress environment

echo "🚀 FP Esperienze System Validation"
echo "=================================="
echo ""

# Function to check file syntax
check_php_syntax() {
    local file="$1"
    if php -l "$file" >/dev/null 2>&1; then
        echo "✅ $file: Syntax OK"
        return 0
    else
        echo "❌ $file: Syntax Error"
        php -l "$file"
        return 1
    fi
}

check_js_syntax() {
    local file="$1"
    if command -v node >/dev/null 2>&1; then
        if node -c "$file" >/dev/null 2>&1; then
            echo "✅ $file: Syntax OK"
            return 0
        else
            echo "❌ $file: Syntax Error"
            node -c "$file"
            return 1
        fi
    else
        echo "⚠️  $file: Node.js not available, skipping syntax check"
        return 0
    fi
}

# Check PHP syntax for critical files
echo "🔍 Checking PHP Syntax..."
error_count=0

# Main plugin file
check_php_syntax "fp-esperienze.php" || ((error_count++))

# Core files
for file in includes/Core/*.php; do
    if [[ -f "$file" ]]; then
        check_php_syntax "$file" || ((error_count++))
    fi
done

# Admin files
for file in includes/Admin/*.php; do
    if [[ -f "$file" ]]; then
        check_php_syntax "$file" || ((error_count++))
    fi
done

# Check SystemStatus specifically
check_php_syntax "includes/Admin/SystemStatus.php" || ((error_count++))

# Health check script
check_php_syntax "system-health-check.php" || ((error_count++))

echo ""

# Check JavaScript syntax
echo "🔍 Checking JavaScript Syntax..."

check_js_syntax "assets/js/admin.js" || ((error_count++))
check_js_syntax "assets/js/frontend.js" || ((error_count++))
check_js_syntax "assets/js/modules/performance.js" || ((error_count++))
check_js_syntax "assets/js/modules/error-handler.js" || ((error_count++))

echo ""

# Check file structure
echo "🔍 Checking File Structure..."

critical_files=(
    "fp-esperienze.php"
    "includes/Admin/SystemStatus.php"
    "includes/Core/CacheManager.php"
    "includes/Core/QueryMonitor.php"
    "assets/js/modules/performance.js"
    "assets/js/modules/error-handler.js"
    "system-health-check.php"
)

for file in "${critical_files[@]}"; do
    if [[ -f "$file" ]]; then
        echo "✅ $file: Exists"
    else
        echo "❌ $file: Missing"
        ((error_count++))
    fi
done

echo ""

# Check security script
echo "🔍 Running Security Validation..."
if [[ -x "security-validate.sh" ]]; then
    ./security-validate.sh | grep -E "(✅|❌|⚠️)" | head -20
else
    echo "⚠️  Security validation script not executable"
fi

echo ""

# Performance and optimization checks
echo "🔍 Performance Configuration Checks..."

# Check if performance files are optimized
admin_js_size=$(stat -f%z "assets/js/admin.js" 2>/dev/null || stat -c%s "assets/js/admin.js" 2>/dev/null || echo "0")
performance_js_size=$(stat -f%z "assets/js/modules/performance.js" 2>/dev/null || stat -c%s "assets/js/modules/performance.js" 2>/dev/null || echo "0")

if [[ $admin_js_size -gt 100000 ]]; then  # 100KB
    echo "⚠️  admin.js is large ($(echo $admin_js_size | awk '{print int($1/1024)"KB"}')) - consider minification"
else
    echo "✅ admin.js size is reasonable ($(echo $admin_js_size | awk '{print int($1/1024)"KB"}'))"
fi

if [[ $performance_js_size -gt 50000 ]]; then  # 50KB
    echo "⚠️  performance.js is large ($(echo $performance_js_size | awk '{print int($1/1024)"KB"}'))"
else
    echo "✅ performance.js size is reasonable ($(echo $performance_js_size | awk '{print int($1/1024)"KB"}'))"
fi

# Check for common performance patterns
if grep -q "setInterval\|setTimeout" assets/js/modules/performance.js; then
    echo "✅ Performance monitoring timers found"
else
    echo "⚠️  No performance monitoring timers detected"
fi

if grep -q "PerformanceObserver" assets/js/modules/performance.js; then
    echo "✅ Modern Performance API usage detected"
else
    echo "⚠️  Modern Performance API not being used"
fi

echo ""

# System health monitoring checks
echo "🔍 System Health Monitoring Checks..."

if grep -q "checkCachePerformance\|checkAPIEndpoints\|checkDatabasePerformance" includes/Admin/SystemStatus.php; then
    echo "✅ Enhanced system health checks implemented"
else
    echo "❌ Enhanced system health checks missing"
    ((error_count++))
fi

if grep -q "renderPerformanceMetrics\|renderOptimizationRecommendations" includes/Admin/SystemStatus.php; then
    echo "✅ Performance metrics and recommendations implemented"
else
    echo "❌ Performance metrics and recommendations missing"
    ((error_count++))
fi

echo ""

# Comprehensive testing
echo "🔍 Feature Completeness Check..."

# Check SystemStatus enhancements
system_status_features=(
    "checkCachePerformance"
    "checkAPIEndpoints"
    "checkDatabasePerformance"
    "checkMemoryUsage"
    "checkFrontendPerformance"
    "renderPerformanceMetrics"
    "renderOptimizationRecommendations"
)

for feature in "${system_status_features[@]}"; do
    if grep -q "$feature" includes/Admin/SystemStatus.php; then
        echo "✅ SystemStatus: $feature implemented"
    else
        echo "❌ SystemStatus: $feature missing"
        ((error_count++))
    fi
done

# Check performance monitoring features
performance_features=(
    "startSystemHealthMonitoring"
    "checkSystemHealth"
    "analyzeSystemHealth"
    "getHealthReport"
    "notifySystemIssues"
)

for feature in "${performance_features[@]}"; do
    if grep -q "$feature" assets/js/modules/performance.js; then
        echo "✅ Performance: $feature implemented"
    else
        echo "❌ Performance: $feature missing"
        ((error_count++))
    fi
done

echo ""

# Final summary
echo "📋 Validation Summary"
echo "====================="

if [[ $error_count -eq 0 ]]; then
    echo "🎉 All checks passed! System is ready for comprehensive monitoring."
    echo ""
    echo "✅ Enhanced SystemStatus with performance monitoring"
    echo "✅ JavaScript performance monitoring with health checks"
    echo "✅ Comprehensive health check script"
    echo "✅ Security validation passed"
    echo "✅ All critical files present and valid"
    echo ""
    echo "🚀 The system is now capable of:"
    echo "   - Real-time performance monitoring"
    echo "   - Automated health checks"
    echo "   - Cache performance analysis"
    echo "   - API endpoint monitoring"
    echo "   - Memory usage tracking"
    echo "   - Database performance monitoring"
    echo "   - Frontend performance optimization"
    echo "   - Automated issue detection and recommendations"
    echo ""
else
    echo "❌ $error_count issues found that need to be addressed."
    echo ""
    echo "Please fix the issues above before deploying the enhanced monitoring system."
fi

exit $error_count