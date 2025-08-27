/**
 * FP Esperienze Frontend JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize frontend functionality
        FPEsperienze.init();
    });

    window.FPEsperienze = {
        
        // Booking widget state
        selectedSlot: null,
        selectedDate: null,
        adultPrice: 0,
        childPrice: 0,
        capacity: 10,
        
        /**
         * Initialize
         */
        init: function() {
            // Check if the new booking widget is present
            if ($('#fp-booking-widget').length && typeof window.FPBookingWidget !== 'undefined') {
                // New GetYourGuide-style widget is present, only init general frontend features
                this.bindGeneralEvents();
            } else {
                // Legacy mode - init everything
                this.bindEvents();
                this.initBookingWidget();
            }
        },

        /**
         * General events that don't conflict with booking widget
         */
        bindGeneralEvents: function() {
            // Experience card hover effects
            $('.fp-experience-card').hover(
                function() {
                    $(this).addClass('hovered');
                },
                function() {
                    $(this).removeClass('hovered');
                }
            );

            // Smooth scroll for anchor links
            $('a[href^="#"]').on('click', function(e) {
                var target = $(this.getAttribute('href'));
                if (target.length) {
                    e.preventDefault();
                    $('html, body').animate({
                        scrollTop: target.offset().top - 80
                    }, 600);
                }
            });
        },

        /**
         * Initialize booking widget
         */
        initBookingWidget: function() {
            if (!$('#fp-date-picker').length) {
                return; // Not on single experience page
            }

            // Get data from hidden fields
            this.adultPrice = parseFloat($('#fp-adult-price').val()) || 0;
            this.childPrice = parseFloat($('#fp-child-price').val()) || 0;
            this.capacity = parseInt($('#fp-capacity').val()) || 10;

            this.updateTotal();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;

            // Experience card hover effects
            $('.fp-experience-card').hover(
                function() {
                    $(this).addClass('hovered');
                },
                function() {
                    $(this).removeClass('hovered');
                }
            );

            // Smooth scroll for anchor links
            $('a[href^="#"]').on('click', function(e) {
                var target = $(this.getAttribute('href'));
                if (target.length) {
                    e.preventDefault();
                    $('html, body').animate({
                        scrollTop: target.offset().top - 80
                    }, 600);
                }
            });

            // Booking widget events
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

            // Time slot selection
            $(document).on('click', '.fp-time-slot:not(.unavailable)', function() {
                $('.fp-time-slot').removeClass('selected');
                $(this).addClass('selected');
                
                self.selectedSlot = {
                    start_time: $(this).data('start-time'),
                    adult_price: $(this).data('adult-price'),
                    child_price: $(this).data('child-price'),
                    available: $(this).data('available')
                };
                
                $('#fp-selected-slot').val(self.selectedDate + ' ' + self.selectedSlot.start_time);
                self.updateTotal();
                self.validateForm();
            });

            // Add to cart
            $('#fp-add-to-cart').on('click', function() {
                self.addToCart();
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
                var max = parseInt($input.attr('max')) || 1;
                var min = parseInt($input.attr('min')) || 0;
                
                if (isPlus && currentVal < max) {
                    $input.val(currentVal + 1);
                } else if (!isPlus && currentVal > min) {
                    $input.val(currentVal - 1);
                }
                
                // Update checkbox state if quantity goes to 0
                var $checkbox = $(this).closest('.fp-extra-item').find('.fp-extra-toggle');
                if (parseInt($input.val()) > 0) {
                    $checkbox.prop('checked', true);
                    $(this).closest('.fp-extra-item').find('.fp-extra-quantity').removeClass('fp-extra-quantity-hidden');
                } else {
                    $checkbox.prop('checked', false);
                    $(this).closest('.fp-extra-item').find('.fp-extra-quantity').addClass('fp-extra-quantity-hidden');
                }
                
                self.updateTotal();
                self.validateForm();
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
            
            // Build the full REST URL
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
                },
                error: function(xhr, status, error) {
                    var errorMsg = 'Failed to load availability.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    } else if (status === 'timeout') {
                        errorMsg = 'Request timed out. Please try again.';
                    } else if (status === 'error') {
                        errorMsg = 'Network error. Please check your connection.';
                    }
                    self.showError(errorMsg);
                    $('#fp-time-slots').html('<p class="fp-slots-placeholder">' + errorMsg + '</p>');
                },
                complete: function() {
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
                html = '<p class="fp-slots-placeholder">No available times for this date.</p>';
            } else {
                slots.forEach(function(slot) {
                    var availableClass = slot.is_available ? '' : 'unavailable';
                    var availableText = slot.is_available ? 
                        slot.available + ' spots left' : 
                        'Fully booked';
                    var availableColorClass = slot.is_available ? 'fp-slot-available' : 'fp-slot-unavailable';
                    
                    html += '<div class="fp-time-slot ' + availableClass + '" ' +
                           'data-start-time="' + slot.start_time + '" ' +
                           'data-adult-price="' + slot.adult_price + '" ' +
                           'data-child-price="' + slot.child_price + '" ' +
                           'data-available="' + slot.available + '">' +
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
                var isRequired = $extra.data('is-required') == '1';
                var extraQty = parseInt($extra.find('.fp-extra-qty-input').val()) || 0;
                
                if (isRequired && extraQty === 0) {
                    isValid = false;
                }
            });
            
            $('#fp-add-to-cart').prop('disabled', !isValid);
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
            
            // Create form data
            var formData = new FormData();
            formData.append('add-to-cart', productId);
            formData.append('quantity', 1); // We handle quantity in our custom fields
            formData.append('fp_slot_start', slotStart);
            formData.append('fp_meeting_point_id', meetingPointId);
            formData.append('fp_lang', language);
            formData.append('fp_qty_adult', adultQty);
            formData.append('fp_qty_child', childQty);
            formData.append('fp_extras', JSON.stringify(extras));
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                if (response.ok) {
                    // Redirect to cart or show success message
                    var cartUrl = (typeof fp_esperienze_params !== 'undefined' && fp_esperienze_params.cart_url) 
                        ? fp_esperienze_params.cart_url 
                        : '/cart';
                    window.location.href = cartUrl;
                } else {
                    throw new Error('Failed to add to cart');
                }
            })
            .catch(function(error) {
                self.showError('Failed to add to cart. Please try again.');
                $('#fp-add-to-cart').prop('disabled', false).text('Add to Cart');
            });
        },

        /**
         * Show error message
         */
        showError: function(message) {
            var errorHtml = '<div class="fp-error-message">' + message + '</div>';
            $('#fp-error-messages').html(errorHtml);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $('#fp-error-messages').fadeOut();
            }, 5000);
        }
    };

})(jQuery);