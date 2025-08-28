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
            this.initBookingsPage();
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
            
            // Ensure experience product type is preserved on form submit
            $('form#post').on('submit', function() {
                if ($('#product-type').val() === 'experience') {
                    // Double-check the product type is set correctly
                    $('#product-type').val('experience');
                }
            });
            
            // Force experience type to be recognized on page load
            if (productType === 'experience') {
                this.forceExperienceType();
            }
        },
        
        /**
         * Force experience type recognition
         */
        forceExperienceType: function() {
            // Ensure the product type dropdown shows experience
            $('#product-type').val('experience');
            
            // Trigger change event to ensure all handlers are called
            $('#product-type').trigger('change');
            
            // Add body class for CSS targeting
            $('body').addClass('product-type-experience');
            
            // Show experience-specific fields
            $('.show_if_experience').show();
            
            // Hide incompatible fields
            $('.show_if_simple, .show_if_variable, .show_if_grouped, .show_if_external').hide();
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
            
            // Special handling for experience type
            if (productType === 'experience') {
                // Show experience-specific elements
                $('.show_if_experience').show();
                $('#experience_product_data, #dynamic_pricing_product_data').show();
                
                // Hide incompatible elements
                $('.show_if_simple, .show_if_variable, .show_if_grouped, .show_if_external').hide();
                
                // Update virtual/downloadable settings for experiences
                $('#_virtual').prop('checked', true).trigger('change');
                $('#_downloadable').prop('checked', false).trigger('change');
            } else {
                // Hide experience-specific elements
                $('.show_if_experience').hide();
                $('#experience_product_data, #dynamic_pricing_product_data').hide();
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
         * Initialize bookings page functionality
         */
        initBookingsPage: function() {
            if ($('#fp-calendar').length) {
                this.initBookingsCalendar();
            }
        },
        
        /**
         * Initialize bookings calendar
         */
        initBookingsCalendar: function() {
            var self = this;
            
            // Load FullCalendar from CDN
            if (typeof FullCalendar === 'undefined') {
                this.loadFullCalendar().then(function() {
                    self.renderCalendar();
                });
            } else {
                this.renderCalendar();
            }
        },
        
        /**
         * Load FullCalendar library
         */
        loadFullCalendar: function() {
            return new Promise(function(resolve, reject) {
                // Load CSS
                var css = document.createElement('link');
                css.rel = 'stylesheet';
                css.href = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css';
                document.head.appendChild(css);
                
                // Load JS
                var script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js';
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });
        },
        
        /**
         * Render FullCalendar
         */
        renderCalendar: function() {
            var calendarEl = document.getElementById('fp-calendar');
            if (!calendarEl) return;
            
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                height: 600,
                events: {
                    url: window.fp_esperienze_admin.rest_url + 'fp-exp/v1/bookings/calendar',
                    method: 'GET',
                    extraParams: function() {
                        return {
                            // Add any filters here if needed
                        };
                    },
                    failure: function() {
                        alert('There was an error while fetching events!');
                    }
                },
                eventClick: function(info) {
                    var booking = info.event.extendedProps;
                    var content = '<div class="fp-booking-popup">' +
                        '<h3>' + info.event.title + '</h3>' +
                        '<p><strong>Order:</strong> #' + booking.order_id + '</p>' +
                        '<p><strong>Customer:</strong> ' + booking.customer_name + '</p>' +
                        '<p><strong>Status:</strong> ' + booking.status + '</p>' +
                        '<p><strong>Participants:</strong> ' + booking.adults + ' adults, ' + booking.children + ' children</p>' +
                        '<p><strong>Date:</strong> ' + info.event.start.toLocaleDateString() + '</p>' +
                        '<p><strong>Time:</strong> ' + info.event.start.toLocaleTimeString() + '</p>' +
                        '</div>';
                    
                    // Show popup (using WordPress admin modal or simple alert)
                    if (typeof tb_show !== 'undefined') {
                        $('body').append('<div id="fp-booking-details" style="display:none">' + content + '</div>');
                        tb_show('Booking Details', '#TB_inline?inlineId=fp-booking-details&width=400&height=300');
                    } else {
                        alert(info.event.title + '\n' + 
                              'Order: #' + booking.order_id + '\n' +
                              'Status: ' + booking.status + '\n' +
                              'Participants: ' + (booking.adults + booking.children));
                    }
                },
                eventDidMount: function(info) {
                    // Add tooltip
                    info.el.setAttribute('title', 
                        info.event.title + '\n' +
                        'Order: #' + info.event.extendedProps.order_id + '\n' +
                        'Status: ' + info.event.extendedProps.status
                    );
                }
            });
            
            calendar.render();
            
            // Store calendar instance for later use
            window.fpBookingsCalendar = calendar;
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