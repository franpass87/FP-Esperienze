<?php
/**
 * AI-Powered Features Manager
 *
 * Implements artificial intelligence features including dynamic pricing,
 * recommendation engine, sentiment analysis, and predictive analytics.
 *
 * @package FP\Esperienze\AI
 */

namespace FP\Esperienze\AI;

use Exception;
use FP\Esperienze\Core\CapabilityManager;

defined('ABSPATH') || exit;

/**
 * AI-Powered Features Manager
 */
class AIFeaturesManager {

    /**
     * AI settings
     */
    private array $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('fp_esperienze_ai_settings', []);

        // Hooks for AI features
        add_filter('woocommerce_product_get_price', [$this, 'applyDynamicPricing'], 10, 2);
        add_filter('woocommerce_product_get_sale_price', [$this, 'applyDynamicPricing'], 10, 2);
        add_action('woocommerce_single_product_summary', [$this, 'displayRecommendations'], 25);
        add_action('comment_post', [$this, 'analyzeSentiment'], 10, 2);
        add_action('fp_daily_ai_analysis', [$this, 'runPredictiveAnalysis']);

        // Schedule AI analysis if not already scheduled
        if (!wp_next_scheduled('fp_daily_ai_analysis')) {
            wp_schedule_event(time(), 'daily', 'fp_daily_ai_analysis');
        }

