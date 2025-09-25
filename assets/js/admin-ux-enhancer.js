/**
 * Admin UX Enhancer JavaScript
 * Provides enhanced admin interface interactions and bulk operations
 */

(function($) {
    'use strict';

    var UNSAVED_NOTICE_CLASS = 'fp-unsaved-warning';

    // Global Admin UX object
    window.FPAdminUXEnhancer = {
        initialized: false,
        progressBar: null,
        unsavedChanges: false,
        dirtyFields: new Set(),
        fieldInitialValues: new Map(),
        fieldDefaultValues: new Map(),
        formIdCounter: 0,

        /**
         * Initialize admin UX enhancements
         */
        init: function() {
            if (this.initialized) {
                return;
            }

            this.setupElements();
            this.clearPersistedUnsavedFlag();
            this.prepareUnsavedChangeTracking();
            this.bindEvents();
            this.initBulkOperations();
            this.initUnsavedChangesWarning();
            this.initConfirmDialogs();
            this.initProgressiveLoading();

            this.initialized = true;
        },

        /**
         * Setup DOM elements
         */
        setupElements: function() {
            this.progressBar = $('#fp-progress-bar');
        },

        /**
         * Capture the initial state for tracked forms so genuine edits can be detected.
         */
        prepareUnsavedChangeTracking: function() {
            var self = this;

            this.dirtyFields = new Set();
            this.fieldInitialValues = new Map();
            this.fieldDefaultValues = new Map();

            $('form').each(function() {
                self.captureInitialValuesForForm(this, true);
            });

            this.setUnsavedState(false);
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;

            $('form').on('change input', 'input, select, textarea', function(event) {
                self.handleFieldInteraction(event);
            });

            $('form').on('submit', function() {
                self.resetUnsavedState(this);
            });

            $('form').on('reset', function() {
                self.resetUnsavedState(this);
            });

            // Bulk operation triggers
            $('.fp-bulk-action').on('click', function() {
                self.handleBulkAction($(this));
            });

            // Progressive form loading
            $('.fp-progressive-form').on('submit', function(e) {
                return self.handleProgressiveForm(e, this);
            });
        },

        /**
         * Initialize bulk operations
         */
        initBulkOperations: function() {
            var self = this;

            $('#bulk-action-selector-top, #bulk-action-selector-bottom').on('change', function() {
                var action = $(this).val();
                var $submitButton = $(this).closest('.tablenav').find('.action');

                if (action && action !== '-1') {
                    $submitButton.prop('disabled', false);
                } else {
                    $submitButton.prop('disabled', true);
                }
            });

            $('#cb-select-all-1, #cb-select-all-2').on('change', function() {
                var isChecked = $(this).prop('checked');
                $('tbody .check-column input[type="checkbox"]').prop('checked', isChecked);
                self.updateBulkActionButtons();
            });

            $('tbody .check-column input[type="checkbox"]').on('change', function() {
                self.updateBulkActionButtons();
            });
        },

        /**
         * Initialize unsaved changes warning
         */
        initUnsavedChangesWarning: function() {
            var self = this;

            $(window).on('pagehide', function() {
                if (!self.unsavedChanges) {
                    return;
                }

                try {
                    if (typeof sessionStorage !== 'undefined') {
                        sessionStorage.setItem('fp_unsaved_changes', 'true');
                    }
                } catch (error) {
                    // Storage access can fail in privacy modes. Ignore.
                }
            });

            $(window).on('beforeunload', function() {
                if (!self.unsavedChanges) {
                    return undefined;
                }

                return 'You have unsaved changes. Are you sure you want to leave?';
            });

            $('.wrap a[href]').on('click', function(e) {
                if (!self.unsavedChanges) {
                    return;
                }

                var href = ($(this).attr('href') || '').trim();
                var isAnchor = !href || href.charAt(0) === '#';
                var isJavaScript = href.toLowerCase().indexOf('javascript:') === 0;

                if (isAnchor || isJavaScript || $(this).attr('target') === '_blank' || $(this).data('noWarning') === true) {
                    return;
                }

                if (!confirm(fpAdminUX.i18n.unsaved_changes || 'You have unsaved changes. Are you sure you want to leave?')) {
                    e.preventDefault();
                } else {
                    self.setUnsavedState(false);
                }
            });
        },

        /**
         * Initialize confirm dialogs
         */
        initConfirmDialogs: function() {
            $('.delete-link, .fp-delete-button').on('click', function(e) {
                var message = fpAdminUX.i18n.confirm_delete;
                if (!confirm(message)) {
                    e.preventDefault();
                }
            });

            $('form').on('submit', function(e) {
                var $form = $(this);
                var action = $form.find('select[name="action"]').val() ||
                            $form.find('select[name="action2"]').val();

                if (action && action.indexOf('delete') !== -1) {
                    var selectedCount = $form.find('input[type="checkbox"]:checked').length - 1;
                    if (selectedCount > 0) {
                        var message = fpAdminUX.i18n.confirm_bulk_delete.replace('%d', selectedCount);
                        if (!confirm(message)) {
                            e.preventDefault();
                        }
                    }
                }
            });
        },

        /**
         * Initialize progressive loading for admin
         */
        initProgressiveLoading: function() {
            var self = this;

            $('.fp-ajax-form').on('submit', function(e) {
                e.preventDefault();
                self.submitAjaxForm($(this));
            });

            $('.fp-tab-loader').on('click', function(e) {
                e.preventDefault();
                self.loadTabContent($(this));
            });
        },

        /**
         * Handle field interaction for unsaved tracking.
         */
        handleFieldInteraction: function(event) {
            if (!event || !event.target || !this.isUserGeneratedEvent(event)) {
                return;
            }

            var field = event.target;
            var key = this.getFieldKey(field);

            if (!key) {
                return;
            }

            if (!this.fieldDefaultValues.has(key)) {
                this.fieldDefaultValues.set(key, this.getFieldDefaultValue(field));
            }

            if (!this.fieldInitialValues.has(key)) {
                this.fieldInitialValues.set(key, this.fieldDefaultValues.get(key));
            }

            var initialValue = this.fieldInitialValues.get(key);
            var currentValue = this.readFieldValue(field);

            if (this.valuesDiffer(initialValue, currentValue)) {
                this.markFieldDirty(key);
            } else {
                this.clearFieldDirty(key);
            }

            this.updateUnsavedStateFromDirtyFields();
        },

        /**
         * Ensure the event originated from user interaction.
         */
        isUserGeneratedEvent: function(event) {
            if (!event.originalEvent) {
                return false;
            }

            if (typeof event.originalEvent.isTrusted !== 'undefined' && event.originalEvent.isTrusted === false) {
                return false;
            }

            return true;
        },

        /**
         * Assign persistent identifiers to forms for tracking.
         */
        ensureFormId: function(form) {
            if (!form) {
                return null;
            }

            var $form = $(form);
            var existing = $form.data('fpFormId');

            if (existing) {
                return existing;
            }

            var formId;

            if (form.id) {
                formId = 'form#' + form.id;
            } else if (form.name) {
                formId = 'form[' + form.name + ']';
            } else {
                this.formIdCounter += 1;
                formId = 'form-' + this.formIdCounter;
            }

            $form.data('fpFormId', formId);

            return formId;
        },

        /**
         * Capture initial values for a form.
         */
        captureInitialValuesForForm: function(form, includeDefaults) {
            var self = this;
            var formId = this.ensureFormId(form);

            if (!formId) {
                return;
            }

            $(form).find('input, select, textarea').each(function() {
                var key = self.getFieldKey(this);

                if (!key) {
                    return;
                }

                if (includeDefaults) {
                    self.fieldDefaultValues.set(key, self.getFieldDefaultValue(this));
                }

                self.fieldInitialValues.set(key, self.readFieldValue(this));
            });
        },

        /**
         * Reset unsaved state for a form and refresh its baseline values.
         */
        resetUnsavedState: function(form) {
            if (form) {
                this.captureInitialValuesForForm(form, true);
                this.clearDirtyFieldsForForm(form);
            } else {
                this.prepareUnsavedChangeTracking();
            }

            this.dirtyFields.delete('__manual__');
            this.updateUnsavedStateFromDirtyFields();
        },

        /**
         * Remove tracked dirty fields for the provided form.
         */
        clearDirtyFieldsForForm: function(form) {
            var formId = this.ensureFormId(form);

            if (!formId) {
                return;
            }

            var prefix = formId + '::';

            this.dirtyFields.forEach(function(key) {
                if (typeof key === 'string' && key.indexOf(prefix) === 0) {
                    this.dirtyFields.delete(key);
                }
            }, this);
        },

        /**
         * Read the current value from a field in a normalised way.
         */
        readFieldValue: function(field) {
            if (!field) {
                return '';
            }

            var $field = $(field);

            if ($field.is(':radio')) {
                var form = field.form;
                if (!form || !field.name) {
                    return '__none__';
                }

                var $checked = $(form).find('input[type="radio"][name="' + field.name + '"]:checked');
                return $checked.length ? String($checked.val()) : '__none__';
            }

            if ($field.is(':checkbox')) {
                return $field.prop('checked') ? '1' : '0';
            }

            if ($field.is('select')) {
                if (field.multiple) {
                    var values = $field.val();
                    if (!Array.isArray(values)) {
                        return '';
                    }
                    return values.slice().sort().join(',');
                }

                var singleValue = $field.val();
                return singleValue === null ? '' : String(singleValue);
            }

            var value = $field.val();
            return value === null ? '' : String(value);
        },

        /**
         * Retrieve the default (initial) value for a field.
         */
        getFieldDefaultValue: function(field) {
            if (!field) {
                return '';
            }

            if (field.type === 'checkbox') {
                return field.defaultChecked ? '1' : '0';
            }

            if (field.type === 'radio') {
                if (!field.form || !field.name) {
                    return '__none__';
                }

                var defaults = $(field.form).find('input[type="radio"][name="' + field.name + '"]').filter(function() {
                    return this.defaultChecked;
                });

                return defaults.length ? String(defaults.val()) : '__none__';
            }

            if (field.tagName === 'SELECT' && field.multiple) {
                var defaultValues = Array.from(field.options).filter(function(option) {
                    return option.defaultSelected;
                }).map(function(option) {
                    return option.value;
                });

                defaultValues.sort();
                return defaultValues.join(',');
            }

            if (typeof field.defaultValue !== 'undefined') {
                return String(field.defaultValue);
            }

            return '';
        },

        /**
         * Generate a unique key for a field within its form.
         */
        getFieldKey: function(field) {
            if (!field || !field.form) {
                return null;
            }

            var formId = this.ensureFormId(field.form);
            var name = field.name || field.id;

            if (!formId || !name) {
                return null;
            }

            if (field.type === 'checkbox' && name.slice(-2) === '[]') {
                return formId + '::' + name + '::' + String(field.value || '1');
            }

            return formId + '::' + name;
        },

        /**
         * Determine if the field's value has changed.
         */
        valuesDiffer: function(a, b) {
            return String(a) !== String(b);
        },

        /**
         * Track a field as dirty.
         */
        markFieldDirty: function(key) {
            if (!key) {
                return;
            }

            this.dirtyFields.add(key);
        },

        /**
         * Remove a field from the dirty tracking set.
         */
        clearFieldDirty: function(key) {
            if (!key) {
                return;
            }

            this.dirtyFields.delete(key);
        },

        /**
         * Update the unsaved state flag based on tracked dirty fields.
         */
        updateUnsavedStateFromDirtyFields: function() {
            this.setUnsavedState(this.dirtyFields.size > 0);
        },

        /**
         * Explicitly set the unsaved state and sync UI/flags.
         */
        setUnsavedState: function(state) {
            var newState = Boolean(state);

            if (newState) {
                if (!this.unsavedChanges) {
                    this.unsavedChanges = true;
                    this.showUnsavedWarning();
                }
            } else {
                if (this.unsavedChanges) {
                    this.unsavedChanges = false;
                    this.hideUnsavedWarning();
                }
            }

            this.syncUnsavedState();
        },

        /**
         * Show unsaved notice in the admin UI.
         */
        showUnsavedWarning: function() {
            if ($('.' + UNSAVED_NOTICE_CLASS).length) {
                return;
            }

            var notice = $(
                '<div class="notice notice-warning ' + UNSAVED_NOTICE_CLASS + '">' +
                    '<p>' + (fpAdminUX.i18n.unsaved_notice || 'You have unsaved changes on this page.') + '</p>' +
                '</div>'
            );

            $('.wrap').first().prepend(notice);
        },

        /**
         * Remove unsaved notice from the admin UI.
         */
        hideUnsavedWarning: function() {
            $('.' + UNSAVED_NOTICE_CLASS).remove();
        },

        /**
         * Remove any persisted flag from a previous navigation cycle.
         */
        clearPersistedUnsavedFlag: function() {
            try {
                if (typeof sessionStorage !== 'undefined') {
                    sessionStorage.removeItem('fp_unsaved_changes');
                }
            } catch (error) {
                // Ignore storage access errors.
            }
        },

        /**
         * Convenience helper for external modules to clear warnings.
         */
        clearUnsavedState: function() {
            this.setUnsavedState(false);
        },

        /**
         * Allow other modules to flag manual unsaved changes.
         */
        flagUnsavedChange: function(persistent) {
            if (persistent) {
                this.dirtyFields.add('__manual__');
            }

            this.setUnsavedState(true);
        },

        /**
         * Refresh baselines for all tracked forms.
         */
        refreshTrackedFormBaselines: function() {
            this.prepareUnsavedChangeTracking();
        },

        /**
         * Sync unsaved state with the main admin controller.
         */
        syncUnsavedState: function() {
            if (window.FPEsperienzeAdmin) {
                window.FPEsperienzeAdmin.hasUnsavedChanges = this.unsavedChanges;
            }
        }
    };

    $(document).ready(function() {
        FPAdminUXEnhancer.init();

        if ($.fn.progressbar) {
            $('#fp-progress-bar').progressbar({
                value: 0,
                max: 100
            });
        }
    });

})(jQuery);
