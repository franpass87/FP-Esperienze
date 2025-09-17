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
			add_filter( 'woocommerce_product_type_selector', array( $this, 'addProductType' ), 10 );
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FP Esperienze: Experience product type filter registered immediately' );
			}
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
		add_filter( 'woocommerce_product_type_selector', array( $this, 'addProductType' ), 10 );
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'FP Esperienze: Experience product type filter registered on init hook' );
		}
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

		?>
			<div id="experience_product_data" class="panel woocommerce_options_panel">
				<?php
				wp_nonce_field( 'fp_esperienze_save', 'fp_esperienze_nonce' );

				// Experience Type selector
				woocommerce_wp_select(
					array(
						'id'          => '_fp_experience_type',
						'label'       => __( 'Type', 'fp-esperienze' ),
						'options'     => array(
							'experience' => __( 'Experience (Recurring Schedule)', 'fp-esperienze' ),
							'event'      => __( 'Event (Fixed Date)', 'fp-esperienze' ),
						),
						'desc_tip'    => true,
						'description' => __( 'Choose whether this is a recurring experience or a fixed-date event', 'fp-esperienze' ),
						'value'       => get_post_meta( $post->ID, '_fp_experience_type', true ) ?: 'experience',
					)
				);

				// Cutoff minutes
				woocommerce_wp_text_input(
                                array(
                                        'id'                => '_fp_exp_cutoff_minutes',
					'label'             => __( 'Booking Cutoff (minutes)', 'fp-esperienze' ),
					'placeholder'       => '120',
					'desc_tip'          => true,
					'description'       => __( 'Minimum minutes before experience start time to allow bookings', 'fp-esperienze' ),
					'type'              => 'number',
					'custom_attributes' => array(
						'step' => '1',
						'min'  => '0',
					),
				)
			);

			// What's included
			woocommerce_wp_textarea_input(
				array(
					'id'          => '_fp_exp_included',
					'label'       => __( "What's Included", 'fp-esperienze' ),
					'placeholder' => __( "Professional guide\nAll activities as described\nSmall group experience", 'fp-esperienze' ),
					'desc_tip'    => true,
					'description' => __( 'List what is included in the experience (one item per line)', 'fp-esperienze' ),
					'rows'        => 5,
				)
			);

			// What's excluded
			woocommerce_wp_textarea_input(
				array(
					'id'          => '_fp_exp_excluded',
					'label'       => __( "What's Not Included", 'fp-esperienze' ),
					'placeholder' => __( "Hotel pickup and drop-off\nFood and drinks\nPersonal expenses\nGratuities", 'fp-esperienze' ),
					'desc_tip'    => true,
					'description' => __( 'List what is not included in the experience (one item per line)', 'fp-esperienze' ),
					'rows'        => 5,
				)
			);

			?>
			
			<div class="options_group">
				<h4><?php _e( 'Cancellation Rules', 'fp-esperienze' ); ?></h4>
				
				<?php

				// Free cancellation until (minutes)
				woocommerce_wp_text_input(
					array(
						'id'                => '_fp_exp_free_cancel_until_minutes',
						'label'             => __( 'Free Cancellation Until (minutes)', 'fp-esperienze' ),
						'placeholder'       => '1440',
						'desc_tip'          => true,
						'description'       => __( 'Minutes before experience start when customers can cancel for free (e.g., 1440 = 24 hours)', 'fp-esperienze' ),
						'type'              => 'number',
						'custom_attributes' => array(
							'step' => '1',
							'min'  => '0',
						),
					)
				);

				// Cancellation fee percentage
				woocommerce_wp_text_input(
					array(
						'id'                => '_fp_exp_cancel_fee_percent',
						'label'             => __( 'Cancellation Fee (%)', 'fp-esperienze' ),
						'placeholder'       => '20',
						'desc_tip'          => true,
						'description'       => __( 'Percentage of total price to charge as cancellation fee after free cancellation period', 'fp-esperienze' ),
						'type'              => 'number',
						'custom_attributes' => array(
							'step' => '0.01',
							'min'  => '0',
							'max'  => '100',
						),
					)
				);

				// No-show policy
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
					)
				);

				?>
			</div>
			
			<fieldset class="options_group fp-schedules-section fp-section-fieldset" id="fp-recurring-schedules">
				<legend class="fp-section-legend"><?php _e( 'Recurring Time Slots', 'fp-esperienze' ); ?></legend>
				
				<div class="fp-section-content">
					<div class="fp-section-description">
						<?php _e( 'Configure weekly recurring time slots for your experience. Each slot can run on multiple days and can have custom settings that override the default product values above.', 'fp-esperienze' ); ?>
					</div>
					
					<div id="fp-schedule-builder-container" style="margin-bottom: 20px;">
						<?php $this->renderScheduleBuilder( $post->ID ); ?>
					</div>
					
                                        <?php if ( apply_filters( 'fp_esperienze_enable_raw_schedules', false ) ) : ?>
                                                <div id="fp-schedule-raw-container" style="display: none;">
                                                        <h5><?php _e( 'Advanced Mode (Raw Schedules)', 'fp-esperienze' ); ?></h5>
                                                        <div id="fp-schedules-container">
                                                                <?php $this->renderSchedulesSection( $post->ID ); ?>
                                                        </div>
                                                        <button type="button" class="button" id="fp-add-schedule">
                                                                <?php _e( 'Add Schedule', 'fp-esperienze' ); ?>
                                                        </button>
                                                </div>

                                                <p>
                                                        <label>
                                                                <input type="checkbox" id="fp-toggle-raw-mode">
                                                                <?php _e( 'Show Advanced Mode', 'fp-esperienze' ); ?>
                                                        </label>
                                                        <span class="description"><?php _e( 'Enable to view/edit individual schedule rows directly', 'fp-esperienze' ); ?></span>
                                                </p>
                                        <?php endif; ?>
                                </div>
                        </fieldset>
			
			<fieldset class="options_group fp-event-schedules-section fp-section-fieldset" id="fp-event-schedules" style="display: none;">
				<legend class="fp-section-legend"><?php _e( 'Event Dates & Times', 'fp-esperienze' ); ?></legend>
				
				<div class="fp-section-content">
					<div class="fp-section-description">
						<?php _e( 'Configure specific dates and times for your event. Each event date can have multiple time slots with different settings.', 'fp-esperienze' ); ?>
					</div>
					
					<div id="fp-event-schedule-container">
						<?php $this->renderEventScheduleBuilder( $post->ID ); ?>
					</div>
					
					<button type="button" class="button fp-primary-button" id="fp-add-event-schedule">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php _e( 'Add Event Date', 'fp-esperienze' ); ?>
					</button>
				</div>
			</fieldset>
			
			<fieldset class="options_group fp-overrides-section-wrapper fp-section-fieldset" id="fp-overrides-section">
				<legend class="fp-section-legend"><?php _e( 'Date-Specific Overrides', 'fp-esperienze' ); ?></legend>
				
				<div class="fp-section-content">
					<div class="fp-section-description">
						<?php _e( 'Add exceptions for specific dates: close the experience, change capacity, or modify prices for particular days.', 'fp-esperienze' ); ?>
					</div>
					
					<div id="fp-overrides-container">
						<?php $this->renderOverridesSection( $post->ID ); ?>
					</div>
					<button type="button" class="button fp-primary-button fp-add-override" id="fp-add-override">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php _e( 'Add Date Override', 'fp-esperienze' ); ?>
					</button>
				</div>
			</fieldset>
			
			<div class="options_group">
				<h4><?php _e( 'Extras', 'fp-esperienze' ); ?></h4>
				<div id="fp-extras-container">
					<?php $this->renderExtrasSection( $post->ID ); ?>
				</div>
			</div>
		</div>
		
		<div id="dynamic_pricing_product_data" class="panel woocommerce_options_panel">
			<?php $this->renderDynamicPricingPanel( $post->ID ); ?>
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
		<div class="fp-schedule-row" data-index="<?php echo esc_attr( $index ); ?>" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; background: #f9f9f9; border-radius: 4px;">
			<input type="hidden" name="schedules[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $schedule->id ?? '' ); ?>">
			
			<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 10px;">
				<div>
					<label style="font-weight: bold; display: block; margin-bottom: 5px;">
						<?php _e( 'Day of Week', 'fp-esperienze' ); ?> <span style="color: red;">*</span>
						<span class="dashicons dashicons-info" title="<?php esc_attr_e( 'Which day of the week this schedule applies to', 'fp-esperienze' ); ?>" style="font-size: 14px; color: #666;"></span>
					</label>
					<select name="schedules[<?php echo esc_attr( $index ); ?>][day_of_week]" required style="width: 100%;">
						<option value=""><?php _e( 'Select Day', 'fp-esperienze' ); ?></option>
						<?php foreach ( $days as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $schedule->day_of_week ?? '', $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				
				<div>
					<label style="font-weight: bold; display: block; margin-bottom: 5px;">
						<?php _e( 'Start Time', 'fp-esperienze' ); ?> <span style="color: red;">*</span>
						<span class="dashicons dashicons-info" title="<?php esc_attr_e( 'When the experience starts (24-hour format)', 'fp-esperienze' ); ?>" style="font-size: 14px; color: #666;"></span>
					</label>
					<input type="time" 
							name="schedules[<?php echo esc_attr( $index ); ?>][start_time]" 
							value="<?php echo esc_attr( $schedule->start_time ?? '' ); ?>" 
							required 
							style="width: 100%;"
							title="<?php esc_attr_e( 'Experience start time', 'fp-esperienze' ); ?>">
				</div>
				
				<div>
					<label style="font-weight: bold; display: block; margin-bottom: 5px;">
						<?php _e( 'Duration (minutes)', 'fp-esperienze' ); ?> <span style="color: red;">*</span>
						<span class="dashicons dashicons-info" title="<?php esc_attr_e( 'How long the experience lasts in minutes', 'fp-esperienze' ); ?>" style="font-size: 14px; color: #666;"></span>
					</label>
					<input type="number"
							name="schedules[<?php echo esc_attr( $index ); ?>][duration_min]"
							value="<?php echo esc_attr( $schedule->duration_min ?? 60 ); ?>"
							min="1"
							step="1"
							required
							style="width: 100%;"
							title="<?php esc_attr_e( 'Duration in minutes (minimum 1)', 'fp-esperienze' ); ?>">
				</div>
				
				<div>
					<label style="font-weight: bold; display: block; margin-bottom: 5px;">
						<?php _e( 'Max Capacity', 'fp-esperienze' ); ?> <span style="color: red;">*</span>
						<span class="dashicons dashicons-info" title="<?php esc_attr_e( 'Maximum number of participants for this schedule', 'fp-esperienze' ); ?>" style="font-size: 14px; color: #666;"></span>
					</label>
					<input type="number"
							name="schedules[<?php echo esc_attr( $index ); ?>][capacity]"
							value="<?php echo esc_attr( $schedule->capacity ?? 10 ); ?>"
							min="1"
							step="1"
							required
							style="width: 100%;"
							title="<?php esc_attr_e( 'Maximum participants (minimum 1)', 'fp-esperienze' ); ?>">
				</div>
			</div>
			
			<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 10px;">
				<div>
					<label style="font-weight: bold; display: block; margin-bottom: 5px;">
						<?php _e( 'Language', 'fp-esperienze' ); ?>
						<span class="dashicons dashicons-info" title="<?php esc_attr_e( 'Experience language code (e.g., en, it, es)', 'fp-esperienze' ); ?>" style="font-size: 14px; color: #666;"></span>
					</label>
					<input type="text"
							name="schedules[<?php echo esc_attr( $index ); ?>][lang]"
							value="<?php echo esc_attr( $schedule->lang ?? 'en' ); ?>"
							maxlength="10"
							style="width: 100%;"
							required
							title="<?php esc_attr_e( 'Language code (ISO format preferred)', 'fp-esperienze' ); ?>">
				</div>
				
				<div>
					<label style="font-weight: bold; display: block; margin-bottom: 5px;">
						<?php _e( 'Meeting Point', 'fp-esperienze' ); ?>
						<span class="dashicons dashicons-info" title="<?php esc_attr_e( 'Where participants should meet for this experience', 'fp-esperienze' ); ?>" style="font-size: 14px; color: #666;"></span>
					</label>
					<select name="schedules[<?php echo esc_attr( $index ); ?>][meeting_point_id]" style="width: 100%;" required>
						<?php foreach ( $meeting_points as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $schedule->meeting_point_id ?? '', $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				
				<div>
					<label style="font-weight: bold; display: block; margin-bottom: 5px;">
						<?php _e( 'Adult Price', 'fp-esperienze' ); ?>
						<span class="dashicons dashicons-info" title="<?php esc_attr_e( 'Price per adult participant', 'fp-esperienze' ); ?>" style="font-size: 14px; color: #666;"></span>
					</label>
					<input type="number"
							name="schedules[<?php echo esc_attr( $index ); ?>][price_adult]"
							value="<?php echo esc_attr( $schedule->price_adult ?? '' ); ?>"
							min="0"
							step="0.01"
							style="width: 100%;"
							required
							title="<?php esc_attr_e( 'Adult price', 'fp-esperienze' ); ?>">
				</div>
				
				<div>
					<label style="font-weight: bold; display: block; margin-bottom: 5px;">
						<?php _e( 'Child Price', 'fp-esperienze' ); ?>
						<span class="dashicons dashicons-info" title="<?php esc_attr_e( 'Price per child participant', 'fp-esperienze' ); ?>" style="font-size: 14px; color: #666;"></span>
					</label>
					<input type="number"
							name="schedules[<?php echo esc_attr( $index ); ?>][price_child]"
							value="<?php echo esc_attr( $schedule->price_child ?? '' ); ?>"
							min="0"
							step="0.01"
							style="width: 100%;"
							required
							title="<?php esc_attr_e( 'Child price', 'fp-esperienze' ); ?>">
				</div>
			</div>
			
			<div style="text-align: right;">
				<button type="button" class="button fp-remove-schedule" style="color: #dc3545;">
					<span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
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
		$schedules      = ScheduleManager::getSchedules( $product_id );
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
			
			<button type="button" class="button fp-add-time-slot" id="fp-add-time-slot">
				<span class="dashicons dashicons-plus-alt"></span>
				<?php _e( 'Add Time Slot', 'fp-esperienze' ); ?>
			</button>
		</div>
		
		<!-- Hidden container for generated schedule inputs -->
		<div id="fp-generated-schedules" style="display: none;"></div>
		<?php
	}

	/**
	 * Render a clean time slot card - REFACTORED VERSION
	 */
	private function renderTimeSlotCardClean( $slot, $index, $days, $meeting_points, $default_duration, $default_capacity, $default_language, $default_meeting_point, $default_price_adult, $default_price_child, $product_id ): void {
		?>
		<div class="fp-time-slot-content-clean">
			<!-- Time slot header -->
			<div class="fp-time-slot-header-clean">
				<div class="fp-time-field-clean">
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
				
				<div class="fp-days-field-clean">
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
					<button type="button" class="fp-remove-time-slot-clean button">
						<span class="dashicons dashicons-trash"></span>
						<?php _e( 'Remove', 'fp-esperienze' ); ?>
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
				<div class="fp-time-field">
					<label>
						<span class="dashicons dashicons-clock"></span>
						<?php _e( 'Start Time', 'fp-esperienze' ); ?> <span style="color: red;">*</span>
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
				
				<div class="fp-days-field">
					<label>
						<span class="dashicons dashicons-calendar-alt"></span>
						<?php _e( 'Days of Week', 'fp-esperienze' ); ?> <span style="color: red;">*</span>
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
					<button type="button" class="fp-remove-time-slot">
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
		<div id="fp-generated-event-schedules" style="display: none;"></div>
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
				<button type="button" class="button fp-add-event-timeslot" data-date="<?php echo esc_attr( $date ); ?>">
					<span class="dashicons dashicons-clock"></span>
					<?php _e( 'Add Time Slot', 'fp-esperienze' ); ?>
				</button>
				<button type="button" class="button fp-remove-event-date" data-date="<?php echo esc_attr( $date ); ?>">
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
					<button type="button" class="button fp-remove-event-timeslot">
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
					
					<button type="button" class="fp-override-remove-clean button">
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
					<button type="button" class="fp-override-remove" aria-label="<?php esc_attr_e( 'Remove this override', 'fp-esperienze' ); ?>">
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
				<div class="fp-date-warning show" style="border-color: #8c8f94; color: #646970;">
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
			<button type="button" class="fp-override-remove" aria-label="<?php esc_attr_e( 'Remove this override', 'fp-esperienze' ); ?>">
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
			
			<button type="button" class="fp-override-remove" aria-label="<?php esc_attr_e( 'Remove this override', 'fp-esperienze' ); ?>">
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
			<p><?php _e( 'Select which extras are available for this experience:', 'fp-esperienze' ); ?></p>
			
			<?php if ( empty( $all_extras ) ) : ?>
				<p class="description">
					<?php
					printf(
						__( 'No extras available. <a href="%s">Create some extras</a> first.', 'fp-esperienze' ),
						admin_url( 'admin.php?page=fp-esperienze-extras' )
					);
					?>
				</p>
			<?php else : ?>
				<div class="fp-available-extras">
					<?php foreach ( $all_extras as $extra ) : ?>
						<label class="fp-extra-checkbox">
							<input type="checkbox" 
									name="fp_product_extras[]" 
									value="<?php echo esc_attr( $extra->id ); ?>"
									<?php checked( in_array( $extra->id, $selected_extra_ids ) ); ?>>
							<strong><?php echo esc_html( $extra->name ); ?></strong>
							<?php if ( function_exists( 'wc_price' ) ) : ?>
								(<?php echo wc_price( $extra->price ); ?> 
							<?php else : ?>
								(<?php echo '$' . number_format( $extra->price, 2 ); ?> 
							<?php endif; ?>
							<?php echo esc_html( $extra->billing_type === 'per_person' ? __( 'per person', 'fp-esperienze' ) : __( 'per booking', 'fp-esperienze' ) ); ?>)
							<?php if ( $extra->description ) : ?>
								<br><span class="description"><?php echo esc_html( $extra->description ); ?></span>
							<?php endif; ?>
						</label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
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

		// Save experience type
		if ( isset( $_POST['_fp_experience_type'] ) ) {
			$experience_type = sanitize_text_field( wp_unslash( $_POST['_fp_experience_type'] ) );
			if ( in_array( $experience_type, array( 'experience', 'event' ) ) ) {
				update_post_meta( $post_id, '_fp_experience_type', $experience_type );
			}
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

			// Validate date is not in the past
			if ( strtotime( $date ) < strtotime( 'today' ) ) {
				$validation_errors[] = sprintf( __( 'Event date cannot be in the past: %s', 'fp-esperienze' ), esc_html( $date ) );
				continue;
			}

			foreach ( $timeslots as $slot_index => $slot_data ) {
				// Skip empty slots
				if ( empty( $slot_data['start_time'] ) ) {
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

		return $processed_ids;
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
		
		<div class="options_group">
			<h4><?php _e( 'Dynamic Pricing Rules', 'fp-esperienze' ); ?></h4>
			
			<div id="fp-pricing-rules-container">
				<?php
				foreach ( $rules as $index => $rule ) {
					$this->renderPricingRuleRow( $rule, $index );
				}
				?>
			</div>
			
			<button type="button" id="fp-add-pricing-rule" class="button">
				<?php _e( 'Add Pricing Rule', 'fp-esperienze' ); ?>
			</button>
		</div>
		
		<div class="options_group">
			<h4><?php _e( 'Pricing Preview', 'fp-esperienze' ); ?></h4>
			
			<div class="fp-pricing-preview">
				<div class="fp-preview-inputs">
					<div>
						<label><?php _e( 'Booking Date', 'fp-esperienze' ); ?></label>
						<input type="date" id="fp-preview-booking-date" value="<?php echo date( 'Y-m-d' ); ?>">
					</div>
					<div>
						<label><?php _e( 'Purchase Date', 'fp-esperienze' ); ?></label>
						<input type="date" id="fp-preview-purchase-date" value="<?php echo date( 'Y-m-d' ); ?>">
					</div>
					<div>
						<label><?php _e( 'Adults', 'fp-esperienze' ); ?></label>
						<input type="number" id="fp-preview-qty-adult" value="2" min="0">
					</div>
					<div>
						<label><?php _e( 'Children', 'fp-esperienze' ); ?></label>
						<input type="number" id="fp-preview-qty-child" value="0" min="0">
					</div>
					<div>
						<button type="button" id="fp-preview-calculate" class="button">
							<?php _e( 'Calculate', 'fp-esperienze' ); ?>
						</button>
					</div>
				</div>
				
				<div id="fp-preview-results" style="margin-top: 15px;"></div>
			</div>
		</div>
		
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				var ruleIndex = <?php echo count( $rules ); ?>;
				
				// Add pricing rule
				$('#fp-add-pricing-rule').click(function() {
					var html = buildPricingRuleTemplate(ruleIndex);
					$('#fp-pricing-rules-container').append(html);
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
				
				// Build pricing rule template
				function buildPricingRuleTemplate(index) {
					return '<div class="fp-pricing-rule-row" data-index="' + index + '" style="border: 1px solid #ccc; padding: 15px; margin-bottom: 10px;">' +
						'<input type="hidden" name="pricing_rules[' + index + '][id]" value="">' +
						'<div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">' +
							'<div>' +
								'<label><?php _e( 'Rule Name', 'fp-esperienze' ); ?></label>' +
								'<input type="text" name="pricing_rules[' + index + '][rule_name]" value="" placeholder="<?php _e( 'Rule Name', 'fp-esperienze' ); ?>" required style="width: 200px;">' +
							'</div>' +
							'<div>' +
								'<label><?php _e( 'Type', 'fp-esperienze' ); ?></label>' +
								'<select name="pricing_rules[' + index + '][rule_type]" class="fp-rule-type" required>' +
									'<option value=""><?php _e( 'Select Type', 'fp-esperienze' ); ?></option>' +
									'<option value="seasonal"><?php _e( 'Seasonal', 'fp-esperienze' ); ?></option>' +
									'<option value="weekend_weekday"><?php _e( 'Weekend/Weekday', 'fp-esperienze' ); ?></option>' +
									'<option value="early_bird"><?php _e( 'Early Bird', 'fp-esperienze' ); ?></option>' +
									'<option value="group"><?php _e( 'Group Discount', 'fp-esperienze' ); ?></option>' +
								'</select>' +
							'</div>' +
							'<div>' +
								'<label><?php _e( 'Priority', 'fp-esperienze' ); ?></label>' +
								'<input type="number" name="pricing_rules[' + index + '][priority]" value="0" min="0" step="1" style="width: 80px;">' +
							'</div>' +
							'<div>' +
								'<label>' +
									'<input type="checkbox" name="pricing_rules[' + index + '][is_active]" value="1" checked>' +
									'<?php _e( 'Active', 'fp-esperienze' ); ?>' +
								'</label>' +
							'</div>' +
							'<button type="button" class="button fp-remove-pricing-rule"><?php _e( 'Remove', 'fp-esperienze' ); ?></button>' +
						'</div>' +
						'<div class="fp-rule-field fp-field-dates" style="display: none; margin-bottom: 10px;">' +
							'<label><?php _e( 'Date Range', 'fp-esperienze' ); ?></label>' +
							'<input type="date" name="pricing_rules[' + index + '][date_start]" value="" placeholder="<?php _e( 'Start Date', 'fp-esperienze' ); ?>">' +
							'<input type="date" name="pricing_rules[' + index + '][date_end]" value="" placeholder="<?php _e( 'End Date', 'fp-esperienze' ); ?>">' +
						'</div>' +
						'<div class="fp-rule-field fp-field-applies-to" style="display: none; margin-bottom: 10px;">' +
							'<label><?php _e( 'Applies To', 'fp-esperienze' ); ?></label>' +
							'<select name="pricing_rules[' + index + '][applies_to]">' +
								'<option value=""><?php _e( 'Select...', 'fp-esperienze' ); ?></option>' +
								'<option value="weekend"><?php _e( 'Weekend', 'fp-esperienze' ); ?></option>' +
								'<option value="weekday"><?php _e( 'Weekday', 'fp-esperienze' ); ?></option>' +
							'</select>' +
						'</div>' +
						'<div class="fp-rule-field fp-field-days-before" style="display: none; margin-bottom: 10px;">' +
							'<label><?php _e( 'Days Before', 'fp-esperienze' ); ?></label>' +
							'<input type="number" name="pricing_rules[' + index + '][days_before]" value="" placeholder="<?php _e( 'Days', 'fp-esperienze' ); ?>" min="1">' +
						'</div>' +
						'<div class="fp-rule-field fp-field-min-participants" style="display: none; margin-bottom: 10px;">' +
							'<label><?php _e( 'Minimum Participants', 'fp-esperienze' ); ?></label>' +
							'<input type="number" name="pricing_rules[' + index + '][min_participants]" value="" placeholder="<?php _e( 'Min Participants', 'fp-esperienze' ); ?>" min="1">' +
						'</div>' +
						'<div style="display: flex; gap: 10px; align-items: center;">' +
							'<div>' +
								'<label><?php _e( 'Adjustment Type', 'fp-esperienze' ); ?></label>' +
								'<select name="pricing_rules[' + index + '][adjustment_type]">' +
									'<option value="percentage"><?php _e( 'Percentage (%)', 'fp-esperienze' ); ?></option>' +
									'<option value="fixed_amount"><?php _e( 'Fixed Amount', 'fp-esperienze' ); ?></option>' +
								'</select>' +
							'</div>' +
							'<div>' +
								'<label><?php _e( 'Adult Adjustment', 'fp-esperienze' ); ?></label>' +
								'<input type="number" name="pricing_rules[' + index + '][adult_adjustment]" value="0" step="0.01" style="width: 100px;">' +
							'</div>' +
							'<div>' +
								'<label><?php _e( 'Child Adjustment', 'fp-esperienze' ); ?></label>' +
								'<input type="number" name="pricing_rules[' + index + '][child_adjustment]" value="0" step="0.01" style="width: 100px;">' +
							'</div>' +
						'</div>' +
					'</div>';
				}
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
		<div class="fp-pricing-rule-row" data-index="<?php echo esc_attr( $index ); ?>" style="border: 1px solid #ccc; padding: 15px; margin-bottom: 10px;">
			<input type="hidden" name="pricing_rules[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $rule->id ?? '' ); ?>">
			
			<div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">
				<div>
					<label><?php _e( 'Rule Name', 'fp-esperienze' ); ?></label>
					<input type="text" name="pricing_rules[<?php echo esc_attr( $index ); ?>][rule_name]" 
							value="<?php echo esc_attr( $rule->rule_name ?? '' ); ?>" 
							placeholder="<?php _e( 'Rule Name', 'fp-esperienze' ); ?>" required style="width: 200px;">
				</div>
				
				<div>
					<label><?php _e( 'Type', 'fp-esperienze' ); ?></label>
					<select name="pricing_rules[<?php echo esc_attr( $index ); ?>][rule_type]" class="fp-rule-type" required>
						<option value=""><?php _e( 'Select Type', 'fp-esperienze' ); ?></option>
						<option value="seasonal" <?php selected( $rule->rule_type ?? '', 'seasonal' ); ?>><?php _e( 'Seasonal', 'fp-esperienze' ); ?></option>
						<option value="weekend_weekday" <?php selected( $rule->rule_type ?? '', 'weekend_weekday' ); ?>><?php _e( 'Weekend/Weekday', 'fp-esperienze' ); ?></option>
						<option value="early_bird" <?php selected( $rule->rule_type ?? '', 'early_bird' ); ?>><?php _e( 'Early Bird', 'fp-esperienze' ); ?></option>
						<option value="group" <?php selected( $rule->rule_type ?? '', 'group' ); ?>><?php _e( 'Group Discount', 'fp-esperienze' ); ?></option>
					</select>
				</div>
				
				<div>
					<label><?php _e( 'Priority', 'fp-esperienze' ); ?></label>
					<input type="number" name="pricing_rules[<?php echo esc_attr( $index ); ?>][priority]" 
							value="<?php echo esc_attr( $rule->priority ?? 0 ); ?>" 
							min="0" step="1" style="width: 80px;">
				</div>
				
				<div>
					<label>
						<input type="checkbox" name="pricing_rules[<?php echo esc_attr( $index ); ?>][is_active]" 
								value="1" <?php checked( $rule->is_active ?? 1, 1 ); ?>>
						<?php _e( 'Active', 'fp-esperienze' ); ?>
					</label>
				</div>
				
				<button type="button" class="button fp-remove-pricing-rule"><?php _e( 'Remove', 'fp-esperienze' ); ?></button>
			</div>
			
			<!-- Rule-specific fields -->
			<div class="fp-rule-field fp-field-dates" style="display: none; margin-bottom: 10px;">
				<label><?php _e( 'Date Range', 'fp-esperienze' ); ?></label>
				<input type="date" name="pricing_rules[<?php echo esc_attr( $index ); ?>][date_start]" 
						value="<?php echo esc_attr( $rule->date_start ?? '' ); ?>" placeholder="<?php _e( 'Start Date', 'fp-esperienze' ); ?>">
				<input type="date" name="pricing_rules[<?php echo esc_attr( $index ); ?>][date_end]" 
						value="<?php echo esc_attr( $rule->date_end ?? '' ); ?>" placeholder="<?php _e( 'End Date', 'fp-esperienze' ); ?>">
			</div>
			
			<div class="fp-rule-field fp-field-applies-to" style="display: none; margin-bottom: 10px;">
				<label><?php _e( 'Applies To', 'fp-esperienze' ); ?></label>
				<select name="pricing_rules[<?php echo esc_attr( $index ); ?>][applies_to]">
					<option value=""><?php _e( 'Select...', 'fp-esperienze' ); ?></option>
					<option value="weekend" <?php selected( $rule->applies_to ?? '', 'weekend' ); ?>><?php _e( 'Weekend', 'fp-esperienze' ); ?></option>
					<option value="weekday" <?php selected( $rule->applies_to ?? '', 'weekday' ); ?>><?php _e( 'Weekday', 'fp-esperienze' ); ?></option>
				</select>
			</div>
			
			<div class="fp-rule-field fp-field-days-before" style="display: none; margin-bottom: 10px;">
				<label><?php _e( 'Days Before', 'fp-esperienze' ); ?></label>
				<input type="number" name="pricing_rules[<?php echo esc_attr( $index ); ?>][days_before]" 
						value="<?php echo esc_attr( $rule->days_before ?? '' ); ?>" 
						placeholder="<?php _e( 'Days', 'fp-esperienze' ); ?>" min="1">
			</div>
			
			<div class="fp-rule-field fp-field-min-participants" style="display: none; margin-bottom: 10px;">
				<label><?php _e( 'Minimum Participants', 'fp-esperienze' ); ?></label>
				<input type="number" name="pricing_rules[<?php echo esc_attr( $index ); ?>][min_participants]" 
						value="<?php echo esc_attr( $rule->min_participants ?? '' ); ?>" 
						placeholder="<?php _e( 'Min Participants', 'fp-esperienze' ); ?>" min="1">
			</div>
			
			<!-- Adjustment fields -->
			<div style="display: flex; gap: 10px; align-items: center;">
				<div>
					<label><?php _e( 'Adjustment Type', 'fp-esperienze' ); ?></label>
					<select name="pricing_rules[<?php echo esc_attr( $index ); ?>][adjustment_type]">
						<option value="percentage" <?php selected( $rule->adjustment_type ?? 'percentage', 'percentage' ); ?>><?php _e( 'Percentage (%)', 'fp-esperienze' ); ?></option>
						<option value="fixed_amount" <?php selected( $rule->adjustment_type ?? 'percentage', 'fixed_amount' ); ?>><?php _e( 'Fixed Amount', 'fp-esperienze' ); ?></option>
					</select>
				</div>
				
				<div>
					<label><?php _e( 'Adult Adjustment', 'fp-esperienze' ); ?></label>
					<input type="number" name="pricing_rules[<?php echo esc_attr( $index ); ?>][adult_adjustment]" 
							value="<?php echo esc_attr( $rule->adult_adjustment ?? 0 ); ?>" 
							step="0.01" style="width: 100px;">
				</div>
				
				<div>
					<label><?php _e( 'Child Adjustment', 'fp-esperienze' ); ?></label>
					<input type="number" name="pricing_rules[<?php echo esc_attr( $index ); ?>][child_adjustment]" 
							value="<?php echo esc_attr( $rule->child_adjustment ?? 0 ); ?>" 
							step="0.01" style="width: 100px;">
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

		// Only show for experience products
		if ( ! $product_object || $product_object->get_type() !== 'experience' ) {
			return;
		}

		echo '<div class="options_group show_if_experience">';

		woocommerce_wp_text_input(
			array(
				'id'                => '_experience_duration_general',
				'label'             => __( 'Duration (minutes)', 'fp-esperienze' ),
				'placeholder'       => '60',
				'desc_tip'          => true,
				'description'       => __( 'Experience duration in minutes', 'fp-esperienze' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => '1',
					'min'  => '1',
				),
				'value'             => get_post_meta( $product_object->get_id(), '_experience_duration', true ),
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
			'event_schedules'           => __( 'Event Dates & Times', 'fp-esperienze' ),
			'recurring_schedules'       => __( 'Recurring Time Slots', 'fp-esperienze' ),
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