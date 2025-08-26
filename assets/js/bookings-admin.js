/**
 * FP Esperienze Bookings Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize bookings admin functionality
        FPEsperienzeBookingsAdmin.init();
    });

    window.FPEsperienzeBookingsAdmin = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Export CSV button
            $('#export-csv').on('click', this.exportCSV);
        },

        /**
         * Export bookings to CSV
         */
        exportCSV: function(e) {
            e.preventDefault();
            
            var button = $(this);
            var originalText = button.text();
            
            button.text(fpEsperienzeBookings.strings.loading).prop('disabled', true);
            
            // Create form for CSV export
            var form = $('<form>', {
                'method': 'POST',
                'action': fpEsperienzeBookings.ajaxUrl,
                'target': '_blank'
            });
            
            form.append($('<input>', {
                'type': 'hidden',
                'name': 'action',
                'value': 'fp_export_bookings_csv'
            }));
            
            form.append($('<input>', {
                'type': 'hidden',
                'name': 'nonce',
                'value': fpEsperienzeBookings.nonce
            }));
            
            // Add current filter parameters
            var filterForm = $('.fp-bookings-filters form');
            filterForm.find('input, select').each(function() {
                var input = $(this);
                if (input.attr('name') && input.val() && input.attr('name') !== 'page' && input.attr('name') !== 'tab') {
                    form.append($('<input>', {
                        'type': 'hidden',
                        'name': input.attr('name'),
                        'value': input.val()
                    }));
                }
            });
            
            $('body').append(form);
            form.submit();
            form.remove();
            
            // Restore button
            setTimeout(function() {
                button.text(originalText).prop('disabled', false);
            }, 1000);
        }
    };

})(jQuery);