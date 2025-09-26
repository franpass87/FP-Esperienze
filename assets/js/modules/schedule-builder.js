/**
 * FP Esperienze - Schedule Builder Module
 * Handles time slot management and schedule building functionality
 */

(function() {
    if (typeof jQuery === 'undefined') {
        console.error('FP Esperienze: jQuery is required for the schedule builder module.');
        return;
    }
    (function($) {
        'use strict';

    window.FPEsperienzeScheduleBuilder = {

        /**
         * Dispatch an event for the admin controller
         *
         * @param {string} eventName
         * @param {Object} [detail]
         */
        dispatchAdminEvent: function(eventName, detail) {
            if (!window.FPEsperienzeAdmin) {
                console.warn('FP Esperienze Admin controller not found for event "' + eventName + '"');
            }
            $(document).trigger(eventName, detail || {});
        },
        
        /**
         * Initialize schedule builder
         */
        init: function() {
            this.bindScheduleBuilderEvents();
            this.initModernScheduleBuilder();

            if ($('#fp-time-slot-template').length) {
                $(document).off('click.fp-modern-add-time-slot');
                this.bindScheduleEvents();
            } else {
                $(document).off('click.fp-legacy-add-time-slot');
                this.initTimeSlotManager();
            }
        },

        /**
         * Bind schedule-related events
         */
        bindScheduleEvents: function() {
            var self = this;
            
            // Add time slot button
            $(document).off('click.fp-legacy-add-time-slot', '#fp-add-time-slot');
            $(document).on('click.fp-legacy-add-time-slot', '#fp-add-time-slot', function(e) {
                e.preventDefault();
                self.addTimeSlot();
            });

            // Remove time slot button
            $(document).on('click', '.fp-remove-time-slot', function(e) {
                e.preventDefault();
                $(this).closest('.fp-time-slot-row').remove();
                self.dispatchAdminEvent('fp:updateSummaryTable');
            });

            // Duplicate time slot card (clean markup)
            $(document).off('click.fp-duplicate-slot', '.fp-duplicate-time-slot-clean');
            $(document).on('click.fp-duplicate-slot', '.fp-duplicate-time-slot-clean', function(e) {
                e.preventDefault();

                var $button = $(this);
                var $sourceCard = $button.closest('.fp-time-slot-card-clean');
                if (!$sourceCard.length) {
                    return;
                }

                $button.prop('disabled', true);

                try {
                    var slotData = self.collectTimeSlotCardData($sourceCard);
                    var $newCard = self.addTimeSlotCardClean(slotData, $sourceCard);

                    if ($newCard && $newCard.length) {
                        self.dispatchAdminEvent('fp:showUserFeedback', {
                            message: 'Time slot duplicated!',
                            type: 'success'
                        });
                    }
                } catch (error) {
                    console.error('FP Esperienze: Error duplicating time slot card:', error);
                } finally {
                    setTimeout(function() {
                        $button.prop('disabled', false);
                    }, 200);
                }
            });
            
            // Meeting point dropdown change
            $(document).on('change', '.fp-meeting-point-select', function() {
                self.dispatchAdminEvent('fp:updateSummaryTable');
            });
            
            // Toggle raw mode when available
            if ($('#fp-toggle-raw-mode').length) {
                $(document).on('click', '#fp-toggle-raw-mode', function() {
                    var showRaw = $(this).is(':checked');
                    self.dispatchAdminEvent('fp:toggleRawMode', { showRaw: showRaw });
                });
            }
        },

        /**
         * Bind enhanced schedule builder events
         */
        bindScheduleBuilderEvents: function() {
            var self = this;
            
            // Time slot input changes
            $(document).on('change', 'input[name*=\"[start_time]\"], input[name*=\"[duration_min]\"], input[name*=\"[capacity]\"], input[name*=\"[lang]\"], select[name*=\"[meeting_point_id]\"], input[name*=\"[price_adult]\"], input[name*=\"[price_child]\"]', function() {
                self.dispatchAdminEvent('fp:updateSummaryTable');
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
                self.dispatchAdminEvent('fp:markAsChanged');
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
            var existingIndexes = $container
                .find('.fp-time-slot-row')
                .map(function() {
                    return parseInt($(this).attr('data-index'), 10);
                })
                .get();
            var newIndex = existingIndexes.length
                ? Math.max.apply(null, existingIndexes) + 1
                : 0;
            
            var template = $('#fp-time-slot-template').html();
            template = template.replace(/\{index\}/g, newIndex);
            
            var $newRow = $(template).attr('data-index', newIndex);
            $container.append($newRow);
            
            // Populate meeting points dropdown
            this.populateMeetingPointsDropdown($newRow.find('.fp-meeting-point-select'));
            
            // Update summary table
            this.dispatchAdminEvent('fp:updateSummaryTable');
        },

        /**
         * Populate meeting points dropdown
         */
        populateMeetingPointsDropdown: function(selectElement) {
            var $select = $(selectElement);

            // Clear existing options except the first
            $select.find('option:not(:first)').remove();

            // Add meeting points from localized data if available
            var meetingPoints = (window.fp_esperienze_admin && fp_esperienze_admin.fp_meeting_points)
                ? fp_esperienze_admin.fp_meeting_points
                : {};

            if ($.isEmptyObject(meetingPoints)) {
                console.error('FP Esperienze: no meeting points available');
                $select.append($('<option>').val('').text('No meeting points available').prop('disabled', true));
                return;
            }

            $.each(meetingPoints, function(id, name) {
                var idInt = parseInt(id, 10);
                var nameStr = String(name);

                if (!isNaN(idInt) && nameStr) {
                    const option = $('<option>').val(idInt).text(nameStr);
                    $select.append(option);
                }
            });
        },

        /**
         * Initialize modern schedule builder
         */
        initModernScheduleBuilder: function() {
            this.validateContainers();
            this.initOverrideManager();
            this.updateSlotChips();
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
            $(document).on('click', '#fp-add-override', function(e) {
                e.preventDefault();

                if (window.FPEsperienzeAdmin &&
                    typeof window.FPEsperienzeAdmin.addOverrideCardClean === 'function') {
                    window.FPEsperienzeAdmin.addOverrideCardClean();
                } else {
                    console.error('FP Esperienze: addOverrideCardClean() is not available');
                }
            });
        },

        /**
         * Bind modern time slot events  
         */
        bindModernTimeSlotEvents: function() {
            var self = this;
            
            $(document).off('click.fp-modern-add-time-slot', '#fp-add-time-slot');
            $(document).on('click.fp-modern-add-time-slot', '#fp-add-time-slot', function(e) {
                e.preventDefault();
                self.addTimeSlotCardClean();
            });

            $(document).off('click.fp-modern-duplicate-time-slot', '.fp-duplicate-time-slot-clean');
            $(document).on('click.fp-modern-duplicate-time-slot', '.fp-duplicate-time-slot-clean', function(e) {
                e.preventDefault();

                var $button = $(this);
                var $sourceCard = $button.closest('.fp-time-slot-card-clean');
                if (!$sourceCard.length) {
                    return;
                }

                $button.prop('disabled', true);

                try {
                    var slotData = self.collectTimeSlotCardData($sourceCard);
                    var $newCard = self.addTimeSlotCardClean(slotData, $sourceCard);

                    if ($newCard && $newCard.length) {
                        self.dispatchAdminEvent('fp:showUserFeedback', {
                            message: 'Time slot duplicated!',
                            type: 'success'
                        });
                    }
                } catch (error) {
                    console.error('FP Esperienze: Error duplicating time slot card:', error);
                } finally {
                    setTimeout(function() {
                        $button.prop('disabled', false);
                    }, 200);
                }
            });
        },


        /**
         * Add time slot card (clean version)
         */
        addTimeSlotCardClean: function(slotData, insertAfterCard) {
            slotData = slotData || null;
            insertAfterCard = insertAfterCard || null;

            try {
                var $container = $('#fp-time-slots-container');
                if (!$container.length) {
                    console.error('FP Esperienze: Time slots container not found');
                    return null;
                }

                var existingIndexes = $container
                    .find('.fp-time-slot-card-clean')
                    .map(function() {
                        var parsed = parseInt($(this).attr('data-index'), 10);
                        return isNaN(parsed) ? null : parsed;
                    })
                    .get()
                    .filter(function(value) {
                        return value !== null;
                    });

                var index = existingIndexes.length
                    ? Math.max.apply(null, existingIndexes) + 1
                    : 0;
                var cardHTML = this.createTimeSlotCardHTMLClean(index);
                if (!cardHTML) {
                    console.error('FP Esperienze: Unable to build time slot card HTML');
                    return null;
                }

                var $newCard = $(cardHTML).attr('data-index', index).addClass('fp-newly-added');

                if (insertAfterCard && insertAfterCard.length) {
                    insertAfterCard.after($newCard);
                } else {
                    $container.append($newCard);
                }

                this.populateMeetingPointsDropdown($newCard.find('.fp-meeting-point-select'));

                if (slotData) {
                    this.applyTimeSlotCardData($newCard, slotData);
                }

                setTimeout(function() {
                    $newCard.removeClass('fp-newly-added');
                }, 1400);

                this.updateSlotChips();
                this.dispatchAdminEvent('fp:markAsChanged');
                this.dispatchAdminEvent('fp:updateSummaryTable');

                if (!slotData) {
                    this.dispatchAdminEvent('fp:showUserFeedback', {
                        message: 'Time slot added successfully!',
                        type: 'success'
                    });
                }

                return $newCard;
            } catch (error) {
                console.error('FP Esperienze: Error adding time slot card:', error);
                return null;
            }
        },

        /**
         * Create time slot card HTML (clean version)
         */
        createTimeSlotCardHTMLClean: function(index) {
            var slotNumber = (index + 1).toString().padStart(2, '0');
            var slotLabel = `Slot ${slotNumber}`;

            return `
                <div class="fp-time-slot-card fp-time-slot-card-clean" data-index="${index}">
                    <div class="fp-time-slot-content-clean">
                        <div class="fp-slot-card-inner">
                            <div class="fp-slot-rail" aria-hidden="true">
                                <span class="fp-slot-rail__dot"></span>
                                <span class="fp-slot-rail__line"></span>
                            </div>
                            <div class="fp-slot-content">
                                <header class="fp-slot-header">
                                    <div class="fp-slot-header__meta">
                                        <span class="fp-slot-chip">${slotLabel}</span>
                                        <p class="fp-slot-subtitle">Recurring weekly availability</p>
                                    </div>
                                    <div class="fp-slot-actions-clean">
                                        <button type="button" class="button fp-duplicate-time-slot-clean">
                                            <span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
                                            Duplicate slot
                                        </button>
                                        <button type="button" class="fp-remove-time-slot-clean button button-link-delete">
                                            <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                            Remove slot
                                        </button>
                                    </div>
                                </header>

                                <div class="fp-slot-primary">
                                    <div class="fp-slot-primary__field fp-time-field-clean">
                                        <label for="time-${index}" class="fp-slot-field-label">
                                            <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                                            <span class="fp-slot-label-text">Start time <span class="required">*</span></span>
                                        </label>
                                        <div class="fp-slot-time-input">
                                            <input type="time" id="time-${index}" name="builder_slots[${index}][start_time]" required />
                                        </div>
                                        <p class="fp-slot-hint">Guests see this start time in their timezone.</p>
                                    </div>

                                    <div class="fp-slot-primary__field fp-days-field-clean">
                                        <label class="fp-slot-field-label">
                                            <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                                            <span class="fp-slot-label-text">Days of week <span class="required">*</span></span>
                                        </label>
                                        <div class="fp-slot-day-grid fp-days-pills-clean">
                                            ${this.createDayPills(index)}
                                        </div>
                                        <p class="fp-slot-hint">Select at least one day to activate this slot.</p>
                                    </div>

                                    <div class="fp-slot-primary__field fp-slot-insight">
                                        <h4>Slot notes</h4>
                                        <ul class="fp-slot-insight-list">
                                            <li><span class="dashicons dashicons-update" aria-hidden="true"></span> Repeats weekly on your chosen days.</li>
                                            <li><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span> Overrides below replace product defaults.</li>
                                            <li><span class="dashicons dashicons-visibility" aria-hidden="true"></span> Customers only see published, in-season slots.</li>
                                        </ul>
                                    </div>
                                </div>

                                <section class="fp-overrides-section-clean" aria-label="Time slot overrides">
                                    <div class="fp-slot-detail-header">
                                        <div>
                                            <h4>Booking details</h4>
                                            <p>Adjust capacity, pricing, and duration for this specific time slot.</p>
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
                                            <label>Meeting point <span class="required">*</span></label>
                                            <select name="builder_slots[${index}][meeting_point_id]" class="fp-meeting-point-select" required>
                                                <option value="" disabled selected>Select meeting point</option>
                                            </select>
                                        </div>
                                        <div class="fp-override-field-clean">
                                            <label>Adult price <span class="required">*</span></label>
                                            <input type="number" name="builder_slots[${index}][price_adult]" min="0" step="0.01" required />
                                        </div>
                                        <div class="fp-override-field-clean">
                                            <label>Child price <span class="required">*</span></label>
                                            <input type="number" name="builder_slots[${index}][price_child]" min="0" step="0.01" required />
                                        </div>
                                    </div>
                                </section>
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
                { value: '1', label: 'Mon', full: 'Monday' },
                { value: '2', label: 'Tue', full: 'Tuesday' },
                { value: '3', label: 'Wed', full: 'Wednesday' },
                { value: '4', label: 'Thu', full: 'Thursday' },
                { value: '5', label: 'Fri', full: 'Friday' },
                { value: '6', label: 'Sat', full: 'Saturday' },
                { value: '0', label: 'Sun', full: 'Sunday' }
            ];

            return days.map(day => `
                <div class="fp-day-pill-clean">
                    <input type="checkbox" id="day-${index}-${day.value}" name="builder_slots[${index}][days][]" value="${day.value}">
                    <label for="day-${index}-${day.value}" title="${day.full}">
                        <span class="fp-slot-day__abbr">${day.label}</span>
                        <span class="fp-slot-day__full">${day.full}</span>
                    </label>
                </div>
            `).join('');
        },

        collectTimeSlotCardData: function($card) {
            if (!$card || !$card.length) {
                return {};
            }

            return {
                start_time: ($card.find('input[name*="[start_time]"]').val() || '').trim(),
                days: $card.find('input[name*="[days][]"]:checked').map(function() {
                    return String($(this).val());
                }).get(),
                duration_min: ($card.find('input[name*="[duration_min]"]').val() || '').trim(),
                capacity: ($card.find('input[name*="[capacity]"]').val() || '').trim(),
                lang: ($card.find('input[name*="[lang]"]').val() || '').trim(),
                meeting_point_id: ($card.find('select[name*="[meeting_point_id]"]').val() || '').toString(),
                price_adult: ($card.find('input[name*="[price_adult]"]').val() || '').trim(),
                price_child: ($card.find('input[name*="[price_child]"]').val() || '').trim()
            };
        },

        applyTimeSlotCardData: function($card, slotData) {
            if (!$card || !$card.length || !slotData) {
                return;
            }

            if (slotData.start_time) {
                $card.find('input[name*="[start_time]"]').val(slotData.start_time);
            }

            $card.find('input[name*="[days][]"]').prop('checked', false);
            if (Array.isArray(slotData.days) && slotData.days.length) {
                slotData.days.forEach(function(dayValue) {
                    $card.find('input[name*="[days][]"][value="' + String(dayValue) + '"]').prop('checked', true);
                });
            }

            if (slotData.duration_min) {
                $card.find('input[name*="[duration_min]"]').val(slotData.duration_min);
            }

            if (slotData.capacity) {
                $card.find('input[name*="[capacity]"]').val(slotData.capacity);
            }

            if (slotData.lang) {
                $card.find('input[name*="[lang]"]').val(slotData.lang);
            }

            if (slotData.meeting_point_id) {
                var meetingValue = String(slotData.meeting_point_id);
                var $select = $card.find('select[name*="[meeting_point_id]"]');
                var meetingPoints = (window.fp_esperienze_admin && fp_esperienze_admin.fp_meeting_points) ? fp_esperienze_admin.fp_meeting_points : {};
                var optionExists = $select.find('option').filter(function() {
                    return String($(this).val()) === meetingValue;
                }).length > 0;

                if (!optionExists) {
                    var label = meetingPoints && meetingPoints[meetingValue] ? meetingPoints[meetingValue] : meetingValue;
                    $select.append($('<option>').val(meetingValue).text(label));
                }

                $select.val(meetingValue);
            }

            if (slotData.price_adult) {
                $card.find('input[name*="[price_adult]"]').val(slotData.price_adult);
            }

            if (slotData.price_child) {
                $card.find('input[name*="[price_child]"]').val(slotData.price_child);
            }
        },

        updateSlotChips: function() {
            $('#fp-time-slots-container .fp-time-slot-card-clean').each(function(position) {
                var $chip = $(this).find('.fp-slot-chip');
                if (!$chip.length) {
                    return;
                }

                var slotNumber = (position + 1).toString().padStart(2, '0');
                $chip.text('Slot ' + slotNumber);
            });
        }
    };

    })(jQuery);
})();
