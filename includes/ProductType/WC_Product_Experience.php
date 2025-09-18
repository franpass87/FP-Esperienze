<?php
/**
 * WooCommerce Experience Product Class
 *
 * @package FP\Esperienze\ProductType
 */

namespace FP\Esperienze\ProductType;

defined('ABSPATH') || exit;

use FP\Esperienze\Data\ScheduleManager;

/**
 * Experience product class
 */
class WC_Product_Experience extends \WC_Product {

    /**
     * Product type
     *
     * @var string
     */
    protected $product_type = 'experience';

    /**
     * Supported features for the experience product type.
     *
     * @var array<string>
     */
    protected $supports = [
        'ajax_add_to_cart',
    ];

    /**
     * Constructor
     *
     * @param mixed $product Product ID or WC_Product
     */
    public function __construct($product = 0) {
        parent::__construct($product);
    }

    /**
     * Get product type
     *
     * @return string
     */
    public function get_type() {
        return 'experience';
    }

    /**
     * Check if product is virtual
     *
     * @return bool
     */
    public function is_virtual() {
        return true;
    }

    /**
     * Check if product is downloadable
     *
     * @return bool
     */
    public function is_downloadable() {
        return false;
    }

    /**
     * Check if product can be purchased.
     *
     * @return bool
     */
    public function is_purchasable() {
        $purchasable = $this->exists() && $this->get_status() === 'publish';

        if ($purchasable) {
            $schedules = ScheduleManager::getSchedules($this->get_id());
            $purchasable = !empty($schedules);
        }

        return apply_filters('woocommerce_is_purchasable', $purchasable, $this);
    }

    /**
     * Check if product needs shipping
     *
     * @return bool
     */
    public function needs_shipping() {
        return false;
    }

    /**
     * Get experience duration
     *
     * @return int Duration in minutes
     */
    public function get_duration() {
        $schedules = ScheduleManager::getSchedules($this->get_id());
        return isset($schedules[0]) ? (int) $schedules[0]->duration_min : 0;
    }

    /**
     * Get experience capacity
     *
     * @return int Maximum capacity
     */
    public function get_capacity() {
        $schedules = ScheduleManager::getSchedules($this->get_id());
        return isset($schedules[0]) ? (int) $schedules[0]->capacity : 0;
    }

    /**
     * Get adult price
     *
     * @return float Adult price
     */
    public function get_adult_price() {
        $schedules = ScheduleManager::getSchedules($this->get_id());
        return isset($schedules[0]) ? (float) $schedules[0]->price_adult : 0.0;
    }

    /**
     * Get child price
     *
     * @return float Child price
     */
    public function get_child_price() {
        $schedules = ScheduleManager::getSchedules($this->get_id());
        return isset($schedules[0]) ? (float) $schedules[0]->price_child : 0.0;
    }

    /**
     * Get adult tax class
     *
     * @return string Adult tax class
     */
    public function get_adult_tax_class() {
        return '';
    }

    /**
     * Get child tax class
     *
     * @return string Child tax class
     */
    public function get_child_tax_class() {
        return '';
    }

    /**
     * Get available languages
     *
     * @return string Available languages
     */
    public function get_languages() {
        $schedules = ScheduleManager::getSchedules($this->get_id());
        $langs = array_unique(array_filter(array_map(static function ($s) {
            return $s->lang;
        }, $schedules)));
        return implode(', ', $langs);
    }

    /**
     * Get default meeting point ID
     *
     * @return int Meeting point ID
     */
    public function get_default_meeting_point() {
        $schedules = ScheduleManager::getSchedules($this->get_id());
        return isset($schedules[0]) ? (int) $schedules[0]->meeting_point_id : 0;
    }

    /**
     * Get tax-aware adult price
     *
     * @param string $context Context for display (view, edit)
     * @return float Adult price with tax
     */
    public function get_adult_price_with_tax($context = 'view') {
        $price = $this->get_adult_price();
        $tax_class = $this->get_adult_tax_class();
        
        // Use WooCommerce function to get price including tax if needed
        return wc_get_price_to_display($this, [
            'price' => $price,
            'tax_class' => $tax_class
        ]);
    }

    /**
     * Get tax-aware child price
     *
     * @param string $context Context for display (view, edit)
     * @return float Child price with tax
     */
    public function get_child_price_with_tax($context = 'view') {
        $price = $this->get_child_price();
        $tax_class = $this->get_child_tax_class();
        
        // Use WooCommerce function to get price including tax if needed
        return wc_get_price_to_display($this, [
            'price' => $price,
            'tax_class' => $tax_class
        ]);
    }

    /**
     * Check if this is an event (fixed date) vs experience (recurring)
     *
     * @return bool True if event, false if experience
     */
    public function is_event(): bool {
        return get_post_meta($this->get_id(), '_fp_experience_type', true) === 'event';
    }

    /**
     * Get experience type
     *
     * @return string 'event' or 'experience'
     */
    public function get_experience_type(): string {
        return get_post_meta($this->get_id(), '_fp_experience_type', true) ?: 'experience';
    }
}