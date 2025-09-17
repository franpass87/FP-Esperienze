/**
 * UX Enhancer JavaScript for Frontend
 * Provides progressive loading, form validation, and better user interactions
 */

(function($) {
    'use strict';
    
    // Global UX object
    window.FPUXEnhancer = {
        initialized: false,
        loadingOverlay: null,
        notificationContainer: null,
        activeOverlayRequests: 0,
        
        /**
         * Initialize UX enhancements
         */
        init: function() {
            if (this.initialized) return;
            
            this.setupElements();
            this.activeOverlayRequests = 0;
            this.bindEvents();
            this.initFormValidation();
            this.initProgressiveLoading();
            this.initAccessibility();
            
            this.initialized = true;
            console.log('FP UX Enhancer initialized');
        },
        
        /**
         * Setup DOM elements
         */
        setupElements: function() {
            this.loadingOverlay = $('#fp-loading-overlay');
            this.notificationContainer = $('#fp-notification-container');
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;
            
            // AJAX loading indicators
            $(document).ajaxSend(function(event, jqXHR, settings) {
                var overlayEligible = self.isOverlayEligibleRequest(jqXHR, settings);

                jqXHR.fpOverlayEligible = overlayEligible;

                if (overlayEligible) {
                    self.activeOverlayRequests += 1;

                    if (self.activeOverlayRequests === 1) {
                        self.showLoading();
                    }
                }
            }).ajaxComplete(function(event, jqXHR) {
                if (!jqXHR.fpOverlayEligible) {
                    return;
                }

                self.activeOverlayRequests = Math.max(0, self.activeOverlayRequests - 1);

                if (self.activeOverlayRequests === 0) {
                    self.hideLoading();
                }
            });
            
            // Form submission enhancements
            $('form[data-fp-enhanced]').on('submit', function(e) {
                return self.handleFormSubmit(e, this);
            });
            
            // Retry button clicks
            $(document).on('click', '.fp-retry-button', function() {
                self.handleRetry($(this));
            });
            
            // Notification dismissal
            $(document).on('click', '.fp-notification-dismiss', function() {
                $(this).closest('.fp-notification').fadeOut();
            });
            
            // Auto-dismiss notifications
            setTimeout(function() {
                $('.fp-notification[data-auto-dismiss="true"]').fadeOut();
            }, 5000);
        },
        
        /**
         * Initialize form validation
         */
        initFormValidation: function() {
            var self = this;
            
            // Real-time validation for specific fields
            $('input[data-fp-validate]').on('blur', function() {
                self.validateField($(this));
            });
            
            // Email validation
            $('input[type="email"]').on('blur', function() {
                self.validateEmail($(this));
            });
            
            // Phone validation
            $('input[data-type="phone"]').on('blur', function() {
                self.validatePhone($(this));
            });
        },
        
        /**
         * Initialize progressive loading
         */
        initProgressiveLoading: function() {
            var self = this;
            
            // Load content progressively
            $('.fp-progressive-content').each(function() {
                var $element = $(this);
                var loadUrl = $element.data('load-url');
                
                if (loadUrl) {
                    self.loadProgressiveContent($element, loadUrl);
                }
            });
            
            // Lazy load images when they come into view
            if ('IntersectionObserver' in window) {
                var imageObserver = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var img = entry.target;
                            var src = img.dataset.src;
                            if (src) {
                                img.src = src;
                                img.classList.remove('fp-lazy');
                                imageObserver.unobserve(img);
                            }
                        }
                    });
                });
                
                $('.fp-lazy').each(function() {
                    imageObserver.observe(this);
                });
            }
        },
        
        /**
         * Initialize accessibility features
         */
        initAccessibility: function() {
            // Add skip links
            if (!$('.fp-skip-link').length) {
                $('body').prepend('<a href="#main" class="fp-skip-link">' + fpUX.i18n.skip_to_content + '</a>');
            }
            
            // Enhance focus management
            $(document).on('keydown', function(e) {
                if (e.key === 'Tab') {
                    $('body').addClass('fp-using-keyboard');
                }
            });
            
            $(document).on('mousedown', function() {
                $('body').removeClass('fp-using-keyboard');
            });
            
            // ARIA live regions for dynamic content
            if (!$('#fp-live-region').length) {
                $('body').append('<div id="fp-live-region" aria-live="polite" class="fp-sr-only"></div>');
            }
        },
        
        /**
         * Show loading overlay
         */
        showLoading: function(text) {
            if (this.loadingOverlay.length) {
                if (text) {
                    this.loadingOverlay.find('.fp-loading-text').text(text);
                }
                this.loadingOverlay.show();
            }
        },
        
        /**
         * Hide loading overlay
         */
        hideLoading: function() {
            if (this.loadingOverlay.length && this.activeOverlayRequests === 0) {
                this.loadingOverlay.hide();
            }
        },

        /**
         * Determine if the loading overlay should be shown for the request
         */
        isOverlayEligibleRequest: function(jqXHR, settings) {
            if (jqXHR && jqXHR.fpSkipOverlay) {
                return false;
            }

            if (settings && settings.fpSkipOverlay) {
                return false;
            }

            var requestUrl = '';

            if (settings && typeof settings.url === 'string') {
                requestUrl = settings.url;
            }

            if (requestUrl) {
                var normalizedUrl = requestUrl.toLowerCase();

                if (normalizedUrl.indexOf('fp-exp/v1/availability') !== -1) {
                    return false;
                }
            }

            return true;
        },
        
        /**
         * Show notification
         */
        showNotification: function(message, type, dismissible) {
            type = type || 'info';
            dismissible = dismissible !== false;
            
            var notification = $('<div class="fp-notification ' + type + '" data-auto-dismiss="' + dismissible + '">' +
                message +
                (dismissible ? '<button type="button" class="fp-notification-dismiss">&times;</button>' : '') +
                '</div>');
            
            this.notificationContainer.append(notification);
            
            // Auto-dismiss after 5 seconds
            if (dismissible) {
                setTimeout(function() {
                    notification.fadeOut();
                }, 5000);
            }
            
            // Announce to screen readers
            $('#fp-live-region').text(message);
        },
        
        /**
         * Validate form field
         */
        validateField: function($field) {
            var self = this;
            var fieldType = $field.data('fp-validate');
            var fieldValue = $field.val();
            
            if (!fieldType || !fieldValue) return;
            
            $.ajax({
                url: fpUX.ajax_url,
                type: 'POST',
                data: {
                    action: 'fp_validate_form_field',
                    nonce: fpUX.nonce,
                    field_type: fieldType,
                    field_value: fieldValue
                },
                success: function(response) {
                    self.updateFieldValidation($field, response);
                }
            });
        },
        
        /**
         * Update field validation display
         */
        updateFieldValidation: function($field, response) {
            $field.removeClass('fp-field-error fp-field-success');
            $field.siblings('.fp-field-message').remove();
            
            var messageClass = response.success ? 'success' : 'error';
            var fieldClass = response.data ? response.data.field_class : '';
            var message = response.data ? response.data.message : '';
            
            if (fieldClass) {
                $field.addClass(fieldClass);
            }
            
            if (message) {
                $field.after('<span class="fp-field-message ' + messageClass + '">' + message + '</span>');
            }
        },
        
        /**
         * Validate email field
         */
        validateEmail: function($field) {
            var email = $field.val();
            var isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            
            $field.removeClass('fp-field-error fp-field-success');
            $field.siblings('.fp-field-message').remove();
            
            if (email && !isValid) {
                $field.addClass('fp-field-error');
                $field.after('<span class="fp-field-message error">' + fpUX.i18n.invalid_email + '</span>');
            } else if (email && isValid) {
                $field.addClass('fp-field-success');
            }
        },
        
        /**
         * Validate phone field
         */
        validatePhone: function($field) {
            var phone = $field.val().replace(/[\s\-\(\)]/g, '');
            var isValid = /^[\+]?[1-9][\d]{0,15}$/.test(phone);
            
            $field.removeClass('fp-field-error fp-field-success');
            $field.siblings('.fp-field-message').remove();
            
            if (phone && !isValid) {
                $field.addClass('fp-field-error');
                $field.after('<span class="fp-field-message error">' + fpUX.i18n.invalid_phone + '</span>');
            } else if (phone && isValid) {
                $field.addClass('fp-field-success');
            }
        },
        
        /**
         * Handle form submission
         */
        handleFormSubmit: function(e, form) {
            var $form = $(form);
            var hasErrors = $form.find('.fp-field-error').length > 0;
            
            if (hasErrors) {
                e.preventDefault();
                this.showNotification(fpUX.i18n.validation_error, 'error');
                $form.find('.fp-field-error').first().focus();
                return false;
            }
            
            this.showLoading(fpUX.i18n.processing);
            return true;
        },
        
        /**
         * Handle retry button clicks
         */
        handleRetry: function($button) {
            var action = $button.data('action');
            if (action && typeof window[action] === 'function') {
                window[action]();
            }
        },
        
        /**
         * Load progressive content
         */
        loadProgressiveContent: function($element, url) {
            var self = this;
            
            $element.html('<div class="fp-loading-placeholder">' + fpUX.i18n.loading + '</div>');
            
            $.ajax({
                url: url,
                type: 'GET',
                success: function(response) {
                    $element.html(response);
                    $element.trigger('fp:content-loaded');
                },
                error: function() {
                    $element.html('<div class="fp-error-placeholder">' + 
                        fpUX.i18n.error + 
                        ' <button type="button" class="fp-retry-button" onclick="FPUXEnhancer.loadProgressiveContent($(this).closest(\'.fp-progressive-content\'), \'' + url + '\')">' + 
                        fpUX.i18n.retry + 
                        '</button></div>');
                }
            });
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        FPUXEnhancer.init();
    });
    
})(jQuery);