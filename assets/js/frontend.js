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
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initExtras();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
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
         * Initialize extras functionality
         */
        initExtras: function() {
            if ($('.fp-extras-section').length === 0) {
                return;
            }

            this.bindExtrasEvents();
            this.updateExtrasTotal();
        },

        /**
         * Bind extras events
         */
        bindExtrasEvents: function() {
            // Handle extra checkbox changes
            $(document).on('change', '.fp-extra-checkbox', function() {
                var $item = $(this).closest('.fp-extra-item');
                var $quantityDiv = $item.find('.fp-extra-quantity');
                
                if ($(this).is(':checked')) {
                    $quantityDiv.show();
                } else {
                    $quantityDiv.hide();
                    $quantityDiv.find('.fp-extra-quantity-input').val(1);
                }
                
                FPEsperienze.updateExtrasTotal();
            });

            // Handle quantity changes
            $(document).on('change', '.fp-extra-quantity-input', function() {
                FPEsperienze.updateExtrasTotal();
            });
        },

        /**
         * Update extras total
         */
        updateExtrasTotal: function() {
            var extrasTotal = 0;
            var hasExtras = false;
            
            // Assume 1 adult for now (this will be dynamic when booking system is implemented)
            var adultCount = 1;
            var childCount = 0;
            var totalPeople = adultCount + childCount;
            
            $('.fp-extra-item').each(function() {
                var $item = $(this);
                var $checkbox = $item.find('.fp-extra-checkbox');
                
                if ($checkbox.is(':checked')) {
                    hasExtras = true;
                    var price = parseFloat($item.data('extra-price')) || 0;
                    var pricingType = $item.data('pricing-type');
                    var quantity = parseInt($item.find('.fp-extra-quantity-input').val()) || 1;
                    
                    if (pricingType === 'per_person') {
                        extrasTotal += price * quantity * totalPeople;
                    } else {
                        extrasTotal += price * quantity;
                    }
                }
            });
            
            // Update extras total display
            var $extrasTotal = $('#fp-extras-total');
            var $extrasTotalAmount = $('#fp-extras-total-amount');
            
            if (hasExtras && extrasTotal > 0) {
                $extrasTotal.show();
                $extrasTotalAmount.html(this.formatPrice(extrasTotal));
            } else {
                $extrasTotal.hide();
            }
            
            // Update overall total
            this.updateOverallTotal();
        },

        /**
         * Update overall total
         */
        updateOverallTotal: function() {
            var baseTotal = 0;
            var extrasTotal = 0;
            
            // Calculate base price (adult + child)
            // This is a placeholder - in a real implementation this would be dynamic based on participant selection
            var adultPrice = parseFloat($('.fp-price-row:contains("Adult")').find('span:last').text().replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
            var childPrice = parseFloat($('.fp-price-row:contains("Child")').find('span:last').text().replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
            baseTotal = adultPrice + childPrice;
            
            // Get extras total
            if ($('#fp-extras-total').is(':visible')) {
                var extrasText = $('#fp-extras-total-amount').text().replace(/[^\d.,]/g, '').replace(',', '.');
                extrasTotal = parseFloat(extrasText) || 0;
            }
            
            var grandTotal = baseTotal + extrasTotal;
            $('#fp-total-amount').html(this.formatPrice(grandTotal));
        },

        /**
         * Format price (simplified - should use WooCommerce formatting in real implementation)
         */
        formatPrice: function(amount) {
            // This is a simplified price formatter
            // In a real implementation, this should use WooCommerce's price formatting
            // For now, we'll use a basic Euro format
            return new Intl.NumberFormat('en-DE', {
                style: 'currency',
                currency: 'EUR',
                minimumFractionDigits: 2
            }).format(amount);
        }
    };

})(jQuery);