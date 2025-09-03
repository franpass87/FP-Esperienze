/**
 * FP Esperienze - Modular Admin JavaScript
 * Main entry point that loads and coordinates all modules
 */

(function($) {
    'use strict';

    // Prevent multiple script execution
    if (window.FPEsperienzeAdmin && window.FPEsperienzeAdmin.initialized) {
        return;
    }

    // Main admin controller
    window.FPEsperienzeAdmin = {
        
        // Track initialization state
        initialized: false,
        hasUnsavedChanges: false,
        
        // Module instances
        modules: {
            scheduleBuilder: null,
            accessibility: null,
            performance: null,
            errorHandler: null
        },

        /**
         * Initialize the admin interface
         */
        init: function() {
            try {
                console.log('FP Esperienze: Initializing admin interface...');
                
                // Initialize error handling first
                this.initializeErrorHandling();
                
                // Initialize performance monitoring
                this.initializePerformanceMonitoring();
                
                // Initialize core functionality
                this.initializeCore();
                
                // Initialize modules
                this.initializeModules();
                
                // Initialize accessibility features
                this.initializeAccessibility();
                
                // Mark as initialized
                this.initialized = true;
                
                console.log('FP Esperienze: Admin interface initialized successfully');
                
                // Dispatch custom event
                $(document).trigger('fp-esperienze-admin-ready');
                
            } catch (error) {
                console.error('FP Esperienze: Failed to initialize admin interface:', error);
                this.handleInitializationError(error);
            }
        },

        /**
         * Initialize error handling
         */
        initializeErrorHandling: function() {
            if (window.FPEsperienzeErrorHandler) {
                this.modules.errorHandler = window.FPEsperienzeErrorHandler;
                this.modules.errorHandler.init();
                console.log('FP Esperienze: Error handling initialized');
            }
        },

        /**
         * Initialize performance monitoring
         */
        initializePerformanceMonitoring: function() {
            if (window.FPEsperienzePerformance) {
                this.modules.performance = window.FPEsperienzePerformance;
                this.modules.performance.init();
                console.log('FP Esperienze: Performance monitoring initialized');
            }
        },

        /**
         * Initialize core functionality
         */
        initializeCore: function() {
            this.handleProductTypeChange();
            this.bindCoreEvents();
            this.initBookingsPage();
            this.setupUnsavedChangesWarning();
        },

        /**
         * Initialize modules
         */
        initializeModules: function() {
            // Initialize schedule builder
            if (window.FPEsperienzeScheduleBuilder) {
                this.modules.scheduleBuilder = window.FPEsperienzeScheduleBuilder;
                this.modules.scheduleBuilder.init();
                console.log('FP Esperienze: Schedule builder initialized');
            }
        },

        /**
         * Initialize accessibility features
         */
        initializeAccessibility: function() {
            if (window.FPEsperienzeAccessibility) {
                this.modules.accessibility = window.FPEsperienzeAccessibility;
                this.modules.accessibility.init();
                console.log('FP Esperienze: Accessibility features initialized');
            }
        },

        /**
         * Handle product type changes
         */
        handleProductTypeChange: function() {
            var self = this;
            
            // Force experience type on experience product pages
            if ($('body').hasClass('post-type-product')) {
                this.forceExperienceType();
            }
            
            // Handle product type dropdown changes
            $('#product-type').on('change', function() {
                self.toggleExperienceFields($(this).val());
            });
            
            // Prevent form submission with wrong product type
            $('form#post').on('submit', function() {
                if ($('body').hasClass('post-type-product') && $('#product-type').val() !== 'experience') {
                    alert('This product must be of type "Experience"');
                    return false;
                }
            });
        },

        /**
         * Force experience product type
         */
        forceExperienceType: function() {
            var $productType = $('#product-type');
            
            if ($productType.length && $productType.val() !== 'experience') {
                $productType.val('experience').trigger('change');
                
                // Show notice
                this.showUserFeedback(
                    'Product type has been set to "Experience" as required by FP Esperienze.',
                    'info'
                );
            }
        },

        /**
         * Toggle experience-specific fields based on product type
         */
        toggleExperienceFields: function(productType) {
            var $body = $('body');
            
            // Remove existing product type classes
            $body.removeClass(function(index, className) {
                return (className.match(/(^|\s)product-type-\S+/g) || []).join(' ');
            });
            
            // Add current product type class
            $body.addClass('product-type-' + productType);
            
            // Show/hide experience fields
            if (productType === 'experience') {
                $('.fp-experience-fields').show();
                $('.show_if_experience').show();
                $('.hide_if_experience').hide();
            } else {
                $('.fp-experience-fields').hide();
                $('.show_if_experience').hide();
                $('.hide_if_experience').show();
            }
        },

        /**
         * Bind core events
         */
        bindCoreEvents: function() {
            var self = this;
            
            // Auto-save indicator
            $(document).on('change', 'input, select, textarea', function() {
                if ($(this).closest('.fp-experience-fields').length) {
                    self.markAsChanged();
                }
            });
            
            // Form validation
            $('form#post').on('submit', function(e) {
                if (!self.validateForm()) {
                    e.preventDefault();
                    return false;
                }
            });
        },

        /**
         * Initialize bookings page functionality
         */
        initBookingsPage: function() {
            if ($('body').hasClass('fp-esperienze_page_fp-esperienze-bookings')) {
                this.initBookingsCalendar();
            }
        },

        /**
         * Initialize bookings calendar
         */
        initBookingsCalendar: function() {
            var self = this;
            
            if ($('#fp-bookings-calendar').length) {
                // Load FullCalendar library if not already loaded
                this.loadFullCalendar().then(function() {
                    self.renderCalendar();
                }).catch(function(error) {
                    console.error('FP Esperienze: Failed to load FullCalendar:', error);
                });
            }
        },

        /**
         * Load FullCalendar library
         */
        loadFullCalendar: function() {
            return new Promise(function(resolve, reject) {
                if (window.FullCalendar) {
                    resolve();
                    return;
                }
                
                // Load CSS
                var css = document.createElement('link');
                css.rel = 'stylesheet';
                css.href = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css';
                document.head.appendChild(css);
                
                // Load JS
                var script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js';
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });
        },

        /**
         * Render bookings calendar
         */
        renderCalendar: function() {
            var calendarEl = document.getElementById('fp-bookings-calendar');
            
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: {
                    url: ajaxurl,
                    method: 'POST',
                    extraParams: function() {
                        return {
                            action: 'fp_esperienze_get_bookings',
                            nonce: fp_esperienze_admin.nonce
                        };
                    },
                    failure: function() {
                        alert('Failed to load bookings');
                    }
                },
                eventClick: function(info) {
                    // Show booking details modal
                    this.showBookingDetails(info.event);
                }.bind(this)
            });
            
            calendar.render();
        },

        /**
         * Setup unsaved changes warning
         */
        setupUnsavedChangesWarning: function() {
            var self = this;
            
            // Warn before leaving page with unsaved changes
            $(window).on('beforeunload', function() {
                if (self.hasUnsavedChanges) {
                    return 'You have unsaved changes. Are you sure you want to leave?';
                }
            });
            
            // Clear warning when form is submitted
            $('form#post').on('submit', function() {
                self.clearUnsavedChanges();
            });
        },

        /**
         * Mark form as having unsaved changes
         */
        markAsChanged: function() {
            this.hasUnsavedChanges = true;
            
            // Update UI to show unsaved state
            if (!$('.fp-unsaved-indicator').length) {
                $('#publish').after('<span class="fp-unsaved-indicator"> (unsaved changes)</span>');
            }
        },

        /**
         * Clear unsaved changes state
         */
        clearUnsavedChanges: function() {
            this.hasUnsavedChanges = false;
            $('.fp-unsaved-indicator').remove();
        },

        /**
         * Validate form before submission
         */
        validateForm: function() {
            var isValid = true;
            var errors = [];
            
            // Validate time slots if schedule builder is present
            if (this.modules.scheduleBuilder && this.modules.scheduleBuilder.validateTimeSlots) {
                if (!this.modules.scheduleBuilder.validateTimeSlots()) {
                    isValid = false;
                    errors.push('Please fix time slot validation errors');
                }
            }
            
            // Show errors if any
            if (!isValid) {
                this.showUserFeedback('Please fix the following errors: ' + errors.join(', '), 'error');
            }
            
            return isValid;
        },

        /**
         * Show user feedback message
         */
        showUserFeedback: function(message, type = 'info', duration = 3000) {
            // Use error handler's feedback if available
            if (this.modules.errorHandler && this.modules.errorHandler.showUserFeedback) {
                this.modules.errorHandler.showUserFeedback(message, type, duration);
                return;
            }
            
            // Fallback implementation
            console.log('FP Esperienze: ' + message);
            
            // Simple notification
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notice);
            
            setTimeout(function() {
                $notice.fadeOut();
            }, duration);
        },

        /**
         * Handle initialization errors
         */
        handleInitializationError: function(error) {
            console.error('FP Esperienze: Initialization failed:', error);
            
            // Show user-friendly error
            this.showUserFeedback(
                'Failed to initialize FP Esperienze admin interface. Some features may not work correctly.',
                'error',
                8000
            );
        },

        /**
         * Update summary table (legacy compatibility)
         */
        updateSummaryTable: function() {
            // This method is called by legacy code and modules
            // Implementation would update any summary displays
            console.log('FP Esperienze: Summary table update requested');
        },

        /**
         * Toggle raw mode (legacy compatibility)
         */
        toggleRawMode: function(showRaw) {
            // Implementation for toggling between builder and raw modes
            if (showRaw) {
                $('.fp-schedule-builder').hide();
                $('.fp-raw-schedule').show();
            } else {
                $('.fp-schedule-builder').show();
                $('.fp-raw-schedule').hide();
            }
        },

        /**
         * Get module instance
         */
        getModule: function(moduleName) {
            return this.modules[moduleName] || null;
        },

        /**
         * Get performance summary
         */
        getPerformanceSummary: function() {
            if (this.modules.performance) {
                return this.modules.performance.getPerformanceSummary();
            }
            return null;
        },

        /**
         * Get error report
         */
        getErrorReport: function() {
            if (this.modules.errorHandler) {
                return this.modules.errorHandler.getErrorReport();
            }
            return null;
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Double-check initialization on DOM ready
        if (window.FPEsperienzeAdmin && window.FPEsperienzeAdmin.initialized) {
            return;
        }
        
        // Initialize admin functionality
        window.FPEsperienzeAdmin.init();
    });

    // Export for debugging
    window.FPAdmin = window.FPEsperienzeAdmin;

})(jQuery);