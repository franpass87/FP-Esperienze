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

        init: function() {
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

            // Load Chart.js from CDN
            var script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            script.onload = this.initChart.bind(this);
            document.head.appendChild(script);
        },

        initChart: function() {
            var ctx = document.getElementById('fp-revenue-chart');
            if (!ctx) return;

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
                        intersect: false,
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
                                drawOnChartArea: false,
                            },
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
            this.loadTopExperiences();
            this.loadUtmConversions();
            this.loadLoadFactors();
        },

        updateReports: function(e) {
            e.preventDefault();
            this.showLoading();
            this.loadInitialData();
            this.loadChartData();
            this.hideLoading();
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
            
            $.post(ajaxurl, {
                action: 'fp_get_kpi_data',
                ...filters
            }, function(response) {
                if (response.success) {
                    var data = response.data;
                    $('#kpi-revenue').html('€' + this.formatNumber(data.total_revenue));
                    $('#kpi-seats').html(this.formatNumber(data.total_seats));
                    $('#kpi-bookings').html(this.formatNumber(data.total_bookings));
                    $('#kpi-avg-value').html('€' + this.formatNumber(data.average_booking_value));
                } else {
                    this.showError('Failed to load KPI data');
                }
            }.bind(this)).fail(function() {
                this.showError('Failed to load KPI data');
            }.bind(this));
        },

        loadChartData: function() {
            if (!this.chart) return;

            var filters = this.getFilters();
            
            $.post(ajaxurl, {
                action: 'fp_get_chart_data',
                period: this.currentPeriod,
                ...filters
            }, function(response) {
                if (response.success) {
                    var data = response.data;
                    this.chart.data.labels = data.labels;
                    this.chart.data.datasets[0].data = data.datasets[0].data;
                    this.chart.data.datasets[1].data = data.datasets[1].data;
                    this.chart.update();
                } else {
                    this.showError('Failed to load chart data');
                }
            }.bind(this)).fail(function() {
                this.showError('Failed to load chart data');
            }.bind(this));
        },

        loadTopExperiences: function() {
            var filters = this.getFilters();
            
            $.post(ajaxurl, {
                action: 'fp_get_kpi_data', // Will include top experiences in response
                ...filters
            }, function(response) {
                if (response.success && response.data.product_stats) {
                    this.renderTopExperiences(response.data.product_stats);
                }
            }.bind(this));
        },

        renderTopExperiences: function(productStats) {
            var html = '';
            var sortedProducts = Object.entries(productStats)
                .sort((a, b) => b[1].revenue - a[1].revenue)
                .slice(0, 10);

            sortedProducts.forEach(function([productId, stats], index) {
                html += '<div class="fp-top-experience-item">';
                html += '<span>' + (index + 1) + '. Product #' + productId + '</span>';
                html += '<span>€' + this.formatNumber(stats.revenue) + '</span>';
                html += '</div>';
            }.bind(this));

            if (html === '') {
                html = '<p>' + fp_reports_i18n.no_data + '</p>';
            }

            $('#fp-top-experiences-list').html(html);
        },

        loadUtmConversions: function() {
            var filters = this.getFilters();
            
            // This would call a separate AJAX endpoint for UTM data
            // For now, we'll simulate with a placeholder
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

        loadLoadFactors: function() {
            var filters = this.getFilters();
            
            // Load load factor data
            $.post(ajaxurl, {
                action: 'fp_get_kpi_data',
                ...filters
            }, function(response) {
                if (response.success && response.data.load_factors) {
                    this.renderLoadFactors(response.data.load_factors);
                }
            }.bind(this));
        },

        renderLoadFactors: function(loadFactors) {
            if (loadFactors.length === 0) {
                $('#fp-load-factors-table').html('<p>' + fp_reports_i18n.no_data + '</p>');
                return;
            }

            var html = '<table class="fp-load-factor-table">';
            html += '<thead><tr>';
            html += '<th>Experience</th><th>Date</th><th>Time</th><th>Capacity</th><th>Sold</th><th>Load Factor</th>';
            html += '</tr></thead><tbody>';

            loadFactors.forEach(function(factor) {
                var loadPercentage = Math.min(factor.load_factor, 100);
                var colorClass = loadPercentage > 80 ? 'high' : (loadPercentage > 60 ? 'medium' : 'low');
                
                html += '<tr>';
                html += '<td>Product #' + factor.product_id + '</td>';
                html += '<td>' + factor.date + '</td>';
                html += '<td>' + factor.time + '</td>';
                html += '<td>' + factor.capacity + '</td>';
                html += '<td>' + factor.seats_sold + '</td>';
                html += '<td>';
                html += '<div class="load-factor-bar">';
                html += '<div class="load-factor-fill" style="width: ' + loadPercentage + '%"></div>';
                html += '</div>';
                html += '<span>' + factor.load_factor + '%</span>';
                html += '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            $('#fp-load-factors-table').html(html);
        },

        prepareExport: function(e) {
            var filters = this.getFilters();
            $('#export-date-from').val(filters.date_from);
            $('#export-date-to').val(filters.date_to);
            $('#export-product-id').val(filters.product_id);
            $('#export-meeting-point-id').val(filters.meeting_point_id);
            $('#export-language').val(filters.language);
            
            // Form will submit normally
            return true;
        },

        showLoading: function() {
            $('#fp-reports-loading').show();
        },

        hideLoading: function() {
            $('#fp-reports-loading').hide();
        },

        showError: function(message) {
            console.error('FP Reports Error:', message);
            // You could show a proper notice here
        },

        formatNumber: function(num) {
            return new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2
            }).format(num);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('#fp-revenue-chart').length) {
            FPReports.init();
        }
    });

    // Make it globally available for debugging
    window.FPReports = FPReports;

})(jQuery);

// Localization object (to be populated by PHP)
var fp_reports_i18n = fp_reports_i18n || {
    no_data: 'No data available',
    loading: 'Loading...'
};