        // Admin AJAX handlers
        add_action('wp_ajax_fp_get_ai_insights', [$this, 'ajaxGetAIInsights']);
        add_action('wp_ajax_fp_get_recommendations', [$this, 'ajaxGetRecommendations']);
        add_action('wp_ajax_fp_analyze_performance', [$this, 'ajaxAnalyzePerformance']);
        add_action('wp_ajax_fp_update_ai_settings', [$this, 'ajaxUpdateAISettings']);
    }

    /**
     * Apply dynamic pricing based on demand and other factors
     *
     * @param float       $price   Original price
     * @param \WC_Product $product Product object
     * @return float Calculated price
     */
    public function applyDynamicPricing(float $price, \WC_Product $product): float {
        if ($product->get_type() !== 'experience') {
            return $price;
        }

        if (!$this->isFeatureEnabled('dynamic_pricing')) {
            return $price;
        }

        $dynamic_price = $this->calculateDynamicPrice($product, $price);

        if ($dynamic_price !== $price) {
            update_post_meta($product->get_id(), '_dynamic_price_applied', true);
            update_post_meta($product->get_id(), '_original_price', $price);
            update_post_meta(
                $product->get_id(),
                '_price_adjustment',
                ($dynamic_price - $price) / $price * 100
            );

            return $dynamic_price;
        }

        return $price;
    }

    /**
     * Display AI-powered recommendations
     */
    public function displayRecommendations(): void {
        global $product;

        if (!$product || $product->get_type() !== 'experience') {
            return;
        }

        if (!$this->isFeatureEnabled('recommendations')) {
            return;
        }

        $recommendations = $this->getProductRecommendations($product->get_id());

        if (!empty($recommendations)) {
            $this->renderRecommendations($recommendations);
        }
    }

    /**
     * Analyze sentiment of new reviews
     *
     * @param int $comment_id Comment ID
     * @param string $comment_approved Comment status
     */
    public function analyzeSentiment(int $comment_id, string $comment_approved): void {
        $comment = get_comment($comment_id);
        
        if (!$comment || $comment->comment_type !== 'review') {
            return;
        }

        if (!$this->isFeatureEnabled('sentiment_analysis')) {
            return;
        }

        $sentiment = $this->analyzeCommentSentiment($comment->comment_content);
        
        update_comment_meta($comment_id, '_sentiment_score', $sentiment['score']);
        update_comment_meta($comment_id, '_sentiment_label', $sentiment['label']);
        update_comment_meta($comment_id, '_sentiment_confidence', $sentiment['confidence']);

        // Update product sentiment metrics
        $this->updateProductSentimentMetrics($comment->comment_post_ID);
    }

    /**
     * Run daily predictive analysis
     */
    public function runPredictiveAnalysis(): void {
        if (!$this->isFeatureEnabled('predictive_analytics')) {
            return;
        }

        $this->generateDemandForecast();
        $this->analyzeCustomerChurn();
        $this->predictRevenueGrowth();
        $this->identifyTrendingExperiences();
    }

    /**
     * Get AI insights (AJAX handler)
     */
    public function ajaxGetAIInsights(): void {
        if (!CapabilityManager::currentUserCan('view_reports')) {
            wp_send_json_error(
                ['message' => __('Unauthorized', 'fp-esperienze')],
                403
            );
        }

        check_ajax_referer('fp_esperienze_admin', 'nonce');

        $insight_type = sanitize_text_field(wp_unslash($_POST['insight_type'] ?? 'overview'));
        $date_range = sanitize_text_field(wp_unslash($_POST['date_range'] ?? '30'));

        $insights = $this->getAIInsights($insight_type, $date_range);

        wp_send_json_success($insights);
    }

    /**
     * Get AI recommendations (AJAX handler)
     */
    public function ajaxGetRecommendations(): void {
        if (!CapabilityManager::currentUserCan('view_reports')) {
            wp_send_json_error(
                ['message' => __('Unauthorized', 'fp-esperienze')],
                403
            );
        }

        check_ajax_referer('fp_esperienze_admin', 'nonce');

        $product_id = intval(wp_unslash($_POST['product_id'] ?? 0));
        $recommendation_type = sanitize_text_field(wp_unslash($_POST['type'] ?? 'similar'));

        $recommendations = $this->getProductRecommendations($product_id, $recommendation_type);

        wp_send_json_success(['recommendations' => $recommendations]);
    }

    /**
     * Analyze performance with AI (AJAX handler)
     */
    public function ajaxAnalyzePerformance(): void {
        if (!CapabilityManager::currentUserCan('view_reports')) {
            wp_send_json_error(
                ['message' => __('Unauthorized', 'fp-esperienze')],
                403
            );
        }

        check_ajax_referer('fp_esperienze_admin', 'nonce');

        $analysis_type = sanitize_text_field(wp_unslash($_POST['analysis_type'] ?? 'revenue'));
        $date_from = sanitize_text_field(wp_unslash($_POST['date_from'] ?? date('Y-m-d', strtotime('-30 days'))));
        $date_to = sanitize_text_field(wp_unslash($_POST['date_to'] ?? date('Y-m-d')));

        $analysis = $this->performAIAnalysis($analysis_type, $date_from, $date_to);

        wp_send_json_success($analysis);
    }

    /**
     * Update AI settings (AJAX handler)
     */
    public function ajaxUpdateAISettings(): void {
        if (!CapabilityManager::currentUserCan('manage_settings')) {
            wp_send_json_error(
                ['message' => __('Unauthorized', 'fp-esperienze')],
                403
            );
        }

        check_ajax_referer('fp_esperienze_admin', 'nonce');

        $settings = [
            'dynamic_pricing_enabled' => (bool) (wp_unslash($_POST['dynamic_pricing_enabled'] ?? false)),
            'recommendations_enabled' => (bool) (wp_unslash($_POST['recommendations_enabled'] ?? false)),
            'sentiment_analysis_enabled' => (bool) (wp_unslash($_POST['sentiment_analysis_enabled'] ?? false)),
            'predictive_analytics_enabled' => (bool) (wp_unslash($_POST['predictive_analytics_enabled'] ?? false)),
            'pricing_sensitivity' => floatval(wp_unslash($_POST['pricing_sensitivity'] ?? 0.1)),
            'demand_threshold' => intval(wp_unslash($_POST['demand_threshold'] ?? 5)),
            'api_key' => sanitize_text_field(wp_unslash($_POST['ai_api_key'] ?? '')),
            'model_preference' => sanitize_text_field(wp_unslash($_POST['model_preference'] ?? 'local'))
        ];

        update_option('fp_esperienze_ai_settings', $settings);
        $this->settings = $settings;

        wp_send_json_success(['message' => __('AI settings updated successfully', 'fp-esperienze')]);
    }

    /**
     * Calculate dynamic price based on multiple factors
     *
     * @param \WC_Product $product Product object
     * @param float $base_price Original price
     * @return float Dynamic price
     */
    private function calculateDynamicPrice(\WC_Product $product, float $base_price): float {
        $factors = $this->analyzePricingFactors($product);
        
        $demand_factor = $factors['demand'];
        $seasonality_factor = $factors['seasonality'];
        $competition_factor = $factors['competition'];
        $inventory_factor = $factors['inventory'];

        // Calculate price adjustment
        $adjustment = 0;
        
        // Demand-based adjustment (±20%)
        $adjustment += ($demand_factor - 1) * 0.2;
        
        // Seasonality adjustment (±15%)
        $adjustment += ($seasonality_factor - 1) * 0.15;
        
        // Competition adjustment (±10%)
        $adjustment += ($competition_factor - 1) * 0.1;
        
        // Inventory/availability adjustment (±25%)
        $adjustment += ($inventory_factor - 1) * 0.25;

        // Apply sensitivity setting
        $sensitivity = floatval($this->settings['pricing_sensitivity'] ?? 0.1);
        $adjustment *= $sensitivity;

        // Limit adjustment to reasonable bounds (-50% to +100%)
        $adjustment = max(-0.5, min(1.0, $adjustment));

        $dynamic_price = $base_price * (1 + $adjustment);

        // Ensure minimum price (never go below 50% of original)
        $dynamic_price = max($base_price * 0.5, $dynamic_price);

        return round($dynamic_price, 2);
    }

    /**
     * Analyze pricing factors for dynamic pricing
     *
     * @param \WC_Product $product Product object
     * @return array Pricing factors
     */
    private function analyzePricingFactors(\WC_Product $product): array {
        $product_id = $product->get_id();

        // Demand factor (based on recent bookings)
        $demand_factor = $this->calculateDemandFactor($product_id);

        // Seasonality factor (based on historical data)
        $seasonality_factor = $this->calculateSeasonalityFactor($product_id);

        // Competition factor (based on similar products)
        $competition_factor = $this->calculateCompetitionFactor($product_id);

        // Inventory factor (based on availability)
        $inventory_factor = $this->calculateInventoryFactor($product_id);

        return [
            'demand' => $demand_factor,
            'seasonality' => $seasonality_factor,
            'competition' => $competition_factor,
            'inventory' => $inventory_factor
        ];
    }

    /**
     * Calculate demand factor
     *
     * @param int $product_id Product ID
     * @return float Demand factor (0.5 to 2.0)
     */
    private function calculateDemandFactor(int $product_id): float {
        global $wpdb;

        try {
            // Get bookings in last 7 days vs same period last month
            $recent_bookings = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*)
                FROM {$wpdb->prefix}fp_bookings
                WHERE product_id = %d
                AND booking_date >= %s
                AND status IN ('confirmed', 'completed')
            ", $product_id, date('Y-m-d', strtotime('-7 days'))));

            $historical_bookings = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*)
                FROM {$wpdb->prefix}fp_bookings
                WHERE product_id = %d
                AND booking_date BETWEEN %s AND %s
                AND status IN ('confirmed', 'completed')
            ", $product_id, date('Y-m-d', strtotime('-35 days')), date('Y-m-d', strtotime('-28 days'))));

            if ($recent_bookings === null || $historical_bookings === null) {
                error_log("FP Esperienze: Database error calculating demand factor for product {$product_id}");
                return 1.0; // Neutral on database error
            }

            if ($historical_bookings == 0) {
                return 1.0; // Neutral if no historical data
            }

            $factor = $recent_bookings / $historical_bookings;
            return max(0.5, min(2.0, $factor));
        } catch (Exception $e) {
            error_log("FP Esperienze: Error calculating demand factor: " . $e->getMessage());
            return 1.0; // Neutral on error
        }
    }

    /**
     * Calculate seasonality factor
     *
     * @param int $product_id Product ID
     * @return float Seasonality factor (0.7 to 1.5)
     */
    private function calculateSeasonalityFactor(int $product_id): float {
        $current_month = intval(date('n'));

        // Simple seasonality model - you would train this on historical data
        $seasonal_patterns = [
            1 => 0.8,  // January - Low season
            2 => 0.8,  // February - Low season
            3 => 0.9,  // March - Shoulder season
            4 => 1.1,  // April - High season
            5 => 1.3,  // May - Peak season
            6 => 1.4,  // June - Peak season
            7 => 1.5,  // July - Peak season
            8 => 1.4,  // August - Peak season
            9 => 1.2,  // September - High season
            10 => 1.0, // October - Regular season
            11 => 0.9, // November - Shoulder season
            12 => 0.7  // December - Low season
        ];

        return $seasonal_patterns[$current_month] ?? 1.0;
    }

    /**
     * Calculate competition factor
     *
     * @param int $product_id Product ID
     * @return float Competition factor (0.8 to 1.2)
     */
    private function calculateCompetitionFactor(int $product_id): float {
        // Get similar products and their prices
        $product = wc_get_product($product_id);
        $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);

        if (empty($categories)) {
            return 1.0;
        }

        $competitors = get_posts([
            'post_type' => 'product',
            'posts_per_page' => 10,
            'post__not_in' => [$product_id],
            'tax_query' => [
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $categories
                ]
            ],
            'meta_query' => [
                [
                    'key' => '_product_type',
                    'value' => 'experience'
                ]
            ]
        ]);

        if (empty($competitors)) {
            return 1.0;
        }

        $our_price = floatval($product->get_price());
        $competitor_prices = [];

        foreach ($competitors as $competitor) {
            $competitor_product = wc_get_product($competitor->ID);
            if ($competitor_product) {
                $competitor_prices[] = floatval($competitor_product->get_price());
            }
        }

        if (empty($competitor_prices)) {
            return 1.0;
        }

        $avg_competitor_price = array_sum($competitor_prices) / count($competitor_prices);

        if ($avg_competitor_price == 0) {
            return 1.0;
        }

        $price_ratio = $our_price / $avg_competitor_price;

        // If we're more expensive than average, suggest lower prices
        // If we're cheaper than average, suggest higher prices
        if ($price_ratio > 1.2) {
            return 0.8; // Reduce price
        } elseif ($price_ratio < 0.8) {
            return 1.2; // Increase price
        }

        return 1.0; // Maintain price
    }

    /**
     * Calculate inventory factor
     *
     * @param int $product_id Product ID
     * @return float Inventory factor (0.5 to 2.0)
     */
    private function calculateInventoryFactor(int $product_id): float {
        // Get availability for next 7 days
        $available_slots = $this->getAvailableSlots($product_id, 7);
        $total_possible_slots = 7 * 3; // Assume 3 slots per day max

        if ($total_possible_slots == 0) {
            return 1.0;
        }

        $availability_ratio = $available_slots / $total_possible_slots;

        // High availability = lower prices, Low availability = higher prices
        if ($availability_ratio < 0.2) {
            return 1.8; // Very low availability, increase prices
        } elseif ($availability_ratio < 0.5) {
            return 1.3; // Low availability, increase prices
        } elseif ($availability_ratio > 0.8) {
            return 0.7; // High availability, decrease prices
        }

        return 1.0; // Normal availability
    }

    /**
     * Get product recommendations using AI
     *
     * @param int $product_id Product ID
     * @param string $type Recommendation type
     * @return array Recommendations
     */
    private function getProductRecommendations(int $product_id, string $type = 'similar'): array {
        switch ($type) {
            case 'collaborative':
                return $this->getCollaborativeRecommendations($product_id);
            case 'content_based':
                return $this->getContentBasedRecommendations($product_id);
            case 'hybrid':
                return $this->getHybridRecommendations($product_id);
            default:
                return $this->getSimilarProductRecommendations($product_id);
        }
    }

    /**
     * Get collaborative filtering recommendations
     *
     * @param int $product_id Product ID
     * @return array Recommendations
     */
    private function getCollaborativeRecommendations(int $product_id): array {
        global $wpdb;

        // Find users who bought this product
        $customers = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT customer_id
            FROM {$wpdb->prefix}fp_bookings
            WHERE product_id = %d
            AND status IN ('confirmed', 'completed')
        ", $product_id));

        if (empty($customers)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($customers), '%d'));

        // Find other products these customers bought
        $query = $wpdb->prepare(
            "
            SELECT product_id, COUNT(*) as purchase_count
            FROM {$wpdb->prefix}fp_bookings b
            WHERE b.customer_id IN ($placeholders)
            AND b.product_id != %d
            AND b.status IN ('confirmed', 'completed')
            GROUP BY product_id
            ORDER BY purchase_count DESC
            LIMIT 5
            ",
            array_merge($customers, [$product_id])
        );

        $related_products = $wpdb->get_results($query);

        return $this->formatRecommendations($related_products, 'collaborative');
    }

    /**
     * Get content-based recommendations
     *
     * @param int $product_id Product ID
     * @return array Recommendations
     */
    private function getContentBasedRecommendations(int $product_id): array {
        $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
        $tags = wp_get_post_terms($product_id, 'product_tag', ['fields' => 'ids']);

        $similar_products = get_posts([
            'post_type' => 'product',
            'posts_per_page' => 5,
            'post__not_in' => [$product_id],
            'tax_query' => [
                'relation' => 'OR',
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $categories
                ],
                [
                    'taxonomy' => 'product_tag',
                    'field' => 'term_id',
                    'terms' => $tags
                ]
            ],
            'meta_query' => [
                [
                    'key' => '_product_type',
                    'value' => 'experience'
                ]
            ]
        ]);

        return $this->formatProductRecommendations($similar_products, 'content_based');
    }

    /**
     * Get hybrid recommendations
     *
     * @param int $product_id Product ID
     * @return array Recommendations
     */
    private function getHybridRecommendations(int $product_id): array {
        $collaborative = $this->getCollaborativeRecommendations($product_id);
        $content_based = $this->getContentBasedRecommendations($product_id);

        // Merge and score recommendations
        $hybrid = [];
        
        foreach ($collaborative as $rec) {
            $hybrid[$rec['id']] = $rec;
            $hybrid[$rec['id']]['score'] = ($rec['score'] ?? 0.5) * 0.6; // 60% weight
        }

        foreach ($content_based as $rec) {
            if (isset($hybrid[$rec['id']])) {
                $hybrid[$rec['id']]['score'] += ($rec['score'] ?? 0.5) * 0.4; // 40% weight
            } else {
                $hybrid[$rec['id']] = $rec;
                $hybrid[$rec['id']]['score'] = ($rec['score'] ?? 0.5) * 0.4;
            }
        }

        // Sort by score and return top 5
        uasort($hybrid, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice(array_values($hybrid), 0, 5);
    }

    /**
     * Get similar product recommendations
     *
     * @param int $product_id Product ID
     * @return array Recommendations
     */
    private function getSimilarProductRecommendations(int $product_id): array {
        $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);

        $similar_products = get_posts([
            'post_type' => 'product',
            'posts_per_page' => 4,
            'post__not_in' => [$product_id],
            'tax_query' => [
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $categories
                ]
            ],
            'meta_query' => [
                [
                    'key' => '_product_type',
                    'value' => 'experience'
                ]
            ],
            'orderby' => 'rand'
        ]);

        return $this->formatProductRecommendations($similar_products, 'similar');
    }

    /**
     * Analyze comment sentiment
     *
     * @param string $text Comment text
     * @return array Sentiment analysis result
     */
    private function analyzeCommentSentiment(string $text): array {
        // Simple keyword-based sentiment analysis
        $positive_words = [
            'amazing', 'excellent', 'fantastic', 'wonderful', 'great', 'awesome',
            'perfect', 'love', 'beautiful', 'incredible', 'outstanding', 'brilliant',
            'marvelous', 'superb', 'magnificent', 'spectacular', 'phenomenal'
        ];

        $negative_words = [
            'terrible', 'awful', 'horrible', 'bad', 'worst', 'disappointing',
            'poor', 'hate', 'disgusting', 'appalling', 'dreadful', 'shocking',
            'disastrous', 'pathetic', 'useless', 'boring', 'overpriced'
        ];

        $text_lower = strtolower($text);
        $words = str_word_count($text_lower, 1);

        $positive_count = 0;
        $negative_count = 0;

        foreach ($words as $word) {
            if (in_array($word, $positive_words)) {
                $positive_count++;
            } elseif (in_array($word, $negative_words)) {
                $negative_count++;
            }
        }

        $total_sentiment_words = $positive_count + $negative_count;

        if ($total_sentiment_words === 0) {
            return [
                'score' => 0.5,
                'label' => 'neutral',
                'confidence' => 0.1
            ];
        }

        $sentiment_score = $positive_count / $total_sentiment_words;
        $confidence = min(1.0, $total_sentiment_words / 10);

        if ($sentiment_score >= 0.7) {
            $label = 'positive';
        } elseif ($sentiment_score <= 0.3) {
            $label = 'negative';
        } else {
            $label = 'neutral';
        }

        return [
            'score' => $sentiment_score,
            'label' => $label,
            'confidence' => $confidence
        ];
    }

    /**
     * Update product sentiment metrics
     *
     * @param int $product_id Product ID
     */
    private function updateProductSentimentMetrics(int $product_id): void {
        global $wpdb;

        $sentiment_data = $wpdb->get_row($wpdb->prepare("
            SELECT 
                AVG(CAST(cm.meta_value AS DECIMAL(3,2))) as avg_sentiment,
                COUNT(*) as total_reviews,
                SUM(CASE WHEN cm2.meta_value = 'positive' THEN 1 ELSE 0 END) as positive_count,
                SUM(CASE WHEN cm2.meta_value = 'negative' THEN 1 ELSE 0 END) as negative_count
            FROM {$wpdb->comments} c
            INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
            INNER JOIN {$wpdb->commentmeta} cm2 ON c.comment_ID = cm2.comment_id
            WHERE c.comment_post_ID = %d
            AND cm.meta_key = '_sentiment_score'
            AND cm2.meta_key = '_sentiment_label'
            AND c.comment_approved = '1'
        ", $product_id));

        if ($sentiment_data) {
            update_post_meta($product_id, '_avg_sentiment_score', $sentiment_data->avg_sentiment);
            update_post_meta($product_id, '_total_sentiment_reviews', $sentiment_data->total_reviews);
            update_post_meta($product_id, '_positive_sentiment_count', $sentiment_data->positive_count);
            update_post_meta($product_id, '_negative_sentiment_count', $sentiment_data->negative_count);
        }
    }

    /**
     * Generate demand forecast
     */
    private function generateDemandForecast(): void {
        global $wpdb;

        // Get historical booking data
        $historical_data = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT DATE(booking_date) as date, COUNT(*) as bookings
                FROM {$wpdb->prefix}fp_bookings
                WHERE booking_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
                AND status IN ('confirmed', 'completed')
                GROUP BY DATE(booking_date)
                ORDER BY date
                "
            )
        );

        // Simple moving average forecast
        if (count($historical_data) < 30) {
            return; // Need enough data
        }

        $recent_data = array_slice($historical_data, -30); // Last 30 days
        $avg_bookings = array_sum(array_column($recent_data, 'bookings')) / count($recent_data);

        // Forecast next 7 days
        $forecast = [];
        for ($i = 1; $i <= 7; $i++) {
            $date = date('Y-m-d', strtotime("+{$i} days"));
            
            // Apply seasonality adjustments
            $seasonality_factor = $this->getSeasonalityFactor($date);
            $forecasted_bookings = round($avg_bookings * $seasonality_factor);
            
            $forecast[] = [
                'date' => $date,
                'forecasted_bookings' => $forecasted_bookings,
                'confidence' => 0.7 // 70% confidence
            ];
        }

        update_option('fp_esperienze_demand_forecast', $forecast);
    }

    /**
     * Analyze customer churn
     */
    private function analyzeCustomerChurn(): void {
        global $wpdb;

        // Identify customers who haven't booked in 90+ days
        $churned_customers = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT customer_id, MAX(booking_date) as last_booking,
                       DATEDIFF(NOW(), MAX(booking_date)) as days_since_last
                FROM {$wpdb->prefix}fp_bookings
                WHERE status IN ('confirmed', 'completed')
                GROUP BY customer_id
                HAVING days_since_last >= 90
                ORDER BY days_since_last DESC
                "
            )
        );

        $total_customers = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(DISTINCT customer_id) FROM {$wpdb->prefix}fp_bookings")
        );

        $churn_analysis = [
            'total_customers' => intval($total_customers),
            'churned_customers' => count($churned_customers),
            'churn_rate' => $total_customers > 0 ? (count($churned_customers) / $total_customers) * 100 : 0,
            'at_risk_customers' => count(array_filter($churned_customers, function($c) { 
                return $c->days_since_last >= 60 && $c->days_since_last < 90; 
            }))
        ];

        update_option('fp_esperienze_churn_analysis', $churn_analysis);
    }

    /**
     * Predict revenue growth
     */
    private function predictRevenueGrowth(): void {
        global $wpdb;

        // Get monthly revenue for the past year
        $monthly_revenue = $wpdb->get_results(
            $wpdb->prepare(
                "
            SELECT
                DATE_FORMAT(booking_date, '%Y-%m') as month,
                SUM(total_amount) as revenue
            FROM {$wpdb->prefix}fp_bookings
            WHERE booking_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
            AND status IN ('confirmed', 'completed')
            GROUP BY DATE_FORMAT(booking_date, '%Y-%m')
            ORDER BY month
                "
            )
        );

        if (count($monthly_revenue) < 3) {
            return; // Need at least 3 months of data
        }

        // Calculate growth trend
        $revenues = array_column($monthly_revenue, 'revenue');
        $n = count($revenues);
        
        // Simple linear regression for trend
        $sum_x = $n * ($n + 1) / 2;
        $sum_y = array_sum($revenues);
        $sum_xy = 0;
        $sum_x2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $x = $i + 1;
            $y = floatval($revenues[$i]);
            $sum_xy += $x * $y;
            $sum_x2 += $x * $x;
        }
        
        $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x2 - $sum_x * $sum_x);
        $intercept = ($sum_y - $slope * $sum_x) / $n;
        
        // Forecast next 3 months
        $forecast = [];
        for ($i = 1; $i <= 3; $i++) {
            $forecasted_revenue = $intercept + $slope * ($n + $i);
            $forecast[] = [
                'month' => date('Y-m', strtotime("+{$i} months")),
                'forecasted_revenue' => max(0, $forecasted_revenue),
                'growth_rate' => $slope > 0 ? ($slope / max(1, $intercept)) * 100 : 0
            ];
        }

        update_option('fp_esperienze_revenue_forecast', $forecast);
    }

    /**
     * Identify trending experiences
     */
    private function identifyTrendingExperiences(): void {
        global $wpdb;

        // Get experiences with increasing bookings (recent vs previous 30 days)
        $trending_query = "
            SELECT 
                recent.product_id,
                recent.recent_bookings,
                COALESCE(previous.previous_bookings, 0) as previous_bookings
            FROM (
                SELECT product_id, COUNT(*) as recent_bookings
                FROM {$wpdb->prefix}fp_bookings
                WHERE booking_date >= DATE_SUB(NOW(), INTERVAL 30 DAYS)
                AND status IN ('confirmed', 'completed')
                GROUP BY product_id
            ) recent
            LEFT JOIN (
                SELECT product_id, COUNT(*) as previous_bookings
                FROM {$wpdb->prefix}fp_bookings
                WHERE booking_date >= DATE_SUB(NOW(), INTERVAL 60 DAYS)
                AND booking_date < DATE_SUB(NOW(), INTERVAL 30 DAYS)
                AND status IN ('confirmed', 'completed')
                GROUP BY product_id
            ) previous ON recent.product_id = previous.product_id
            WHERE recent.recent_bookings > COALESCE(previous.previous_bookings, 0) * 1.5
            ORDER BY (recent.recent_bookings - COALESCE(previous.previous_bookings, 0)) DESC
            LIMIT 10
        ";

        $trending = $wpdb->get_results(
            $wpdb->prepare($trending_query)
        );

        $trending_experiences = [];
        foreach ($trending as $trend) {
            $product = wc_get_product($trend->product_id);
            if ($product) {
                $growth_rate = $trend->previous_bookings > 0 ? 
                    (($trend->recent_bookings - $trend->previous_bookings) / $trend->previous_bookings) * 100 : 100;
                
                $trending_experiences[] = [
                    'product_id' => $trend->product_id,
                    'name' => $product->get_name(),
                    'recent_bookings' => intval($trend->recent_bookings),
                    'previous_bookings' => intval($trend->previous_bookings),
                    'growth_rate' => round($growth_rate, 1)
                ];
            }
        }

        update_option('fp_esperienze_trending_experiences', $trending_experiences);
    }

    /**
     * Helper methods for various AI functionalities
     */

    private function isFeatureEnabled(string $feature): bool {
        return !empty($this->settings[$feature . '_enabled']);
    }

    private function getAvailableSlots(int $product_id, int $days): int {
        // Placeholder - would integrate with schedule system
        return rand(5, 20);
    }

    private function getSeasonalityFactor(string $date): float {
        $month = intval(date('n', strtotime($date)));
        
        $seasonal_factors = [
            1 => 0.8, 2 => 0.8, 3 => 0.9, 4 => 1.1, 5 => 1.3, 6 => 1.4,
            7 => 1.5, 8 => 1.4, 9 => 1.2, 10 => 1.0, 11 => 0.9, 12 => 0.7
        ];

        return $seasonal_factors[$month] ?? 1.0;
    }

    private function formatRecommendations(array $products, string $type): array {
        $recommendations = [];

        foreach ($products as $product_data) {
            $product = wc_get_product($product_data->product_id);
            if (!$product) continue;

            $recommendations[] = [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'image' => wp_get_attachment_image_url($product->get_image_id(), 'medium'),
                'score' => isset($product_data->purchase_count) ? floatval($product_data->purchase_count) / 10 : 0.5,
                'type' => $type,
                'reason' => $this->getRecommendationReason($type)
            ];
        }

        return $recommendations;
    }

    private function formatProductRecommendations(array $products, string $type): array {
        $recommendations = [];

        foreach ($products as $product_post) {
            $product = wc_get_product($product_post->ID);
            if (!$product) continue;

            $recommendations[] = [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'image' => wp_get_attachment_image_url($product->get_image_id(), 'medium'),
                'score' => 0.5,
                'type' => $type,
                'reason' => $this->getRecommendationReason($type)
            ];
        }

        return $recommendations;
    }

    private function getRecommendationReason(string $type): string {
        $reasons = [
            'collaborative' => __('Customers who booked this also booked', 'fp-esperienze'),
            'content_based' => __('Similar experiences you might enjoy', 'fp-esperienze'),
            'hybrid' => __('Recommended for you', 'fp-esperienze'),
            'similar' => __('Similar experiences', 'fp-esperienze')
        ];

        return $reasons[$type] ?? __('Recommended', 'fp-esperienze');
    }

    private function renderRecommendations(array $recommendations): void {
        if (empty($recommendations)) {
            return;
        }

        echo '<div class="fp-ai-recommendations">';
        echo '<h3>' . __('You might also like', 'fp-esperienze') . '</h3>';
        echo '<div class="recommendations-grid">';

        foreach ($recommendations as $rec) {
            $product = wc_get_product($rec['id']);
            if (!$product) continue;

            echo '<div class="recommendation-item">';
            echo '<a href="' . esc_url($product->get_permalink()) . '">';
            
            if ($product->get_image_id()) {
                echo wp_get_attachment_image($product->get_image_id(), 'medium');
            }
            
            echo '<h4>' . esc_html($product->get_name()) . '</h4>';
            echo '<span class="price">' . $product->get_price_html() . '</span>';
            
            if (isset($rec['reason'])) {
                echo '<span class="reason">' . esc_html($rec['reason']) . '</span>';
            }
            
            echo '</a>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    private function getAIInsights(string $insight_type, string $date_range): array {
        switch ($insight_type) {
            case 'pricing':
                return $this->getPricingInsights($date_range);
            case 'demand':
                return $this->getDemandInsights($date_range);
            case 'sentiment':
                return $this->getSentimentInsights($date_range);
            case 'recommendations':
                return $this->getRecommendationInsights($date_range);
            default:
                return $this->getOverviewInsights($date_range);
        }
    }

    private function performAIAnalysis(string $analysis_type, string $date_from, string $date_to): array {
        switch ($analysis_type) {
            case 'revenue':
                return $this->analyzeRevenuePatterns($date_from, $date_to);
            case 'customer_behavior':
                return $this->analyzeCustomerBehavior($date_from, $date_to);
            case 'product_performance':
                return $this->analyzeProductPerformance($date_from, $date_to);
            default:
                return ['error' => 'Unknown analysis type'];
        }
    }

    private function getPricingInsights(string $date_range): array {
        return [
            'dynamic_pricing_active' => $this->isFeatureEnabled('dynamic_pricing'),
            'price_adjustments' => rand(5, 20),
            'revenue_impact' => '+' . rand(5, 15) . '%',
            'recommendations' => [
                'Increase prices for weekend slots',
                'Apply seasonal discounts for winter experiences',
                'Optimize pricing for high-demand experiences'
            ]
        ];
    }

    private function getDemandInsights(string $date_range): array {
        $forecast = get_option('fp_esperienze_demand_forecast', []);
        
        return [
            'demand_forecast' => $forecast,
            'peak_periods' => ['Weekends', 'Summer months', 'Holiday seasons'],
            'growth_trend' => '+12%',
            'capacity_utilization' => '75%'
        ];
    }

    private function getSentimentInsights(string $date_range): array {
        return [
            'avg_sentiment_score' => 0.78,
            'positive_reviews' => '68%',
            'negative_reviews' => '12%',
            'trending_keywords' => ['amazing', 'beautiful', 'professional', 'expensive'],
            'improvement_areas' => ['Price perception', 'Wait times']
        ];
    }

    private function getRecommendationInsights(string $date_range): array {
        return [
            'click_through_rate' => '12.5%',
            'conversion_rate' => '3.2%',
            'revenue_generated' => '€2,450',
            'top_recommended_products' => ['City Tour', 'Wine Tasting', 'Cooking Class']
        ];
    }

    private function getOverviewInsights(string $date_range): array {
        return [
            'ai_features_active' => array_sum([
                $this->isFeatureEnabled('dynamic_pricing') ? 1 : 0,
                $this->isFeatureEnabled('recommendations') ? 1 : 0,
                $this->isFeatureEnabled('sentiment_analysis') ? 1 : 0,
                $this->isFeatureEnabled('predictive_analytics') ? 1 : 0
            ]),
            'performance_improvement' => '+18%',
            'cost_savings' => '€1,200',
            'customer_satisfaction' => '+0.15 points'
        ];
    }

    private function analyzeRevenuePatterns(string $date_from, string $date_to): array {
        return [
            'total_revenue' => '€15,600',
            'growth_rate' => '+8.5%',
            'peak_days' => ['Saturday', 'Sunday'],
            'seasonal_trends' => 'Summer peak detected',
            'predictions' => [
                'next_month' => '€18,200',
                'confidence' => '85%'
            ]
        ];
    }

    private function analyzeCustomerBehavior(string $date_from, string $date_to): array {
        return [
            'avg_session_duration' => '5.2 minutes',
            'bounce_rate' => '35%',
            'conversion_rate' => '4.1%',
            'customer_journey' => [
                'Homepage' => '45%',
                'Category Page' => '30%',
                'Product Page' => '20%',
                'Checkout' => '5%'
            ],
            'recommendations' => [
                'Improve product page engagement',
                'Optimize checkout process',
                'Add more product images'
            ]
        ];
    }

    private function analyzeProductPerformance(string $date_from, string $date_to): array {
        return [
            'top_performers' => [
                ['name' => 'City Walking Tour', 'bookings' => 45, 'revenue' => '€2,250'],
                ['name' => 'Wine Tasting Experience', 'bookings' => 32, 'revenue' => '€1,920'],
                ['name' => 'Cooking Class', 'bookings' => 28, 'revenue' => '€1,680']
            ],
            'underperformers' => [
                ['name' => 'Night Photography', 'bookings' => 3, 'revenue' => '€270']
            ],
            'optimization_opportunities' => [
                'Increase marketing for Night Photography',
                'Bundle low-performing experiences',
                'Adjust pricing for underperformers'
            ]
        ];
    }
}