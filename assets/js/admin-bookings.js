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

        var restNamespace = normalizeNamespace(adminData.rest_namespace);
        var experienceRestBase = '';

        if (adminData.rest_url) {
            experienceRestBase = ensureTrailingSlash(adminData.rest_url);
            if (restNamespace && experienceRestBase.indexOf(restNamespace) === -1) {
                experienceRestBase += restNamespace;
            }
        } else if (adminData.experience_rest_url) {
            experienceRestBase = ensureTrailingSlash(adminData.experience_rest_url);
        }

        if (!experienceRestBase) {
            console.error('FP Esperienze: REST base URL missing; cannot load calendar events.');
            return;
        }

        var eventsEndpoint = 'events';
        if (experienceRestBase.indexOf('fp-exp/v1') !== -1) {
            eventsEndpoint = 'bookings/calendar';
        }

        var perPage = parseInt(adminData.calendar_page_size, 10);
        if (isNaN(perPage) || perPage <= 0) {
            perPage = 50;
        }
        perPage = Math.min(Math.max(perPage, 1), 200);

        var maxPages = parseInt(adminData.calendar_max_pages, 10);
        if (isNaN(maxPages) || maxPages <= 0) {
            maxPages = 20;
        }

        var fetchPaginatedEvents = function(startDate, endDate, callback, errorCallback) {
            var aggregatedEvents = [];
            var currentPage = 1;

            var loadPage = function(pageToLoad) {
                if (pageToLoad > maxPages) {
                    callback(aggregatedEvents);
                    return;
                }

                var requestData = {
                    start: startDate,
                    end: endDate,
                    page: pageToLoad,
                    per_page: perPage
                };

                $.ajax({
                    url: experienceRestBase + eventsEndpoint,
                    type: 'GET',
                    dataType: 'json',
                    data: requestData,
                    beforeSend: function(xhr) {
                        if (adminData.nonce) {
                            xhr.setRequestHeader('X-WP-Nonce', adminData.nonce);
                        }
                    },
                    success: function(response) {
                        if (response && response.events && Array.isArray(response.events)) {
                            aggregatedEvents = aggregatedEvents.concat(response.events);
                        }

                        var meta = response && response.meta ? response.meta : null;
                        var hasMore = false;
                        var totalPages = 1;

                        if (meta) {
                            totalPages = parseInt(meta.total_pages, 10);
                            if (isNaN(totalPages) || totalPages < 1) {
                                totalPages = 1;
                            }
                            hasMore = !!meta.has_more && pageToLoad < totalPages;
                        } else if (typeof response.total === 'number') {
                            totalPages = Math.max(1, Math.ceil(response.total / perPage));
                            hasMore = pageToLoad < totalPages;
                        } else if (response && response.events && Array.isArray(response.events)) {
                            hasMore = response.events.length === perPage;
                        }

                        if (hasMore) {
                            loadPage(pageToLoad + 1);
                        } else {
                            callback(aggregatedEvents);
                        }
                    },
                    error: function(xhr, status, error) {
                        errorCallback(xhr, status, error);
                        callback(aggregatedEvents);
                    }
                });
            };

            loadPage(currentPage);
        };

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
                var startDate = start.format('YYYY-MM-DD');
                var endDate = end.format('YYYY-MM-DD');

                fetchPaginatedEvents(startDate, endDate, function(events) {
                    callback(events);
                }, function(xhr, status, error) {
                    console.error('FP Esperienze: Error fetching events', {
                        status: status,
                        error: error,
                        response: xhr ? xhr.responseText : null
                    });

                    if (typeof adminData.i18n !== 'undefined') {
                        alert(adminData.i18n.errorFetchingEvents);
                    } else {
                        alert('There was an error while fetching events. Please try again.');
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
