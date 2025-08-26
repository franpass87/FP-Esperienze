/**
 * FP Esperienze Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize admin functionality
        FPEsperienzeAdmin.init();
    });

    window.FPEsperienzeAdmin = {
        
        /**
         * Initialize
         */
        init: function() {
            this.handleProductTypeChange();
            this.bindEvents();
        },

        /**
         * Handle product type change
         */
        handleProductTypeChange: function() {
            var productType = $('#product-type').val();
            this.toggleExperienceFields(productType);

            // Listen for product type changes
            $('#product-type').on('change', function() {
                FPEsperienzeAdmin.toggleExperienceFields($(this).val());
            });
        },

        /**
         * Toggle experience fields visibility
         */
        toggleExperienceFields: function(productType) {
            var $body = $('body');
            
            // Remove all product type classes
            $body.removeClass(function(index, className) {
                return (className.match(/(^|\s)product-type-\S+/g) || []).join(' ');
            });
            
            // Add current product type class
            if (productType) {
                $body.addClass('product-type-' + productType);
            }
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Add any additional admin event handlers here
        }
    };

})(jQuery);