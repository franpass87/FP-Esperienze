/**
 * FP Esperienze Booking Widget
 * GetYourGuide-style functionality
 */

(function($) {
    'use strict';

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
                return;
            }

            this.bindEvents();
            this.initStickyWidget();
            this.initAccessibility();
            this.initData();
            this.updateTotal();
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
            
            if ($widget.length && $stickyNotice.length) {
                $(window).on('scroll', function() {
                    var widgetTop = $widget.offset().top;
                    var scrollTop = $(window).scrollTop();
                    var windowHeight = $(window).height();
                    
                    // Show sticky notice on mobile when widget is not visible
                    if (scrollTop + windowHeight < widgetTop || scrollTop > widgetTop + $widget.height()) {
                        $stickyNotice.addClass('fp-sticky-visible');
                    } else {
                        $stickyNotice.removeClass('fp-sticky-visible');
                    }
                });
            }
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
                
                self.selectedSlot = {
                    start_time: $(this).data('start-time'),
                    adult_price: $(this).data('adult-price'),
                    child_price: $(this).data('child-price'),
                    available: $(this).data('available')
                };
                
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
            
            $('#fp-loading').show();
            $('#fp-time-slots').html('<p class="fp-slots-placeholder">' + 'Loading available times...' + '</p>');
            $('#fp-error-messages').empty();
            
            var restUrl = (typeof fp_esperienze_params !== 'undefined' && fp_esperienze_params.rest_url) 
                ? fp_esperienze_params.rest_url + 'fp-exp/v1/availability'
                : '/wp-json/fp-exp/v1/availability';
            
            $.ajax({
                url: restUrl,
                method: 'GET',
                data: {
                    product_id: productId,
                    date: date
                },
                success: function(response) {
                    self.displayTimeSlots(response.slots);
                    $('#fp-loading').hide();
                },
                error: function(xhr, status, error) {
                    var errorMsg = 'Failed to load availability.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    
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
                html = '<p class="fp-no-slots">No availability for this date.</p>';
            } else {
                slots.forEach(function(slot) {
                    var availableClass = slot.is_available ? 'available' : 'unavailable';
                    var availableText = slot.is_available ? 
                        slot.available + ' spots left' : 
                        'Sold out';
                    var availableColorClass = slot.is_available ? 'fp-slot-available' : 'fp-slot-unavailable';
                    
                    html += '<div class="fp-time-slot ' + availableClass + '" ' +
                           'data-start-time="' + slot.start_time + '" ' +
                           'data-adult-price="' + slot.adult_price + '" ' +
                           'data-child-price="' + slot.child_price + '" ' +
                           'data-available="' + slot.available + '" ' +
                           'role="radio" ' +
                           'tabindex="0" ' +
                           'aria-checked="false">' +
                           '<div class="fp-slot-time">' + slot.start_time + ' - ' + slot.end_time + '</div>' +
                           '<div class="fp-slot-info">' +
                           '<div class="fp-slot-price">From €' + slot.adult_price + '</div>' +
                           '<div class="' + availableColorClass + '">' + availableText + '</div>' +
                           '</div>' +
                           '</div>';
                });
            }
            
            $('#fp-time-slots').html(html);
            this.selectedSlot = null;
            $('#fp-selected-slot').val('');
            this.validateForm();
        },

        /**
         * Update social proof based on availability
         */
        updateSocialProof: function() {
            var $socialProof = $('#fp-social-proof');
            
            if (this.selectedSlot && this.selectedSlot.available <= 5 && this.selectedSlot.available > 0) {
                var message = this.selectedSlot.available === 1 ? 
                    'Only 1 spot left!' : 
                    'Only ' + this.selectedSlot.available + ' spots left!';
                
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
            if (typeof window.dataLayer !== 'undefined') {
                window.dataLayer.push({
                    'event': 'select_item',
                    'item_list_name': 'Available Time Slots',
                    'items': [{
                        'item_id': $('#fp-product-id').val(),
                        'item_name': document.title,
                        'item_category': 'Experience',
                        'item_variant': this.selectedSlot.start_time,
                        'price': this.selectedSlot.adult_price
                    }]
                });
            }
            // TODO: Implement actual GA4 tracking in Milestone D
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
                detailsHtml += '<div>' + adultQty + ' Adult' + (adultQty > 1 ? 's' : '') + ': €' + adultTotal.toFixed(2) + '</div>';
            }
            if (childQty > 0) {
                detailsHtml += '<div>' + childQty + ' Child' + (childQty > 1 ? 'ren' : '') + ': €' + childTotal.toFixed(2) + '</div>';
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
                        detailsHtml += '<div>' + extraName + ' (' + extraQty + ' × ' + totalParticipants + ' people): €' + extraItemTotal.toFixed(2) + '</div>';
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
            
            $('#fp-add-to-cart').prop('disabled', !isValid);
            
            // Update button text and help text
            if (isValid) {
                $('#fp-cart-help').text('Ready to book this experience');
            } else if (!this.selectedDate) {
                $('#fp-cart-help').text('Select a date to continue');
            } else if (!this.selectedSlot) {
                $('#fp-cart-help').text('Select a time slot to continue');
            } else if (adultQty === 0 && childQty === 0) {
                $('#fp-cart-help').text('Select at least one participant');
            } else {
                $('#fp-cart-help').text('Complete all required fields');
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
            var meetingPointId = $('#fp-meeting-point-id').val() || 1;
            
            $('#fp-add-to-cart').prop('disabled', true).text('Adding...');
            
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
            if (window.FPEsperienze && window.FPEsperienze.addToCart) {
                window.FPEsperienze.addToCart();
            } else {
                // Fallback: redirect to shop with error
                self.showError('Booking system temporarily unavailable. Please try again.');
                $('#fp-add-to-cart').prop('disabled', false).text('Add to Cart');
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