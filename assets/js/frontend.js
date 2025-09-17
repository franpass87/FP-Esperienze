/**
 * FP Esperienze Frontend JavaScript
 */

(function($) {
    'use strict';

    const { __, sprintf } = wp.i18n;

    if (typeof fp_esperienze_params !== 'undefined' && typeof fp_esperienze_params.banner_offset !== 'undefined') {
        document.documentElement.style.setProperty('--fp-banner-offset', fp_esperienze_params.banner_offset + 'px');
    }

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

            // Archive filters functionality
            this.initArchiveFilters();

            // Analytics tracking for experience cards
            this.initAnalyticsTracking();
            
            // Voucher functionality
            this.initVoucherHandling();
        },
        
        /**
         * Initialize voucher handling
         */
        initVoucherHandling: function() {
            var self = this;
            
            // Apply voucher button
            $(document).on('click', '.fp-apply-voucher-btn', function(e) {
                e.preventDefault();
                
                var $btn = $(this);
                var $form = $btn.closest('.fp-voucher-form');
                var $input = $form.find('.fp-voucher-code-input');
                var voucherCode = $input.val().trim();
                var productId = $form.data('product-id');
                var cartItemKey = $form.data('cart-item-key');
                
                if (!voucherCode) {
                    self.showVoucherMessage($form, 'error', __('Please enter a voucher code.', 'fp-esperienze'));
                    return;
                }
                
                self.applyVoucher(voucherCode, productId, cartItemKey, $form);
            });
            
            // Remove voucher button
            $(document).on('click', '.fp-remove-voucher-btn', function(e) {
                e.preventDefault();
                
                var $btn = $(this);
                var $form = $btn.closest('.fp-voucher-form');
                var cartItemKey = $form.data('cart-item-key');
                
                self.removeVoucher(cartItemKey, $form);
            });
            
            // Enter key on voucher input
            $(document).on('keypress', '.fp-voucher-code-input', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $(this).siblings('.fp-apply-voucher-btn').click();
                }
            });
        },
        
        /**
         * Apply voucher
         */
        applyVoucher: function(voucherCode, productId, cartItemKey, $form) {
            var self = this;
            var $btn = $form.find('.fp-apply-voucher-btn');
            var originalText = $btn.text();
            
            $btn.prop('disabled', true).text(__('Applying...', 'fp-esperienze'));
            self.clearVoucherMessage($form);
            
            $.ajax({
                url: fp_esperienze_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'apply_voucher',
                    nonce: fp_esperienze_params.voucher_nonce,
                    voucher_code: voucherCode,
                    product_id: productId,
                    cart_item_key: cartItemKey
                },
                success: function(response) {
                    if (response.success) {
                        self.showVoucherMessage($form, 'success', response.data.message);
                        self.updateVoucherUI($form, 'applied', {
                            code: voucherCode,
                            discount_info: response.data.discount_info
                        });
                        
                        // Refresh cart totals
                        if (typeof wc_cart_fragments_params !== 'undefined') {
                            $(document.body).trigger('wc_fragment_refresh');
                        }
                    } else {
                        self.showVoucherMessage($form, 'error', response.data.message);
                    }
                },
                error: function() {
                    self.showVoucherMessage($form, 'error', __('Something went wrong. Please try again.', 'fp-esperienze'));
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },
        
        /**
         * Remove voucher
         */
        removeVoucher: function(cartItemKey, $form) {
            var self = this;
            var $btn = $form.find('.fp-remove-voucher-btn');
            var originalText = $btn.text();
            
            $btn.prop('disabled', true).text(__('Removing...', 'fp-esperienze'));
            self.clearVoucherMessage($form);
            
            $.ajax({
                url: fp_esperienze_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'remove_voucher',
                    nonce: fp_esperienze_params.voucher_nonce,
                    cart_item_key: cartItemKey
                },
                success: function(response) {
                    if (response.success) {
                        self.showVoucherMessage($form, 'success', response.data.message);
                        self.updateVoucherUI($form, 'input', null);
                        
                        // Refresh cart totals
                        if (typeof wc_cart_fragments_params !== 'undefined') {
                            $(document.body).trigger('wc_fragment_refresh');
                        }
                    } else {
                        self.showVoucherMessage($form, 'error', response.data.message);
                    }
                },
                error: function() {
                    self.showVoucherMessage($form, 'error', __('Something went wrong. Please try again.', 'fp-esperienze'));
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },
        
        /**
         * Update voucher UI state
         */
        updateVoucherUI: function($form, state, data) {
            var $input = $form.find('.fp-voucher-code-input');
            var $applyBtn = $form.find('.fp-apply-voucher-btn');
            var $removeBtn = $form.find('.fp-remove-voucher-btn');
            var $status = $form.find('.fp-voucher-status');
            
            if (state === 'applied') {
                $input.prop('readonly', true).val(data.code);
                $applyBtn.hide();
                $removeBtn.show();
                
                $status.html(
                    '<span class="fp-voucher-applied">' +
                    '<i class="dashicons dashicons-yes-alt"></i> ' +
                    sprintf(__('Voucher applied: %s', 'fp-esperienze'), data.discount_info.description) +
                    '</span>'
                ).addClass('success').show();
                
            } else if (state === 'input') {
                $input.prop('readonly', false).val('');
                $applyBtn.show();
                $removeBtn.hide();
                $status.hide().removeClass('success error');
            }
        },
        
        /**
         * Show voucher message
         */
        showVoucherMessage: function($form, type, message) {
            var $message = $form.find('.fp-voucher-message');
            if (!$message.length) {
                $message = $('<div class="fp-voucher-message"></div>');
                $form.append($message);
            }
            
            $message.removeClass('success error')
                    .addClass(type)
                    .html('<p>' + message + '</p>')
                    .show();
        },
        
        /**
         * Clear voucher message
         */
        clearVoucherMessage: function($form) {
            $form.find('.fp-voucher-message').hide();
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

                self.adultPrice = parseFloat($(this).data('adult-price')) || 0;
                self.childPrice = parseFloat($(this).data('child-price')) || 0;

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
                    var errorMsg = __('Failed to load availability.', 'fp-esperienze');
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    } else if (status === 'timeout') {
                        errorMsg = __('Request timed out. Please try again.', 'fp-esperienze');
                    } else if (status === 'error') {
                        errorMsg = __('Network error. Please check your connection.', 'fp-esperienze');
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
            this.adultPrice = 0;
            this.childPrice = 0;
            $('#fp-selected-slot').val('');
            this.updateTotal();
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
            
            // Collect gift data
            var isGift = $('#fp-gift-toggle').is(':checked');
            
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
            
            // Add gift data if this is a gift purchase
            if (isGift) {
                formData.append('fp_is_gift', '1');
                formData.append('fp_gift_sender_name', $('#fp-gift-sender-name').val() || '');
                formData.append('fp_gift_recipient_name', $('#fp-gift-recipient-name').val() || '');
                formData.append('fp_gift_recipient_email', $('#fp-gift-recipient-email').val() || '');
                formData.append('fp_gift_message', $('#fp-gift-message').val() || '');
                formData.append('fp_gift_send_date', $('#fp-gift-send-date').val() || '');
            }
            
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
                    throw new Error(__('Failed to add to cart', 'fp-esperienze'));
                }
            })
            .catch(function(error) {
                self.showError(__('Failed to add to cart. Please try again.', 'fp-esperienze'));
                $('#fp-add-to-cart').prop('disabled', false).text(__('Add to Cart', 'fp-esperienze'));
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
        },

        /**
         * Initialize archive filters
         */
        initArchiveFilters: function() {
            var self = this;

            // Auto-submit filters on change (with debounce)
            var filterTimeout;
            $('#fp-filters-form select, #fp-filters-form input').on('change', function() {
                clearTimeout(filterTimeout);
                filterTimeout = setTimeout(function() {
                    self.submitFilters();
                }, 300);
            });

            // Prevent form submission and handle via AJAX for better UX
            $('#fp-filters-form').on('submit', function(e) {
                e.preventDefault();
                self.submitFilters();
            });

            // Clear filters
            $('.fp-filter-reset').on('click', function(e) {
                e.preventDefault();
                self.clearFilters();
            });
        },

        /**
         * Submit filters via URL update
         */
        submitFilters: function() {
            var form = $('#fp-filters-form');
            if (!form.length) return;

            // Show loading state
            this.showFilterLoading(true);

            // Build query string
            var params = new URLSearchParams();
            
            // Add filter values
            form.find('select, input').each(function() {
                var $field = $(this);
                var value = $field.val();
                if (value && value !== '') {
                    params.set($field.attr('name'), value);
                }
            });

            // Add hidden fields
            form.find('input[type="hidden"]').each(function() {
                var $field = $(this);
                var value = $field.val();
                if (value && value !== '') {
                    params.set($field.attr('name'), value);
                }
            });

            // Reset pagination
            params.delete('paged');

            // Update URL and reload
            var newUrl = window.location.pathname + '?' + params.toString();
            window.location.href = newUrl;
        },

        /**
         * Clear all filters
         */
        clearFilters: function() {
            // Build URL with only non-filter parameters
            var params = new URLSearchParams(window.location.search);
            var keepParams = new URLSearchParams();

            // Keep only non-filter parameters
            for (var [key, value] of params) {
                if (!key.startsWith('fp_') && key !== 'paged') {
                    keepParams.set(key, value);
                }
            }

            var newUrl = window.location.pathname;
            if (keepParams.toString()) {
                newUrl += '?' + keepParams.toString();
            }

            window.location.href = newUrl;
        },

        /**
         * Show/hide filter loading state
         */
        showFilterLoading: function(show) {
            var $results = $('.fp-experience-results');
            if (!$results.length) return;

            if (show) {
                $results.addClass('fp-loading');
                // Optionally add skeleton cards
                this.showSkeletonCards();
            } else {
                $results.removeClass('fp-loading');
            }
        },

        /**
         * Show skeleton loading cards
         */
        showSkeletonCards: function() {
            var $grid = $('.fp-experience-grid');
            if (!$grid.length) return;

            var skeletonHtml = '';
            for (var i = 0; i < 6; i++) {
                skeletonHtml += '<div class="fp-experience-card fp-skeleton">' +
                    '<div class="fp-experience-image"></div>' +
                    '<div class="fp-experience-content">' +
                    '<h3 class="fp-experience-title">Loading experience...</h3>' +
                    '<div class="fp-experience-excerpt">Loading description...</div>' +
                    '<div class="fp-experience-price">Loading price...</div>' +
                    '</div></div>';
            }

            $grid.html(skeletonHtml);
        },

        /**
         * Initialize analytics tracking
         */
        initAnalyticsTracking: function() {
            // Track clicks on experience details buttons
            $(document).on('click', '.fp-details-btn', function(e) {
                var $btn = $(this);
                var itemId = $btn.data('item-id');
                var itemName = $btn.data('item-name');

                // Push to dataLayer if available
                if (typeof window.dataLayer !== 'undefined') {
                    window.dataLayer.push({
                        event: 'select_item',
                        items: [{
                            item_id: itemId,
                            item_name: itemName,
                            item_category: 'experience'
                        }]
                    });
                }
            });

            // Track filter usage
            $(document).on('change', '.fp-filter-select, .fp-filter-date', function() {
                var $field = $(this);
                var filterType = $field.attr('name');
                var filterValue = $field.val();

                if (typeof window.dataLayer !== 'undefined' && filterValue) {
                    window.dataLayer.push({
                        event: 'filter_experience',
                        filter_type: filterType,
                        filter_value: filterValue
                    });
                }
            });
        }
    };

})(jQuery);