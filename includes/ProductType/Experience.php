<?php
/**
 * Experience Product Type
 *
 * @package FP\Esperienze\ProductType
 */

namespace FP\Esperienze\ProductType;

use FP\Esperienze\Data\ScheduleManager;
use FP\Esperienze\Data\OverrideManager;
use FP\Esperienze\Data\MeetingPointManager;
use FP\Esperienze\Data\ExtraManager;
use FP\Esperienze\Data\DynamicPricingManager;
use FP\Esperienze\Helpers\ScheduleHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Experience product type class
 */
class Experience {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Load the WC_Product_Experience class immediately to ensure it's available
		$this->loadProductClass();

		// Register product type filter IMMEDIATELY if WooCommerce is available
		// This fixes the timing issue where the filter was registered too late
		if ( function_exists( 'wc_get_product_types' ) ) {
                       add_filter( 'product_type_selector', array( $this, 'addProductType' ), 10 );
		} else {
			// Fallback: register on init hook if WooCommerce isn't ready yet
			add_action( 'init', array( $this, 'registerProductType' ), 5 );
		}
		
		// Register other filters with proper timing
		add_action( 'init', array( $this, 'registerProductFilters' ), 6 );

		// Register admin hooks with proper timing  
		add_action( 'init', array( $this, 'registerAdminHooks' ), 7 );
	}

	/**
	 * Load the WC_Product_Experience class with enhanced validation
	 */
	private function loadProductClass(): void {
		// Check if WooCommerce is available
		if ( ! class_exists( 'WC_Product' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FP Esperienze: WC_Product class not found. WooCommerce may not be active.' );
			}
			return;
		}
		
		// Verify our product class is autoloaded correctly
		if ( ! class_exists( '\FP\Esperienze\ProductType\WC_Product_Experience' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FP Esperienze: WC_Product_Experience class not found. Check autoloader configuration.' );
			}
		}
	}

	/**
	 * Initialize
	 */
	public function init(): void {
		// No manual class loading needed - autoloading handles this
		// This method can be used for any other initialization in the future
	}

	/**
	 * Register product type on init hook with proper timing
	 * This ensures WooCommerce is ready but our type is registered before WC core types
	 */
	public function registerProductType(): void {
		// Only register if WooCommerce is available
		if ( ! function_exists( 'wc_get_product_types' ) ) {
			return;
		}

		// Register the product type selector filter
               add_filter( 'product_type_selector', array( $this, 'addProductType' ), 10 );
	}

	/**
	 * Register other product-related filters
	 * Called after product type registration to ensure proper order
	 */
	public function registerProductFilters(): void {
		// Only register if WooCommerce is available
		if ( ! function_exists( 'wc_get_product_types' ) ) {
			return;
		}

		// Register product class and data store filters
		add_filter( 'woocommerce_product_class', array( $this, 'getProductClass' ), 10, 2 );
		add_filter( 'woocommerce_data_stores', array( $this, 'registerDataStore' ), 10, 1 );
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'FP Esperienze: Experience product filters registered on init hook' );
		}
	}

	/**
	 * Register admin-related hooks
	 * Called after core filters to ensure proper integration
	 */
	public function registerAdminHooks(): void {
		// Only register admin hooks if WooCommerce is available
		if ( ! function_exists( 'wc_get_product_types' ) ) {
			return;
		}

		// Register admin interface hooks
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'addProductDataTabs' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'addProductDataPanels' ) );

		// Hook into product type saving with higher priority and multiple hooks
		add_action( 'woocommerce_process_product_meta', array( $this, 'saveProductData' ), 20 );
		// Also hook into the product save process to ensure type is preserved
		add_action( 'woocommerce_update_product', array( $this, 'ensureProductType' ), 5 );
		add_action( 'woocommerce_new_product', array( $this, 'ensureProductType' ), 5 );

		add_action( 'admin_notices', array( $this, 'showScheduleValidationNotices' ) );

		// Additional hooks for proper WooCommerce integration
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'addExperienceProductFields' ) );

		// Ensure admin scripts are loaded on product edit pages
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAdminScripts' ) );
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'FP Esperienze: Experience admin hooks registered on init hook' );
		}
	}

	/**
	 * Add experience to product type selector
	 *
	 * @param array $types Product types
	 * @return array
	 */
	public function addProductType( array $types ): array {
		$types['experience'] = __( 'Experience', 'fp-esperienze' );
		return $types;
	}

	/**
	 * Register data store for experience products
	 *
	 * @param array $stores Data stores
	 * @return array
	 */
	public function registerDataStore( array $stores ): array {
		$stores['product-experience'] = 'WC_Product_Data_Store_CPT';
		return $stores;
	}

	/**
	 * Get product class for experience products
	 *
	 * @param string $classname Current class name
	 * @param string $product_type Product type
	 * @return string
	 */
	public function getProductClass( string $classname, string $product_type ): string {
		if ( $product_type === 'experience' ) {
			$experience_class = '\FP\Esperienze\ProductType\WC_Product_Experience';
			
			// Verify the class exists before returning it
			if ( class_exists( $experience_class ) ) {
				return $experience_class;
			} else {
				// Log the error and fall back to default
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'FP Esperienze: Experience product class not found: ' . $experience_class );
				}
				// Return the default WC_Product class as fallback
				return 'WC_Product';
			}
		}
		return $classname;
	}

	/**
	 * Add product data tabs
	 *
	 * @param array $tabs Product data tabs
	 * @return array
	 */
	public function addProductDataTabs( array $tabs ): array {
		$tabs['experience']      = array(
			'label'  => __( 'Experience', 'fp-esperienze' ),
			'target' => 'experience_product_data',
			'class'  => array( 'show_if_experience' ),
		);
		$tabs['dynamic_pricing'] = array(
			'label'  => __( 'Dynamic Pricing', 'fp-esperienze' ),
			'target' => 'dynamic_pricing_product_data',
			'class'  => array( 'show_if_experience' ),
		);
		return $tabs;
	}

	/**
	 * Add product data panels
	 */
        public function addProductDataPanels(): void {
                global $post;

                $gallery_images = get_post_meta( $post->ID, '_fp_exp_gallery_images', true );
                if ( ! is_array( $gallery_images ) ) {
			$gallery_images = array();
		}
		$gallery_images = array_values(
			array_filter(
				array_map( 'absint', $gallery_images )
			)
		);

                ?>
                        <div id="experience_product_data" class="panel woocommerce_options_panel">
                                <?php wp_nonce_field( 'fp_esperienze_save', 'fp_esperienze_nonce' ); ?>

                                <?php
                                $sections = array(
                                        array(
                                                'id'          => 'fp-experience-basics',
                                                'title'       => __( 'Experience basics', 'fp-esperienze' ),
                                                'nav_classes' => array(),
                                                'callback'    => function () use ( $post ) {
                                                        $this->renderExperienceBasicsSection( $post );
                                                },
                                                'args'        => array(
                                                        'summary' => __( 'Capture the core product details, defaults, and marketplace settings for your tour.', 'fp-esperienze' ),
                                                ),
                                        ),
                                        array(
                                                'id'          => 'fp-experience-content',
                                                'title'       => __( 'Content & media', 'fp-esperienze' ),
                                                'nav_classes' => array(),
                                                'callback'    => function () use ( $post, $gallery_images ) {
                                                        $this->renderExperienceContentSection( $post, $gallery_images );
                                                },
                                                'args'        => array(
                                                        'summary' => __( 'Add compelling descriptions, highlights, and gallery imagery to inspire bookings.', 'fp-esperienze' ),
                                                ),
                                        ),
                                        array(
                                                'id'          => 'fp-experience-policies',
                                                'title'       => __( 'Policies', 'fp-esperienze' ),
                                                'nav_classes' => array(),
                                                'callback'    => function () use ( $post ) {
                                                        $this->renderExperiencePoliciesSection( $post );
                                                },
                                                'args'        => array(
                                                        'summary' => __( 'Define cancellation rules, requirements, and important notices for guests.', 'fp-esperienze' ),
                                                ),
                                        ),
                                        array(
                                                'id'          => 'fp-recurring-schedules',
                                                'title'       => __( 'Recurring schedule', 'fp-esperienze' ),
                                                'nav_classes' => array( 'fp-experience-nav__item--recurring-only' ),
                                                'nav_badge'   => array(
                                                        'type'           => 'count',
                                                        'selector'       => '.fp-time-slot-card-clean, .fp-time-slot-row',
                                                        'watch'          => '#fp-time-slots-container',
                                                        'singular_label' => __( 'slot configured', 'fp-esperienze' ),
                                                        'plural_label'   => __( 'slots configured', 'fp-esperienze' ),
                                                        'empty_label'    => __( 'No slots configured yet', 'fp-esperienze' ),
                                                        'empty_visible'  => '–',
                                                ),
                                                'callback'    => function () use ( $post ) {
                                                        $this->renderExperienceSchedulesSection( $post->ID );
                                                },
                                                'args'        => array(
                                                        'classes' => array( 'fp-schedules-section' ),
                                                        'summary' => __( 'Create repeating departures with capacities, durations, and base pricing.', 'fp-esperienze' ),
                                                ),
                                        ),
                                        array(
                                                'id'          => 'fp-event-schedules',
                                                'title'       => __( 'Event dates & times', 'fp-esperienze' ),
                                                'nav_classes' => array( 'fp-experience-nav__item--event-only' ),
                                                'nav_badge'   => array(
                                                        'type'           => 'count',
                                                        'selector'       => '.fp-event-date-card',
                                                        'watch'          => '#fp-event-schedule-builder',
                                                        'singular_label' => __( 'event date', 'fp-esperienze' ),
                                                        'plural_label'   => __( 'event dates', 'fp-esperienze' ),
                                                        'empty_label'    => __( 'No event dates yet', 'fp-esperienze' ),
                                                        'empty_visible'  => '–',
                                                ),
                                                'callback'    => function () use ( $post ) {
                                                        $this->renderExperienceEventSchedulesSection( $post->ID );
                                                },
                                                'args'        => array(
                                                        'classes' => array( 'fp-event-schedules-section', 'fp-hidden' ),
                                                        'summary' => __( 'Plan one-off events with bespoke availability outside of the recurring cadence.', 'fp-esperienze' ),
                                                ),
                                        ),
                                        array(
                                                'id'          => 'fp-overrides-section',
                                                'title'       => __( 'Schedule exceptions', 'fp-esperienze' ),
                                                'nav_classes' => array( 'fp-experience-nav__item--recurring-only' ),
                                                'nav_badge'   => array(
                                                        'type'           => 'count',
                                                        'selector'       => '.fp-override-row',
                                                        'watch'          => '#fp-overrides-container',
                                                        'singular_label' => __( 'override', 'fp-esperienze' ),
                                                        'plural_label'   => __( 'overrides', 'fp-esperienze' ),
                                                        'empty_label'    => __( 'No overrides yet', 'fp-esperienze' ),
                                                        'empty_visible'  => '–',
                                                ),
                                                'callback'    => function () use ( $post ) {
                                                        $this->renderExperienceOverridesSection( $post->ID );
                                                },
                                                'args'        => array(
                                                        'classes' => array( 'fp-overrides-section-wrapper' ),
                                                        'summary' => __( 'Block dates, adjust capacities, or tweak pricing for specific departures.', 'fp-esperienze' ),
                                                ),
                                        ),
                                        array(
                                                'id'          => 'fp-experience-extras',
                                                'title'       => __( 'Extras', 'fp-esperienze' ),
                                                'nav_classes' => array(),
                                                'nav_badge'   => array(
                                                        'type'           => 'checked',
                                                        'selector'       => 'input[name="fp_product_extras[]"]:checked',
                                                        'watch'          => '#fp-extras-container',
                                                        'singular_label' => __( 'extra selected', 'fp-esperienze' ),
                                                        'plural_label'   => __( 'extras selected', 'fp-esperienze' ),
                                                        'empty_label'    => __( 'No extras selected', 'fp-esperienze' ),
                                                        'empty_visible'  => '–',
                                                ),
                                                'callback'    => function () use ( $post ) {
                                                        $this->renderExperienceExtrasSection( $post->ID );
                                                },
                                                'args'        => array(
                                                        'summary' => __( 'Offer optional add-ons with flexible pricing and availability rules.', 'fp-esperienze' ),
                                                ),
                                        ),
                                );
                                ?>

                                <div class="fp-experience-layout">
                                        <nav class="fp-experience-nav" aria-label="<?php esc_attr_e( 'Experience product sections', 'fp-esperienze' ); ?>">
                                                <h2 class="fp-experience-nav__title"><?php esc_html_e( 'Quick navigation', 'fp-esperienze' ); ?></h2>
                                                <ol class="fp-experience-nav__list">
                                                        <?php foreach ( $sections as $section ) : ?>
                                                                <?php
                                                                $nav_classes = array_merge(
                                                                        array( 'fp-experience-nav__item' ),
                                                                        $section['nav_classes']
                                                                );
                                                                $nav_classes = array_map( 'sanitize_html_class', array_filter( $nav_classes ) );
                                                                ?>
                                                                <?php $section_summary = $section['args']['summary'] ?? ''; ?>
                                                                <li class="<?php echo esc_attr( implode( ' ', $nav_classes ) ); ?>" data-section="<?php echo esc_attr( $section['id'] ); ?>">
                                                                        <a class="fp-experience-nav__link" data-section-target="<?php echo esc_attr( $section['id'] ); ?>" href="#<?php echo esc_attr( $section['id'] ); ?>" title="<?php echo esc_attr( trim( $section_summary ) ? $section['title'] . ' — ' . $section_summary : $section['title'] ); ?>">
                                                                                <span class="fp-experience-nav__label">
                                                                                        <span class="fp-experience-nav__label-text"><?php echo esc_html( $section['title'] ); ?></span>
                                                                                        <?php if ( ! empty( $section_summary ) ) : ?>
                                                                                                <span class="fp-experience-nav__hint"><?php echo esc_html( $section_summary ); ?></span>
                                                                                        <?php endif; ?>
                                                                                </span>
                                                                                <?php if ( ! empty( $section['nav_badge'] ) ) :
                                                                                        $badge = $section['nav_badge'];
                                                                                        $badge_attributes = array(
                                                                                                'class="fp-experience-nav__badge"',
                                                                                                'aria-hidden="true"',
                                                                                                'data-badge-type="' . esc_attr( $badge['type'] ) . '"',
                                                                                                'data-target-selector="' . esc_attr( $badge['selector'] ) . '"',
                                                                                                ! empty( $badge['watch'] ) ? 'data-watch-target="' . esc_attr( $badge['watch'] ) . '"' : '',
                                                                                                ! empty( $badge['singular_label'] ) ? 'data-label-singular="' . esc_attr( $badge['singular_label'] ) . '"' : '',
                                                                                                ! empty( $badge['plural_label'] ) ? 'data-label-plural="' . esc_attr( $badge['plural_label'] ) . '"' : '',
                                                                                                ! empty( $badge['empty_label'] ) ? 'data-empty-label="' . esc_attr( $badge['empty_label'] ) . '"' : '',
                                                                                                isset( $badge['empty_visible'] ) ? 'data-empty-visible="' . esc_attr( $badge['empty_visible'] ) . '"' : '',
                                                                                        );
                                                                                        ?>
                                                                                        <span <?php echo implode( ' ', array_filter( $badge_attributes ) ); ?>></span>
                                                                                        <span class="screen-reader-text fp-experience-nav__badge-sr"></span>
                                                                                <?php endif; ?>
                                                                        </a>
                                                                </li>
                                                        <?php endforeach; ?>
                                                </ol>
                                        </nav>

                                        <div class="fp-experience-sections">
                                                <div class="metabox-holder fp-experience-metabox-holder">
                                                        <?php
                                                        foreach ( $sections as $section ) {
                                                                $this->renderExperienceMetabox(
                                                                        $section['id'],
                                                                        $section['title'],
                                                                        $section['callback'],
                                                                        $section['args'] ?? array()
                                                                );
                                                        }
                                                        ?>
                                                </div>
                                        </div>
                                </div>
                        </div>

                        <div id="dynamic_pricing_product_data" class="panel woocommerce_options_panel fp-pricing-panel">
                                <?php $this->renderDynamicPricingPanel( $post->ID ); ?>
                        </div>
                <?php
        }

        /**
         * Render a metabox wrapper that mimics core WordPress styling.
         *
         * @param string   $id       Metabox ID.
         * @param string   $title    Heading text.
         * @param callable $callback Content callback.
         * @param array    $args     Optional extra args.
         */
        private function renderExperienceMetabox( string $id, string $title, callable $callback, array $args = array() ): void {
                $classes = array_merge(
                        array( 'postbox', 'fp-experience-metabox' ),
                        isset( $args['classes'] ) ? (array) $args['classes'] : array()
                );

                $classes = array_map( 'sanitize_html_class', array_filter( $classes ) );
                $heading_id = $id . '-title';

                ?>
                $summary = isset( $args['summary'] ) ? wp_strip_all_tags( $args['summary'] ) : '';

                ?>
                <div id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-nav-section="<?php echo esc_attr( $id ); ?>" tabindex="-1" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>"<?php echo $summary ? ' data-section-summary="' . esc_attr( $summary ) . '"' : ''; ?>>
                        <h2 class="hndle" id="<?php echo esc_attr( $heading_id ); ?>"><span><?php echo esc_html( $title ); ?></span></h2>
                        <div class="inside">
                                <?php if ( $summary ) : ?>
                                        <p class="fp-experience-metabox__summary"><?php echo esc_html( $summary ); ?></p>
                                <?php endif; ?>
                                <?php call_user_func( $callback ); ?>
                        </div>
                </div>
                <?php
        }

        /**
         * Render basic configuration fields for the experience product.
         */
        private function renderExperienceBasicsSection( \WP_Post $post ): void {
                woocommerce_wp_select(
                        array(
                                'id'          => '_fp_experience_type',
                                'label'       => __( 'Scheduling mode', 'fp-esperienze' ),
                                'options'     => array(
                                        'experience' => __( 'Recurring schedule', 'fp-esperienze' ),
                                        'event'      => __( 'Fixed date event', 'fp-esperienze' ),
                                ),
                                'desc_tip'    => true,
                                'description' => __( 'Pick how customers can book this product: a repeating weekly schedule or individual fixed dates.', 'fp-esperienze' ),
                                'value'       => get_post_meta( $post->ID, '_fp_experience_type', true ) ?: 'experience',
                        )
                );

                woocommerce_wp_text_input(
                        array(
                                'id'                => '_fp_exp_cutoff_minutes',
                                'label'             => __( 'Booking Cutoff (minutes)', 'fp-esperienze' ),
                                'placeholder'       => '120',
                                'desc_tip'          => true,
                                'description'       => __( 'Minimum minutes before experience start time to allow bookings', 'fp-esperienze' ),
                                'type'              => 'number',
                                'value'             => get_post_meta( $post->ID, '_fp_exp_cutoff_minutes', true ),
                                'custom_attributes' => array(
                                        'step' => '1',
                                        'min'  => '0',
                                ),
                        )
                );

                $reviews_enabled_meta = get_post_meta( $post->ID, '_fp_exp_enable_reviews', true );
                if ( '' === $reviews_enabled_meta ) {
                        $reviews_enabled_meta = 'yes';
                }

                // Always submit an explicit value so the setting can be turned off reliably.
                echo '<input type="hidden" name="_fp_exp_enable_reviews" value="no" />';

                woocommerce_wp_checkbox(
                        array(
                                'id'          => '_fp_exp_enable_reviews',
                                'label'       => __( 'Enable Reviews Section', 'fp-esperienze' ),
                                'description' => __( 'Show the reviews section on the experience page and related APIs.', 'fp-esperienze' ),
                                'value'       => 'no' === $reviews_enabled_meta ? 'no' : 'yes',
                                'cbvalue'     => 'yes',
                        )
                );
        }

        /**
         * Render content-related fields including the gallery selector.
         */
        private function renderExperienceContentSection( \WP_Post $post, array $gallery_images ): void {
                woocommerce_wp_textarea_input(
                        array(
                                'id'          => '_fp_exp_included',
                                'label'       => __( "What's Included", 'fp-esperienze' ),
                                'placeholder' => __( "Professional guide\nAll activities as described\nSmall group experience", 'fp-esperienze' ),
                                'desc_tip'    => true,
                                'description' => __( 'List what is included in the experience (one item per line)', 'fp-esperienze' ),
                                'rows'        => 5,
                                'value'       => get_post_meta( $post->ID, '_fp_exp_included', true ),
                        )
                );

                woocommerce_wp_textarea_input(
                        array(
                                'id'          => '_fp_exp_excluded',
                                'label'       => __( "What's Not Included", 'fp-esperienze' ),
                                'placeholder' => __( "Hotel pickup and drop-off\nFood and drinks\nPersonal expenses\nGratuities", 'fp-esperienze' ),
                                'desc_tip'    => true,
                                'description' => __( 'List what is not included in the experience (one item per line)', 'fp-esperienze' ),
                                'rows'        => 5,
                                'value'       => get_post_meta( $post->ID, '_fp_exp_excluded', true ),
                        )
                );

                $this->renderExperienceGalleryField( $gallery_images, $post );
        }

        /**
         * Render the policy fields block.
         */
        private function renderExperiencePoliciesSection( \WP_Post $post ): void {
                echo '<p class="description">' . esc_html__( 'Define cancellation timing, fees and no-show policy for the product.', 'fp-esperienze' ) . '</p>';

                woocommerce_wp_text_input(
                        array(
                                'id'                => '_fp_exp_free_cancel_until_minutes',
                                'label'             => __( 'Free Cancellation Until (minutes)', 'fp-esperienze' ),
                                'placeholder'       => '1440',
                                'desc_tip'          => true,
                                'description'       => __( 'Minutes before experience start when customers can cancel for free (e.g., 1440 = 24 hours)', 'fp-esperienze' ),
                                'type'              => 'number',
                                'value'             => get_post_meta( $post->ID, '_fp_exp_free_cancel_until_minutes', true ),
                                'custom_attributes' => array(
                                        'step' => '1',
                                        'min'  => '0',
                                ),
                        )
                );

                woocommerce_wp_text_input(
                        array(
                                'id'                => '_fp_exp_cancel_fee_percent',
                                'label'             => __( 'Cancellation Fee (%)', 'fp-esperienze' ),
                                'placeholder'       => '20',
                                'desc_tip'          => true,
                                'description'       => __( 'Percentage of total price to charge as cancellation fee after free cancellation period', 'fp-esperienze' ),
                                'type'              => 'number',
                                'value'             => get_post_meta( $post->ID, '_fp_exp_cancel_fee_percent', true ),
                                'custom_attributes' => array(
                                        'step' => '0.01',
                                        'min'  => '0',
                                        'max'  => '100',
                                ),
                        )
                );

                woocommerce_wp_select(
                        array(
                                'id'          => '_fp_exp_no_show_policy',
                                'label'       => __( 'No-Show Policy', 'fp-esperienze' ),
                                'options'     => array(
                                        'no_refund'      => __( 'No refund', 'fp-esperienze' ),
                                        'partial_refund' => __( 'Partial refund (use cancellation fee %)', 'fp-esperienze' ),
                                        'full_refund'    => __( 'Full refund', 'fp-esperienze' ),
                                ),
                                'desc_tip'    => true,
                                'description' => __( 'Policy for customers who do not show up for their experience', 'fp-esperienze' ),
                                'value'       => get_post_meta( $post->ID, '_fp_exp_no_show_policy', true ),
                        )
                );
        }

        /**
         * Render the gallery control.
         *
         * @param array    $gallery_images Attachment IDs.
         * @param \WP_Post $post           Product post.
         */
        private function renderExperienceGalleryField( array $gallery_images, \WP_Post $post ): void {
                ?>
                <div class="form-field fp-exp-gallery-field__wrapper">
                        <label for="fp-exp-gallery-list"><?php esc_html_e( 'Experience gallery', 'fp-esperienze' ); ?></label>
                        <div class="fp-exp-gallery-actions wp-clearfix">
                                <button type="button" class="button button-secondary fp-exp-gallery-add"><?php esc_html_e( 'Add images', 'fp-esperienze' ); ?></button>
                                <button type="button" class="button-link fp-exp-gallery-clear<?php echo empty( $gallery_images ) ? ' fp-hidden' : ''; ?>"><?php esc_html_e( 'Remove all', 'fp-esperienze' ); ?></button>
                        </div>
                        <p class="description"><?php esc_html_e( 'Select the media items that will build the gallery on the Experience page. Drag thumbnails to change their order and use Remove to delete a slide.', 'fp-esperienze' ); ?></p>
                        <div class="fp-exp-gallery-field" id="fp-exp-gallery-field">
                                <ul class="fp-exp-gallery-list" id="fp-exp-gallery-list">
                                        <?php foreach ( $gallery_images as $attachment_id ) : ?>
                                                <?php
                                                $attachment_id = absint( $attachment_id );
                                                if ( 0 === $attachment_id ) {
                                                        continue;
                                                }

                                                $thumbnail_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
                                                if ( ! $thumbnail_url ) {
                                                        $thumbnail_url = wp_get_attachment_image_url( $attachment_id, 'medium' );
                                                }
                                                if ( ! $thumbnail_url ) {
                                                        $thumbnail_url = wp_get_attachment_image_url( $attachment_id, 'large' );
                                                }

                                                if ( ! $thumbnail_url ) {
                                                        continue;
                                                }

                                                $alt_text = trim( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
                                                if ( '' === $alt_text ) {
                                                        $alt_text = get_the_title( $post->ID );
                                                }
                                                ?>
                                                <li class="fp-exp-gallery-item" data-attachment-id="<?php echo esc_attr( $attachment_id ); ?>">
                                                        <div class="fp-exp-gallery-item__image">
                                                                <img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="<?php echo esc_attr( $alt_text ); ?>" />
                                                        </div>
                                                        <button type="button" class="button-link-delete fp-exp-gallery-remove" aria-label="<?php esc_attr_e( 'Remove image', 'fp-esperienze' ); ?>">&times;</button>
                                                        <input type="hidden" name="_fp_exp_gallery_images[]" value="<?php echo esc_attr( $attachment_id ); ?>" />
                                                </li>
                                        <?php endforeach; ?>
                                </ul>
                                <p class="fp-exp-gallery-empty<?php echo ! empty( $gallery_images ) ? ' fp-hidden' : ''; ?>"><?php esc_html_e( 'No gallery images selected yet. Use “Add images” to pick them from the media library.', 'fp-esperienze' ); ?></p>
                                <span class="screen-reader-text fp-exp-gallery-status" aria-live="polite"></span>
                        </div>
                </div>
                <?php
        }

        /**
         * Render the recurring schedules section.
         */
        private function renderExperienceSchedulesSection( int $product_id ): void {
                ?>
                <p class="description"><?php _e( 'Create the weekly pattern for this experience. Add the start time, days and availability for each repeating slot below.', 'fp-esperienze' ); ?></p>

                <div id="fp-schedule-builder-container" class="fp-schedule-builder-wrapper">
                        <?php $this->renderScheduleBuilder( $product_id ); ?>
                </div>

                <?php if ( apply_filters( 'fp_esperienze_enable_raw_schedules', false ) ) : ?>
                        <div id="fp-schedule-raw-container" class="fp-schedule-raw-container fp-hidden">
                                <h3><?php _e( 'Advanced Mode (Raw Schedules)', 'fp-esperienze' ); ?></h3>
                                <div id="fp-schedules-container">
                                        <?php $this->renderSchedulesSection( $product_id ); ?>
                                </div>
                                <button type="button" class="button button-secondary" id="fp-add-schedule">
                                        <?php _e( 'Add Schedule', 'fp-esperienze' ); ?>
                                </button>
                        </div>

                        <p class="fp-toggle-raw-mode">
                                <label>
                                        <input type="checkbox" id="fp-toggle-raw-mode">
                                        <?php _e( 'Show Advanced Mode', 'fp-esperienze' ); ?>
                                </label>
                                <span class="description"><?php _e( 'Enable to view/edit individual schedule rows directly', 'fp-esperienze' ); ?></span>
                        </p>
                <?php endif; ?>
                <?php
        }

        /**
         * Render the event schedules section.
         */
        private function renderExperienceEventSchedulesSection( int $product_id ): void {
                ?>
                <p class="description"><?php _e( 'Configure specific dates and times for your event. Each event date can have multiple time slots with different settings.', 'fp-esperienze' ); ?></p>

                <div id="fp-event-schedule-container">
                        <?php $this->renderEventScheduleBuilder( $product_id ); ?>
                </div>

                <button type="button" class="button button-primary" id="fp-add-event-schedule">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php _e( 'Add Event Date', 'fp-esperienze' ); ?>
                </button>
                <?php
        }

        /**
         * Render the overrides section.
         */
        private function renderExperienceOverridesSection( int $product_id ): void {
                ?>
                <p class="description"><?php _e( 'Handle special dates here — close sales, tweak capacity or adjust pricing for single days without touching your recurring plan.', 'fp-esperienze' ); ?></p>

                <div id="fp-overrides-container">
                        <?php $this->renderOverridesSection( $product_id ); ?>
                </div>
                <button type="button" class="button button-primary fp-add-override" id="fp-add-override">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php _e( 'Add Date Override', 'fp-esperienze' ); ?>
                </button>
                <?php
        }

        /**
         * Render the extras section.
         */
        private function renderExperienceExtrasSection( int $product_id ): void {
                ?>
                <div id="fp-extras-container">
                        <?php $this->renderExtrasSection( $product_id ); ?>
                </div>
                <?php
        }

        /**
         * Render schedules section
         *
         * @param int $product_id Product ID
         */
	private function renderSchedulesSection( int $product_id ): void {
		$schedules      = ScheduleManager::getSchedules( $product_id );
		$meeting_points = $this->getMeetingPoints();

		foreach ( $schedules as $index => $schedule ) {
			$this->renderScheduleRow( $schedule, $index, $meeting_points );
		}
	}

	/**
	 * Render a single schedule row
	 *
	 * @param object $schedule Schedule object
	 * @param int    $index Row index
	 * @param array  $meeting_points Meeting points options
	 */
	private function renderScheduleRow( $schedule, int $index, array $meeting_points ): void {
		$days = array(
			0 => __( 'Sunday', 'fp-esperienze' ),
			1 => __( 'Monday', 'fp-esperienze' ),
			2 => __( 'Tuesday', 'fp-esperienze' ),
			3 => __( 'Wednesday', 'fp-esperienze' ),
			4 => __( 'Thursday', 'fp-esperienze' ),
			5 => __( 'Friday', 'fp-esperienze' ),
			6 => __( 'Saturday', 'fp-esperienze' ),
		);

                ?>
                <div class="fp-schedule-row" data-index="<?php echo esc_attr( $index ); ?>">
                        <input type="hidden" name="schedules[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $schedule->id ?? '' ); ?>">

                        <div class="fp-schedule-row__grid">
                                <div class="fp-schedule-row__field form-field">
                                        <label>
                                                <span class="fp-field-label-text">
                                                        <?php _e( 'Day of Week', 'fp-esperienze' ); ?>
                                                        <span class="fp-required-indicator">*</span>
                                                </span>
                                                <span class="woocommerce-help-tip" data-tip="<?php echo esc_attr__( 'Which day of the week this schedule applies to', 'fp-esperienze' ); ?>"></span>
                                        </label>
                                        <select name="schedules[<?php echo esc_attr( $index ); ?>][day_of_week]" required>
                                                <option value=""><?php _e( 'Select Day', 'fp-esperienze' ); ?></option>
                                                <?php foreach ( $days as $value => $label ) : ?>
                                                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $schedule->day_of_week ?? '', $value ); ?>>
                                                                <?php echo esc_html( $label ); ?>
                                                        </option>
                                                <?php endforeach; ?>
                                        </select>
                                </div>

                                <div class="fp-schedule-row__field form-field">
                                        <label>
                                                <span class="fp-field-label-text">
                                                        <?php _e( 'Start Time', 'fp-esperienze' ); ?>
                                                        <span class="fp-required-indicator">*</span>
                                                </span>
                                                <span class="woocommerce-help-tip" data-tip="<?php echo esc_attr__( 'When the experience starts (24-hour format)', 'fp-esperienze' ); ?>"></span>
                                        </label>
                                        <input type="time"
                                                        name="schedules[<?php echo esc_attr( $index ); ?>][start_time]"
                                                        value="<?php echo esc_attr( $schedule->start_time ?? '' ); ?>"
                                                        required
                                                        title="<?php esc_attr_e( 'Experience start time', 'fp-esperienze' ); ?>">
                                </div>

                                <div class="fp-schedule-row__field form-field">
                                        <label>
                                                <span class="fp-field-label-text">
                                                        <?php _e( 'Duration (minutes)', 'fp-esperienze' ); ?>
                                                        <span class="fp-required-indicator">*</span>
                                                </span>
                                                <span class="woocommerce-help-tip" data-tip="<?php echo esc_attr__( 'How long the experience lasts in minutes', 'fp-esperienze' ); ?>"></span>
                                        </label>
                                        <input type="number"
                                                        name="schedules[<?php echo esc_attr( $index ); ?>][duration_min]"
                                                        value="<?php echo esc_attr( $schedule->duration_min ?? 60 ); ?>"
                                                        min="1"
                                                        step="1"
                                                        required
                                                        title="<?php esc_attr_e( 'Duration in minutes (minimum 1)', 'fp-esperienze' ); ?>">
                                </div>

                                <div class="fp-schedule-row__field form-field">
                                        <label>
                                                <span class="fp-field-label-text">
                                                        <?php _e( 'Max Capacity', 'fp-esperienze' ); ?>
                                                        <span class="fp-required-indicator">*</span>
                                                </span>
                                                <span class="woocommerce-help-tip" data-tip="<?php echo esc_attr__( 'Maximum number of participants for this schedule', 'fp-esperienze' ); ?>"></span>
                                        </label>
                                        <input type="number"
                                                        name="schedules[<?php echo esc_attr( $index ); ?>][capacity]"
                                                        value="<?php echo esc_attr( $schedule->capacity ?? 10 ); ?>"
                                                        min="1"
                                                        step="1"
                                                        required
                                                        title="<?php esc_attr_e( 'Maximum participants (minimum 1)', 'fp-esperienze' ); ?>">
                                </div>
                        </div>

                        <div class="fp-schedule-row__grid">
                                <div class="fp-schedule-row__field form-field">
                                        <label>
                                                <span class="fp-field-label-text">
                                                        <?php _e( 'Language', 'fp-esperienze' ); ?>
                                                        <span class="fp-required-indicator">*</span>
                                                </span>
                                                <span class="woocommerce-help-tip" data-tip="<?php echo esc_attr__( 'Experience language code (e.g., en, it, es)', 'fp-esperienze' ); ?>"></span>
                                        </label>
                                        <input type="text"
                                                        name="schedules[<?php echo esc_attr( $index ); ?>][lang]"
                                                        value="<?php echo esc_attr( $schedule->lang ?? 'en' ); ?>"
                                                        maxlength="10"
                                                        required
                                                        title="<?php esc_attr_e( 'Language code (ISO format preferred)', 'fp-esperienze' ); ?>">
                                </div>

                                <div class="fp-schedule-row__field form-field">
                                        <label>
                                                <span class="fp-field-label-text">
                                                        <?php _e( 'Meeting Point', 'fp-esperienze' ); ?>
                                                        <span class="fp-required-indicator">*</span>
                                                </span>
                                                <span class="woocommerce-help-tip" data-tip="<?php echo esc_attr__( 'Where participants should meet for this experience', 'fp-esperienze' ); ?>"></span>
                                        </label>
                                        <select name="schedules[<?php echo esc_attr( $index ); ?>][meeting_point_id]" required>
                                                <option value=""><?php _e( 'Select meeting point', 'fp-esperienze' ); ?></option>
                                                <?php foreach ( $meeting_points as $value => $label ) : ?>
                                                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $schedule->meeting_point_id ?? '', $value ); ?>>
                                                                <?php echo esc_html( $label ); ?>
                                                        </option>
                                                <?php endforeach; ?>
                                        </select>
                                </div>

                                <div class="fp-schedule-row__field form-field">
                                        <label>
                                                <span class="fp-field-label-text">
                                                        <?php _e( 'Adult Price', 'fp-esperienze' ); ?>
                                                        <span class="fp-required-indicator">*</span>
                                                </span>
                                                <span class="woocommerce-help-tip" data-tip="<?php echo esc_attr__( 'Price per adult participant', 'fp-esperienze' ); ?>"></span>
                                        </label>
                                        <input type="number"
                                                        name="schedules[<?php echo esc_attr( $index ); ?>][price_adult]"
                                                        value="<?php echo esc_attr( $schedule->price_adult ?? '' ); ?>"
                                                        min="0"
                                                        step="0.01"
                                                        required
                                                        title="<?php esc_attr_e( 'Adult price', 'fp-esperienze' ); ?>">
                                </div>

                                <div class="fp-schedule-row__field form-field">
                                        <label>
                                                <span class="fp-field-label-text">
                                                        <?php _e( 'Child Price', 'fp-esperienze' ); ?>
                                                        <span class="fp-required-indicator">*</span>
                                                </span>
                                                <span class="woocommerce-help-tip" data-tip="<?php echo esc_attr__( 'Price per child participant', 'fp-esperienze' ); ?>"></span>
                                        </label>
                                        <input type="number"
                                                        name="schedules[<?php echo esc_attr( $index ); ?>][price_child]"
                                                        value="<?php echo esc_attr( $schedule->price_child ?? '' ); ?>"
                                                        min="0"
                                                        step="0.01"
                                                        required
                                                        title="<?php esc_attr_e( 'Child price', 'fp-esperienze' ); ?>">
                                </div>
                        </div>

                        <div class="fp-schedule-row__actions">
                                <button type="button" class="button button-link-delete fp-remove-schedule">
                                        <span class="dashicons dashicons-trash"></span>
                                        <?php _e( 'Remove Schedule', 'fp-esperienze' ); ?>
                                </button>
                        </div>
                </div>
                <?php
	}

	/**
	 * Render the schedule builder UI
	 *
	 * @param int $product_id Product ID
	 */
	private function renderScheduleBuilder( int $product_id ): void {
		$schedules      = ScheduleManager::getRecurringSchedules( $product_id );
		$meeting_points = $this->getMeetingPoints();

                // Default placeholders when slot values are missing
                $default_duration      = '60';
                $default_capacity      = '10';
                $default_language      = 'en';
                $default_meeting_point = '';
                $default_price_adult   = '0.00';
                $default_price_child   = '0.00';

		// Aggregate existing schedules for builder view
		$aggregated = ScheduleHelper::aggregateSchedulesForBuilder( $schedules, $product_id );

		$days = array(
			1 => __( 'Monday', 'fp-esperienze' ),
			2 => __( 'Tuesday', 'fp-esperienze' ),
			3 => __( 'Wednesday', 'fp-esperienze' ),
			4 => __( 'Thursday', 'fp-esperienze' ),
			5 => __( 'Friday', 'fp-esperienze' ),
			6 => __( 'Saturday', 'fp-esperienze' ),
			0 => __( 'Sunday', 'fp-esperienze' ),
		);

		?>
		<div id="fp-schedule-builder" class="fp-schedule-builder-refactored">
			<!-- Summary table -->
			<?php $this->renderSlotsSummaryTable( $aggregated['time_slots'], $days ); ?>
			
			<!-- Time slots container with clean structure -->
			<div id="fp-time-slots-container" class="fp-time-slots-container-clean">
				<?php if ( empty( $aggregated['time_slots'] ) ) : ?>
					<div class="fp-empty-slots-message">
						<p><?php _e( 'No time slots configured yet. Add your first time slot below.', 'fp-esperienze' ); ?></p>
					</div>
				<?php else : ?>
					<?php foreach ( $aggregated['time_slots'] as $index => $slot ) : ?>
						<div class="fp-time-slot-card fp-time-slot-card-clean" data-index="<?php echo esc_attr( $index ); ?>">
							<?php $this->renderTimeSlotCardClean( $slot, $index, $days, $meeting_points, $default_duration, $default_capacity, $default_language, $default_meeting_point, $default_price_adult, $default_price_child, $product_id ); ?>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
			
                        <button type="button" class="button button-primary fp-add-time-slot" id="fp-add-time-slot">
                                <span class="dashicons dashicons-plus-alt"></span>
                                <?php _e( 'Add Time Slot', 'fp-esperienze' ); ?>
                        </button>
		</div>
		
		<!-- Hidden container for generated schedule inputs -->
                <div id="fp-generated-schedules" class="fp-hidden"></div>
		<?php
	}

	/**
	 * Render a clean time slot card - REFACTORED VERSION
	 */
        private function renderTimeSlotCardClean( $slot, $index, $days, $meeting_points, $default_duration, $default_capacity, $default_language, $default_meeting_point, $default_price_adult, $default_price_child, $product_id ): void {
                $start_time        = isset( $slot['start_time'] ) ? trim( (string) $slot['start_time'] ) : '';
                $day_count         = count( $slot['days'] ?? array() );
                $schedule_id_count = count( $slot['schedule_ids'] ?? array() );
                $meeting_point_id  = $slot['meeting_point_id'] ?? $default_meeting_point;
                $has_missing_fields = '' === $start_time || 0 === $day_count || '' === (string) $meeting_point_id;

                $status_badges = array();

                if ( $has_missing_fields ) {
                        $status_badges[] = array(
                                'label' => __( 'Needs details', 'fp-esperienze' ),
                                'icon'  => 'warning',
                                'class' => 'is-warning',
                        );
                }

                if ( $schedule_id_count > 0 ) {
                        $status_badges[] = array(
                                'label' => sprintf(
                                        _n( '%d active slot', '%d active slots', $schedule_id_count, 'fp-esperienze' ),
                                        $schedule_id_count
                                ),
                                'icon'  => 'yes',
                                'class' => 'is-success',
                        );
                } else {
                        $status_badges[] = array(
                                'label' => __( 'Draft slot', 'fp-esperienze' ),
                                'icon'  => 'clock',
                                'class' => 'is-info',
                        );
                }

                if ( $day_count > 0 ) {
                        $status_badges[] = array(
                                'label' => sprintf(
                                        _n( '%d day selected', '%d days selected', $day_count, 'fp-esperienze' ),
                                        $day_count
                                ),
                                'icon'  => 'calendar-alt',
                                'class' => 'is-info',
                        );
                }

                ?>
                <div class="fp-time-slot-content-clean">
                        <div class="fp-slot-status-badges">
                                <?php foreach ( $status_badges as $badge ) :
                                        $badge_classes = array(
                                                'fp-slot-status-badge',
                                                sanitize_html_class( $badge['class'] )
                                        );
                                        ?>
                                        <span class="<?php echo esc_attr( implode( ' ', $badge_classes ) ); ?>">
                                                <span class="dashicons dashicons-<?php echo esc_attr( $badge['icon'] ); ?>" aria-hidden="true"></span>
                                                <?php echo esc_html( $badge['label'] ); ?>
                                        </span>
                                <?php endforeach; ?>
                        </div>

                        <!-- Time slot header -->
                        <div class="fp-time-slot-header-clean">
                                <div class="fp-time-field-clean form-field fp-field fp-field--time">
                                        <label for="time-<?php echo esc_attr( $index ); ?>">
                                                <span class="dashicons dashicons-clock"></span>
                                                <?php _e( 'Start Time', 'fp-esperienze' ); ?> <span class="required">*</span>
                                        </label>
					<input type="time" 
							id="time-<?php echo esc_attr( $index ); ?>"
							name="builder_slots[<?php echo esc_attr( $index ); ?>][start_time]" 
							value="<?php echo esc_attr( $slot['start_time'] ?? '' ); ?>" 
							required>
				</div>
				
                                <div class="fp-days-field-clean form-field fp-field fp-field--days">
                                        <label>
                                                <span class="dashicons dashicons-calendar-alt"></span>
                                                <?php _e( 'Days of Week', 'fp-esperienze' ); ?> <span class="required">*</span>
                                        </label>
					<div class="fp-days-pills-clean">
						<?php foreach ( $days as $day_value => $day_label ) : ?>
							<div class="fp-day-pill-clean">
								<input type="checkbox" 
										id="day-<?php echo esc_attr( $index ); ?>-<?php echo esc_attr( $day_value ); ?>"
										name="builder_slots[<?php echo esc_attr( $index ); ?>][days][]" 
										value="<?php echo esc_attr( $day_value ); ?>"
										<?php checked( in_array( $day_value, $slot['days'] ?? array() ) ); ?>>
								<label for="day-<?php echo esc_attr( $index ); ?>-<?php echo esc_attr( $day_value ); ?>">
									<?php echo esc_html( substr( $day_label, 0, 3 ) ); ?>
								</label>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				
                                <div class="fp-slot-actions-clean">
                                        <button type="button" class="button button-link-delete fp-remove-time-slot-clean" data-index="<?php echo esc_attr( $index ); ?>" aria-label="<?php esc_attr_e( 'Remove time slot', 'fp-esperienze' ); ?>">
                                                <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                                <?php _e( 'Remove slot', 'fp-esperienze' ); ?>
                                        </button>
                                </div>
			</div>
			
			<!-- Slot settings -->
			<div class="fp-overrides-section-clean">
				<div class="fp-overrides-grid-clean">
					<div class="fp-override-field-clean">
						<label><?php _e( 'Duration (minutes)', 'fp-esperienze' ); ?></label>
						<input type="number"
								name="builder_slots[<?php echo esc_attr( $index ); ?>][duration_min]"
								value="<?php echo esc_attr( $slot['duration_min'] ?? $default_duration ); ?>"
								min="1"
								required>
					</div>

					<div class="fp-override-field-clean">
						<label><?php _e( 'Capacity', 'fp-esperienze' ); ?></label>
						<input type="number"
								name="builder_slots[<?php echo esc_attr( $index ); ?>][capacity]"
								value="<?php echo esc_attr( $slot['capacity'] ?? $default_capacity ); ?>"
								min="1"
								required>
					</div>

					<div class="fp-override-field-clean">
						<label><?php _e( 'Language', 'fp-esperienze' ); ?></label>
						<input type="text"
								name="builder_slots[<?php echo esc_attr( $index ); ?>][lang]"
								value="<?php echo esc_attr( $slot['lang'] ?? $default_language ); ?>"
								maxlength="10"
								required>
					</div>

					<div class="fp-override-field-clean">
						<label><?php _e( 'Meeting Point', 'fp-esperienze' ); ?></label>
						<select name="builder_slots[<?php echo esc_attr( $index ); ?>][meeting_point_id]" required>
							<?php foreach ( $meeting_points as $mp_id => $mp_name ) : ?>
								<option value="<?php echo esc_attr( $mp_id ); ?>" <?php selected( $slot['meeting_point_id'] ?? $default_meeting_point, $mp_id ); ?>>
									<?php echo esc_html( $mp_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="fp-override-field-clean">
						<label><?php _e( 'Adult Price', 'fp-esperienze' ); ?> (<?php echo get_woocommerce_currency_symbol(); ?>)</label>
						<input type="number"
								name="builder_slots[<?php echo esc_attr( $index ); ?>][price_adult]"
								value="<?php echo esc_attr( $slot['price_adult'] ?? $default_price_adult ); ?>"
								min="0"
								step="0.01"
								required>
					</div>

					<div class="fp-override-field-clean">
						<label><?php _e( 'Child Price', 'fp-esperienze' ); ?> (<?php echo get_woocommerce_currency_symbol(); ?>)</label>
						<input type="number"
								name="builder_slots[<?php echo esc_attr( $index ); ?>][price_child]"
								value="<?php echo esc_attr( $slot['price_child'] ?? $default_price_child ); ?>"
								min="0"
								step="0.01"
								required>
					</div>
				</div>
			</div>
			
			<!-- Store schedule IDs for updates -->
			<?php if ( ! empty( $slot['schedule_ids'] ) ) : ?>
				<?php foreach ( $slot['schedule_ids'] as $schedule_id ) : ?>
					<input type="hidden" name="builder_slots[<?php echo esc_attr( $index ); ?>][schedule_ids][]" value="<?php echo esc_attr( $schedule_id ); ?>">
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a single time slot in the builder
	 */
	private function renderTimeSlot( $slot, $index, $days, $meeting_points, $default_duration, $default_capacity, $default_language, $default_meeting_point, $default_price_adult, $default_price_child, $product_id ): void {
		?>
		<div class="fp-time-slot-row">
                        <div class="fp-time-slot-header">
                                <div class="fp-time-field form-field fp-field fp-field--time">
                                        <label>
                                                <span class="dashicons dashicons-clock"></span>
                                                <?php _e( 'Start Time', 'fp-esperienze' ); ?> <span class="fp-required-indicator">*</span>
                                        </label>
					<input type="time" 
							name="builder_slots[<?php echo esc_attr( $index ); ?>][start_time]" 
							value="<?php echo esc_attr( $slot['start_time'] ?? '' ); ?>" 
							required 
							aria-describedby="fp-time-help-<?php echo esc_attr( $index ); ?>">
					<div id="fp-time-help-<?php echo esc_attr( $index ); ?>" class="screen-reader-text">
						<?php _e( 'Enter the start time for this experience slot in 24-hour format', 'fp-esperienze' ); ?>
					</div>
				</div>
				
                                <div class="fp-days-field form-field fp-field fp-field--days">
                                        <label>
                                                <span class="dashicons dashicons-calendar-alt"></span>
                                                <?php _e( 'Days of Week', 'fp-esperienze' ); ?> <span class="fp-required-indicator">*</span>
                                        </label>
					<div class="fp-days-selector" aria-describedby="fp-days-help-<?php echo esc_attr( $index ); ?>">
						<div class="fp-days-pills">
							<?php foreach ( $days as $day_value => $day_label ) : ?>
								<div class="fp-day-pill">
									<input type="checkbox" 
											id="day-<?php echo esc_attr( $index ); ?>-<?php echo esc_attr( $day_value ); ?>"
											name="builder_slots[<?php echo esc_attr( $index ); ?>][days][]" 
											value="<?php echo esc_attr( $day_value ); ?>"
											<?php checked( in_array( $day_value, $slot['days'] ?? array() ) ); ?>>
									<label for="day-<?php echo esc_attr( $index ); ?>-<?php echo esc_attr( $day_value ); ?>">
										<?php echo esc_html( substr( $day_label, 0, 3 ) ); ?>
									</label>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
					<div id="fp-days-help-<?php echo esc_attr( $index ); ?>" class="screen-reader-text">
						<?php _e( 'Select which days of the week this time slot is available', 'fp-esperienze' ); ?>
					</div>
				</div>
				
                                <div>
                                        <button type="button" class="button button-link-delete fp-remove-time-slot" aria-label="<?php esc_attr_e( 'Remove time slot', 'fp-esperienze' ); ?>">
                                                <span class="dashicons dashicons-trash"></span>
                                                <?php _e( 'Remove', 'fp-esperienze' ); ?>
                                        </button>
                                </div>
			</div>
			
			<div class="fp-overrides-section">
				<div>
					<div>
						<label>
							<?php _e( 'Duration (minutes)', 'fp-esperienze' ); ?>
						</label>
						<input type="number"
								name="builder_slots[<?php echo esc_attr( $index ); ?>][duration_min]"
								value="<?php echo esc_attr( $slot['duration_min'] ?? $default_duration ); ?>"
								min="1"
								required>
					</div>

					<div>
						<label>
							<?php _e( 'Capacity', 'fp-esperienze' ); ?>
						</label>
						<input type="number"
								name="builder_slots[<?php echo esc_attr( $index ); ?>][capacity]"
								value="<?php echo esc_attr( $slot['capacity'] ?? $default_capacity ); ?>"
								required>
					</div>

					<div>
						<label>
							<?php _e( 'Language', 'fp-esperienze' ); ?>
						</label>
						<input type="text"
								name="builder_slots[<?php echo esc_attr( $index ); ?>][lang]"
								value="<?php echo esc_attr( $slot['lang'] ?? $default_language ); ?>"
								maxlength="10"
								required>
					</div>
				</div>

				<div>
					<div>
						<label>
							<?php _e( 'Meeting Point', 'fp-esperienze' ); ?>
						</label>
						<select name="builder_slots[<?php echo esc_attr( $index ); ?>][meeting_point_id]" required>
							<?php foreach ( $meeting_points as $mp_id => $mp_name ) : ?>
								<option value="<?php echo esc_attr( $mp_id ); ?>" <?php selected( $slot['meeting_point_id'] ?? $default_meeting_point, $mp_id ); ?>>
									<?php echo esc_html( $mp_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div>
						<label>
							<?php _e( 'Adult Price', 'fp-esperienze' ); ?> (<?php echo get_woocommerce_currency_symbol(); ?>)
						</label>
						<input type="number"
								name="builder_slots[<?php echo esc_attr( $index ); ?>][price_adult]"
								value="<?php echo esc_attr( $slot['price_adult'] ?? $default_price_adult ); ?>"
								min="0"
								step="0.01"
								required>
					</div>

					<div>
						<label>
							<?php _e( 'Child Price', 'fp-esperienze' ); ?> (<?php echo get_woocommerce_currency_symbol(); ?>)
						</label>
						<input type="number"
								name="builder_slots[<?php echo esc_attr( $index ); ?>][price_child]"
								value="<?php echo esc_attr( $slot['price_child'] ?? $default_price_child ); ?>"
								min="0"
								step="0.01"
								required>
					</div>
				</div>
			</div>
			
			<!-- Store schedule IDs for updates -->
			<?php if ( ! empty( $slot['schedule_ids'] ) ) : ?>
				<?php foreach ( $slot['schedule_ids'] as $schedule_id ) : ?>
					<input type="hidden" name="builder_slots[<?php echo esc_attr( $index ); ?>][schedule_ids][]" value="<?php echo esc_attr( $schedule_id ); ?>">
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render slots summary table
	 *
	 * @param array $time_slots Time slots data
	 * @param array $days Days mapping
	 */
	private function renderSlotsSummaryTable( array $time_slots, array $days ): void {
		?>
		<div class="fp-slots-summary">
			<div class="fp-slots-summary-header">
				<span class="dashicons dashicons-clock"></span>
				<?php _e( 'Configured Time Slots Overview', 'fp-esperienze' ); ?>
			</div>
			<div class="fp-slots-summary-content">
				<?php if ( empty( $time_slots ) ) : ?>
					<div class="fp-empty-state">
						<div class="fp-empty-state-icon">
							<span class="dashicons dashicons-clock"></span>
						</div>
						<div class="fp-empty-state-title">
							<?php _e( 'No time slots configured yet', 'fp-esperienze' ); ?>
						</div>
						<div class="fp-empty-state-description">
							<?php _e( 'Create recurring weekly time slots to make your experience bookable. Each slot can have different settings and run on multiple days.', 'fp-esperienze' ); ?>
						</div>
                                                <div class="fp-empty-state-examples">
                                                        <h5><?php _e( 'Examples:', 'fp-esperienze' ); ?></h5>
                                                        <ul>
                                                                <li><?php _e( 'Morning tour: 09:00 on Mon, Wed, Fri', 'fp-esperienze' ); ?></li>
                                                                <li><?php _e( 'Afternoon tour: 14:30 on Tue, Thu, Sat', 'fp-esperienze' ); ?></li>
                                                                <li><?php _e( 'Weekend special: 10:00 on Sat, Sun with different pricing', 'fp-esperienze' ); ?></li>
                                                        </ul>
                                                </div>
					</div>
				<?php else : ?>
					<table class="fp-summary-table">
						<thead>
							<tr>
								<th><?php _e( 'Time', 'fp-esperienze' ); ?></th>
								<th><?php _e( 'Days', 'fp-esperienze' ); ?></th>
								<th><?php _e( 'Duration', 'fp-esperienze' ); ?></th>
								<th><?php _e( 'Capacity', 'fp-esperienze' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $time_slots as $slot ) : ?>
								<tr>
									<td>
										<span class="fp-time-badge"><?php echo esc_html( $slot['start_time'] ?? '' ); ?></span>
									</td>
									<td>
										<div class="fp-days-summary">
											<?php
											$slot_days = $slot['days'] ?? array();
											// Sort days to show in week order
											$sorted_days = array_intersect( array_keys( $days ), $slot_days );
											foreach ( $sorted_days as $day ) :
												$day_short = substr( $days[ $day ], 0, 3 );
												?>
												<span class="fp-day-badge"><?php echo esc_html( $day_short ); ?></span>
											<?php endforeach; ?>
										</div>
									</td>
									<td>
										<?php
										$duration = $slot['duration_min'] ?? null;
										echo $duration ? esc_html( $duration . ' min' ) : '-';
										?>
									</td>
									<td>
										<?php
										$capacity = $slot['capacity'] ?? null;
										echo $capacity ? esc_html( $capacity ) : '-';
										?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render event schedule builder for fixed-date events
	 *
	 * @param int $product_id Product ID
	 */
	private function renderEventScheduleBuilder( int $product_id ): void {
		$event_schedules = ScheduleManager::getEventSchedules( $product_id );
		$meeting_points  = $this->getMeetingPoints();
		
		// Group events by date
		$events_by_date = array();
		foreach ( $event_schedules as $schedule ) {
			$date = $schedule->event_date;
			if ( ! isset( $events_by_date[ $date ] ) ) {
				$events_by_date[ $date ] = array();
			}
			$events_by_date[ $date ][] = $schedule;
		}
		
		// Sort dates
		ksort( $events_by_date );
		
		?>
		<div id="fp-event-schedule-builder" class="fp-event-schedule-builder">
			<?php if ( empty( $events_by_date ) ) : ?>
				<div class="fp-empty-events-message">
					<p><?php _e( 'No event dates configured yet. Add your first event date below.', 'fp-esperienze' ); ?></p>
				</div>
			<?php else : ?>
				<?php foreach ( $events_by_date as $date => $schedules ) : ?>
					<div class="fp-event-date-card" data-date="<?php echo esc_attr( $date ); ?>">
						<?php $this->renderEventDateCard( $date, $schedules, $meeting_points, $product_id ); ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		
		<!-- Hidden container for generated event schedule inputs -->
                <div id="fp-generated-event-schedules" class="fp-hidden"></div>
		<?php
	}

	/**
	 * Render event date card with time slots
	 *
	 * @param string $date Event date
	 * @param array  $schedules Schedules for this date
	 * @param array  $meeting_points Meeting points
	 * @param int    $product_id Product ID
	 */
	private function renderEventDateCard( string $date, array $schedules, array $meeting_points, int $product_id ): void {
		?>
		<div class="fp-event-date-header">
			<div class="fp-event-date-info">
				<span class="dashicons dashicons-calendar-alt"></span>
				<strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $date ) ) ); ?></strong>
				<span class="fp-event-date-meta"><?php printf( _n( '%d time slot', '%d time slots', count( $schedules ), 'fp-esperienze' ), count( $schedules ) ); ?></span>
			</div>
			<div class="fp-event-date-actions">
                                <button type="button" class="button button-primary fp-add-event-timeslot" data-date="<?php echo esc_attr( $date ); ?>">
                                        <span class="dashicons dashicons-clock"></span>
                                        <?php _e( 'Add Time Slot', 'fp-esperienze' ); ?>
                                </button>
                                <button type="button" class="button button-link-delete fp-remove-event-date" data-date="<?php echo esc_attr( $date ); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                        <?php _e( 'Remove Date', 'fp-esperienze' ); ?>
                                </button>
			</div>
		</div>
		
		<div class="fp-event-timeslots">
			<?php foreach ( $schedules as $index => $schedule ) : ?>
				<div class="fp-event-timeslot-card" data-schedule-id="<?php echo esc_attr( $schedule->id ); ?>">
					<?php $this->renderEventTimeslotCard( $schedule, $index, $meeting_points, $date ); ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render individual event timeslot card
	 *
	 * @param object $schedule Schedule object
	 * @param int    $index Index
	 * @param array  $meeting_points Meeting points
	 * @param string $date Event date
	 */
	private function renderEventTimeslotCard( $schedule, int $index, array $meeting_points, string $date ): void {
		?>
		<div class="fp-event-timeslot-content">
			<input type="hidden" name="event_schedules[<?php echo esc_attr( $date ); ?>][<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $schedule->id ?? '' ); ?>">
			<input type="hidden" name="event_schedules[<?php echo esc_attr( $date ); ?>][<?php echo esc_attr( $index ); ?>][event_date]" value="<?php echo esc_attr( $date ); ?>">
			<input type="hidden" name="event_schedules[<?php echo esc_attr( $date ); ?>][<?php echo esc_attr( $index ); ?>][schedule_type]" value="fixed">
			
			<div class="fp-event-timeslot-grid">
				<div class="fp-timeslot-field">
					<label>
						<span class="dashicons dashicons-clock"></span>
						<?php _e( 'Start Time', 'fp-esperienze' ); ?> <span class="required">*</span>
					</label>
					<input type="time" 
							name="event_schedules[<?php echo esc_attr( $date ); ?>][<?php echo esc_attr( $index ); ?>][start_time]"
							value="<?php echo esc_attr( $schedule->start_time ?? '' ); ?>"
							required>
				</div>
				
				<div class="fp-timeslot-field">
					<label><?php _e( 'Duration (min)', 'fp-esperienze' ); ?></label>
					<input type="number" 
							name="event_schedules[<?php echo esc_attr( $date ); ?>][<?php echo esc_attr( $index ); ?>][duration_min]"
							value="<?php echo esc_attr( $schedule->duration_min ?? 60 ); ?>"
							min="1" required>
				</div>
				
				<div class="fp-timeslot-field">
					<label><?php _e( 'Capacity', 'fp-esperienze' ); ?></label>
					<input type="number" 
							name="event_schedules[<?php echo esc_attr( $date ); ?>][<?php echo esc_attr( $index ); ?>][capacity]"
							value="<?php echo esc_attr( $schedule->capacity ?? 10 ); ?>"
							min="1" required>
				</div>
				
				<div class="fp-timeslot-field">
					<label><?php _e( 'Language', 'fp-esperienze' ); ?></label>
					<input type="text" 
							name="event_schedules[<?php echo esc_attr( $date ); ?>][<?php echo esc_attr( $index ); ?>][lang]"
							value="<?php echo esc_attr( $schedule->lang ?? 'en' ); ?>"
							maxlength="10" required>
				</div>
				
				<div class="fp-timeslot-field">
					<label><?php _e( 'Meeting Point', 'fp-esperienze' ); ?></label>
					<select name="event_schedules[<?php echo esc_attr( $date ); ?>][<?php echo esc_attr( $index ); ?>][meeting_point_id]" required>
						<?php foreach ( $meeting_points as $mp_id => $mp_name ) : ?>
							<option value="<?php echo esc_attr( $mp_id ); ?>" <?php selected( $schedule->meeting_point_id ?? '', $mp_id ); ?>>
								<?php echo esc_html( $mp_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				
				<div class="fp-timeslot-field">
					<label><?php _e( 'Adult Price', 'fp-esperienze' ); ?> (<?php echo get_woocommerce_currency_symbol(); ?>)</label>
					<input type="number" 
							name="event_schedules[<?php echo esc_attr( $date ); ?>][<?php echo esc_attr( $index ); ?>][price_adult]"
							value="<?php echo esc_attr( $schedule->price_adult ?? 0 ); ?>"
							min="0" step="0.01" required>
				</div>
				
				<div class="fp-timeslot-field">
					<label><?php _e( 'Child Price', 'fp-esperienze' ); ?> (<?php echo get_woocommerce_currency_symbol(); ?>)</label>
					<input type="number" 
							name="event_schedules[<?php echo esc_attr( $date ); ?>][<?php echo esc_attr( $index ); ?>][price_child]"
							value="<?php echo esc_attr( $schedule->price_child ?? 0 ); ?>"
							min="0" step="0.01" required>
				</div>
				
                                <div class="fp-timeslot-actions">
                                        <button type="button" class="button button-link-delete fp-remove-event-timeslot">
                                                <span class="dashicons dashicons-trash"></span>
                                                <?php _e( 'Remove', 'fp-esperienze' ); ?>
                                        </button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render overrides section - MODERN DESIGN
	 *
	 * @param int $product_id Product ID
	 */
	private function renderOverridesSection( int $product_id ): void {
		$overrides = OverrideManager::getOverrides( $product_id );

		// Sort overrides by date
		usort(
			$overrides,
			function ( $a, $b ) {
				return strcmp( $a->date ?? '', $b->date ?? '' );
			}
		);

		?>
		<div class="fp-overrides-container-clean">
			<?php if ( empty( $overrides ) ) : ?>
				<div class="fp-overrides-empty-clean">
					<p><?php _e( 'No date overrides configured. Add exceptions below for specific dates when you need to close, change capacity, or modify pricing.', 'fp-esperienze' ); ?></p>
				</div>
			<?php else : ?>
				<?php foreach ( $overrides as $index => $override ) : ?>
					<?php $this->renderOverrideCardClean( $override, $index ); ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a clean override card - REFACTORED VERSION
	 *
	 * @param object $override Override object
	 * @param int    $index Index
	 */
	private function renderOverrideCardClean( $override, int $index ): void {
		$price_override    = $override->price_override_json ? json_decode( $override->price_override_json, true ) : array();
		$date              = $override->date ?? '';
		$is_closed         = ! empty( $override->is_closed );
		$capacity_override = $override->capacity_override ?? '';
		$reason            = $override->reason ?? '';
		$adult_price       = $price_override['adult'] ?? '';
		$child_price       = $price_override['child'] ?? '';

		?>
		<div class="fp-override-card-clean<?php echo $is_closed ? ' is-closed' : ''; ?>" data-index="<?php echo esc_attr( $index ); ?>">
			<input type="hidden" name="overrides[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $override->id ?? '' ); ?>">
			
			<!-- Override header -->
			<div class="fp-override-header-clean">
				<div class="fp-override-date-field-clean">
					<label for="override-date-<?php echo esc_attr( $index ); ?>">
						<span class="dashicons dashicons-calendar-alt"></span>
						<?php _e( 'Date', 'fp-esperienze' ); ?> <span class="required">*</span>
					</label>
					<input type="date" 
							id="override-date-<?php echo esc_attr( $index ); ?>"
							name="overrides[<?php echo esc_attr( $index ); ?>][date]" 
							value="<?php echo esc_attr( $date ); ?>"
							required>
				</div>
				
				<div class="fp-override-actions-clean">
					<div class="fp-override-checkbox-clean">
						<input type="checkbox" 
								name="overrides[<?php echo esc_attr( $index ); ?>][is_closed]" 
								value="1" 
								id="override-closed-<?php echo esc_attr( $index ); ?>"
								<?php checked( $is_closed ); ?>>
						<label for="override-closed-<?php echo esc_attr( $index ); ?>"><?php _e( 'Closed', 'fp-esperienze' ); ?></label>
					</div>
					
                                        <button type="button" class="button button-link-delete fp-override-remove-clean">
                                                <span class="dashicons dashicons-trash"></span>
                                                <?php _e( 'Remove', 'fp-esperienze' ); ?>
                                        </button>
				</div>
			</div>
			
			<!-- Override fields -->
			<div class="fp-override-fields-clean<?php echo $is_closed ? ' is-closed' : ''; ?>">
				<div class="fp-override-grid-clean">
					<div class="fp-override-field-clean">
						<label for="override-capacity-<?php echo esc_attr( $index ); ?>"><?php _e( 'Capacity Override', 'fp-esperienze' ); ?></label>
						<input type="number" 
								id="override-capacity-<?php echo esc_attr( $index ); ?>"
								name="overrides[<?php echo esc_attr( $index ); ?>][capacity_override]" 
								value="<?php echo esc_attr( $capacity_override ); ?>"
								placeholder="<?php esc_attr_e( 'Leave empty = use default', 'fp-esperienze' ); ?>" 
								min="0" 
								step="1">
					</div>
					
					<div class="fp-override-field-clean">
						<label for="override-reason-<?php echo esc_attr( $index ); ?>"><?php _e( 'Reason/Note', 'fp-esperienze' ); ?></label>
						<input type="text" 
								id="override-reason-<?php echo esc_attr( $index ); ?>"
								name="overrides[<?php echo esc_attr( $index ); ?>][reason]" 
								value="<?php echo esc_attr( $reason ); ?>"
								placeholder="<?php esc_attr_e( 'Optional note (e.g., Holiday, Maintenance)', 'fp-esperienze' ); ?>">
					</div>
					
					<div class="fp-override-field-clean">
						<label for="override-adult-price-<?php echo esc_attr( $index ); ?>"><?php _e( 'Adult Price', 'fp-esperienze' ); ?> (<?php echo get_woocommerce_currency_symbol(); ?>)</label>
						<input type="number" 
								id="override-adult-price-<?php echo esc_attr( $index ); ?>"
								name="overrides[<?php echo esc_attr( $index ); ?>][price_adult]" 
								value="<?php echo esc_attr( $adult_price ); ?>"
								placeholder="<?php esc_attr_e( 'Leave empty = use default', 'fp-esperienze' ); ?>" 
								min="0" 
								step="0.01">
					</div>
					
					<div class="fp-override-field-clean">
						<label for="override-child-price-<?php echo esc_attr( $index ); ?>"><?php _e( 'Child Price', 'fp-esperienze' ); ?> (<?php echo get_woocommerce_currency_symbol(); ?>)</label>
						<input type="number" 
								id="override-child-price-<?php echo esc_attr( $index ); ?>"
								name="overrides[<?php echo esc_attr( $index ); ?>][price_child]" 
								value="<?php echo esc_attr( $child_price ); ?>"
								placeholder="<?php esc_attr_e( 'Leave empty = use default', 'fp-esperienze' ); ?>" 
								min="0" 
								step="0.01">
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a single override card - MODERN DESIGN
	 *
	 * @param object $override Override object
	 * @param int    $index Index
	 */
	private function renderOverrideCard( $override, int $index ): void {
		$price_override    = $override->price_override_json ? json_decode( $override->price_override_json, true ) : array();
		$date              = $override->date ?? '';
		$is_closed         = ! empty( $override->is_closed );
		$capacity_override = $override->capacity_override ?? '';
		$reason            = $override->reason ?? '';
		$adult_price       = $price_override['adult'] ?? '';
		$child_price       = $price_override['child'] ?? '';

		$card_classes = array( 'fp-override-card' );
		if ( $is_closed ) {
			$card_classes[] = 'is-closed';
		}
		?>
		<div class="<?php echo esc_attr( implode( ' ', $card_classes ) ); ?>" data-index="<?php echo esc_attr( $index ); ?>">
			<input type="hidden" name="overrides[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $override->id ?? '' ); ?>">
			
			<div class="fp-override-header">
				<div class="fp-override-date-field">
					<span class="dashicons dashicons-calendar-alt"></span>
					<input type="date" 
							name="overrides[<?php echo esc_attr( $index ); ?>][date]" 
							class="fp-override-input fp-override-date" 
							value="<?php echo esc_attr( $date ); ?>"
							required 
							aria-label="<?php esc_attr_e( 'Override date', 'fp-esperienze' ); ?>"
							data-original-value="<?php echo esc_attr( $date ); ?>">
				</div>
				<div class="fp-override-actions">
					<div class="fp-override-checkbox">
						<input type="checkbox" 
								name="overrides[<?php echo esc_attr( $index ); ?>][is_closed]" 
								value="1" 
								id="override-closed-<?php echo esc_attr( $index ); ?>"
								<?php checked( $is_closed ); ?>
								data-original-checked="<?php echo $is_closed ? '1' : '0'; ?>">
						<label for="override-closed-<?php echo esc_attr( $index ); ?>"><?php _e( 'Closed', 'fp-esperienze' ); ?></label>
					</div>
                                        <button type="button" class="button button-link-delete fp-override-remove" aria-label="<?php esc_attr_e( 'Remove this override', 'fp-esperienze' ); ?>">
                                                <span class="dashicons dashicons-trash"></span>
                                                <?php _e( 'Remove', 'fp-esperienze' ); ?>
                                        </button>
				</div>
			</div>
			
			<div class="fp-override-fields<?php echo $is_closed ? ' is-closed' : ''; ?>">
				<div class="fp-override-field">
					<label><?php _e( 'Capacity Override', 'fp-esperienze' ); ?></label>
					<input type="number" 
							name="overrides[<?php echo esc_attr( $index ); ?>][capacity_override]" 
							class="fp-override-input" 
							value="<?php echo esc_attr( $capacity_override ); ?>"
							placeholder="<?php esc_attr_e( 'Leave empty = use default', 'fp-esperienze' ); ?>" 
							min="0" 
							step="1"
							aria-label="<?php esc_attr_e( 'Capacity override', 'fp-esperienze' ); ?>"
							data-original-value="<?php echo esc_attr( $capacity_override ); ?>">
				</div>
				
				<div class="fp-override-field">
					<label><?php _e( 'Adult Price (€)', 'fp-esperienze' ); ?></label>
					<input type="number" 
							name="overrides[<?php echo esc_attr( $index ); ?>][price_adult]" 
							class="fp-override-input" 
							value="<?php echo esc_attr( $adult_price ); ?>"
							placeholder="<?php esc_attr_e( 'Leave empty = use default', 'fp-esperienze' ); ?>" 
							min="0" 
							step="0.01"
							aria-label="<?php esc_attr_e( 'Adult price override', 'fp-esperienze' ); ?>"
							data-original-value="<?php echo esc_attr( $adult_price ); ?>">
				</div>
				
				<div class="fp-override-field">
					<label><?php _e( 'Child Price (€)', 'fp-esperienze' ); ?></label>
					<input type="number" 
							name="overrides[<?php echo esc_attr( $index ); ?>][price_child]" 
							class="fp-override-input" 
							value="<?php echo esc_attr( $child_price ); ?>"
							placeholder="<?php esc_attr_e( 'Leave empty = use default', 'fp-esperienze' ); ?>" 
							min="0" 
							step="0.01"
							aria-label="<?php esc_attr_e( 'Child price override', 'fp-esperienze' ); ?>"
							data-original-value="<?php echo esc_attr( $child_price ); ?>">
				</div>
				
				<div class="fp-override-field">
					<label><?php _e( 'Reason (Optional)', 'fp-esperienze' ); ?></label>
					<input type="text" 
							name="overrides[<?php echo esc_attr( $index ); ?>][reason]" 
							class="fp-override-input" 
							value="<?php echo esc_attr( $reason ); ?>"
							placeholder="<?php esc_attr_e( 'Holiday, Maintenance, etc.', 'fp-esperienze' ); ?>"
							aria-label="<?php esc_attr_e( 'Reason for this override', 'fp-esperienze' ); ?>"
							data-original-value="<?php echo esc_attr( $reason ); ?>">
				</div>
			</div>
			
			<div class="fp-override-status <?php echo $is_closed ? 'closed' : 'normal'; ?>"></div>
		</div>
		<?php
	}

	/**
	 * Render a single override table row - LEGACY
	 *
	 * @param object $override Override object
	 * @param int    $index Row index
	 */
	private function renderOverrideTableRow( $override, int $index ): void {
		$price_override = $override->price_override_json ? json_decode( $override->price_override_json, true ) : array();
		$date           = $override->date ?? '';
		$today          = date( 'Y-m-d' );
		$distant_future = date( 'Y-m-d', strtotime( '+5 years' ) );
		$is_distant     = $date > $distant_future;
		$is_past        = $date < $today;

		?>
		<input type="hidden" name="overrides[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $override->id ?? '' ); ?>">
		
		<td>
			<input type="date" 
					name="overrides[<?php echo esc_attr( $index ); ?>][date]" 
					value="<?php echo esc_attr( $date ); ?>" 
					required
					class="fp-override-input fp-override-date"
					aria-label="<?php esc_attr_e( 'Override date', 'fp-esperienze' ); ?>"
					data-original-value="<?php echo esc_attr( $date ); ?>">
			<?php if ( $is_distant ) : ?>
				<div class="fp-date-warning show">
					<span class="dashicons dashicons-warning"></span>
					<?php _e( 'This date is very far in the future. Please verify it\'s correct.', 'fp-esperienze' ); ?>
				</div>
			<?php endif; ?>
                        <?php if ( $is_past ) : ?>
                                <div class="fp-date-warning show is-info">
					<span class="dashicons dashicons-info"></span>
					<?php _e( 'This date is in the past.', 'fp-esperienze' ); ?>
				</div>
			<?php endif; ?>
		</td>
		
		<td>
			<div class="fp-override-checkbox">
				<input type="checkbox" 
						name="overrides[<?php echo esc_attr( $index ); ?>][is_closed]" 
						value="1" 
						id="override-closed-<?php echo esc_attr( $index ); ?>"
						<?php checked( $override->is_closed ?? 0, 1 ); ?>
						data-original-checked="<?php echo $override->is_closed ?? 0; ?>">
				<label for="override-closed-<?php echo esc_attr( $index ); ?>">
					<?php _e( 'Closed', 'fp-esperienze' ); ?>
				</label>
			</div>
		</td>
		
		<td>
			<input type="number" 
					name="overrides[<?php echo esc_attr( $index ); ?>][capacity_override]" 
					value="<?php echo esc_attr( $override->capacity_override ?? '' ); ?>" 
					placeholder="<?php esc_attr_e( 'Leave empty = use default', 'fp-esperienze' ); ?>" 
					min="0" 
					step="1"
					class="fp-override-input fp-override-number"
					aria-label="<?php esc_attr_e( 'Capacity override', 'fp-esperienze' ); ?>"
					data-original-value="<?php echo esc_attr( $override->capacity_override ?? '' ); ?>">
		</td>
		
		<td>
			<input type="number" 
					name="overrides[<?php echo esc_attr( $index ); ?>][price_adult]" 
					value="<?php echo esc_attr( $price_override['adult'] ?? '' ); ?>" 
					placeholder="<?php esc_attr_e( 'Leave empty = use default', 'fp-esperienze' ); ?>" 
					min="0" 
					step="0.01"
					class="fp-override-input fp-override-number"
					aria-label="<?php esc_attr_e( 'Adult price override', 'fp-esperienze' ); ?>"
					data-original-value="<?php echo esc_attr( $price_override['adult'] ?? '' ); ?>">
		</td>
		
		<td>
			<input type="number" 
					name="overrides[<?php echo esc_attr( $index ); ?>][price_child]" 
					value="<?php echo esc_attr( $price_override['child'] ?? '' ); ?>" 
					placeholder="<?php esc_attr_e( 'Leave empty = use default', 'fp-esperienze' ); ?>" 
					min="0" 
					step="0.01"
					class="fp-override-input fp-override-number"
					aria-label="<?php esc_attr_e( 'Child price override', 'fp-esperienze' ); ?>"
					data-original-value="<?php echo esc_attr( $price_override['child'] ?? '' ); ?>">
		</td>
		
		<td>
			<input type="text" 
					name="overrides[<?php echo esc_attr( $index ); ?>][reason]" 
					value="<?php echo esc_attr( $override->reason ?? '' ); ?>" 
					placeholder="<?php esc_attr_e( 'Optional: Holiday, Maintenance, etc.', 'fp-esperienze' ); ?>"
					class="fp-override-input fp-override-reason"
					aria-label="<?php esc_attr_e( 'Reason for this override', 'fp-esperienze' ); ?>"
					data-original-value="<?php echo esc_attr( $override->reason ?? '' ); ?>">
		</td>
		
		<td>
                   <button type="button" class="button button-link-delete fp-override-remove" aria-label="<?php esc_attr_e( 'Remove this override', 'fp-esperienze' ); ?>">
                            <span class="dashicons dashicons-trash"></span>
                            <?php _e( 'Remove', 'fp-esperienze' ); ?>
                   </button>
		</td>
		<?php
	}

	/**
	 * Render a single override row (legacy format for non-table view)
	 *
	 * @param object $override Override object
	 * @param int    $index Row index
	 */
	private function renderOverrideRow( $override, int $index ): void {
		$price_override = $override->price_override_json ? json_decode( $override->price_override_json, true ) : array();
		$date           = $override->date ?? '';
		$today          = date( 'Y-m-d' );
		$distant_future = date( 'Y-m-d', strtotime( '+5 years' ) );
		$is_distant     = $date > $distant_future;
		$is_past        = $date < $today;

		$row_classes = array( 'fp-override-row' );
		if ( $is_distant ) {
			$row_classes[] = 'distant-date';
		}

		?>
		<div class="<?php echo esc_attr( implode( ' ', $row_classes ) ); ?>" data-index="<?php echo esc_attr( $index ); ?>" data-date="<?php echo esc_attr( $date ); ?>">
			<input type="hidden" name="overrides[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $override->id ?? '' ); ?>">
			
			<div>
				<input type="date" 
						name="overrides[<?php echo esc_attr( $index ); ?>][date]" 
						value="<?php echo esc_attr( $date ); ?>" 
						required
						class="fp-override-input"
						aria-label="<?php esc_attr_e( 'Override date', 'fp-esperienze' ); ?>"
						data-original-value="<?php echo esc_attr( $date ); ?>">
				<?php if ( $is_distant ) : ?>
					<div class="fp-date-warning show">
						<?php _e( 'Very distant date - please verify', 'fp-esperienze' ); ?>
					</div>
				<?php endif; ?>
			</div>
			
			<div class="fp-override-checkbox">
				<input type="checkbox" 
						name="overrides[<?php echo esc_attr( $index ); ?>][is_closed]" 
						value="1" 
						id="override-closed-<?php echo esc_attr( $index ); ?>"
						<?php checked( $override->is_closed ?? 0, 1 ); ?>
						data-original-checked="<?php echo $override->is_closed ?? 0; ?>">
				<label for="override-closed-<?php echo esc_attr( $index ); ?>">
					<?php _e( 'Closed', 'fp-esperienze' ); ?>
				</label>
			</div>
			
			<input type="number" 
					name="overrides[<?php echo esc_attr( $index ); ?>][capacity_override]" 
					value="<?php echo esc_attr( $override->capacity_override ?? '' ); ?>" 
					placeholder="<?php esc_attr_e( 'Capacity (empty = default)', 'fp-esperienze' ); ?>" 
					min="0" 
					step="1"
					class="fp-override-input"
					aria-label="<?php esc_attr_e( 'Capacity override', 'fp-esperienze' ); ?>"
					data-original-value="<?php echo esc_attr( $override->capacity_override ?? '' ); ?>">
			
			<input type="number" 
					name="overrides[<?php echo esc_attr( $index ); ?>][price_adult]" 
					value="<?php echo esc_attr( $price_override['adult'] ?? '' ); ?>" 
					placeholder="<?php esc_attr_e( 'Adult € (empty = default)', 'fp-esperienze' ); ?>" 
					min="0" 
					step="0.01"
					class="fp-override-input"
					aria-label="<?php esc_attr_e( 'Adult price override', 'fp-esperienze' ); ?>"
					data-original-value="<?php echo esc_attr( $price_override['adult'] ?? '' ); ?>">
			
			<input type="number" 
					name="overrides[<?php echo esc_attr( $index ); ?>][price_child]" 
					value="<?php echo esc_attr( $price_override['child'] ?? '' ); ?>" 
					placeholder="<?php esc_attr_e( 'Child € (empty = default)', 'fp-esperienze' ); ?>" 
					min="0" 
					step="0.01"
					class="fp-override-input"
					aria-label="<?php esc_attr_e( 'Child price override', 'fp-esperienze' ); ?>"
					data-original-value="<?php echo esc_attr( $price_override['child'] ?? '' ); ?>">
			
			<input type="text" 
					name="overrides[<?php echo esc_attr( $index ); ?>][reason]" 
					value="<?php echo esc_attr( $override->reason ?? '' ); ?>" 
					placeholder="<?php esc_attr_e( 'Reason (optional)', 'fp-esperienze' ); ?>"
					class="fp-override-input"
					aria-label="<?php esc_attr_e( 'Reason for this override', 'fp-esperienze' ); ?>"
					data-original-value="<?php echo esc_attr( $override->reason ?? '' ); ?>">
			
                   <button type="button" class="button button-link-delete fp-override-remove" aria-label="<?php esc_attr_e( 'Remove this override', 'fp-esperienze' ); ?>">
                            <span class="dashicons dashicons-trash"></span>
                            <?php _e( 'Remove', 'fp-esperienze' ); ?>
                   </button>
		</div>
		<?php
	}

	/**
	 * Render extras section
	 *
	 * @param int $product_id Product ID
	 */
	private function renderExtrasSection( int $product_id ): void {
		$all_extras         = ExtraManager::getAllExtras( true ); // Only active extras
		$product_extras     = ExtraManager::getProductExtras( $product_id, false ); // Include inactive for editing
		$selected_extra_ids = array_column( $product_extras, 'id' );

                ?>
                <div class="fp-extras-selection">
                        <fieldset class="fp-extras-fieldset">
                                <legend><?php esc_html_e( 'Available extras', 'fp-esperienze' ); ?></legend>
                                <p class="fp-extras-intro description"><?php esc_html_e( 'Select the add-ons that customers can purchase alongside this experience.', 'fp-esperienze' ); ?></p>

                                <?php if ( empty( $all_extras ) ) : ?>
                                        <div class="fp-extras-empty notice notice-info inline">
                                                <p><?php esc_html_e( 'You have not created any extras yet. Extras let you upsell add-ons such as equipment rental or tastings.', 'fp-esperienze' ); ?></p>
                                                <p>
                                                        <a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=fp-esperienze-extras' ) ); ?>">
                                                                <?php esc_html_e( 'Create your first extra', 'fp-esperienze' ); ?>
                                                        </a>
                                                </p>
                                        </div>
                                <?php else : ?>
                                        <ul class="fp-extras-list">
                                                <?php foreach ( $all_extras as $extra ) :
                                                        $is_selected     = in_array( $extra->id, $selected_extra_ids, true );
                                                        $description     = trim( (string) ( $extra->description ?? '' ) );
                                                        $description_id  = $description ? 'fp-extra-desc-' . absint( $extra->id ) : '';
                                                        $billing_label   = 'per_person' === $extra->billing_type ? __( 'Per person', 'fp-esperienze' ) : __( 'Per booking', 'fp-esperienze' );
                                                        $price_display   = '';

                                                        if ( function_exists( 'wc_price' ) ) {
                                                                $price_display = wc_price( (float) $extra->price );
                                                        } else {
                                                                $currency_symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
                                                                $price_display   = sprintf( '%s%s', $currency_symbol, number_format_i18n( (float) $extra->price, 2 ) );
                                                        }
                                                        ?>
                                                        <li class="fp-extras-list__item">
                                                                <label class="fp-extra-card">
                                                                        <input type="checkbox"
                                                                                name="fp_product_extras[]"
                                                                                value="<?php echo esc_attr( $extra->id ); ?>"
                                                                                <?php
                                                                                if ( $description_id ) {
                                                                                        printf( ' aria-describedby="%s"', esc_attr( $description_id ) );
                                                                                }
                                                                                ?>
                                                                                <?php checked( $is_selected ); ?>>
                                                                        <span class="fp-extra-card__inner">
                                                                                <span class="fp-extra-card__header">
                                                                                        <span class="fp-extra-card__name"><?php echo esc_html( $extra->name ); ?></span>
                                                                                        <span class="fp-extra-card__meta">
                                                                                                <span class="fp-extra-card__price"><?php echo wp_kses_post( $price_display ); ?></span>
                                                                                                <span class="fp-extra-card__billing"><?php echo esc_html( $billing_label ); ?></span>
                                                                                        </span>
                                                                                </span>
                                                                                <?php if ( $description ) : ?>
                                                                                        <span class="fp-extra-card__description" id="<?php echo esc_attr( $description_id ); ?>"><?php echo esc_html( $description ); ?></span>
                                                                                <?php endif; ?>
                                                                        </span>
                                                                        <span class="fp-extra-card__status" aria-hidden="true">
                                                                                <span class="dashicons dashicons-yes-alt"></span>
                                                                        </span>
                                                                </label>
                                                        </li>
                                                <?php endforeach; ?>
                                        </ul>
                                <?php endif; ?>
                        </fieldset>
                </div>
                <?php
        }

	/**
	 * Save product data
	 *
	 * @param int $post_id Post ID
	 */
	public function saveProductData( int $post_id ): void {
               // Check nonce
               if ( ! isset( $_POST['fp_esperienze_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['fp_esperienze_nonce'] ), 'fp_esperienze_save' ) ) {
                       return;
               }

		// Only proceed if this is an experience product
		$product_type = sanitize_text_field( isset( $_POST['product-type'] ) ? wp_unslash( $_POST['product-type'] ) : '' );
		if ( $product_type !== 'experience' ) {
			return;
		}

		// Ensure product type is set to 'experience' - this MUST happen
		// Use multiple approaches to ensure it sticks
		update_post_meta( $post_id, '_product_type', 'experience' );

		// Also set it on the global $_POST to ensure WooCommerce core picks it up
		$_POST['product-type'] = 'experience';

		if ( isset( $_POST['_experience_duration'] ) ) {
			$raw_duration = wp_unslash( $_POST['_experience_duration'] );
			$duration     = absint( $raw_duration );

			if ( $duration > 0 ) {
				update_post_meta( $post_id, '_experience_duration', $duration );
				$_POST['_experience_duration'] = (string) $duration;
			} else {
				delete_post_meta( $post_id, '_experience_duration' );
				$_POST['_experience_duration'] = '';
			}
		}

                // Save basic experience fields
                $fields = array(
                        '_fp_exp_cutoff_minutes',
                        '_fp_exp_free_cancel_until_minutes',
                        '_fp_exp_cancel_fee_percent',
                        '_fp_exp_no_show_policy',
                );

                $int_fields = array(
                        '_fp_exp_cutoff_minutes',
                        '_fp_exp_free_cancel_until_minutes',
                );

                $float_fields = array(
                        '_fp_exp_cancel_fee_percent',
                );

		foreach ( $fields as $field ) {
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}

			$raw_value = wp_unslash( $_POST[ $field ] );

                        if ( in_array( $field, $int_fields, true ) ) {
                                $value = absint( $raw_value );
                        } elseif ( in_array( $field, $float_fields, true ) ) {
				$value = floatval( $raw_value );
			} else {
				$value = sanitize_text_field( $raw_value );
			}

			update_post_meta( $post_id, $field, $value );
		}

		// Save textarea fields with appropriate sanitization
		$textarea_fields = array(
			'_fp_exp_included',
			'_fp_exp_excluded',
		);

                foreach ( $textarea_fields as $field ) {
                        if ( isset( $_POST[ $field ] ) ) {
                                update_post_meta( $post_id, $field, sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) ) );
                        }
                }

                // Reviews section toggle - default to enabled for existing products, but allow explicit disabling.
                $existing_reviews_flag = get_post_meta( $post_id, '_fp_exp_enable_reviews', true );
                $enable_reviews        = 'no' === $existing_reviews_flag ? 'no' : 'yes';

                if ( array_key_exists( '_fp_exp_enable_reviews', $_POST ) ) {
                        $raw_enable_reviews = sanitize_text_field( wp_unslash( $_POST['_fp_exp_enable_reviews'] ) );
                        $enable_reviews     = ( 'yes' === $raw_enable_reviews ) ? 'yes' : 'no';
                }

                update_post_meta( $post_id, '_fp_exp_enable_reviews', $enable_reviews );

                // Save experience type
                if ( isset( $_POST['_fp_experience_type'] ) ) {
                        $experience_type = sanitize_text_field( wp_unslash( $_POST['_fp_experience_type'] ) );
                        if ( in_array( $experience_type, array( 'experience', 'event' ) ) ) {
                                update_post_meta( $post_id, '_fp_experience_type', $experience_type );
			}
		}

		// Save gallery images
		if ( isset( $_POST['_fp_exp_gallery_images'] ) ) {
			$raw_gallery = wp_unslash( (array) $_POST['_fp_exp_gallery_images'] );
			$gallery_ids = array_values( array_unique( array_filter( array_map( 'absint', $raw_gallery ) ) ) );

			if ( ! empty( $gallery_ids ) ) {
				update_post_meta( $post_id, '_fp_exp_gallery_images', $gallery_ids );
			} else {
				delete_post_meta( $post_id, '_fp_exp_gallery_images' );
			}
		} else {
			delete_post_meta( $post_id, '_fp_exp_gallery_images' );
		}

		// Save schedules
		$this->saveSchedules( $post_id );

		// Save overrides
		$this->saveOverrides( $post_id );

		// Save extras
		$this->saveExtras( $post_id );

		// Save dynamic pricing rules
		$this->savePricingRules( $post_id );
	}

	/**
	 * Ensure product type is preserved during save
	 *
	 * @param int $product_id Product ID
	 */
	public function ensureProductType( int $product_id ): void {
		// Only proceed if we're saving an experience product
		$product_type = sanitize_text_field( isset( $_POST['product-type'] ) ? wp_unslash( $_POST['product-type'] ) : '' );
		if ( $product_type !== 'experience' ) {
			return;
		}

		// Double-check that product type is properly set
		$current_type = get_post_meta( $product_id, '_product_type', true );
		if ( $current_type !== 'experience' ) {
			update_post_meta( $product_id, '_product_type', 'experience' );
		}
	}

	/**
	 * Save dynamic pricing rules
	 *
	 * @param int $product_id Product ID
	 */
	private function savePricingRules( int $product_id ): void {
		if ( ! isset( $_POST['pricing_rules'] ) || ! is_array( $_POST['pricing_rules'] ) ) {
			return;
		}

		// First, delete all existing rules for this product
		global $wpdb;
		$table_name = $wpdb->prefix . 'fp_dynamic_pricing_rules';
		$wpdb->delete( $table_name, array( 'product_id' => $product_id ), array( '%d' ) );

		$pricing_rules = wp_unslash( $_POST['pricing_rules'] );

		$float_fields = array(
			'adult_adjustment',
			'child_adjustment',
			'action_value',
		);

		$int_fields = array(
			'id',
			'priority',
			'days_before',
			'min_participants',
		);

		$bool_fields = array( 'is_active' );

		// Save new rules
		foreach ( $pricing_rules as $rule_data ) {
			if ( ! is_array( $rule_data ) ) {
				continue;
			}

			$rule_data = wp_unslash( $rule_data );

			// Sanitize rule data
			$sanitized_rule = array(
				'rule_name'  => sanitize_text_field( $rule_data['rule_name'] ?? '' ),
				'rule_type'  => sanitize_text_field( $rule_data['rule_type'] ?? '' ),
				'product_id' => $product_id,
				'is_active'  => 0,
			);

			if ( empty( $sanitized_rule['rule_name'] ) || empty( $sanitized_rule['rule_type'] ) ) {
				continue;
			}

			// Copy other sanitized fields if they exist
			foreach ( $rule_data as $key => $value ) {
				if ( in_array( $key, array( 'rule_name', 'rule_type', 'product_id' ), true ) ) {
					continue;
				}

				if ( is_array( $value ) ) {
					$value = wp_unslash( $value );
					$sanitized_rule[ $key ] = array_map( 'sanitize_text_field', $value );
					continue;
				}

				$value = wp_unslash( $value );

				if ( in_array( $key, $float_fields, true ) ) {
					$sanitized_rule[ $key ] = floatval( $value );
				} elseif ( in_array( $key, $int_fields, true ) ) {
					$sanitized_rule[ $key ] = absint( $value );
				} elseif ( in_array( $key, $bool_fields, true ) ) {
					$sanitized_rule[ $key ] = absint( $value ) > 0 ? 1 : 0;
				} elseif ( preg_match( '/(amount|adjustment|percentage)$/', $key ) ) {
					// Treat any additional amount/percentage fields as floats
					$sanitized_rule[ $key ] = floatval( $value );
				} else {
					$sanitized_rule[ $key ] = sanitize_text_field( $value );
				}
			}

			DynamicPricingManager::saveRule( $sanitized_rule );
		}
	}

	/**
	 * Save schedules data
	 *
	 * @param int $product_id Product ID
	 */
	private function saveSchedules( int $product_id ): void {
		// Get existing schedules
		$existing_schedules = ScheduleManager::getSchedules( $product_id );
		$existing_ids       = array_column( $existing_schedules, 'id' );
		$processed_ids      = array();
		$validation_errors  = array();

                // Validate existing schedules to ensure explicit values
		$required_fields = array( 'duration_min', 'capacity', 'lang', 'meeting_point_id', 'price_adult', 'price_child' );
		foreach ( $existing_schedules as $schedule ) {
			foreach ( $required_fields as $field ) {
				if ( $schedule->$field === null || $schedule->$field === '' ) {
					$validation_errors[] = sprintf(
						__( 'Schedule %1$d is missing required %2$s.', 'fp-esperienze' ),
						$schedule->id,
						$field
					);
					$processed_ids[]     = $schedule->id; // prevent deletion
					break;
				}
			}
		}

		// Process builder slots first if they exist
		$has_builder_slots = isset( $_POST['builder_slots'] ) && is_array( $_POST['builder_slots'] ) && ! empty( $_POST['builder_slots'] );

		if ( $has_builder_slots ) {
			// Add debug logging for builder slots processing
			error_log( "FP Esperienze: Processing builder slots for product {$product_id}" );
			$processed_ids = array_merge( $processed_ids, $this->processBuilderSlots( $product_id, $_POST['builder_slots'], $validation_errors ) );
		}

		// Process raw schedules ONLY if we don't have builder slots (to prevent conflicts)
		// Raw schedules are for advanced/legacy mode when not using the visual builder
		if ( ! $has_builder_slots && isset( $_POST['schedules'] ) && is_array( $_POST['schedules'] ) ) {
			// Add debug logging for raw schedules processing
			error_log( "FP Esperienze: Processing raw schedules for product {$product_id}" );
			$processed_ids = array_merge( $processed_ids, $this->processRawSchedules( $product_id, $_POST['schedules'], $validation_errors ) );
		}

		// Add debug logging for potential conflicts
		if ( $has_builder_slots && isset( $_POST['schedules'] ) && ! empty( $_POST['schedules'] ) ) {
			error_log( "FP Esperienze: WARNING - Both builder_slots and schedules data present for product {$product_id}, ignoring schedules to prevent conflicts" );
		}

		// Process event schedules
		if ( isset( $_POST['event_schedules'] ) && is_array( $_POST['event_schedules'] ) ) {
			error_log( "FP Esperienze: Processing event schedules for product {$product_id}" );
			$processed_ids = array_merge( $processed_ids, $this->processEventSchedules( $product_id, $_POST['event_schedules'], $validation_errors ) );
		}

		// Delete schedules that were removed
		$ids_to_delete = array_diff( $existing_ids, $processed_ids );
		foreach ( $ids_to_delete as $id ) {
			ScheduleManager::deleteSchedule( $id );
		}

		// Store validation feedback in transients for display
		if ( ! empty( $validation_errors ) ) {
			set_transient( "fp_schedule_validation_errors_{$product_id}", $validation_errors, 60 );
		}

                // Set success notice if schedules were saved
                if ( ! empty( $processed_ids ) ) {
                        set_transient( "fp_schedule_saved_{$product_id}", count( $processed_ids ), 60 );
                }

                // Sync product price meta based on current schedules
                $this->syncProductPriceMeta( $product_id );
        }

	/**
	 * Sync product price related meta fields from active schedules.
	 *
	 * @param int $product_id Product ID.
	 */
	private function syncProductPriceMeta( int $product_id ): void {
		$schedules    = ScheduleManager::getSchedules( $product_id );
		$adult_prices = array();
		$child_prices = array();

		foreach ( $schedules as $schedule ) {
			if ( isset( $schedule->price_adult ) && is_numeric( $schedule->price_adult ) ) {
				$adult_prices[] = (float) $schedule->price_adult;
			}

			if ( isset( $schedule->price_child ) && is_numeric( $schedule->price_child ) ) {
				$child_prices[] = (float) $schedule->price_child;
			}
		}

		$adult_base_price = $this->determineBaseSchedulePrice( $adult_prices );
		$child_base_price = $this->determineBaseSchedulePrice( $child_prices );

		if ( null !== $adult_base_price ) {
			update_post_meta( $product_id, '_experience_adult_price', $adult_base_price );

			$formatted_adult_price = $this->formatPriceForMeta( $adult_base_price );
			update_post_meta( $product_id, '_price', $formatted_adult_price );
			update_post_meta( $product_id, '_regular_price', $formatted_adult_price );
		} else {
			delete_post_meta( $product_id, '_experience_adult_price' );
			delete_post_meta( $product_id, '_price' );
			delete_post_meta( $product_id, '_regular_price' );
		}

		if ( null !== $child_base_price ) {
			update_post_meta( $product_id, '_experience_child_price', $child_base_price );
		} else {
			delete_post_meta( $product_id, '_experience_child_price' );
		}

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $product_id );
		}
	}

	/**
	 * Determine the base price from a list of schedule prices.
	 *
	 * Uses the lowest positive price when available, or 0 if all values are
	 * zero/negative. Returns null when there are no numeric prices.
	 *
	 * @param array $prices Collected price values.
	 * @return float|null
	 */
	private function determineBaseSchedulePrice( array $prices ): ?float {
		if ( empty( $prices ) ) {
			return null;
		}

		$positive_prices  = array();
		$has_non_positive = false;

		foreach ( $prices as $price ) {
			if ( $price > 0 ) {
				$positive_prices[] = $price;
			} else {
				$has_non_positive = true;
			}
		}

		if ( ! empty( $positive_prices ) ) {
			return min( $positive_prices );
		}

		if ( $has_non_positive ) {
			return 0.0;
		}

		return null;
	}

	/**
	 * Format a price value for storage in WooCommerce price meta fields.
	 *
	 * @param float $price Price value.
	 * @return string
	 */
	private function formatPriceForMeta( float $price ): string {
		if ( function_exists( 'wc_format_decimal' ) ) {
			$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;

			return wc_format_decimal( $price, $decimals );
		}

		return number_format( $price, 2, '.', '' );
	}


	/**
	 * Process builder slots and create individual schedule records
	 *
	 * @param int   $product_id Product ID
	 * @param array $builder_slots Builder slot data
	 * @param array &$validation_errors Reference to validation errors array
	 * @return array Array of processed schedule IDs
	 */
	private function processBuilderSlots( int $product_id, array $builder_slots, array &$validation_errors ): array {
		$processed_ids = array();

		foreach ( $builder_slots as $slot_index => $slot_data ) {
			// Validate required fields - be more specific about what's missing
			if ( empty( $slot_data['start_time'] ) ) {
				// Skip empty slots silently - they might be from auto-generated empty rows
				continue;
			}

			if ( empty( $slot_data['days'] ) || ! is_array( $slot_data['days'] ) ) {
				// Skip slots without selected days
				continue;
			}

			// Sanitize and validate time (allow optional seconds)
			$start_time = trim( $slot_data['start_time'] );
			if ( preg_match( '/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $start_time, $m ) ) {
				$start_time = sprintf( '%02d:%02d', $m[1], $m[2] );
			} else {
				// Add debug information if logging is enabled
				if ( apply_filters( 'fp_esperienze_debug_validation', false ) ) {
					error_log( "FP Esperienze: Invalid time format for slot {$slot_index}: '{$start_time}' (original: '{$slot_data['start_time']}')" );
				}

				$validation_errors[] = sprintf(
					__( 'Time slot %1$d: Invalid time format "%2$s". Use HH:MM format (e.g., 09:30).', 'fp-esperienze' ),
					$slot_index + 1,
					esc_html( $slot_data['start_time'] ) // Show original for user feedback
				);
				continue;
			}

			// Require all slot fields
			$required_fields = array(
				'duration_min'     => __( 'duration', 'fp-esperienze' ),
				'capacity'         => __( 'capacity', 'fp-esperienze' ),
				'lang'             => __( 'language', 'fp-esperienze' ),
				'meeting_point_id' => __( 'meeting point', 'fp-esperienze' ),
				'price_adult'      => __( 'adult price', 'fp-esperienze' ),
				'price_child'      => __( 'child price', 'fp-esperienze' ),
			);

			$missing = array();
			foreach ( $required_fields as $field_key => $label ) {
				if ( ! isset( $slot_data[ $field_key ] ) || $slot_data[ $field_key ] === '' ) {
					$missing[] = $label;
				}
			}

			if ( ! empty( $missing ) ) {
				$validation_errors[] = sprintf(
					__( 'Time slot %1$d: Missing %2$s.', 'fp-esperienze' ),
					$slot_index + 1,
					implode( ', ', $missing )
				);
				continue;
			}

			$duration_override      = max( 1, (int) $slot_data['duration_min'] );
			$capacity_override      = max( 1, (int) $slot_data['capacity'] );
			$lang_override          = sanitize_text_field( $slot_data['lang'] );
			$meeting_point_override = (int) $slot_data['meeting_point_id'];
			$price_adult_override   = max( 0, (float) $slot_data['price_adult'] );
			$price_child_override   = max( 0, (float) $slot_data['price_child'] );

			// Track existing schedule IDs for this slot
			$existing_slot_ids  = ! empty( $slot_data['schedule_ids'] ) ? array_map( 'intval', $slot_data['schedule_ids'] ) : array();
			$slot_processed_ids = array();

			// Create or update schedule for each selected day
			foreach ( $slot_data['days'] as $day_of_week ) {
				$day_of_week = (int) $day_of_week;

				// Prepare schedule data
				$schedule_data = array(
					'product_id'       => $product_id,
					'day_of_week'      => $day_of_week,
					'start_time'       => $start_time, // Use the sanitized time from validation
					'duration_min'     => $duration_override,
					'capacity'         => $capacity_override,
					'lang'             => $lang_override,
					'meeting_point_id' => $meeting_point_override,
					'price_adult'      => $price_adult_override,
					'price_child'      => $price_child_override,
					'is_active'        => 1,
				);

				// Try to find existing schedule for this day/time combination
				$existing_schedule_id = null;
				foreach ( $existing_slot_ids as $id ) {
					$existing = ScheduleManager::getSchedule( $id );
					if ( $existing && $existing->day_of_week == $day_of_week && $existing->start_time == $start_time ) {
						$existing_schedule_id = $id;
						break;
					}
				}

				if ( $existing_schedule_id ) {
					// Update existing schedule
					ScheduleManager::updateSchedule( $existing_schedule_id, $schedule_data );
					$slot_processed_ids[] = $existing_schedule_id;
				} else {
					// Create new schedule
					$new_id = ScheduleManager::createSchedule( $schedule_data );
					if ( $new_id ) {
						$slot_processed_ids[] = $new_id;
					}
				}
			}

			$processed_ids = array_merge( $processed_ids, $slot_processed_ids );
		}

		return $processed_ids;
	}

	/**
	 * Process raw schedules (advanced mode)
	 *
	 * @param int   $product_id Product ID
	 * @param array $schedules Raw schedule data
	 * @param array &$validation_errors Reference to validation errors array
	 * @return array Array of processed schedule IDs
	 */
	private function processRawSchedules( int $product_id, array $schedules, array &$validation_errors ): array {
		$processed_ids   = array();
		$discarded_count = 0;

		foreach ( $schedules as $index => $schedule_data ) {
			// Validate required fields
			if ( empty( $schedule_data['day_of_week'] ) || empty( $schedule_data['start_time'] ) ) {
				++$discarded_count;
				continue;
			}

			// Validate time format (HH:MM)
			if ( ! preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $schedule_data['start_time'] ) ) {
				$validation_errors[] = sprintf( __( 'Row %d: Invalid time format. Use HH:MM format.', 'fp-esperienze' ), $index + 1 );
				++$discarded_count;
				continue;
			}

			// Ensure all fields are provided
			$required_fields = array(
				'duration_min'     => __( 'duration', 'fp-esperienze' ),
				'capacity'         => __( 'capacity', 'fp-esperienze' ),
				'lang'             => __( 'language', 'fp-esperienze' ),
				'meeting_point_id' => __( 'meeting point', 'fp-esperienze' ),
				'price_adult'      => __( 'adult price', 'fp-esperienze' ),
				'price_child'      => __( 'child price', 'fp-esperienze' ),
			);

			$missing = array();
			foreach ( $required_fields as $field_key => $label ) {
				if ( ! isset( $schedule_data[ $field_key ] ) || $schedule_data[ $field_key ] === '' ) {
					$missing[] = $label;
				}
			}

			if ( ! empty( $missing ) ) {
				$validation_errors[] = sprintf(
					__( 'Row %1$d: Missing %2$s.', 'fp-esperienze' ),
					$index + 1,
					implode( ', ', $missing )
				);
				++$discarded_count;
				continue;
			}

			$schedule_id = ! empty( $schedule_data['id'] ) ? (int) $schedule_data['id'] : 0;

			// Prepare data for raw schedule
			$data = array(
				'product_id'       => $product_id,
				'day_of_week'      => (int) $schedule_data['day_of_week'],
				'start_time'       => sanitize_text_field( $schedule_data['start_time'] ),
				'duration_min'     => (int) $schedule_data['duration_min'],
				'capacity'         => (int) $schedule_data['capacity'],
				'lang'             => sanitize_text_field( $schedule_data['lang'] ),
				'meeting_point_id' => (int) $schedule_data['meeting_point_id'],
				'price_adult'      => (float) $schedule_data['price_adult'],
				'price_child'      => (float) $schedule_data['price_child'],
				'is_active'        => 1,
			);

			if ( $schedule_id > 0 ) {
				// Update existing schedule
				ScheduleManager::updateSchedule( $schedule_id, $data );
				$processed_ids[] = $schedule_id;
			} else {
				// Create new schedule
				$new_id = ScheduleManager::createSchedule( $data );
				if ( $new_id ) {
					$processed_ids[] = $new_id;
				}
			}
		}

		if ( $discarded_count > 0 ) {
			set_transient( "fp_schedule_discarded_{$product_id}", $discarded_count, 60 );
		}

		return $processed_ids;
	}

	/**
	 * Process event schedules for fixed-date events
	 *
	 * @param int   $product_id Product ID
	 * @param array $event_schedules Event schedule data grouped by date
	 * @param array &$validation_errors Reference to validation errors array
	 * @return array Array of processed schedule IDs
	 */
	private function processEventSchedules( int $product_id, array $event_schedules, array &$validation_errors ): array {
		$processed_ids = array();

		foreach ( $event_schedules as $date => $timeslots ) {
			// Validate date format
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				$validation_errors[] = sprintf( __( 'Invalid date format: %s', 'fp-esperienze' ), esc_html( $date ) );
				continue;
			}

			$is_past_date = strtotime( $date ) < strtotime( 'today' );
			$has_new_past_slot = false;

			if ( $is_past_date ) {
				foreach ( $timeslots as $legacy_slot ) {
					$legacy_id    = ! empty( $legacy_slot['id'] ) ? (int) $legacy_slot['id'] : 0;
					$legacy_start = isset( $legacy_slot['start_time'] ) ? trim( (string) $legacy_slot['start_time'] ) : '';

					if ( $legacy_id > 0 ) {
						$processed_ids[] = $legacy_id;
					}

					if ( $legacy_id <= 0 && $legacy_start !== '' ) {
						$has_new_past_slot = true;
					}
				}

				if ( $has_new_past_slot ) {
					$validation_errors[] = sprintf( __( 'Event date cannot be in the past: %s', 'fp-esperienze' ), esc_html( $date ) );
				}
			}

			foreach ( $timeslots as $slot_index => $slot_data ) {
				// Skip empty slots
				if ( empty( $slot_data['start_time'] ) ) {
					continue;
				}

				if ( $is_past_date && empty( $slot_data['id'] ) ) {
					// Prevent creating new slots for past dates but keep legacy ones
					continue;
				}

				// Validate required fields
				$required_fields = array(
					'start_time'       => __( 'start time', 'fp-esperienze' ),
					'duration_min'     => __( 'duration', 'fp-esperienze' ),
					'capacity'         => __( 'capacity', 'fp-esperienze' ),
					'lang'             => __( 'language', 'fp-esperienze' ),
					'meeting_point_id' => __( 'meeting point', 'fp-esperienze' ),
					'price_adult'      => __( 'adult price', 'fp-esperienze' ),
					'price_child'      => __( 'child price', 'fp-esperienze' ),
				);

				$missing = array();
				foreach ( $required_fields as $field_key => $label ) {
					if ( ! isset( $slot_data[ $field_key ] ) || $slot_data[ $field_key ] === '' ) {
						$missing[] = $label;
					}
				}

				if ( ! empty( $missing ) ) {
					$validation_errors[] = sprintf(
						__( 'Event %1$s, slot %2$d: Missing %3$s.', 'fp-esperienze' ),
						esc_html( $date ),
						$slot_index + 1,
						implode( ', ', $missing )
					);
					continue;
				}

				// Validate time format
				$start_time = trim( $slot_data['start_time'] );
				if ( ! preg_match( '/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $start_time, $m ) ) {
					$validation_errors[] = sprintf(
						__( 'Event %1$s, slot %2$d: Invalid time format "%3$s". Use HH:MM format.', 'fp-esperienze' ),
						esc_html( $date ),
						$slot_index + 1,
						esc_html( $start_time )
					);
					continue;
				}

				// Normalize time format
				$start_time = sprintf( '%02d:%02d', $m[1], $m[2] );

                               // Prepare schedule data
                               $schedule_data = array(
                                       'product_id'       => $product_id,
                                       'schedule_type'    => 'fixed',
                                       'event_date'       => $date,
					'start_time'       => $start_time,
					'duration_min'     => max( 1, (int) $slot_data['duration_min'] ),
					'capacity'         => max( 1, (int) $slot_data['capacity'] ),
					'lang'             => sanitize_text_field( $slot_data['lang'] ),
					'meeting_point_id' => (int) $slot_data['meeting_point_id'],
					'price_adult'      => max( 0, (float) $slot_data['price_adult'] ),
					'price_child'      => max( 0, (float) $slot_data['price_child'] ),
					'is_active'        => 1,
				);

                               $schedule_id = ! empty( $slot_data['id'] ) ? (int) $slot_data['id'] : 0;

                               if ( $schedule_id > 0 ) {
                                       // Update existing schedule
                                       ScheduleManager::updateSchedule( $schedule_id, $schedule_data );
                                       $processed_ids[] = $schedule_id;
                               } else {
                                       // Create new schedule
                                       $new_id = ScheduleManager::createSchedule( $schedule_data );
                                       if ( $new_id ) {
                                               $processed_ids[] = $new_id;
                                       }
                               }
                       }
               }

               return array_values( array_unique( $processed_ids ) );
       }

	/**
	 * Save overrides data
	 *
	 * @param int $product_id Product ID
	 */
	private function saveOverrides( int $product_id ): void {
		// Get existing overrides to track which ones should be deleted
		$existing_overrides = OverrideManager::getOverrides( $product_id );
		$existing_dates     = array_map(
			function ( $override ) {
				return $override->date;
			},
			$existing_overrides
		);

		$submitted_dates = array();

		// Process submitted overrides
		if ( isset( $_POST['overrides'] ) && is_array( $_POST['overrides'] ) ) {
			foreach ( $_POST['overrides'] as $override_data ) {
				if ( empty( $override_data['date'] ) ) {
					continue;
				}

				$date              = sanitize_text_field( $override_data['date'] );
				$submitted_dates[] = $date;

				$price_override = array();
				if ( ! empty( $override_data['price_adult'] ) ) {
					$price_override['adult'] = (float) $override_data['price_adult'];
				}
				if ( ! empty( $override_data['price_child'] ) ) {
					$price_override['child'] = (float) $override_data['price_child'];
				}

				$data = array(
					'product_id'          => $product_id,
					'date'                => $date,
					'is_closed'           => ! empty( $override_data['is_closed'] ) ? 1 : 0,
					'capacity_override'   => ! empty( $override_data['capacity_override'] ) ? (int) $override_data['capacity_override'] : null,
					'price_override_json' => ! empty( $price_override ) ? $price_override : null,
					'reason'              => sanitize_text_field( $override_data['reason'] ?? '' ),
				);

				OverrideManager::saveOverride( $data );
			}
		}

		// Delete overrides that were removed from the form
		$dates_to_delete = array_diff( $existing_dates, $submitted_dates );
		foreach ( $dates_to_delete as $date ) {
			OverrideManager::deleteOverride( $product_id, $date );
		}
	}

	/**
	 * Save extras
	 *
	 * @param int $product_id Product ID
	 */
	private function saveExtras( int $product_id ): void {
		$selected_extras = isset( $_POST['fp_product_extras'] ) ? array_map( 'absint', $_POST['fp_product_extras'] ) : array();
		ExtraManager::updateProductExtras( $product_id, $selected_extras );
	}

	/**
	 * Get meeting points for select dropdown
	 *
	 * @return array
	 */
	private function getMeetingPoints(): array {
		return MeetingPointManager::getMeetingPointsForSelect();
	}

	/**
	 * Render dynamic pricing panel
	 *
	 * @param int $product_id Product ID
	 */
	private function renderDynamicPricingPanel( int $product_id ): void {
		$rules = DynamicPricingManager::getProductRules( $product_id, false );
		wp_nonce_field( 'fp_pricing_nonce', 'fp_pricing_nonce' );
		?>
		
                <div class="options_group fp-pricing-options-group">
                        <h4 class="fp-pricing-group__title"><?php _e( 'Dynamic Pricing Rules', 'fp-esperienze' ); ?></h4>
                        <p class="fp-pricing-group__description"><?php _e( 'Combine booking windows, group sizes, and customer types to automatically adjust prices.', 'fp-esperienze' ); ?></p>

                        <div id="fp-pricing-rules-container">
                                <?php
                                foreach ( $rules as $index => $rule ) {
                                        $this->renderPricingRuleRow( $rule, $index );
                                }
                                ?>
                        </div>

                        <template id="fp-pricing-rule-template">
                                <?php echo $this->getPricingRuleRowTemplate( '__INDEX__' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </template>

                        <button type="button" id="fp-add-pricing-rule" class="button button-secondary fp-pricing-add-rule">
                                <?php _e( 'Add Pricing Rule', 'fp-esperienze' ); ?>
                        </button>
                </div>

                <div class="options_group fp-pricing-options-group fp-pricing-options-group--preview">
                        <h4 class="fp-pricing-group__title"><?php _e( 'Pricing Preview', 'fp-esperienze' ); ?></h4>
                        <p class="fp-pricing-group__description"><?php _e( 'Test the rules above by simulating bookings before publishing your changes.', 'fp-esperienze' ); ?></p>

                        <div class="fp-pricing-preview">
                                <div class="fp-preview-inputs">
                                        <div class="form-field fp-field">
                                                <label for="fp-preview-booking-date"><?php _e( 'Booking Date', 'fp-esperienze' ); ?></label>
                                                <input type="date" id="fp-preview-booking-date" value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
                                        </div>
                                        <div class="form-field fp-field">
                                                <label for="fp-preview-purchase-date"><?php _e( 'Purchase Date', 'fp-esperienze' ); ?></label>
                                                <input type="date" id="fp-preview-purchase-date" value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
                                        </div>
                                        <div class="form-field fp-field">
                                                <label for="fp-preview-qty-adult"><?php _e( 'Adults', 'fp-esperienze' ); ?></label>
                                                <input type="number" id="fp-preview-qty-adult" value="2" min="0">
                                        </div>
                                        <div class="form-field fp-field">
                                                <label for="fp-preview-qty-child"><?php _e( 'Children', 'fp-esperienze' ); ?></label>
                                                <input type="number" id="fp-preview-qty-child" value="0" min="0">
                                        </div>
                                        <div class="form-field fp-field fp-preview-submit">
                                                <label class="screen-reader-text" for="fp-preview-calculate"><?php _e( 'Calculate pricing preview', 'fp-esperienze' ); ?></label>
                                                <button type="button" id="fp-preview-calculate" class="button button-primary fp-pricing-preview__button">
                                                        <?php _e( 'Calculate', 'fp-esperienze' ); ?>
                                                </button>
                                        </div>
                                </div>

                                <div id="fp-preview-results" class="fp-preview-results" role="region" aria-live="polite"></div>
                        </div>
                </div>

                <script type="text/javascript">
                        jQuery(document).ready(function($) {
                                var ruleIndex = <?php echo count( $rules ); ?>;
                                var $rulesContainer = $('#fp-pricing-rules-container');
                                var ruleTemplate = document.getElementById('fp-pricing-rule-template');

                                // Add pricing rule
                                $('#fp-add-pricing-rule').on('click', function() {
                                        if (!ruleTemplate) {
                                                return;
                                        }

                                        var templateHtml = ruleTemplate.innerHTML.replace(/__INDEX__/g, ruleIndex);
                                        var $newRule = $(templateHtml.trim());

                                        $rulesContainer.append($newRule);
                                        $newRule.find('.fp-rule-type').trigger('change');
                                        $(document.body).trigger('init_tooltips');

                                        ruleIndex++;
                                });

                                // Remove pricing rule
                                $(document).on('click', '.fp-remove-pricing-rule', function() {
                                        $(this).closest('.fp-pricing-rule-row').remove();
                                });
				
				// Preview calculation
				$('#fp-preview-calculate').click(function() {
					var data = {
						action: 'fp_preview_pricing',
						product_id: <?php echo $product_id; ?>,
						booking_date: $('#fp-preview-booking-date').val(),
						purchase_date: $('#fp-preview-purchase-date').val(),
						qty_adult: $('#fp-preview-qty-adult').val(),
						qty_child: $('#fp-preview-qty-child').val(),
						nonce: $('#fp_pricing_nonce').val()
					};
					
					$.post(ajaxurl, data, function(response) {
						if (response.success) {
							var result = response.data;
							var html = '<h5><?php _e( 'Price Breakdown', 'fp-esperienze' ); ?></h5>';
							
							html += '<table class="widefat">';
							html += '<tr><td><?php _e( 'Base Adult Price', 'fp-esperienze' ); ?></td><td>' + result.base_prices.adult + ' <?php echo get_woocommerce_currency_symbol(); ?></td></tr>';
							html += '<tr><td><?php _e( 'Base Child Price', 'fp-esperienze' ); ?></td><td>' + result.base_prices.child + ' <?php echo get_woocommerce_currency_symbol(); ?></td></tr>';
							html += '<tr><td><?php _e( 'Final Adult Price', 'fp-esperienze' ); ?></td><td>' + result.final_prices.adult + ' <?php echo get_woocommerce_currency_symbol(); ?></td></tr>';
							html += '<tr><td><?php _e( 'Final Child Price', 'fp-esperienze' ); ?></td><td>' + result.final_prices.child + ' <?php echo get_woocommerce_currency_symbol(); ?></td></tr>';
							html += '<tr><td><strong><?php _e( 'Total Base', 'fp-esperienze' ); ?></strong></td><td><strong>' + result.total.base + ' <?php echo get_woocommerce_currency_symbol(); ?></strong></td></tr>';
							html += '<tr><td><strong><?php _e( 'Total Final', 'fp-esperienze' ); ?></strong></td><td><strong>' + result.total.final + ' <?php echo get_woocommerce_currency_symbol(); ?></strong></td></tr>';
							html += '</table>';
							
							if (result.applied_rules.adult.length > 0 || result.applied_rules.child.length > 0) {
								html += '<h5><?php _e( 'Applied Rules', 'fp-esperienze' ); ?></h5>';
								// Add applied rules details here
							}
							
							$('#fp-preview-results').html(html);
						} else {
							$('#fp-preview-results').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
						}
					});
				});
				
				// Rule type change handler
				$(document).on('change', '.fp-rule-type', function() {
					var ruleType = $(this).val();
					var container = $(this).closest('.fp-pricing-rule-row');
					
					// Hide all conditional fields first
					container.find('.fp-rule-field').hide();
					
					// Show relevant fields based on rule type
					switch(ruleType) {
						case 'seasonal':
							container.find('.fp-field-dates').show();
							break;
						case 'weekend_weekday':
							container.find('.fp-field-applies-to').show();
							break;
						case 'early_bird':
							container.find('.fp-field-days-before').show();
							break;
						case 'group':
							container.find('.fp-field-min-participants').show();
							break;
					}
				});
				
				// Trigger change event for existing rules
                                $('.fp-rule-type').trigger('change');
                        });
                </script>

                <?php
        }

	/**
	 * Render a single pricing rule row
	 *
	 * @param object $rule Rule object
	 * @param int    $index Row index
	 */
	private function renderPricingRuleRow( $rule, int $index ): void {
		echo $this->getPricingRuleRowTemplate( $index, $rule );
	}

	/**
	 * Get pricing rule row template
	 *
	 * @param mixed       $index Row index or placeholder
	 * @param object|null $rule Rule object
	 * @return string HTML template
	 */
	private function getPricingRuleRowTemplate( mixed $index, ?object $rule = null ): string {
		ob_start();
                ?>
                <?php
                $rule_name_tip      = function_exists( 'wc_help_tip' ) ? wc_help_tip( __( 'Only administrators can see this name. Keep it short and descriptive to identify the rule later.', 'fp-esperienze' ) ) : '';
                $rule_type_tip      = function_exists( 'wc_help_tip' ) ? wc_help_tip( __( 'The selected type controls which conditional fields appear below.', 'fp-esperienze' ) ) : '';
                $rule_priority_tip  = function_exists( 'wc_help_tip' ) ? wc_help_tip( __( 'Lower numbers run first when multiple rules are eligible.', 'fp-esperienze' ) ) : '';
                ?>
                <div class="fp-pricing-rule-row" data-index="<?php echo esc_attr( $index ); ?>">
                        <input type="hidden" name="pricing_rules[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $rule->id ?? '' ); ?>">

                        <div class="fp-pricing-rule-row__header">
                                <div class="form-field fp-field fp-field--rule-name">
                                        <label for="fp-pricing-rule-name-<?php echo esc_attr( $index ); ?>"><?php _e( 'Rule Name', 'fp-esperienze' ); ?></label>
                                        <?php echo wp_kses_post( $rule_name_tip ); ?>
                                        <input id="fp-pricing-rule-name-<?php echo esc_attr( $index ); ?>"
                                                type="text"
                                                name="pricing_rules[<?php echo esc_attr( $index ); ?>][rule_name]"
                                                value="<?php echo esc_attr( $rule->rule_name ?? '' ); ?>"
                                                placeholder="<?php esc_attr_e( 'Rule Name', 'fp-esperienze' ); ?>"
                                                required>
                                </div>

                                <div class="form-field fp-field fp-field--rule-type">
                                        <label for="fp-pricing-rule-type-<?php echo esc_attr( $index ); ?>"><?php _e( 'Type', 'fp-esperienze' ); ?></label>
                                        <?php echo wp_kses_post( $rule_type_tip ); ?>
                                        <select id="fp-pricing-rule-type-<?php echo esc_attr( $index ); ?>" name="pricing_rules[<?php echo esc_attr( $index ); ?>][rule_type]" class="fp-rule-type" required>
                                                <option value=""><?php _e( 'Select Type', 'fp-esperienze' ); ?></option>
                                                <option value="seasonal" <?php selected( $rule->rule_type ?? '', 'seasonal' ); ?>><?php _e( 'Seasonal', 'fp-esperienze' ); ?></option>
                                                <option value="weekend_weekday" <?php selected( $rule->rule_type ?? '', 'weekend_weekday' ); ?>><?php _e( 'Weekend/Weekday', 'fp-esperienze' ); ?></option>
                                                <option value="early_bird" <?php selected( $rule->rule_type ?? '', 'early_bird' ); ?>><?php _e( 'Early Bird', 'fp-esperienze' ); ?></option>
                                                <option value="group" <?php selected( $rule->rule_type ?? '', 'group' ); ?>><?php _e( 'Group Discount', 'fp-esperienze' ); ?></option>
                                        </select>
                                </div>

                                <div class="form-field fp-field fp-pricing-priority">
                                        <label for="fp-pricing-rule-priority-<?php echo esc_attr( $index ); ?>"><?php _e( 'Priority', 'fp-esperienze' ); ?></label>
                                        <?php echo wp_kses_post( $rule_priority_tip ); ?>
                                        <input id="fp-pricing-rule-priority-<?php echo esc_attr( $index ); ?>"
                                                type="number"
                                                name="pricing_rules[<?php echo esc_attr( $index ); ?>][priority]"
                                                value="<?php echo esc_attr( $rule->priority ?? 0 ); ?>"
                                                min="0"
                                                step="1">
                                </div>

                                <div class="form-field fp-field fp-pricing-toggle">
                                        <label class="fp-toggle-control">
                                                <input type="checkbox" name="pricing_rules[<?php echo esc_attr( $index ); ?>][is_active]"
                                                                value="1" <?php checked( $rule->is_active ?? 1, 1 ); ?>>
                                                <span><?php _e( 'Active', 'fp-esperienze' ); ?></span>
                                        </label>
                                </div>

                                <div class="form-field fp-field fp-pricing-actions">
                                        <button type="button"
                                                class="button-link-delete fp-remove-pricing-rule"
                                                aria-label="<?php esc_attr_e( 'Remove pricing rule', 'fp-esperienze' ); ?>">
                                                <?php _e( 'Remove', 'fp-esperienze' ); ?>
                                        </button>
                                </div>
                        </div>

                        <!-- Rule-specific fields -->
                        <div class="form-field fp-field fp-rule-field fp-field-dates">
                                <label for="fp-pricing-date-start-<?php echo esc_attr( $index ); ?>"><?php _e( 'Date Range', 'fp-esperienze' ); ?></label>
                                <div class="fp-pricing-adjustments">
                                        <div class="form-field fp-field">
                                                <label class="screen-reader-text" for="fp-pricing-date-start-<?php echo esc_attr( $index ); ?>"><?php _e( 'Start Date', 'fp-esperienze' ); ?></label>
                                                <input id="fp-pricing-date-start-<?php echo esc_attr( $index ); ?>"
                                                        type="date"
                                                        name="pricing_rules[<?php echo esc_attr( $index ); ?>][date_start]"
                                                        value="<?php echo esc_attr( $rule->date_start ?? '' ); ?>"
                                                        placeholder="<?php esc_attr_e( 'Start Date', 'fp-esperienze' ); ?>">
                                        </div>
                                        <div class="form-field fp-field">
                                                <label class="screen-reader-text" for="fp-pricing-date-end-<?php echo esc_attr( $index ); ?>"><?php _e( 'End Date', 'fp-esperienze' ); ?></label>
                                                <input id="fp-pricing-date-end-<?php echo esc_attr( $index ); ?>"
                                                        type="date"
                                                        name="pricing_rules[<?php echo esc_attr( $index ); ?>][date_end]"
                                                        value="<?php echo esc_attr( $rule->date_end ?? '' ); ?>"
                                                        placeholder="<?php esc_attr_e( 'End Date', 'fp-esperienze' ); ?>">
                                        </div>
                                </div>
                        </div>

                        <div class="form-field fp-field fp-rule-field fp-field-applies-to">
                                <label for="fp-pricing-applies-to-<?php echo esc_attr( $index ); ?>"><?php _e( 'Applies To', 'fp-esperienze' ); ?></label>
                                <select id="fp-pricing-applies-to-<?php echo esc_attr( $index ); ?>" name="pricing_rules[<?php echo esc_attr( $index ); ?>][applies_to]">
                                        <option value=""><?php _e( 'Select...', 'fp-esperienze' ); ?></option>
                                        <option value="weekend" <?php selected( $rule->applies_to ?? '', 'weekend' ); ?>><?php _e( 'Weekend', 'fp-esperienze' ); ?></option>
                                        <option value="weekday" <?php selected( $rule->applies_to ?? '', 'weekday' ); ?>><?php _e( 'Weekday', 'fp-esperienze' ); ?></option>
                                </select>
                        </div>

                        <div class="form-field fp-field fp-rule-field fp-field-days-before">
                                <label for="fp-pricing-days-before-<?php echo esc_attr( $index ); ?>"><?php _e( 'Days Before', 'fp-esperienze' ); ?></label>
                                <input id="fp-pricing-days-before-<?php echo esc_attr( $index ); ?>"
                                        type="number"
                                        name="pricing_rules[<?php echo esc_attr( $index ); ?>][days_before]"
                                        value="<?php echo esc_attr( $rule->days_before ?? '' ); ?>"
                                        placeholder="<?php esc_attr_e( 'Days', 'fp-esperienze' ); ?>"
                                        min="1">
                        </div>

                        <div class="form-field fp-field fp-rule-field fp-field-min-participants">
                                <label for="fp-pricing-min-participants-<?php echo esc_attr( $index ); ?>"><?php _e( 'Minimum Participants', 'fp-esperienze' ); ?></label>
                                <input id="fp-pricing-min-participants-<?php echo esc_attr( $index ); ?>"
                                        type="number"
                                        name="pricing_rules[<?php echo esc_attr( $index ); ?>][min_participants]"
                                        value="<?php echo esc_attr( $rule->min_participants ?? '' ); ?>"
                                        placeholder="<?php esc_attr_e( 'Min Participants', 'fp-esperienze' ); ?>"
                                        min="1">
                        </div>

                        <!-- Adjustment fields -->
                        <div class="fp-pricing-adjustments">
                                <div class="form-field fp-field">
                                        <label for="fp-pricing-adjustment-type-<?php echo esc_attr( $index ); ?>"><?php _e( 'Adjustment Type', 'fp-esperienze' ); ?></label>
                                        <select id="fp-pricing-adjustment-type-<?php echo esc_attr( $index ); ?>" name="pricing_rules[<?php echo esc_attr( $index ); ?>][adjustment_type]">
                                                <option value="percentage" <?php selected( $rule->adjustment_type ?? 'percentage', 'percentage' ); ?>><?php _e( 'Percentage (%)', 'fp-esperienze' ); ?></option>
                                                <option value="fixed_amount" <?php selected( $rule->adjustment_type ?? 'percentage', 'fixed_amount' ); ?>><?php _e( 'Fixed Amount', 'fp-esperienze' ); ?></option>
                                        </select>
                                </div>

                                <div class="form-field fp-field">
                                        <label for="fp-pricing-adult-adjustment-<?php echo esc_attr( $index ); ?>"><?php _e( 'Adult Adjustment', 'fp-esperienze' ); ?></label>
                                        <input id="fp-pricing-adult-adjustment-<?php echo esc_attr( $index ); ?>"
                                                type="number"
                                                name="pricing_rules[<?php echo esc_attr( $index ); ?>][adult_adjustment]"
                                                value="<?php echo esc_attr( $rule->adult_adjustment ?? 0 ); ?>"
                                                step="0.01">
                                </div>

                                <div class="form-field fp-field">
                                        <label for="fp-pricing-child-adjustment-<?php echo esc_attr( $index ); ?>"><?php _e( 'Child Adjustment', 'fp-esperienze' ); ?></label>
                                        <input id="fp-pricing-child-adjustment-<?php echo esc_attr( $index ); ?>"
                                                type="number"
                                                name="pricing_rules[<?php echo esc_attr( $index ); ?>][child_adjustment]"
                                                value="<?php echo esc_attr( $rule->child_adjustment ?? 0 ); ?>"
                                                step="0.01">
                                </div>
                        </div>
                </div>
                <?php
                return ob_get_clean();
        }

	/**
	 * Show schedule validation notices
	 */
	public function showScheduleValidationNotices(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'product' ) {
			return;
		}

		$product_id = get_the_ID();
		if ( ! $product_id ) {
			return;
		}

		// Check for validation errors
		$validation_errors = get_transient( "fp_schedule_validation_errors_{$product_id}" );
		if ( $validation_errors ) {
			echo '<div class="notice notice-error"><p>';
			echo '<strong>' . __( 'Schedule Validation Errors:', 'fp-esperienze' ) . '</strong><br>';
			foreach ( $validation_errors as $error ) {
				echo '• ' . esc_html( $error ) . '<br>';
			}
			echo '</p></div>';
			delete_transient( "fp_schedule_validation_errors_{$product_id}" );
		}

		// Check for discarded schedules
		$discarded_count = get_transient( "fp_schedule_discarded_{$product_id}" );
		if ( $discarded_count ) {
			echo '<div class="notice notice-warning"><p>';
			printf(
				_n( '%d invalid schedule was discarded.', '%d invalid schedules were discarded.', $discarded_count, 'fp-esperienze' ),
				$discarded_count
			);
			echo '</p></div>';
			delete_transient( "fp_schedule_discarded_{$product_id}" );
		}

		// Check for successful saves
		$saved_count = get_transient( "fp_schedule_saved_{$product_id}" );
		if ( $saved_count ) {
			echo '<div class="notice notice-success"><p>';
			printf(
				_n( '%d schedule saved successfully.', '%d schedules saved successfully.', $saved_count, 'fp-esperienze' ),
				$saved_count
			);
			echo '</p></div>';
			delete_transient( "fp_schedule_saved_{$product_id}" );
		}
	}

	/**
	 * Add experience product fields to general tab for better admin integration
	 */
       public function addExperienceProductFields(): void {
               global $product_object;

               $product_id    = 0;
               $default_value = '';

               if ( $product_object instanceof \WC_Product ) {
                       $product_id = $product_object->get_id();
               }

               if ( $product_id ) {
                       $default_value = get_post_meta( $product_id, '_experience_duration', true );

                       if ( '' !== $default_value ) {
                               $default_value = absint( $default_value );

                               if ( $default_value <= 0 ) {
                                       $default_value = '';
                               }
                       }
               }

               echo '<div class="options_group show_if_experience">';

               woocommerce_wp_text_input(
                       array(
                               'id'                => '_experience_duration',
                               'name'              => '_experience_duration',
                               'label'             => __( 'Duration (minutes)', 'fp-esperienze' ),
                               'placeholder'       => '60',
                               'desc_tip'          => true,
                               'description'       => __( 'Experience duration in minutes', 'fp-esperienze' ),
                               'type'              => 'number',
                               'custom_attributes' => array(
                                       'step' => '1',
                                       'min'  => '1',
                               ),
                               'value'             => $default_value,
                       )
               );

               echo '</div>';
       }

	/**
	 * Enqueue admin scripts for product edit pages
	 */
	public function enqueueAdminScripts( $hook ): void {
		// Only load on product edit pages
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ) ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'product' ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );

		wp_enqueue_script(
			'fp-esperienze-product-admin',
			FP_ESPERIENZE_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'wc-admin-product-meta-boxes' ),
			FP_ESPERIENZE_VERSION,
			true
		);

                $admin_data = array(
                        'ajax_url'            => admin_url( 'admin-ajax.php' ),
                        'rest_url'            => get_rest_url(),
                        'experience_rest_url' => get_rest_url( null, 'fp-exp/v1/' ),
                        'rest_namespace'      => 'fp-exp/v1/',
                        'nonce'               => wp_create_nonce( 'fp_esperienze_admin_nonce' ),
                        'banner_offset'       => apply_filters( 'fp_esperienze_banner_offset', 20 ),
                        'plugin_url'          => FP_ESPERIENZE_PLUGIN_URL,
                        'strings'             => array(
                                'confirm_remove_override' => __( 'Are you sure you want to remove this date override?', 'fp-esperienze' ),
                                'distant_date_warning'    => __( 'This date is very far in the future. Please verify it\'s correct.', 'fp-esperienze' ),
                        ),
                );

		$experience_strings = array(
			'experience_type'           => __( 'Experience', 'fp-esperienze' ),
			'select_date'               => __( 'Select Date', 'fp-esperienze' ),
			'loading'                   => __( 'Loading...', 'fp-esperienze' ),
			'unsaved_changes'           => __( 'You have unsaved changes. Are you sure you want to leave?', 'fp-esperienze' ),
			'validation_error'          => __( 'Please fix the validation errors before saving.', 'fp-esperienze' ),
                        'event_schedules'           => __( 'Event dates & times', 'fp-esperienze' ),
                        'recurring_schedules'       => __( 'Recurring schedule', 'fp-esperienze' ),
                        'schedule_overrides'        => __( 'Schedule exceptions', 'fp-esperienze' ),
			'confirm_remove_event_date' => __( 'Are you sure you want to remove this event date and all its time slots?', 'fp-esperienze' ),
			'event_date_exists'         => __( 'This event date already exists.', 'fp-esperienze' ),
			'add_time_slot'             => __( 'Add Time Slot', 'fp-esperienze' ),
			'remove_date'               => __( 'Remove Date', 'fp-esperienze' ),
			'start_time'                => __( 'Start Time', 'fp-esperienze' ),
			'duration'                  => __( 'Duration (min)', 'fp-esperienze' ),
			'capacity'                  => __( 'Capacity', 'fp-esperienze' ),
			'language'                  => __( 'Language', 'fp-esperienze' ),
			'meeting_point'             => __( 'Meeting Point', 'fp-esperienze' ),
			'adult_price'               => __( 'Adult Price', 'fp-esperienze' ),
			'child_price'               => __( 'Child Price', 'fp-esperienze' ),
			'remove'                    => __( 'Remove', 'fp-esperienze' ),
			'gallery_add_images'       => __( 'Add images', 'fp-esperienze' ),
			'gallery_clear_all'        => __( 'Remove all', 'fp-esperienze' ),
			'gallery_clear_confirm'    => __( 'Remove all gallery images?', 'fp-esperienze' ),
			'gallery_frame_title'      => __( 'Experience gallery', 'fp-esperienze' ),
			'gallery_frame_button'     => __( 'Use these images', 'fp-esperienze' ),
			'gallery_remove_image'     => __( 'Remove image', 'fp-esperienze' ),
			'gallery_empty_state'      => __( 'No gallery images selected yet. Use “Add images” to pick them from the media library.', 'fp-esperienze' ),
			'gallery_items_count'      => __( '%d gallery images selected', 'fp-esperienze' ),
			'gallery_drag_instruction' => __( 'Drag and drop to change image order.', 'fp-esperienze' ),
		);

                // Meeting point IDs mapped to names, no placeholder option.
                $admin_data['fp_meeting_points'] = MeetingPointManager::getMeetingPointsForSelect();
		$admin_data['strings']           = array_merge_recursive( $admin_data['strings'], $experience_strings );

		wp_localize_script(
			'fp-esperienze-product-admin',
			'fp_esperienze_admin',
			$admin_data
		);
		// Add custom CSS for experience product type
		wp_add_inline_style(
			'woocommerce_admin_styles',
			'
            .product-type-experience .show_if_simple,
            .product-type-experience .show_if_variable,
            .product-type-experience .show_if_grouped,
            .product-type-experience .show_if_external {
                display: none !important;
            }
            /* Only show experience elements when product type is experience AND in experience context */
            .product-type-experience .woocommerce_options_panel .show_if_experience {
                display: block;
            }
            /* Hide experience elements when not experience type */
            body:not(.product-type-experience) .show_if_experience {
                display: none !important;
            }
            /* Ensure experience tabs are properly hidden when not active */
            #experience_product_data:not(.active),
            #dynamic_pricing_product_data:not(.active) {
                display: none !important;
            }
            .woocommerce_options_panel label,
            .woocommerce_options_panel legend {
                margin: 0 0 5px 0 !important;
            }
            .woocommerce_options_panel h4 {
                margin: 15px 12px 10px 12px !important;
                padding: 0 !important;
            }
            .woocommerce_options_panel .options_group {
                position: relative !important;
                clear: both !important;
                z-index: auto !important;
                margin-left: 0 !important;
            }
        '
		);
	}

	/**
	 * Check if overrides contain actual differences from product defaults
	 *
	 * @param array $overrides Override values from the slot
	 * @param int $index Slot index (for debugging)
	 * @param int $product_id Product ID
	 * @return bool True if there are actual overrides that differ from defaults
	 */
	private function hasActualOverrides( array $overrides, int $index, int $product_id ): bool {
		// Get product defaults for comparison
		$defaults = [
			'capacity' => get_post_meta( $product_id, '_fp_exp_capacity', true ),
			'price_adult' => get_post_meta( $product_id, '_fp_exp_adult_price', true ),
			'price_child' => get_post_meta( $product_id, '_fp_exp_child_price', true ),
		];
		
		// Check each override field against defaults
		foreach ( $overrides as $key => $value ) {
			if ( isset( $defaults[ $key ] ) ) {
				// Convert to comparable types
				$default_value = (string) $defaults[ $key ];
				$override_value = (string) $value;
				
				// If they differ, this is a real override
				if ( $default_value !== $override_value ) {
					return true;
				}
			}
		}
		
		return false;
	}
}