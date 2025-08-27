<?php
/**
 * Reports Manager
 *
 * @package FP\Esperienze\Admin
 */

namespace FP\Esperienze\Admin;

use FP\Esperienze\Core\CapabilityManager;

defined('ABSPATH') || exit;

/**
 * Reports Manager class for analytics and KPI calculations
 */
class ReportsManager {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_fp_get_kpi_data', [$this, 'ajaxGetKpiData']);
        add_action('wp_ajax_fp_get_chart_data', [$this, 'ajaxGetChartData']);
        add_action('wp_ajax_fp_export_report_data', [$this, 'ajaxExportReportData']);
    }

    /**
     * Get KPI data for dashboard
     *
     * @param array $filters Date range and other filters
     * @return array KPI metrics
     */
    public function getKpiData(array $filters = []): array {
        global $wpdb;

        // Default date range: last 30 days
        $date_from = $filters['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $date_to = $filters['date_to'] ?? date('Y-m-d');
        $product_id = $filters['product_id'] ?? 0;
        $meeting_point_id = $filters['meeting_point_id'] ?? 0;
        $language = $filters['language'] ?? '';

        // Build WHERE clauses
        $where_conditions = [
            "b.booking_date BETWEEN %s AND %s",
            "b.status IN ('confirmed', 'completed')"
        ];
        $where_params = [$date_from, $date_to];

        if ($product_id > 0) {
            $where_conditions[] = "b.product_id = %d";
            $where_params[] = $product_id;
        }

        if ($meeting_point_id > 0) {
            $where_conditions[] = "b.meeting_point_id = %d";
            $where_params[] = $meeting_point_id;
        }

        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

        // Get booking data with order totals
        $booking_table = $wpdb->prefix . 'fp_bookings';
        $postmeta_table = $wpdb->prefix . 'postmeta';

        $query = "
            SELECT 
                b.*,
                pm.meta_value as order_total
            FROM {$booking_table} b
            LEFT JOIN {$postmeta_table} pm ON b.order_id = pm.post_id AND pm.meta_key = '_order_total'
            {$where_clause}
        ";

        $bookings = $wpdb->get_results($wpdb->prepare($query, $where_params));

        // Calculate KPIs
        $total_revenue = 0;
        $total_seats = 0;
        $total_bookings = count($bookings);
        $product_stats = [];
        $meeting_point_stats = [];

        foreach ($bookings as $booking) {
            $seats = $booking->adults + $booking->children;
            $revenue = floatval($booking->order_total ?? 0);

            $total_revenue += $revenue;
            $total_seats += $seats;

            // Group by product
            if (!isset($product_stats[$booking->product_id])) {
                $product_stats[$booking->product_id] = [
                    'revenue' => 0,
                    'seats' => 0,
                    'bookings' => 0
                ];
            }
            $product_stats[$booking->product_id]['revenue'] += $revenue;
            $product_stats[$booking->product_id]['seats'] += $seats;
            $product_stats[$booking->product_id]['bookings']++;

            // Group by meeting point
            if ($booking->meeting_point_id) {
                if (!isset($meeting_point_stats[$booking->meeting_point_id])) {
                    $meeting_point_stats[$booking->meeting_point_id] = [
                        'revenue' => 0,
                        'seats' => 0,
                        'bookings' => 0
                    ];
                }
                $meeting_point_stats[$booking->meeting_point_id]['revenue'] += $revenue;
                $meeting_point_stats[$booking->meeting_point_id]['seats'] += $seats;
                $meeting_point_stats[$booking->meeting_point_id]['bookings']++;
            }
        }

        // Calculate load factors (requires capacity data)
        $load_factors = $this->calculateLoadFactors($bookings, $filters);

        return [
            'total_revenue' => $total_revenue,
            'total_seats' => $total_seats,
            'total_bookings' => $total_bookings,
            'average_booking_value' => $total_bookings > 0 ? ($total_revenue / $total_bookings) : 0,
            'average_seats_per_booking' => $total_bookings > 0 ? ($total_seats / $total_bookings) : 0,
            'product_stats' => $product_stats,
            'meeting_point_stats' => $meeting_point_stats,
            'load_factors' => $load_factors,
            'date_range' => [
                'from' => $date_from,
                'to' => $date_to
            ]
        ];
    }

    /**
     * Calculate load factors for experiences
     *
     * @param array $bookings Booking data
     * @param array $filters Applied filters
     * @return array Load factor data
     */
    private function calculateLoadFactors(array $bookings, array $filters): array {
        global $wpdb;

        $load_factors = [];
        $schedules_table = $wpdb->prefix . 'fp_schedules';

        // Group bookings by product and date/time
        $booking_groups = [];
        foreach ($bookings as $booking) {
            $key = $booking->product_id . '_' . $booking->booking_date . '_' . $booking->booking_time;
            if (!isset($booking_groups[$key])) {
                $booking_groups[$key] = [
                    'product_id' => $booking->product_id,
                    'date' => $booking->booking_date,
                    'time' => $booking->booking_time,
                    'seats_sold' => 0
                ];
            }
            $booking_groups[$key]['seats_sold'] += ($booking->adults + $booking->children);
        }

        // Get capacity information from product meta and calculate load factor
        foreach ($booking_groups as $group) {
            $capacity = get_post_meta($group['product_id'], '_experience_capacity', true) ?: 10; // Default capacity
            $load_factor = $capacity > 0 ? ($group['seats_sold'] / $capacity) * 100 : 0;

            $load_factors[] = [
                'product_id' => $group['product_id'],
                'date' => $group['date'],
                'time' => $group['time'],
                'capacity' => $capacity,
                'seats_sold' => $group['seats_sold'],
                'load_factor' => round($load_factor, 2)
            ];
        }

        return $load_factors;
    }

    /**
     * Get chart data for various time periods
     *
     * @param string $period 'day', 'week', or 'month'
     * @param array $filters Additional filters
     * @return array Chart data
     */
    public function getChartData(string $period, array $filters = []): array {
        global $wpdb;

        $date_from = $filters['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $date_to = $filters['date_to'] ?? date('Y-m-d');

        // Determine date grouping based on period
        switch ($period) {
            case 'day':
                $date_format = '%Y-%m-%d';
                $period_label = 'Daily';
                break;
            case 'week':
                $date_format = '%Y-%u';
                $period_label = 'Weekly';
                break;
            case 'month':
                $date_format = '%Y-%m';
                $period_label = 'Monthly';
                break;
            default:
                $date_format = '%Y-%m-%d';
                $period_label = 'Daily';
        }

        $booking_table = $wpdb->prefix . 'fp_bookings';
        $postmeta_table = $wpdb->prefix . 'postmeta';

        $query = "
            SELECT 
                DATE_FORMAT(b.booking_date, '{$date_format}') as period,
                COUNT(*) as bookings,
                SUM(b.adults + b.children) as total_seats,
                SUM(CAST(pm.meta_value AS DECIMAL(10,2))) as total_revenue
            FROM {$booking_table} b
            LEFT JOIN {$postmeta_table} pm ON b.order_id = pm.post_id AND pm.meta_key = '_order_total'
            WHERE b.booking_date BETWEEN %s AND %s
            AND b.status IN ('confirmed', 'completed')
            GROUP BY period
            ORDER BY period ASC
        ";

        $results = $wpdb->get_results($wpdb->prepare($query, $date_from, $date_to));

        $chart_data = [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => [],
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                    'borderWidth' => 1,
                    'yAxisID' => 'y'
                ],
                [
                    'label' => 'Seats Sold',
                    'data' => [],
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'borderColor' => 'rgba(255, 99, 132, 1)',
                    'borderWidth' => 1,
                    'yAxisID' => 'y1'
                ]
            ],
            'period' => $period_label
        ];

        foreach ($results as $result) {
            $chart_data['labels'][] = $result->period;
            $chart_data['datasets'][0]['data'][] = floatval($result->total_revenue);
            $chart_data['datasets'][1]['data'][] = intval($result->total_seats);
        }

        return $chart_data;
    }

    /**
     * Get top performing experiences
     *
     * @param int $limit Number of top experiences to return
     * @param array $filters Date range and other filters
     * @return array Top experiences data
     */
    public function getTopExperiences(int $limit = 10, array $filters = []): array {
        global $wpdb;

        $date_from = $filters['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $date_to = $filters['date_to'] ?? date('Y-m-d');

        $booking_table = $wpdb->prefix . 'fp_bookings';
        $postmeta_table = $wpdb->prefix . 'postmeta';
        $posts_table = $wpdb->prefix . 'posts';

        $query = "
            SELECT 
                b.product_id,
                p.post_title as product_name,
                COUNT(*) as total_bookings,
                SUM(b.adults + b.children) as total_seats,
                SUM(CAST(pm.meta_value AS DECIMAL(10,2))) as total_revenue
            FROM {$booking_table} b
            LEFT JOIN {$postmeta_table} pm ON b.order_id = pm.post_id AND pm.meta_key = '_order_total'
            LEFT JOIN {$posts_table} p ON b.product_id = p.ID
            WHERE b.booking_date BETWEEN %s AND %s
            AND b.status IN ('confirmed', 'completed')
            GROUP BY b.product_id
            ORDER BY total_revenue DESC
            LIMIT %d
        ";

        $results = $wpdb->get_results($wpdb->prepare($query, $date_from, $date_to, $limit));

        return $results;
    }

    /**
     * Get UTM conversion data from order meta
     *
     * @param array $filters Date range filters
     * @return array UTM conversion statistics
     */
    public function getUtmConversions(array $filters = []): array {
        global $wpdb;

        $date_from = $filters['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $date_to = $filters['date_to'] ?? date('Y-m-d');

        $booking_table = $wpdb->prefix . 'fp_bookings';
        $postmeta_table = $wpdb->prefix . 'postmeta';

        // Get UTM source data from order meta
        $query = "
            SELECT 
                pm.meta_value as utm_source,
                COUNT(DISTINCT b.order_id) as orders,
                SUM(CAST(pm2.meta_value AS DECIMAL(10,2))) as total_revenue
            FROM {$booking_table} b
            LEFT JOIN {$postmeta_table} pm ON b.order_id = pm.post_id AND pm.meta_key = '_utm_source'
            LEFT JOIN {$postmeta_table} pm2 ON b.order_id = pm2.post_id AND pm2.meta_key = '_order_total'
            WHERE b.booking_date BETWEEN %s AND %s
            AND b.status IN ('confirmed', 'completed')
            AND pm.meta_value IS NOT NULL
            AND pm.meta_value != ''
            GROUP BY pm.meta_value
            ORDER BY total_revenue DESC
        ";

        $utm_data = $wpdb->get_results($wpdb->prepare($query, $date_from, $date_to));

        // Also get data without UTM (direct traffic)
        $direct_query = "
            SELECT 
                COUNT(DISTINCT b.order_id) as orders,
                SUM(CAST(pm.meta_value AS DECIMAL(10,2))) as total_revenue
            FROM {$booking_table} b
            LEFT JOIN {$postmeta_table} pm ON b.order_id = pm.post_id AND pm.meta_key = '_order_total'
            LEFT JOIN {$postmeta_table} pm2 ON b.order_id = pm2.post_id AND pm2.meta_key = '_utm_source'
            WHERE b.booking_date BETWEEN %s AND %s
            AND b.status IN ('confirmed', 'completed')
            AND (pm2.meta_value IS NULL OR pm2.meta_value = '')
        ";

        $direct_data = $wpdb->get_row($wpdb->prepare($direct_query, $date_from, $date_to));

        $conversions = [];
        foreach ($utm_data as $data) {
            $conversions[] = [
                'source' => $data->utm_source,
                'orders' => intval($data->orders),
                'revenue' => floatval($data->total_revenue),
                'avg_order_value' => $data->orders > 0 ? ($data->total_revenue / $data->orders) : 0
            ];
        }

        // Add direct traffic data
        if ($direct_data && $direct_data->orders > 0) {
            $conversions[] = [
                'source' => 'Direct',
                'orders' => intval($direct_data->orders),
                'revenue' => floatval($direct_data->total_revenue),
                'avg_order_value' => $direct_data->orders > 0 ? ($direct_data->total_revenue / $direct_data->orders) : 0
            ];
        }

        return $conversions;
    }

    /**
     * Export report data in specified format
     *
     * @param string $format 'csv' or 'json'
     * @param array $filters Applied filters
     * @return void
     */
    public function exportReportData(string $format, array $filters = []): void {
        if (!CapabilityManager::canManageFPEsperienze()) {
            wp_die(__('Insufficient permissions.', 'fp-esperienze'));
        }

        $kpi_data = $this->getKpiData($filters);
        $top_experiences = $this->getTopExperiences(50, $filters); // Export more for detailed analysis
        $utm_conversions = $this->getUtmConversions($filters);

        $export_data = [
            'generated_at' => current_time('mysql'),
            'date_range' => $kpi_data['date_range'],
            'kpi_summary' => [
                'total_revenue' => $kpi_data['total_revenue'],
                'total_seats' => $kpi_data['total_seats'],
                'total_bookings' => $kpi_data['total_bookings'],
                'average_booking_value' => $kpi_data['average_booking_value']
            ],
            'top_experiences' => $top_experiences,
            'utm_conversions' => $utm_conversions,
            'load_factors' => $kpi_data['load_factors']
        ];

        $filename = 'fp-esperienze-report-' . date('Y-m-d-H-i-s');

        if ($format === 'json') {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '.json"');
            echo wp_json_encode($export_data, JSON_PRETTY_PRINT);
        } else {
            // CSV format
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            // Summary section
            fputcsv($output, ['FP Esperienze Report Summary']);
            fputcsv($output, ['Generated', $export_data['generated_at']]);
            fputcsv($output, ['Date Range', $export_data['date_range']['from'] . ' to ' . $export_data['date_range']['to']]);
            fputcsv($output, ['Total Revenue', $export_data['kpi_summary']['total_revenue']]);
            fputcsv($output, ['Total Seats', $export_data['kpi_summary']['total_seats']]);
            fputcsv($output, ['Total Bookings', $export_data['kpi_summary']['total_bookings']]);
            fputcsv($output, []);
            
            // Top experiences
            fputcsv($output, ['Top Experiences']);
            fputcsv($output, ['Product ID', 'Product Name', 'Bookings', 'Seats Sold', 'Revenue']);
            foreach ($export_data['top_experiences'] as $exp) {
                fputcsv($output, [
                    $exp->product_id,
                    $exp->product_name,
                    $exp->total_bookings,
                    $exp->total_seats,
                    $exp->total_revenue
                ]);
            }
            fputcsv($output, []);
            
            // UTM conversions
            fputcsv($output, ['UTM Source Conversions']);
            fputcsv($output, ['Source', 'Orders', 'Revenue', 'Avg Order Value']);
            foreach ($export_data['utm_conversions'] as $utm) {
                fputcsv($output, [
                    $utm['source'],
                    $utm['orders'],
                    $utm['revenue'],
                    $utm['avg_order_value']
                ]);
            }
            
            fclose($output);
        }
        exit;
    }

    /**
     * AJAX handler for KPI data
     */
    public function ajaxGetKpiData(): void {
        check_ajax_referer('fp_reports_nonce', 'nonce');
        
        if (!CapabilityManager::canManageFPEsperienze()) {
            wp_die(__('Insufficient permissions.', 'fp-esperienze'));
        }

        $filters = [
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? ''),
            'product_id' => absint($_POST['product_id'] ?? 0),
            'meeting_point_id' => absint($_POST['meeting_point_id'] ?? 0),
            'language' => sanitize_text_field($_POST['language'] ?? '')
        ];

        $kpi_data = $this->getKpiData(array_filter($filters));
        wp_send_json_success($kpi_data);
    }

    /**
     * AJAX handler for chart data
     */
    public function ajaxGetChartData(): void {
        check_ajax_referer('fp_reports_nonce', 'nonce');
        
        if (!CapabilityManager::canManageFPEsperienze()) {
            wp_die(__('Insufficient permissions.', 'fp-esperienze'));
        }

        $period = sanitize_text_field($_POST['period'] ?? 'day');
        $filters = [
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? ''),
        ];

        $chart_data = $this->getChartData($period, array_filter($filters));
        wp_send_json_success($chart_data);
    }

    /**
     * AJAX handler for export
     */
    public function ajaxExportReportData(): void {
        check_ajax_referer('fp_reports_nonce', 'nonce');
        
        if (!CapabilityManager::canManageFPEsperienze()) {
            wp_die(__('Insufficient permissions.', 'fp-esperienze'));
        }
        
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $filters = [
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? ''),
            'product_id' => absint($_POST['product_id'] ?? 0),
            'meeting_point_id' => absint($_POST['meeting_point_id'] ?? 0),
            'language' => sanitize_text_field($_POST['language'] ?? '')
        ];

        $this->exportReportData($format, array_filter($filters));
    }
}