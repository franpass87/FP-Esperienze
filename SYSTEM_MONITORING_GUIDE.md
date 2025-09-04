# FP Esperienze - Comprehensive System Monitoring

This document describes the enhanced system monitoring and performance capabilities implemented in FP Esperienze.

## 📊 System Health Monitoring Overview

The FP Esperienze plugin now includes comprehensive system health monitoring with real-time performance tracking, automated issue detection, and optimization recommendations.

## 🛠️ Enhanced Features

### 1. Advanced SystemStatus Dashboard

**Location**: `Admin > FP Esperienze > System Status`

#### New Monitoring Capabilities:
- **Cache Performance Analysis**: Real-time cache hit/miss ratios and response times
- **API Endpoint Health**: Automated testing of all REST API endpoints
- **Database Performance**: Query execution time monitoring and optimization suggestions
- **Memory Usage Tracking**: Real-time memory consumption with threshold alerts
- **Frontend Performance**: Asset optimization status and performance metrics

#### Performance Metrics Section:
- Live memory usage with percentage indicators
- Cache statistics with hit ratios
- Database query performance metrics
- PHP configuration status
- Server load monitoring

#### Optimization Recommendations:
- Automated analysis of system performance
- Priority-based recommendations (High/Medium/Low)
- Actionable steps for performance improvements
- Intelligent suggestions based on current system state

### 2. Real-Time JavaScript Performance Monitoring

**Location**: `assets/js/modules/performance.js`

#### Features:
- **System Health Monitoring**: Continuous monitoring every 30 seconds
- **Memory Usage Tracking**: JavaScript heap monitoring with leak detection
- **Performance Metrics**: Frame rate, response time, and interaction tracking
- **Health Trend Analysis**: Historical performance data with trend calculation
- **Automatic Issue Detection**: Real-time alerts for performance degradation
- **Admin Notifications**: In-dashboard alerts for critical issues

#### Monitoring Capabilities:
```javascript
// Access performance data
FPPerformance.getHealthReport(); // Get comprehensive health report
FPPerformance.getMetrics(); // Get current performance metrics
```

### 3. Comprehensive Health Check Script

**Location**: `system-health-check.php`

#### Usage:
```bash
# Basic health check
php system-health-check.php

# Detailed analysis
php system-health-check.php --detailed

# Auto-fix issues
php system-health-check.php --fix-issues
```

#### Checks Performed:
- **System Requirements**: PHP, WordPress, WooCommerce versions
- **Database Health**: Connection, tables, query performance
- **Performance Health**: Memory usage, cache performance, query optimization
- **Security Health**: File permissions, SSL, security headers
- **Integration Health**: External API connectivity, configured integrations
- **Cache Health**: Cache operations, statistics, optimization
- **API Health**: All REST endpoints functionality and response times
- **File System Health**: Critical files, permissions, disk space

### 4. Enhanced Cache Management

**Location**: `includes/Core/CacheManager.php`

#### Improvements:
- **Smart Cache Invalidation**: Automatic cache clearing on data changes
- **Pre-building**: Automated cache warming for better performance
- **Performance Analytics**: Cache hit/miss ratio tracking
- **Intelligent TTL**: Dynamic cache expiration based on usage patterns

### 5. Advanced Query Monitoring

**Location**: `includes/Core/QueryMonitor.php`

#### Features:
- **Slow Query Detection**: Automatic identification of performance bottlenecks
- **Query Analysis**: EXPLAIN plan logging for optimization
- **Performance Statistics**: Real-time query performance metrics
- **FP-Specific Monitoring**: Focused on plugin-related queries

## 🚀 Performance Improvements

### Implemented Optimizations:

1. **Intelligent Caching**:
   - 10-minute TTL for availability data
   - Automatic cache pre-building for next 7 days
   - Smart invalidation on booking changes

2. **Database Optimization**:
   - Query performance monitoring
   - Slow query detection and logging
   - Prepared statement usage verification

3. **Frontend Optimization**:
   - Asset minification support
   - Performance monitoring integration
   - Memory leak detection

4. **API Performance**:
   - Response time monitoring
   - Automatic endpoint health checks
   - Rate limiting protection

