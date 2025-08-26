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
            this.bindEvents();
            this.initBookingWidget();
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
        },

        /**
         * Load availability for selected date
         */
        loadAvailability: function(date) {
            var self = this;
            var productId = $('#fp-product-id').val();
            
            $('#fp-loading').show();
            $('#fp-time-slots').html('<p class="fp-slots-placeholder">' + 'Loading available times...' + '</p>');
            
            $.ajax({
                url: '/wp-json/fp-exp/v1/availability',
                method: 'GET',
                data: {
                    product_id: productId,
                    date: date
                },
                success: function(response) {
                    self.displayTimeSlots(response.slots);
                },
                error: function(xhr) {
                    var errorMsg = 'Failed to load availability.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
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
                           '<div class="fp-slot-price">From $' + slot.adult_price + '</div>' +
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
            
            var adultTotal = adultQty * this.adultPrice;
            var childTotal = childQty * this.childPrice;
            var total = adultTotal + childTotal;
            
            var detailsHtml = '';
            if (adultQty > 0) {
                detailsHtml += '<div>' + adultQty + ' Adult' + (adultQty > 1 ? 's' : '') + ': $' + adultTotal.toFixed(2) + '</div>';
            }
            if (childQty > 0) {
                detailsHtml += '<div>' + childQty + ' Child' + (childQty > 1 ? 'ren' : '') + ': $' + childTotal.toFixed(2) + '</div>';
            }
            
            $('#fp-price-details').html(detailsHtml);
            $('#fp-total-amount').text('$' + total.toFixed(2));
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
            
            $('#fp-add-to-cart').prop('disabled', true).text('Adding...');
            
            // Create form data
            var formData = new FormData();
            formData.append('add-to-cart', productId);
            formData.append('quantity', 1); // We handle quantity in our custom fields
            formData.append('fp_slot_start', slotStart);
            formData.append('fp_meeting_point_id', 1); // Default meeting point
            formData.append('fp_lang', language);
            formData.append('fp_qty_adult', adultQty);
            formData.append('fp_qty_child', childQty);
            
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