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
            
            // Add schedule button
            $(document).on('click', '#add-schedule', function(e) {
                e.preventDefault();
                self.addScheduleRow();
            });
            
            // Remove schedule button
            $(document).on('click', '.remove-schedule', function(e) {
                e.preventDefault();
                $(this).closest('.schedule-row').remove();
            });
        },

        /**
         * Bind override events
         */
        bindOverrideEvents: function() {
            var self = this;
            
            // Add override button
            $(document).on('click', '#add-override', function(e) {
                e.preventDefault();
                self.addOverrideRow();
            });
            
            // Remove override button
            $(document).on('click', '.remove-override', function(e) {
                e.preventDefault();
                $(this).closest('.override-row').remove();
            });
        },

        /**
         * Add new schedule row
         */
        addScheduleRow: function() {
            var index = $('#schedule-list .schedule-row').length;
            var days = {
                0: 'Sunday',
                1: 'Monday', 
                2: 'Tuesday',
                3: 'Wednesday',
                4: 'Thursday',
                5: 'Friday',
                6: 'Saturday'
            };
            
            var html = '<div class="schedule-row" data-index="' + index + '">' +
                '<table class="widefat">' +
                '<tr>' +
                    '<td>' +
                        '<label>Day</label>' +
                        '<select name="schedules[' + index + '][day_of_week]">';
            
            for (var day in days) {
                html += '<option value="' + day + '">' + days[day] + '</option>';
            }
            
            html += '</select>' +
                    '</td>' +
                    '<td>' +
                        '<label>Start Time</label>' +
                        '<input type="time" name="schedules[' + index + '][start_time]" required />' +
                    '</td>' +
                    '<td>' +
                        '<label>Duration (min)</label>' +
                        '<input type="number" name="schedules[' + index + '][duration_min]" value="60" min="1" required />' +
                    '</td>' +
                    '<td>' +
                        '<label>Capacity</label>' +
                        '<input type="number" name="schedules[' + index + '][capacity]" value="10" min="1" required />' +
                    '</td>' +
                '</tr>' +
                '<tr>' +
                    '<td>' +
                        '<label>Language</label>' +
                        '<input type="text" name="schedules[' + index + '][lang]" placeholder="en" />' +
                    '</td>' +
                    '<td>' +
                        '<label>Meeting Point</label>' +
                        '<select name="schedules[' + index + '][meeting_point_id]">' +
                            '<option value="">Select a meeting point</option>' +
                        '</select>' +
                    '</td>' +
                    '<td>' +
                        '<label>Adult Price</label>' +
                        '<input type="number" name="schedules[' + index + '][price_adult]" step="0.01" min="0" />' +
                    '</td>' +
                    '<td>' +
                        '<label>Child Price</label>' +
                        '<input type="number" name="schedules[' + index + '][price_child]" step="0.01" min="0" />' +
                    '</td>' +
                '</tr>' +
                '<tr>' +
                    '<td colspan="3">' +
                        '<label>' +
                            '<input type="checkbox" name="schedules[' + index + '][is_active]" value="1" checked />' +
                            ' Active' +
                        '</label>' +
                    '</td>' +
                    '<td>' +
                        '<button type="button" class="button remove-schedule">Remove</button>' +
                    '</td>' +
                '</tr>' +
                '</table>' +
                '</div>';
            
            $('#schedule-list').append(html);
        },

        /**
         * Add new override row
         */
        addOverrideRow: function() {
            var index = $('#override-list .override-row').length;
            
            var html = '<div class="override-row" data-index="' + index + '">' +
                '<table class="widefat">' +
                '<tr>' +
                    '<td>' +
                        '<label>Date</label>' +
                        '<input type="date" name="overrides[' + index + '][date]" required />' +
                    '</td>' +
                    '<td>' +
                        '<label>' +
                            '<input type="checkbox" name="overrides[' + index + '][is_closed]" value="1" />' +
                            ' Closed' +
                        '</label>' +
                    '</td>' +
                    '<td>' +
                        '<label>Capacity Override</label>' +
                        '<input type="number" name="overrides[' + index + '][capacity_override]" min="0" />' +
                    '</td>' +
                    '<td>' +
                        '<button type="button" class="button remove-override">Remove</button>' +
                    '</td>' +
                '</tr>' +
                '<tr>' +
                    '<td>' +
                        '<label>Adult Price Override</label>' +
                        '<input type="number" name="overrides[' + index + '][price_adult]" step="0.01" min="0" />' +
                    '</td>' +
                    '<td>' +
                        '<label>Child Price Override</label>' +
                        '<input type="number" name="overrides[' + index + '][price_child]" step="0.01" min="0" />' +
                    '</td>' +
                    '<td colspan="2">' +
                        '<label>Reason</label>' +
                        '<input type="text" name="overrides[' + index + '][reason]" placeholder="Optional reason" />' +
                    '</td>' +
                '</tr>' +
                '</table>' +
                '</div>';
            
            $('#override-list').append(html);
        }
    };

})(jQuery);