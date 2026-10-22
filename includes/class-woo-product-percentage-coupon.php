<?php
/**
 * Main class that handles per-product percentage coupons in WooCommerce.
 *
 * This class provides functionality to create and manage percentage-based coupons
 * that can have different discount values for different products or variations.
 *
 * @since 1.0.0
 */
final class Woo_Product_Percentage_Coupon {

	// phpcs:disable

	/* Internal variables */
	private $discount_type            = 'percent_per_product';
	private $mode                     = 'default';
	private $products_magic_not_valid = array();
	private $version;

	/* Single instance */
	protected static $instance = null;

	// phpcs:enable

	/**
	 * Constructor
	 *
	 * Initializes the plugin version from the PCPW_VERSION constant and sets up all
	 * necessary hooks for WooCommerce integration through the init_hooks() method.
	 * This constructor is called when the class is instantiated via the singleton pattern
	 * by the instance() method.
	 */
	public function __construct() {
		$this->version = PCPW_VERSION;
		$this->init_hooks();
	}

	/**
	 * Ensures only one instance of the plugin is loaded (Singleton pattern).
	 *
	 * @return Woo_Product_Percentage_Coupon The single instance of this class
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize all WordPress hooks for the plugin.
	 *
	 * Hooks into WooCommerce coupon, product, and integration actions and filters.
	 *
	 * @access private
	 */
	private function init_hooks() {
		// Init stuff
		add_action( 'init', array( $this, 'init' ) );
		// Add coupon type to WooCommerce
		add_filter( 'woocommerce_coupon_discount_types', array( $this, 'woocommerce_coupon_discount_types' ) );
		add_filter( 'woocommerce_product_coupon_types', array( $this, 'woocommerce_product_coupon_types' ) );
		add_action( 'woocommerce_coupon_options', array( $this, 'woocommerce_coupon_options' ), 10, 2 );
		add_filter( 'woocommerce_coupon_get_discount_amount', array( $this, 'woocommerce_coupon_get_discount_amount' ), 10, 5 );
		// Custom product fields
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'woocommerce_product_data_tabs' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'woocommerce_product_data_panels' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'woocommerce_process_product_meta' ) );
		// Custom product variation fields
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'woocommerce_product_after_variable_attributes' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'woocommerce_save_product_variation' ), 10, 2 );
		// Add coupon type to InvoiceXpress
		add_filter( 'invoicexpress_woocommerce_allowed_coupon_types', array( $this, 'invoicexpress_woocommerce_allowed_coupon_types' ) );
		// Magic coupon valid for product
		add_filter( 'magic_coupon_coupon_is_valid_for_product', array( $this, 'magic_coupon_coupon_is_valid_for_product' ), 10, 3 );
	}

	/**
	 * Initialize plugin operation mode.
	 *
	 * Determines whether to use 'single' percentage mode or the default mode based on a filter.
	 * In single mode, all coupons share one global percentage setting per product.
	 * In default mode, each coupon can have different percentages for each product.
	 *
	 * @return void
	 */
	public function init() {
		// Maybe not needed because we should have a PRO version with a specific taxonomy and percentages within the coupon settings
		if ( apply_filters( 'product_percentage_coupon_woo_single_percentage_mode', false ) ) {
			$this->mode = 'single';
		}
	}

	/**
	 * Add coupon type to WooCommerce discount types list.
	 *
	 * Registers our custom "Percentage discount (per product)" coupon type
	 * in WooCommerce's available discount types dropdown on the coupon admin screen.
	 *
	 * @param array $discount_types Array of existing discount types.
	 * @return array Modified array with our custom discount type added.
	 */
	public function woocommerce_coupon_discount_types( $discount_types ) {
		$discount_types[ $this->discount_type ] = __( 'Percentage discount (per product)', 'product-percentage-coupon-woo' );
		return $discount_types;
	}

	/**
	 * Add coupon type to WooCommerce product coupon types.
	 *
	 * Ensures our custom coupon type is included in the list of coupon types
	 * that can be applied to products in WooCommerce.
	 *
	 * @param array $coupon_types Existing WooCommerce product coupon types.
	 * @return array Modified coupon types with our custom type added.
	 */
	public function woocommerce_product_coupon_types( $coupon_types ) {
		$coupon_types[] = $this->discount_type;
		return $coupon_types;
	}

	/**
	 * Display instructions for the custom percentage coupon on the coupon edit page.
	 *
	 * Shows explanatory text to users when they are editing a percentage-per-product coupon,
	 * explaining how this coupon type differs from standard percentage coupons.
	 *
	 * @param int       $coupon_id The ID of the coupon being edited.
	 * @param WC_Coupon $coupon    The coupon object.
	 */
	public function woocommerce_coupon_options( $coupon_id, $coupon ) {
		if ( is_a( $coupon, 'WC_Coupon' ) ) {
			if ( $coupon->get_discount_type() === $this->discount_type ) {
				?>
				<p>
					<strong>
						<?php esc_html_e( 'A “Percentage discount (per product)” coupon works just like a regular “Percentage discount” coupon, setting the default discount at the percentage set on this page, but applies a custom discount if you set it on each product.', 'product-percentage-coupon-woo' ); ?>
					</strong>
				</p>
				<?php
			}
		}
	}

	/**
	 * Retrieve all percentage-per-product coupons from the database.
	 *
	 * Queries the database for all published coupons with our custom discount type,
	 * and converts them to WC_Coupon objects for easy manipulation.
	 *
	 * @return array Collection of WC_Coupon objects with our custom discount type.
	 */
	public function get_all_coupons() {
		// There are no CRUD methods for coupons at this time
		$coupons = get_posts(
			array(
				'post_type'      => 'shop_coupon',
				'status'         => 'publish',
				'posts_per_page' => -1, // I know, nasty...
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_key'       => 'discount_type', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $this->discount_type, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		foreach ( $coupons as $key => $post ) {
			$coupons[ $key ] = new WC_Coupon( $post->post_title );
		}
		return $coupons;
	}

	/**
	 * Calculate custom discount amount for cart items when our coupon type is used.
	 *
	 * Intercepts WooCommerce's discount calculation process when our custom coupon type
	 * is being applied, and calculates product-specific discount percentages instead
	 * of using the coupon's global percentage.
	 *
	 * @param float     $discount           Original discount amount calculated by WooCommerce.
	 * @param float     $discounting_amount Price amount to apply the discount to.
	 * @param array     $cart_item          Cart item data array.
	 * @param bool      $single             Whether to apply discount to one item or multiple.
	 * @param WC_Coupon $coupon             Coupon object being applied.
	 * @return float    Modified discount amount based on product-specific settings.
	 */
	public function woocommerce_coupon_get_discount_amount( $discount, $discounting_amount, $cart_item, $single, $coupon ) {
		if ( $coupon->get_discount_type() === $this->discount_type ) {
			$product = wc_get_product( $cart_item['product_id'] );
			if ( $product ) {
				$variation = isset( $cart_item['variation_id'] ) && intval( $cart_item['variation_id'] ) > 0 ? wc_get_product( $cart_item['variation_id'] ) : null;
				$discount  = $this->get_discount_amount( $discounting_amount, $coupon, $product, $variation );
			}
		}
		return $discount;
	}

	/**
	 * Calculate product-specific discount amount based on coupon and product settings.
	 *
	 * Core calculation method that determines the actual discount amount to apply
	 * based on product and variation meta values. Can be used both internally
	 * and by external plugins like Magic Coupon.
	 *
	 * @param float      $discounting_amount Price amount to discount.
	 * @param WC_Coupon  $coupon             Coupon object.
	 * @param WC_Product $product            Product object.
	 * @param WC_Product $variation          Optional. Product variation object.
	 * @return float     Calculated discount amount.
	 */
	public function get_discount_amount( $discounting_amount, $coupon, $product, $variation = null ) {
		// Coupon amount
		$amount = floatval( $coupon->get_amount() );
		// Variation? - Get meta from correct parent product
		if ( $product->get_type() === 'variation' ) {
			// Just in case, for integrations that don't pass the $product and $variation to the function
			$meta_product   = wc_get_product( $product->get_parent_id() );
			$meta_variation = $product;
		} else {
			// On this plugin we should always be here
			$meta_product   = $product;
			$meta_variation = $variation;
		}
		// Product amount?
		switch ( $this->mode ) {

			case 'single':
				// Single (BF) mode
				if ( $meta_variation && trim( $meta_variation->get_meta( '_product_percentage_coupon_woo_active_global' ) ) === 'yes' ) {
					// Variation
					$temp_amount = floatval( $meta_variation->get_meta( '_product_percentage_coupon_woo_amount_global' ) );
					if ( $temp_amount > 0 ) {
						$amount = $temp_amount; // Only replace coupon value if > 0
					}
				} elseif ( trim( $meta_product->get_meta( '_product_percentage_coupon_woo_active_global' ) ) !== 'no' ) {
					$temp_amount = floatval( $meta_product->get_meta( '_product_percentage_coupon_woo_amount_global' ) );
					if ( $temp_amount > 0 ) {
						$amount = $temp_amount;
					}
				} else {
					$amount = 0;
				}
				break;

			default:
				// Default - list all coupons
				if ( $meta_variation && trim( $meta_variation->get_meta( '_product_percentage_coupon_woo_active_' . $coupon->get_id() ) ) === 'yes' ) {
					// Variation
					$temp_amount = floatval( $meta_variation->get_meta( '_product_percentage_coupon_woo_amount_' . $coupon->get_id() ) );
					if ( $temp_amount > 0 ) {
						$amount = $temp_amount; // Only replace coupon value if > 0
					}
				} elseif ( trim( $meta_product->get_meta( '_product_percentage_coupon_woo_active_' . $coupon->get_id() ) ) !== 'no' ) {
					// Product
					$temp_amount = floatval( $meta_product->get_meta( '_product_percentage_coupon_woo_amount_' . $coupon->get_id() ) );
					if ( $temp_amount > 0 ) {
						$amount = $temp_amount; // Only replace coupon value if > 0
					}
				} else {
					// None
					$amount = 0;
				}
				break;

		}
		// Set Magic Coupon "is on sale" to false
		if ( $amount === 0 ) {
			$add_filter     = true;
			$filter_product = $meta_variation ? $meta_variation : $meta_product;
			// Is the product on sale because of "sale price"?
			if ( '' !== (string) $filter_product->get_sale_price() && $filter_product->get_regular_price() > $filter_product->get_sale_price() ) {
				$add_filter = false;
				if ( $filter_product->get_date_on_sale_from() && $filter_product->get_date_on_sale_from()->getTimestamp() > time() ) {
					$add_filter = true;
				}
				if ( $filter_product->get_date_on_sale_to() && $filter_product->get_date_on_sale_to()->getTimestamp() < time() ) {
					$add_filter = true;
				}
			}
			// if ( $add_filter ) {
			// add_filter( 'magic_coupon_product_'.$filter_product->get_id().'_is_on_sale', '__return_false' );
			// }
			$add_to_array = false;
			if ( $filter_product->get_type() === 'variable' ) {
				// We start by setting it to true
				$add_to_array = true;
				// Get childs just to be sure
				$temp_variations = $product->get_available_variations();
				$temp_variations = wp_list_pluck( $temp_variations, 'variation_id' );
				foreach ( $temp_variations as $temp_variation_id ) {
					$temp_variation = wc_get_product( $temp_variation_id );
					switch ( $this->mode ) {
						case 'single':
							$temp_meta_active = '_product_percentage_coupon_woo_active_global';
							$temp_meta_amount = '_product_percentage_coupon_woo_amount_global';
							break;
						default:
							$temp_meta_active = '_product_percentage_coupon_woo_active_' . $coupon->get_id();
							$temp_meta_amount = '_product_percentage_coupon_woo_amount_' . $coupon->get_id();
							break;
					}
					if ( trim( $temp_variation->get_meta( $temp_meta_active ) ) === 'yes' ) {
						if ( floatval( $temp_variation->get_meta( $temp_meta_amount ) ) > 0 ) {
							// This variation has discount - $add_to_array = false; and break because we don't need to go further
							$add_to_array = false;
							break;
						}
					}
				}
			} else {
				// Amount is 0, and not variable, add to array
				$add_to_array = true;
			}
			if ( $add_to_array ) {
				if ( ! ( isset( $this->products_magic_not_valid[ $coupon->get_id() ] ) && is_array( $this->products_magic_not_valid[ $coupon->get_id() ] ) ) ) {
					$this->products_magic_not_valid[ $coupon->get_id() ] = array();
				}
				$this->products_magic_not_valid[ $coupon->get_id() ] = array_unique( array_merge( $this->products_magic_not_valid[ $coupon->get_id() ], array( $filter_product->get_id() ) ) );
				if ( $add_filter ) {
					add_filter( 'magic_coupon_product_' . $filter_product->get_id() . '_is_on_sale', '__return_false' );
				}
			}
		}
		// Just like in class-wc-coupon.php get_discount_amount()
		$discount = (float) $amount * ( $discounting_amount / 100 );
		// Allow the PRO add-on to recaulculate it
		return apply_filters( 'product_percentage_coupon_woo_discount', $discount, $discounting_amount, $coupon, $meta_product, $meta_variation );
	}

	/**
	 * Filter Magic Coupon's product validity check for our coupon type.
	 *
	 * Prevents products with zero discount from being eligible for coupon application
	 * when integrating with the Magic Coupon plugin.
	 *
	 * @param bool      $valid      Current validity status.
	 * @param WC_Coupon $coupon     Coupon being evaluated.
	 * @param int       $product_id Product ID being checked.
	 * @return bool     Whether the product should be valid for the coupon.
	 */
	public function magic_coupon_coupon_is_valid_for_product( $valid, $coupon, $product_id ) {
		// Product ID is on not_valid for magic coupon
		if ( isset( $this->products_magic_not_valid[ $coupon->get_id() ] ) && is_array( $this->products_magic_not_valid[ $coupon->get_id() ] ) && in_array( $product_id, $this->products_magic_not_valid[ $coupon->get_id() ], true ) ) {
			return false;
		}
		return $valid;
	}

	/**
	 * Add custom product data tab for percentage coupon settings.
	 *
	 * Creates a new tab in the WooCommerce product data metabox for managing
	 * product-specific coupon percentages.
	 *
	 * @param array $tabs Existing product data tabs.
	 * @return array Modified tabs with our settings tab added.
	 */
	public function woocommerce_product_data_tabs( $tabs ) {
		$tabs['product_percentage_coupon'] = array(
			'label'    => __( 'Percentage discount coupons', 'product-percentage-coupon-woo' ),
			'target'   => 'product_percentage_coupon',
			'class'    => array(),
			'priority' => 9999,
		);
		return $tabs;
	}

	/**
	 * Render content for the percentage coupon product data panel.
	 *
	 * Displays all the form fields needed to configure product-specific coupon percentages
	 * for either single mode or per-coupon mode, along with necessary JavaScript.
	 */
	public function woocommerce_product_data_panels() {
		?>
		<div id="product_percentage_coupon" class="panel woocommerce_options_panel">
			<div class="options_group">
				<p>
					<strong>
						<?php echo wp_kses_post( __( 'The fields below are used to set custom percentage discounts for “Percentage discount (per product)” coupons <span class="show_if_variable">and might be overridden at the variations settings</span>', 'product-percentage-coupon-woo' ) ); ?>
					</strong>
				</p>
				<?php
				switch ( $this->mode ) {

					case 'single':
						// Single (BF) mode
						woocommerce_wp_select(
							array(
								'id'      => '_product_percentage_coupon_woo_active_global',
								'label'   => __( 'Activate coupon discount (%)', 'product-percentage-coupon-woo' ),
								'options' => array(
									'yes' => __( 'Yes', 'product-percentage-coupon-woo' ),
									'no'  => __( 'No', 'product-percentage-coupon-woo' ),
								),
								'class'   => 'product_percentage_coupon_woo_active select short',
							)
						);
						woocommerce_wp_text_input(
							array(
								'id'                => '_product_percentage_coupon_woo_amount_global',
								'label'             => __( 'Coupon discount (%)', 'product-percentage-coupon-woo' ),
								'placeholder'       => __( 'Coupon default', 'product-percentage-coupon-woo' ),
								'data_type'         => 'decimal',
								'custom_attributes' => array(
									'size' => 5,
								),
							)
						);
						break;

					default:
						// Default - list all coupons
						$coupons = $this->get_all_coupons();
						if ( ! empty( $coupons ) && is_array( $coupons ) ) {
							foreach ( $coupons as $coupon ) {
								woocommerce_wp_select(
									array(
										'id'      => '_product_percentage_coupon_woo_active_' . $coupon->get_id(),
										'label'   => sprintf(
											/* translators: %s is the coupon code */
											__( 'Activate “%s” discount (%%)', 'product-percentage-coupon-woo' ),
											$coupon->get_code()
										),
										'options' => array(
											'yes' => __( 'Yes', 'product-percentage-coupon-woo' ),
											'no'  => __( 'No', 'product-percentage-coupon-woo' ),
										),
										'class'   => 'product_percentage_coupon_woo_active select short',
									)
								);
								woocommerce_wp_text_input(
									array(
										'id'          => '_product_percentage_coupon_woo_amount_' . $coupon->get_id(),
										'label'       => sprintf(
											/* translators: %s is the coupon code */
											__( '“%s” discount (%%)', 'product-percentage-coupon-woo' ),
											$coupon->get_code()
										),
										'placeholder' => $coupon->get_amount(),
										'data_type'   => 'decimal',
										'custom_attributes' => array(
											'size' => 5,
										),
									)
								);
							}
						} else {
							?>
							<p>
								<?php esc_html_e( 'There are no “Percentage discount (per product)” coupons yet.', 'product-percentage-coupon-woo' ); ?>
							</p>
							<?php
						}
						break;

				}
				?>
				<script type="text/javascript">
				jQuery( function( $ ) {
					function product_percentage_coupon_hide_fields() {
						$( '.product_percentage_coupon_woo_active' ).each( function() {
							var id = $( this ).attr( 'id' );
							var perc_id = id.replace( '_active_', '_amount_' );
							if ( $( this ).val() == 'yes' ) {
								$( '.'+perc_id+'_field' ).show();
							} else {
								$( '.'+perc_id+'_field' ).hide();
							}
						} );
					}
					product_percentage_coupon_hide_fields();
					$( document ).on( 'change', '.product_percentage_coupon_woo_active', function() {
						product_percentage_coupon_hide_fields();
					} );
					$( '#woocommerce-product-data' ).on( 'woocommerce_variations_loaded', function() {
						product_percentage_coupon_hide_fields();
					} );
				} );
				</script>
				<style type="text/css">
					#woocommerce-product-data ul.wc-tabs li.product_percentage_coupon_options a::before,
					.product_percentage_coupon_plugin_title::before {
						font-family: Dashicons;
						speak: none;
						font-weight: 400;
						font-variant: normal;
						text-transform: none;
						line-height: 1;
						-webkit-font-smoothing: antialiased;
						content: "\f323";
						text-decoration: none;
					}
					.product_percentage_coupon_variation_fields .form-field input {
						width: auto !important;
					}
					.product_percentage_coupon_woo_amount_variable_field {
						display: none;
					}
				</style>
				<?php
				// Action for integration
				do_action( 'product_percentage_coupon_product_data_panel_end' );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save product-level percentage coupon settings.
	 *
	 * Processes and saves the custom coupon percentage settings when a product
	 * is being updated in the admin.
	 *
	 * WooCommerce takes care of nonce verification and security checks.
	 *
	 * @param int $post_id ID of the product being saved.
	 */
	public function woocommerce_process_product_meta( $post_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$meta = array();
		switch ( $this->mode ) {

			case 'single':
				// Single (BF) mode
				$meta['_product_percentage_coupon_woo_active_global'] = ! empty( $_POST['_product_percentage_coupon_woo_active_global'] ) ? wc_clean( wp_unslash( $_POST['_product_percentage_coupon_woo_active_global'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$meta['_product_percentage_coupon_woo_amount_global'] = ! empty( $_POST['_product_percentage_coupon_woo_amount_global'] ) ? wc_clean( wp_unslash( $_POST['_product_percentage_coupon_woo_amount_global'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				break;

			default:
				// Default - list all coupons
				$coupons = $this->get_all_coupons();
				if ( ! empty( $coupons ) && is_array( $coupons ) ) {
					foreach ( $coupons as $coupon ) {
						$meta[ '_product_percentage_coupon_woo_active_' . $coupon->get_id() ] = ! empty( $_POST[ '_product_percentage_coupon_woo_active_' . $coupon->get_id() ] ) ? wc_clean( wp_unslash( $_POST[ '_product_percentage_coupon_woo_active_' . $coupon->get_id() ] ) ) : 'yes'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
						$meta[ '_product_percentage_coupon_woo_amount_' . $coupon->get_id() ] = ! empty( $_POST[ '_product_percentage_coupon_woo_amount_' . $coupon->get_id() ] ) ? wc_clean( wp_unslash( $_POST[ '_product_percentage_coupon_woo_amount_' . $coupon->get_id() ] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					}
				}
				break;

		}
		// Update meta - CRUD
		$product = wc_get_product( $post_id );
		foreach ( $meta as $key => $value ) {
			$product->update_meta_data( $key, $value );
		}
		$product->save();
		//phpcs:enable
	}

	/**
	 * Add percentage coupon fields to variation forms.
	 *
	 * Renders the custom coupon percentage fields for each variation of a variable product,
	 * allowing variation-specific discount percentages.
	 *
	 * @param int     $loop           Position in the loop for the current variation.
	 * @param array   $variation_data Variation data.
	 * @param WP_Post $variation      Variation post object.
	 */
	public function woocommerce_product_after_variable_attributes( $loop, $variation_data, $variation ) {
		global $thepostid;
		if ( ! isset( $thepostid ) ) {
			$thepostid = $variation->post_parent;
		}
		$product   = wc_get_product( $thepostid );
		$variation = wc_get_product( $variation->ID );
		?>
		<div class="clear"></div>
		<div id="product_percentage_coupon_<?php echo esc_attr( $loop ); ?>" class="product_percentage_coupon_variation_fields">
			<br/>
			<hr/>
			<p class="form-field form-row form-row-full product_percentage_coupon_plugin_title">
				<strong><?php esc_html_e( 'Percentage discount coupons', 'product-percentage-coupon-woo' ); ?></strong>
			</p>
			<p>
				<?php esc_html_e( 'The fields below are used to set custom percentage discounts for “Percentage discount (per product)” coupons and will override the product level settings', 'product-percentage-coupon-woo' ); ?>
			</p>
			<?php
			switch ( $this->mode ) {

				case 'single':
					// Single (BF) mode
					woocommerce_wp_select(
						array(
							'id'      => "_product_percentage_coupon_woo_active_global_variable{$loop}",
							'name'    => "_product_percentage_coupon_woo_active_global_variable[{$loop}]",
							'label'   => __( 'Activate coupon discount (%)', 'product-percentage-coupon-woo' ),
							'options' => array(
								'no'  => __( 'No (inherit from product)', 'product-percentage-coupon-woo' ),
								'yes' => __( 'Yes', 'product-percentage-coupon-woo' ),
							),
							'value'   => $variation->get_meta( '_product_percentage_coupon_woo_active_global' ),
							'class'   => 'product_percentage_coupon_woo_active select short',
						)
					);
					woocommerce_wp_text_input(
						array(
							'id'                => "_product_percentage_coupon_woo_amount_global_variable{$loop}",
							'name'              => "_product_percentage_coupon_woo_amount_global_variable[{$loop}]",
							'label'             => __( 'Coupon discount (%)', 'product-percentage-coupon-woo' ),
							'placeholder'       => __( 'Coupon default', 'product-percentage-coupon-woo' ),
							'data_type'         => 'decimal',
							'value'             => $variation->get_meta( '_product_percentage_coupon_woo_amount_global' ),
							'custom_attributes' => array(
								'size' => 5,
							),
							'wrapper_class'     => 'product_percentage_coupon_woo_amount_variable_field',
						)
					);
					break;

				default:
					// Default - list all coupons
					$coupons = $this->get_all_coupons();
					if ( ! empty( $coupons ) && is_array( $coupons ) ) {
						foreach ( $coupons as $coupon ) {
							woocommerce_wp_select(
								array(
									'id'      => '_product_percentage_coupon_woo_active_' . $coupon->get_id() . "_variable{$loop}",
									'name'    => '_product_percentage_coupon_woo_active_' . $coupon->get_id() . "_variable[{$loop}]",
									'label'   => sprintf(
										/* translators: %s is the coupon code */
										__( 'Activate “%s” discount (%%)', 'product-percentage-coupon-woo' ),
										$coupon->get_code()
									),
									'options' => array(
										'no'  => __( 'No (inherit from product)', 'product-percentage-coupon-woo' ),
										'yes' => __( 'Yes', 'product-percentage-coupon-woo' ),
									),
									'value'   => $variation->get_meta( '_product_percentage_coupon_woo_active_' . $coupon->get_id() ),
									'class'   => 'product_percentage_coupon_woo_active select short',
								)
							);
							woocommerce_wp_text_input(
								array(
									'id'                => '_product_percentage_coupon_woo_amount_' . $coupon->get_id() . "_variable{$loop}",
									'name'              => '_product_percentage_coupon_woo_amount_' . $coupon->get_id() . "_variable[{$loop}]",
									'label'             => sprintf(
										/* translators: %s is the coupon code */
										__( '“%s” discount (%%)', 'product-percentage-coupon-woo' ),
										$coupon->get_code()
									),
									'placeholder'       => $coupon->get_amount(),
									'data_type'         => 'decimal',
									'value'             => $variation->get_meta( '_product_percentage_coupon_woo_amount_' . $coupon->get_id() ),
									'custom_attributes' => array(
										'size' => 5,
									),
									'wrapper_class'     => 'product_percentage_coupon_woo_amount_variable_field',
								)
							);
						}
					} else {
						?>
						<p>
							<?php esc_html_e( 'There are no “Percentage discount (per product)” coupons yet.', 'product-percentage-coupon-woo' ); ?>
						</p>
						<?php
					}
					break;

			}
			// Action for integration
			do_action( 'product_percentage_coupon_product_variation_data_panel_end' );
			?>
			<div class="clear"></div>
		</div>
		<?php
	}

	/**
	 * Save variation-level percentage coupon settings.
	 *
	 * Processes and saves the custom coupon percentage settings when a product variation
	 * is being updated in the admin.
	 *
	 * WooCommerce takes care of nonce verification and security checks.
	 *
	 * @param int $variation_id ID of the variation being saved.
	 * @param int $index        Index of the variation in the form.
	 */
	public function woocommerce_save_product_variation( $variation_id, $index ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$variation = wc_get_product( $variation_id );

		$meta = array();
		switch ( $this->mode ) {

			case 'single':
				// Single (BF) mode
				$meta['_product_percentage_coupon_woo_active_global'] = ! empty( $_POST['_product_percentage_coupon_woo_active_global_variable'][ $index ] ) ? wc_clean( wp_unslash( $_POST['_product_percentage_coupon_woo_active_global_variable'][ $index ] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$meta['_product_percentage_coupon_woo_amount_global'] = ! empty( $_POST['_product_percentage_coupon_woo_amount_global_variable'][ $index ] ) ? wc_clean( wp_unslash( $_POST['_product_percentage_coupon_woo_amount_global_variable'][ $index ] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				break;

			default:
				// Default - list all coupons
				$coupons = $this->get_all_coupons();
				if ( ! empty( $coupons ) && is_array( $coupons ) ) {
					foreach ( $coupons as $coupon ) {
						$meta[ '_product_percentage_coupon_woo_active_' . $coupon->get_id() ] = ! empty( $_POST[ '_product_percentage_coupon_woo_active_' . $coupon->get_id() . '_variable' ][ $index ] ) ? wc_clean( wp_unslash( $_POST[ '_product_percentage_coupon_woo_active_' . $coupon->get_id() . '_variable' ][ $index ] ) ) : 'yes'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
						$meta[ '_product_percentage_coupon_woo_amount_' . $coupon->get_id() ] = ! empty( $_POST[ '_product_percentage_coupon_woo_amount_' . $coupon->get_id() . '_variable' ][ $index ] ) ? wc_clean( wp_unslash( $_POST[ '_product_percentage_coupon_woo_amount_' . $coupon->get_id() . '_variable' ][ $index ] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					}
				}
				break;

		}
		// Update meta - CRUD
		foreach ( $meta as $key => $value ) {
			$variation->update_meta_data( $key, $value );
		}

		$variation->save();
		// phpcs:enable
	}

	/**
	 * Add our custom coupon type to InvoiceXpress plugin integration.
	 *
	 * Ensures that InvoiceXpress properly recognizes our custom coupon type
	 * when generating invoices.
	 *
	 * @param array $coupon_types Currently allowed coupon types for InvoiceXpress.
	 * @return array Modified list of allowed coupon types.
	 */
	public function invoicexpress_woocommerce_allowed_coupon_types( $coupon_types ) {
		$coupon_types[] = $this->discount_type;
		return $coupon_types;
	}
}