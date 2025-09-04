/**
 * FP Esperienze - Performance Monitoring Module
 * Handles performance metrics, monitoring, and optimization
 */

(function($) {
    'use strict';

    window.FPEsperienzePerformance = {
        
        // Performance metrics storage
        metrics: {
            scriptLoadTime: 0,
            initTime: 0,
            interactions: 0,
            errors: 0,
            avgResponseTime: 0,
            frameRate: {
                samples: [],
                current: 0
            },
            memoryUsage: {
                initial: 0,
                current: 0,
                peak: 0
            }
        },
        
        // Performance observers
        observers: {
            intersection: null,
            mutation: null,
            performance: null
        },
        
        /**
         * Initialize performance monitoring
         */
        init: function() {
            this.recordInitTime();
            this.initPerformanceMonitoring();
            this.monitorFrameRate();
            this.setupMemoryMonitoring();
            this.setupNetworkMonitoring();
        },

        /**
         * Record initialization time
         */
        recordInitTime: function() {
            this.metrics.scriptLoadTime = performance.now();
            this.metrics.initTime = this.metrics.scriptLoadTime;
            
            if (performance.memory) {
                this.metrics.memoryUsage.initial = performance.memory.usedJSHeapSize;
                this.metrics.memoryUsage.current = performance.memory.usedJSHeapSize;
            }
        },

        /**
         * Initialize performance monitoring
         */
        initPerformanceMonitoring: function() {
            try {
                // Setup Performance Observer for navigation and resource timing
                if ('PerformanceObserver' in window) {
                    this.setupPerformanceObserver();
                }
                
                // Setup Intersection Observer for lazy loading optimization
                this.setupIntersectionObserver();
                
                // Setup Mutation Observer for DOM changes monitoring
                this.setupMutationObserver();
                
                // Track user interactions
                this.trackUserInteractions();
                
                // Start system health monitoring
                this.startSystemHealthMonitoring();
                
                console.log('FP Esperienze: Performance monitoring initialized');
                
            } catch (error) {
                console.error('FP Esperienze: Performance monitoring setup failed:', error);
            }
        },

        /**
         * Setup Performance Observer
         */
        setupPerformanceObserver: function() {
            try {
                this.observers.performance = new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    
                    entries.forEach(entry => {
                        if (entry.entryType === 'navigation') {
                            this.trackNavigationTiming(entry);
                        } else if (entry.entryType === 'resource') {
                            this.trackResourceTiming(entry);
                        } else if (entry.entryType === 'measure') {
                            this.trackCustomMeasure(entry);
                        }
                    });
                });
                
                this.observers.performance.observe({
                    entryTypes: ['navigation', 'resource', 'measure']
                });
                
            } catch (error) {
                console.warn('FP Esperienze: PerformanceObserver not supported:', error);
            }
        },

        /**
         * Setup Intersection Observer for performance optimization
         */
        setupIntersectionObserver: function() {
            try {
                this.observers.intersection = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            this.handleElementVisible(entry.target);
                        }
                    });
                }, {
                    rootMargin: '50px',
                    threshold: 0.1
                });
                
                // Observe elements that can be lazy loaded
                $('.fp-time-slot-card-clean, .fp-override-card-clean').each((index, element) => {
                    this.observers.intersection.observe(element);
                });
                
            } catch (error) {
                console.warn('FP Esperienze: IntersectionObserver not supported:', error);
            }
        },

        /**
         * Setup Mutation Observer for DOM changes
         */
        setupMutationObserver: function() {
            try {
                this.observers.mutation = new MutationObserver((mutations) => {
                    mutations.forEach(mutation => {
                        if (mutation.type === 'childList') {
                            this.handleDOMChanges(mutation);
                        }
                    });
                });
                
                this.observers.mutation.observe(document.body, {
                    childList: true,
                    subtree: true
                });
                
            } catch (error) {
                console.warn('FP Esperienze: MutationObserver setup failed:', error);
            }
        },

        /**
         * Track navigation timing
         */
        trackNavigationTiming: function(entry) {
            const timing = {
                domContentLoaded: entry.domContentLoadedEventEnd - entry.domContentLoadedEventStart,
                loadComplete: entry.loadEventEnd - entry.loadEventStart,
                firstPaint: entry.responseEnd - entry.requestStart,
                totalLoadTime: entry.loadEventEnd - entry.navigationStart
            };
            
            console.log('FP Esperienze Navigation Timing:', timing);
            this.reportMetric('navigation', timing);
        },

        /**
         * Track resource timing
         */
        trackResourceTiming: function(entry) {
            // Only track our plugin's resources
            if (entry.name.includes('fp-esperienze') || entry.name.includes('admin.js')) {
                const timing = {
                    name: entry.name,
                    duration: entry.duration,
                    transferSize: entry.transferSize || 0,
                    encodedBodySize: entry.encodedBodySize || 0
                };
                
                this.reportMetric('resource', timing);
            }
        },

        /**
         * Track custom performance measures
         */
        trackCustomMeasure: function(entry) {
            console.log(`FP Esperienze Custom Measure: ${entry.name} - ${entry.duration}ms`);
            this.reportMetric('custom', {
                name: entry.name,
                duration: entry.duration
            });
        },

        /**
         * Monitor frame rate
         */
        monitorFrameRate: function() {
            let lastTime = performance.now();
            let frameCount = 0;
            
            const measureFrameRate = (currentTime) => {
                frameCount++;
                
                if (currentTime - lastTime >= 1000) {
                    this.metrics.frameRate.current = frameCount;
                    this.metrics.frameRate.samples.push(frameCount);
                    
                    // Keep only last 10 samples
                    if (this.metrics.frameRate.samples.length > 10) {
                        this.metrics.frameRate.samples.shift();
                    }
                    
                    frameCount = 0;
                    lastTime = currentTime;
                    
                    // Alert if frame rate is consistently low
                    if (this.getAverageFrameRate() < 30) {
                        console.warn('FP Esperienze: Low frame rate detected:', this.getAverageFrameRate());
                    }
                }
                
                requestAnimationFrame(measureFrameRate);
            };
            
            requestAnimationFrame(measureFrameRate);
        },

        /**
         * Setup memory monitoring
         */
        setupMemoryMonitoring: function() {
            if (!performance.memory) return;
            
            setInterval(() => {
                const currentMemory = performance.memory.usedJSHeapSize;
                this.metrics.memoryUsage.current = currentMemory;
                
                if (currentMemory > this.metrics.memoryUsage.peak) {
                    this.metrics.memoryUsage.peak = currentMemory;
                }
                
                // Alert if memory usage is high
                const memoryIncrease = currentMemory - this.metrics.memoryUsage.initial;
                if (memoryIncrease > 50 * 1024 * 1024) { // 50MB increase
                    console.warn('FP Esperienze: High memory usage detected:', this.formatBytes(currentMemory));
                }
                
            }, 5000); // Check every 5 seconds
        },

        /**
         * Setup network monitoring
         */
        setupNetworkMonitoring: function() {
            // Monitor AJAX requests
            const originalAjax = $.ajax;
            const self = this;
            
            $.ajax = function(settings) {
                const startTime = performance.now();
                
                const originalSuccess = settings.success;
                const originalError = settings.error;
                
                settings.success = function(data, textStatus, jqXHR) {
                    const duration = performance.now() - startTime;
                    self.trackAjaxRequest(settings.url, duration, true);
                    
                    if (originalSuccess) {
                        originalSuccess.apply(this, arguments);
                    }
                };
                
                settings.error = function(jqXHR, textStatus, errorThrown) {
                    const duration = performance.now() - startTime;
                    self.trackAjaxRequest(settings.url, duration, false);
                    
                    if (originalError) {
                        originalError.apply(this, arguments);
                    }
                };
                
                return originalAjax.call(this, settings);
            };
        },

        /**
         * Track AJAX requests
         */
        trackAjaxRequest: function(url, duration, success) {
            // Only track our plugin's AJAX requests
            if (url && url.includes('fp_esperienze')) {
                this.updateAverageResponseTime(duration);
                
                if (!success) {
                    this.metrics.errors++;
                }
                
                console.log(`FP Esperienze AJAX: ${url} - ${duration.toFixed(2)}ms - ${success ? 'Success' : 'Error'}`);
            }
        },

        /**
         * Track user interactions
         */
        trackUserInteractions: function() {
            $(document).on('click change input', '[data-fp-track]', () => {
                this.metrics.interactions++;
            });
        },

        /**
         * Handle element visibility for lazy loading
         */
        handleElementVisible: function(element) {
            const $element = $(element);
            
            // Lazy load images
            $element.find('img[data-src]').each(function() {
                const $img = $(this);
                $img.attr('src', $img.data('src')).removeAttr('data-src');
            });
            
            // Initialize complex widgets only when visible
            if ($element.hasClass('fp-complex-widget') && !$element.data('initialized')) {
                this.initializeComplexWidget($element);
                $element.data('initialized', true);
            }
        },

        /**
         * Handle DOM changes
         */
        handleDOMChanges: function(mutation) {
            // Track DOM mutations for performance impact
            if (mutation.addedNodes.length > 5) {
                console.log('FP Esperienze: Large DOM change detected:', mutation.addedNodes.length, 'nodes added');
            }
            
            // Observe new elements for intersection
            mutation.addedNodes.forEach(node => {
                if (node.nodeType === 1 && $(node).hasClass('fp-time-slot-card-clean')) {
                    this.observers.intersection.observe(node);
                }
            });
        },

        /**
         * Update average response time
         */
        updateAverageResponseTime: function(duration) {
            if (this.metrics.avgResponseTime === 0) {
                this.metrics.avgResponseTime = duration;
            } else {
                this.metrics.avgResponseTime = (this.metrics.avgResponseTime + duration) / 2;
            }
        },

        /**
         * Get average frame rate
         */
        getAverageFrameRate: function() {
            if (this.metrics.frameRate.samples.length === 0) return 60;
            
            const sum = this.metrics.frameRate.samples.reduce((a, b) => a + b, 0);
            return sum / this.metrics.frameRate.samples.length;
        },

        /**
         * Get performance summary
         */
        getPerformanceSummary: function() {
            return {
                ...this.metrics,
                averageFrameRate: this.getAverageFrameRate(),
                memoryUsageFormatted: {
                    initial: this.formatBytes(this.metrics.memoryUsage.initial),
                    current: this.formatBytes(this.metrics.memoryUsage.current),
                    peak: this.formatBytes(this.metrics.memoryUsage.peak)
                }
            };
        },

        /**
         * Report metric to external service (if configured)
         */
        reportMetric: function(type, data) {
            // This could be extended to send metrics to analytics service
            if (window.fp_esperienze_analytics && window.fp_esperienze_analytics.enabled) {
                // Send to analytics service
            }
        },

        /**
         * Format bytes for human readability
         */
        formatBytes: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        /**
         * Create performance timing mark
         */
        mark: function(name) {
            if (performance.mark) {
                performance.mark(`fp-esperienze-${name}`);
            }
        },

        /**
         * Create performance timing measure
         */
        measure: function(name, startMark, endMark) {
            if (performance.measure) {
                performance.measure(`fp-esperienze-${name}`, `fp-esperienze-${startMark}`, `fp-esperienze-${endMark}`);
            }
        },

        /**
         * Optimize performance by debouncing expensive operations
         */
        debounce: function(func, wait, immediate) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                var later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                var callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        },

        /**
         * Throttle function for scroll/resize events
         */
        throttle: function(func, limit) {
            var inThrottle;
            return function() {
                var args = arguments;
                var context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        },

        /**
         * Start system health monitoring
         */
        startSystemHealthMonitoring: function() {
            // Monitor system health every 30 seconds
            setInterval(() => {
                this.checkSystemHealth();
            }, 30000);

            // Initial health check after 5 seconds
            setTimeout(() => {
                this.checkSystemHealth();
            }, 5000);
        },

        /**
         * Check overall system health
         */
        checkSystemHealth: function() {
            try {
                const healthData = {
                    timestamp: Date.now(),
                    memory: this.getMemoryUsage(),
                    performance: this.getPerformanceMetrics(),
                    errors: this.metrics.errors,
                    interactions: this.metrics.interactions,
                    dom_nodes: document.querySelectorAll('*').length,
                    active_timers: this.getActiveTimersCount()
                };

                // Check for performance issues
                this.analyzeSystemHealth(healthData);

                // Store health data for trending
                this.storeHealthData(healthData);

            } catch (error) {
                console.error('FP Esperienze: System health check failed:', error);
            }
        },

        /**
         * Get current memory usage information
         */
        getMemoryUsage: function() {
            if (performance.memory) {
                return {
                    used: performance.memory.usedJSHeapSize,
                    total: performance.memory.totalJSHeapSize,
                    limit: performance.memory.jsHeapSizeLimit,
                    usage_percent: (performance.memory.usedJSHeapSize / performance.memory.jsHeapSizeLimit) * 100
                };
            }
            return null;
        },

        /**
         * Get performance metrics summary
         */
        getPerformanceMetrics: function() {
            return {
                avg_response_time: this.metrics.avgResponseTime,
                frame_rate: this.getAverageFrameRate(),
                init_time: this.metrics.initTime,
                total_interactions: this.metrics.interactions
            };
        },

        /**
         * Estimate active timers count
         */
        getActiveTimersCount: function() {
            // This is an approximation since we can't directly access all timers
            let count = 0;
            
            // Count setInterval handlers we can detect
            if (window.FPEsperienzeAdmin && window.FPEsperienzeAdmin.activeTimers) {
                count += window.FPEsperienzeAdmin.activeTimers.length;
            }
            
            return count;
        },

        /**
         * Analyze system health and generate alerts
         */
        analyzeSystemHealth: function(healthData) {
            const warnings = [];
            const errors = [];

            // Memory usage analysis
            if (healthData.memory) {
                if (healthData.memory.usage_percent > 80) {
                    errors.push('High memory usage: ' + healthData.memory.usage_percent.toFixed(1) + '%');
                } else if (healthData.memory.usage_percent > 60) {
                    warnings.push('Moderate memory usage: ' + healthData.memory.usage_percent.toFixed(1) + '%');
                }
            }

            // Performance analysis
            if (healthData.performance.avg_response_time > 1000) {
                warnings.push('Slow API responses: ' + healthData.performance.avg_response_time.toFixed(0) + 'ms average');
            }

            if (healthData.performance.frame_rate < 30) {
                warnings.push('Low frame rate: ' + healthData.performance.frame_rate.toFixed(1) + ' FPS');
            }

            // DOM nodes count
            if (healthData.dom_nodes > 5000) {
                warnings.push('High DOM complexity: ' + healthData.dom_nodes + ' nodes');
            }

            // Error rate analysis
            if (healthData.errors > 10) {
                errors.push('High error count: ' + healthData.errors + ' errors');
            }

            // Log significant issues
            if (errors.length > 0) {
                console.error('FP Esperienze: System health issues detected:', errors);
                this.notifySystemIssues('error', errors);
            } else if (warnings.length > 0) {
                console.warn('FP Esperienze: System performance warnings:', warnings);
                this.notifySystemIssues('warning', warnings);
            }
        },

        /**
         * Store health data for trending analysis
         */
        storeHealthData: function(healthData) {
            // Store last 20 health checks for trending
            if (!this.healthHistory) {
                this.healthHistory = [];
            }

            this.healthHistory.push(healthData);
            
            if (this.healthHistory.length > 20) {
                this.healthHistory.shift();
            }

            // Update current health metrics
            this.currentHealth = healthData;
        },

        /**
         * Notify about system issues
         */
        notifySystemIssues: function(level, issues) {
            // Only notify for critical issues to avoid spam
            if (level === 'error' && !this.lastNotificationTime || 
                Date.now() - this.lastNotificationTime > 300000) { // 5 minutes throttle
                
                this.lastNotificationTime = Date.now();
                
                // Show admin notice if on admin page
                if (window.pagenow && window.pagenow.includes('fp-esperienze')) {
                    const notice = document.createElement('div');
                    notice.className = 'notice notice-error is-dismissible';
                    notice.innerHTML = '<p><strong>FP Esperienze Performance Alert:</strong> ' + issues.join(', ') + '</p>';
                    
                    const adminNotices = document.querySelector('.wrap h1');
                    if (adminNotices && adminNotices.parentNode) {
                        adminNotices.parentNode.insertBefore(notice, adminNotices.nextSibling);
                    }
                }
            }
        },

        /**
         * Get system health report
         */
        getHealthReport: function() {
            return {
                current: this.currentHealth,
                history: this.healthHistory,
                metrics: this.metrics,
                trends: this.calculateHealthTrends()
            };
        },

        /**
         * Calculate health trends from history
         */
        calculateHealthTrends: function() {
            if (!this.healthHistory || this.healthHistory.length < 2) {
                return null;
            }

            const recent = this.healthHistory.slice(-5); // Last 5 checks
            const older = this.healthHistory.slice(0, 5); // First 5 checks

            const recentAvg = recent.reduce((sum, h) => sum + (h.memory ? h.memory.usage_percent : 0), 0) / recent.length;
            const olderAvg = older.reduce((sum, h) => sum + (h.memory ? h.memory.usage_percent : 0), 0) / older.length;

            return {
                memory_trend: recentAvg - olderAvg,
                performance_trend: recent[recent.length - 1].performance.avg_response_time - older[0].performance.avg_response_time,
                error_trend: recent[recent.length - 1].errors - older[0].errors
            };
        },

        /**
         * Cleanup observers and listeners
         */
        cleanup: function() {
            if (this.observers.intersection) {
                this.observers.intersection.disconnect();
            }
            
            if (this.observers.mutation) {
                this.observers.mutation.disconnect();
            }
            
            if (this.observers.performance) {
                this.observers.performance.disconnect();
            }
        }
    };

    // Expose performance utilities globally
    window.FPPerformance = {
        debounce: window.FPEsperienzePerformance.debounce,
        throttle: window.FPEsperienzePerformance.throttle,
        mark: window.FPEsperienzePerformance.mark.bind(window.FPEsperienzePerformance),
        measure: window.FPEsperienzePerformance.measure.bind(window.FPEsperienzePerformance),
        getHealthReport: window.FPEsperienzePerformance.getHealthReport.bind(window.FPEsperienzePerformance),
        getMetrics: function() { return window.FPEsperienzePerformance.metrics; }
    };

})(jQuery);