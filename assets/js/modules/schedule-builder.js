/**
 * FP Esperienze - Schedule Builder Module
 * Handles time slot management and schedule building functionality
 */

(function($) {
    'use strict';

    window.FPEsperienzeScheduleBuilder = {
        
        /**
         * Initialize schedule builder
         */
        init: function() {
            this.bindScheduleEvents();
            this.bindScheduleBuilderEvents();
            this.initModernScheduleBuilder();
            this.initTimeSlotManager();
        },

        /**
         * Bind schedule-related events
         */
        bindScheduleEvents: function() {
            var self = this;
            
            // Add time slot button
            $(document).on('click', '#fp-add-time-slot', function(e) {
                e.preventDefault();
                self.addTimeSlot();
            });
            
            // Remove time slot button
            $(document).on('click', '.fp-remove-time-slot', function(e) {
                e.preventDefault();
                $(this).closest('.fp-time-slot-row').remove();
                window.FPEsperienzeAdmin.updateSummaryTable();
            });
            
            // Meeting point dropdown change
            $(document).on('change', '.fp-meeting-point-select', function() {
                window.FPEsperienzeAdmin.updateSummaryTable();
            });
            
            // Toggle raw mode
            $(document).on('click', '#fp-toggle-raw-mode', function() {
                var showRaw = $(this).is(':checked');
                window.FPEsperienzeAdmin.toggleRawMode(showRaw);
            });
        },

        /**
         * Bind enhanced schedule builder events
         */
        bindScheduleBuilderEvents: function() {
            var self = this;
            
            // Time slot input changes
            $(document).on('change', 'input[name*=\"[start_time]\"], input[name*=\"[duration_min]\"], input[name*=\"[capacity]\"], input[name*=\"[lang]\"], select[name*=\"[meeting_point_id]\"], input[name*=\"[price_adult]\"], input[name*=\"[price_child]\"]', function() {
                window.FPEsperienzeAdmin.updateSummaryTable();
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
                window.FPEsperienzeAdmin.markAsChanged();
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
                var capacity = $slot.find('input[name*="[capacity]"]').val();
                
                if (!startTime) {
                    errorMessages.push('All time slots must have a start time');
                    hasErrors = true;
                }
                
                if (!capacity || capacity < 1) {
                    errorMessages.push('All time slots must have a capacity of at least 1');
                    hasErrors = true;
                }
            });
            
            if (hasErrors) {
                alert('Please fix the following errors:\n\n' + errorMessages.join('\n'));
                return false;
            }
            
            return true;
        },

        /**
         * Add a new time slot
         */
        addTimeSlot: function() {
            var $container = $('#fp-time-slots-container');
            var rowCount = $container.find('.fp-time-slot-row').length;
            var newIndex = rowCount;
            
            var template = $('#fp-time-slot-template').html();
            template = template.replace(/\{index\}/g, newIndex);
            
            var $newRow = $(template);
            $container.append($newRow);
            
            // Populate meeting points dropdown
            this.populateMeetingPointsDropdown($newRow.find('.fp-meeting-point-select'));
            
            // Update summary table
            window.FPEsperienzeAdmin.updateSummaryTable();
        },

        /**
         * Populate meeting points dropdown
         */
        populateMeetingPointsDropdown: function(selectElement) {
            var $select = $(selectElement);
            
            // Clear existing options except the first
            $select.find('option:not(:first)').remove();
            
            // Add meeting points from global variable if available
            if (typeof fp_meeting_points !== 'undefined') {
                $.each(fp_meeting_points, function(id, name) {
                    $select.append('<option value="' + id + '">' + name + '</option>');
                });
            }
        },

        /**
         * Initialize modern schedule builder
         */
        initModernScheduleBuilder: function() {
            this.validateContainers();
            this.initOverrideManager();
        },

        /**
         * Validate containers are present
         */
        validateContainers: function() {
            var timeSlotsContainer = $('#fp-time-slots-container');
            var overridesContainer = $('#fp-overrides-container .fp-overrides-container-clean');
            
            if (!timeSlotsContainer.length) {
                console.warn('FP Esperienze: Time slots container #fp-time-slots-container not found');
            } else {
                // Debug logging removed for production
            }
            
            if (!overridesContainer.length) {
                console.warn('FP Esperienze: Overrides container .fp-overrides-container-clean not found');
            } else {
                // Debug logging removed for production
            }
        },

        /**
         * Initialize override manager
         */
        initOverrideManager: function() {
            this.bindModernOverrideEvents();
        },

        /**
         * Initialize time slot manager
         */
        initTimeSlotManager: function() {
            this.bindModernTimeSlotEvents();
        },

        /**
         * Bind modern override events
         */
        bindModernOverrideEvents: function() {
            var self = this;
            
            $(document).on('click', '#fp-add-override', function(e) {
                e.preventDefault();
                self.addOverrideCard();
            });
        },

        /**
         * Bind modern time slot events  
         */
        bindModernTimeSlotEvents: function() {
            var self = this;
            
            $(document).on('click', '#fp-add-time-slot-clean', function(e) {
                e.preventDefault();
                self.addTimeSlotCardClean();
            });
        },

        /**
         * Add override card
         */
        addOverrideCard: function() {
            try {
                var $container = $('#fp-overrides-container .fp-overrides-container-clean');
                if (!$container.length) {
                    console.error('FP Esperienze: Override container not found');
                    return;
                }
                
                var index = $container.find('.fp-override-card-clean').length;
                var cardHTML = this.createOverrideCardHTML(index);
                var $newCard = $(cardHTML);
                
                $container.append($newCard);
                
                // Initialize datepicker if available
                if ($.fn.datepicker) {
                    $newCard.find('input[type="date"]').datepicker({
                        dateFormat: 'yy-mm-dd',
                        changeMonth: true,
                        changeYear: true
                    });
                }
                
                // Show user feedback
                if (window.FPEsperienzeAdmin && window.FPEsperienzeAdmin.showUserFeedback) {
                    window.FPEsperienzeAdmin.showUserFeedback('Override added successfully!', 'success');
                }
                
                // Debug logging removed for production
                
            } catch (error) {
                console.error('FP Esperienze: Error adding override card:', error);
            }
        },

        /**
         * Create override card HTML
         */
        createOverrideCardHTML: function(index) {
            return `
                <div class="fp-override-card-clean" data-index="${index}">
                    <input type="hidden" name="overrides[${index}][id]" value="">
                    <div class="fp-override-header-clean">
                        <div class="fp-override-date-field-clean">
                            <label for="override-date-${index}">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                Date <span class="required">*</span>
                            </label>
                            <input type="date"
                                   id="override-date-${index}"
                                   name="overrides[${index}][date]"
                                   required>
                        </div>
                        <div class="fp-override-actions-clean">
                            <div class="fp-override-checkbox-clean">
                                <input type="checkbox"
                                       name="overrides[${index}][is_closed]"
                                       value="1"
                                       id="override-closed-${index}">
                                <label for="override-closed-${index}">Closed</label>
                            </div>
                            <button type="button" class="fp-override-remove-clean button">
                                <span class="dashicons dashicons-trash"></span>
                                Remove
                            </button>
                        </div>
                    </div>
                    <div class="fp-override-fields-clean">
                        <div class="fp-override-grid-clean">
                            <div class="fp-override-field-clean">
                                <label for="override-capacity-${index}">Capacity Override</label>
                                <input type="number"
                                       id="override-capacity-${index}"
                                       name="overrides[${index}][capacity_override]"
                                       placeholder="Leave empty = use default"
                                       min="0"
                                       step="1">
                            </div>
                            <div class="fp-override-field-clean">
                                <label for="override-reason-${index}">Reason/Note</label>
                                <input type="text"
                                       id="override-reason-${index}"
                                       name="overrides[${index}][reason]"
                                       placeholder="Optional note (e.g., Holiday, Maintenance)">
                            </div>
                            <div class="fp-override-field-clean">
                                <label for="override-adult-price-${index}">Adult Price</label>
                                <input type="number"
                                       id="override-adult-price-${index}"
                                       name="overrides[${index}][price_adult]"
                                       placeholder="Leave empty = use default"
                                       min="0"
                                       step="0.01">
                            </div>
                            <div class="fp-override-field-clean">
                                <label for="override-child-price-${index}">Child Price</label>
                                <input type="number"
                                       id="override-child-price-${index}"
                                       name="overrides[${index}][price_child]"
                                       placeholder="Leave empty = use default"
                                       min="0"
                                       step="0.01">
                            </div>
                        </div>
                    </div>
                </div>
            `;
        },

        /**
         * Add time slot card (clean version)
         */
        addTimeSlotCardClean: function() {
            try {
                // The container for time slots is the element with ID fp-time-slots-container
                var $container = $('#fp-time-slots-container');
                if (!$container.length) {
                    console.error('FP Esperienze: Time slots container not found');
                    return;
                }

                var index = $container.find('.fp-time-slot-card-clean').length;
                var cardHTML = this.createTimeSlotCardHTMLClean(index);
                var $newCard = $(cardHTML);

                $container.append($newCard);
                
                // Show user feedback
                if (window.FPEsperienzeAdmin && window.FPEsperienzeAdmin.showUserFeedback) {
                    window.FPEsperienzeAdmin.showUserFeedback('Time slot added successfully!', 'success');
                }
                
                // Debug logging removed for production
                
            } catch (error) {
                console.error('FP Esperienze: Error adding time slot card:', error);
            }
        },

        /**
         * Create time slot card HTML (clean version)
         */
        createTimeSlotCardHTMLClean: function(index) {
            return `
                <div class="fp-time-slot-card fp-time-slot-card-clean" data-index="${index}">
                    <div class="fp-time-slot-content-clean">
                        <div class="fp-time-slot-header-clean">
                            <div class="fp-time-field-clean">
                                <label for="time-${index}">
                                    <span class="dashicons dashicons-clock"></span>
                                    Start Time <span class="required">*</span>
                                </label>
                                <input type="time" id="time-${index}" name="builder_slots[${index}][start_time]" required />
                            </div>

                            <div class="fp-days-field-clean">
                                <label>
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    Days of Week <span class="required">*</span>
                                </label>
                                <div class="fp-days-pills-clean">
                                    ${this.createDayPills(index)}
                                </div>
                            </div>

                            <div class="fp-slot-actions-clean">
                                <button type="button" class="fp-remove-time-slot-clean button">
                                    <span class="dashicons dashicons-trash"></span>
                                    Remove
                                </button>
                            </div>
                        </div>

                        <div class="fp-overrides-grid-clean">
                            <div class="fp-override-field-clean">
                                <label>Duration (minutes) <span class="required">*</span></label>
                                <input type="number" name="builder_slots[${index}][duration_min]" min="1" required />
                            </div>
                            <div class="fp-override-field-clean">
                                <label>Capacity <span class="required">*</span></label>
                                <input type="number" name="builder_slots[${index}][capacity]" min="1" required />
                            </div>
                            <div class="fp-override-field-clean">
                                <label>Language <span class="required">*</span></label>
                                <input type="text" name="builder_slots[${index}][lang]" maxlength="10" required />
                            </div>
                            <div class="fp-override-field-clean">
                                <label>Meeting Point <span class="required">*</span></label>
                                <select name="builder_slots[${index}][meeting_point_id]" class="fp-meeting-point-select" required>
                                    <option value="" disabled selected>Select meeting point</option>
                                </select>
                            </div>
                            <div class="fp-override-field-clean">
                                <label>Adult Price <span class="required">*</span></label>
                                <input type="number" name="builder_slots[${index}][price_adult]" min="0" step="0.01" required />
                            </div>
                            <div class="fp-override-field-clean">
                                <label>Child Price <span class="required">*</span></label>
                                <input type="number" name="builder_slots[${index}][price_child]" min="0" step="0.01" required />
                            </div>
                        </div>
                    </div>
                </div>
            `;
        },

        /**
         * Create day pills HTML
         */
        createDayPills: function(index) {
            const days = [
                {value: '1', label: 'Mon'},
                {value: '2', label: 'Tue'},
                {value: '3', label: 'Wed'},
                {value: '4', label: 'Thu'},
                {value: '5', label: 'Fri'},
                {value: '6', label: 'Sat'},
                {value: '0', label: 'Sun'}
            ];

            return days.map(day => `
                <div class="fp-day-pill-clean">
                    <input type="checkbox" id="day-${index}-${day.value}" name="builder_slots[${index}][days][]" value="${day.value}">
                    <label for="day-${index}-${day.value}">${day.label}</label>
                </div>
            `).join('');
        }
    };

})(jQuery);