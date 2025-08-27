<?php
/**
 * Voucher Redemption Form Template
 *
 * @package FP\Esperienze
 * @var string $cart_item_key Cart item key
 * @var int $product_id Product ID
 * @var array|null $applied_voucher Applied voucher data
 */

defined('ABSPATH') || exit;

$has_voucher = !empty($applied_voucher);
?>

<div class="fp-voucher-form" data-product-id="<?php echo esc_attr($product_id); ?>" data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>">
    <?php wp_nonce_field('fp_voucher_nonce', 'fp_voucher_nonce', false); ?>
    <div class="fp-voucher-header">
        <h4><?php esc_html_e('Have a voucher?', 'fp-esperienze'); ?></h4>
    </div>
    
    <div class="fp-voucher-input-group">
        <input 
            type="text" 
            class="fp-voucher-code-input" 
            placeholder="<?php esc_attr_e('Enter voucher code', 'fp-esperienze'); ?>"
            value="<?php echo $has_voucher ? esc_attr($applied_voucher['code']) : ''; ?>"
            <?php echo $has_voucher ? 'readonly' : ''; ?>
        />
        <button 
            type="button" 
            class="fp-apply-voucher-btn fp-btn fp-btn-secondary"
            <?php echo $has_voucher ? 'style="display:none"' : ''; ?>
        >
            <?php esc_html_e('Apply', 'fp-esperienze'); ?>
        </button>
        <button 
            type="button" 
            class="fp-remove-voucher-btn fp-btn fp-btn-outline"
            <?php echo !$has_voucher ? 'style="display:none"' : ''; ?>
        >
            <?php esc_html_e('Remove', 'fp-esperienze'); ?>
        </button>
    </div>
    
    <div class="fp-voucher-status" <?php echo !$has_voucher ? 'style="display:none"' : ''; ?>>
        <?php if ($has_voucher): ?>
            <span class="fp-voucher-applied success">
                <i class="dashicons dashicons-yes-alt"></i>
                <?php 
                printf(
                    esc_html__('Voucher applied: %s', 'fp-esperienze'),
                    $applied_voucher['amount_type'] === 'full' 
                        ? esc_html__('Full discount', 'fp-esperienze')
                        : sprintf(esc_html__('Up to %s', 'fp-esperienze'), wc_price($applied_voucher['amount']))
                );
                ?>
            </span>
        <?php endif; ?>
    </div>
    
    <div class="fp-voucher-message" style="display:none;"></div>
</div>