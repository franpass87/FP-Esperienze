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
        
        // Timer IDs for cleanup
        timers: {
            memoryMonitor: null,
            frameRateMonitor: null
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
                
                // Debug logging removed for production
                
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
            
            // Debug logging removed for production
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
            // Debug logging removed for production
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
            
            this.timers.memoryMonitor = setInterval(() => {
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
                
                // Debug logging removed for production
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
                // Debug logging removed for production
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
         * Cleanup observers and listeners
         */
        cleanup: function() {
            // Clean up observers
            if (this.observers.intersection) {
                this.observers.intersection.disconnect();
            }
            
            if (this.observers.mutation) {
                this.observers.mutation.disconnect();
            }
            
            if (this.observers.performance) {
                this.observers.performance.disconnect();
            }
            
            // Clean up timers
            if (this.timers.memoryMonitor) {
                clearInterval(this.timers.memoryMonitor);
                this.timers.memoryMonitor = null;
            }
            
            if (this.timers.frameRateMonitor) {
                clearInterval(this.timers.frameRateMonitor);
                this.timers.frameRateMonitor = null;
            }
        }
    };

    // Expose performance utilities globally
    window.FPPerformance = {
        debounce: window.FPEsperienzePerformance.debounce,
        throttle: window.FPEsperienzePerformance.throttle,
        mark: window.FPEsperienzePerformance.mark.bind(window.FPEsperienzePerformance),
        measure: window.FPEsperienzePerformance.measure.bind(window.FPEsperienzePerformance)
    };

})(jQuery);