<?php
/**
 * Advanced Analytics Dashboard
 *
 * Enterprise-level analytics with conversion funnels, attribution reporting,
 * ROI calculations, and advanced data export capabilities.
 *
 * @package FP\Esperienze\Admin
 */

namespace FP\Esperienze\Admin;

use FP\Esperienze\Core\CapabilityManager;

defined('ABSPATH') || exit;

/**
 * Advanced Analytics Manager for enterprise reporting
 */
class AdvancedAnalytics {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_fp_get_conversion_funnel', [$this, 'ajaxGetConversionFunnel']);
        add_action('wp_ajax_fp_get_attribution_report', [$this, 'ajaxGetAttributionReport']);
        add_action('wp_ajax_fp_get_roi_analysis', [$this, 'ajaxGetRoiAnalysis']);
        add_action('wp_ajax_fp_export_analytics_data', [$this, 'ajaxExportAnalyticsData']);
        add_action('wp_ajax_fp_get_revenue_by_channel', [$this, 'ajaxGetRevenueByChannel']);
        add_action('wp_ajax_fp_get_customer_journey', [$this, 'ajaxGetCustomerJourney']);
    }

    /**
     * Get conversion funnel data
     */
    public function ajaxGetConversionFunnel(): void {
        if (!CapabilityManager::currentUserCan('view_reports')) {
            wp_die('Unauthorized', 403);
        }

        check_ajax_referer('fp_esperienze_admin', 'nonce');

        $date_from = sanitize_text_field($_POST['date_from'] ?? date('Y-m-d', strtotime('-30 days')));
        $date_to = sanitize_text_field($_POST['date_to'] ?? date('Y-m-d'));

        $funnel_data = $this->getConversionFunnelData($date_from, $date_to);

        wp_send_json_success($funnel_data);
    }

    /**
     * Get attribution report data
     */
    public function ajaxGetAttributionReport(): void {
        if (!CapabilityManager::currentUserCan('view_reports')) {
            wp_die('Unauthorized', 403);
        }

        check_ajax_referer('fp_esperienze_admin', 'nonce');

        $date_from = sanitize_text_field($_POST['date_from'] ?? date('Y-m-d', strtotime('-30 days')));
        $date_to = sanitize_text_field($_POST['date_to'] ?? date('Y-m-d'));

        $attribution_data = $this->getAttributionReportData($date_from, $date_to);

        wp_send_json_success($attribution_data);
    }

    /**
     * Get ROI analysis data
     */
    public function ajaxGetRoiAnalysis(): void {
        if (!CapabilityManager::currentUserCan('view_reports')) {
            wp_die('Unauthorized', 403);
        }

        check_ajax_referer('fp_esperienze_admin', 'nonce');

        $date_from = sanitize_text_field($_POST['date_from'] ?? date('Y-m-d', strtotime('-30 days')));
        $date_to = sanitize_text_field($_POST['date_to'] ?? date('Y-m-d'));

        $roi_data = $this->getRoiAnalysisData($date_from, $date_to);

        wp_send_json_success($roi_data);
    }

    /**
     * Export analytics data
     */
    public function ajaxExportAnalyticsData(): void {
        if (!CapabilityManager::currentUserCan('export_data')) {
            wp_die('Unauthorized', 403);
        }

        check_ajax_referer('fp_esperienze_admin', 'nonce');

        $export_type = sanitize_text_field($_POST['export_type'] ?? 'funnel');
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $date_from = sanitize_text_field($_POST['date_from'] ?? date('Y-m-d', strtotime('-30 days')));
        $date_to = sanitize_text_field($_POST['date_to'] ?? date('Y-m-d'));

        $this->exportData($export_type, $format, $date_from, $date_to);
    }

    /**
     * Get revenue by channel data
     */
    public function ajaxGetRevenueByChannel(): void {
        if (!CapabilityManager::currentUserCan('view_reports')) {
            wp_die('Unauthorized', 403);
        }

        check_ajax_referer('fp_esperienze_admin', 'nonce');

        $date_from = sanitize_text_field($_POST['date_from'] ?? date('Y-m-d', strtotime('-30 days')));
        $date_to = sanitize_text_field($_POST['date_to'] ?? date('Y-m-d'));

        $channel_data = $this->getRevenueByChannelData($date_from, $date_to);

        wp_send_json_success($channel_data);
    }

    /**
     * Get customer journey analysis
     */
    public function ajaxGetCustomerJourney(): void {
        if (!CapabilityManager::currentUserCan('view_reports')) {
            wp_die('Unauthorized', 403);
        }

        check_ajax_referer('fp_esperienze_admin', 'nonce');

        $customer_id = intval($_POST['customer_id'] ?? 0);
        $order_id = intval($_POST['order_id'] ?? 0);

        $journey_data = $this->getCustomerJourneyData($customer_id, $order_id);

        wp_send_json_success($journey_data);
    }

    /**
     * Calculate conversion funnel metrics
     *
     * @param string $date_from Start date
     * @param string $date_to End date
     * @return array Funnel data
     */
    private function getConversionFunnelData(string $date_from, string $date_to): array {
        global $wpdb;

        // Get website visits (from GA4 or approximate from page views)
        $visits = $this->getWebsiteVisits($date_from, $date_to);

        // Get product page views for experiences
        $product_views = $this->getProductViews($date_from, $date_to);

        // Get cart additions
        $cart_additions = $this->getCartAdditions($date_from, $date_to);

        // Get checkout initiations
        $checkout_starts = $this->getCheckoutStarts($date_from, $date_to);

        // Get completed purchases
        $purchases = $this->getCompletedPurchases($date_from, $date_to);

        return [
            'funnel_steps' => [
                [
                    'step' => 'Website Visits',
                    'count' => $visits,
                    'conversion_rate' => 100.0,
                    'drop_off' => 0
                ],
                [
                    'step' => 'Product Views',
                    'count' => $product_views,
                    'conversion_rate' => $visits > 0 ? round(($product_views / $visits) * 100, 2) : 0,
                    'drop_off' => $visits - $product_views
                ],
                [
                    'step' => 'Add to Cart',
                    'count' => $cart_additions,
                    'conversion_rate' => $product_views > 0 ? round(($cart_additions / $product_views) * 100, 2) : 0,
                    'drop_off' => $product_views - $cart_additions
                ],
                [
                    'step' => 'Checkout Start',
                    'count' => $checkout_starts,
                    'conversion_rate' => $cart_additions > 0 ? round(($checkout_starts / $cart_additions) * 100, 2) : 0,
                    'drop_off' => $cart_additions - $checkout_starts
                ],
                [
                    'step' => 'Purchase Complete',
                    'count' => $purchases,
                    'conversion_rate' => $checkout_starts > 0 ? round(($purchases / $checkout_starts) * 100, 2) : 0,
                    'drop_off' => $checkout_starts - $purchases
                ]
            ],
            'overall_conversion_rate' => $visits > 0 ? round(($purchases / $visits) * 100, 2) : 0,
            'total_revenue' => $this->getTotalRevenue($date_from, $date_to),
            'average_order_value' => $purchases > 0 ? round($this->getTotalRevenue($date_from, $date_to) / $purchases, 2) : 0
        ];
    }

    /**
     * Get attribution report data
     *
     * @param string $date_from Start date
     * @param string $date_to End date
     * @return array Attribution data
     */
    private function getAttributionReportData(string $date_from, string $date_to): array {
        global $wpdb;

        $table_orders = $wpdb->prefix . 'wc_orders';

        // Get attribution data from order metadata
        $attribution_query = $wpdb->prepare("
            SELECT 
                om.meta_value as attribution_data,
                COUNT(o.id) as order_count,
                SUM(o.total_amount) as revenue
            FROM {$table_orders} o
            INNER JOIN {$wpdb->prefix}wc_orders_meta om ON o.id = om.order_id
            WHERE om.meta_key = '_fp_attribution_data'
            AND o.date_created_gmt BETWEEN %s AND %s
            AND o.status IN ('wc-processing', 'wc-completed')
            GROUP BY om.meta_value
            ORDER BY revenue DESC
        ", $date_from . ' 00:00:00', $date_to . ' 23:59:59');

        $attribution_results = $wpdb->get_results($attribution_query);

        $attribution_summary = [];
        $total_revenue = 0;
        $total_orders = 0;

        foreach ($attribution_results as $result) {
            $attribution = json_decode($result->attribution_data, true);
            if (!$attribution) continue;

            $source = $attribution['utm_source'] ?? 'direct';
            $medium = $attribution['utm_medium'] ?? 'none';
            $campaign = $attribution['utm_campaign'] ?? 'none';
            
            $key = $source . '/' . $medium;
            
            if (!isset($attribution_summary[$key])) {
                $attribution_summary[$key] = [
                    'source' => $source,
                    'medium' => $medium,
                    'campaigns' => [],
                    'orders' => 0,
                    'revenue' => 0,
                    'avg_order_value' => 0
                ];
            }

            $attribution_summary[$key]['orders'] += intval($result->order_count);
            $attribution_summary[$key]['revenue'] += floatval($result->revenue);
            
            if (!in_array($campaign, $attribution_summary[$key]['campaigns'])) {
                $attribution_summary[$key]['campaigns'][] = $campaign;
            }

            $total_revenue += floatval($result->revenue);
            $total_orders += intval($result->order_count);
        }

        // Calculate percentages and AOV
        foreach ($attribution_summary as &$channel) {
            $channel['revenue_percentage'] = $total_revenue > 0 ? round(($channel['revenue'] / $total_revenue) * 100, 2) : 0;
            $channel['avg_order_value'] = $channel['orders'] > 0 ? round($channel['revenue'] / $channel['orders'], 2) : 0;
            $channel['campaigns'] = implode(', ', array_slice($channel['campaigns'], 0, 3));
        }

        return [
            'channels' => array_values($attribution_summary),
            'total_revenue' => $total_revenue,
            'total_orders' => $total_orders,
            'average_order_value' => $total_orders > 0 ? round($total_revenue / $total_orders, 2) : 0
        ];
    }

    /**
     * Get ROI analysis data
     *
     * @param string $date_from Start date
     * @param string $date_to End date
     * @return array ROI data
     */
    private function getRoiAnalysisData(string $date_from, string $date_to): array {
        // Get campaign cost data (would normally come from advertising APIs)
        $campaigns = $this->getCampaignCostData($date_from, $date_to);
        $attribution_data = $this->getAttributionReportData($date_from, $date_to);

        $roi_analysis = [];

        foreach ($attribution_data['channels'] as $channel) {
            $source = $channel['source'];
            $medium = $channel['medium'];
            
            // Find matching campaign cost data
            $campaign_cost = 0;
            $campaign_name = '';
            
            foreach ($campaigns as $campaign) {
                if ($campaign['source'] === $source && $campaign['medium'] === $medium) {
                    $campaign_cost = $campaign['cost'];
                    $campaign_name = $campaign['name'];
                    break;
                }
            }

            $revenue = $channel['revenue'];
            $roi = $campaign_cost > 0 ? round((($revenue - $campaign_cost) / $campaign_cost) * 100, 2) : 0;
            $roas = $campaign_cost > 0 ? round($revenue / $campaign_cost, 2) : 0;

            $roi_analysis[] = [
                'channel' => $source . '/' . $medium,
                'campaign_name' => $campaign_name,
                'revenue' => $revenue,
                'cost' => $campaign_cost,
                'profit' => $revenue - $campaign_cost,
                'roi_percentage' => $roi,
                'roas' => $roas,
                'orders' => $channel['orders'],
                'cost_per_acquisition' => $channel['orders'] > 0 ? round($campaign_cost / $channel['orders'], 2) : 0
            ];
        }

        // Sort by ROI descending
        usort($roi_analysis, function($a, $b) {
            return $b['roi_percentage'] <=> $a['roi_percentage'];
        });

        return [
            'campaigns' => $roi_analysis,
            'summary' => [
                'total_revenue' => array_sum(array_column($roi_analysis, 'revenue')),
                'total_cost' => array_sum(array_column($roi_analysis, 'cost')),
                'total_profit' => array_sum(array_column($roi_analysis, 'profit')),
                'overall_roi' => array_sum(array_column($roi_analysis, 'cost')) > 0 ? 
                    round((array_sum(array_column($roi_analysis, 'profit')) / array_sum(array_column($roi_analysis, 'cost'))) * 100, 2) : 0
            ]
        ];
    }

    /**
     * Get revenue by channel data
     *
     * @param string $date_from Start date
     * @param string $date_to End date
     * @return array Channel revenue data
     */
    private function getRevenueByChannelData(string $date_from, string $date_to): array {
        $attribution_data = $this->getAttributionReportData($date_from, $date_to);
        
        return [
            'chart_data' => array_map(function($channel) {
                return [
                    'label' => $channel['source'] . ' (' . $channel['medium'] . ')',
                    'value' => $channel['revenue'],
                    'orders' => $channel['orders'],
                    'percentage' => $channel['revenue_percentage']
                ];
            }, $attribution_data['channels']),
            'total_revenue' => $attribution_data['total_revenue']
        ];
    }

    /**
     * Get customer journey data
     *
     * @param int $customer_id Customer ID
     * @param int $order_id Order ID
     * @return array Journey data
     */
    private function getCustomerJourneyData(int $customer_id, int $order_id): array {
        global $wpdb;

        $journey_events = [];

        if ($order_id > 0) {
            $order = wc_get_order($order_id);
            if ($order) {
                // Get attribution data
                $attribution = $order->get_meta('_fp_attribution_data');
                if ($attribution) {
                    $attribution_data = json_decode($attribution, true);
                    
                    $journey_events[] = [
                        'timestamp' => $order->get_date_created()->format('Y-m-d H:i:s'),
                        'event' => 'First Visit',
                        'source' => $attribution_data['utm_source'] ?? 'direct',
                        'medium' => $attribution_data['utm_medium'] ?? 'none',
                        'campaign' => $attribution_data['utm_campaign'] ?? 'none',
                        'details' => 'Customer arrived via ' . ($attribution_data['utm_source'] ?? 'direct traffic')
                    ];
                }

                $journey_events[] = [
                    'timestamp' => $order->get_date_created()->format('Y-m-d H:i:s'),
                    'event' => 'Purchase',
                    'source' => 'checkout',
                    'medium' => 'website',
                    'campaign' => '',
                    'details' => 'Completed purchase - Order #' . $order->get_order_number() . ' - €' . $order->get_total()
                ];
            }
        }

        return [
            'events' => $journey_events,
            'customer_id' => $customer_id,
            'order_id' => $order_id
        ];
    }

    /**
     * Export analytics data
     *
     * @param string $export_type Type of export
     * @param string $format Export format
     * @param string $date_from Start date
     * @param string $date_to End date
     */
    private function exportData(string $export_type, string $format, string $date_from, string $date_to): void {
        $data = [];
        $filename = '';

        switch ($export_type) {
            case 'funnel':
                $data = $this->getConversionFunnelData($date_from, $date_to);
                $filename = 'conversion-funnel-' . $date_from . '-to-' . $date_to;
                break;
            case 'attribution':
                $data = $this->getAttributionReportData($date_from, $date_to);
                $filename = 'attribution-report-' . $date_from . '-to-' . $date_to;
                break;
            case 'roi':
                $data = $this->getRoiAnalysisData($date_from, $date_to);
                $filename = 'roi-analysis-' . $date_from . '-to-' . $date_to;
                break;
        }

        if ($format === 'csv') {
            $this->exportToCsv($data, $filename);
        } else {
            $this->exportToExcel($data, $filename);
        }
    }

    /**
     * Export data to CSV
     *
     * @param array $data Data to export
     * @param string $filename Filename
     */
    private function exportToCsv(array $data, string $filename): void {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

        $output = fopen('php://output', 'w');

        // Export based on data structure
        if (isset($data['funnel_steps'])) {
            fputcsv($output, ['Step', 'Count', 'Conversion Rate %', 'Drop Off']);
            foreach ($data['funnel_steps'] as $step) {
                fputcsv($output, [$step['step'], $step['count'], $step['conversion_rate'], $step['drop_off']]);
            }
        } elseif (isset($data['channels'])) {
            fputcsv($output, ['Source', 'Medium', 'Orders', 'Revenue', 'Revenue %', 'AOV', 'Campaigns']);
            foreach ($data['channels'] as $channel) {
                fputcsv($output, [
                    $channel['source'],
                    $channel['medium'],
                    $channel['orders'],
                    $channel['revenue'],
                    $channel['revenue_percentage'],
                    $channel['avg_order_value'],
                    $channel['campaigns']
                ]);
            }
        } elseif (isset($data['campaigns'])) {
            fputcsv($output, ['Channel', 'Campaign', 'Revenue', 'Cost', 'Profit', 'ROI %', 'ROAS', 'Orders', 'CPA']);
            foreach ($data['campaigns'] as $campaign) {
                fputcsv($output, [
                    $campaign['channel'],
                    $campaign['campaign_name'],
                    $campaign['revenue'],
                    $campaign['cost'],
                    $campaign['profit'],
                    $campaign['roi_percentage'],
                    $campaign['roas'],
                    $campaign['orders'],
                    $campaign['cost_per_acquisition']
                ]);
            }
        }

        fclose($output);
        exit;
    }

    /**
     * Export data to Excel (simplified CSV with .xlsx extension)
     *
     * @param array $data Data to export
     * @param string $filename Filename
     */
    private function exportToExcel(array $data, string $filename): void {
        // For now, use CSV format with Excel extension
        // In a real implementation, you would use PhpSpreadsheet
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        
        $this->exportToCsv($data, $filename);
    }

    /**
     * Helper methods for data retrieval
     */

    private function getWebsiteVisits(string $date_from, string $date_to): int {
        // Placeholder - would integrate with GA4 API
        return rand(1000, 5000);
    }

    private function getProductViews(string $date_from, string $date_to): int {
        // Placeholder - would track via custom events
        return rand(200, 800);
    }

    private function getCartAdditions(string $date_from, string $date_to): int {
        // Placeholder - would track via WooCommerce events
        return rand(50, 200);
    }

    private function getCheckoutStarts(string $date_from, string $date_to): int {
        // Placeholder - would track via checkout events
        return rand(30, 150);
    }

    private function getCompletedPurchases(string $date_from, string $date_to): int {
        global $wpdb;
        
        $table_orders = $wpdb->prefix . 'wc_orders';
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$table_orders}
            WHERE date_created_gmt BETWEEN %s AND %s
            AND status IN ('wc-processing', 'wc-completed')
        ", $date_from . ' 00:00:00', $date_to . ' 23:59:59'));

        return intval($count);
    }

    private function getTotalRevenue(string $date_from, string $date_to): float {
        global $wpdb;
        
        $table_orders = $wpdb->prefix . 'wc_orders';
        
        $revenue = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(total_amount)
            FROM {$table_orders}
            WHERE date_created_gmt BETWEEN %s AND %s
            AND status IN ('wc-processing', 'wc-completed')
        ", $date_from . ' 00:00:00', $date_to . ' 23:59:59'));

        return floatval($revenue);
    }

    private function getCampaignCostData(string $date_from, string $date_to): array {
        // Placeholder - would integrate with Google Ads API, Meta Ads API, etc.
        return [
            [
                'source' => 'google',
                'medium' => 'cpc',
                'name' => 'Summer Experiences Campaign',
                'cost' => 1500.00
            ],
            [
                'source' => 'facebook',
                'medium' => 'social',
                'name' => 'Meta Awareness Campaign',
                'cost' => 800.00
            ]
        ];
    }
}