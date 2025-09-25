
/**
 * FP Esperienze Booking Widget
 * GetYourGuide-style functionality
 */

(function($) {
    'use strict';

    var i18n = (typeof window !== 'undefined' && window.wp && window.wp.i18n) ? window.wp.i18n : null;
    var __ = (i18n && typeof i18n.__ === 'function') ? i18n.__ : function(text) {
        return text;
    };
    var sprintf = (i18n && typeof i18n.sprintf === 'function') ? i18n.sprintf : function(template) {
        var args = Array.prototype.slice.call(arguments, 1);
        var autoIndex = 0;

        return template.replace(/%((\d+)\$)?[sd]/g, function(match, position, explicitIndex) {
            var index;

            if (explicitIndex) {
                index = parseInt(explicitIndex, 10) - 1;
            } else {
                index = autoIndex++;
            }

            if (index < 0 || index >= args.length) {
                return '';
            }

            var value = args[index];
            return value === null || typeof value === 'undefined' ? '' : value;
        });
    };

    $(document).ready(function() {
        // Initialize booking widget functionality
        FPBookingWidget.init();
    });

    window.FPBookingWidget = {
        
        // Widget state
        selectedSlot: null,
        selectedDate: null,
        adultPrice: 0,
        childPrice: 0,
        capacity: 10,
        
        /**
         * Initialize widget
         */
        init: function() {
            // Only init on experience single pages
            if (!$('#fp-booking-widget').length) {
                console.log('FP Booking Widget: No booking widget container found');
                return;
            }

            console.log('FP Booking Widget: Initializing...');
            
            try {
                this.bindEvents();
                this.initStickyWidget();
                this.initAccessibility();
                this.initData();
                this.updateTotal();
                
                console.log('FP Booking Widget: Successfully initialized');
                
                // Add debug information to console
                if (typeof fp_booking_widget_i18n !== 'undefined') {
                    console.log('FP Booking Widget: Localization data available');
                } else {
                    console.warn('FP Booking Widget: Localization data missing');
                }
                
            } catch (error) {
                console.error('FP Booking Widget: Initialization failed:', error);
                this.showError('Booking widget failed to initialize. Please refresh the page.');
            }
        },

        /**
         * Initialize widget data
         */
        initData: function() {
            this.adultPrice = parseFloat($('#fp-adult-price').val()) || 0;
            this.childPrice = parseFloat($('#fp-child-price').val()) || 0;
            this.capacity = parseInt($('#fp-capacity').val()) || 10;
        },

        /**
         * Initialize sticky widget behavior
         */
        initStickyWidget: function() {
            var $widget = $('#fp-booking-widget');
            var $stickyNotice = $('.fp-sticky-notice');

            if (!$stickyNotice.length) {
                return;
            }

            // Simple debounce to improve scroll performance
            var debounce = function(func, wait) {
                var timeout;
                return function() {
                    var context = this, args = arguments;
                    clearTimeout(timeout);
                    timeout = setTimeout(function() {
                        func.apply(context, args);
                    }, wait);
                };
            };

            var isMobileView = function() {
                return window.matchMedia('(max-width: 768px)').matches;
            };

            var toggleStickyForDesktop = function() {
                if (!$widget.length) {
                    $stickyNotice.removeClass('fp-sticky-visible');
                    return;
                }

                var widgetTop = $widget.offset().top;
                var widgetBottom = widgetTop + $widget.outerHeight();
                var scrollTop = $(window).scrollTop();
                var windowHeight = $(window).height();
                var viewportBottom = scrollTop + windowHeight;

                if (viewportBottom < widgetTop || scrollTop > widgetBottom) {
                    $stickyNotice.addClass('fp-sticky-visible');
                } else {
                    $stickyNotice.removeClass('fp-sticky-visible');
                }
            };

            var updateStickyVisibility = function() {
                if (isMobileView()) {
                    $stickyNotice.addClass('fp-sticky-visible fp-sticky-mobile-active');
                    return;
                }

                $stickyNotice.removeClass('fp-sticky-mobile-active');
                toggleStickyForDesktop();
            };

            if ($widget.length) {
                $(window).on('scroll', debounce(function() {
                    if (!isMobileView()) {
                        toggleStickyForDesktop();
                    }
                }, 100));
            }

            $(window).on('resize orientationchange', debounce(updateStickyVisibility, 150));

            updateStickyVisibility();

            $('.fp-show-booking').on('click', function(event) {
                event.preventDefault();

                if (!$widget.length) {
                    return;
                }

                var targetOffset = $widget.offset().top;
                $('html, body').animate({ scrollTop: Math.max(targetOffset - 20, 0) }, 400);
            });
        },

        /**
         * Initialize accessibility features
         */
        initAccessibility: function() {
            // FAQ accordion
            $('.fp-faq-question').on('click', function() {
                var $button = $(this);
                var $answer = $button.next('.fp-faq-answer');
                var isExpanded = $button.attr('aria-expanded') === 'true';
                
                // Close all other FAQ items
                $('.fp-faq-question').attr('aria-expanded', 'false');
                $('.fp-faq-answer').attr('hidden', true);
                $('.fp-faq-icon').text('+');
                
                // Toggle current item
                if (!isExpanded) {
                    $button.attr('aria-expanded', 'true');
                    $answer.removeAttr('hidden');
                    $button.find('.fp-faq-icon').text('−');
                }
            });

            // Keyboard navigation for time slots
            $(document).on('keydown', '.fp-time-slot', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).click();
                }
            });

            // Enhanced FAQ keyboard navigation
            $(document).on('keydown', '.fp-faq-question', function(e) {
                var $faqButtons = $('.fp-faq-question');
                var currentIndex = $faqButtons.index(this);
                
                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        var nextIndex = (currentIndex + 1) % $faqButtons.length;
                        $faqButtons.eq(nextIndex).focus();
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        var prevIndex = currentIndex === 0 ? $faqButtons.length - 1 : currentIndex - 1;
                        $faqButtons.eq(prevIndex).focus();
                        break;
                    case 'Home':
                        e.preventDefault();
                        $faqButtons.first().focus();
                        break;
                    case 'End':
                        e.preventDefault();
                        $faqButtons.last().focus();
                        break;
                }
            });
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;

            // Date picker
            $('#fp-date-picker').on('change', function() {
                self.selectedDate = $(this).val();
                if (self.selectedDate) {
                    self.loadAvailability(self.selectedDate);
                }
            });

            // Quantity controls
            $(document).on('click', '.fp-qty-btn', function() {
                var $target = $('#' + $(this).data('target'));
                var currentVal = parseInt($target.val()) || 0;
                var isPlus = $(this).hasClass('fp-qty-plus');
                var max = parseInt($target.attr('max')) || self.capacity;
                
                if (isPlus && currentVal < max) {
                    $target.val(currentVal + 1);
                } else if (!isPlus && currentVal > 0) {
                    $target.val(currentVal - 1);
                }
                
                self.updateTotal();
                self.validateForm();
            });

            // Time slot selection with GA4 event
            $(document).on('click', '.fp-time-slot:not(.unavailable)', function() {
                $('.fp-time-slot').removeClass('selected').attr('aria-checked', 'false');
                $(this).addClass('selected').attr('aria-checked', 'true');

                self.adultPrice = parseFloat($(this).data('adult-price')) || 0;
                self.childPrice = parseFloat($(this).data('child-price')) || 0;

                var meetingPointId = $(this).attr('data-meeting-point');
                if (typeof meetingPointId === 'undefined' || meetingPointId === null) {
                    meetingPointId = '';
                }

                self.selectedSlot = {
                    start_time: $(this).data('start-time'),
                    adult_price: $(this).data('adult-price'),
                    child_price: $(this).data('child-price'),
                    available: $(this).data('available'),
                    meeting_point_id: meetingPointId !== '' ? meetingPointId : null
                };

                $('#fp-meeting-point-id').val(meetingPointId);
                $('#fp-selected-slot').val(self.selectedDate + ' ' + self.selectedSlot.start_time);

                // GA4 select_item event
                self.trackSlotSelection();

                // Update social proof
                self.updateSocialProof();

                self.updateTotal();
                self.validateForm();
            });

            // Extras handlers
            $(document).on('change', '.fp-extra-toggle', function() {
                var $extra = $(this).closest('.fp-extra-item');
                var $quantityDiv = $extra.find('.fp-extra-quantity');
                var $quantityInput = $extra.find('.fp-extra-qty-input');
                
                if ($(this).is(':checked')) {
                    $quantityDiv.removeClass('fp-extra-quantity-hidden');
                    $quantityInput.val(1);
                } else {
                    $quantityDiv.addClass('fp-extra-quantity-hidden');
                    $quantityInput.val(0);
                }
                
                self.updateTotal();
                self.validateForm();
            });
            
            $(document).on('click', '.fp-extra-qty-plus, .fp-extra-qty-minus', function() {
                var $input = $(this).siblings('.fp-extra-qty-input');
                var currentVal = parseInt($input.val()) || 0;
                var isPlus = $(this).hasClass('fp-extra-qty-plus');
                var max = parseInt($input.attr('max')) || 99;
                var min = parseInt($input.attr('min')) || 0;
                
                if (isPlus && currentVal < max) {
                    $input.val(currentVal + 1);
                } else if (!isPlus && currentVal > min) {
                    $input.val(currentVal - 1);
                }
                
                self.updateTotal();
                self.validateForm();
            });

            // Gift form toggle
            $('#fp-gift-toggle').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#fp-gift-form').slideDown(300);
                } else {
                    $('#fp-gift-form').slideUp(300);
                }
                self.validateForm();
            });
            
            // Gift form validation
            $('.fp-required-field').on('input blur', function() {
                self.validateForm();
            });

            // Add to cart
            $('#fp-add-to-cart').on('click', function() {
                self.addToCart();
            });
        },

        /**
         * Load availability for selected date
         */
        loadAvailability: function(date) {
            var self = this;
            var productId = $('#fp-product-id').val();
            
            // Show loading state
            $('#fp-loading').show();
            $('#fp-time-slots').html('<p class="fp-slots-placeholder">' + __('Loading available times...', 'fp-esperienze') + '</p>');
            $('#fp-error-messages').empty();
            
            // Construct REST URL - try from localized data first, then fallback
            var restUrl = '/wp-json/fp-exp/v1/availability';
            if (typeof fp_booking_widget_i18n !== 'undefined' && fp_booking_widget_i18n.rest_url) {
                restUrl = fp_booking_widget_i18n.rest_url + 'availability';
            } else if (typeof fp_esperienze_params !== 'undefined' && fp_esperienze_params.rest_url) {
                restUrl = fp_esperienze_params.rest_url + 'fp-exp/v1/availability';
            }
            
            $.ajax({
                url: restUrl,
                method: 'GET',
                data: {
                    product_id: productId,
                    date: date
                },
                beforeSend: function(xhr) {
                    // Add nonce for authentication if available
                    if (typeof fp_booking_widget_i18n !== 'undefined' && fp_booking_widget_i18n.nonce) {
                        xhr.setRequestHeader('X-WP-Nonce', fp_booking_widget_i18n.nonce);
                    }
                },
                success: function(response) {
                    if (response && response.slots) {
                        self.displayTimeSlots(response.slots);
                    } else {
                        self.showError(__('Invalid response format from server.', 'fp-esperienze'));
                    }
                    $('#fp-loading').hide();
                },
                error: function(xhr, status, error) {
                    var errorMsg = (typeof fp_booking_widget_i18n !== 'undefined') 
                        ? fp_booking_widget_i18n.error_failed_load_availability
                        : __('Failed to load availability.', 'fp-esperienze');
                        
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    
                    console.error('FP Esperienze: Availability load error:', {
                        status: status,
                        error: error,
                        response: xhr.responseJSON,
                        url: restUrl
                    });
                    
                    self.showError(errorMsg);
                    $('#fp-loading').hide();
                }
            });
        },

        /**
         * Display time slots
         */
        displayTimeSlots: function(slots) {
            var html = '';
            
            if (!slots || slots.length === 0) {
                html = '<p class="fp-no-slots">' + __('No availability for this date.', 'fp-esperienze') + '</p>';
            } else {
                slots.forEach(function(slot) {
                    var availableClass = slot.is_available ? 'available' : 'unavailable';
                    var availableText = slot.is_available ?
                        slot.available + ' ' + __('spots left', 'fp-esperienze') :
                        __('Sold out', 'fp-esperienze');
                    var availableLabel = slot.is_available ?
                        sprintf(__('%d spots available', 'fp-esperienze'), slot.available) :
                        __('sold out', 'fp-esperienze');
                    var availableColorClass = slot.is_available ? 'fp-slot-available' : 'fp-slot-unavailable';
                    
                    html += '<div class="fp-time-slot ' + availableClass + '" ' +
                           'data-start-time="' + slot.start_time + '" ' +
                           'data-adult-price="' + slot.adult_price + '" ' +
                           'data-child-price="' + slot.child_price + '" ' +
                           'data-meeting-point="' + ((typeof slot.meeting_point_id !== 'undefined' && slot.meeting_point_id !== null) ? slot.meeting_point_id : '') + '" ' +
                           'data-available="' + slot.available + '" ' +
                           'role="radio" ' +
                           'tabindex="0" ' +
                           'aria-checked="false" ' +
                           'aria-label="' + sprintf(__('Time slot %1$s to %2$s, %3$s, price from €%4$s', 'fp-esperienze'), slot.start_time, slot.end_time, availableLabel, slot.adult_price) + '">' +
                           '<div class="fp-slot-time">' + slot.start_time + ' - ' + slot.end_time + '</div>' +
                           '<div class="fp-slot-info">' +
                           '<div class="fp-slot-price">' + sprintf(__('From €%s', 'fp-esperienze'), slot.adult_price) + '</div>' +
                           '<div class="' + availableColorClass + '">' + availableText + '</div>' +
                           '</div>' +
                           '</div>';
                });
            }
            
            $('#fp-time-slots').html(html);
            this.selectedSlot = null;
            this.adultPrice = 0;
            this.childPrice = 0;
            $('#fp-selected-slot').val('');
            $('#fp-meeting-point-id').val('');
            this.updateTotal();
            this.validateForm();
        },

        /**
         * Update social proof based on availability
         */
        updateSocialProof: function() {
            var $socialProof = $('#fp-social-proof');
            
            if (this.selectedSlot && this.selectedSlot.available <= 5 && this.selectedSlot.available > 0) {
                var message = this.selectedSlot.available === 1 ?
                    __('Only 1 spot left!', 'fp-esperienze') :
                    sprintf(__('Only %d spots left!', 'fp-esperienze'), this.selectedSlot.available);
                
                $socialProof.find('.fp-urgency-text').text(message);
                $socialProof.show();
            } else {
                $socialProof.hide();
            }
        },

        /**
         * Track slot selection for GA4
         */
        trackSlotSelection: function() {
            if (typeof window.FPTracking !== 'undefined' && this.selectedSlot) {
                $(document).trigger('fp_track_select_item', {
                    product_id: $('#fp-product-id').val(),
                    product_name: document.title,
                    price: this.selectedSlot.adult_price,
                    slot_start: this.selectedSlot.start_time,
                    meeting_point_id: $('#fp-meeting-point-id').val() || null,
                    lang: $('#fp-language').val() || null
                });
            }
        },

        /**
         * Update total price
         */
        updateTotal: function() {
            var adultQty = parseInt($('#fp-qty-adult').val()) || 0;
            var childQty = parseInt($('#fp-qty-child').val()) || 0;
            var totalParticipants = adultQty + childQty;
            
            var adultTotal = adultQty * this.adultPrice;
            var childTotal = childQty * this.childPrice;
            var baseTotal = adultTotal + childTotal;
            
            var detailsHtml = '';
            if (adultQty > 0) {
                var adultLabel = adultQty === 1 ? __('Adult', 'fp-esperienze') : __('Adults', 'fp-esperienze');
                detailsHtml += '<div>' + adultQty + ' ' + adultLabel + ': €' + adultTotal.toFixed(2) + '</div>';
            }
            if (childQty > 0) {
                var childLabel = childQty === 1 ? __('Child', 'fp-esperienze') : __('Children', 'fp-esperienze');
                detailsHtml += '<div>' + childQty + ' ' + childLabel + ': €' + childTotal.toFixed(2) + '</div>';
            }
            
            // Calculate extras
            var extrasTotal = 0;
            $('.fp-extra-item').each(function() {
                var $extra = $(this);
                var extraQty = parseInt($extra.find('.fp-extra-qty-input').val()) || 0;
                
                if (extraQty > 0) {
                    var extraPrice = parseFloat($extra.data('price')) || 0;
                    var billingType = $extra.data('billing-type');
                    var extraName = $extra.find('strong').first().text();
                    
                    var extraItemTotal = 0;
                    if (billingType === 'per_person') {
                        extraItemTotal = extraPrice * extraQty * totalParticipants;
                        detailsHtml += '<div>' + extraName + ' (' + extraQty + ' × ' + totalParticipants + ' ' + __('people', 'fp-esperienze') + '): €' + extraItemTotal.toFixed(2) + '</div>';
                    } else {
                        extraItemTotal = extraPrice * extraQty;
                        detailsHtml += '<div>' + extraName + ' (' + extraQty + '): €' + extraItemTotal.toFixed(2) + '</div>';
                    }
                    
                    extrasTotal += extraItemTotal;
                }
            });
            
            var total = baseTotal + extrasTotal;
            
            $('#fp-price-details').html(detailsHtml);
            $('#fp-total-amount').text('€' + total.toFixed(2));
            
            // Update sticky notice price
            $('.fp-sticky-notice .fp-amount').text('€' + (this.adultPrice || 0).toFixed(2));
        },

        /**
         * Validate form
         */
        validateForm: function() {
            var isValid = true;
            var adultQty = parseInt($('#fp-qty-adult').val()) || 0;
            var childQty = parseInt($('#fp-qty-child').val()) || 0;
            
            // Check if date and slot are selected
            if (!this.selectedDate || !this.selectedSlot) {
                isValid = false;
            }
            
            // Check if at least one participant is selected
            if (adultQty === 0 && childQty === 0) {
                isValid = false;
            }
            
            // Check capacity
            if (this.selectedSlot && (adultQty + childQty) > this.selectedSlot.available) {
                isValid = false;
            }
            
            // Check required extras
            $('.fp-extra-item').each(function() {
                var $extra = $(this);
                var isRequired = $extra.data('is-required') === 1;
                var extraQty = parseInt($extra.find('.fp-extra-qty-input').val()) || 0;
                
                if (isRequired && extraQty === 0) {
                    isValid = false;
                }
            });
            
            // Check gift form validation if gift is selected
            if ($('#fp-gift-toggle').is(':checked')) {
                var recipientName = $('#fp-gift-recipient-name').val().trim();
                var recipientEmail = $('#fp-gift-recipient-email').val().trim();
                
                if (!recipientName || !recipientEmail) {
                    isValid = false;
                }
                
                // Basic email validation
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (recipientEmail && !emailRegex.test(recipientEmail)) {
                    isValid = false;
                }
            }
            
            $('#fp-add-to-cart').prop('disabled', !isValid);
            
            // Update button text and help text
            if (isValid) {
                $('#fp-cart-help').text(__('Ready to book this experience', 'fp-esperienze'));
            } else if (!this.selectedDate) {
                $('#fp-cart-help').text(__('Select a date to continue', 'fp-esperienze'));
            } else if (!this.selectedSlot) {
                $('#fp-cart-help').text(__('Select a time slot to continue', 'fp-esperienze'));
            } else if (adultQty === 0 && childQty === 0) {
                $('#fp-cart-help').text(__('Select at least one participant', 'fp-esperienze'));
            } else {
                $('#fp-cart-help').text(__('Complete all required fields', 'fp-esperienze'));
            }
        },

        /**
         * Add to cart
         */
        addToCart: function() {
            var self = this;
            var productId = $('#fp-product-id').val();
            var slotStart = $('#fp-selected-slot').val();
            var language = $('#fp-language').val();
            var adultQty = parseInt($('#fp-qty-adult').val()) || 0;
            var childQty = parseInt($('#fp-qty-child').val()) || 0;

            $('#fp-add-to-cart').prop('disabled', true).text(__('Adding...', 'fp-esperienze'));
            
            // Collect extras data
            var extras = {};
            $('.fp-extra-item').each(function() {
                var $extra = $(this);
                var extraId = $extra.data('extra-id');
                var extraQty = parseInt($extra.find('.fp-extra-qty-input').val()) || 0;
                
                if (extraQty > 0) {
                    extras[extraId] = extraQty;
                }
            });
            
            // Use existing cart functionality from frontend.js
            if (window.FPEsperienze && typeof window.FPEsperienze.addToCart === 'function') {
                // Set the legacy widget state to match our state
                window.FPEsperienze.selectedSlot = self.selectedSlot;
                window.FPEsperienze.selectedDate = self.selectedDate;
                window.FPEsperienze.addToCart();
            } else {
                // Fallback: redirect to shop with error
                self.showError(fp_booking_widget_i18n.error_booking_unavailable || __('Booking system temporarily unavailable. Please try again.', 'fp-esperienze'));
                $('#fp-add-to-cart').prop('disabled', false).text(__('Add to Cart', 'fp-esperienze'));
            }
        },

        /**
         * Show error message
         */
        showError: function(message) {
            var $errorContainer = $('#fp-error-messages');
            $errorContainer.html('<div class="fp-error-message">' + message + '</div>');
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $errorContainer.empty();
            }, 5000);
        }
    };

})(jQuery);