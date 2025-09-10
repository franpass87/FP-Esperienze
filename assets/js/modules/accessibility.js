/**
 * FP Esperienze - Accessibility Module
 * Handles accessibility features, ARIA support, and screen reader compatibility
 */

if (typeof jQuery === 'undefined') {
    console.error('FP Esperienze: jQuery is required for the accessibility module.');
    return;
}

(function($) {
    'use strict';

    window.FPEsperienzeAccessibility = {
        
        /**
         * Initialize accessibility features
         */
        init: function() {
            this.enhanceAccessibility();
            this.enhanceKeyboardNavigation();
            this.setupScreenReaderAnnouncements();
        },

        /**
         * Enhance accessibility features
         */
        enhanceAccessibility: function() {
            try {
                // Add ARIA labels to form fields
                this.addAriaLabels();
                
                // Setup keyboard shortcuts
                this.setupKeyboardShortcuts();
                
                // Enhance focus management
                this.enhanceFocusManagement();
                
                // Add skip links
                this.addSkipLinks();
                
                // Debug logging removed for production
                
            } catch (error) {
                console.error('FP Esperienze: Error enhancing accessibility:', error);
            }
        },

        /**
         * Add ARIA labels to form elements
         */
        addAriaLabels: function() {
            // Time slot inputs
            $('input[name*="[start_time]"]').attr({
                'aria-label': 'Experience start time',
                'aria-describedby': 'time-format-help'
            });
            
            $('input[name*="[duration_min]"]').attr({
                'aria-label': 'Experience duration in minutes',
                'aria-describedby': 'duration-help'
            });
            
            $('input[name*="[capacity]"]').attr({
                'aria-label': 'Maximum capacity for this time slot',
                'aria-describedby': 'capacity-help'
            });
            
            // Override inputs
            $('input[name*="[date]"]').attr({
                'aria-label': 'Override date',
                'aria-describedby': 'date-format-help'
            });
            
            $('input[name*="[available_spots]"]').attr({
                'aria-label': 'Available spots for this date',
                'aria-describedby': 'spots-help'
            });
            
            // Add remove button labels
            $('.fp-remove-time-slot-clean, .fp-remove-override-clean').attr({
                'aria-label': 'Remove this item',
                'title': 'Remove this item'
            });
            
            // Add add button labels
            $('#fp-add-time-slot-clean').attr({
                'aria-label': 'Add new time slot',
                'aria-expanded': 'false'
            });
            
            $('#fp-add-override').attr({
                'aria-label': 'Add new date override',
                'aria-expanded': 'false'
            });
        },

        /**
         * Setup keyboard shortcuts
         */
        setupKeyboardShortcuts: function() {
            $(document).on('keydown', function(e) {
                // Alt + T: Add time slot
                if (e.altKey && e.key === 't') {
                    e.preventDefault();
                    $('#fp-add-time-slot-clean').click();
                }
                
                // Alt + O: Add override
                if (e.altKey && e.key === 'o') {
                    e.preventDefault();
                    $('#fp-add-override').click();
                }
                
                // Alt + S: Save (submit form)
                if (e.altKey && e.key === 's') {
                    e.preventDefault();
                    $('#publish, #save-post').click();
                }
            });
        },

        /**
         * Enhance keyboard navigation
         */
        enhanceKeyboardNavigation: function() {
            // Arrow key navigation for day pills
            $(document).on('keydown', '.fp-day-pill-clean input', function(e) {
                var $pills = $(this).closest('.fp-days-pills-clean').find('.fp-day-pill-clean input');
                var currentIndex = $pills.index(this);
                var $target;
                
                switch(e.which) {
                    case 37: // Left arrow
                        $target = $pills.eq(currentIndex - 1);
                        break;
                    case 39: // Right arrow
                        $target = $pills.eq(currentIndex + 1);
                        break;
                    case 32: // Space
                        e.preventDefault();
                        $(this).prop('checked', !$(this).prop('checked')).trigger('change');
                        return;
                }
                
                if ($target && $target.length) {
                    e.preventDefault();
                    $target.focus();
                }
            });
            
            // Tab trap for modal dialogs (if any)
            this.setupTabTrapping();
        },

        /**
         * Setup tab trapping for modal dialogs
         */
        setupTabTrapping: function() {
            $(document).on('keydown', '.fp-modal', function(e) {
                if (e.which === 9) { // Tab key
                    var $modal = $(this);
                    var $focusableElements = $modal.find('a[href], button, textarea, input[type="text"], input[type="radio"], input[type="checkbox"], select');
                    var $firstFocusable = $focusableElements.first();
                    var $lastFocusable = $focusableElements.last();
                    
                    if (e.shiftKey) {
                        if (document.activeElement === $firstFocusable[0]) {
                            e.preventDefault();
                            $lastFocusable.focus();
                        }
                    } else {
                        if (document.activeElement === $lastFocusable[0]) {
                            e.preventDefault();
                            $firstFocusable.focus();
                        }
                    }
                }
            });
        },

        /**
         * Enhance focus management
         */
        enhanceFocusManagement: function() {
            // Store focus when adding new elements
            $(document).on('click', '#fp-add-time-slot-clean, #fp-add-override', function() {
                $(this).data('lastFocused', document.activeElement);
            });
            
            // Focus management for dynamically added content
            $(document).on('DOMNodeInserted', '.fp-time-slot-card-clean, .fp-override-card-clean', function() {
                var $newCard = $(this);
                setTimeout(function() {
                    var $firstInput = $newCard.find('input, select').first();
                    if ($firstInput.length) {
                        $firstInput.focus();
                        $newCard[0].scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'nearest' 
                        });
                    }
                }, 300);
            });
        },

        /**
         * Add skip links for better navigation
         */
        addSkipLinks: function() {
            if ($('#fp-skip-links').length) return;
            
            var skipLinksHTML = `
                <div id="fp-skip-links" class="fp-skip-links" aria-label="Skip navigation">
                    <a href="#fp-time-slots-container" class="fp-skip-link">Skip to time slots</a>
                    <a href="#fp-overrides-container" class="fp-skip-link">Skip to overrides</a>
                    <a href="#publish" class="fp-skip-link">Skip to save</a>
                </div>
            `;
            
            $('body').prepend(skipLinksHTML);
            
            // Style skip links
            var skipLinksCSS = `
                <style>
                .fp-skip-links {
                    position: absolute;
                    top: -1000px;
                    left: -1000px;
                    width: 1px;
                    height: 1px;
                    overflow: hidden;
                }
                .fp-skip-link:focus {
                    position: absolute;
                    top: 10px;
                    left: 10px;
                    width: auto;
                    height: auto;
                    padding: 8px 12px;
                    background: #fff;
                    color: #333;
                    border: 2px solid #0073aa;
                    border-radius: 3px;
                    text-decoration: none;
                    z-index: 999999;
                }
                </style>
            `;
            
            $('head').append(skipLinksCSS);
        },

        /**
         * Setup screen reader announcements
         */
        setupScreenReaderAnnouncements: function() {
            // Create announcement region if it doesn't exist
            if (!$('#fp-aria-announcements').length) {
                $('body').append('<div id="fp-aria-announcements" aria-live="polite" aria-atomic="true" class="sr-only"></div>');
            }
        },

        /**
         * Announce message to screen readers
         */
        announceToScreenReader: function(message) {
            var $announcer = $('#fp-aria-announcements');
            if ($announcer.length) {
                $announcer.text(message);
                // Clear after announcement
                setTimeout(function() {
                    $announcer.empty();
                }, 1000);
            }
        },

        /**
         * Validate form accessibility
         */
        validateFormAccessibility: function() {
            var issues = [];
            
            // Check for inputs without labels
            $('input, select, textarea').each(function() {
                var $input = $(this);
                var id = $input.attr('id');
                var hasLabel = false;
                
                if (id) {
                    hasLabel = $('label[for="' + id + '"]').length > 0;
                }
                
                if (!hasLabel && !$input.attr('aria-label') && !$input.attr('aria-labelledby')) {
                    issues.push('Input without proper label: ' + ($input.attr('name') || 'unnamed'));
                }
            });
            
            // Check for proper heading structure
            var headings = $('h1, h2, h3, h4, h5, h6');
            if (headings.length === 0) {
                issues.push('No headings found for document structure');
            }
            
            // Check for alt text on images
            $('img').each(function() {
                if (!$(this).attr('alt')) {
                    issues.push('Image without alt text: ' + ($(this).attr('src') || 'unknown'));
                }
            });
            
            if (issues.length > 0) {
                console.warn('FP Esperienze Accessibility Issues:', issues);
            } else {
                // Debug logging removed for production
            }
            
            return issues;
        },

        /**
         * Add high contrast mode toggle
         */
        addHighContrastToggle: function() {
            if ($('#fp-high-contrast-toggle').length) return;
            
            var toggleHTML = `
                <button id="fp-high-contrast-toggle" class="fp-accessibility-toggle" 
                        aria-label="Toggle high contrast mode" 
                        title="Toggle high contrast mode">
                    <span class="dashicons dashicons-visibility"></span>
                    High Contrast
                </button>
            `;
            
            $('.wrap h1').after(toggleHTML);
            
            $('#fp-high-contrast-toggle').on('click', function() {
                $('body').toggleClass('fp-high-contrast');
                var isHighContrast = $('body').hasClass('fp-high-contrast');
                $(this).attr('aria-pressed', isHighContrast);
                
                // Store preference
                localStorage.setItem('fp-high-contrast', isHighContrast);
                
                // Announce change
                this.announceToScreenReader(
                    isHighContrast ? 'High contrast mode enabled' : 'High contrast mode disabled'
                );
            }.bind(this));
            
            // Load saved preference
            if (localStorage.getItem('fp-high-contrast') === 'true') {
                $('body').addClass('fp-high-contrast');
                $('#fp-high-contrast-toggle').attr('aria-pressed', 'true');
            }
        },

        /**
         * Add focus indicators
         */
        enhanceFocusIndicators: function() {
            var focusCSS = `
                <style>
                .fp-enhanced-focus:focus {
                    outline: 3px solid #0073aa !important;
                    outline-offset: 2px !important;
                    box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.3) !important;
                }
                
                .fp-high-contrast {
                    background: #000 !important;
                    color: #fff !important;
                }
                
                .fp-high-contrast input,
                .fp-high-contrast select,
                .fp-high-contrast textarea {
                    background: #fff !important;
                    color: #000 !important;
                    border: 2px solid #fff !important;
                }
                
                .fp-high-contrast button {
                    background: #fff !important;
                    color: #000 !important;
                    border: 2px solid #fff !important;
                }
                
                .sr-only {
                    position: absolute !important;
                    width: 1px !important;
                    height: 1px !important;
                    padding: 0 !important;
                    margin: -1px !important;
                    overflow: hidden !important;
                    clip: rect(0, 0, 0, 0) !important;
                    white-space: nowrap !important;
                    border: 0 !important;
                }
                </style>
            `;
            
            $('head').append(focusCSS);
            
            // Add enhanced focus class to interactive elements
            $('input, select, textarea, button, a').addClass('fp-enhanced-focus');
        }
    };

})(jQuery);