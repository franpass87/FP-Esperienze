/**
 * FP Esperienze - Error Handling Module
 * Handles error recovery, reporting, and user feedback
 */

(function($) {
    'use strict';

    window.FPEsperienzeErrorHandler = {
        
        // Error tracking
        errorLog: [],
        criticalErrors: 0,
        maxErrors: 10,
        recoveryAttempts: 0,
        maxRecoveryAttempts: 3,
        healthCheckInterval: null,
        recoveryTimeout: null,
        
        /**
         * Initialize error handling system
         */
        init: function() {
            this.setupGlobalErrorHandling();
            this.setupPromiseRejectionHandling();
            this.setupAjaxErrorHandling();
            this.initErrorRecovery();
            this.startHealthChecks();
            
            // Setup cleanup on page unload
            $(window).on('beforeunload', () => {
                this.cleanup();
            });
        },

        /**
         * Setup global error handling
         */
        setupGlobalErrorHandling: function() {
            const self = this;
            
            window.addEventListener('error', function(event) {
                self.handleGlobalError({
                    message: event.message,
                    filename: event.filename,
                    lineno: event.lineno,
                    colno: event.colno,
                    error: event.error,
                    type: 'javascript'
                });
            });
            
            // Also handle errors in jQuery
            $(document).ajaxError(function(event, jqXHR, ajaxSettings, thrownError) {
                self.handleAjaxError({
                    url: ajaxSettings.url,
                    status: jqXHR.status,
                    statusText: jqXHR.statusText,
                    error: thrownError,
                    responseText: jqXHR.responseText
                });
            });
        },

        /**
         * Setup promise rejection handling
         */
        setupPromiseRejectionHandling: function() {
            const self = this;
            
            window.addEventListener('unhandledrejection', function(event) {
                self.handlePromiseRejection({
                    reason: event.reason,
                    promise: event.promise
                });
            });
        },

        /**
         * Setup AJAX error handling
         */
        setupAjaxErrorHandling: function() {
            const self = this;
            
            // Override jQuery AJAX to add error handling
            const originalAjax = $.ajax;
            $.ajax = function(settings) {
                
                // Add default error handler if none provided
                if (!settings.error) {
                    settings.error = function(jqXHR, textStatus, errorThrown) {
                        self.handleAjaxError({
                            url: settings.url,
                            status: jqXHR.status,
                            statusText: jqXHR.statusText,
                            error: errorThrown,
                            responseText: jqXHR.responseText
                        });
                    };
                }
                
                return originalAjax.call(this, settings);
            };
        },

        /**
         * Handle global JavaScript errors
         */
        handleGlobalError: function(errorInfo) {
            this.logError('global', errorInfo);
            
            // Only handle FP Esperienze related errors
            if (this.isFPEsperienzeError(errorInfo)) {
                this.criticalErrors++;
                
                console.error('FP Esperienze Global Error:', errorInfo);
                
                // Show user-friendly error message
                this.showUserFriendlyError('A system error occurred. The interface will attempt to recover.');
                
                // Attempt recovery for critical errors
                if (this.criticalErrors >= 3) {
                    this.attemptRecovery();
                }
            }
        },

        /**
         * Handle AJAX errors
         */
        handleAjaxError: function(errorInfo) {
            this.logError('ajax', errorInfo);
            
            // Only handle FP Esperienze AJAX calls
            if (errorInfo.url && errorInfo.url.includes('fp_esperienze')) {
                console.error('FP Esperienze AJAX Error:', errorInfo);
                
                // Handle specific error statuses
                switch (errorInfo.status) {
                    case 403:
                        this.handlePermissionError();
                        break;
                    case 404:
                        this.handleNotFoundError();
                        break;
                    case 500:
                        this.handleServerError();
                        break;
                    case 0:
                        this.handleNetworkError();
                        break;
                    default:
                        this.handleGenericError(errorInfo);
                }
            }
        },

        /**
         * Handle promise rejections
         */
        handlePromiseRejection: function(rejectionInfo) {
            this.logError('promise', rejectionInfo);
            
            console.error('FP Esperienze Promise Rejection:', rejectionInfo.reason);
            
            // Attempt to recover from promise rejections
            this.handleCriticalError('Promise rejection', rejectionInfo.reason);
        },

        /**
         * Handle critical errors with recovery
         */
        handleCriticalError: function(message, error) {
            try {
                this.criticalErrors++;
                
                // Show user-friendly error with recovery options
                this.showUserFeedback(
                    'A system error occurred. The interface will attempt to recover automatically.',
                    'error',
                    5000
                );
                
                // Attempt automatic recovery with timeout tracking
                this.recoveryTimeout = setTimeout(() => {
                    this.attemptRecovery();
                }, 1000);
                
                // Log detailed error information
                console.error('FP Esperienze Critical Error:', {
                    message: message,
                    error: error,
                    timestamp: new Date().toISOString(),
                    userAgent: navigator.userAgent,
                    url: window.location.href,
                    criticalErrors: this.criticalErrors
                });
                
            } catch (recoveryError) {
                console.error('FP Esperienze: Error recovery failed:', recoveryError);
                // Fallback: show basic alert
                alert('A critical error occurred. Please refresh the page.');
            }
        },

        /**
         * Attempt to recover from errors
         */
        attemptRecovery: function() {
            if (this.recoveryAttempts >= this.maxRecoveryAttempts) {
                this.showFinalErrorMessage();
                return;
            }
            
            this.recoveryAttempts++;
            
            try {
                // Debug logging removed for production
                
                // Re-validate container existence
                this.validateContainers();
                
                // Re-bind critical event handlers
                this.rebindCriticalEvents();
                
                // Clear any stuck loading states
                $('.fp-loading').removeClass('fp-loading');
                
                // Reset error counters on successful recovery
                this.criticalErrors = 0;
                
                // Announce recovery to user
                this.showUserFeedback('System recovered successfully. You can continue working.', 'success');
                
                // Debug logging removed for production
                
            } catch (error) {
                console.error('FP Esperienze: Recovery attempt failed:', error);
                this.showUserFeedback('Unable to recover automatically. Please refresh the page.', 'warning', 8000);
            }
        },

        /**
         * Validate containers exist
         */
        validateContainers: function() {
            const requiredContainers = [
                '#fp-time-slots-container',
                '#fp-overrides-container'
            ];
            
            requiredContainers.forEach(selector => {
                if (!$(selector).length) {
                    console.warn(`FP Esperienze: Required container missing: ${selector}`);
                    throw new Error(`Required container missing: ${selector}`);
                }
            });
        },

        /**
         * Re-bind critical event handlers
         */
        rebindCriticalEvents: function() {
            // Re-initialize modules if they exist
            if (window.FPEsperienzeScheduleBuilder) {
                window.FPEsperienzeScheduleBuilder.init();
            }
            
            if (window.FPEsperienzeAccessibility) {
                window.FPEsperienzeAccessibility.init();
            }
        },

        /**
         * Handle specific error types
         */
        handlePermissionError: function() {
            this.showUserFeedback(
                'You don\'t have permission to perform this action. Please check your user role.',
                'error'
            );
        },

        handleNotFoundError: function() {
            this.showUserFeedback(
                'The requested resource was not found. Please try again.',
                'warning'
            );
        },

        handleServerError: function() {
            this.showUserFeedback(
                'A server error occurred. Please try again in a moment.',
                'error'
            );
        },

        handleNetworkError: function() {
            this.showUserFeedback(
                'Network connection failed. Please check your internet connection.',
                'warning'
            );
        },

        handleGenericError: function(errorInfo) {
            this.showUserFeedback(
                'An unexpected error occurred. Please try again.',
                'error'
            );
        },

        /**
         * Show user-friendly error message
         */
        showUserFriendlyError: function(message) {
            if (window.FPEsperienzeAdmin && window.FPEsperienzeAdmin.showUserFeedback) {
                window.FPEsperienzeAdmin.showUserFeedback(message, 'error', 5000);
            } else {
                // Fallback to console and alert
                console.error('FP Esperienze:', message);
                alert(message);
            }
        },

        /**
         * Show user feedback
         */
        showUserFeedback: function(message, type = 'info', duration = 3000) {
            try {
                // Remove existing feedback
                $('.fp-user-feedback').remove();
                
                var iconClass = {
                    'success': 'dashicons-yes-alt',
                    'error': 'dashicons-warning',
                    'warning': 'dashicons-flag',
                    'info': 'dashicons-info'
                }[type] || 'dashicons-info';
                
                var $feedback = $(`
                    <div class="fp-user-feedback fp-feedback-${type}">
                        <span class="dashicons ${iconClass}"></span>
                        <span class="fp-feedback-message">${message}</span>
                        <button class="fp-feedback-close" aria-label="Close">×</button>
                    </div>
                `);
                
                // Add styles
                $feedback.css({
                    'position': 'fixed',
                    'top': '20px',
                    'right': '20px',
                    'background': type === 'error' ? '#d63638' : type === 'warning' ? '#dba617' : type === 'success' ? '#00a32a' : '#0073aa',
                    'color': 'white',
                    'padding': '12px 16px',
                    'border-radius': '4px',
                    'box-shadow': '0 2px 8px rgba(0,0,0,0.15)',
                    'z-index': '999999',
                    'max-width': '400px',
                    'transform': 'translateX(100%)',
                    'transition': 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)'
                });
                
                $('body').append($feedback);
                
                // Slide in
                setTimeout(function() {
                    $feedback.css({
                        'transform': 'translateX(0)'
                    });
                }, 100);
                
                // Close button functionality
                $feedback.find('.fp-feedback-close').on('click', function() {
                    $feedback.css({
                        'opacity': '0',
                        'transform': 'translateX(100%)'
                    });
                    setTimeout(function() {
                        $feedback.remove();
                    }, 300);
                });
                
                // Auto-remove
                setTimeout(function() {
                    $feedback.css({
                        'opacity': '0',
                        'transform': 'translateX(100%)'
                    });
                    setTimeout(function() {
                        $feedback.remove();
                    }, 300);
                }, duration);
                
            } catch (error) {
                console.error('FP Esperienze: Error showing user feedback:', error);
            }
        },

        /**
         * Show final error message when recovery fails
         */
        showFinalErrorMessage: function() {
            const message = 'Multiple errors have occurred and automatic recovery has failed. Please save your work and refresh the page.';
            
            this.showUserFeedback(message, 'error', 10000);
            
            // Also log to console
            console.error('FP Esperienze: Final error state reached. Manual intervention required.');
        },

        /**
         * Log error to internal storage
         */
        logError: function(type, errorInfo) {
            const errorEntry = {
                type: type,
                timestamp: new Date().toISOString(),
                info: errorInfo,
                url: window.location.href,
                userAgent: navigator.userAgent
            };
            
            this.errorLog.push(errorEntry);
            
            // Keep only last 50 errors
            if (this.errorLog.length > 50) {
                this.errorLog.shift();
            }
        },

        /**
         * Check if error is related to FP Esperienze
         */
        isFPEsperienzeError: function(errorInfo) {
            if (!errorInfo) return false;
            
            const fpKeywords = ['FP', 'Esperienze', 'fp-esperienze', 'fp_esperienze'];
            const checkString = JSON.stringify(errorInfo).toLowerCase();
            
            return fpKeywords.some(keyword => checkString.includes(keyword.toLowerCase()));
        },

        /**
         * Start periodic health checks
         */
        startHealthChecks: function() {
            // Clear existing interval if any
            if (this.healthCheckInterval) {
                clearInterval(this.healthCheckInterval);
            }
            
            this.healthCheckInterval = setInterval(() => {
                this.performHealthCheck();
            }, 30000); // Every 30 seconds
        },

        /**
         * Stop health checks (cleanup)
         */
        stopHealthChecks: function() {
            if (this.healthCheckInterval) {
                clearInterval(this.healthCheckInterval);
                this.healthCheckInterval = null;
            }
        },

        /**
         * Perform system health check
         */
        performHealthCheck: function() {
            try {
                // Check if jQuery is available
                if (typeof $ === 'undefined') {
                    throw new Error('jQuery not available');
                }
                
                // Check if main containers exist
                this.validateContainers();
                
                // Check memory usage
                if (performance.memory) {
                    const memoryUsage = performance.memory.usedJSHeapSize;
                    if (memoryUsage > 100 * 1024 * 1024) { // 100MB
                        console.warn('FP Esperienze: High memory usage detected:', this.formatBytes(memoryUsage));
                    }
                }
                
                // Reset error counters if health check passes
                if (this.criticalErrors > 0) {
                    this.criticalErrors = Math.max(0, this.criticalErrors - 1);
                }
                
            } catch (error) {
                console.warn('FP Esperienze: Health check failed:', error);
                this.handleCriticalError('Health check failed', error);
            }
        },

        /**
         * Get error report
         */
        getErrorReport: function() {
            return {
                errorLog: this.errorLog,
                criticalErrors: this.criticalErrors,
                recoveryAttempts: this.recoveryAttempts,
                timestamp: new Date().toISOString()
            };
        },

        /**
         * Format bytes for display
         */
        formatBytes: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        /**
         * Clear error log
         */
        clearErrorLog: function() {
            this.errorLog = [];
            this.criticalErrors = 0;
            this.recoveryAttempts = 0;
            // Debug logging removed for production
        },

        /**
         * Cleanup method for page unload
         */
        cleanup: function() {
            this.stopHealthChecks();
            // Clear any pending timeouts that might be stored
            if (this.recoveryTimeout) {
                clearTimeout(this.recoveryTimeout);
                this.recoveryTimeout = null;
            }
        }
    };

})(jQuery);