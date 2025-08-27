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
}