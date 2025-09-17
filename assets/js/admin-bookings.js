/**
 * FP Esperienze Admin Bookings Calendar
 */

if (typeof jQuery === 'undefined') {
    console.error('FP Esperienze: jQuery is required for the admin bookings script.');
    return;
}

(function($) {
    'use strict';

    var adminData = window.fpEsperienzeAdmin || window.fp_esperienze_admin || {};

    window.FPEsperienzeAdmin = window.FPEsperienzeAdmin || {};

    FPEsperienzeAdmin.initBookingsCalendar = function() {
        if (!$('#fp-calendar').length) {
            console.warn('FP Esperienze: Calendar container not found');
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

        var restNamespace = normalizeNamespace(adminData.rest_namespace) || 'fp-exp/v1/';
        var experienceRestBase = '';

        if (adminData.experience_rest_url) {
            experienceRestBase = ensureTrailingSlash(adminData.experience_rest_url);
        } else if (adminData.rest_url) {
            experienceRestBase = ensureTrailingSlash(adminData.rest_url) + restNamespace;
        }

        if (!experienceRestBase) {
            console.error('FP Esperienze: REST base URL missing; cannot load calendar events.');
            return;
        }

        // Initialize FullCalendar
        $('#fp-calendar').fullCalendar({
            header: {
                left: 'prev,next today',
                center: 'title',
                right: 'month,agendaWeek,agendaDay'
            },
            defaultView: 'month',
            navLinks: true,
            editable: false,
            selectable: false,
            selectHelper: true,
            height: 'auto',
            loading: function(isLoading) {
                if (isLoading) {
                    $('#fp-calendar-loading').show();
                } else {
                    $('#fp-calendar-loading').hide();
                }
            },
            events: function(start, end, timezone, callback) {
                // Use our new REST endpoint
                $.ajax({
                    url: experienceRestBase + 'bookings/calendar',
                    type: 'GET',
                    dataType: 'json',
                    data: {
                        start: start.format('YYYY-MM-DD'),
                        end: end.format('YYYY-MM-DD')
                    },
                    beforeSend: function(xhr) {
                        if (adminData.nonce) {
                            xhr.setRequestHeader('X-WP-Nonce', adminData.nonce);
                        }
                    },
                    success: function(response) {
                        if (response && response.events) {
                            callback(response.events);
                        } else {
                            console.warn('FP Esperienze: Invalid events response', response);
                            callback([]);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('FP Esperienze: Error fetching events', {
                            status: status,
                            error: error,
                            response: xhr.responseText
                        });

                        // Show user-friendly error message
                        if (typeof adminData.i18n !== 'undefined') {
                            alert(adminData.i18n.errorFetchingEvents);
                        } else {
                            alert('There was an error while fetching events. Please try again.');
                        }

                        callback([]);
                    }
                });
            },
            eventClick: function(calEvent, jsEvent, view) {
                // Handle booking event click
                var booking = calEvent.extendedProps;
                if (booking && booking.booking_id) {
                    // Show booking details modal or navigate to booking details
                    var bookingUrl = 'admin.php?page=fp-esperienze-bookings&booking_id=' + booking.booking_id;
                    window.location.href = bookingUrl;
                }
            },
            eventRender: function(event, element) {
                // Add custom tooltip or styling
                var booking = event.extendedProps;
                if (booking) {
                    var tooltip = booking.customer_name + ' (' + booking.participants + ' participants)';
                    element.attr('title', tooltip);
                }
            }
        });

        // Add loading indicator
        if (!$('#fp-calendar-loading').length) {
            $('#fp-calendar').before(
                '<div id="fp-calendar-loading" style="display:none; text-align:center; padding:20px;">' +
                '<div class="spinner is-active"></div>' +
                '<p>' + (adminData.i18n ? adminData.i18n.loadingEvents : 'Loading events...') + '</p>' +
                '</div>'
            );
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Auto-initialize if calendar view is active
        if ($('#fp-calendar').is(':visible')) {
            FPEsperienzeAdmin.initBookingsCalendar();
        }
    });

})(jQuery);