## 🔧 Configuration

### Performance Settings
Access via `Admin > FP Esperienze > Performance`

- **Cache Pre-build Days**: Set number of days to pre-build availability cache
- **Cache Statistics**: View current cache usage and hit ratios
- **Asset Optimization**: Enable/disable minification and compression
- **Performance Actions**: Manual cache clearing and optimization

### System Status Settings
Access via `Admin > FP Esperienze > System Status`

- **Real-time Monitoring**: View current system health
- **Performance Metrics**: Live performance data
- **Optimization Recommendations**: Automated suggestions
- **Fix Actions**: One-click fixes for common issues

## 📈 Monitoring Thresholds

### Performance Thresholds:
- **Memory Usage**: Warning at 60%, Error at 80%
- **Cache Performance**: Warning at 10ms response time
- **API Response Time**: Warning at 1000ms
- **Database Queries**: Warning at 100ms execution time
- **Frame Rate**: Warning below 30 FPS

### Health Check Intervals:
- **JavaScript Monitoring**: Every 30 seconds
- **System Health**: Every 5 minutes (admin pages)
- **Cache Pre-building**: Hourly via WP Cron
- **Health History**: Last 20 checks stored for trending

## 🛡️ Security Enhancements

### Current Security Status:
- **Input Sanitization**: ⚠️ Some unsanitized $_POST usage detected
- **Output Escaping**: ⚠️ Some unescaped output in templates
- **Prepared Statements**: ✅ Implemented
- **NONCE Protection**: ✅ Implemented
- **Rate Limiting**: ✅ Implemented
- **HMAC Security**: ✅ Implemented
- **File Security**: ✅ .htaccess protection implemented

### Recommendations:
1. Review and sanitize all $_POST/$_GET usage
2. Ensure all template output is properly escaped
3. Convert remaining unprepared queries to use $wpdb->prepare()

## 🔍 Troubleshooting

### Common Issues and Solutions:

1. **High Memory Usage**:
   - Increase PHP memory_limit
   - Enable object caching (Redis/Memcached)
   - Review code for memory leaks

2. **Slow Database Queries**:
   - Check QueryMonitor logs
   - Add database indexes
   - Optimize complex queries

3. **API Endpoint Failures**:
   - Check server logs
   - Verify WordPress rewrite rules
   - Test endpoint permissions

4. **Cache Performance Issues**:
   - Consider Redis/Memcached
   - Adjust cache TTL settings
   - Monitor cache hit ratios

### Debug Information:
- All performance data is logged when WP_DEBUG is enabled
- Health check data is stored in browser console
- System issues generate admin notices

## 📚 API Reference

### JavaScript Performance API:
```javascript
// Get current metrics
const metrics = FPPerformance.getMetrics();

// Get health report
const health = FPPerformance.getHealthReport();

// Performance utilities
const debouncedFn = FPPerformance.debounce(fn, 300);
const throttledFn = FPPerformance.throttle(fn, 100);
```

### PHP Health Check API:
```php
// Manual health check
$systemStatus = new \FP\Esperienze\Admin\SystemStatus();
$checks = $systemStatus->runSystemChecks();

// Cache operations
$cacheStats = \FP\Esperienze\Core\CacheManager::getCacheStats();
$cleared = \FP\Esperienze\Core\CacheManager::clearAllCaches();

// Query monitoring
$queryStats = \FP\Esperienze\Core\QueryMonitor::getStatistics();
```

## 🎯 Next Steps

### Recommended Actions:
1. **Regular Monitoring**: Check System Status weekly
2. **Performance Baseline**: Run initial health check to establish baseline
3. **Security Review**: Address identified security issues
4. **Optimization**: Implement recommended performance improvements
5. **Monitoring Setup**: Configure external monitoring for production sites

### Future Enhancements:
- External monitoring integration (New Relic, DataDog)
- Advanced alerting via email/webhooks
- Performance regression testing
- Automated optimization suggestions
- Machine learning-based anomaly detection

---

**Note**: This comprehensive monitoring system ensures that all FP Esperienze systems are functioning optimally and provides actionable insights for continuous performance improvement.