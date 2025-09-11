/**
 * Admin UX Enhancer JavaScript
 * Provides enhanced admin interface interactions and bulk operations
 */

(function($) {
    'use strict';
    
    // Global Admin UX object
    window.FPAdminUXEnhancer = {
        initialized: false,
        progressBar: null,
        unsavedChanges: false,
        
        /**
         * Initialize admin UX enhancements
         */
        init: function() {
            if (this.initialized) return;
            
            this.setupElements();
            this.bindEvents();
            this.initBulkOperations();
            this.initUnsavedChangesWarning();
            this.initConfirmDialogs();
            this.initProgressiveLoading();
            
            this.initialized = true;
            console.log('FP Admin UX Enhancer initialized');
        },
        
        /**
         * Setup DOM elements
         */
        setupElements: function() {
            this.progressBar = $('#fp-progress-bar');
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;
            
            // Form change tracking
            $('form').on('change input', 'input, select, textarea', function() {
                self.unsavedChanges = true;
            });
            
            // Form submission resets unsaved changes
            $('form').on('submit', function() {
                self.unsavedChanges = false;
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
            
            // Bulk action handlers
            $('#bulk-action-selector-top, #bulk-action-selector-bottom').on('change', function() {
                var action = $(this).val();
                var $submitButton = $(this).closest('.tablenav').find('.action');
                
                if (action && action !== '-1') {
                    $submitButton.prop('disabled', false);
                } else {
                    $submitButton.prop('disabled', true);
                }
            });
            
            // Bulk checkbox selection
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
            
            $(window).on('beforeunload', function(e) {
                if (self.unsavedChanges) {
                    var message = fpAdminUX.i18n.unsaved_changes;
                    e.returnValue = message;
                    return message;
                }
            });
            
            // Navigation link warnings
            $('a:not(.no-warning)').on('click', function(e) {
                if (self.unsavedChanges) {
                    if (!confirm(fpAdminUX.i18n.unsaved_changes)) {
                        e.preventDefault();
                    } else {
                        self.unsavedChanges = false;
                    }
                }
            });
        },
        
        /**
         * Initialize confirm dialogs
         */
        initConfirmDialogs: function() {
            // Delete confirmations
            $('.delete-link, .fp-delete-button').on('click', function(e) {
                var message = fpAdminUX.i18n.confirm_delete;
                if (!confirm(message)) {
                    e.preventDefault();
                }
            });
            
            // Bulk delete confirmations
            $('form').on('submit', function(e) {
                var $form = $(this);
                var action = $form.find('select[name="action"]').val() || 
                            $form.find('select[name="action2"]').val();
                
                if (action && action.indexOf('delete') !== -1) {
                    var selectedCount = $form.find('input[type="checkbox"]:checked').length - 1; // Exclude select-all
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
            
            // AJAX loading for admin forms
            $('.fp-ajax-form').on('submit', function(e) {
                e.preventDefault();
                self.submitAjaxForm($(this));
            });
            
            // Tab loading
            $('.fp-tab-loader').on('click', function(e) {
                e.preventDefault();
                self.loadTabContent($(this));
            });
        },
        
        /**
         * Handle bulk actions
         */
        handleBulkAction: function($button) {
            var action = $button.data('action');
            var selectedItems = this.getSelectedItems();
            
            if (selectedItems.length === 0) {
                alert('Please select items to perform bulk action.');
                return;
            }
            
            var message = fpAdminUX.i18n.bulk_processing.replace('%d', selectedItems.length);
            this.showProgress(message);
            
            this.performBulkAction(action, selectedItems);
        },
        
        /**
         * Perform bulk action with progress tracking
         */
        performBulkAction: function(action, items) {
            var self = this;
            var processed = 0;
            var total = items.length;
            var errors = [];
            
            function processNext() {
                if (processed >= total) {
                    self.hideProgress();
                    if (errors.length > 0) {
                        alert(fpAdminUX.i18n.bulk_error + ': ' + errors.join(', '));
                    } else {
                        alert(fpAdminUX.i18n.bulk_complete);
                        location.reload();
                    }
                    return;
                }
                
                var item = items[processed];
                var progress = Math.round((processed / total) * 100);
                self.updateProgress(progress);
                
                $.ajax({
                    url: fpAdminUX.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'fp_bulk_' + action,
                        nonce: fpAdminUX.nonce,
                        item_id: item
                    },
                    success: function(response) {
                        if (!response.success) {
                            errors.push('Item ' + item + ': ' + (response.data || 'Unknown error'));
                        }
                        processed++;
                        setTimeout(processNext, 100); // Small delay between requests
                    },
                    error: function() {
                        errors.push('Item ' + item + ': Network error');
                        processed++;
                        setTimeout(processNext, 100);
                    }
                });
            }
            
            processNext();
        },
        
        /**
         * Show progress dialog
         */
        showProgress: function(message) {
            $('#fp-admin-bulk-progress').show();
            $('#fp-admin-bulk-progress h3').text(message);
            this.updateProgress(0);
        },
        
        /**
         * Update progress bar
         */
        updateProgress: function(percent) {
            if (this.progressBar.length) {
                this.progressBar.progressbar('value', percent);
                $('#fp-progress-text').text(percent + '%');
            }
        },
        
        /**
         * Hide progress dialog
         */
        hideProgress: function() {
            $('#fp-admin-bulk-progress').hide();
        },
        
        /**
         * Get selected items from checkboxes
         */
        getSelectedItems: function() {
            var items = [];
            $('tbody .check-column input[type="checkbox"]:checked').each(function() {
                var value = $(this).val();
                if (value && value !== 'on') {
                    items.push(value);
                }
            });
            return items;
        },
        
        /**
         * Update bulk action button states
         */
        updateBulkActionButtons: function() {
            var selectedCount = this.getSelectedItems().length;
            var $buttons = $('.action');
            
            if (selectedCount > 0) {
                $buttons.prop('disabled', false);
            } else {
                $buttons.prop('disabled', true);
            }
        },
        
        /**
         * Handle progressive form submission
         */
        handleProgressiveForm: function(e, form) {
            var $form = $(form);
            var hasSteps = $form.data('steps');
            
            if (hasSteps) {
                e.preventDefault();
                this.processFormSteps($form);
                return false;
            }
            
            return true;
        },
        
        /**
         * Process multi-step form
         */
        processFormSteps: function($form) {
            var self = this;
            var steps = $form.data('steps');
            var currentStep = 0;
            
            function processNextStep() {
                if (currentStep >= steps) {
                    self.hideProgress();
                    $form.trigger('fp:form-complete');
                    return;
                }
                
                var progress = Math.round((currentStep / steps) * 100);
                self.updateProgress(progress);
                
                // Trigger step processing
                $form.trigger('fp:process-step', [currentStep]);
                
                currentStep++;
                setTimeout(processNextStep, 500);
            }
            
            this.showProgress('Processing form...');
            processNextStep();
        },
        
        /**
         * Submit AJAX form
         */
        submitAjaxForm: function($form) {
            var self = this;
            var $submitButton = $form.find('[type="submit"]');
            var originalText = $submitButton.text();
            
            $submitButton.prop('disabled', true).text('Processing...');
            
            $.ajax({
                url: $form.attr('action') || fpAdminUX.ajax_url,
                type: $form.attr('method') || 'POST',
                data: $form.serialize(),
                success: function(response) {
                    if (response.success) {
                        $form.trigger('fp:form-success', [response]);
                        if (response.data && response.data.redirect) {
                            window.location.href = response.data.redirect;
                        }
                    } else {
                        $form.trigger('fp:form-error', [response]);
                        alert('Error: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function() {
                    $form.trigger('fp:form-error');
                    alert('Network error occurred. Please try again.');
                },
                complete: function() {
                    $submitButton.prop('disabled', false).text(originalText);
                }
            });
        },
        
        /**
         * Load tab content dynamically
         */
        loadTabContent: function($tab) {
            var url = $tab.attr('href');
            var $container = $($tab.data('target'));
            
            if (!$container.length) return;
            
            $container.html('<div class="fp-loading">Loading...</div>');
            
            $.ajax({
                url: url,
                type: 'GET',
                success: function(response) {
                    $container.html(response);
                    $container.trigger('fp:tab-loaded');
                },
                error: function() {
                    $container.html('<div class="fp-error">Failed to load content.</div>');
                }
            });
        },
        
        /**
         * Add unsaved changes indicator
         */
        showUnsavedWarning: function() {
            if (!$('.fp-unsaved-warning').length) {
                var warning = $('<div class="fp-unsaved-warning">' +
                    'You have unsaved changes that will be lost if you navigate away.' +
                    '</div>');
                $('.wrap').prepend(warning);
            }
        },
        
        /**
         * Remove unsaved changes indicator
         */
        hideUnsavedWarning: function() {
            $('.fp-unsaved-warning').remove();
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        FPAdminUXEnhancer.init();
        
        // Initialize jQuery UI progressbar if available
        if ($.fn.progressbar) {
            $('#fp-progress-bar').progressbar({
                value: 0,
                max: 100
            });
        }
    });
    
})(jQuery);