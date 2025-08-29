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
        
        // Track unsaved changes
        hasUnsavedChanges: false,
        
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
            
            // Schedule Builder events
            this.bindScheduleBuilderEvents();
            
            // Raw Schedule events (for advanced mode)
            $(document).on('click', '#fp-add-schedule', function(e) {
                e.preventDefault();
                self.addScheduleRow();
            });
            
            // Remove schedule
            $(document).on('click', '.fp-remove-schedule', function(e) {
                e.preventDefault();
                $(this).closest('.fp-schedule-row').remove();
            });
            
            // Toggle raw mode
            $(document).on('change', '#fp-toggle-raw-mode', function() {
                self.toggleRawMode($(this).is(':checked'));
            });
            
            // Form submission handling for schedule builder
            $('form#post').on('submit', function() {
                if ($('#product-type').val() === 'experience') {
                    self.generateSchedulesFromBuilder();
                    // Clear unsaved changes flag on successful submission
                    self.clearUnsavedChanges();
                }
            });
        },
        
        /**
         * Bind Schedule Builder specific events
         */
        bindScheduleBuilderEvents: function() {
            var self = this;
            
            // Add time slot
            $(document).on('click', '#fp-add-time-slot', function(e) {
                e.preventDefault();
                self.addTimeSlot();
            });
            
            // Remove time slot
            $(document).on('click', '.fp-remove-time-slot', function(e) {
                e.preventDefault();
                $(this).closest('.fp-time-slot-row').remove();
                self.updateSummaryTable();
            });
            
            // Toggle overrides visibility
            $(document).on('change', '.fp-show-overrides-toggle', function() {
                var overridesSection = $(this).closest('.fp-time-slot-row').find('.fp-overrides-section');
                var advancedEnabledField = $(this).closest('.fp-time-slot-row').find('.fp-advanced-enabled');
                
                if ($(this).is(':checked')) {
                    overridesSection.slideDown(200);
                    advancedEnabledField.val('1');
                } else {
                    overridesSection.slideUp(200);
                    // Clear override values when hiding
                    overridesSection.find('input, select').val('');
                    advancedEnabledField.val('0');
                }
                self.updateSummaryTable();
            });
            
            // Update summary when time or days change
            $(document).on('change', 'input[name*="[start_time]"], input[name*="[days][]"]', function() {
                self.updateSummaryTable();
            });
            
            // Handle day pill clicks - click on label should toggle the checkbox
            $(document).on('click', '.fp-day-pill label', function(e) {
                // Don't prevent default - let the label naturally trigger the checkbox
                // But ensure we update the summary after the checkbox state changes
                var self_ref = self;
                setTimeout(function() {
                    self_ref.updateSummaryTable();
                }, 10);
            });
            
            // Update summary when overrides change
            $(document).on('change', 'input[name*="[duration_min]"], input[name*="[capacity]"]', function() {
                self.updateSummaryTable();
            });
            
            // Validate time slots before form submission
            $('form#post').on('submit', function(e) {
                if (!self.validateTimeSlots()) {
                    e.preventDefault();
                    return false;
                }
            });
            
            // Auto-save functionality for better UX
            $(document).on('change', '#fp-time-slots-container input, #fp-time-slots-container select', function() {
                self.markAsChanged();
            });
        },
        
        /**
         * Validate time slots before saving
         */
        validateTimeSlots: function() {
            var hasErrors = false;
            var errorMessages = [];
            
            $('#fp-time-slots-container .fp-time-slot-row').each(function() {
                var $slot = $(this);
                var startTime = $slot.find('input[name*="[start_time]"]').val();
                var selectedDays = $slot.find('input[name*="[days][]"]:checked').length;
                
                if (!startTime) {
                    hasErrors = true;
                    $slot.find('input[name*="[start_time]"]').css('border-color', '#d63638');
                    errorMessages.push('All time slots must have a start time.');
                } else {
                    $slot.find('input[name*="[start_time]"]').css('border-color', '');
                }
                
                if (selectedDays === 0) {
                    hasErrors = true;
                    $slot.find('.fp-days-pills').css('border', '2px solid #d63638');
                    errorMessages.push('All time slots must have at least one day selected.');
                } else {
                    $slot.find('.fp-days-pills').css('border', '');
                }
            });
            
            if (hasErrors) {
                var uniqueMessages = [...new Set(errorMessages)];
                alert('Please fix the following errors:\n\n' + uniqueMessages.join('\n'));
                return false;
            }
            
            return true;
        },
        
        /**
         * Mark form as changed for unsaved changes warning
         */
        markAsChanged: function() {
            if (!this.hasUnsavedChanges) {
                this.hasUnsavedChanges = true;
                this.showUnsavedChangesWarning();
            }
        },
        
        /**
         * Show unsaved changes warning
         */
        showUnsavedChangesWarning: function() {
            window.addEventListener('beforeunload', function(e) {
                if (FPEsperienzeAdmin.hasUnsavedChanges) {
                    e.preventDefault();
                    e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                    return e.returnValue;
                }
            });
        },
        
        /**
         * Clear unsaved changes flag
         */
        clearUnsavedChanges: function() {
            this.hasUnsavedChanges = false;
        },
        
        /**
         * Update the summary table
         */
        updateSummaryTable: function() {
            var summaryContainer = $('.fp-slots-summary-content');
            if (!summaryContainer.length) return;
            
            var slots = [];
            var days = {
                '1': 'Mon', '2': 'Tue', '3': 'Wed', '4': 'Thu', 
                '5': 'Fri', '6': 'Sat', '0': 'Sun'
            };
            
            // Collect slot data
            $('#fp-time-slots-container .fp-time-slot-row').each(function() {
                var $slot = $(this);
                var startTime = $slot.find('input[name*="[start_time]"]').val();
                var selectedDays = [];
                
                $slot.find('input[name*="[days][]"]:checked').each(function() {
                    selectedDays.push($(this).val());
                });
                
                if (startTime && selectedDays.length > 0) {
                    var overridesEnabled = $slot.find('.fp-show-overrides-toggle').is(':checked');
                    var customCount = 0;
                    
                    if (overridesEnabled) {
                        $slot.find('.fp-overrides-section input, .fp-overrides-section select').each(function() {
                            if ($(this).val() && $(this).val() !== '') {
                                customCount++;
                            }
                        });
                    }
                    
                    var duration = $slot.find('input[name*="[duration_min]"]').val();
                    var capacity = $slot.find('input[name*="[capacity]"]').val();
                    
                    slots.push({
                        time: startTime,
                        days: selectedDays,
                        duration: duration,
                        capacity: capacity,
                        customCount: customCount
                    });
                }
            });
            
            // Build new summary HTML
            var summaryHtml;
            if (slots.length === 0) {
                summaryHtml = '<div class="fp-summary-table"><div class="fp-empty-state">No time slots configured yet. Click "Add Time Slot" below to get started.</div></div>';
            } else {
                summaryHtml = '<table class="fp-summary-table">' +
                    '<thead><tr>' +
                        '<th>Time</th>' +
                        '<th>Days</th>' +
                        '<th>Duration</th>' +
                        '<th>Capacity</th>' +
                        '<th>Customized</th>' +
                    '</tr></thead><tbody>';
                
                slots.forEach(function(slot) {
                    summaryHtml += '<tr>' +
                        '<td><span class="fp-time-badge">' + slot.time + '</span></td>' +
                        '<td><div class="fp-days-summary">';
                    
                    // Sort days and show badges
                    var sortedDays = ['1','2','3','4','5','6','0'].filter(function(day) {
                        return slot.days.indexOf(day) !== -1;
                    });
                    
                    sortedDays.forEach(function(day) {
                        summaryHtml += '<span class="fp-day-badge">' + days[day] + '</span>';
                    });
                    
                    summaryHtml += '</div></td>' +
                        '<td>' + (slot.duration ? slot.duration + ' min' : '<em>Default</em>') + '</td>' +
                        '<td>' + (slot.capacity ? slot.capacity : '<em>Default</em>') + '</td>' +
                        '<td>' + (slot.customCount > 0 ? slot.customCount + ' setting' + (slot.customCount > 1 ? 's' : '') : '<em>None</em>') + '</td>' +
                        '</tr>';
                });
                
                summaryHtml += '</tbody></table>';
            }
            
            summaryContainer.html(summaryHtml);
        },
        
        /**
         * Toggle between builder and raw mode
         */
        toggleRawMode: function(showRaw) {
            if (showRaw) {
                $('#fp-schedule-builder-container').hide();
                $('#fp-schedule-raw-container').show();
            } else {
                $('#fp-schedule-builder-container').show();
                $('#fp-schedule-raw-container').hide();
            }
        },
        
        /**
         * Add a new time slot to the builder
         */
        addTimeSlot: function() {
            var container = $('#fp-time-slots-container');
            var index = container.find('.fp-time-slot-row').length;
            
            var days = {
                '1': 'Monday',
                '2': 'Tuesday', 
                '3': 'Wednesday',
                '4': 'Thursday',
                '5': 'Friday',
                '6': 'Saturday',
                '0': 'Sunday'
            };
            
            // Get product defaults for placeholders
            var defaultDuration = $('#_fp_exp_duration').val() || '60';
            var defaultCapacity = $('#_fp_exp_capacity').val() || '10';
            var defaultLanguage = $('#_fp_exp_language').val() || 'en';
            var defaultMeetingPoint = $('#_fp_exp_meeting_point_id option:selected').text() || 'None';
            var defaultPriceAdult = $('#_regular_price').val() || '0.00';
            var defaultPriceChild = $('#_fp_exp_price_child').val() || '0.00';
            
            var timeSlotHtml = '<div class="fp-time-slot-row" data-index="' + index + '">' +
                    '<div class="fp-time-slot-header">' +
                        '<div class="fp-time-field">' +
                            '<label>' +
                                '<span class="dashicons dashicons-clock"></span>' +
                                'Start Time <span style="color: red;">*</span>' +
                            '</label>' +
                            '<input type="time" name="builder_slots[' + index + '][start_time]" required>' +
                        '</div>' +
                        '<div class="fp-days-field">' +
                            '<label>' +
                                '<span class="dashicons dashicons-calendar-alt"></span>' +
                                'Days of Week <span style="color: red;">*</span>' +
                            '</label>' +
                            '<div class="fp-days-selector">' +
                                '<div class="fp-days-pills">';
                                
            for (var dayValue in days) {
                var dayLabel = days[dayValue].substring(0, 3);
                timeSlotHtml += '<div class="fp-day-pill">' +
                    '<input type="checkbox" id="day-' + index + '-' + dayValue + '" name="builder_slots[' + index + '][days][]" value="' + dayValue + '">' +
                    '<label for="day-' + index + '-' + dayValue + '">' + dayLabel + '</label>' +
                '</div>';
            }
            
            timeSlotHtml += '</div></div></div>' +
                        '<div>' +
                            '<button type="button" class="fp-remove-time-slot">' +
                                '<span class="dashicons dashicons-trash"></span> Remove' +
                            '</button>' +
                        '</div>' +
                    '</div>' +
                    
                    '<div class="fp-override-toggle">' +
                        '<label>' +
                            '<input type="checkbox" class="fp-show-overrides-toggle">' +
                            '<span class="dashicons dashicons-admin-tools"></span>' +
                            ' Advanced Settings' +
                        '</label>' +
                        '<span class="description">Override default values for this specific time slot</span>' +
                        '<input type="hidden" name="builder_slots[' + index + '][advanced_enabled]" value="0" class="fp-advanced-enabled">' +
                    '</div>' +
                    
                    '<div class="fp-overrides-section" style="display: none;">' +
                        '<div>' +
                            '<div>' +
                                '<label>Duration (minutes)</label>' +
                                '<input type="number" name="builder_slots[' + index + '][duration_min]" min="1" placeholder="Default: ' + defaultDuration + '">' +
                            '</div>' +
                            '<div>' +
                                '<label>Capacity</label>' +
                                '<input type="number" name="builder_slots[' + index + '][capacity]" placeholder="Default: ' + defaultCapacity + '">' +
                            '</div>' +
                            '<div>' +
                                '<label>Language</label>' +
                                '<input type="text" name="builder_slots[' + index + '][lang]" maxlength="10" placeholder="Default: ' + defaultLanguage + '">' +
                            '</div>' +
                        '</div>' +
                        '<div>' +
                            '<div>' +
                                '<label>Meeting Point</label>' +
                                '<select name="builder_slots[' + index + '][meeting_point_id]">' +
                                    '<option value="">Default: ' + defaultMeetingPoint + '</option>' +
                                '</select>' +
                            '</div>' +
                            '<div>' +
                                '<label>Adult Price</label>' +
                                '<input type="number" name="builder_slots[' + index + '][price_adult]" min="0" step="0.01" placeholder="Default: ' + defaultPriceAdult + '">' +
                            '</div>' +
                            '<div>' +
                                '<label>Child Price</label>' +
                                '<input type="number" name="builder_slots[' + index + '][price_child]" min="0" step="0.01" placeholder="Default: ' + defaultPriceChild + '">' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
            
            container.append(timeSlotHtml);
            
            // Populate meeting points dropdown
            this.populateMeetingPointsDropdown(container.find('.fp-time-slot-row').last().find('select[name*="meeting_point_id"]'));
            
            // Update summary table
            this.updateSummaryTable();
        },
        
        /**
         * Populate meeting points dropdown
         */
        populateMeetingPointsDropdown: function(selectElement) {
            // Copy options from the main meeting point select
            var mainSelect = $('#_fp_exp_meeting_point_id');
            if (mainSelect.length) {
                mainSelect.find('option').each(function() {
                    if ($(this).val()) { // Skip empty option
                        selectElement.append('<option value="' + $(this).val() + '">' + $(this).text() + '</option>');
                    }
                });
            }
        },
        
        /**
         * Generate schedule inputs from builder before form submission
         */
        generateSchedulesFromBuilder: function() {
            var generatedContainer = $('#fp-generated-schedules');
            generatedContainer.empty();
            
            var scheduleIndex = 0;
            
            // Process each time slot
            $('#fp-time-slots-container .fp-time-slot-row').each(function() {
                var timeSlot = $(this);
                var startTime = timeSlot.find('input[name*="[start_time]"]').val();
                var selectedDays = [];
                
                // Get selected days
                timeSlot.find('input[name*="[days][]"]:checked').each(function() {
                    selectedDays.push($(this).val());
                });
                
                if (!startTime || selectedDays.length === 0) {
                    return; // Skip invalid slots
                }
                
                // Get override values
                var overrides = {};
                if (timeSlot.find('.fp-show-overrides-toggle').is(':checked')) {
                    var duration = timeSlot.find('input[name*="[duration_min]"]').val();
                    var capacity = timeSlot.find('input[name*="[capacity]"]').val();
                    var lang = timeSlot.find('input[name*="[lang]"]').val();
                    var meetingPoint = timeSlot.find('select[name*="[meeting_point_id]"]').val();
                    var priceAdult = timeSlot.find('input[name*="[price_adult]"]').val();
                    var priceChild = timeSlot.find('input[name*="[price_child]"]').val();
                    
                    if (duration) overrides.duration_min = duration;
                    if (capacity) overrides.capacity = capacity;
                    if (lang) overrides.lang = lang;
                    if (meetingPoint) overrides.meeting_point_id = meetingPoint;
                    if (priceAdult) overrides.price_adult = priceAdult;
                    if (priceChild) overrides.price_child = priceChild;
                }
                
                // Generate schedule input for each selected day
                selectedDays.forEach(function(dayOfWeek) {
                    var scheduleHtml = '<input type="hidden" name="schedules[' + scheduleIndex + '][day_of_week]" value="' + dayOfWeek + '">' +
                        '<input type="hidden" name="schedules[' + scheduleIndex + '][start_time]" value="' + startTime + '">';
                    
                    // Add override values if they exist
                    for (var key in overrides) {
                        scheduleHtml += '<input type="hidden" name="schedules[' + scheduleIndex + '][' + key + ']" value="' + overrides[key] + '">';
                    }
                    
                    generatedContainer.append(scheduleHtml);
                    scheduleIndex++;
                });
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
                '<input type="date" name="overrides[' + index + '][date]" required aria-label="Override date">' +
                '<label>' +
                    '<input type="checkbox" name="overrides[' + index + '][is_closed]" value="1"> Closed' +
                '</label>' +
                '<input type="number" name="overrides[' + index + '][capacity_override]" placeholder="Capacity" min="0" step="1" aria-label="Capacity override">' +
                '<input type="number" name="overrides[' + index + '][price_adult]" placeholder="Adult €" min="0" step="0.01" aria-label="Adult price override">' +
                '<input type="number" name="overrides[' + index + '][price_child]" placeholder="Child €" min="0" step="0.01" aria-label="Child price override">' +
                '<input type="text" name="overrides[' + index + '][reason]" placeholder="Reason (optional)" aria-label="Reason for this override">' +
                '<button type="button" class="fp-remove-override" aria-label="Remove this override">Remove</button>' +
                '</div>');
            
            container.append(row);
        }
    };

})(jQuery);