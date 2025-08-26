<?php
/**
 * Single Experience Product Template
 *
 * This template is used for displaying single experience products.
 * Based on WooCommerce single-product.php but customized for experiences.
 *
 * @package FP\Esperienze
 */

defined('ABSPATH') || exit;

get_header('shop'); ?>

<?php
/**
 * woocommerce_before_main_content hook.
 *
 * @hooked woocommerce_output_content_wrapper - 10 (outputs opening divs for the content)
 * @hooked woocommerce_breadcrumb - 20
 */
do_action('woocommerce_before_main_content');
?>

<div id="primary" class="content-area fp-experience-single">
    <main id="main" class="site-main">
        
        <?php while (have_posts()) : the_post(); ?>
            
            <div class="fp-experience-container">
                
                <?php
                /**
                 * Hook: woocommerce_before_single_product.
                 *
                 * @hooked woocommerce_output_all_notices - 10
                 */
                do_action('woocommerce_before_single_product');
                
                if (post_password_required()) {
                    echo get_the_password_form(); // WPCS: XSS ok.
                    return;
                }
                ?>
                
                <div id="product-<?php the_ID(); ?>" <?php wc_product_class('fp-experience-product', $product); ?>>
                    
                    <div class="fp-experience-layout">
                        
                        <!-- Product Images -->
                        <div class="fp-experience-gallery">
                            <?php
                            /**
                             * Hook: woocommerce_before_single_product_summary.
                             *
                             * @hooked woocommerce_show_product_sale_flash - 10
                             * @hooked woocommerce_show_product_images - 20
                             */
                            do_action('woocommerce_before_single_product_summary');
                            ?>
                        </div>
                        
                        <!-- Product Info and Booking Widget -->
                        <div class="fp-experience-info">
                            
                            <div class="fp-experience-summary">
                                <?php
                                /**
                                 * Hook: woocommerce_single_product_summary.
                                 *
                                 * @hooked woocommerce_template_single_title - 5
                                 * @hooked woocommerce_template_single_rating - 10
                                 * @hooked woocommerce_template_single_price - 10
                                 * @hooked woocommerce_template_single_excerpt - 20
                                 * @hooked FP\Esperienze\Frontend\BookingWidget::display_booking_widget - 25
                                 * @hooked woocommerce_template_single_meta - 40
                                 * @hooked woocommerce_template_single_sharing - 50
                                 */
                                do_action('woocommerce_single_product_summary');
                                ?>
                            </div>
                            
                        </div>
                        
                    </div>
                    
                    <!-- Experience Details Tabs -->
                    <div class="fp-experience-details">
                        <?php
                        /**
                         * Hook: woocommerce_after_single_product_summary.
                         *
                         * @hooked woocommerce_output_product_data_tabs - 10
                         * @hooked woocommerce_upsell_display - 15
                         * @hooked woocommerce_output_related_products - 20
                         */
                        do_action('woocommerce_after_single_product_summary');
                        ?>
                    </div>
                    
                </div>
                
                <?php do_action('woocommerce_after_single_product'); ?>
                
            </div>
            
        <?php endwhile; // end of the loop. ?>
        
    </main><!-- #main -->
</div><!-- #primary -->

<?php
/**
 * woocommerce_after_main_content hook.
 *
 * @hooked woocommerce_output_content_wrapper_end - 10 (outputs closing divs for the content)
 */
do_action('woocommerce_after_main_content');

/**
 * woocommerce_sidebar hook.
 *
 * @hooked woocommerce_get_sidebar - 10
 */
do_action('woocommerce_sidebar');

get_footer('shop');