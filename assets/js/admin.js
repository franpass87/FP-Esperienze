/**
 * FP Esperienze Admin JavaScript
 */

(function() {
    if (typeof jQuery === 'undefined') {
        console.error('FP Esperienze: jQuery is required for the admin script.');
        return;
    }
    (function($) {
        'use strict';

    let __ = ( text ) => text;
    let sprintf = ( template, ...args ) => {
        let i = 0;
        return template.replace(/%s|%d/g, () => ( i < args.length ? args[i++] : '' ));
    };

    if ( window.wp && wp.i18n ) {
        ( { __, sprintf } = wp.i18n );
    } else {
        console.warn( 'wp.i18n not found; translations will not be available.' );
    }

    // Prevent multiple script execution
    if (window.FPEsperienzeAdmin && window.FPEsperienzeAdmin.initialized) {
        return;
    }

    $(document).ready(function() {
        // Double-check initialization on DOM ready
        if (window.FPEsperienzeAdmin && window.FPEsperienzeAdmin.initialized) {
            return;
        }
        
        // Initialize admin functionality
        FPEsperienzeAdmin.init();
        
        // Mark as initialized
        window.FPEsperienzeAdmin.initialized = true;
    });

    window.FPEsperienzeAdmin = {
        
        // Track unsaved changes
        hasUnsavedChanges: false,
        
        // Prevent double initialization
        initialized: false,
        
        // Debug utility
        debug: function(message, data) {
            if (typeof fpEsperienzeAdmin !== 'undefined' && fpEsperienzeAdmin.debug === '1') {
                // Debug logging removed for production
            }
        },

        /**
         * Retrieve and validate localization data.
         *
         * @param {Array} required List of required top-level fields.
         * @return {Object|null} Localization object or null if missing.
         */
        ensureAdminData: function(required = []) {
            const data = window.fp_esperienze_admin;
            if (!data) {
                console.error('fp_esperienze_admin localization object not found.');
                this.showUserFeedback(__('Required localization data is missing.', 'fp-esperienze'), 'error', 8000);
                return null;
            }

            for (let i = 0; i < required.length; i++) {
                if (typeof data[required[i]] === 'undefined' || data[required[i]] === '') {
                    console.error('fp_esperienze_admin missing required field: ' + required[i]);
                    this.showUserFeedback(__('Required localization data is missing.', 'fp-esperienze'), 'error', 8000);
                    return null;
                }
            }

            return data;
        },

        /**
         * Safely retrieve a localized string.
         *
         * @param {string} key      String key in fp_esperienze_admin.strings.
         * @param {string} fallback Fallback string if key is missing.
         * @return {string} Localized string.
         */
        getAdminString: function(key, fallback) {
            const data = this.ensureAdminData(['strings']);
            if (data && data.strings && data.strings[key]) {
                return data.strings[key];
            }

            console.error('Localization string "' + key + '" is missing.');
            this.showUserFeedback(__('Required localization data is missing.', 'fp-esperienze'), 'error', 8000);
            return fallback;
        },

        /**
         * Retrieve localized weekday labels.
         *
         * @param {string} format Either 'abbrev' or 'names'.
         * @return {Object} Mapping of weekday numbers (Mon-Sun order) to labels.
         */
        getWeekdayLabels: function(format = 'abbrev') {
            if (!this._weekdayCache) {
                this._weekdayCache = {};
            }

            if (this._weekdayCache[format]) {
                return this._weekdayCache[format];
            }

            var fallback;
            if (format === 'names') {
                fallback = {
                    '1': 'Monday',
                    '2': 'Tuesday',
                    '3': 'Wednesday',
                    '4': 'Thursday',
                    '5': 'Friday',
                    '6': 'Saturday',
                    '0': 'Sunday'
                };
            } else {
                fallback = {
                    '1': 'Mon',
                    '2': 'Tue',
                    '3': 'Wed',
                    '4': 'Thu',
                    '5': 'Fri',
                    '6': 'Sat',
                    '0': 'Sun'
                };
            }

            try {
                var data = this.ensureAdminData(['strings']);
                var strings = data && data.strings ? data.strings : null;
                var key = format === 'names' ? 'weekday_names' : 'weekday_abbrev';

                if (strings && strings[key] && typeof strings[key] === 'object') {
                    var localized = Object.assign({}, fallback);
                    ['1', '2', '3', '4', '5', '6', '0'].forEach(function(dayKey) {
                        if (strings[key][dayKey]) {
                            localized[dayKey] = strings[key][dayKey];
                        }
                    });

                    this._weekdayCache[format] = localized;
                    return localized;
                }
            } catch (error) {
                console.warn('FP Esperienze: Unable to resolve localized weekday labels.', error);
            }

            this._weekdayCache[format] = fallback;
            return fallback;
        },

        getWeekdayAbbreviations: function() {
            return this.getWeekdayLabels('abbrev');
        },

        getWeekdayNames: function() {
            return this.getWeekdayLabels('names');
        },
        
        /**
         * Initialize
         */
        init: function() {
            const data = this.ensureAdminData(['banner_offset']);
            if (data && typeof data.banner_offset !== 'undefined') {
                document.documentElement.style.setProperty('--fp-banner-offset', data.banner_offset + 'px');
            }

            this.handleProductTypeChange();
            this.handleExperienceTypeChange(); // Add experience type handling
            this.bindEvents();
            this.initBookingsPage();

            // Initialize enhanced schedule builder features
            if ($('#fp-time-slots-container').length) {
                this.enhanceAccessibility();
            }

            // Initialize enhanced features
            this.initializeEnhancements();
            this.initExperienceGalleryField();
            this.dedupeProductTypeField();
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
                FPEsperienzeAdmin.dedupeProductTypeField();
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
         * Remove duplicated product type dropdowns inside the experience panel.
         */
        dedupeProductTypeField: function() {
            var $experiencePanel = $('#experience_product_data');
            if (!$experiencePanel.length) {
                return;
            }

            var $productTypeFields = $experiencePanel.find('#product-type').closest('.form-field');
            if ($productTypeFields.length > 1) {
                $productTypeFields.slice(1).remove();
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
                // Update virtual/downloadable settings for experiences
                $('#_virtual').prop('checked', true).trigger('change');
                $('#_downloadable').prop('checked', false).trigger('change');
                
                // Hide incompatible elements
                $('.show_if_simple, .show_if_variable, .show_if_grouped, .show_if_external').hide();
                
                // Let WooCommerce handle tab visibility naturally
                // Don't force show experience elements - they should only show when their tab is active
            } else {
                // Hide experience-specific elements when not experience type
                $('.show_if_experience').hide();
                $('#experience_product_data, #dynamic_pricing_product_data').hide();
            }
        },

        /**
         * Handle experience type change (experience vs event)
         */
        handleExperienceTypeChange: function() {
            var $experienceTypeField = $('#_fp_experience_type');
            
            if ($experienceTypeField.length) {
                // Initialize visibility based on current value
                this.toggleExperienceTypeFields($experienceTypeField.val());
                
                // Listen for experience type changes
                $experienceTypeField.on('change', function() {
                    FPEsperienzeAdmin.toggleExperienceTypeFields($(this).val());
                });
            }
        },

        /**
         * Toggle fields based on experience type (experience vs event)
         */
        toggleExperienceTypeFields: function(experienceType) {
            var $recurringSchedules = $('#fp-recurring-schedules');
            var $eventSchedules = $('#fp-event-schedules');
            var $overridesSection = $('#fp-overrides-section');
            var $recurringHeading = $recurringSchedules.find('.hndle span');
            var $eventHeading = $eventSchedules.find('.hndle span');
            var $overridesHeading = $overridesSection.find('.hndle span');

            if (experienceType === 'event') {
                // Show event sections, hide experience sections
                $recurringSchedules.hide();
                $eventSchedules.show();
                
                // Hide overrides for events (events have fixed dates, no need for overrides)
                $overridesSection.hide();

                // Update section descriptions
                if ($eventHeading.length) {
                    $eventHeading.text(fp_esperienze_admin.strings.event_schedules || 'Event dates & times');
                }

            } else {
                // Show experience sections, hide event sections
                $recurringSchedules.show();
                $eventSchedules.hide();
                $overridesSection.show();

                // Restore section descriptions
                if ($recurringHeading.length) {
                    $recurringHeading.text(fp_esperienze_admin.strings.recurring_schedules || 'Recurring schedule');
                }
            }

            if ($overridesHeading.length) {
                $overridesHeading.text(fp_esperienze_admin.strings.schedule_overrides || 'Schedule exceptions');
            }

            // Add body class for CSS targeting
            $('body').removeClass('fp-experience-type-experience fp-experience-type-event')
                     .addClass('fp-experience-type-' + (experienceType || 'experience'));
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Schedule management
            this.bindScheduleEvents();

            // Event schedule management
            this.bindEventScheduleEvents();

            // Modern schedule and override management - REFACTORED
            this.initModernScheduleBuilder();

            // Reset unsaved changes after automatic builder setup
            this.clearUnsavedChanges();
        },

        /**
         * Initialize experience gallery field handling.
         */
        initExperienceGalleryField: function() {
            const $group = $('.fp-exp-gallery-group');

            if (!$group.length) {
                return;
            }

            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                console.warn('FP Esperienze: wp.media not available for gallery field.');
                return;
            }

            const self = this;
            const $fieldWrapper = $group.find('#fp-exp-gallery-field');
            const $list = $fieldWrapper.find('.fp-exp-gallery-list');
            const $empty = $fieldWrapper.find('.fp-exp-gallery-empty');
            const $clear = $group.find('.fp-exp-gallery-clear');
            const $status = $fieldWrapper.find('.fp-exp-gallery-status');
            const $addButton = $group.find('.fp-exp-gallery-add');
            let mediaFrame = null;

            function notifyUnsavedChange() {
                if (window.FPAdminUXEnhancer && typeof window.FPAdminUXEnhancer.flagUnsavedChange === 'function') {
                    window.FPAdminUXEnhancer.flagUnsavedChange(true);
                }

                if (window.FPEsperienzeAdmin && typeof window.FPEsperienzeAdmin.flagUnsavedChange === 'function') {
                    window.FPEsperienzeAdmin.flagUnsavedChange();
                } else if (window.FPEsperienzeAdmin) {
                    window.FPEsperienzeAdmin.hasUnsavedChanges = true;
                    if (typeof window.FPEsperienzeAdmin.showUnsavedChangesWarning === 'function') {
                        window.FPEsperienzeAdmin.showUnsavedChangesWarning();
                    }
                }
            }

            if ($clear.length) {
                $clear.attr('aria-label', self.getAdminString('gallery_clear_all', 'Remove all'));
            }

            if ($addButton.length) {
                $addButton.attr({
                    'aria-controls': 'fp-exp-gallery-list',
                    'aria-label': self.getAdminString('gallery_add_images', 'Add images'),
                    title: self.getAdminString('gallery_drag_instruction', 'Drag and drop to change image order.')
                });
            }

            function updateEmptyState() {
                const $items = $list.children('.fp-exp-gallery-item');
                const hasItems = $items.length > 0;

                if ($empty.length) {
                    $empty.toggle(!hasItems);
                }

                if ($clear.length) {
                    $clear.toggle(hasItems);

                    if (hasItems) {
                        $clear.removeAttr('aria-disabled');
                    } else {
                        $clear.attr('aria-disabled', 'true');
                    }
                }

                if ($status.length) {
                    if (hasItems) {
                        const baseText = self.getAdminString('gallery_items_count', '%d gallery images selected');
                        const instruction = self.getAdminString('gallery_drag_instruction', 'Drag and drop to change image order.');
                        $status.text(sprintf(baseText, $items.length) + ' ' + instruction);
                    } else {
                        $status.text(self.getAdminString('gallery_empty_state', 'No gallery images selected.'));
                    }
                }
            }

            if (typeof $list.sortable === 'function') {
                $list.sortable({
                    items: '.fp-exp-gallery-item',
                    axis: 'x',
                    tolerance: 'pointer',
                    stop: function() {
                        updateEmptyState();
                        notifyUnsavedChange();
                    }
                });
            }

            $group.on('click', '.fp-exp-gallery-add', function(event) {
                event.preventDefault();

                if (!mediaFrame) {
                    mediaFrame = wp.media({
                        title: self.getAdminString('gallery_frame_title', 'Experience gallery'),
                        button: {
                            text: self.getAdminString('gallery_frame_button', 'Use these images')
                        },
                        library: {
                            type: 'image'
                        },
                        multiple: true
                    });

                    mediaFrame.on('select', function() {
                        const selection = mediaFrame.state().get('selection');
                        if (!selection) {
                            return;
                        }

                        selection.each(function(attachment) {
                            const data = typeof attachment.toJSON === 'function' ? attachment.toJSON() : attachment;
                            const id = data && data.id ? data.id : attachment.id;

                            if (!id) {
                                return;
                            }

                            if ($list.find('.fp-exp-gallery-item[data-attachment-id="' + id + '"]').length) {
                                return;
                            }

                            let thumbUrl = data && data.url ? data.url : '';
                            if (data && data.sizes) {
                                if (data.sizes.thumbnail) {
                                    thumbUrl = data.sizes.thumbnail.url;
                                } else if (data.sizes.medium) {
                                    thumbUrl = data.sizes.medium.url;
                                } else if (data.sizes.full) {
                                    thumbUrl = data.sizes.full.url;
                                }
                            }

                            const altText = data && (data.alt || data.title) ? (data.alt || data.title) : '';

                            const $item = $('<li/>', {
                                'class': 'fp-exp-gallery-item',
                                'data-attachment-id': id
                            });

                            const $imageWrapper = $('<div/>', { 'class': 'fp-exp-gallery-item__image' });
                            if (thumbUrl) {
                                $('<img/>', {
                                    src: thumbUrl,
                                    alt: altText
                                }).appendTo($imageWrapper);
                            } else {
                                $('<div/>', {
                                    'class': 'fp-exp-gallery-item__placeholder',
                                    text: self.getAdminString('gallery_frame_title', 'Experience gallery')
                                }).appendTo($imageWrapper);
                            }

                            const $remove = $('<button/>', {
                                type: 'button',
                                'class': 'button-link-delete fp-exp-gallery-remove',
                                'aria-label': self.getAdminString('gallery_remove_image', 'Remove image')
                            }).text('×');

                            const $hidden = $('<input/>', {
                                type: 'hidden',
                                name: '_fp_exp_gallery_images[]',
                                value: id
                            });

                            $item.append($imageWrapper, $remove, $hidden);
                            $list.append($item);
                            notifyUnsavedChange();
                        });

                        updateEmptyState();
                    });
                }

                mediaFrame.open();
            });

            $group.on('click', '.fp-exp-gallery-remove', function(event) {
                event.preventDefault();
                $(this).closest('.fp-exp-gallery-item').remove();
                updateEmptyState();
                notifyUnsavedChange();
            });

            $group.on('click', '.fp-exp-gallery-clear', function(event) {
                event.preventDefault();

                if (!window.confirm(self.getAdminString('gallery_clear_confirm', 'Remove all gallery images?'))) {
                    return;
                }

                $list.empty();
                updateEmptyState();
                notifyUnsavedChange();
            });

            updateEmptyState();
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
            var adminData = this.ensureAdminData(['rest_url']);
            if (!adminData) {
                return;
            }

            // Load FullCalendar from CDN
            if (typeof FullCalendar === 'undefined') {
                this.loadFullCalendar()
                    .then(function() {
                        self.renderCalendar(adminData);
                    })
                    .catch(function(error) {
                        console.error('FullCalendar failed to load', error);
                        self.showUserFeedback(
                            __('Failed to load calendar library. Attempting local copy...', 'fp-esperienze'),
                            'warning',
                            5000
                        );

                        if (typeof self.loadLocalFullCalendar === 'function') {
                            self.loadLocalFullCalendar()
                                .then(function() {
                                    self.renderCalendar(adminData);
                                })
                                .catch(function(localError) {
                                    console.error('Local FullCalendar failed to load', localError);
                                    self.showUserFeedback(
                                        __('Calendar could not be loaded. Please check your network and try again.', 'fp-esperienze'),
                                        'error',
                                        8000
                                    );
                                });
                        } else {
                            self.showUserFeedback(
                                __('Calendar could not be loaded. Please check your network and try again.', 'fp-esperienze'),
                                'error',
                                8000
                            );
                        }
                    });
            } else {
                this.renderCalendar(adminData);
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
         * Load local FullCalendar library as fallback
         */
        loadLocalFullCalendar: function() {
            const data = this.ensureAdminData(['plugin_url']);
            if (!data) {
                return Promise.reject(new Error('Plugin URL not available'));
            }

            const base = data.plugin_url;

            return new Promise(function(resolve, reject) {
                // Load CSS
                var css = document.createElement('link');
                css.rel = 'stylesheet';
                css.href = base + 'assets/vendor/fullcalendar/index.global.min.css';
                document.head.appendChild(css);

                // Load JS
                var script = document.createElement('script');
                script.src = base + 'assets/vendor/fullcalendar/index.global.min.js';
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });
        },
        
        /**
         * Render FullCalendar
         */
        renderCalendar: function(adminData) {
            var calendarEl = document.getElementById('fp-calendar');
            if (!calendarEl) return;

            if (!adminData || (!adminData.rest_url && !adminData.experience_rest_url)) {
                console.error('REST URL missing; cannot render calendar.');
                this.showUserFeedback(__('Required localization data is missing.', 'fp-esperienze'), 'error', 8000);
                return;
            }

            var ensureTrailingSlash = function(value) {
                if (typeof value !== 'string' || value.length === 0) {
                    return '';
                }

                return value.endsWith('/') ? value : value + '/';
            };

            var normalizeNamespace = function(value) {
                if (typeof value !== 'string' || value.length === 0) {
                    return '';
                }

                var sanitized = value.replace(/^\//, '');
                return sanitized.endsWith('/') ? sanitized : sanitized + '/';
            };

            var namespace = normalizeNamespace(adminData.rest_namespace) || 'fp-exp/v1/';
            var experienceRestBase = '';

            if (adminData.experience_rest_url) {
                experienceRestBase = ensureTrailingSlash(adminData.experience_rest_url);
            } else if (adminData.rest_url) {
                experienceRestBase = ensureTrailingSlash(adminData.rest_url) + namespace;
            }

            if (!experienceRestBase) {
                console.error('Unable to determine the experience REST base URL.');
                this.showUserFeedback(__('Required localization data is missing.', 'fp-esperienze'), 'error', 8000);
                return;
            }

            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                height: 600,
                events: {
                    url: experienceRestBase + 'bookings/calendar',
                    method: 'GET',
                    extraParams: function() {
                        return {
                            // Add any filters here if needed
                        };
                    },
                    failure: function() {
                        alert(__('There was an error while fetching events!', 'fp-esperienze'));
                    }
                },
                eventClick: function(info) {
                    var booking = info.event.extendedProps;
                    var content = '<div class="fp-booking-popup">' +
                        '<h3>' + info.event.title + '</h3>' +
                        '<p><strong>' + __('Order', 'fp-esperienze') + ':</strong> #' + booking.order_id + '</p>' +
                        '<p><strong>' + __('Customer', 'fp-esperienze') + ':</strong> ' + booking.customer_name + '</p>' +
                        '<p><strong>' + __('Status', 'fp-esperienze') + ':</strong> ' + booking.status + '</p>' +
                        '<p><strong>' + __('Participants', 'fp-esperienze') + ':</strong> ' + booking.adults + ' ' + __('adults', 'fp-esperienze') + ', ' + booking.children + ' ' + __('children', 'fp-esperienze') + '</p>' +
                        '<p><strong>' + __('Date', 'fp-esperienze') + ':</strong> ' + info.event.start.toLocaleDateString() + '</p>' +
                        '<p><strong>' + __('Time', 'fp-esperienze') + ':</strong> ' + info.event.start.toLocaleTimeString() + '</p>' +
                        '</div>';
                    
                    // Show popup (using WordPress admin modal or simple alert)
                    if (typeof tb_show !== 'undefined') {
                        $('body').append('<div id="fp-booking-details" style="display:none">' + content + '</div>');
                        tb_show(__('Booking Details', 'fp-esperienze'), '#TB_inline?inlineId=fp-booking-details&width=400&height=300');
                    } else {
                        alert(info.event.title + '\n' +
                              __('Order', 'fp-esperienze') + ': #' + booking.order_id + '\n' +
                              __('Status', 'fp-esperienze') + ': ' + booking.status + '\n' +
                              __('Participants', 'fp-esperienze') + ': ' + (booking.adults + booking.children));
                    }
                },
                eventDidMount: function(info) {
                    // Add tooltip
                    info.el.setAttribute('title',
                        info.event.title + '\n' +
                        __('Order', 'fp-esperienze') + ': #' + info.event.extendedProps.order_id + '\n' +
                        __('Status', 'fp-esperienze') + ': ' + info.event.extendedProps.status
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
            
            // Toggle raw mode when advanced schedules are enabled
            if ($('#fp-toggle-raw-mode').length) {
                $(document).on('change', '#fp-toggle-raw-mode', function() {
                    self.toggleRawMode($(this).is(':checked'));
                });
            }
            
            // Form submission handling for schedule builder
            $('form#post').on('submit', function() {
                if ($('#product-type').val() === 'experience') {
                    // Always clear the generated schedules container to prevent duplicate data
                    $('#fp-generated-schedules').empty();
                    
                    // Check if we're using the modern builder interface (which sends builder_slots data)
                    var hasBuilderSlots = $('#fp-time-slots-container .fp-time-slot-row, #fp-time-slots-container .fp-time-slot-card-clean').length > 0;
                    
                    // Only generate legacy schedule data if:
                    // 1. We're not using the builder interface AND
                    // 2. There are no existing builder_slots form inputs
                    var hasBuilderFormInputs = $('input[name*="builder_slots"]').length > 0;
                    
                    if (!hasBuilderSlots && !hasBuilderFormInputs) {
                        // This is the legacy raw schedule mode
                        self.generateSchedulesFromBuilder();
                    }
                    
                    // Clear unsaved changes flag on successful submission
                    self.clearUnsavedChanges();
                }
            });
        },
        
        /**
         * Bind event schedule events
         */
        bindEventScheduleEvents: function() {
            var self = this;
            
            // Add new event date
            $(document).on('click', '#fp-add-event-schedule', function(e) {
                e.preventDefault();
                self.addEventDateRow();
            });
            
            // Remove event date
            $(document).on('click', '.fp-remove-event-date', function(e) {
                e.preventDefault();
                if (confirm(fp_esperienze_admin.strings.confirm_remove_event_date || 'Are you sure you want to remove this event date and all its time slots?')) {
                    $(this).closest('.fp-event-date-card').remove();
                    self.markUnsavedChanges();
                }
            });
            
            // Add time slot to existing event date
            $(document).on('click', '.fp-add-event-timeslot', function(e) {
                e.preventDefault();
                var eventDate = $(this).data('date');
                self.addEventTimeslotRow(eventDate, $(this).closest('.fp-event-date-card'));
            });
            
            // Remove event time slot
            $(document).on('click', '.fp-remove-event-timeslot', function(e) {
                e.preventDefault();
                var $timeslotCard = $(this).closest('.fp-event-timeslot-card');
                var $dateCard = $timeslotCard.closest('.fp-event-date-card');
                
                $timeslotCard.remove();
                
                // If no more timeslots, remove the entire date
                if ($dateCard.find('.fp-event-timeslot-card').length === 0) {
                    $dateCard.remove();
                }
                
                self.markUnsavedChanges();
            });
            
            // Mark unsaved changes on event schedule field changes
            $(document).on('change', 'input[name*="event_schedules"], select[name*="event_schedules"]', function() {
                self.markUnsavedChanges();
            });
        },
        
        /**
         * Add new event date row
         */
        addEventDateRow: function() {
            var today = new Date();
            var tomorrow = new Date(today.getTime() + 24 * 60 * 60 * 1000);
            var dateStr = tomorrow.toISOString().split('T')[0];
            
            // Check if date already exists
            if ($('.fp-event-date-card[data-date="' + dateStr + '"]').length > 0) {
                alert(fp_esperienze_admin.strings.event_date_exists || 'This event date already exists.');
                return;
            }
            
            var $container = $('#fp-event-schedule-container');
            var $emptyMessage = $container.find('.fp-empty-events-message');
            
            if ($emptyMessage.length) {
                $emptyMessage.remove();
            }
            
            var eventCardHtml = this.generateEventDateCardHtml(dateStr);
            $container.append(eventCardHtml);
            
            this.markUnsavedChanges();
        },
        
        /**
         * Add timeslot to existing event date
         */
        addEventTimeslotRow: function(eventDate, $dateCard) {
            var $timeslotsContainer = $dateCard.find('.fp-event-timeslots');
            var timeslotIndex = $timeslotsContainer.find('.fp-event-timeslot-card').length;
            
            var timeslotHtml = this.generateEventTimeslotHtml(eventDate, timeslotIndex);
            $timeslotsContainer.append(timeslotHtml);
            
            this.markUnsavedChanges();
        },
        
        /**
         * Generate HTML for new event date card
         */
        generateEventDateCardHtml: function(dateStr) {
            var dateObj = new Date(dateStr);
            var formattedDate = dateObj.toLocaleDateString();
            
            return '<div class="fp-event-date-card" data-date="' + dateStr + '">' +
                '<div class="fp-event-date-header">' +
                    '<div class="fp-event-date-info">' +
                        '<span class="dashicons dashicons-calendar-alt"></span>' +
                        '<strong>' + formattedDate + '</strong>' +
                        '<span class="fp-event-date-meta">0 time slots</span>' +
                    '</div>' +
                    '<div class="fp-event-date-actions">' +
                        '<button type="button" class="button fp-add-event-timeslot" data-date="' + dateStr + '">' +
                            '<span class="dashicons dashicons-clock"></span>' +
                            (fp_esperienze_admin.strings.add_time_slot || 'Add Time Slot') +
                        '</button>' +
                        '<button type="button" class="button fp-remove-event-date" data-date="' + dateStr + '">' +
                            '<span class="dashicons dashicons-trash"></span>' +
                            (fp_esperienze_admin.strings.remove_date || 'Remove Date') +
                        '</button>' +
                    '</div>' +
                '</div>' +
                '<div class="fp-event-timeslots">' +
                    this.generateEventTimeslotHtml(dateStr, 0) +
                '</div>' +
            '</div>';
        },
        
        /**
         * Generate HTML for new event timeslot
         */
        generateEventTimeslotHtml: function(eventDate, index) {
            var meetingPoints = fp_esperienze_admin.fp_meeting_points || {};
            var meetingPointOptions = '';
            
            for (var id in meetingPoints) {
                meetingPointOptions += '<option value="' + id + '">' + meetingPoints[id] + '</option>';
            }
            
            return '<div class="fp-event-timeslot-card">' +
                '<div class="fp-event-timeslot-content">' +
                    '<input type="hidden" name="event_schedules[' + eventDate + '][' + index + '][event_date]" value="' + eventDate + '">' +
                    '<input type="hidden" name="event_schedules[' + eventDate + '][' + index + '][schedule_type]" value="fixed">' +
                    '<div class="fp-event-timeslot-grid">' +
                        '<div class="fp-timeslot-field">' +
                            '<label><span class="dashicons dashicons-clock"></span> ' + (fp_esperienze_admin.strings.start_time || 'Start Time') + ' <span class="required">*</span></label>' +
                            '<input type="time" name="event_schedules[' + eventDate + '][' + index + '][start_time]" required>' +
                        '</div>' +
                        '<div class="fp-timeslot-field">' +
                            '<label>' + (fp_esperienze_admin.strings.duration || 'Duration (min)') + '</label>' +
                            '<input type="number" name="event_schedules[' + eventDate + '][' + index + '][duration_min]" value="60" min="1" required>' +
                        '</div>' +
                        '<div class="fp-timeslot-field">' +
                            '<label>' + (fp_esperienze_admin.strings.capacity || 'Capacity') + '</label>' +
                            '<input type="number" name="event_schedules[' + eventDate + '][' + index + '][capacity]" value="10" min="1" required>' +
                        '</div>' +
                        '<div class="fp-timeslot-field">' +
                            '<label>' + (fp_esperienze_admin.strings.language || 'Language') + '</label>' +
                            '<input type="text" name="event_schedules[' + eventDate + '][' + index + '][lang]" value="en" maxlength="10" required>' +
                        '</div>' +
                        '<div class="fp-timeslot-field">' +
                            '<label>' + (fp_esperienze_admin.strings.meeting_point || 'Meeting Point') + '</label>' +
                            '<select name="event_schedules[' + eventDate + '][' + index + '][meeting_point_id]" required>' + meetingPointOptions + '</select>' +
                        '</div>' +
                        '<div class="fp-timeslot-field">' +
                            '<label>' + (fp_esperienze_admin.strings.adult_price || 'Adult Price') + '</label>' +
                            '<input type="number" name="event_schedules[' + eventDate + '][' + index + '][price_adult]" value="0" min="0" step="0.01" required>' +
                        '</div>' +
                        '<div class="fp-timeslot-field">' +
                            '<label>' + (fp_esperienze_admin.strings.child_price || 'Child Price') + '</label>' +
                            '<input type="number" name="event_schedules[' + eventDate + '][' + index + '][price_child]" value="0" min="0" step="0.01" required>' +
                        '</div>' +
                        '<div class="fp-timeslot-actions">' +
                            '<button type="button" class="button fp-remove-event-timeslot">' +
                                '<span class="dashicons dashicons-trash"></span>' +
                                (fp_esperienze_admin.strings.remove || 'Remove') +
                            '</button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
        },

        /**
         * Bind Schedule Builder specific events
         */
        bindScheduleBuilderEvents: function() {
            var self = this;
            
            // Legacy time slot handlers - DISABLED for clean version
            // $(document).on('click', '#fp-add-time-slot', function(e) {
            //     e.preventDefault();
            //     self.addTimeSlot();
            // });
            
            // Remove time slot
            $(document).on('click', '.fp-remove-time-slot', function(e) {
                e.preventDefault();
                $(this).closest('.fp-time-slot-row').remove();
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
            
            // Update summary when any override values change
            $(document).on('change', 'input[name*="[duration_min]"], input[name*="[capacity]"], input[name*="[lang]"], select[name*="[meeting_point_id]"], input[name*="[price_adult]"], input[name*="[price_child]"]', function() {
                self.updateSummaryTable();
            });
            
            // Validate time slots before form submission
            $('form#post').on('submit', function(e) {
                if ($('#product-type').val() === 'experience') {
                    // Enhanced validation before submission
                    var isValid = self.validateTimeSlots();
                    var hasValidData = self.validateFormData();
                    
                    if (!isValid || !hasValidData) {
                        e.preventDefault();
                        return false;
                    }
                }
            });
            
            // Auto-save functionality for better UX
            $(document).on('change', '#fp-time-slots-container input, #fp-time-slots-container select', function(event) {
                self.markAsChanged(event);
            });
        },
        
        /**
         * Validate time slots before saving
         */
        validateTimeSlots: function() {
            var hasErrors = false;
            var errorMessages = [];
            
            // Support both old (.fp-time-slot-row) and new (.fp-time-slot-card-clean) formats
            var selector = '#fp-time-slots-container .fp-time-slot-row, #fp-time-slots-container .fp-time-slot-card-clean';
            $(selector).each(function() {
                var $slot = $(this);
                var startTime = $slot.find('input[name*="[start_time]"]').val();
                var selectedDays = $slot.find('input[name*="[days][]"]:checked').length;
                
                if (!startTime) {
                    hasErrors = true;
                    $slot.find('input[name*="[start_time]"]').css('border-color', '#d63638');
                    errorMessages.push('All time slots must have a start time.');
                } else {
                    // Validate time format on frontend too (allow optional seconds)
                    var timePattern = /^([01]?[0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/;
                    if (!timePattern.test(startTime)) {
                        hasErrors = true;
                        $slot.find('input[name*="[start_time]"]').css('border-color', '#d63638');
                        errorMessages.push('Time "' + startTime + '" has invalid format. Use HH:MM format.');
                    } else {
                        startTime = startTime.substring(0,5);
                        $slot.find('input[name*="[start_time]"]').val(startTime).css('border-color', '');
                    }
                }
                
                if (selectedDays === 0) {
                    hasErrors = true;
                    // Support both old and new day pills containers
                    $slot.find('.fp-days-pills, .fp-days-pills-clean').css('border', '2px solid #d63638');
                    errorMessages.push('All time slots must have at least one day selected.');
                } else {
                    $slot.find('.fp-days-pills, .fp-days-pills-clean').css('border', '');
                }
            });
            
            if (hasErrors) {
                var uniqueMessages = [...new Set(errorMessages)];
                alert(__('Please fix the following errors:', 'fp-esperienze') + '\n\n' + uniqueMessages.join('\n'));
                return false;
            }
            
            return true;
        },
        
        /**
         * Validate form data for conflicts and issues
         */
        validateFormData: function() {
            var hasBuilderSlots = $('input[name*="builder_slots"]').length > 0;
            var hasGeneratedSchedules = $('#fp-generated-schedules input').length > 0;
            
            // Check for potential conflicts
            if (hasBuilderSlots && hasGeneratedSchedules) {
                console.warn('FP Esperienze: Detected both builder_slots and generated schedules data, clearing generated schedules to prevent conflicts');
                $('#fp-generated-schedules').empty();
            }
            
            return true;
        },
        
        /**
         * Mark form as changed for unsaved changes warning
         */
        markAsChanged: function(event) {
            if (!event || !event.originalEvent) {
                return;
            }

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
                    e.returnValue = __('You have unsaved changes. Are you sure you want to leave?', 'fp-esperienze');
                    return e.returnValue;
                }
            });
        },
        
        /**
         * Clear unsaved changes flag
         */
        clearUnsavedChanges: function() {
            this.hasUnsavedChanges = false;

            if (window.FPAdminUXEnhancer && typeof window.FPAdminUXEnhancer.refreshTrackedFormBaselines === 'function') {
                window.FPAdminUXEnhancer.refreshTrackedFormBaselines();
            } else if (window.FPAdminUXEnhancer && typeof window.FPAdminUXEnhancer.clearUnsavedState === 'function') {
                window.FPAdminUXEnhancer.clearUnsavedState();
            } else if (window.FPAdminUXEnhancer && typeof window.FPAdminUXEnhancer.setUnsavedState === 'function') {
                window.FPAdminUXEnhancer.setUnsavedState(false);
            }
        },
        
        /**
         * Update the summary table
         */
        updateSummaryTable: function() {
            var summaryContainer = $('.fp-slots-summary-content');
            if (!summaryContainer.length) return;
            
            var slots = [];
            var days = this.getWeekdayAbbreviations();
            
            // Collect slot data - support both old (.fp-time-slot-row) and new (.fp-time-slot-card-clean) formats
            var selector = '#fp-time-slots-container .fp-time-slot-row, #fp-time-slots-container .fp-time-slot-card-clean';
            $(selector).each(function() {
                var $slot = $(this);
                var startTime = $slot.find('input[name*="[start_time]"]').val();
                var selectedDays = [];

                $slot.find('input[name*="[days][]"]:checked').each(function() {
                    selectedDays.push($(this).val());
                });

                if (startTime && selectedDays.length > 0) {
                    var duration = $slot.find('input[name*="[duration_min]"]').val();
                    var capacity = $slot.find('input[name*="[capacity]"]').val();

                    slots.push({
                        time: startTime,
                        days: selectedDays,
                        duration: duration,
                        capacity: capacity
                    });
                }
            });
            
            // Build new summary HTML
            var summaryHtml;
            if (slots.length === 0) {
                summaryHtml = '<div class="fp-summary-table"><div class="fp-empty-state">' +
                    sprintf(__('No time slots configured yet. Click "%s" below to get started.', 'fp-esperienze'), __('Add Time Slot', 'fp-esperienze')) +
                    '</div></div>';
            } else {
                summaryHtml = '<table class="fp-summary-table">' +
                    '<thead><tr>' +
                        '<th>' + __('Time', 'fp-esperienze') + '</th>' +
                        '<th>' + __('Days', 'fp-esperienze') + '</th>' +
                        '<th>' + __('Duration', 'fp-esperienze') + '</th>' +
                        '<th>' + __('Capacity', 'fp-esperienze') + '</th>' +
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
                        '<td>' + slot.duration + ' ' + __('min', 'fp-esperienze') + '</td>' +
                        '<td>' + slot.capacity + '</td>' +
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
        /**
         * Legacy addTimeSlot function - DEPRECATED
         * Use addTimeSlotCard() instead for new modern design
         */
        addTimeSlot: function() {
            // Redirect to modern implementation
            console.warn('FP Esperienze: addTimeSlot is deprecated, using modern addTimeSlotCard instead');
            this.addTimeSlotCard();
        },
        
        /**
         * Populate meeting points dropdown
         */
        populateMeetingPointsDropdown: function(selectElement) {
            var $select = $(selectElement);

            if (!$select || !$select.length) {
                console.warn('FP Esperienze: Meeting point select element not found.');
                return;
            }

            var adminData = this.ensureAdminData(['fp_meeting_points']);
            if (!adminData) {
                return;
            }

            var meetingPoints = adminData.fp_meeting_points || {};
            var selectedValue = $select.val();
            var $placeholderOption = $select.find('option').first().clone();

            $select.empty();

            if ($placeholderOption.length) {
                // Ensure placeholder stays as the default option
                $placeholderOption.prop('selected', true);
                $select.append($placeholderOption);
            }

            if ($.isEmptyObject(meetingPoints)) {
                var noMeetingPointOption = $('<option>')
                    .val('')
                    .text(__('No meeting points available', 'fp-esperienze'))
                    .prop('disabled', true);

                if (!$placeholderOption.length) {
                    noMeetingPointOption.prop('selected', true);
                }

                $select.append(noMeetingPointOption);
                return;
            }

            $.each(meetingPoints, function(id, name) {
                var idInt = parseInt(id, 10);
                var nameStr = String(name || '').trim();

                if (isNaN(idInt) || !nameStr) {
                    return;
                }

                $select.append(
                    $('<option>')
                        .val(idInt)
                        .text(nameStr)
                );
            });

            if (selectedValue) {
                var hasSelected = $select.find('option').filter(function() {
                    return String($(this).val()) === String(selectedValue);
                }).length > 0;

                if (hasSelected) {
                    $select.val(selectedValue);
                }
            }
        },
        
        /**
         * Generate schedule inputs from builder before form submission
         */
        generateSchedulesFromBuilder: function() {
            var generatedContainer = $('#fp-generated-schedules');
            generatedContainer.empty();
            
            var scheduleIndex = 0;
            
            // Process each time slot - support both old (.fp-time-slot-row) and new (.fp-time-slot-card-clean) formats
            var selector = '#fp-time-slots-container .fp-time-slot-row, #fp-time-slots-container .fp-time-slot-card-clean';
            $(selector).each(function() {
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

                var duration = timeSlot.find('input[name*="[duration_min]"]').val();
                var capacity = timeSlot.find('input[name*="[capacity]"]').val();
                var lang = timeSlot.find('input[name*="[lang]"]').val();
                var meetingPoint = timeSlot.find('select[name*="[meeting_point_id]"]').val();
                var priceAdult = timeSlot.find('input[name*="[price_adult]"]').val();
                var priceChild = timeSlot.find('input[name*="[price_child]"]').val();

                // Generate schedule input for each selected day
                selectedDays.forEach(function(dayOfWeek) {
                    var scheduleHtml = '<input type="hidden" name="schedules[' + scheduleIndex + '][day_of_week]" value="' + dayOfWeek + '">' +
                        '<input type="hidden" name="schedules[' + scheduleIndex + '][start_time]" value="' + startTime + '">' +
                        '<input type="hidden" name="schedules[' + scheduleIndex + '][duration_min]" value="' + duration + '">' +
                        '<input type="hidden" name="schedules[' + scheduleIndex + '][capacity]" value="' + capacity + '">' +
                        '<input type="hidden" name="schedules[' + scheduleIndex + '][lang]" value="' + lang + '">' +
                        '<input type="hidden" name="schedules[' + scheduleIndex + '][meeting_point_id]" value="' + meetingPoint + '">' +
                        '<input type="hidden" name="schedules[' + scheduleIndex + '][price_adult]" value="' + priceAdult + '">' +
                        '<input type="hidden" name="schedules[' + scheduleIndex + '][price_child]" value="' + priceChild + '">';

                    generatedContainer.append(scheduleHtml);
                    scheduleIndex++;
                });
            });
        },
        
        /**
         * ======================================
         * MODERN SCHEDULE BUILDER - REFACTORED
         * ======================================
         */
        
        /**
         * Initialize modern schedule builder - REFACTORED CLEAN VERSION
         */
        initModernScheduleBuilder: function() {
            var self = this;
            
            self.debug('Initializing clean schedule builder');
            
            // Validate containers first
            this.validateContainers();
            
            // Unbind any existing handlers to prevent conflicts
            $(document).off('click.fp-clean', '#fp-add-time-slot');
            $(document).off('click.fp-clean', '.fp-remove-time-slot-clean');
            $(document).off('click.fp-clean', '#fp-add-override');
            $(document).off('click.fp-clean', '.fp-override-remove-clean');
            $(document).off('change.fp-clean', '.fp-override-checkbox-clean input[type="checkbox"]');
            
            // Time slot management - clean version with namespace and enhanced error handling
            $(document).on('click.fp-clean', '#fp-add-time-slot', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.debug('Add time slot clicked');
                
                // Disable button temporarily to prevent double clicks
                var $button = $(this);
                var originalText = $button.text();
                $button.prop('disabled', true).addClass('fp-loading');
                
                try {
                    self.addTimeSlotCardClean();
                    // Re-enable button with success feedback
                    setTimeout(function() {
                        $button.prop('disabled', false).removeClass('fp-loading');
                    }, 300);
                } catch (error) {
                    console.error('FP Esperienze: Error adding time slot:', error);
                    alert(__('Error adding time slot. Please try again.', 'fp-esperienze'));
                    $button.prop('disabled', false).removeClass('fp-loading');
                }
            });
            
            $(document).on('click.fp-clean', '.fp-remove-time-slot-clean', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.debug('Remove time slot clicked');
                
                var $button = $(this);
                var $card = $button.closest('.fp-time-slot-card-clean');
                
                // Add confirmation for destructive action
                if (confirm(__('Are you sure you want to remove this time slot?', 'fp-esperienze'))) {
                    $button.prop('disabled', true).addClass('fp-loading');
                    
                    try {
                        self.removeTimeSlotCardClean($button);
                    } catch (error) {
                        console.error('FP Esperienze: Error removing time slot:', error);
                        alert(__('Error removing time slot. Please try again.', 'fp-esperienze'));
                        $button.prop('disabled', false).removeClass('fp-loading');
                    }
                }
            });
            
            // Override management - clean version with namespace
            $(document).on('click.fp-clean', '#fp-add-override', function(e) {
                e.preventDefault();
                e.stopPropagation();
                // Debug logging removed for production
                try {
                    self.addOverrideCardClean();
                } catch (error) {
                    console.error('FP Esperienze: Error adding override:', error);
                }
            });
            
            $(document).on('click.fp-clean', '.fp-override-remove-clean', function(e) {
                e.preventDefault();
                e.stopPropagation();
                // Debug logging removed for production
                try {
                    self.removeOverrideCardClean($(this));
                } catch (error) {
                    console.error('FP Esperienze: Error removing override:', error);
                }
            });
            
            // Override closed checkbox - clean version with namespace
            $(document).on('change.fp-clean', '.fp-override-checkbox-clean input[type="checkbox"]', function() {
                // Debug logging removed for production
                try {
                    self.handleOverrideClosedClean($(this));
                } catch (error) {
                    console.error('FP Esperienze: Error handling override closed:', error);
                }
            });
            
            // Validate containers on initialization
            this.validateContainers();
        },

        /**
         * Validate containers are present - ENHANCED ERROR CHECKING
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
            
            // Check if buttons are present
            var addTimeSlotBtn = $('#fp-add-time-slot');
            var addOverrideBtn = $('#fp-add-override');
            
            if (!addTimeSlotBtn.length) {
                console.warn('FP Esperienze: Add time slot button #fp-add-time-slot not found');
            } else {
                // Debug logging removed for production
            }
            
            if (!addOverrideBtn.length) {
                console.warn('FP Esperienze: Add override button #fp-add-override not found');
            } else {
                // Debug logging removed for production
            }
        },

        /**
         * Modern override management - REFACTORED
         */
        initOverrideManager: function() {
            this.bindModernOverrideEvents();
        },

        /**
         * Modern time slot management - REFACTORED
         */
        initTimeSlotManager: function() {
            this.bindModernTimeSlotEvents();
        },

        /**
         * Bind modern override events - SIMPLIFIED - DISABLED (using clean version instead)
         */
        bindModernOverrideEvents: function() {
            // DISABLED - Modern handler conflicts with clean version
            // Using initModernScheduleBuilder clean handlers instead
            return;
        },

        /**
         * Bind modern time slot events - SIMPLIFIED - DISABLED (using clean version instead)
         */
        bindModernTimeSlotEvents: function() {
            // DISABLED - Modern handler conflicts with clean version
            // Using initModernScheduleBuilder clean handlers instead
            return;
        },


        /**
         * Remove override card - IMPROVED
         */
        removeOverrideCard: function($button) {
            var $card = $button.closest('.fp-override-card');
            var dateValue = $card.find('input[name*="[date]"]').val();
            
            // Confirm removal if there's a date value
            if (dateValue) {
                var msg = this.getAdminString(
                    'confirm_remove_override',
                    __('Are you sure you want to remove this date override?', 'fp-esperienze')
                );
                if (!confirm(msg)) {
                    return;
                }
            }
            
            // Animate removal
            $card.animate({opacity: 0, height: 0, marginBottom: 0, paddingTop: 0, paddingBottom: 0}, 300, function() {
                $card.remove();
                
                // Show empty state if no cards left
                var container = $('#fp-overrides-container');
                if (container.find('.fp-override-card').length === 0) {
                    container.find('.fp-overrides-empty').show();
                }
            });
        },

        /**
         * Track override changes - ENHANCED
         */
        trackOverrideChanges: function($input) {
            var $card = $input.closest('.fp-override-card');
            var originalValue = $input.data('original-value') || '';
            var currentValue = $input.val();
            var hasChanged = originalValue !== currentValue;
            
            // Update card status
            if (hasChanged) {
                $card.addClass('has-changes');
                $card.find('.fp-override-status').removeClass('normal').addClass('modified');
            } else {
                // Check if any other fields in this card have changes
                var anyChanges = false;
                $card.find('.fp-override-input').each(function() {
                    var orig = $(this).data('original-value') || '';
                    var curr = $(this).val();
                    if (orig !== curr) {
                        anyChanges = true;
                        return false;
                    }
                });
                
                if (!anyChanges) {
                    $card.removeClass('has-changes');
                    $card.find('.fp-override-status').removeClass('modified').addClass('normal');
                }
            }
        },

        /**
         * Handle closed checkbox - IMPROVED
         */
        handleOverrideClosed: function($checkbox) {
            var $card = $checkbox.closest('.fp-override-card');
            var isChecked = $checkbox.is(':checked');
            
            // Update visual state
            if (isChecked) {
                $card.addClass('is-closed');
                $card.find('.fp-override-status').removeClass('normal modified').addClass('closed');
                $card.find('.fp-override-field').not($checkbox.closest('.fp-override-actions')).addClass('is-closed');
            } else {
                $card.removeClass('is-closed');
                $card.find('.fp-override-status').removeClass('closed').addClass('normal');
                $card.find('.fp-override-field').removeClass('is-closed');
            }
        },

        /**
         * Add time slot card - MODERNIZED
         */
        addTimeSlotCard: function() {
            var container = $('#fp-time-slots-container');
            if (!container.length) {
                console.warn('FP Esperienze: Time slots container not found');
                return;
            }
            
            var index = container.find('.fp-time-slot-card').length;
            var cardHtml = this.createTimeSlotCardHTML(index);
            container.append(cardHtml);
            
            // Focus on the time input
            var $newCard = container.find('.fp-time-slot-card').last();
            $newCard.find('input[type="time"]').focus();
            
            // Populate meeting points dropdown
            this.populateMeetingPointsDropdown($newCard.find('select[name*="meeting_point_id"]'));
            
            // Add entrance animation
            $newCard.css('opacity', '0').animate({opacity: 1}, 300);
        },

        /**
         * Create time slot card HTML - MODERN DESIGN
         */
        createTimeSlotCardHTML: function(index) {
            var days = {
                '1': 'Mon', '2': 'Tue', '3': 'Wed', '4': 'Thu', 
                '5': 'Fri', '6': 'Sat', '0': 'Sun'
            };
            
            var daysHtml = '';
            for (var dayValue in days) {
                daysHtml += '<div class="fp-day-pill">' +
                    '<input type="checkbox" id="day-' + index + '-' + dayValue + '" name="builder_slots[' + index + '][days][]" value="' + dayValue + '">' +
                    '<label for="day-' + index + '-' + dayValue + '">' + days[dayValue] + '</label>' +
                '</div>';
            }
            
            return '<div class="fp-time-slot-card" data-index="' + index + '">' +
                '<div class="fp-time-slot-header">' +
                    '<div class="fp-time-field">' +
                        '<label>' +
                            '<span class="dashicons dashicons-clock"></span>' +
                            'Start Time <span class="required">*</span>' +
                        '</label>' +
                        '<input type="time" name="builder_slots[' + index + '][start_time]" required>' +
                    '</div>' +
                    '<div class="fp-days-field">' +
                        '<label>' +
                            '<span class="dashicons dashicons-calendar-alt"></span>' +
                            'Days of Week <span class="required">*</span>' +
                        '</label>' +
                        '<div class="fp-days-selector">' +
                            '<div class="fp-days-pills">' +
                                daysHtml +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div>' +
                        '<button type="button" class="fp-remove-time-slot">' +
                            '<span class="dashicons dashicons-trash"></span> Remove' +
                        '</button>' +
                    '</div>' +
                '</div>' +

                '<div class="fp-overrides-grid">' +
                    '<div class="fp-override-field">' +
                        '<label>Duration (minutes) <span class="required">*</span></label>' +
                        '<input type="number" name="builder_slots[' + index + '][duration_min]" min="1" required>' +
                    '</div>' +
                    '<div class="fp-override-field">' +
                        '<label>Capacity <span class="required">*</span></label>' +
                        '<input type="number" name="builder_slots[' + index + '][capacity]" min="1" required>' +
                    '</div>' +
                    '<div class="fp-override-field">' +
                        '<label>Language <span class="required">*</span></label>' +
                        '<input type="text" name="builder_slots[' + index + '][lang]" maxlength="10" required>' +
                    '</div>' +
                    '<div class="fp-override-field">' +
                        '<label>Meeting Point <span class="required">*</span></label>' +
                        '<select name="builder_slots[' + index + '][meeting_point_id]" class="fp-meeting-point-select" required>' +
                            '<option value="" disabled selected>Select meeting point</option>' +
                        '</select>' +
                    '</div>' +
                    '<div class="fp-override-field">' +
                        '<label>Adult Price <span class="required">*</span></label>' +
                        '<input type="number" name="builder_slots[' + index + '][price_adult]" min="0" step="0.01" required>' +
                    '</div>' +
                    '<div class="fp-override-field">' +
                        '<label>Child Price <span class="required">*</span></label>' +
                        '<input type="number" name="builder_slots[' + index + '][price_child]" min="0" step="0.01" required>' +
                    '</div>' +
                '</div>' +
            '</div>';
        },

        /**
         * Remove time slot card - IMPROVED
         */
        removeTimeSlotCard: function($button) {
            var $card = $button.closest('.fp-time-slot-card');
            
            // Animate removal
            $card.animate({opacity: 0, height: 0, marginBottom: 0, paddingTop: 0, paddingBottom: 0}, 300, function() {
                $card.remove();
            });
        },

        // Removed toggleTimeSlotOverrides as advanced settings are no longer optional
        
        /**
         * ======================================
         * END MODERN SCHEDULE BUILDER
         * ======================================
         */
        
        /**
         * Bind override events - LEGACY (keeping for compatibility)
         */
        bindOverrideEvents: function() {
            var self = this;
            
            // Unbind existing events to prevent double binding
            $(document).off('click.fp-override', '#fp-add-override');
            $(document).off('click.fp-override', '.fp-remove-override, .fp-override-remove');
            $(document).off('change.fp-override', '.fp-override-input');
            $(document).off('input.fp-override', '.fp-override-input');
            
            // Add override (both main button and empty state button)
            $(document).on('click.fp-override', '#fp-add-override', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.addOverrideRow();
            });
            
            // Remove override (both table and row format)
            $(document).on('click.fp-override', '.fp-remove-override, .fp-override-remove', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var $row = $(this).closest('.fp-override-row, tr');
                var dateValue = $row.find('input[name*="[date]"]').val();
                
                // Confirm removal if there's a date value
                if (dateValue) {
                    var msg = self.getAdminString(
                        'confirm_remove_override',
                        __('Are you sure you want to remove this date override?', 'fp-esperienze')
                    );
                    if (!confirm(msg)) {
                        return;
                    }
                }
                
                $row.remove();
                self.updateOverrideNumbers();
            });
            
            // Track changes and validate dates
            $(document).on('change.fp-override input.fp-override', '.fp-override-input', function() {
                var $input = $(this);
                var $row = $input.closest('.fp-override-row, tr');
                
                // Track if value has changed from original
                var originalValue = $input.data('original-value') || '';
                var currentValue = $input.val();
                var hasChanged = originalValue !== currentValue;
                
                // Mark row as changed
                if (hasChanged) {
                    $row.addClass('has-changes');
                } else {
                    // Check if any other fields in this row have changes
                    var anyChanges = false;
                    $row.find('.fp-override-input').each(function() {
                        var orig = $(this).data('original-value') || '';
                        var curr = $(this).val();
                        if (orig !== curr) {
                            anyChanges = true;
                            return false;
                        }
                    });
                    
                    if (!anyChanges) {
                        $row.removeClass('has-changes');
                    }
                }
                
                // Validate date if this is a date input
                if ($input.attr('type') === 'date') {
                    self.validateOverrideDate($input);
                }
                
                // Sort overrides by date
                self.sortOverridesByDate();
            });
            
            // Handle checkbox changes for original state tracking
            $(document).on('change.fp-override', 'input[name*="[is_closed]"]', function() {
                var $checkbox = $(this);
                var $row = $checkbox.closest('.fp-override-row, tr');
                var originalChecked = $checkbox.data('original-checked') == '1';
                var currentChecked = $checkbox.is(':checked');
                
                if (originalChecked !== currentChecked) {
                    $row.addClass('has-changes');
                } else {
                    // Check other fields for changes
                    var anyChanges = false;
                    $row.find('.fp-override-input').each(function() {
                        var orig = $(this).data('original-value') || '';
                        var curr = $(this).val();
                        if (orig !== curr) {
                            anyChanges = true;
                            return false;
                        }
                    });
                    
                    if (!anyChanges) {
                        $row.removeClass('has-changes');
                    }
                }
            });
        },
        
        /**
         * Validate override date
         */
        validateOverrideDate: function($dateInput) {
            var dateValue = $dateInput.val();
            var $row = $dateInput.closest('.fp-override-row, tr');
            var $warning = $row.find('.fp-date-warning');
            
            if (!dateValue) {
                $warning.removeClass('show');
                $row.removeClass('distant-date');
                return;
            }
            
            var selectedDate = new Date(dateValue);
            var today = new Date();
            var fiveYearsFromNow = new Date();
            fiveYearsFromNow.setFullYear(today.getFullYear() + 5);
            
            // Check for very distant dates
            if (selectedDate > fiveYearsFromNow) {
                if (!$warning.length) {
                    $dateInput.after('<div class="fp-date-warning"><span class="dashicons dashicons-warning"></span> ' +
                        this.getAdminString(
                            'distant_date_warning',
                            'This date is very far in the future. Please verify it\'s correct.'
                        ) + '</div>');
                } else {
                    $warning.addClass('show');
                }
                $row.addClass('distant-date');
            } else {
                $warning.removeClass('show');
                $row.removeClass('distant-date');
            }
        },
        
        /**
         * Sort overrides by date
         */
        sortOverridesByDate: function() {
            var $container = $('#fp-overrides-container');
            var $tableBody = $container.find('tbody');
            
            if ($tableBody.length) {
                // Sort table rows
                var $rows = $tableBody.find('tr').get();
                $rows.sort(function(a, b) {
                    var dateA = $(a).find('input[name*="[date]"]').val() || '';
                    var dateB = $(b).find('input[name*="[date]"]').val() || '';
                    return dateA.localeCompare(dateB);
                });
                
                $.each($rows, function(index, row) {
                    $tableBody.append(row);
                });
            } else {
                // Sort div rows
                var $rows = $container.find('.fp-override-row').get();
                $rows.sort(function(a, b) {
                    var dateA = $(a).data('date') || $(a).find('input[name*="[date]"]').val() || '';
                    var dateB = $(b).data('date') || $(b).find('input[name*="[date]"]').val() || '';
                    return dateA.localeCompare(dateB);
                });
                
                $.each($rows, function(index, row) {
                    $container.append(row);
                });
            }
        },
        
        /**
         * Update override row numbers after sorting or removal
         */
        updateOverrideNumbers: function() {
            var $container = $('#fp-overrides-container');
            var $rows = $container.find('.fp-override-row, tbody tr');
            
            $rows.each(function(index) {
                var $row = $(this);
                $row.find('input, select').each(function() {
                    var name = $(this).attr('name');
                    if (name && name.includes('overrides[')) {
                        var newName = name.replace(/overrides\[\d+\]/, 'overrides[' + index + ']');
                        $(this).attr('name', newName);
                    }
                });
                
                $row.find('label[for]').each(function() {
                    var forAttr = $(this).attr('for');
                    if (forAttr && forAttr.includes('override-closed-')) {
                        $(this).attr('for', 'override-closed-' + index);
                    }
                });
                
                $row.find('input[id]').each(function() {
                    var id = $(this).attr('id');
                    if (id && id.includes('override-closed-')) {
                        $(this).attr('id', 'override-closed-' + index);
                    }
                });
                
                $row.attr('data-index', index);
            });
        },
        
        /**
         * Add schedule row
         */
        addScheduleRow: function() {
            var container = $('#fp-schedules-container');
            var index = container.find('.fp-schedule-row').length;
            
            var selectDayLabel = __('Select Day', 'fp-esperienze');
            var days = this.getWeekdayNames();
            var dayOrder = ['0', '1', '2', '3', '4', '5', '6'];

            var dayOptions = '<option value="">' + selectDayLabel + '</option>';
            dayOrder.forEach(function(value) {
                var label = days[value] || '';
                dayOptions += '<option value="' + value + '">' + label + '</option>';
            });
            
            var row = $('<div class="fp-schedule-row" data-index="' + index + '">' +
                '<input type="hidden" name="schedules[' + index + '][id]" value="">' +
                '<select name="schedules[' + index + '][day_of_week]" required>' + dayOptions + '</select>' +
                '<input type="time" name="schedules[' + index + '][start_time]" required>' +
                '<input type="number" name="schedules[' + index + '][duration_min]" value="60" min="1" step="1" required>' +
                '<input type="number" name="schedules[' + index + '][capacity]" placeholder="10" min="1" step="1" required>' +
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
        /**
         * Legacy addOverrideRow function - DEPRECATED
         * Use addOverrideCardClean() instead for new modern design
         */
        addOverrideRow: function() {
            // Redirect to modern implementation
            console.warn('FP Esperienze: addOverrideRow is deprecated, using modern addOverrideCardClean instead');
            this.addOverrideCardClean();
        },
        
        // ========================================
        // CLEAN REFACTORED METHODS
        // ========================================
        
        /**
         * Add time slot card - ENHANCED VERSION with comprehensive improvements
         */
        addTimeSlotCardClean: function() {
            // Debug logging removed for production
            
            try {
                var container = $('#fp-time-slots-container');
                if (!container.length) {
                    console.error('FP Esperienze: Time slots container not found');
                    this.showUserFeedback(__('Error: Unable to find time slots container. Please refresh the page.', 'fp-esperienze'), 'error');
                    return;
                }
                
                // Hide empty state with smooth transition
                var $emptyState = container.find('.fp-empty-slots-message');
                if ($emptyState.length) {
                    $emptyState.fadeOut(200);
                }
                
                var index = container.find('.fp-time-slot-card-clean').length;
                // Debug logging removed for production
                
                var cardHtml = this.createTimeSlotCardHTMLClean(index);
                if (!cardHtml) {
                    console.error('FP Esperienze: Failed to create card HTML');
                    this.showUserFeedback(__('Error creating time slot card. Please try again.', 'fp-esperienze'), 'error');
                    return;
                }
                
                // Create card with enhanced animation
                var $newCard = $(cardHtml);
                $newCard.css({
                    'opacity': '0',
                    'transform': 'translateY(20px) scale(0.95)',
                    'transition': 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)'
                });
                
                container.append($newCard);

                // Populate meeting points dropdown for the new card
                this.populateMeetingPointsDropdown($newCard.find('.fp-meeting-point-select'));

                // Trigger reflow and animate in
                requestAnimationFrame(function() {
                    $newCard.css({
                        'opacity': '1',
                        'transform': 'translateY(0) scale(1)'
                    });
                });

                // Gently focus the first time input without forcing a scroll jump
                setTimeout(function() {
                    var timeField = $newCard.find('input[type="time"]').get(0);

                    if (!timeField || typeof timeField.focus !== 'function') {
                        return;
                    }

                    try {
                        timeField.focus({ preventScroll: true });
                    } catch (focusError) {
                        // Older browsers may not support the options bag; fall back to a silent focus.
                        try {
                            timeField.focus();
                        } catch (fallbackError) {
                            // Ignore focus failures – the field is still added without scrolling.
                        }
                    }
                }, 250);

                // Update visual feedback and mark as dirty
                this.updateSlotCountFeedback();
                this.markUnsavedChanges();

                // Track for analytics (if needed)
                this.trackUserAction('time_slot_added', { index: index });

            } catch (error) {
                console.error('FP Esperienze: Error in addTimeSlotCardClean:', error);
                this.showUserFeedback('An unexpected error occurred while adding the time slot. Please try again.', 'error');
            }
        },

        /**
         * Track user actions for analytics and debugging
         */
        trackUserAction: function(action, data = {}) {
            try {
                // Store action in session for debugging
                if (window.sessionStorage) {
                    var actions = JSON.parse(sessionStorage.getItem('fp_user_actions') || '[]');
                    actions.push({
                        action: action,
                        data: data,
                        timestamp: new Date().toISOString()
                    });
                    // Keep only last 50 actions
                    if (actions.length > 50) {
                        actions = actions.slice(-50);
                    }
                    sessionStorage.setItem('fp_user_actions', JSON.stringify(actions));
                }
            } catch (error) {
                console.warn('FP Esperienze: Error tracking user action:', error);
            }
        },
        
        /**
         * Create time slot card HTML - CLEAN VERSION - ENHANCED
         */
        createTimeSlotCardHTMLClean: function(index) {
            try {
                var days = this.getWeekdayAbbreviations();
                var dayOrder = ['1', '2', '3', '4', '5', '6', '0'];
                var daysHtml = '';

                dayOrder.forEach(function(dayValue) {
                    var label = days[dayValue] || '';
                    daysHtml += '<div class="fp-day-pill-clean">' +
                        '<input type="checkbox" id="day-' + index + '-' + dayValue + '" name="builder_slots[' + index + '][days][]" value="' + dayValue + '">' +
                        '<label for="day-' + index + '-' + dayValue + '">' + label + '</label>' +
                    '</div>';
                });
                
                return '<div class="fp-time-slot-card-clean" data-index="' + index + '">' +
                    '<div class="fp-time-slot-content-clean">' +
                        '<div class="fp-time-slot-header-clean">' +
                            '<div class="fp-time-field-clean">' +
                                '<label for="time-' + index + '">' +
                                    '<span class="dashicons dashicons-clock"></span>' +
                                    'Start Time <span class="required">*</span>' +
                                '</label>' +
                                '<input type="time" id="time-' + index + '" name="builder_slots[' + index + '][start_time]" required>' +
                            '</div>' +
                            '<div class="fp-days-field-clean">' +
                                '<label>' +
                                    '<span class="dashicons dashicons-calendar-alt"></span>' +
                                    'Days of Week <span class="required">*</span>' +
                                '</label>' +
                                '<div class="fp-days-pills-clean">' + daysHtml + '</div>' +
                            '</div>' +
                            '<div class="fp-slot-actions-clean">' +
                                '<button type="button" class="fp-remove-time-slot-clean button">' +
                                    '<span class="dashicons dashicons-trash"></span>' +
                                    'Remove' +
                                '</button>' +
                            '</div>' +
                        '</div>' +
                        '<div class="fp-overrides-grid-clean">' +
                            '<div class="fp-override-field-clean">' +
                                '<label>Duration (minutes) <span class="required">*</span></label>' +
                                '<input type="number" name="builder_slots[' + index + '][duration_min]" min="1" required>' +
                            '</div>' +
                            '<div class="fp-override-field-clean">' +
                                '<label>Capacity <span class="required">*</span></label>' +
                                '<input type="number" name="builder_slots[' + index + '][capacity]" min="1" required>' +
                            '</div>' +
                            '<div class="fp-override-field-clean">' +
                                '<label>Language <span class="required">*</span></label>' +
                                '<input type="text" name="builder_slots[' + index + '][lang]" maxlength="10" required>' +
                            '</div>' +
                            '<div class="fp-override-field-clean">' +
                                '<label>Meeting Point <span class="required">*</span></label>' +
                                '<select name="builder_slots[' + index + '][meeting_point_id]" class="fp-meeting-point-select" required>' +
                                    '<option value="" disabled selected>Select meeting point</option>' +
                                '</select>' +
                            '</div>' +
                            '<div class="fp-override-field-clean">' +
                                '<label>Adult Price <span class="required">*</span></label>' +
                                '<input type="number" name="builder_slots[' + index + '][price_adult]" min="0" step="0.01" required>' +
                            '</div>' +
                            '<div class="fp-override-field-clean">' +
                                '<label>Child Price <span class="required">*</span></label>' +
                                '<input type="number" name="builder_slots[' + index + '][price_child]" min="0" step="0.01" required>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            } catch (error) {
                console.error('FP Esperienze: Error creating time slot card HTML:', error);
                return null;
            }
        },
        
        /**
         * Remove time slot card - CLEAN VERSION
         */
        removeTimeSlotCardClean: function($button) {
            var $card = $button.closest('.fp-time-slot-card-clean');
            var container = $('#fp-time-slots-container');
            var self = this;
            
            // Smooth removal animation
            $card.animate({
                opacity: 0,
                height: 0,
                marginBottom: 0,
                paddingTop: 0,
                paddingBottom: 0
            }, 400, 'swing', function() {
                $card.remove();
                
                // Show empty state if no cards left
                if (container.find('.fp-time-slot-card-clean').length === 0) {
                    var emptyMessage = '<div class="fp-empty-slots-message" style="opacity: 0;">' +
                        '<p>' + __('No time slots configured yet. Add your first time slot below.', 'fp-esperienze') + '</p>' +
                    '</div>';
                    var $emptyMsg = $(emptyMessage);
                    container.prepend($emptyMsg);
                    $emptyMsg.animate({opacity: 1}, 300);
                    
                    // Reset button text
                    var button = $('#fp-add-time-slot');
                    var $buttonText = button.find('span:not(.dashicons)');
                    if ($buttonText.length) {
                        $buttonText.text(__('Add Time Slot', 'fp-esperienze'));
                    } else {
                        button.text(__('Add Time Slot', 'fp-esperienze'));
                    }
                } else {
                    // Update button text if cards remain
                    self.updateSlotCountFeedback();
                }
            });
        },
        
        
        /**
         * Add override card - CLEAN VERSION - ENHANCED
         */
        addOverrideCardClean: function() {
            // Debug logging removed for production
            
            var container = $('#fp-overrides-container .fp-overrides-container-clean');
            if (!container.length) {
                console.error('FP Esperienze: Override container not found');
                alert(__('Error: Override container not found. Please refresh the page.', 'fp-esperienze'));
                return;
            }
            
            try {
                // Hide empty state if exists
                container.find('.fp-overrides-empty-clean').hide();
                
                var index = container.find('.fp-override-card-clean').length;
                // Debug logging removed for production
                
                var cardHtml = this.createOverrideCardHTMLClean(index);
                if (!cardHtml) {
                    console.error('FP Esperienze: Failed to create override card HTML');
                    return;
                }
                
                // Add the card with animation
                var $newCard = $(cardHtml);
                $newCard.css('opacity', '0');
                container.append($newCard);
                
                // Animate in
                $newCard.animate({opacity: 1}, 300);
                
                // Focus on the date input
                setTimeout(function() {
                    $newCard.find('input[type="date"]').focus();
                }, 350);
                
                // Debug logging removed for production
                
                // Update visual feedback
                this.updateOverrideCountFeedback();
                
            } catch (error) {
                console.error('FP Esperienze: Error in addOverrideCardClean:', error);
                alert(__('Error adding override. Please try again.', 'fp-esperienze'));
            }
        },

        /**
         * Validate form inputs - ENHANCED
         */
        validateTimeSlotInputs: function($card) {
            var isValid = true;
            var errors = [];
            
            // Validate time input
            var $timeInput = $card.find('input[type="time"]');
            if ($timeInput.length && !$timeInput.val()) {
                errors.push('Start time is required');
                $timeInput.addClass('fp-error-field');
                isValid = false;
            } else {
                $timeInput.removeClass('fp-error-field');
            }
            
            // Validate at least one day selected
            var checkedDays = $card.find('.fp-day-pill-clean input:checked').length;
            if (checkedDays === 0) {
                errors.push(__('Select at least one day of the week', 'fp-esperienze'));
                $card.find('.fp-days-selection-clean').addClass('fp-error-field');
                isValid = false;
            } else {
                $card.find('.fp-days-selection-clean').removeClass('fp-error-field');
            }

            // Show errors if any
            if (!isValid) {
                var errorMsg = __('Please fix the following errors:', 'fp-esperienze') + '\n' + errors.join('\n');
                alert(errorMsg);
            }
            
            return isValid;
        },
        
        /**
         * Enhanced accessibility support with comprehensive ARIA
         */
        enhanceAccessibility: function() {
            try {
                // Add comprehensive ARIA support
                $('#fp-time-slots-container').attr({
                    'role': 'region',
                    'aria-label': 'Time slots configuration',
                    'aria-live': 'polite'
                });
                
                $('#fp-overrides-container').attr({
                    'role': 'region',
                    'aria-label': 'Date overrides configuration',
                    'aria-live': 'polite'
                });
                
                // Enhanced button accessibility
                $('#fp-add-time-slot').attr({
                    'aria-describedby': 'fp-add-time-slot-desc',
                    'aria-expanded': 'false'
                });
                
                // Add keyboard navigation enhancement
                this.enhanceKeyboardNavigation();
                
                // Announce changes to screen readers
                this.setupScreenReaderAnnouncements();
                
                // Debug logging removed for production
            } catch (error) {
                console.warn('FP Esperienze: Accessibility enhancement failed:', error);
            }
        },

        /**
         * Enhanced keyboard navigation support
         */
        enhanceKeyboardNavigation: function() {
            // Keyboard support for day pills
            $(document).on('keydown.fp-accessibility', '.fp-day-pill-clean label', function(e) {
                if (e.key === ' ' || e.key === 'Enter') {
                    e.preventDefault();
                    $(this).closest('.fp-day-pill-clean').find('input').click();
                    // Announce state change
                    var dayName = $(this).text().trim();
                    var isChecked = $(this).closest('.fp-day-pill-clean').find('input').is(':checked');
                    this.announceToScreenReader(dayName + ' ' + (isChecked ? __('selected', 'fp-esperienze') : __('deselected', 'fp-esperienze')));
                }
            });
            
            // Enhanced focus management
            $(document).on('focus.fp-accessibility', '.fp-time-slot-card-clean input, .fp-time-slot-card-clean button', function() {
                $(this).closest('.fp-time-slot-card-clean').addClass('fp-focused');
            });
            
            $(document).on('blur.fp-accessibility', '.fp-time-slot-card-clean input, .fp-time-slot-card-clean button', function() {
                var $card = $(this).closest('.fp-time-slot-card-clean');
                setTimeout(function() {
                    if (!$card.find(':focus').length) {
                        $card.removeClass('fp-focused');
                    }
                }, 100);
            });
        },

        /**
         * Screen reader announcements for dynamic changes
         */
        setupScreenReaderAnnouncements: function() {
            // Create live region for announcements
            if (!$('#fp-sr-announcements').length) {
                $('body').append('<div id="fp-sr-announcements" class="fp-sr-only" aria-live="polite" aria-atomic="true"></div>');
            }
        },

        /**
         * Announce changes to screen readers
         */
        announceToScreenReader: function(message) {
            var $announcer = $('#fp-sr-announcements');
            if ($announcer.length) {
                $announcer.text(message);
                // Clear after announcement
                setTimeout(function() {
                    $announcer.empty();
                }, 1000);
            }
        },
        /**
         * Enhanced user feedback system
         */
        showUserFeedback: function(message, type = 'info', duration = 3000) {
            try {
                // Remove existing feedback
                $('.fp-user-feedback').remove();
                
                var iconClass = {
                    'success': 'dashicons-yes-alt',
                    'error': 'dashicons-warning',
                    'warning': 'dashicons-flag',
                    'info': 'dashicons-info'
                }[type] || 'dashicons-info';
                
                var feedbackHtml = '<div class="fp-user-feedback fp-feedback-' + type + '" style="position: fixed; top: 32px; right: 20px; z-index: 999999; background: #fff; border-left: 4px solid; padding: 12px 16px; border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); max-width: 350px; opacity: 0; transform: translateX(100%);">' +
                    '<div style="display: flex; align-items: center; gap: 8px;">' +
                        '<span class="dashicons ' + iconClass + '"></span>' +
                        '<span>' + message + '</span>' +
                    '</div>' +
                '</div>';
                
                var $feedback = $(feedbackHtml);
                $('body').append($feedback);
                
                // Animate in
                requestAnimationFrame(function() {
                    $feedback.css({
                        'opacity': '1',
                        'transform': 'translateX(0)',
                        'transition': 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)'
                    });
                });
                
                // Announce to screen readers
                this.announceToScreenReader(message);
                
                // Auto-remove
                setTimeout(function() {
                    $feedback.css({
                        'opacity': '0',
                        'transform': 'translateX(100%)'
                    });
                    setTimeout(function() {
                        $feedback.remove();
                    }, 300);
                }, duration);
                
                return $feedback;
            } catch (error) {
                console.error('FP Esperienze: Error showing user feedback:', error);
            }
        },

        /**
         * Performance-optimized debounce function
         */
        debounce: function(func, wait, immediate) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                var later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                var callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        },

        /**
         * Enhanced visual feedback and button state management
         */
        updateSlotCountFeedback: function() {
            try {
                var container = $('#fp-time-slots-container');
                var count = container.find('.fp-time-slot-card-clean').length;
                var button = $('#fp-add-time-slot');
                
                if (count > 0) {
                    button.find('span:not(.dashicons)').text(__('Add Another Time Slot', 'fp-esperienze'));
                    button.attr('aria-expanded', 'true');
                } else {
                    button.find('span:not(.dashicons)').text(__('Add Time Slot', 'fp-esperienze'));
                    button.attr('aria-expanded', 'false');
                }
                
                // Update container state for CSS targeting
                container.attr('data-slot-count', count);
                
                // Announce change to screen readers
                if (count > 0) {
                    var slotMsg = count === 1 ? __('1 time slot configured', 'fp-esperienze') : sprintf(__('%d time slots configured', 'fp-esperienze'), count);
                    this.announceToScreenReader(slotMsg);
                }
            } catch (error) {
                console.warn('FP Esperienze: Error updating slot count feedback:', error);
            }
        },

        /**
         * Enhanced override count feedback with better UX
         */
        updateOverrideCountFeedback: function() {
            try {
                var container = $('#fp-overrides-container .fp-overrides-container-clean');
                var count = container.find('.fp-override-card-clean').length;
                var button = $('#fp-add-override');
                
                if (count > 0) {
                    button.find('span:not(.dashicons)').text(__('Add Another Date Override', 'fp-esperienze'));
                    button.attr('aria-expanded', 'true');
                } else {
                    button.find('span:not(.dashicons)').text(__('Add Date Override', 'fp-esperienze'));
                    button.attr('aria-expanded', 'false');
                }
                
                // Update container state
                container.attr('data-override-count', count);
                
                // Announce change to screen readers
                if (count > 0) {
                    var overrideMsg = count === 1 ? __('1 date override configured', 'fp-esperienze') : sprintf(__('%d date overrides configured', 'fp-esperienze'), count);
                    this.announceToScreenReader(overrideMsg);
                }
            } catch (error) {
                console.warn('FP Esperienze: Error updating override count feedback:', error);
            }
        },

        /**
         * Enhanced form validation with better user feedback
         */
        validateTimeSlotInputsEnhanced: function($card) {
            try {
                var isValid = true;
                var errors = [];
                
                // Clear previous error states
                $card.find('.fp-error-field').removeClass('fp-error-field');
                $card.find('.fp-field-error-message').remove();
                
                // Validate time input
                var $timeInput = $card.find('input[type="time"]');
                if ($timeInput.length && !$timeInput.val()) {
                    errors.push(__('Start time is required', 'fp-esperienze'));
                    $timeInput.addClass('fp-error-field');
                    this.showFieldError($timeInput, __('Please select a start time', 'fp-esperienze'));
                    isValid = false;
                }
                
                // Validate at least one day selected
                var checkedDays = $card.find('.fp-day-pill-clean input:checked').length;
                if (checkedDays === 0) {
                    errors.push(__('Select at least one day of the week', 'fp-esperienze'));
                    $card.find('.fp-days-pills-clean').addClass('fp-error-field');
                    this.showFieldError($card.find('.fp-days-pills-clean'), __('Please select at least one day', 'fp-esperienze'));
                    isValid = false;
                }
                
                // Show consolidated error feedback
                if (!isValid) {
                    this.showUserFeedback(__('Please fix the validation errors in the time slot configuration', 'fp-esperienze'), 'error');
                    $card.addClass('fp-error-shake');
                    setTimeout(function() {
                        $card.removeClass('fp-error-shake');
                    }, 400);
                } else {
                    $card.addClass('fp-success-feedback');
                    setTimeout(function() {
                        $card.removeClass('fp-success-feedback');
                    }, 600);
                }
                
                return isValid;
            } catch (error) {
                console.error('FP Esperienze: Error validating time slot inputs:', error);
                return false;
            }
        },

        /**
         * Show field-specific error messages
         */
        showFieldError: function($field, message) {
            try {
                var $errorMsg = $('<div class="fp-field-error-message" style="color: #dc3545; font-size: 12px; margin-top: 4px;">' + message + '</div>');
                $field.after($errorMsg);
                
                // Auto-remove on focus/change
                $field.one('focus change', function() {
                    $errorMsg.fadeOut(200, function() {
                        $errorMsg.remove();
                    });
                    $field.removeClass('fp-error-field');
                });
            } catch (error) {
                console.warn('FP Esperienze: Error showing field error:', error);
            }
        },
        
        /**
         * Create override card HTML - CLEAN VERSION - ENHANCED
         */
        createOverrideCardHTMLClean: function(index) {
            try {
                return '<div class="fp-override-card-clean" data-index="' + index + '">' +
                    '<input type="hidden" name="overrides[' + index + '][id]" value="">' +
                    '<div class="fp-override-header-clean">' +
                        '<div class="fp-override-date-field-clean">' +
                            '<label for="override-date-' + index + '">' +
                                '<span class="dashicons dashicons-calendar-alt"></span>' +
                                'Date <span class="required">*</span>' +
                            '</label>' +
                            '<input type="date" id="override-date-' + index + '" name="overrides[' + index + '][date]" required>' +
                        '</div>' +
                        '<div class="fp-override-actions-clean">' +
                            '<div class="fp-override-checkbox-clean">' +
                                '<input type="checkbox" name="overrides[' + index + '][is_closed]" value="1" id="override-closed-' + index + '">' +
                                '<label for="override-closed-' + index + '">Closed</label>' +
                            '</div>' +
                            '<button type="button" class="fp-override-remove-clean button">' +
                                '<span class="dashicons dashicons-trash"></span>' +
                                'Remove' +
                            '</button>' +
                        '</div>' +
                    '</div>' +
                    '<div class="fp-override-fields-clean">' +
                        '<div class="fp-override-grid-clean">' +
                            '<div class="fp-override-field-clean">' +
                                '<label for="override-capacity-' + index + '">Capacity Override</label>' +
                                '<input type="number" id="override-capacity-' + index + '" name="overrides[' + index + '][capacity_override]" min="0" step="1" placeholder="Leave empty = use default">' +
                            '</div>' +
                            '<div class="fp-override-field-clean">' +
                                '<label for="override-reason-' + index + '">Reason/Note</label>' +
                                '<input type="text" id="override-reason-' + index + '" name="overrides[' + index + '][reason]" placeholder="Optional note (e.g., Holiday, Maintenance)">' +
                            '</div>' +
                            '<div class="fp-override-field-clean">' +
                                '<label for="override-adult-price-' + index + '">Adult Price</label>' +
                                '<input type="number" id="override-adult-price-' + index + '" name="overrides[' + index + '][price_adult]" min="0" step="0.01" placeholder="Leave empty = use default">' +
                            '</div>' +
                            '<div class="fp-override-field-clean">' +
                                '<label for="override-child-price-' + index + '">Child Price</label>' +
                                '<input type="number" id="override-child-price-' + index + '" name="overrides[' + index + '][price_child]" min="0" step="0.01" placeholder="Leave empty = use default">' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            } catch (error) {
                console.error('FP Esperienze: Error creating override card HTML:', error);
                return null;
            }
        },
        
        /**
         * Remove override card - CLEAN VERSION
         */
        removeOverrideCardClean: function($button) {
            var $card = $button.closest('.fp-override-card-clean');
            var container = $('#fp-overrides-container .fp-overrides-container-clean');
            var self = this;
            
            $card.fadeOut(300, function() {
                $card.remove();
                
                // Show empty state if no cards left
                if (container.find('.fp-override-card-clean').length === 0) {
                    var emptyMessage = '<div class="fp-overrides-empty-clean">' +
                        '<p>' + __('No date overrides configured. Add exceptions below for specific dates when you need to close, change capacity, or modify pricing.', 'fp-esperienze') + '</p>' +
                    '</div>';
                    container.prepend(emptyMessage);

                    // Reset button text
                    var button = $('#fp-add-override');
                    button.find('span:not(.dashicons)').text(__('Add Date Override', 'fp-esperienze'));
                } else {
                    // Update button text if only one left
                    self.updateOverrideCountFeedback();
                }
            });
        },
        
        /**
         * Performance monitoring and optimization
         */
        initPerformanceMonitoring: function() {
            try {
                // Track performance metrics
                this.performanceMetrics = {
                    startTime: performance.now(),
                    interactions: 0,
                    errors: 0
                };
                
                // Monitor frame rate for smooth animations
                this.monitorFrameRate();
                
                // Optimize heavy operations
                this.debouncedValidation = this.debounce(this.validateTimeSlotInputsEnhanced, 300);
                this.debouncedSummaryUpdate = this.debounce(this.updateSummaryTable, 200);
                
                // Debug logging removed for production
            } catch (error) {
                console.warn('FP Esperienze: Performance monitoring setup failed:', error);
            }
        },

        /**
         * Monitor frame rate for smooth animations
         */
        monitorFrameRate: function() {
            let frameCount = 0;
            let lastTime = performance.now();
            
            const checkFrameRate = (currentTime) => {
                frameCount++;
                
                if (currentTime - lastTime >= 1000) {
                    const fps = Math.round((frameCount * 1000) / (currentTime - lastTime));
                    
                    // Adjust animation complexity based on performance
                    if (fps < 30) {
                        document.body.classList.add('fp-reduced-animations');
                        console.warn('FP Esperienze: Low frame rate detected, reducing animations');
                    } else if (fps > 50) {
                        document.body.classList.remove('fp-reduced-animations');
                    }
                    
                    frameCount = 0;
                    lastTime = currentTime;
                }
                
                if (this.performanceMetrics) {
                    requestAnimationFrame(checkFrameRate);
                }
            };
            
            requestAnimationFrame(checkFrameRate);
        },

        /**
         * Enhanced error recovery system
         */
        initErrorRecovery: function() {
            try {
                // Global error handler for uncaught exceptions
                window.addEventListener('error', (event) => {
                    if (event.filename && event.filename.includes('admin.js')) {
                        console.error('FP Esperienze: Uncaught error:', event.error);
                        this.handleCriticalError(__('Unexpected error occurred', 'fp-esperienze'), event.error);
                    }
                });
                
                // Promise rejection handler
                window.addEventListener('unhandledrejection', (event) => {
                    console.error('FP Esperienze: Unhandled promise rejection:', event.reason);
                    this.handleCriticalError(__('Promise rejection', 'fp-esperienze'), event.reason);
                });
                
                // Set up periodic health checks
                this.startHealthChecks();
                
                // Debug logging removed for production
            } catch (error) {
                console.warn('FP Esperienze: Error recovery setup failed:', error);
            }
        },

        /**
         * Handle critical errors with recovery options
         */
        handleCriticalError: function(message, error) {
            try {
                this.performanceMetrics.errors++;
                
                // Show user-friendly error with recovery options
                this.showUserFeedback(
                    'A system error occurred. The interface will attempt to recover automatically.',
                    'error',
                    5000
                );
                
                // Attempt automatic recovery
                setTimeout(() => {
                    this.attemptRecovery();
                }, 1000);
                
                // Log detailed error information
                console.error('FP Esperienze Critical Error:', {
                    message: message,
                    error: error,
                    timestamp: new Date().toISOString(),
                    userAgent: navigator.userAgent,
                    url: window.location.href
                });
                
            } catch (recoveryError) {
                console.error('FP Esperienze: Error recovery failed:', recoveryError);
                // Fallback: show basic alert
                alert(__('A critical error occurred. Please refresh the page.', 'fp-esperienze'));
            }
        },

        /**
         * Attempt to recover from errors
         */
        attemptRecovery: function() {
            try {
                // Debug logging removed for production
                
                // Re-validate container existence
                this.validateContainers();
                
                // Re-bind critical event handlers
                this.rebindCriticalEvents();
                
                // Clear any stuck loading states
                $('.fp-loading').removeClass('fp-loading');
                
                // Announce recovery to user
                this.showUserFeedback('System recovered successfully. You can continue working.', 'success');
                
                // Debug logging removed for production
                
            } catch (error) {
                console.error('FP Esperienze: Recovery attempt failed:', error);
                this.showUserFeedback('Unable to recover automatically. Please refresh the page.', 'warning', 8000);
            }
        },

        /**
         * Re-bind critical event handlers after recovery
         */
        rebindCriticalEvents: function() {
            try {
                // Unbind all existing clean handlers
                $(document).off('.fp-clean');
                
                // Re-initialize the modern schedule builder
                this.initModernScheduleBuilder();
                
                // Debug logging removed for production
            } catch (error) {
                console.error('FP Esperienze: Failed to re-bind critical events:', error);
                throw error;
            }
        },

        /**
         * Periodic health checks
         */
        startHealthChecks: function() {
            setInterval(() => {
                try {
                    this.performHealthCheck();
                } catch (error) {
                    console.warn('FP Esperienze: Health check failed:', error);
                }
            }, 30000); // Check every 30 seconds
        },

        /**
         * Perform system health check
         */
        performHealthCheck: function() {
            // Check if critical containers exist
            const criticalElements = [
                '#fp-time-slots-container',
                '#fp-add-time-slot',
                '#fp-add-override'
            ];
            
            let missingElements = [];
            criticalElements.forEach(selector => {
                if (!$(selector).length) {
                    missingElements.push(selector);
                }
            });
            
            if (missingElements.length > 0) {
                console.warn('FP Esperienze: Missing critical elements:', missingElements);
                // Don't auto-recover from missing DOM elements as this might be expected
            }
            
            // Check for memory leaks (basic check)
            if (this.performanceMetrics.interactions > 1000) {
                console.warn('FP Esperienze: High interaction count, possible memory leak');
            }
        },

        /**
         * Handle override closed checkbox - ENHANCED VERSION
         */
        handleOverrideClosedClean: function($checkbox) {
            try {
                var $card = $checkbox.closest('.fp-override-card-clean');
                var $fields = $card.find('.fp-override-fields-clean');
                var isChecked = $checkbox.is(':checked');
                
                if (isChecked) {
                    $card.addClass('is-closed');
                    $fields.addClass('is-closed');
                    this.announceToScreenReader(__('Date marked as closed', 'fp-esperienze'));
                } else {
                    $card.removeClass('is-closed');
                    $fields.removeClass('is-closed');
                    this.announceToScreenReader(__('Date reopened for bookings', 'fp-esperienze'));
                }
                
                // Track the interaction
                if (this.performanceMetrics) {
                    this.performanceMetrics.interactions++;
                }
                
            } catch (error) {
                console.error('FP Esperienze: Error handling override closed:', error);
                this.showUserFeedback(__('Error updating closed status. Please try again.', 'fp-esperienze'), 'error');
            }
        },

        /**
         * Initialize enhanced features on page load
         */
        initializeEnhancements: function() {
            try {
                // Initialize performance monitoring
                this.initPerformanceMonitoring();
                
                // Initialize error recovery
                this.initErrorRecovery();
                
                // Initialize enhanced loading states
                this.initLoadingStates();
                
                // Initialize better form handling
                this.initFormEnhancements();
                
                // Add version info for debugging
                window.FPEsperienzeVersion = {
                    version: '2.0.0-enhanced',
                    features: [
                        'enhanced-accessibility',
                        'performance-monitoring', 
                        'error-recovery',
                        'visual-feedback',
                        'smooth-animations',
                        'enhanced-loading',
                        'form-improvements'
                    ],
                    initialized: new Date().toISOString()
                };
                
                // Debug logging removed for production
                
            } catch (error) {
                console.error('FP Esperienze: Failed to initialize enhancements:', error);
                // Continue with basic functionality even if enhancements fail
            }
        },

        /**
         * Initialize enhanced loading states
         */
        initLoadingStates: function() {
            var self = this;
            
            // Add loading overlay functionality
            $('body').append('<div id="fp-loading-overlay" class="fp-loading-overlay" style="display: none;"><div class="fp-spinner"></div><div class="fp-loading-text">' + __('Loading...', 'fp-esperienze') + '</div></div>');
            
            // Enhance AJAX requests with loading states
            $(document).on('click', '.fp-async-button', function(e) {
                var $button = $(this);
                var originalText = $button.text();
                
                $button.prop('disabled', true)
                       .addClass('loading')
                       .html('<span class="fp-spinner-small"></span> ' + ($button.data('loading-text') || __('Loading...', 'fp-esperienze')));
                
                // Restore button after timeout if no other action
                setTimeout(function() {
                    if ($button.hasClass('loading')) {
                        $button.prop('disabled', false)
                               .removeClass('loading')
                               .text(originalText);
                    }
                }, 10000);
            });
            
            // Enhanced form submission with loading
            $(document).on('submit', '.fp-enhanced-form', function(e) {
                var $form = $(this);
                var $submitButton = $form.find('input[type="submit"], button[type="submit"]');
                
                $form.addClass('submitting');
                $submitButton.prop('disabled', true);
                
                // Show loading overlay for complex forms
                if ($form.hasClass('fp-complex-form')) {
                    self.showLoadingOverlay($form.data('loading-message') || __('Processing...', 'fp-esperienze'));
                }
            });
        },

        /**
         * Initialize form enhancements
         */
        initFormEnhancements: function() {
            var self = this;
            
            // Auto-save drafts for long forms
            $('form.fp-auto-save').each(function() {
                var $form = $(this);
                var formId = $form.attr('id') || 'fp-form-' + Math.random().toString(36).substr(2, 9);
                
                $form.find('input, textarea, select').on('change input', _.debounce(function() {
                    self.autoSaveDraft(formId, $form.serialize());
                }, 2000));
                
                // Restore draft on page load
                self.restoreDraft(formId, $form);
            });
            
            // Enhanced validation feedback
            $(document).on('invalid', '.fp-enhanced-form input, .fp-enhanced-form textarea, .fp-enhanced-form select', function(e) {
                var $field = $(this);
                var $wrapper = $field.closest('.form-field, .field-wrapper');
                
                $wrapper.addClass('has-error');
                
                // Add custom error message
                var errorMsg = $field[0].validationMessage || 'This field is required';
                var $errorDiv = $wrapper.find('.field-error');
                
                if ($errorDiv.length === 0) {
                    $errorDiv = $('<div class="field-error"></div>');
                    $wrapper.append($errorDiv);
                }
                
                $errorDiv.text(errorMsg).show();
                
                // Remove error when field becomes valid
                $field.on('input change', function() {
                    if (this.checkValidity()) {
                        $wrapper.removeClass('has-error');
                        $errorDiv.hide();
                    }
                });
            });
            
            // Smart form sections with progress
            $('.fp-multi-step-form').each(function() {
                var $form = $(this);
                var $steps = $form.find('.form-step');
                var currentStep = 0;
                
                // Add progress bar
                var progressHtml = '<div class="fp-form-progress"><div class="progress-bar"><div class="progress-fill"></div></div><div class="step-counter"><span class="current">1</span> of <span class="total">' + $steps.length + '</span></div></div>';
                $form.prepend(progressHtml);
                
                // Step navigation
                $form.on('click', '.next-step', function() {
                    if (self.validateStep($steps.eq(currentStep))) {
                        currentStep++;
                        self.updateFormProgress($form, currentStep, $steps.length);
                        self.showStep($steps, currentStep);
                    }
                });
                
                $form.on('click', '.prev-step', function() {
                    currentStep--;
                    self.updateFormProgress($form, currentStep, $steps.length);
                    self.showStep($steps, currentStep);
                });
            });
        },

        /**
         * Show loading overlay
         */
        showLoadingOverlay: function(message) {
            var $overlay = $('#fp-loading-overlay');
            if (message) {
                $overlay.find('.fp-loading-text').text(message);
            }
            $overlay.fadeIn(200);
        },

        /**
         * Hide loading overlay
         */
        hideLoadingOverlay: function() {
            $('#fp-loading-overlay').fadeOut(200);
        },

        /**
         * Auto-save form draft
         */
        autoSaveDraft: function(formId, data) {
            try {
                localStorage.setItem('fp_draft_' + formId, data);
                this.showNotification('Draft saved automatically', 'info', 2000);
            } catch (e) {
                console.warn('FP Esperienze: Could not save draft:', e);
            }
        },

        /**
         * Restore form draft
         */
        restoreDraft: function(formId, $form) {
            try {
                var draft = localStorage.getItem('fp_draft_' + formId);
                if (draft) {
                    // Parse and restore form data
                    var params = new URLSearchParams(draft);
                    params.forEach(function(value, key) {
                        var $field = $form.find('[name="' + key + '"]');
                        if ($field.length) {
                            if ($field.is(':checkbox, :radio')) {
                                $field.filter('[value="' + value + '"]').prop('checked', true);
                            } else {
                                $field.val(value);
                            }
                        }
                    });
                    
                    this.showNotification('Draft restored', 'success', 3000);
                }
            } catch (e) {
                console.warn('FP Esperienze: Could not restore draft:', e);
            }
        },

        /**
         * Validate form step
         */
        validateStep: function($step) {
            var isValid = true;
            $step.find('input[required], textarea[required], select[required]').each(function() {
                if (!this.checkValidity()) {
                    isValid = false;
                    $(this).trigger('invalid');
                }
            });
            return isValid;
        },

        /**
         * Update form progress
         */
        updateFormProgress: function($form, currentStep, totalSteps) {
            var progress = ((currentStep + 1) / totalSteps) * 100;
            $form.find('.progress-fill').css('width', progress + '%');
            $form.find('.step-counter .current').text(currentStep + 1);
        },

        /**
         * Show form step
         */
        showStep: function($steps, stepIndex) {
            $steps.removeClass('active').eq(stepIndex).addClass('active');
        },

        /**
         * Show notification
         */
        showNotification: function(message, type, duration) {
            type = type || 'info';
            duration = duration || 5000;
            
            var $notification = $('<div class="fp-notification fp-notification-' + type + '">' + message + '</div>');
            
            // Remove existing notifications of same type
            $('.fp-notification-' + type).remove();
            
            $('body').append($notification);
            
            // Animate in
            setTimeout(function() {
                $notification.addClass('show');
            }, 100);
            
            // Auto remove
            setTimeout(function() {
                $notification.removeClass('show');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, duration);
        }
    };

    })(jQuery);
})();
