<?php

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @link       https://otakupahp.com/quien-es-pablo-hernandez-otakupahp
 * @author     Pablo Hernández (OtakuPahp) <pablo@otakupahp.com>
 * @since      1.0.0
 *
 * @package    Woo_Contifico
 * @subpackage Woo_Contifico/public
 */
class Woo_Contifico_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	private $version;

	/**
	 * Woo_Contifico instance
	 *
	 * @since 1.3.0
	 * @access private
	 * @var Woo_Contifico $woo_contifico An instance of the main class containing
	 */
	private $woo_contifico;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version The version of this plugin.
	 *
	 * @since   1.0.0
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name   = $plugin_name;
		$this->version       = $version;
		$this->woo_contifico = new Woo_Contifico( false );
	}

	/**
	 * Set recurring actions using the action scheduler
	 *
	 * @see     init
	 *
	 * @since   1.3.0
	 */
	public function init() {

		# Set time in second to the action scheduler
		$time_in_seconds = [
			'daily' => DAY_IN_SECONDS,
			'twicedaily' => 12 * HOUR_IN_SECONDS,
			'hourly' => HOUR_IN_SECONDS,
		];

                if ( ! function_exists( 'as_next_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
                        return;
                }

                # Schedule stock sync event
                if ( isset( $this->woo_contifico->settings['actualizar_stock'] ) && ( $this->woo_contifico->settings['actualizar_stock'] !== 'manual' ) && ! (boolean) as_next_scheduled_action( 'woo_contifico_sync_stock' ) ) {
			as_schedule_recurring_action(
				strtotime('now'),
				$time_in_seconds[ $this->woo_contifico->settings['actualizar_stock'] ],
				'woo_contifico_sync_stock',
				[1],
				$this->plugin_name
			);
		}

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since   1.0.0
	 * @see     wp_enqueue_scripts
	 */
	public function enqueue_styles() {
		# Load the style just in checkout and account pages
		if(is_checkout() || is_account_page()) {
			wp_enqueue_style( $this->plugin_name, WOO_CONTIFICO_URL . 'public/css/woo-contifico-public.css', [], $this->version, 'all' );
		}
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since   1.0.0
	 * @see     wp_enqueue_scripts
	 */
        public function enqueue_scripts() {
                # Load the scripts just in checkout and account pages
                if(is_checkout() || is_account_page()) {
                        wp_enqueue_script( $this->plugin_name, WOO_CONTIFICO_URL . 'public/js/woo-contifico-public.js', [ 'jquery', 'select2' ], $this->version, false );
                }

                if ( function_exists( 'is_product' ) && is_product() ) {
                        $this->enqueue_product_stock_refresh_script();
                }
        }

        /**
         * Exclude the realtime stock script from WP Rocket's delay JavaScript feature.
         *
         * Preventing the delay ensures the stock AJAX request runs as soon as the
         * product page loads, avoiding stale quantities when cache optimizations are
         * enabled.
         *
         * @since 4.3.0
         *
         * @param array $excluded_js Patterns excluded from delay.
         *
         * @return array
         */
        public function exclude_wp_rocket_delay_for_stock_script( $excluded_js ) : array {

                if ( ! is_array( $excluded_js ) ) {
                        $excluded_js = [];
                }

                $stock_handle = "{$this->plugin_name}-product-stock";

                $excluded_js[] = $stock_handle;
                $excluded_js[] = "{$stock_handle}.js";

                return array_values( array_unique( $excluded_js ) );
        }

	/**
	 * Enqueue the realtime stock refresh script in single product pages.
	 *
	 * @since 4.3.0
	 *
	 * @return void
	 */
	private function enqueue_product_stock_refresh_script() : void {

		if ( ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		$product_id = get_queried_object_id();

		if ( $product_id <= 0 ) {
			return;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		$supports_refresh = apply_filters(
			'woo_contifico_should_refresh_product_stock',
			$this->product_supports_stock_refresh( $product ),
			$product
		);

		if ( ! $supports_refresh ) {
			return;
		}

                wp_enqueue_script(
                        "{$this->plugin_name}-product-stock",
                        WOO_CONTIFICO_URL . 'public/js/woo-contifico-product-stock.js',
                        [],
                        $this->version,
                        true
                );

		$stock_selector = apply_filters( 'woo_contifico_product_stock_selector', '.summary .stock', $product );

		wp_localize_script(
			"{$this->plugin_name}-product-stock",
			'wooContificoProductStock',
			[
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'woo_ajax_nonce' ),
				'productId'    => $product->get_id(),
				'sku'          => $product->get_sku(),
				'manageStock'  => $this->product_supports_stock_refresh( $product ),
				'selectors'    => [
					'stockNode' => $stock_selector,
				],
				'messages'     => [
					'syncing'             => __( 'Actualizando inventario…', 'woo-contifico' ),
					'error'               => __( 'No fue posible actualizar el inventario en este momento.', 'woo-contifico' ),
					'inStock'             => __( 'Hay existencias', 'woo-contifico' ),
					'inStockWithQuantity' => __( 'Hay existencias (%d disponibles)', 'woo-contifico' ),
					'outOfStock'          => __( 'Agotado', 'woo-contifico' ),
				],
			]
		);
	}

	/**
	 * Determine if the product supports realtime stock refresh.
	 *
	 * @since 4.3.0
	 *
	 * @param WC_Product $product
	 *
	 * @return bool
	 */
	private function product_supports_stock_refresh( $product ) : bool {

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return false;
		}

		if ( method_exists( $product, 'managing_stock' ) ) {
			if ( (bool) $product->managing_stock() ) {
				return true;
			}
		} elseif ( (bool) $product->get_manage_stock() ) {
			return true;
		}

		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $variation_id ) {
				$variation = wc_get_product( $variation_id );

				if ( ! $variation || ! is_a( $variation, 'WC_Product_Variation' ) ) {
					continue;
				}

				if ( method_exists( $variation, 'managing_stock' ) ) {
					if ( (bool) $variation->managing_stock() ) {
						return true;
					}
				} elseif ( (bool) $variation->get_manage_stock() ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Add Tax fields to checkout.
	 *
	 * @since   1.4.0
	 * @see     woocommerce_checkout_fields
	 *
	 * @param array $checkout_fields
	 * @return   array
	 */
	public function checkout_field( $checkout_fields ) {
		$fields                     = $this->woo_contifico->get_account_fields( true );
		$checkout_fields['billing'] = array_merge( $checkout_fields['billing'], $fields );

		return $checkout_fields;
	}

	/**
	 * Load tax info from logged in users.
	 *
	 * @since   1.4.0
	 * @see     woocommerce_checkout_get_value
	 *
	 * @param $input
	 * @param $key
	 * @return  string
	 */
	public function populate_field( $input, $key ) {

		// Check if field is tax_id, tax_type or tax_subject
		if ( in_array( $key, [ 'tax_id', 'tax_type', 'tax_subject' ] ) ) {

			// if customer is logged in and the field is tax_id or tax_type
			$user_meta = get_user_meta( get_current_user_id(), $key, true );
			if ( ! empty( $user_meta ) ) {
				return esc_attr( get_user_meta( get_current_user_id(), $key, true ) );
			} // The customer is not logged in or his cedula/ruc/pasaporte/exterior is NOT defined
			else {
				return $input;
			}
		} else {
			return $input;
		}

	}

	/**
	 * Validate user account data.
	 *
	 * @since   1.4.0
	 * @see     woocommerce_checkout_process
	 * @see woocommerce_save_account_details_errors
	 */
	public function validate_user_account_data() {

		$tax_subject     = sanitize_text_field( $_POST['tax_subject'] ?? '' );
        $taxpayer_type   = sanitize_text_field( $_POST['taxpayer_type'] ?? '' );
		$billing_company = sanitize_text_field( $_POST['billing_company'] ?? '' );
		$billing_country = sanitize_text_field( $_POST['billing_country'] ?? '' );
		$type            = sanitize_text_field( $_POST['tax_type'] ?? '' );
		$data            = sanitize_text_field( $_POST['tax_id'] ?? '' );

		# Check if Tax Id is set
		if( !empty($data) && in_array($type, ['cedula', 'ruc', 'pasaporte', 'exterior']) ) {
			try {
				$this->woo_contifico->validate_tax_id( $type, $data );
			}
			catch (Exception $exception) {
				wc_add_notice( $exception->getMessage(), 'error' );
			}
		}
		# The tax type was modified by an external plugin
		else {
            wc_add_notice( __( '<b>Tipo de identificación</b> no tiene un valor válido. ¿El valor fue modificado?<br>&nbsp;&nbsp;Los valores válidos son: cedula, ruc, pasaporte, exterior' ), 'error' );
        }

		# The tax payer type was modified by an external plugin
        if( !in_array($taxpayer_type, ['N', 'J']) ) {
            wc_add_notice( __( '<b>Tipo de contribuyente</b> no tiene un valor válido. ¿El valor fue modificado?<br>&nbsp;&nbsp;Los valores válidos son: N y J' ), 'error' );
        }

		# Billing company name required if the option tax subject was checked
		if ( ! empty( $tax_subject ) && empty( $billing_company ) ) {
			wc_add_notice( __( '<b>Nombre de la empresa</b> es un campo requerido cuando solicita emitir el documento a nombre de la empresa' ), 'error' );
		}

		# A client can't use passport if the billing country  EC
		if ( strtolower( $billing_country ) === 'ec' && $type === 'pasaporte' ) {
			wc_add_notice( __( '<b>Tipo de identificación</b> no puede ser pasaporte  si la factura se emite para Ecuador' ), 'error' );
		}

	}

	/**
	 * Update order metadata.
	 *
	 * @since   1.4.0
	 * @see     woocommerce_checkout_update_order_meta
	 *
	 * @param int $order_id
	 * @return  void
	 */
	public function checkout_update_order_meta( $order_id ) {
		$tax_subject   = sanitize_text_field( $_POST['tax_subject'] ?? '' );
		$tax_type      = sanitize_text_field( $_POST['tax_type'] ?? '' );
		$tax_id        = sanitize_text_field( $_POST['tax_id'] ?? '' );
		$taxpayer_type = sanitize_text_field( $_POST['taxpayer_type'] ?? '' );
                if ( ! empty( $tax_id ) ) {
                        $order = wc_get_order( $order_id );
                        if ( ! $order ) {
                                return;
                        }

                        $order->update_meta_data( '_billing_tax_subject', $tax_subject );
                        $order->update_meta_data( '_billing_tax_type', $tax_type );
                        $order->update_meta_data( '_billing_tax_id', $tax_id );
                        $order->update_meta_data( '_billing_taxpayer_type', $taxpayer_type );
                        $order->save();

                        // updating user meta (for customer my account edit details page post data)
                        $user_id = $order->get_customer_id();
                        if ( ! empty( $user_id ) ) {
                                update_user_meta( $user_id, 'tax_subject', $tax_subject );
                                update_user_meta( $user_id, 'tax_type', $tax_type );
                                update_user_meta( $user_id, 'tax_id', $tax_id );
                                update_user_meta( $user_id, 'taxpayer_type', $taxpayer_type );
                        }
                }
	}

	/**
	 * Add fields to my account area.
	 *
	 * @return  void
	 * @see     woocommerce_edit_account_form
	 *
	 * @since   1.4.0
	 */
	public function print_user_frontend_fields() {

		//Get additional account fields
		$fields = $this->woo_contifico->get_account_fields();

		foreach ( $fields as $key => $field_args ) {
			if ( ! empty( $field_args['hide_in_account'] ) ) {
				continue;
			}
			$value = ( isset( $_POST[ $key ] ) ) ? $_POST[ $key ] : $field_args['value'];
			woocommerce_form_field( $key, $field_args, $value );
		}
	}

}
