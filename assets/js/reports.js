/**
 * Reports page JavaScript functionality
 *
 * @package FP\Esperienze\Assets\JS
 */

if (typeof jQuery === 'undefined') {
    console.error('FP Esperienze: jQuery is required for the reports script.');
    return;
}

(function($) {
    'use strict';

    var FPReports = {
        chart: null,
        currentPeriod: 'day',
        pendingRequests: 0,
        loadingFallbackTimer: null,

        init: function() {
            this.hideLoading();
            this.bindEvents();
            this.loadChartJS();
            this.loadInitialData();
        },

        bindEvents: function() {
            $('#fp-update-reports').on('click', this.updateReports.bind(this));
            $('.chart-period').on('click', this.changePeriod.bind(this));
            $('#fp-export-form').on('submit', this.prepareExport.bind(this));
        },

        loadChartJS: function() {
            if (typeof Chart !== 'undefined') {
                this.initChart();
                return;
            }

            var script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            script.onload = this.initChart.bind(this);
            document.head.appendChild(script);
        },

        initChart: function() {
            var ctx = document.getElementById('fp-revenue-chart');
            if (!ctx) {
                return;
            }

            this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Revenue',
                        data: [],
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1,
                        yAxisID: 'y'
                    }, {
                        label: 'Seats Sold',
                        data: [],
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        tension: 0.1,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Period'
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Revenue (€)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Seats Sold'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Revenue & Seats Trends'
                        }
                    }
                }
            });

            this.loadChartData();
        },

        loadInitialData: function() {
            this.loadKpiData();
            this.loadUtmConversions();
        },

        updateReports: function(e) {
            e.preventDefault();
            this.showLoading();
            this.loadInitialData();
            this.loadChartData();
        },

        changePeriod: function(e) {
            e.preventDefault();

            $('.chart-period').removeClass('button-primary');
            $(e.target).addClass('button-primary');

            this.currentPeriod = $(e.target).data('period');
            this.loadChartData();
        },

        getFilters: function() {
            return {
                date_from: $('#fp-date-from').val(),
                date_to: $('#fp-date-to').val(),
                product_id: $('#fp-product-filter').val(),
                meeting_point_id: $('#fp-meeting-point-filter').val(),
                language: $('#fp-language-filter').val()
            };
        },

        loadKpiData: function() {
            var filters = this.getFilters();
            this.startRequest();

            $.post(ajaxurl, {
                action: 'fp_get_kpi_data',
                ...filters
            }).done(function(response) {
                try {
                    if (response && response.success) {
                        var data = response.data || {};
                        var stats = data.product_stats || {};
                        var loadFactors = Array.isArray(data.load_factors) ? data.load_factors : [];

                        $('#kpi-revenue').html('€' + this.formatNumber(data.total_revenue || 0));
                        $('#kpi-seats').html(this.formatNumber(data.total_seats || 0));
                        $('#kpi-bookings').html(this.formatNumber(data.total_bookings || 0));
                        $('#kpi-avg-value').html('€' + this.formatNumber(data.average_booking_value || 0));

                        this.renderTopExperiences(stats);
                        this.renderLoadFactors(loadFactors);
                    } else {
                        this.renderTopExperiences({});
                        this.renderLoadFactors([]);
                        this.showError('Failed to load KPI data');
                    }
                } catch (error) {
                    console.error('FP Reports: error rendering KPI response', error);
                    this.renderTopExperiences({});
                    this.renderLoadFactors([]);
                }
            }.bind(this)).fail(function() {
                this.renderTopExperiences({});
                this.renderLoadFactors([]);
                this.showError('Failed to load KPI data');
            }.bind(this)).always(this.finishRequest.bind(this));
        },

        loadChartData: function() {
            if (!this.chart) {
                return;
            }

            var filters = this.getFilters();
            this.startRequest();

            $.post(ajaxurl, {
                action: 'fp_get_chart_data',
                period: this.currentPeriod,
                ...filters
            }).done(function(response) {
                try {
                    if (response && response.success && response.data) {
                        var data = response.data;
                        this.chart.data.labels = Array.isArray(data.labels) ? data.labels : [];

                        if (Array.isArray(data.datasets) && data.datasets.length >= 2) {
                            this.chart.data.datasets[0].data = Array.isArray(data.datasets[0].data) ? data.datasets[0].data : [];
                            this.chart.data.datasets[1].data = Array.isArray(data.datasets[1].data) ? data.datasets[1].data : [];
                        } else {
                            this.chart.data.datasets[0].data = [];
                            this.chart.data.datasets[1].data = [];
                        }

                        this.chart.update();
                    } else {
                        this.showError('Failed to load chart data');
                    }
                } catch (error) {
                    console.error('FP Reports: error rendering chart response', error);
                }
            }.bind(this)).fail(function() {
                this.showError('Failed to load chart data');
            }.bind(this)).always(this.finishRequest.bind(this));
        },

        renderTopExperiences: function(productStats) {
            var html = '';
            var entries = [];

            try {
                entries = Object.entries(productStats || {});
            } catch (error) {
                entries = [];
            }

            entries
                .map(function(entry) {
                    var stats = Object.assign({ revenue: 0 }, entry[1] || {});
                    stats.revenue = parseFloat(stats.revenue) || 0;
                    return [entry[0], stats];
                })
                .sort(function(a, b) { return b[1].revenue - a[1].revenue; })
                .slice(0, 10)
                .forEach(function(item, index) {
                    html += '<div class="fp-top-experience-item">';
                    html += '<span>' + (index + 1) + '. Product #' + item[0] + '</span>';
                    html += '<span>€' + this.formatNumber(item[1].revenue) + '</span>';
                    html += '</div>';
                }.bind(this));

            if (html === '') {
                html = '<p>' + fp_reports_i18n.no_data + '</p>';
            }

            $('#fp-top-experiences-list').html(html);
        },

        loadUtmConversions: function() {
            var html = '<div class="fp-utm-conversion-item">';
            html += '<span>Source</span><span>Orders</span><span>Revenue</span><span>Avg Value</span>';
            html += '</div>';
            html += '<div class="fp-utm-conversion-item">';
            html += '<span>Direct</span><span>45</span><span>€2,250</span><span>€50.00</span>';
            html += '</div>';
            html += '<div class="fp-utm-conversion-item">';
            html += '<span>Google</span><span>32</span><span>€1,600</span><span>€50.00</span>';
            html += '</div>';
            html += '<div class="fp-utm-conversion-item">';
            html += '<span>Facebook</span><span>18</span><span>€900</span><span>€50.00</span>';
            html += '</div>';

            $('#fp-utm-conversions').html(html);
        },

        renderLoadFactors: function(loadFactors) {
            if (!Array.isArray(loadFactors) || loadFactors.length === 0) {
                $('#fp-load-factors-table').html('<p>' + fp_reports_i18n.no_data + '</p>');
                return;
            }

            var html = '<table class="fp-load-factor-table">';
            html += '<thead><tr>';
            html += '<th>Experience</th><th>Date</th><th>Time</th><th>Capacity</th><th>Sold</th><th>Load Factor</th>';
            html += '</tr></thead><tbody>';

            loadFactors.forEach(function(factor) {
                var load = parseFloat(factor.load_factor) || 0;
                var loadPercentage = Math.max(0, Math.min(load, 100));

                html += '<tr>';
                html += '<td>Product #' + (factor.product_id || '-') + '</td>';
                html += '<td>' + (factor.date || '-') + '</td>';
                html += '<td>' + (factor.time || '-') + '</td>';
                html += '<td>' + (factor.capacity || 0) + '</td>';
                html += '<td>' + (typeof factor.seats_sold === 'undefined' ? '' : factor.seats_sold) + '</td>';
                html += '<td>';
                html += '<div class="load-factor-bar">';
                html += '<div class="load-factor-fill" style="width: ' + loadPercentage + '%"></div>';
                html += '</div>';
                html += '<span>' + loadPercentage.toFixed(0) + '%</span>';
                html += '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            $('#fp-load-factors-table').html(html);
        },

        prepareExport: function() {
            var filters = this.getFilters();
            $('#export-date-from').val(filters.date_from);
            $('#export-date-to').val(filters.date_to);
            $('#export-product-id').val(filters.product_id);
            $('#export-meeting-point-id').val(filters.meeting_point_id);
            $('#export-language').val(filters.language);

            return true;
        },

        showLoading: function() {
            $('#fp-reports-loading').show();
        },

        hideLoading: function() {
            if (this.loadingFallbackTimer) {
                clearTimeout(this.loadingFallbackTimer);
                this.loadingFallbackTimer = null;
            }

            $('#fp-reports-loading').hide();
        },

        startRequest: function() {
            this.pendingRequests += 1;
            this.showLoading();
            this.scheduleLoadingFallback();
        },

        finishRequest: function() {
            this.pendingRequests = Math.max(0, this.pendingRequests - 1);
            if (this.pendingRequests === 0) {
                this.hideLoading();
            }
        },

        scheduleLoadingFallback: function() {
            if (this.loadingFallbackTimer) {
                clearTimeout(this.loadingFallbackTimer);
            }

            this.loadingFallbackTimer = setTimeout(function() {
                this.pendingRequests = 0;
                this.hideLoading();
            }.bind(this), 10000);
        },

        showError: function(message) {
            console.error('FP Reports Error:', message);
        },

        formatNumber: function(num) {
            return new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2
            }).format(num);
        }
    };

    $(document).ready(function() {
        if ($('#fp-revenue-chart').length) {
            FPReports.init();
        }
    });

    window.FPReports = FPReports;

})(jQuery);

var fp_reports_i18n = fp_reports_i18n || {
    no_data: 'No data available',
    loading: 'Loading...'
};
