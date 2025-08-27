<?php
/**
 * WooCommerce Experience Product Class
 *
 * @package FP\Esperienze\ProductType
 */

defined('ABSPATH') || exit;

/**
 * Experience product class
 */
class WC_Product_Experience extends WC_Product {

    /**
     * Product type
     *
     * @var string
     */
    protected $product_type = 'experience';

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
        return (int) get_post_meta($this->get_id(), '_experience_duration', true);
    }

    /**
     * Get experience capacity
     *
     * @return int Maximum capacity
     */
    public function get_capacity() {
        return (int) get_post_meta($this->get_id(), '_experience_capacity', true);
    }

    /**
     * Get adult price
     *
     * @return float Adult price
     */
    public function get_adult_price() {
        return (float) get_post_meta($this->get_id(), '_experience_adult_price', true);
    }

    /**
     * Get child price
     *
     * @return float Child price
     */
    public function get_child_price() {
        return (float) get_post_meta($this->get_id(), '_experience_child_price', true);
    }

    /**
     * Get adult tax class
     *
     * @return string Adult tax class
     */
    public function get_adult_tax_class() {
        return get_post_meta($this->get_id(), '_experience_adult_tax_class', true) ?: '';
    }

    /**
     * Get child tax class
     *
     * @return string Child tax class
     */
    public function get_child_tax_class() {
        return get_post_meta($this->get_id(), '_experience_child_tax_class', true) ?: '';
    }

    /**
     * Get available languages
     *
     * @return string Available languages
     */
    public function get_languages() {
        return get_post_meta($this->get_id(), '_experience_languages', true);
    }

    /**
     * Get default meeting point ID
     *
     * @return int Meeting point ID
     */
    public function get_default_meeting_point() {
        return (int) get_post_meta($this->get_id(), '_fp_exp_meeting_point_id', true);
    }

    /**
     * Get free cancellation time limit in minutes
     *
     * @return int Minutes before experience when free cancellation is allowed
     */
    public function get_free_cancel_until_minutes() {
        return (int) get_post_meta($this->get_id(), '_fp_exp_free_cancel_until_minutes', true) ?: 1440; // Default 24 hours
    }

    /**
     * Get cancellation fee percentage
     *
     * @return float Cancellation fee as percentage (0-100)
     */
    public function get_cancellation_fee_percentage() {
        return (float) get_post_meta($this->get_id(), '_fp_exp_cancellation_fee_percentage', true);
    }

    /**
     * Get no-show policy
     *
     * @return string No-show policy ('no_refund', 'partial_refund', 'full_refund')
     */
    public function get_no_show_policy() {
        return get_post_meta($this->get_id(), '_fp_exp_no_show_policy', true) ?: 'no_refund';
    }

    /**
     * Get cutoff time in minutes for booking changes
     *
     * @return int Minutes before experience when changes are no longer allowed
     */
    public function get_cutoff_minutes() {
        return (int) get_post_meta($this->get_id(), '_fp_exp_cutoff_minutes', true) ?: 120; // Default 2 hours
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
}