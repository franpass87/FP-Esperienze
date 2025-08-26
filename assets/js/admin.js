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
            // Schedule management
            this.bindScheduleEvents();
            
            // Override management
            this.bindOverrideEvents();
        },
        
        /**
         * Bind schedule events
         */
        bindScheduleEvents: function() {
            var self = this;
            
            // Add schedule
            $(document).on('click', '#fp-add-schedule', function(e) {
                e.preventDefault();
                self.addScheduleRow();
            });
            
            // Remove schedule
            $(document).on('click', '.fp-remove-schedule', function(e) {
                e.preventDefault();
                $(this).closest('.fp-schedule-row').remove();
            });
        },
        
        /**
         * Bind override events
         */
        bindOverrideEvents: function() {
            var self = this;
            
            // Add override
            $(document).on('click', '#fp-add-override', function(e) {
                e.preventDefault();
                self.addOverrideRow();
            });
            
            // Remove override
            $(document).on('click', '.fp-remove-override', function(e) {
                e.preventDefault();
                $(this).closest('.fp-override-row').remove();
            });
        },
        
        /**
         * Add schedule row
         */
        addScheduleRow: function() {
            var container = $('#fp-schedules-container');
            var index = container.find('.fp-schedule-row').length;
            
            var days = {
                '': 'Select Day',
                '0': 'Sunday',
                '1': 'Monday', 
                '2': 'Tuesday',
                '3': 'Wednesday',
                '4': 'Thursday',
                '5': 'Friday',
                '6': 'Saturday'
            };
            
            var dayOptions = '';
            $.each(days, function(value, label) {
                dayOptions += '<option value="' + value + '">' + label + '</option>';
            });
            
            var row = $('<div class="fp-schedule-row" data-index="' + index + '">' +
                '<input type="hidden" name="schedules[' + index + '][id]" value="">' +
                '<select name="schedules[' + index + '][day_of_week]" required>' + dayOptions + '</select>' +
                '<input type="time" name="schedules[' + index + '][start_time]" required>' +
                '<input type="number" name="schedules[' + index + '][duration_min]" value="60" min="1" step="1" required>' +
                '<input type="number" name="schedules[' + index + '][capacity]" value="10" min="1" step="1" required>' +
                '<input type="text" name="schedules[' + index + '][lang]" value="en" maxlength="10">' +
                '<select name="schedules[' + index + '][meeting_point_id]"></select>' +
                '<input type="number" name="schedules[' + index + '][price_adult]" min="0" step="0.01">' +
                '<input type="number" name="schedules[' + index + '][price_child]" min="0" step="0.01">' +
                '<button type="button" class="button fp-remove-schedule">Remove</button>' +
                '</div>');
            
            container.append(row);
        },
        
        /**
         * Add override row
         */
        addOverrideRow: function() {
            var container = $('#fp-overrides-container');
            var index = container.find('.fp-override-row').length;
            
            var row = $('<div class="fp-override-row" data-index="' + index + '">' +
                '<input type="hidden" name="overrides[' + index + '][id]" value="">' +
                '<input type="date" name="overrides[' + index + '][date]" required>' +
                '<label><input type="checkbox" name="overrides[' + index + '][is_closed]" value="1"> Closed</label>' +
                '<input type="number" name="overrides[' + index + '][capacity_override]" placeholder="Capacity Override" min="0" step="1">' +
                '<input type="number" name="overrides[' + index + '][price_adult]" placeholder="Adult Price" min="0" step="0.01">' +
                '<input type="number" name="overrides[' + index + '][price_child]" placeholder="Child Price" min="0" step="0.01">' +
                '<input type="text" name="overrides[' + index + '][reason]" placeholder="Reason">' +
                '<button type="button" class="button fp-remove-override">Remove</button>' +
                '</div>');
            
            container.append(row);
        }
    };

})(jQuery);