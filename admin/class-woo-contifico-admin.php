<?php

use Pahp\SDK\Contifico;

if ( ! class_exists( 'Woo_Contifico_Diagnostics_Table' ) ) {
        require_once plugin_dir_path( __FILE__ ) . 'class-woo-contifico-diagnostics-table.php';
}

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @link       https://otakupahp.com/quien-es-pablo-hernandez-otakupahp
 * @author     Pablo Hernández (OtakuPahp) <pablo@otakupahp.com>
 * @since      1.0.0
 *
 * @package    Woo_Contifico
 * @subpackage Woo_Contifico/admin
 *
 */
class Woo_Contifico_Admin {

        private const SYNC_DEBUG_TRANSIENT_KEY = 'woo_contifico_sync_debug_entries';
        private const PRODUCT_ID_META_KEY      = '_woo_contifico_product_id';

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
	 * @since 1.2.0
	 * @access private
	 * @var Woo_Contifico $woo_contifico An instance of the main class containing
	 */
	private $woo_contifico;

	/**
	 * Stores in database configuration errors
	 *
	 * @since 2.1.0
	 * @access private
	 * @var array
	 */
	private $config_status;

	/**
	 * @since 2.1.0
	 * @access private
	 * @var array
	 */
	private $settings_names;

	/**
	 * Contifico SDK class
	 *
	 * @since 1.3.0
	 * @access protected
	 * @var Contifico $contifico contifico class from SDK
	 */
	public $contifico;

	/**
	 * @since 3.1.0
	 * @access private
	 * @var string
	 */
        private $log_path;

        /**
         * @since 4.1.6
         * @access private
         * @var string
         */
        private $sync_debug_log_path;

        /**
         * @since 4.1.6
         * @access private
         * @var string
         */
        private $sync_debug_log_url;

	/**
	 * @since 3.1.0
	 * @access protected
	 * @var string
	 */
	protected $log_route;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version The version of this plugin.
	 *
	 * @since    1.0.0
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name   = $plugin_name;
		$this->version       = $version;
		$this->woo_contifico = new Woo_Contifico( false );

		# On first login, null the plugin
		$env = '';
		if( isset($this->woo_contifico->settings['ambiente']) ) {
			$env     = ( (int) $this->woo_contifico->settings['ambiente'] === WOO_CONTIFICO_TEST ) ? 'test' : 'prod';
                }
                $api_key = $this->woo_contifico->settings["{$env}_api_key"] ?? '';
                $this->log_path = WOO_CONTIFICO_PATH . 'contifico_log.txt';
                $this->log_route = WOO_CONTIFICO_URL. 'contifico_log.txt';
                $this->sync_debug_log_path = WOO_CONTIFICO_PATH . 'contifico_sync_debug_log.txt';
                $this->sync_debug_log_url  = WOO_CONTIFICO_URL . 'contifico_sync_debug_log.txt';
		$this->contifico = new Contifico(
			$api_key ,
			(bool) isset($this->woo_contifico->settings['activar_registro']),
			$this->log_path
		);

                # Load config status
                $this->config_status = get_option('woo_contifico_config_status', [ 'status' => false, 'errors' => [] ]);
                if ( ! is_array( $this->config_status ) ) {
                        $this->config_status = [ 'status' => false, 'errors' => [] ];
                }
                if ( ! isset( $this->config_status['errors'] ) || ! is_array( $this->config_status['errors'] ) ) {
                        $this->config_status['errors'] = [];
                }
                unset( $this->config_status['errors']['plugin'] );
                $this->config_status['status'] = (bool) ( $this->config_status['status'] ?? false );

                # Set settings names
                $this->settings_names = [
                        'woocommerce'     => __( 'WooCommerce', $this->plugin_name ),
			'contifico'       => __( 'Integración con Contífico', $this->plugin_name ),
			'emisor'          => __( 'Emisor de documentos', $this->plugin_name ),
			'establecimiento' => __( 'Establecimiento asociado', $this->plugin_name ),
		];
	}

        /**
         * Register the stylesheets for the admin area.
         *
         * @since 1.0.0
         * @see admin_enqueue_scripts
         *
         * @param string $hook_suffix
         */
        public function enqueue_styles($hook_suffix) {
                $current_post_type = get_post_type();
                $is_product_editor = false;

                if ( 'product' === $current_post_type ) {
                        $is_product_editor = true;
                } elseif ( function_exists( 'get_current_screen' ) ) {
                        $screen = get_current_screen();

                        if ( $screen && isset( $screen->post_type ) && 'product' === $screen->post_type ) {
                                $is_product_editor = true;
                        }
                }

                if ( $hook_suffix === "woocommerce_page_{$this->plugin_name}" || $is_product_editor ) {
                        wp_enqueue_style( $this->plugin_name, WOO_CONTIFICO_URL . 'admin/css/woo-contifico-admin.css', [], $this->version, 'all' );

                        if ( $hook_suffix === "woocommerce_page_{$this->plugin_name}" ) {
                                wp_enqueue_style( "{$this->plugin_name}-diagnostics", WOO_CONTIFICO_URL . 'admin/css/woo-contifico-diagnostics.css', [], $this->version, 'all' );
                        }
                }
        }

        /**
         * Register the JavaScript for the admin area.
         *
         * @since 1.0.0
         * @see admin_enqueue_scripts
         *
         * @param string $hook_suffix
         */
        public function enqueue_scripts($hook_suffix) {
                $current_post_type = get_post_type();
                $is_product_editor = false;
                $should_enqueue_js = false;

                if ( 'product' === $current_post_type ) {
                        $is_product_editor = true;
                } elseif ( function_exists( 'get_current_screen' ) ) {
                        $screen = get_current_screen();

                        if ( $screen && isset( $screen->post_type ) && 'product' === $screen->post_type ) {
                                $is_product_editor = true;
                        }
                }

                if ( $hook_suffix === "woocommerce_page_{$this->plugin_name}" || 'shop_order' === $current_post_type || $is_product_editor ) {
                        $should_enqueue_js = true;
                }

                if ( ! $should_enqueue_js ) {
                        return;
                }

                wp_enqueue_script( $this->plugin_name, WOO_CONTIFICO_URL . 'admin/js/woo-contifico-admin.js', [ 'jquery' ], $this->version, false );

                if ( $hook_suffix === "woocommerce_page_{$this->plugin_name}" ) {
                        wp_enqueue_script( "{$this->plugin_name}-diagnostics", WOO_CONTIFICO_URL . 'admin/js/woo-contifico-diagnostics.js', [ 'jquery' ], $this->version, true );
                }

                $params = [
                        'plugin_name' => $this->plugin_name,
                        'woo_nonce'   => wp_create_nonce( 'woo_ajax_nonce' ),
                        'messages'    => [
                                'stockUpdated'     => __( 'Inventario actualizado.', 'woo-contifico' ),
                                'priceUpdated'     => __( 'Precio actualizado.', 'woo-contifico' ),
                                'metaUpdated'      => __( 'Identificador de Contífico actualizado.', 'woo-contifico' ),
                                'outOfStock'       => __( 'Producto sin stock.', 'woo-contifico' ),
                                'noChanges'        => __( 'Sin cambios en inventario ni precio.', 'woo-contifico' ),
                                'wooSkuLabel'      => __( 'SKU en WooCommerce:', 'woo-contifico' ),
                                'contificoSkuLabel'=> __( 'SKU en Contífico:', 'woo-contifico' ),
                                'contificoIdLabel' => __( 'ID de Contífico:', 'woo-contifico' ),
                                'stockLabel'       => __( 'Inventario disponible:', 'woo-contifico' ),
                                'priceLabel'       => __( 'Precio actual:', 'woo-contifico' ),
                                'changesLabel'     => __( 'Cambios detectados:', 'woo-contifico' ),
                        ],
                ];
                wp_localize_script( $this->plugin_name, 'woo_contifico_globals', $params );
        }

        /**
         * Display the stored Contífico identifier in the main product editor.
         *
         * @since 4.2.0
         *
         * @return void
         */
        public function display_contifico_product_identifier() : void {

                if ( ! function_exists( 'wc_get_product' ) ) {
                        return;
                }

                global $post;

                if ( ! $post || ! isset( $post->ID ) || 'product' !== $post->post_type ) {
                        return;
                }

                $product = wc_get_product( $post->ID );

                if ( ! $product ) {
                        return;
                }

                $contifico_id       = (string) $product->get_meta( self::PRODUCT_ID_META_KEY, true );
                $product_sku        = (string) $product->get_sku();
                $generic_error      = __( 'No fue posible sincronizar el producto. Intenta nuevamente.', 'woo-contifico' );
                $missing_identifier = __( 'No hay identificador de Contífico guardado.', 'woo-contifico' );
                $sync_button_label  = __( 'Sincronizar con Contífico', 'woo-contifico' );

                echo '<div class="options_group woo-contifico-product-id-field"';
                echo ' data-product-id="' . esc_attr( (string) $product->get_id() ) . '"';
                echo ' data-product-sku="' . esc_attr( $product_sku ) . '"';
                echo ' data-generic-error="' . esc_attr( $generic_error ) . '"';
                echo ' data-missing-identifier="' . esc_attr( $missing_identifier ) . '"';
                echo '>';
                echo '<p class="form-field">';
                echo '<label>' . esc_html__( 'ID de Contífico', 'woo-contifico' ) . '</label>';

                if ( '' !== $contifico_id ) {
                        echo '<span class="woo-contifico-product-id-value">' . esc_html( $contifico_id ) . '</span>';
                } else {
                        echo '<span class="woo-contifico-product-id-missing">' . esc_html( $missing_identifier ) . '</span>';
                }

                echo '</p>';
                echo '<div class="woo-contifico-product-sync-controls">';
                echo '<button type="button" class="button button-secondary woo-contifico-sync-product-button">' . esc_html( $sync_button_label ) . '</button>';
                echo '<span class="spinner woo-contifico-sync-spinner"></span>';
                echo '</div>';
                echo '<div class="woo-contifico-sync-result" aria-live="polite"></div>';
                echo '</div>';
        }

        /**
         * Display the stored Contífico identifier for each variation.
         *
         * @since 4.2.0
         *
         * @param int      $loop
         * @param array    $variation_data
         * @param WP_Post  $variation
         *
         * @return void
         */
        public function display_contifico_variation_identifier( $loop, $variation_data, $variation ) : void {

                if ( ! function_exists( 'wc_get_product' ) ) {
                        return;
                }

                $variation_id = 0;

                if ( is_numeric( $variation ) ) {
                        $variation_id = (int) $variation;
                } elseif ( is_object( $variation ) && isset( $variation->ID ) ) {
                        $variation_id = (int) $variation->ID;
                }

                if ( $variation_id <= 0 ) {
                        return;
                }

                $variation_product = wc_get_product( $variation_id );

                if ( ! $variation_product ) {
                        return;
                }

                $contifico_id = (string) $variation_product->get_meta( self::PRODUCT_ID_META_KEY, true );

                if ( '' === $contifico_id ) {
                        $parent_id = $variation_product->get_parent_id();

                        if ( $parent_id ) {
                                $parent_product = wc_get_product( $parent_id );

                                if ( $parent_product ) {
                                        $contifico_id = (string) $parent_product->get_meta( self::PRODUCT_ID_META_KEY, true );
                                }
                        }
                }

                echo '<div class="woo-contifico-variation-id">';
                echo '<span class="woo-contifico-variation-id-label">' . esc_html__( 'ID de Contífico:', 'woo-contifico' ) . '</span> ';

                if ( '' !== $contifico_id ) {
                        echo '<span class="woo-contifico-variation-id-value">' . esc_html( $contifico_id ) . '</span>';
                } else {
                        echo '<span class="woo-contifico-variation-id-missing">' . esc_html__( 'No hay identificador de Contífico guardado.', 'woo-contifico' ) . '</span>';
                }

                echo '</div>';
        }

	/**
	 * Add settings option in plugins list table.
	 *
	 * @param array $links Current plugin links
	 *
	 * @return  array
	 * @noinspection PhpUnused
	 * @since   1.2.0
	 * @see plugin_action_links_woo-contifico.php
	 *
	 */
	public function add_settings_link( array $links ) : array {

		$settings_link = '<a href="' . admin_url( 'admin.php?page=' . $this->plugin_name ) . '">' . __( 'Ajustes', $this->plugin_name ) . '</a>';
		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Register menu for the admin area.
	 *
	 * @return void
	 * @see admin_menu
	 *
	 * @since    1.2.0
	 */
	public function register_menu() {
		add_submenu_page( 'woocommerce', __( 'Facturación Electrónica Contifico', $this->plugin_name ), __( 'Facturación Electrónica Contifico', $this->plugin_name ), 'manage_woocommerce', $this->plugin_name, [
			$this,
			'plugin_settings_page'
		] );
	}

	/**
	 * Initialize admin settings
	 *
	 * @return  void
	 * @see admin_init
	 *
	 * @since   1.2.0
        */
       public function admin_init() {

                if (
                        $this->woo_contifico->multilocation instanceof Woo_Contifico_MultiLocation_Compatibility
                        && $this->woo_contifico->multilocation->is_active()
                ) {
                        $warehouse_options = [
                                '' => __( 'Seleccione una bodega', $this->plugin_name ),
                        ];

                        try {
                                $this->contifico->fetch_warehouses();
                        }
                        catch ( Exception $exception ) {
                                add_settings_error(
                                        'woo_contifico_settings',
                                        'warehouses_fetch_error',
                                        sprintf(
                                                /* translators: %s: error message */
                                                __( 'No se pudo actualizar la lista de bodegas de Contífico: %s', $this->plugin_name ),
                                                $exception->getMessage()
                                        )
                                );
                        }

                        $warehouses = (array) get_option( 'woo_contifico_warehouses', [] );

                        foreach ( $warehouses as $warehouse_id => $warehouse_code ) {
                                $code  = (string) $warehouse_code;
                                $label = $code;
                                if ( '' !== (string) $warehouse_id ) {
                                        $label = sprintf( '%s (%s)', $code, $warehouse_id );
                                }
                                $warehouse_options[ $code ] = $label;
                        }

                        foreach ( $this->woo_contifico->settings_fields['woo_contifico_integration']['fields'] as &$field ) {
                                if ( isset( $field['multiloca_location_id'] ) ) {
                                        $field['options'] = $warehouse_options;
                                }
                        }
                        unset( $field );
                }

                # Register plugin settings
                foreach ( $this->woo_contifico->settings_fields as $index => $section ) {
                        add_settings_section( $index, $section['name'], [ $this, 'show_settings_section' ], "{$index}_settings" );
			foreach ( $section['fields'] as $field ) {
				$field['setting_name'] = "{$index}_settings";
				if( !isset($field['id'])) {
					$field['id'] = '';
				}
				add_settings_field(
					"{$this->plugin_name}_{$field['id']}",
					$field['label'],
					[ $this, 'print_fields' ],
					"{$index}_settings",
					$index,
					$field
				);
			}
			$args = isset( $section['validation_function'] ) ? [
				$this,
				$section['validation_function']
			] : [];
			register_setting( "{$index}_settings", "{$index}_settings", $args );
		}

		# Avoid double notice
		global $pagenow;
		if ( $pagenow !== 'options.php' ) {

                        # Check that all needed settings are filled
                        foreach ( $this->woo_contifico->settings['settings_status'] as $key => $value ) {
                                if ( $value === TRUE && ! isset( $this->config_status['errors'][ $key ] ) ) {
                                        $this->config_status['errors'][ $key ] = __( 'Configure la opción', $this->plugin_name );
                                }
                        }

			$message = '';
			if( $this->config_status['status'] === false ) {
				foreach ( $this->config_status['errors'] as $key => $error ) {
					if( isset($this->settings_names[ $key ]) ) {
						$message .= sprintf(
							__( '<br> - La configuración para <!--suppress HtmlUnknownTarget -->
							<a href="%s">%s</a> es incorrecta: %s', $this->plugin_name ),
							admin_url( "admin.php?page={$this->plugin_name}&tab={$key}" ),
							$this->settings_names[ $key ],
							$error
						);
					}
				}
			}

			if ( ! empty( $message ) ) {
				add_settings_error(
					'woo_contifico_init',
					'settings_registered',
					__( "<b>Facturación Electrónica Contífico</b>{$message}", $this->plugin_name ),
					'error'
				);
			}
			# If the plugin is in test mode, notify the user about it
			elseif( WOO_CONTIFICO_TEST === (int)$this->woo_contifico->settings['ambiente'] ) {
					/** @noinspection HtmlUnknownTarget */
					add_settings_error(
						'woo_contifico_init',
						'woo_contifico_environment',
						sprintf(
							__('<b>Facturación Electrónica Contífico:</b> Plugin en ambiente de pruebas. <a href="%s">Cambiar a producción</a>', $this->plugin_name),
							admin_url( "admin.php?page={$this->plugin_name}&tab=contifico" )
						),
						'warning'
					);
				}
		}

	}

	/**
	 *  Show admin notices for init check
	 *
	 * @return void
	 * @see admin_notices
	 *
	 * @since 1.3.0
	 */
	public function admin_init_notice() {
		# Avoid double notice
		global $pagenow;
		if ( strpos( $pagenow, 'option' ) === false ) {
			settings_errors( 'woo_contifico_init' );
		}

	}

	/**
	 * Load plugin configuration page
	 *
	 * @return  void
	 * @see     add_submenu_page()
	 *
	 * @since   1.2.0
	 */
	public function plugin_settings_page() {
		require_once plugin_dir_path( __FILE__ ) . 'partials/woo-contifico-admin-display.php';
	}

	/**
	 * Shows information for each section of the settings page
	 *
	 * @param array $section Section array
	 *
	 * @return  void
	 * @since   1.2.0
	 * @see     add_settings_section()
	 *
	 */
	public function show_settings_section( $section ) {
		echo "<span>{$this->woo_contifico->settings_fields[$section['id']]['description']}</span>";
	}

        /**
         * Display settings fields
         *
         * @param array $args Arguments send by add_contact_fields()
         *
         * @return void
         * @since 1.2.0
         * @see    add_contact_fields()
         *
         */
        public function print_fields( $args ) {
                $name     = $args['custom_name'] ?? "{$args['setting_name']}[{$args['id']}]";
                $key       = "{$this->plugin_name}_{$args['id']}";
                if ( isset( $args['value_key'] ) ) {
                        $value = $this->get_setting_value( (array) $args['value_key'] );
                }
                else {
                        $value = $this->woo_contifico->settings[ $args['id'] ] ?? '';
                }
                $required = ( isset($args['required']) && $args['required'] ) ? 'required' : '';
                $field    = '';

		switch ( $args['type'] ) {
			case 'hidden':
				$field = "<input type='{$args['type']}' name='{$name}' id='{$key}' value='{$value}' />";
				break;
			case 'text':
			case 'password':
				$field = "<input type='{$args['type']}' class='input-text' name='{$name}' id='{$key}' value='{$value}' {$required} size='{$args['size']}' />";
				break;
			case 'select':
				$options = '';
				if ( ! empty( $args['options'] ) ) {
					foreach ( $args['options'] as $option_key => $option_text ) {
						$options .= "<option value='{$option_key}' " . selected( $value, $option_key, false ) . ">{$option_text}</option>";
					}
					$field = "<select name='{$name}' id='{$key}' class='select' >{$options}</select>";
				}
				break;
			case 'radio':
				$field = '';
				if ( ! empty( $args['options'] ) ) {
					foreach ( $args['options'] as $option_key => $option_text ) {
						$checked = checked( $value, $option_key, false );
						$field   .= "<input type='radio' class='input-radio' value='{$option_key}' name='{$name}' id='{$key}_{$option_key}' {$checked} />";
						$field   .= "<label for='{$key}_{$option_key}' class='radio'>{$option_text}</label>";
					}
				}
				break;
                        case 'check':
                                $checked = checked( $value, true, false );
                                $value   = $value ?: 1;
                                $field   = "<input type='checkbox' class='input-check' value='{$value}' name='{$name}' id='{$key}' {$checked} />";
                                break;
                        case 'textarea':
                                $rows  = isset( $args['rows'] ) ? (int) $args['rows'] : 5;
                                $cols  = isset( $args['cols'] ) ? (int) $args['cols'] : 50;
                                $value = esc_textarea( (string) $value );
                                $field = "<textarea name='{$name}' id='{$key}' rows='{$rows}' cols='{$cols}' class='textarea'>{$value}</textarea>";
                                break;
                        case 'title':
                                $field = "&nbsp;";
                                break;
                }

                $desc = empty( $args['description'] ) ? '' : "<span>{$args['description']}</span>";
                echo "{$field}&nbsp;{$desc}";
        }

        /**
         * Retrieve a nested setting value.
         *
         * @param array $path
         *
         * @return mixed
         */
        private function get_setting_value( array $path ) {
                $value = $this->woo_contifico->settings;

                foreach ( $path as $segment ) {
                        if ( is_array( $value ) && array_key_exists( $segment, $value ) ) {
                                $value = $value[ $segment ];
                        }
                        else {
                                return '';
                        }
                }

                return is_array( $value ) ? '' : $value;
        }

        /**
         * Maintain backward compatibility with the old activation flag.
         *
         * @since 2.1.0
         *
         * @return bool
         */
        public function is_active() : bool {
                if ( isset( $this->config_status['errors']['plugin'] ) ) {
                        unset( $this->config_status['errors']['plugin'] );
                        $this->update_config_status();
                }

                return true;
        }

        /**
         * Ajax function to fetch and save products
	 *
	 * @since 1.3.0
	 * @see wp_ajax_fetch_products
	 * @noinspection PhpUnused
	 */
        public function fetch_products() {

                # Check the validity of the ajax request
                check_ajax_referer( 'woo_ajax_nonce', 'security' );

		# Get sync step
		$step = isset($_POST['step']) ? (int) sanitize_text_field($_POST['step']) : 1;

                # Reset transients if the process is starting
                if( $step === 1 ) {
                        delete_transient( 'woo_contifico_fetch_productos' );
                        delete_transient( 'woo_contifico_full_inventory' );
                        delete_transient( 'woo_sync_result' );
                        $this->reset_sync_debug_log();
                        $this->contifico->reset_inventory_cache();
                }

		try {
			# Sync Contifico products
			$result = $this->sync_stock( $step, $this->woo_contifico->settings['batch_size'] );

			# Return the result of the fetch
			wp_send_json( $result );
		}
		catch (Exception $exception) {
			# Return the error from the server
			wp_send_json( $exception->getMessage(),  500);
                }
        }

        /**
         * Ajax endpoint to synchronize a single product.
         *
         * @since 4.2.0
         *
         * @return void
         */
        public function sync_single_product() : void {

                check_ajax_referer( 'woo_ajax_nonce', 'security' );

                $sku        = isset( $_POST['sku'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sku'] ) ) : '';
                $product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;

                if ( $product_id <= 0 && '' === $sku ) {
                        wp_send_json_error(
                                [ 'message' => __( 'Debes proporcionar un SKU para iniciar la sincronización.', 'woo-contifico' ) ],
                                400
                        );

                        return;
                }

                try {
                        if ( $product_id > 0 ) {
                                $result = $this->sync_single_product_by_product_id( $product_id, $sku );
                        } else {
                                $result = $this->sync_single_product_by_sku( $sku );
                        }

                        wp_send_json_success( $result );
                }
                catch ( Exception $exception ) {
                        wp_send_json_error(
                                [ 'message' => $exception->getMessage() ],
                                500
                        );
                }
        }

	/**
	 * Batch processing function
	 *
	 * @since 2.0.0
	 * @see woo_contifico_sync_stock
	 * @noinspection PhpUnused
	 *
	 * @param int $step
	 * @throws Exception
	 */
        public function batch_sync_processing(int $step = 1) {

                # Reset transients if the process is starting
                if( $step === 1 ) {
                        delete_transient( 'woo_contifico_fetch_productos' );
                        delete_transient( 'woo_contifico_full_inventory' );
                        delete_transient( 'woo_sync_result' );
                        $this->reset_sync_debug_log();
                }

		$result = $this->sync_stock($step, $this->woo_contifico->settings['batch_size']);

		# Rerun the batch if the batch is not finished yet
		if($result['step'] !== 'done') {
			as_enqueue_async_action( 'woo_contifico_sync_stock', [$result['step']], $this->plugin_name );
		}
	}

	/**
	 * Batch function to synchronize stock
	 *
	 * @since 1.3.0
	 *
	 * @param int $step
	 * @param int $batch_size
	 * @return array sync results
	 * @throws Exception
	 */
        public function sync_stock(int $step, int $batch_size) : array
        {

                $result = [];

		# Check is plugin is active
		if ( $this->is_active() === true ) {

                        # Fetch warehouse stock
                        $this->contifico->fetch_warehouses();
                        $location_stock    = [];
                        $location_map      = [];
                        $manage_stock      = wc_string_to_bool( get_option( 'woocommerce_manage_stock' ) );
                        $id_warehouse      = $this->contifico->get_id_bodega( $this->woo_contifico->settings['bodega'] );
                        $warehouses_map    = $this->contifico->get_warehouses_map();
                        $debug_log_entries = get_transient( self::SYNC_DEBUG_TRANSIENT_KEY );

                        if ( ! is_array( $debug_log_entries ) ) {
                                $debug_log_entries = [];
                        }

                        if ( $manage_stock ) {
                                if (
                                        $this->woo_contifico->multilocation instanceof Woo_Contifico_MultiLocation_Compatibility
                                        && $this->woo_contifico->multilocation->is_active()
                                ) {
                                        $configured_locations = $this->woo_contifico->settings['multiloca_locations'] ?? [];

                                        if ( is_array( $configured_locations ) ) {
                                                foreach ( $configured_locations as $location_id => $warehouse_code ) {
                                                        $code = (string) $warehouse_code;

                                                        if ( '' === $code ) {
                                                                continue;
                                                        }

                                                        $location_map[ (string) $location_id ] = $code;
                                                }
                                        }
                                }
                        }

                        # Get products of this batch
                        $fetched_products = $this->contifico->fetch_products( $step, $batch_size );

                        $products_by_sku = [];
                        $contifico_skus  = [];

                        foreach ( $fetched_products as $product_data ) {
                                if ( ! is_array( $product_data ) ) {
                                        continue;
                                }

                                $sku = isset( $product_data['sku'] ) ? (string) $product_data['sku'] : '';

                                if ( '' === $sku ) {
                                        continue;
                                }

                                $products_by_sku[ $sku ] = $product_data;
                                $contifico_skus[]         = $sku;

                                foreach ( $this->generate_alternate_skus( $sku ) as $alternate_sku ) {
                                        if ( ! isset( $products_by_sku[ $alternate_sku ] ) ) {
                                                $products_by_sku[ $alternate_sku ] = $product_data;
                                        }

                                        $contifico_skus[] = $alternate_sku;
                                }
                        }

                        if ( ! empty( $contifico_skus ) ) {
                                $contifico_skus = array_values( array_unique( $contifico_skus ) );
                        }

                        # Check if the batch processing is finished
                        if ( empty( $fetched_products ) )
                        {
                                $this->write_sync_debug_log( $debug_log_entries );
                                delete_transient( self::SYNC_DEBUG_TRANSIENT_KEY );
                                $result = [
                                        'step'      => 'done',
                                        'debug_log' => $this->sync_debug_log_url,
                                ];
                        }
                        else {

				# Get result transient
				$batch_result = (array) get_transient('woo_sync_result');

				# Results of the synchronization
				$result = [
					'fetched'    => $this->contifico->count_fetched_products(),
					'found'      => $batch_result['found'] ?? 0,
					'updated'    => $batch_result['updated'] ?? 0,
					'outofstock' => $batch_result['outofstock'] ?? 0,
					'step'       => $step+1,
				];

				# Get WooCommerce products from this batch
                                $products                 = [];
                                $matched_contifico_ids    = [];

                                if ( ! empty( $contifico_skus ) ) {
                                        $products_ids = $this->get_products_ids_by_skus( $contifico_skus );
                                }
                                else {
                                        $products_ids = [];
                                }

                                foreach ( $products_ids as $product_id ) {
                                        $wc_product = wc_get_product($product_id);
                                        if ( ! $wc_product ) {
                                                continue;
                                        }

                                        $sku = (string) $wc_product->get_sku();

                                        if ( '' === $sku || ! isset( $products_by_sku[ $sku ] ) ) {
                                                continue;
                                        }

                                        $contifico_product = $products_by_sku[ $sku ];
                                        $contifico_id      = isset( $contifico_product['codigo'] ) ? (string) $contifico_product['codigo'] : '';
                                        $contifico_sku      = isset( $contifico_product['sku'] ) ? (string) $contifico_product['sku'] : $sku;

                                        if ( '' === $contifico_id ) {
                                                continue;
                                        }

                                        $resolved_product = $this->resolve_wc_product_for_contifico_sku( $wc_product, $contifico_sku );

                                        if ( ! $resolved_product ) {
                                                continue;
                                        }

                                        # Keep Contifico product ID and WP product object
                                        $products[] = [
                                                'id'       => $contifico_id,
                                                'pvp1'     => isset( $contifico_product['pvp1'] ) ? (float) $contifico_product['pvp1'] : 0.0,
                                                'pvp2'     => isset( $contifico_product['pvp2'] ) ? (float) $contifico_product['pvp2'] : 0.0,
                                                'pvp3'     => isset( $contifico_product['pvp3'] ) ? (float) $contifico_product['pvp3'] : 0.0,
                                                'product'  => $resolved_product,
                                        ];
                                        $matched_contifico_ids[ $contifico_id ] = true;
                                }

                                foreach ( $fetched_products as $product_data ) {
                                        if ( ! is_array( $product_data ) ) {
                                                continue;
                                        }

                                        $contifico_id = isset( $product_data['codigo'] ) ? (string) $product_data['codigo'] : '';

                                        if ( '' === $contifico_id || isset( $matched_contifico_ids[ $contifico_id ] ) ) {
                                                continue;
                                        }

                                        $contifico_sku = isset( $product_data['sku'] ) ? (string) $product_data['sku'] : '';

                                        if ( '' === $contifico_sku ) {
                                                continue;
                                        }

                                        $product_id = $this->find_wc_product_id_for_contifico_sku( $contifico_sku );

                                        if ( $product_id <= 0 ) {
                                                continue;
                                        }

                                        $wc_product = wc_get_product( $product_id );

                                        if ( ! $wc_product ) {
                                                continue;
                                        }

                                        $resolved_product = $this->resolve_wc_product_for_contifico_sku( $wc_product, $contifico_sku );

                                        if ( ! $resolved_product ) {
                                                continue;
                                        }

                                        $products[] = [
                                                'id'      => $contifico_id,
                                                'pvp1'    => isset( $product_data['pvp1'] ) ? (float) $product_data['pvp1'] : 0.0,
                                                'pvp2'    => isset( $product_data['pvp2'] ) ? (float) $product_data['pvp2'] : 0.0,
                                                'pvp3'    => isset( $product_data['pvp3'] ) ? (float) $product_data['pvp3'] : 0.0,
                                                'product' => $resolved_product,
                                        ];

                                        $matched_contifico_ids[ $contifico_id ] = true;
                                }

                                $result['found'] = $result['found'] + count( $products );

                                if ( $manage_stock && ! empty( $location_map ) && ! empty( $products ) ) {
                                        $product_ids_for_batch = array_map(
                                                static function ( array $product_entry ) : string {
                                                        return isset( $product_entry['id'] ) ? (string) $product_entry['id'] : '';
                                                },
                                                $products
                                        );

                                        $product_ids_for_batch = array_values( array_filter( $product_ids_for_batch, 'strlen' ) );

                                        if ( ! empty( $product_ids_for_batch ) ) {
                                                $warehouses_stock = $this->contifico->get_warehouses_stock(
                                                        array_values( $location_map ),
                                                        $product_ids_for_batch
                                                );

                                                foreach ( $location_map as $location_id => $warehouse_code ) {
                                                        $location_id                    = (string) $location_id;
                                                        $warehouse_code                 = (string) $warehouse_code;
                                                        $location_stock[ $location_id ] = $warehouses_stock[ $warehouse_code ] ?? [];
                                                }
                                        }
                                }

                                # Update new stock and price
                                $product_stock_cache = [];
                                $warehouse_id_cache  = [];

                                foreach ( $products as $product ) {

                                        $this->update_product_from_contifico_data(
                                                $product,
                                                $result,
                                                $debug_log_entries,
                                                $product_stock_cache,
                                                $warehouse_id_cache,
                                                $location_stock,
                                                $warehouses_map,
                                                $location_map,
                                                (string) $id_warehouse
                                        );

                                }

                                # Store results in a transient to get it in the next batch
                                set_transient( 'woo_sync_result', $result, HOUR_IN_SECONDS );
                                set_transient( self::SYNC_DEBUG_TRANSIENT_KEY, $debug_log_entries, HOUR_IN_SECONDS );
			}

		}

                return $result;

        }

        /**
         * Synchronize a single product identified by product ID.
         *
         * @since 4.2.0
         *
         * @param int    $product_id
         * @param string $fallback_sku
         *
         * @return array
         * @throws Exception
         */
        private function sync_single_product_by_product_id( int $product_id, string $fallback_sku = '' ) : array {

                if ( $product_id <= 0 ) {
                        throw new Exception( __( 'No se indicó un producto válido para sincronizar.', 'woo-contifico' ) );
                }

                if ( $this->is_active() !== true ) {
                        throw new Exception( __( 'El conector no está activo.', 'woo-contifico' ) );
                }

                $environment = $this->prepare_single_product_sync_environment();

                $wc_product = wc_get_product( $product_id );

                if ( ! $wc_product ) {
                        throw new Exception( __( 'No se pudo cargar el producto de WooCommerce.', 'woo-contifico' ) );
                }

                $lookup_sku = trim( $fallback_sku );

                if ( '' === $lookup_sku ) {
                        $lookup_sku = (string) $wc_product->get_sku();
                }

                return $this->execute_single_product_sync( $wc_product, $environment, $lookup_sku );
        }

        /**
         * Prepare shared context for single-product synchronization.
         *
         * @since 4.2.0
         *
         * @return array{manage_stock:bool,default_warehouse_id:string,warehouses_map:array,location_map:array}
         */
        private function prepare_single_product_sync_environment() : array {
                $this->contifico->fetch_warehouses();

                $manage_stock   = wc_string_to_bool( get_option( 'woocommerce_manage_stock' ) );
                $id_warehouse   = $this->contifico->get_id_bodega( $this->woo_contifico->settings['bodega'] );
                $warehouses_map = $this->contifico->get_warehouses_map();
                $location_map   = [];

                if ( $manage_stock ) {
                        if (
                                $this->woo_contifico->multilocation instanceof Woo_Contifico_MultiLocation_Compatibility
                                && $this->woo_contifico->multilocation->is_active()
                        ) {
                                $configured_locations = $this->woo_contifico->settings['multiloca_locations'] ?? [];

                                if ( is_array( $configured_locations ) ) {
                                        foreach ( $configured_locations as $location_id => $warehouse_code ) {
                                                $code = (string) $warehouse_code;

                                                if ( '' === $code ) {
                                                        continue;
                                                }

                                                $location_map[ (string) $location_id ] = $code;
                                        }
                                }
                        }
                }

                return [
                        'manage_stock'         => $manage_stock,
                        'default_warehouse_id' => is_scalar( $id_warehouse ) ? (string) $id_warehouse : '',
                        'warehouses_map'       => is_array( $warehouses_map ) ? $warehouses_map : [],
                        'location_map'         => $location_map,
                ];
        }

        /**
         * Execute the synchronization for a resolved WooCommerce product.
         *
         * @since 4.2.0
         *
         * @param WC_Product $resolved_product
         * @param array      $environment
         * @param string     $lookup_sku
         *
         * @return array
         * @throws Exception
         */
        private function execute_single_product_sync( $resolved_product, array $environment, string $lookup_sku = '' ) : array {

                if ( ! $resolved_product || ! is_a( $resolved_product, 'WC_Product' ) ) {
                        throw new Exception( __( 'No se pudo cargar el producto de WooCommerce.', 'woo-contifico' ) );
                }

                $lookup_sku = trim( $lookup_sku );

                if ( '' === $lookup_sku ) {
                        $lookup_sku = (string) $resolved_product->get_sku();
                }

                $contifico_product = $this->get_contifico_product_data_for_product( $resolved_product, $lookup_sku );

                if ( empty( $contifico_product ) || ! is_array( $contifico_product ) ) {
                        $contifico_meta_id = (string) $resolved_product->get_meta( self::PRODUCT_ID_META_KEY, true );

                        if ( '' !== $contifico_meta_id ) {
                                throw new Exception(
                                        sprintf(
                                                __( 'No se encontró el producto con el identificador de Contífico "%s" en Contífico.', 'woo-contifico' ),
                                                $contifico_meta_id
                                        )
                                );
                        }

                        if ( '' !== $lookup_sku ) {
                                throw new Exception(
                                        sprintf( __( 'No se encontró el producto con el SKU "%s" en Contífico.', 'woo-contifico' ), $lookup_sku )
                                );
                        }

                        throw new Exception( __( 'No se encontró el producto en Contífico.', 'woo-contifico' ) );
                }

                $contifico_id  = isset( $contifico_product['codigo'] ) ? (string) $contifico_product['codigo'] : '';
                $contifico_sku = isset( $contifico_product['sku'] ) ? (string) $contifico_product['sku'] : $lookup_sku;

                if ( '' === $contifico_id ) {
                        throw new Exception( __( 'El producto de Contífico no tiene un identificador válido.', 'woo-contifico' ) );
                }

                $product_entry = [
                        'id'      => $contifico_id,
                        'pvp1'    => isset( $contifico_product['pvp1'] ) ? (float) $contifico_product['pvp1'] : 0.0,
                        'pvp2'    => isset( $contifico_product['pvp2'] ) ? (float) $contifico_product['pvp2'] : 0.0,
                        'pvp3'    => isset( $contifico_product['pvp3'] ) ? (float) $contifico_product['pvp3'] : 0.0,
                        'product' => $resolved_product,
                ];

                $result = [
                        'found'      => 1,
                        'updated'    => 0,
                        'outofstock' => 0,
                ];

                $debug_log_entries   = [];
                $product_stock_cache = [];
                $warehouse_id_cache  = [];
                $location_stock      = [];

                $warehouses_map = isset( $environment['warehouses_map'] ) && is_array( $environment['warehouses_map'] )
                        ? $environment['warehouses_map']
                        : [];
                $location_map   = isset( $environment['location_map'] ) && is_array( $environment['location_map'] )
                        ? $environment['location_map']
                        : [];
                $default_warehouse = isset( $environment['default_warehouse_id'] )
                        ? (string) $environment['default_warehouse_id']
                        : '';

                $changes = $this->update_product_from_contifico_data(
                        $product_entry,
                        $result,
                        $debug_log_entries,
                        $product_stock_cache,
                        $warehouse_id_cache,
                        $location_stock,
                        $warehouses_map,
                        $location_map,
                        $default_warehouse
                );

                $message = __( 'El producto se sincronizó correctamente y no registró cambios.', 'woo-contifico' );

                if ( $changes['stock_updated'] || $changes['price_updated'] ) {
                        $message = __( 'El producto se sincronizó correctamente.', 'woo-contifico' );
                }

                if ( $changes['outofstock'] ) {
                        $message = __( 'El producto se sincronizó correctamente y quedó sin stock.', 'woo-contifico' );
                }

                if ( $changes['meta_updated'] && ! ( $changes['stock_updated'] || $changes['price_updated'] ) ) {
                        $message = __( 'Se actualizó el identificador de Contífico para el producto.', 'woo-contifico' );
                }

                return [
                        'message'             => $message,
                        'contifico_id'        => $contifico_id,
                        'contifico_sku'       => $contifico_sku,
                        'woocommerce_sku'     => (string) $resolved_product->get_sku(),
                        'woocommerce_product' => $resolved_product->get_id(),
                        'changes'             => $changes,
                        'result'              => $result,
                        'stock_quantity'      => $resolved_product->get_manage_stock() ? (int) $resolved_product->get_stock_quantity() : null,
                        'price'               => (float) $resolved_product->get_price(),
                ];
        }

        /**
         * Synchronize a single product identified by SKU.
         *
         * @since 4.2.0
         *
         * @param string $sku
         *
         * @return array
         * @throws Exception
         */
        private function sync_single_product_by_sku( string $sku ) : array {

                $sku = trim( $sku );

                if ( '' === $sku ) {
                        throw new Exception( __( 'Debes proporcionar un SKU para iniciar la sincronización.', 'woo-contifico' ) );
                }

                if ( $this->is_active() !== true ) {
                        throw new Exception( __( 'El conector no está activo.', 'woo-contifico' ) );
                }

                $environment = $this->prepare_single_product_sync_environment();

                $product_id = $this->find_wc_product_id_for_contifico_sku( $sku );

                if ( $product_id <= 0 ) {
                        throw new Exception( sprintf( __( 'No se encontró un producto con el SKU "%s" en WooCommerce.', 'woo-contifico' ), $sku ) );
                }

                $wc_product = wc_get_product( $product_id );

                if ( ! $wc_product ) {
                        throw new Exception( __( 'No se pudo cargar el producto de WooCommerce.', 'woo-contifico' ) );
                }

                $resolved_product = $this->resolve_wc_product_for_contifico_sku( $wc_product, $sku );

                if ( ! $resolved_product ) {
                        throw new Exception( __( 'No se pudo resolver la variación del producto para el SKU indicado.', 'woo-contifico' ) );
                }

                return $this->execute_single_product_sync( $resolved_product, $environment, $sku );
        }
        /**
         * Apply Contífico updates to a WooCommerce product entry.
         *
         * @since 4.2.0
         *
         * @param array $product_entry
         * @param array $result
         * @param array $debug_log_entries
         * @param array $product_stock_cache
         * @param array $warehouse_id_cache
         * @param array $location_stock
         * @param array $warehouses_map
         * @param array $location_map
         * @param string $default_warehouse_id
         *
         * @return array{stock_updated:bool,price_updated:bool,meta_updated:bool,outofstock:bool}
         */
        private function update_product_from_contifico_data(
                array $product_entry,
                array &$result,
                array &$debug_log_entries,
                array &$product_stock_cache,
                array &$warehouse_id_cache,
                array &$location_stock,
                array $warehouses_map,
                array $location_map,
                string $default_warehouse_id
        ) : array {

                $changes = [
                        'stock_updated' => false,
                        'price_updated' => false,
                        'meta_updated'  => false,
                        'outofstock'    => false,
                ];

                if ( ! isset( $product_entry['product'] ) || ! is_a( $product_entry['product'], 'WC_Product' ) ) {
                        return $changes;
                }

                $product           = $product_entry['product'];
                $product_cache_key = isset( $product_entry['id'] ) ? (string) $product_entry['id'] : '';

                if ( '' === $product_cache_key ) {
                        return $changes;
                }

                if ( ! array_key_exists( $product_cache_key, $product_stock_cache ) ) {
                        $product_stock_cache[ $product_cache_key ] = $this->contifico->get_product_stock_by_warehouses( $product_cache_key );
                }

                $stock_by_warehouse = (array) $product_stock_cache[ $product_cache_key ];

                if ( $product->get_manage_stock() ) {
                        $new_stock = 0;

                        if ( ! empty( $location_map ) ) {
                                $global_quantity = 0;

                                foreach ( $location_map as $location_id => $warehouse_code ) {
                                        $location_id    = (string) $location_id;
                                        $warehouse_code = (string) $warehouse_code;
                                        $quantity       = null;

                                        if ( isset( $location_stock[ $location_id ][ $product_cache_key ] ) ) {
                                                $quantity = (int) $location_stock[ $location_id ][ $product_cache_key ];
                                        }

                                        if ( null === $quantity ) {
                                                if ( ! array_key_exists( $warehouse_code, $warehouse_id_cache ) ) {
                                                        $warehouse_id_cache[ $warehouse_code ] = (string) ( $this->contifico->get_id_bodega( $warehouse_code ) ?? '' );
                                                }

                                                $warehouse_id = $warehouse_id_cache[ $warehouse_code ];

                                                if ( '' !== $warehouse_id && isset( $stock_by_warehouse[ $warehouse_id ] ) ) {
                                                        $quantity = (int) $stock_by_warehouse[ $warehouse_id ];
                                                }
                                        }

                                        if ( null === $quantity ) {
                                                $quantity = 0;
                                        }

                                        $location_stock[ $location_id ][ $product_cache_key ] = $quantity;
                                        $global_quantity                                      += $quantity;

                                        if ( method_exists( $this->woo_contifico->multilocation, 'update_location_stock' ) ) {
                                                $this->woo_contifico->multilocation->update_location_stock( $product, $location_id, $quantity );
                                        }
                                }

                                $new_stock = $global_quantity;
                        }
                        else {
                                if ( '' !== $default_warehouse_id && isset( $stock_by_warehouse[ $default_warehouse_id ] ) ) {
                                        $new_stock = (int) $stock_by_warehouse[ $default_warehouse_id ];
                                }
                        }

                        $old_stock = (int) $product->get_stock_quantity();

                        if ( $old_stock !== $new_stock ) {
                                if ( $new_stock < 1 ) {
                                        $product->set_stock_quantity( 0 );
                                        $product->set_stock_status( 'outofstock' );
                                        $result['outofstock'] ++;
                                        $changes['outofstock'] = true;
                                }
                                else {
                                        $product->set_stock_quantity( $new_stock );
                                        $product->set_stock_status( 'instock' );
                                }

                                $changes['stock_updated'] = true;
                        }
                }

                $updated_price = false;

                if ( $this->woo_contifico->settings['sync_price'] !== 'no' ) {
                        $price_key = $this->woo_contifico->settings['sync_price'];

                        if ( isset( $product_entry[ $price_key ] ) ) {
                                $new_price = (float) $product_entry[ $price_key ];
                                $old_price = (float) $product->get_price();

                                if ( $new_price !== $old_price ) {
                                        $product->set_regular_price( $new_price );
                                        $updated_price = true;
                                }
                        }
                }

                if ( $updated_price ) {
                        $changes['price_updated'] = true;
                }

                $current_meta_id = (string) $product->get_meta( self::PRODUCT_ID_META_KEY, true );

                if ( $current_meta_id !== $product_cache_key ) {
                        $product->update_meta_data( self::PRODUCT_ID_META_KEY, $product_cache_key );
                        $changes['meta_updated'] = true;
                }

                $needs_save = $changes['stock_updated'] || $changes['price_updated'] || $changes['meta_updated'];

                if ( $changes['stock_updated'] || $changes['price_updated'] ) {
                        $result['updated'] ++;
                }

                if ( $needs_save ) {
                        $product->save();
                }

                $sku = (string) $product->get_sku();
                $warehouse_stock_summary = [];

                foreach ( $stock_by_warehouse as $warehouse_id => $quantity ) {
                        $warehouse_code = isset( $warehouses_map[ $warehouse_id ] ) ? (string) $warehouses_map[ $warehouse_id ] : (string) $warehouse_id;
                        $warehouse_stock_summary[ $warehouse_code ] = (float) $quantity;
                }

                $debug_log_entries[ $product_cache_key ] = [
                        'id'    => $product_cache_key,
                        'sku'   => $sku,
                        'stock' => $warehouse_stock_summary,
                ];

                return $changes;
        }

        /**
         * Retrieve Contífico data for a WooCommerce product.
         *
         * @since 4.2.0
         *
         * @param WC_Product $product
         * @param string     $sku
         *
         * @return array|null
         */
        private function get_contifico_product_data_for_product( $product, string $sku ) {

                $contifico_id = '';

                if ( $product && is_a( $product, 'WC_Product' ) ) {
                        $contifico_id = (string) $product->get_meta( self::PRODUCT_ID_META_KEY, true );

                        if ( '' === $contifico_id && $product->is_type( 'variation' ) ) {
                                $parent_id = $product->get_parent_id();

                                if ( $parent_id ) {
                                        $parent_product = wc_get_product( $parent_id );

                                        if ( $parent_product ) {
                                                $contifico_id = (string) $parent_product->get_meta( self::PRODUCT_ID_META_KEY, true );
                                        }
                                }
                        }
                }

                if ( '' !== $contifico_id ) {
                        $product_data = $this->get_contifico_product_data_by_id( $contifico_id );

                        if ( ! empty( $product_data ) ) {
                                return $product_data;
                        }
                }

                return $this->get_contifico_product_data_by_sku( $sku );
        }

        /**
         * Locate a Contífico product by its identifier.
         *
         * @since 4.2.0
         *
         * @param string $contifico_id
         *
         * @return array|null
         */
        private function get_contifico_product_data_by_id( string $contifico_id ) {

                $contifico_id = trim( $contifico_id );

                if ( '' === $contifico_id ) {
                        return null;
                }

                $product = $this->contifico->get_product_by_id( $contifico_id );

                if ( ! empty( $product ) ) {
                        return $product;
                }

                $inventory = $this->contifico->get_products();

                if ( is_array( $inventory ) ) {
                        $product = $this->locate_product_in_inventory_by_id( $inventory, $contifico_id );

                        if ( $product ) {
                                return $product;
                        }
                }

                return null;
        }

        /**
         * Locate a Contífico product by SKU.
         *
         * @since 4.2.0
         *
         * @param string $sku
         *
         * @return array|null
         */
        private function get_contifico_product_data_by_sku( string $sku ) {

                $sku = trim( $sku );

                if ( '' === $sku ) {
                        return null;
                }

                $candidates = array_merge( [ $sku ], $this->generate_alternate_skus( $sku ) );

                foreach ( $candidates as $candidate ) {
                        $candidate = trim( (string) $candidate );

                        if ( '' === $candidate ) {
                                continue;
                        }

                        try {
                                $contifico_id = (string) $this->contifico->get_product_id( $candidate );
                        }
                        catch ( Exception $exception ) {
                                $contifico_id = '';
                        }

                        if ( '' === $contifico_id ) {
                                continue;
                        }

                        $product = $this->contifico->get_product_by_id( $contifico_id );

                        if ( ! empty( $product ) ) {
                                return $product;
                        }
                }

                $inventory = $this->contifico->get_products();

                if ( is_array( $inventory ) ) {
                        $product = $this->locate_product_in_inventory_by_sku( $inventory, $candidates );

                        if ( $product ) {
                                return $product;
                        }
                }

                return null;
        }

        /**
         * Helper to locate a product in a Contífico inventory dump using SKU candidates.
         *
         * @since 4.2.0
         *
         * @param array $inventory
         * @param array $candidates
         *
         * @return array|null
         */
        private function locate_product_in_inventory_by_sku( array $inventory, array $candidates ) {

                if ( empty( $inventory ) || empty( $candidates ) ) {
                        return null;
                }

                $normalized_candidates = array_map( static function ( $candidate ) {
                        return (string) $candidate;
                }, array_unique( array_filter( $candidates, 'strlen' ) ) );

                foreach ( $inventory as $product ) {
                        if ( ! is_array( $product ) ) {
                                continue;
                        }

                        $product_sku = isset( $product['sku'] ) ? (string) $product['sku'] : '';

                        if ( '' === $product_sku ) {
                                continue;
                        }

                        if ( in_array( $product_sku, $normalized_candidates, true ) ) {
                                return $product;
                        }
                }

                return null;
        }

        /**
         * Helper to locate a product in a Contífico inventory dump using the Contífico ID.
         *
         * @since 4.2.0
         *
         * @param array  $inventory
         * @param string $contifico_id
         *
         * @return array|null
         */
        private function locate_product_in_inventory_by_id( array $inventory, string $contifico_id ) {

                if ( '' === $contifico_id || empty( $inventory ) ) {
                        return null;
                }

                foreach ( $inventory as $product ) {
                        if ( ! is_array( $product ) ) {
                                continue;
                        }

                        if ( isset( $product['codigo'] ) && (string) $product['codigo'] === $contifico_id ) {
                                return $product;
                        }
                }

                return null;
        }

        /**
         * Reset synchronization debug log helpers.
         *
         * @since 4.1.6
         *
         * @return void
         */
        private function reset_sync_debug_log() : void {

                delete_transient( self::SYNC_DEBUG_TRANSIENT_KEY );

                if ( ! empty( $this->sync_debug_log_path ) && file_exists( $this->sync_debug_log_path ) ) {
                        wp_delete_file( $this->sync_debug_log_path );
                }

        }

        /**
         * Persist synchronization debug log entries into a text file.
         *
         * @since 4.1.6
         *
         * @param array $entries
         *
         * @return void
         */
        private function write_sync_debug_log( array $entries ) : void {

                if ( empty( $this->sync_debug_log_path ) ) {
                        return;
                }

                $timestamp = current_time( 'mysql' );
                $lines     = [
                        sprintf( 'Registro de sincronización generado el %s', $timestamp ),
                        'Producto ID, SKU, STOCK POR CADA BODEGA',
                ];

                if ( empty( $entries ) ) {
                        $lines[] = 'Sin coincidencias de productos entre Contífico y WooCommerce.';
                } else {
                        ksort( $entries );

                        foreach ( $entries as $entry ) {
                                if ( ! is_array( $entry ) ) {
                                        continue;
                                }

                                $product_id = isset( $entry['id'] ) ? (string) $entry['id'] : '';
                                $sku        = isset( $entry['sku'] ) ? (string) $entry['sku'] : '';
                                $stock_info = [];

                                if ( isset( $entry['stock'] ) && is_array( $entry['stock'] ) ) {
                                        foreach ( $entry['stock'] as $warehouse_code => $quantity ) {
                                                $warehouse_code = (string) $warehouse_code;
                                                $quantity       = wc_format_decimal( (float) $quantity, 2 );
                                                $stock_info[]   = sprintf( '%s:%s', $warehouse_code, $quantity );
                                        }
                                }

                                if ( empty( $stock_info ) ) {
                                        $stock_info[] = 'Sin datos de bodega';
                                }

                                $lines[] = sprintf(
                                        '%s, %s, %s',
                                        $product_id,
                                        $sku,
                                        implode( ' | ', $stock_info )
                                );
                        }
                }

                wp_mkdir_p( dirname( $this->sync_debug_log_path ) );
                file_put_contents( $this->sync_debug_log_path, implode( PHP_EOL, $lines ) . PHP_EOL );

        }

	/**
	 * Get product ids from an array of skus
	 *
	 * @since 2.0.0
	 *
	 * @param array $skus
	 * @return array
	 */
        private function get_products_ids_by_skus($skus) : array {

                $skus = array_values( array_unique( array_filter( (array) $skus, 'strlen' ) ) );

                if ( empty( $skus ) ) {
                        return [];
                }

                $query = new WP_Query ( [
                        'fields' => 'ids',
                        'post_type' => ['product','product_variation'],
                        'post_status' => 'any',
                        'posts_per_page' => -1,
                        'meta_query' => [
                                [
                                        'key' => '_sku',
                                        'value' => $skus,
                                        'compare' => 'IN',
                                ],
                        ]
                ] );
                return $query->posts;
        }

        /**
         * Generate alternate SKU representations for variation lookups.
         *
         * @since 4.1.7
         *
         * @param string $sku
         * @return array
         */
        private function generate_alternate_skus( string $sku ) : array {

                $sku = trim( $sku );

                if ( '' === $sku ) {
                        return [];
                }

                $alternates = [];

                if ( false !== strpos( $sku, '/' ) ) {
                        $alternates[] = str_replace( '/', '-', $sku );
                        $alternates[] = str_replace( '/', '_', $sku );
                        $alternates[] = str_replace( '/', '', $sku );

                        $base = strstr( $sku, '/', true );
                        $variant = substr( strrchr( $sku, '/' ), 1 );

                        if ( is_string( $base ) && '' !== $base && is_string( $variant ) && '' !== $variant ) {
                                foreach ( [ '-', '_', '' ] as $separator ) {
                                        $alternates[] = $base . $separator . $variant;
                                }
                        }
                }

                $lower = strtolower( $sku );
                $upper = strtoupper( $sku );

                if ( $lower !== $sku ) {
                        $alternates[] = $lower;
                }

                if ( $upper !== $sku ) {
                        $alternates[] = $upper;
                }

                return array_values( array_unique( array_filter( $alternates, 'strlen' ) ) );
        }

        /**
         * Attempt to locate a WooCommerce product ID for a Contífico SKU.
         *
         * @since 4.1.7
         *
         * @param string $sku
         * @return int
         */
        private function find_wc_product_id_for_contifico_sku( string $sku ) : int {

                $sku = trim( $sku );

                if ( '' === $sku || ! function_exists( 'wc_get_product_id_by_sku' ) ) {
                        return 0;
                }

                $candidates = array_merge( [ $sku ], $this->generate_alternate_skus( $sku ) );

                foreach ( $candidates as $candidate ) {
                        $candidate = trim( (string) $candidate );

                        if ( '' === $candidate ) {
                                continue;
                        }

                        $product_id = wc_get_product_id_by_sku( $candidate );

                        if ( $product_id ) {
                                return (int) $product_id;
                        }
                }

                if ( false !== strpos( $sku, '/' ) ) {
                        $base = strstr( $sku, '/', true );

                        if ( is_string( $base ) && '' !== $base ) {
                                $product_id = wc_get_product_id_by_sku( $base );

                                if ( $product_id ) {
                                        return (int) $product_id;
                                }
                        }
                }

                return 0;
        }

        /**
         * Resolve the WooCommerce product that should receive stock updates for a Contífico SKU.
         *
         * @since 4.1.7
         *
         * @param WC_Product $product
         * @param string     $contifico_sku
         * @return WC_Product|null
         */
        private function resolve_wc_product_for_contifico_sku( $product, string $contifico_sku ) {

                if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
                        return null;
                }

                if ( $product->is_type( 'variation' ) ) {
                        return $product;
                }

                if ( $product->is_type( 'variable' ) ) {
                        $variation = $this->locate_variation_for_contifico_sku( $product, $contifico_sku );

                        if ( $variation ) {
                                return $variation;
                        }
                }

                return $product;
        }

        /**
         * Locate a variation that matches a Contífico SKU pattern.
         *
         * @since 4.1.7
         *
         * @param WC_Product $parent_product
         * @param string     $contifico_sku
         * @return WC_Product|null
         */
        private function locate_variation_for_contifico_sku( $parent_product, string $contifico_sku ) {

                if ( ! $parent_product || ! is_a( $parent_product, 'WC_Product' ) || ! $parent_product->is_type( 'variable' ) ) {
                        return null;
                }

                if ( false === strpos( $contifico_sku, '/' ) ) {
                        return null;
                }

                $suffix = substr( strrchr( $contifico_sku, '/' ), 1 );

                if ( false === $suffix ) {
                        return null;
                }

                $suffix            = trim( (string) $suffix );
                $normalized_suffix = sanitize_title( $suffix );
                $lower_suffix      = strtolower( $suffix );

                $candidate_skus = array_merge( [ $contifico_sku ], $this->generate_alternate_skus( $contifico_sku ) );

                foreach ( (array) $parent_product->get_children() as $child_id ) {
                        $variation = wc_get_product( $child_id );

                        if ( ! $variation ) {
                                continue;
                        }

                        $variation_sku = (string) $variation->get_sku();

                        if ( '' !== $variation_sku && in_array( $variation_sku, $candidate_skus, true ) ) {
                                return $variation;
                        }

                        $attributes = (array) $variation->get_attributes();

                        foreach ( $attributes as $attribute_value ) {
                                $attribute_value = (string) $attribute_value;

                                if ( '' === $attribute_value ) {
                                        continue;
                                }

                                if (
                                        sanitize_title( $attribute_value ) === $normalized_suffix
                                        || strtolower( $attribute_value ) === $lower_suffix
                                        || $attribute_value === $suffix
                                ) {
                                        return $variation;
                                }
                        }
                }

                return null;
        }

	/**
	 * Transfer stock from the main warehouse to a temporal web warehouse
	 *
	 * @since 3.0.0
	 * @see woocommerce_reduce_order_stock
	 * @see woocommerce_order_refunded
	 * @noinspection PhpUnused
	 *
	 * @param WC_Order $order
	 */
	public function transfer_contifico_stock( $order ) {

		# Transfer stock to a provisional web warehouse if is configured
                $order_id       = $order->get_id();
                $stock_reduced  = wc_string_to_bool( $order->get_meta( '_woo_contifico_stock_reduced', true ) );
                if(
                        !$stock_reduced &&
                        isset($this->woo_contifico->settings['bodega_facturacion']) &&
                        !empty($this->woo_contifico->settings['bodega_facturacion'])
                ) {
			$id_origin_warehouse = $this->contifico->get_id_bodega( $this->woo_contifico->settings['bodega'] );
			$id_destination_warehouse = $this->contifico->get_id_bodega( $this->woo_contifico->settings['bodega_facturacion'] );
			$env                 = ( (int) $this->woo_contifico->settings['ambiente'] === WOO_CONTIFICO_TEST ) ? 'test' : 'prod';
			$transfer_stock      = [
				'tipo'              => 'TRA',
				'fecha'             => date( 'd/m/Y' ),
				'bodega_id'         => $id_origin_warehouse,
				'bodega_destino_id' => $id_destination_warehouse,
				'detalles'          => [],
				'descripcion'       => sprintf(
					__( 'Referencia: Tienda online Orden %d', $this->plugin_name ),
					$order_id
				),
				'codigo_interno'    => null,
				'pos'               => $this->woo_contifico->settings["{$env}_api_token"],
			];

			try {
				/** @var WC_Order_item_Product $item */
				foreach ( $order->get_items() as $item ) {
					$wc_product = $item->get_product();
					$sku        = $wc_product->get_sku();
					$product_id = $this->contifico->get_product_id( $sku );

					$transfer_stock['detalles'][] = [
						'producto_id' => $product_id,
						'cantidad'    => $item->get_quantity(),
					];
				}

				$result = $this->contifico->transfer_stock( json_encode( $transfer_stock ) );
                                $order->add_order_note( sprintf(
                                                __( '<b>Contífico: </b><br> Inventario trasladado a la bodega web %s', $this->plugin_name ),
                                                $result['codigo']
                                        )
                                );
                                $order->update_meta_data( '_woo_contifico_stock_reduced', wc_bool_to_string( true ) );
                                $order->save();
			}
			catch ( Exception $exception ) {
				$order->add_order_note( sprintf(
						__( '<b>Contífico retornó un error al transferir inventario a la bodega web</b><br>%s', $this->plugin_name ),
						$exception->getMessage()
					)
				);
			}
		}
	}

	/**
	 * Restore stock from the web warehouse to the main one
	 *
	 * @since 1.3.0
	 * @see woocommerce_restore_order_stock
	 * @see woocommerce_order_refunded
	 * @noinspection PhpUnused
	 *
	 * @param int|WC_Order $order_id
	 * @param int $refund_id
	 */
	public function restore_contifico_stock( $order_id, $refund_id = 0 ) {

		# Get order data
		if ( is_a( $order_id, 'WC_Order' ) ) {
			$order    = $order_id;
			$order_id = $order->get_id();
		} else {
			$order = wc_get_order( $order_id );
		}

		# Transfer stock from the provisional web warehouse if is configured
                if(
                        wc_string_to_bool( $order->get_meta( '_woo_contifico_stock_reduced', true ) ) &&
                        isset($this->woo_contifico->settings['bodega_facturacion'])
                ) {

			# Ger refund data
			$refund = null;
			if ( ! empty( $refund_id ) ) {
				$refund = new WC_Order_Refund( $refund_id );
			}

			$id_origin_warehouse      = $this->contifico->get_id_bodega( $this->woo_contifico->settings['bodega_facturacion'] );
			$id_destination_warehouse = $this->contifico->get_id_bodega( $this->woo_contifico->settings['bodega'] );
			$env                      = ( (int) $this->woo_contifico->settings['ambiente'] === WOO_CONTIFICO_TEST ) ? 'test' : 'prod';
			$restore_stock            = [
				'tipo'              => 'TRA',
				'fecha'             => date( 'd/m/Y' ),
				'bodega_id'         => $id_origin_warehouse,
				'bodega_destino_id' => $id_destination_warehouse,
				'detalles'          => [],
				'descripcion'       => sprintf(
					__( 'Referencia: Tienda online reembolso Orden %d', $this->plugin_name ),
					$order_id
				),
				'codigo_interno'    => null,
				'pos'               => $this->woo_contifico->settings["{$env}_api_token"],
			];

			# Get items to restore
			$items = empty( $refund_id ) ? $order->get_items() : $refund->get_items();
			if( empty($items) && !empty($refund) ) {
				$items = $order->get_items();
				$refund = null;
			}

			try {
				/** @var WC_Order_item_Product $item */
				foreach ( $items as $item ) {
					$wc_product = $item->get_product();
					$sku        = $wc_product->get_sku();
					$price      = $wc_product->get_price();
					$product_id = $this->contifico->get_product_id( $sku );

					if ( empty( $refund ) ) {
						$item_stock_reduced = $item->get_meta( '_reduced_stock', true );
						$item_quantity      = empty( $item_stock_reduced ) ? $item->get_quantity() : $item_stock_reduced;
					} else {
						$item_quantity = abs( $item->get_quantity() );
					}

					$restore_stock['detalles'][] = [
						'producto_id' => $product_id,
						'precio'      => $price,
						'cantidad'    => $item_quantity,
					];
				}

				if ( ! empty( $restore_stock['detalles'] ) ) {
					$result = $this->contifico->transfer_stock( json_encode( $restore_stock ) );
					$order->add_order_note( sprintf(
							__( '<b>Contífico: </b><br> Inventario restaurado a la bodega principal debido a %s: %s', $this->plugin_name ),
							empty( $refund ) ?
								empty($refund_id) ? 'cancelación de la orden' : "reembolso #{$refund_id}"
								: "reembolso total o parcial #{$refund_id}",
							$result['codigo']
						)
					);
				}
			}
			catch ( Exception $exception ) {
				$order->add_order_note( sprintf(
						__( '<b>Contífico retornó un error al restituir inventario a la bodega principal</b><br>%s', $this->plugin_name ),
						$exception->getMessage()
					)
				);
			}
		}

	}

	/**
	 * Validate integration data before saving
	 *
	 * @param array $input
	 *
	 * @return  array
	 *
	 * @since   1.3.0
	 * @see     register_setting()
	 * @noinspection PhpUnused
	 *
	 */
	public function validate_integration( array $input ) : array {

		$message = '';
		$env_error = '';
		$error = false;

		# Check that the correct API is set
		if( (int) $input['ambiente'] === WOO_CONTIFICO_TEST ) {
			$env_error .= !empty($input['test_api_key']) ? '' : __( '<br>&nbsp;&nbsp; + API Key de prueba requerido', $this->plugin_name );
			$env_error .= !empty($input['test_api_token']) ? '' : __( '<br>&nbsp;&nbsp;  + API Token de prueba requerido', $this->plugin_name );
		}
		else {
			$env_error .= !empty($input['prod_api_key']) ? '' : __( '<br>&nbsp;&nbsp; +  API Key de producción requerido', $this->plugin_name );
			$env_error .= !empty($input['prod_api_token']) ? '' : __( '<br>&nbsp;&nbsp; +  API Token de producción requerido', $this->plugin_name );
		}
		if(!empty($env_error)) {
			$message .= $env_error;
			$error = true;
		}

                $input['multiloca_manual_enable'] = isset( $input['multiloca_manual_enable'] );

                if ( isset( $input['multiloca_manual_locations'] ) ) {
                        $input['multiloca_manual_locations'] = sanitize_textarea_field( (string) $input['multiloca_manual_locations'] );
                } else {
                        $input['multiloca_manual_locations'] = '';
                }

                # Re Schedule cron
                try {
			# Check if cron recurrence changed
			if ( $this->woo_contifico->settings['actualizar_stock'] !== sanitize_text_field( $input['actualizar_stock'] ) ) {
				as_unschedule_all_actions( 'woo_contifico_sync_stock', [1], $this->plugin_name);
				$message .= __( '<br> - Frecuencia de actualización de stock modificada', $this->plugin_name );
			}
		}
		catch ( Exception $exception ) {
			$message .= __( '<br> - Error al modificar la frecuencia de actualización stock', $this->plugin_name );
			$error   = true;
		}

		if ( $error ) {
			$this->config_status['errors']['contifico'] = sprintf(
				__( 'Existen errores que deben ser corregidos: %s', $this->plugin_name ),
				$message
			);
		}
		else {
			unset($this->config_status['errors']['contifico']);
			add_settings_error(
				'woo_contifico_settings',
				'settings_updated',
				sprintf(
					__( 'Opciones almacenadas correctamente %s', $this->plugin_name ),
					$message
				),
				'updated'
			);
		}

		# Remove contifico notice if changed from test to production
                if( WOO_CONTIFICO_PRODUCTION == $input['ambiente'] ) {
                        $this->remove_notice('woo_contifico_environment');
                }

                if ( ! isset( $input['multiloca_locations'] ) && isset( $_POST['multiloca_locations'] ) ) {
                        $input['multiloca_locations'] = (array) wp_unslash( $_POST['multiloca_locations'] );
                }

                if ( isset( $input['multiloca_locations'] ) && is_array( $input['multiloca_locations'] ) ) {
                        $valid_locations = [];

                        foreach ( $this->woo_contifico->settings_fields['woo_contifico_integration']['fields'] as $field ) {
                                if ( isset( $field['multiloca_location_id'] ) ) {
                                        $valid_locations[] = (string) $field['multiloca_location_id'];
                                }
                        }

                        $sanitized_locations = [];

                        foreach ( $input['multiloca_locations'] as $location_id => $warehouse_code ) {
                                $location_key = (string) $location_id;

                                if ( ! empty( $valid_locations ) && ! in_array( $location_key, $valid_locations, true ) ) {
                                        continue;
                                }

                                $code = sanitize_text_field( (string) $warehouse_code );

                                if ( '' !== $code ) {
                                        $sanitized_locations[ $location_key ] = $code;
                                }
                        }

                        $input['multiloca_locations'] = $sanitized_locations;
                }

                $this->update_config_status();
                return $input;

        }

        /**
         * Validate sender data before saving
	 *
	 * @param array $input
	 *
	 * @return  array $input
	 * @since   1.3.0
	 * @see     register_setting()
	 * @noinspection PhpUnused
	 *
	 */
	public function validate_sender( $input ) : array {

		# Validate RUC
		try {
			$this->woo_contifico->validate_tax_id( 'ruc', sanitize_text_field( $input['emisor_ruc'] ) );
			unset($this->config_status['errors']['emisor']);
			add_settings_error(
				'woo_contifico_settings',
				'settings_updated',
				__('Opciones almacenadas correctamente', $this->plugin_name),
				'updated'
			);
		}
		catch (Exception $exception) {
			$this->config_status['errors']['emisor'] = $exception->getMessage();
		}

		$this->update_config_status();
		return $input;

	}

	/**
	 * Validate pos data before saving
	 *
	 * @param array $input
	 *
	 * @return  array $input
	 * @since   2.0.3
	 * @see     register_setting()
	 * @noinspection PhpUnused
	 *
	 */
	public function validate_pos( $input ) : array {

		# Check that the correct API is set
		$message = '';
		if( in_array( $this->woo_contifico->settings['tipo_documento'], ['FAC', 'PRE'] ) ) {
			if ( (int) $this->woo_contifico->settings['ambiente'] === WOO_CONTIFICO_TEST ) {
				$message .= ! empty( $input['test_establecimiento_punto'] ) ? '' : __( '<br> - Código del establecimiento de prueba requerido', $this->plugin_name );
				$message .= ! empty( $input['test_establecimiento_codigo'] ) ? '' : __( '<br> - Código del punto de emisión de prueba requerido', $this->plugin_name );
				$message .= ! empty( $input['test_secuencial_factura'] ) ? '' : __( '<br> - Número secuencial de la factura de prueba requerido', $this->plugin_name );
			} else {
				$message .= ! empty( $input['prod_establecimiento_punto'] ) ? '' : __( '<br> - Código del establecimiento de prueba requerido', $this->plugin_name );
				$message .= ! empty( $input['prod_establecimiento_codigo'] ) ? '' : __( '<br> - Código del punto de emisión de prueba requerido', $this->plugin_name );
				$message .= ! empty( $input['prod_secuencial_factura'] ) ? '' : __( '<br> - Número secuencial de la factura de prueba requerido', $this->plugin_name );
			}
		}

		if ( empty($message) ) {
			unset($this->config_status['errors']['establecimiento']);
			add_settings_error(
				'woo_contifico_settings',
				'settings_updated',
				__( 'Opciones almacenadas correctamente', $this->plugin_name ),
				'updated'
			);
		}
		else {
			$this->config_status['errors']['establecimiento'] = sprintf(
				__( 'Existen errores que deben ser corregidos: %s', $this->plugin_name ),
				$message
			);
		}

		$this->update_config_status();
		return $input;

	}

	/**
	 * Show saved settings notice
	 *
	 * @since   1.3.0
	 * @see     register_setting()
	 * @noinspection PhpUnused
	 *
	 * @param array $input
	 * @return  array
	 *
	 */
	public function save_settings( $input ) : array {
		add_settings_error(
			'woo_contifico_settings',
			'settings_updated',
			__( 'Opciones almacenadas correctamente', $this->plugin_name ),
			'updated'
		);

		# Remove contifico log notice
		$this->remove_notice('woo_contifico_log');

		# Remove log file is "Activar registro" is not set
		if( !isset($input['activar_registro']) && file_exists($this->log_path) ) {
			unlink($this->log_path);
			add_settings_error(
				'woo_contifico_settings',
				'settings_updated',
				__('El registro de transacciones ha sido eliminado', $this->plugin_name),
				'updated'
			);
		}

		unset($this->config_status['errors']['woocommerce']);
		$this->update_config_status();
		return $input;
	}

	/**
	 * Call invoice webservice.
	 *
	 * @param int $order_id Order id
	 *
	 * @see     woocommerce_order_status_processing
	 * @see     woocommerce_order_status_completed
	 *
	 * @since    1.4.0
	 */
	public function contifico_call( $order_id ) {

		# Get order
		$order = wc_get_order( $order_id );

		# Check if the plugin is not configured correctly
		if( $this->config_status['status'] === false ) {
			$order->add_order_note(__('Woo-Contifico no está correctamente configurado. Por favor corrija los errores para poder emitir este documento', $this->plugin_name));
			return;
		}

		# Check if order is only for people of Ecuador, if not, check if _billing_tax_type is "exterior"
		$country  = strtolower( $order->get_billing_country() );
		$tax_type = $order->get_meta( '_billing_tax_type' );
		if ( $country != 'ec' && $tax_type != 'exterior' ) {
			$order->add_order_note( __( 'Para documentos fuera del Ecuador, por favor seleccione tipo de identificación: "exterior"', $this->plugin_name ) );
			return;
		}

		# If the _billing_tax_type is "exterior" and country is not Ecuador, the IVA does not apply
		$extranjero = false;
		if ( $country !== 'ec' && $tax_type === 'exterior' ) {
			$extranjero = true;
		}

		# Check if order has a value greater than 0
		if ( $order->get_total() == 0 ) {
			$order->add_order_note( __( 'Solamente se emite documento electrónico para pedidos con valor mayor a 0 dólares', $this->plugin_name ) );
			return;
		}

		# Check if invoice was already generated
		$id_factura = $order->get_meta( '_id_factura' );
		if ( ! empty( $id_factura ) ) {
			$order->add_order_note( __( 'Ya se emitió un documento para esta orden. Para emitir un nuevo documento es necesario crear una nueva orden.', $this->plugin_name ) );
			return;
		}

		# Check if generate invoice to company or not
		$tax_subject     = $order->get_meta( '_billing_tax_subject' );
		$billing_company = $order->get_billing_company();
		if ( ! empty( $tax_subject ) && empty( $billing_company ) ) {
			$order->add_order_note( __( 'Se eligió la opción de emitir el documento a nombre de la empresa, pero no está registrado el nombre de la empresa. Por favor corregir el error y volver a emitir el documento', $this->plugin_name ) );
			return;
		}

		# Define Razón Social
		$razon_social = empty( $tax_subject ) ? $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() : $billing_company;

		# Validate tax id
		$tax_id = $order->get_meta( '_billing_tax_id' );
		if ( empty( $tax_type ) ) {
			$tax_id = '9999999999999';
		}
		else {
			try {
				$this->woo_contifico->validate_tax_id( $tax_type, $tax_id );
			}
			catch (Exception $exception) {
				set_transient("woo_contifico_invoice_{$order_id}_ok", FALSE);
				return;
			}
		}

		$fecha      = date( 'd/m/Y' );
		$env = ( (int) $this->woo_contifico->settings['ambiente'] === WOO_CONTIFICO_TEST ) ? 'test' : 'prod';

		# Items
		$items              = [];
		$order_items        = $order->get_items();
		$porcentaje_iva = null;
		$iva_global = 0;
		/** @var WC_Order_item_Product $item */
		foreach ( $order_items as $item ) {
			$product_wc = $item->get_product();
			$sku        = $product_wc->get_sku();
			$cantidad   = (float) $item['quantity'];
			$precio     = (float) $item['total'];
			$item_tax   = $item->get_total_tax();

			try {
				$product_id = $this->contifico->get_product_id( $sku );
			}
			catch (Exception $exception) {
				$exception_error =  sprintf(
					__( 'Ha ocurrido un error al emitir el documento. El error se generó al intentar obtener el código del producto <b>%s</b> con SKU <b>%s</b><br><br>', $this->plugin_name ),
					$product_wc->get_name(),
					$sku
				);

				if($exception->getCode() === 404) {
					$exception_error .= __('No se encontró un producto con el SKU proporcionado');
				}
				else {
					$exception_error .= sprintf(
						__('El servidor retornó el siguiente error: %s'),
						$exception->getMessage()
					);
				}
				$order->add_order_note($exception_error);
				return;
			}

			# Get iva percentage if not set yet
			if( is_null($porcentaje_iva) ) {
				$item_taxes     = $item->get_taxes();
				$porcentaje_iva = $this->get_iva_rate( $item_taxes['total'] );
			}

			if ( $extranjero ) {
				$base_no_gravable = $precio;
				$base_cero        = 0;
				$base_gravable    = 0;
			} else {
				$base_no_gravable = 0;
				if ( $item_tax > 0 ) {
					$base_cero     = 0;
					$base_gravable = $precio;
				} else {
					$base_cero     = $precio;
					$base_gravable = 0;
				}
			}

			# Set item tax
			$item_iva = (float) ($base_gravable * $porcentaje_iva / 100);
			$iva_global += $item_iva;

			$porcentaje_descuento = ( $item['subtotal'] > 0 ) ? ((float) $item['subtotal'] - $precio) / (float) $item['subtotal'] * 100 : 100;

			$items[] = [
				'producto_id'          => $product_id,
				'cantidad'             => round( $cantidad, 2 ),
				'precio'               => round( (float) $item['subtotal']/ $cantidad , 2 ),
				'porcentaje_iva'       => ( $item_tax > 0 ) ? $porcentaje_iva : 0,
				'porcentaje_descuento' => round( $porcentaje_descuento, 2 ),
				'base_cero'            => round( $base_cero, 2 ),
				'base_gravable'        => round( $base_gravable, 2 ),
				'base_no_gravable'     => round( $base_no_gravable, 2 ),
			];
		}

		#  Shipping status
		$shipping_total = (float) $order->get_shipping_total();
		if ( $shipping_total > 0 ) {
			try {
				$shipping_id = $this->contifico->get_product_id( $this->woo_contifico->settings['shipping_code'] );
			}
			catch (Exception $exception) {
				$exception_error = __( 'No se encontró en Contífico un producto para el envío, por favor revisar que el código del producto de envío configurado sea el correcto.', $this->plugin_name );

				if($exception->getCode() === 404) {
					$exception_error .= __('No se encontró un producto con el SKU proporcionado');
				}
				else {
					$exception_error .= sprintf(
						__('El servidor retornó el siguiente error: %s'),
						$exception->getMessage()
					);
				}
				$order->add_order_note($exception_error);
				return;
			}

			$shipping_tax    = (float) $order->get_shipping_tax();
			if ( $extranjero ) {
				$base_no_gravable = $shipping_total;
				$base_cero        = 0;
				$base_gravable    = 0;
			}
			else {
				$base_no_gravable = 0;
				if ( $shipping_tax > 0 ) {
					$base_cero     = 0;
					$base_gravable = $shipping_total;
					$shipping_tax = (float) ($base_gravable * $porcentaje_iva / 100);
				}
				else {
					$base_cero     = $shipping_total;
					$base_gravable = 0;
				}
			}
			$iva_global     += $shipping_tax;

			$items[] = [
				'producto_id'          => $shipping_id,
				'cantidad'             => round( 1, 2 ),
				'precio'               => round( $shipping_total, 2 ),
				'porcentaje_iva'       => ($shipping_tax > 0) ? $porcentaje_iva : 0,
				'porcentaje_descuento' => round( 0, 2 ),
				'base_cero'            => round( $base_cero, 2 ),
				'base_gravable'        => round( $base_gravable, 2 ),
				'base_no_gravable'     => round( $base_no_gravable, 2 ),
			];
		}

		# Calculate subtotals
		$subtotal_impuestos = array_sum(array_column($items, 'base_gravable'));
		$subtotal_0 = array_sum(array_column($items, 'base_cero'));

		# Check that all taxes are equal
		$iva_global = round($iva_global,2);
		$iva_gravable = round( (float) ($subtotal_impuestos * $porcentaje_iva / 100), 2);
		$order_tax = $order->get_total_tax();
		if ( !( (float) $iva_gravable === (float) $iva_global && (float) $iva_global === (float) $order_tax ) ) {
			$order->add_order_note(
				sprintf(
					__( 'Hay una diferencia en el cálculo del impuesto debido un error de redondeo:<br>
						- IVA sumado por cada item: <b>%s</b><br>
						- IVA calculado del subtotal gravable (%s): <b>%s</b><br>
						- IVA calculado por WooCommerce: <b>%s</b><br><br>
						Se enviará el IVA sumado por cada Item, pero eso generará un documento con un valor diferente a lo cobrado al cliente. 
						', $this->plugin_name ),
					$iva_global,
					$subtotal_impuestos,
					$iva_gravable,
					$order_tax
				)
			);
		}

		$total_con_impuestos = round($subtotal_impuestos,2) + round($subtotal_0,2) + $iva_global;

		# Generate document number
		if ( 'FAC' === $this->woo_contifico->settings['tipo_documento'] ) {
			$secuencial = str_pad( $this->get_secuencial(), 9, '0', STR_PAD_LEFT );
			$documento = $this->woo_contifico->settings["{$env}_establecimiento_punto"] . '-' . $this->woo_contifico->settings["{$env}_establecimiento_codigo"] . '-' . $secuencial;
		}
		else {
			$documento = date( 'Ymd' ) . $order_id;
		}

		$data = [
			'pos'            => $this->woo_contifico->settings["{$env}_api_token"],
			'fecha_emision'  => $fecha,
			'tipo_documento' => $this->woo_contifico->settings['tipo_documento'],
			'documento'      => $documento,
			'estado'         => 'P',
			'caja_id'        => null,
			'cliente'        => [
				$tax_type       => $tax_id,
				'razon_social'  => $razon_social,
				'telefonos'     => $order->get_billing_phone(),
				'direccion'     => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
				'tipo'          => $order->get_meta( '_billing_taxpayer_type' ),
				'email'         => $order->get_billing_email(),
				'es_extranjero' => $extranjero,
			],
			'vendedor'       => [
				'ruc'           => $this->woo_contifico->settings['emisor_ruc'],
				'razon_social'  => $this->woo_contifico->settings['emisor_razon_social'],
				'telefonos'     => $this->woo_contifico->settings['emisor_telefonos'],
				'direccion'     => $this->woo_contifico->settings['emisor_direccion'],
				'tipo'          => $this->woo_contifico->settings['tipo_contribuyente'],
				'email'         => $this->woo_contifico->settings['emisor_email'],
				'es_extranjero' => $extranjero,
			],
			'descripcion'    => sprintf( __( 'Referencia: Orden %d', $this->plugin_name ), $order_id),
			'subtotal_0'     => round( $subtotal_0, 2 ),
			'subtotal_12'    => round( $subtotal_impuestos, 2 ),
			'iva'            => round( $iva_global, 2 ),
			'servicio'       => round( 0, 2 ),
			'total'          => round( $total_con_impuestos, 2 ),
			'adicional1'     => '',
			'adicional2'     => '',
			'detalles'       => $items,
		];

		if( 'FAC' === $this->woo_contifico->settings['tipo_documento'] ) {
			# Set payment method
			$payment_codes = [
				'cod' => [
					'forma_cobro' => 'EF',
				],
				/*'bacs' => [
					'forma_cobro' => 'TRA',
				],*/
				'cheque' => [
					'forma_cobro' => 'CH',
					'numero_cheque' => 'S/N',
				],
				'other' => [
					'forma_cobro' => 'TC',
					'tipo_ping' => 'D',
				],
			];
			$payment_method = $order->get_payment_method();
			$medio_pago = ( in_array($payment_method, array_keys($payment_codes)) )
				? $payment_codes[$payment_method]
				: $payment_codes['other'];
			$payment_data = array_merge(
				$medio_pago,
				[
					'monto' => round($total_con_impuestos,2),
					'fecha' => $fecha
				]
			);
			$data_factura = [
				'electronico'    => true,
				'autorizacion'   => '',
				'lote'           => date('Ymd'),
				'cobros'         => [$payment_data],
			];
			$data = array_merge($data, $data_factura);
		}

		$json_data = json_encode( $data );

		try {
			$documento_electronico = $this->contifico->call( 'documento/', $json_data, 'POST' );
			if ( isset( $documento_electronico['code'] ) ) {
				$json_error = $documento_electronico['response']['mensaje'];
				if ( 'FAC' === $this->woo_contifico->settings['tipo_documento'] ) {
					$this->secuencial_rollback();
				}
				$order->add_order_note(sprintf(
					__( 'Contífico retornó errores en la petición. La respuesta del servidor es: %s', $this->plugin_name ),
					$json_error
				));
			}
			else {
                                $order->update_meta_data( '_id_factura', $documento_electronico['id'] );
                                $order->update_meta_data( '_numero_factura', $documento_electronico['documento'] );
                                $order->save();
				$order_note = __( 'El documento fue generado correctamente.<br><br>', $this->plugin_name );
				switch( $this->woo_contifico->settings['tipo_documento'] ) {
					case 'FAC':
						$order_note .= sprintf(
							__( 'El número de la factura es: %s', $this->plugin_name ),
							$documento_electronico['documento']
						);
						break;
					case 'PRE':
						$order_note .= sprintf(
							__( 'El número de la pre factura es: %s', $this->plugin_name ),
							$documento_electronico['documento']
						);
				}
				$order->add_order_note( $order_note, 1 );
			}
		}
		catch (Exception $exception) {
			$order->add_order_note( sprintf(
					__('<b>Contífico retornó un error</b><br>%s', $this->plugin_name),
					$exception->getMessage()
				)
			);
		}

	}

	/**
	 *  Add fields to admin area.
	 *
	 * @since   1.4.0
	 * @see     show_user_profile
	 * @see     edit_user_profile
	 *
	 * @noinspection PhpUnusedLocalVariableInspection
	 */
	public function print_user_admin_fields() {
		global $user_id;
		$fields      = $this->woo_contifico->get_account_fields( false, $user_id );
		$plugin_name = $this->plugin_name;
		require_once plugin_dir_path( __FILE__ ) . 'partials/woo-contifico-admin-user-fields.php';
	}

	/**
	 *  Display field value on the order edit page
	 *
	 * @param $order
	 *
	 * @see     woocommerce_admin_order_data_after_billing_address
	 * @since   1.4.0
	 */
	public function display_admin_order_meta( $order ) {

		# Order info
                $order_id      = $order->get_id();
                $tax_subject   = $order->get_meta( '_billing_tax_subject', true );
                $tax_type      = $order->get_meta( '_billing_tax_type', true );
                $tax_id        = $order->get_meta( '_billing_tax_id', true );
                $taxpayer_type = $order->get_meta( '_billing_taxpayer_type', true );

		# Order labels
		/* @noinspection PhpUnusedLocalVariableInspection */
		$invoice_name = empty( $tax_subject ) ? '' : __( 'Emitir el documento a nombre de la empresa', $this->plugin_name );
		/* @noinspection PhpUnusedLocalVariableInspection */
		$tax_payer    = ( $taxpayer_type === 'N' ) ? __( 'Persona Natural', $this->plugin_name ) : __( 'Persona Jurídica', $this->plugin_name );
		/* @noinspection PhpUnusedLocalVariableInspection */
		$tax_label    = empty( $tax_type ) ? '' : ucfirst( $tax_type ) . ':';

		# Get fields
		$fields                           = $this->woo_contifico->get_account_fields();
		$fields['tax_type']['value']      = $tax_type;
		$fields['tax_id']['value']        = $tax_id;
		$fields['taxpayer_type']['value'] = $taxpayer_type;

		require_once plugin_dir_path( __FILE__ ) . 'partials/woo-contifico-admin-edit-fields.php';
	}

	/**
	 *  Update order meta with Tax Id and Type
	 *
	 * @param $order_id
	 *
	 * @see     woocommerce_process_shop_order_meta
	 * @since   1.4.0
	 */
	public function save_order_meta_fields( $order_id ) {

		$tax_subject   = isset( $_POST['tax_subject'] ) ? sanitize_text_field( $_POST['tax_subject'] ) : '';
		$tax_type      = sanitize_text_field( $_POST['tax_type'] );
		$tax_id        = sanitize_text_field( $_POST['tax_id'] );
		$taxpayer_type = sanitize_text_field( $_POST['taxpayer_type'] );

                $order = wc_get_order( $order_id );
                if ( ! $order ) {
                        return;
                }

                $order->update_meta_data( '_billing_tax_subject', $tax_subject );
                $order->update_meta_data( '_billing_tax_type', $tax_type );
                $order->update_meta_data( '_billing_tax_id', $tax_id );
                $order->update_meta_data( '_billing_taxpayer_type', $taxpayer_type );
                $order->save();

		#  updating user meta (for customer my account edit details page post data)
		$user_id = sanitize_text_field( $_POST['customer_user'] );
		if ( ! empty( $user_id ) ) {
			update_user_meta( $user_id, 'tax_subject', $tax_subject );
			update_user_meta( $user_id, 'tax_type', $tax_type );
			update_user_meta( $user_id, 'tax_id', $tax_id );
			update_user_meta( $user_id, 'taxpayer_type', $taxpayer_type );
		}
	}

	/**
	 *  Update user meta with Tax Id and Type (in checkout and my account edit details pages)
	 *
	 * @param $customer_id
	 *
	 * @see     personal_options_update
	 * @see     edit_user_profile_update
	 * @see     woocommerce_save_account_details
	 * @see     woocommerce_created_customer
	 * @since   1.4.0
	 */
	public function save_account_fields( $customer_id ) {

		$fields = $this->woo_contifico->get_account_fields();
		foreach ( $fields as $key => $field ) {
			$value = isset( $_POST[ $key ] ) ? sanitize_text_field( $_POST[ $key ] ) : '';
			update_user_meta( $customer_id, $key, $value );
		}
	}

	/**
	 * Validate user account data.
	 *
	 * @param $errors
	 *
	 * @see     user_profile_update_errors
	 * @since   1.4.0
	 */
	public function check_fields( $errors ) {

		#  Check if Tax Id is set
		if ( $_POST['tax_id'] ) {

			$type = sanitize_text_field( $_POST['tax_type'] );
			$data = sanitize_text_field( $_POST['tax_id'] );

			try {
				$this->woo_contifico->validate_tax_id($type, $data);
			}
			catch (Exception $exception) {
				$errors->add('error', $exception->getMessage());
			}
		}
	}

	/**
	 * Validate order data
	 *
	 * @since   1.4.0
	 * @see     save_post_shop_order
	 * @noinspection PhpUnused
	 *
	 */
	public function order_check_fields() {

		$error_message = [];

		$tax_type        = empty( $_POST['tax_type'] ) ? '' : sanitize_text_field( $_POST['tax_type'] );
		$tax_id          = empty( $_POST['tax_id'] ) ? '' : sanitize_text_field( $_POST['tax_id'] );
		$billing_company = empty( $_POST['billing_company'] ) ? '' : sanitize_text_field( $_POST['billing_company'] );
		$billing_country = sanitize_text_field( $_POST['billing_country'] ?? '' );
		$type            = sanitize_text_field( $_POST['tax_type'] ?? '' );
		$tax_subject     = empty( $_POST['tax_subject'] ) ? '' : sanitize_text_field( $_POST['tax_subject'] );

		#  Validate if company is required and set
		if ( ! empty( $tax_subject ) && empty( $billing_company ) ) {
			$error_message[] = __( '<strong>Nombre de la empresa</strong> es un campo requerido cuando solicita emitir el documento a nombre de la empresa' );
		}

		# A client can't use passport if the billing country  EC
		if ( strtolower( $billing_country ) === 'ec' && $type === 'pasaporte' ) {
			wc_add_notice( __( '<b>Tipo de identificación</b> no puede ser pasaporte  si la factura se emite para Ecuador' ), 'error' );
		}

		# Validate tax info
		if (!empty($tax_id) ) {
			try {
				$this->woo_contifico->validate_tax_id( $tax_type, $tax_id );
			}
			catch (Exception $exception) {
				$error_message[] = $exception->getMessage();
			}
		}

		if ( ! empty( $error_message ) ) {
			add_settings_error(
				'woo_contifico_validation_error',
				'validation_error',
				(string) $error_message,
				'error'
			);
		}
	}

	/**
	 * Add generate invoice to order actions box
	 *
	 * @param array $actions Current actions in box
	 *
	 * @return    array
	 * @since   1.4.0
	 * @see     woocommerce_order_actions
	 * @noinspection PhpUnused
	 *
	 */
	public function add_generate_invoice_box_action( $actions ) : array {
		return array_merge( [ 'contifico_generate_invoice' => __( 'Generar documento electrónico', $this->plugin_name ) ], $actions );
	}

	/**
	 * Call invoice function
	 *
	 * @param   $order
	 *
	 * @since   1.4.0
	 * @see     woocommerce_order_action_contifico_generate_invoice
	 * @noinspection PhpUnused
	 */
	public function generate_invoice_action( $order ) {
		$this->contifico_call( $order->get_id() );
	}

	/**
	 * Get next invoice number and add 1
	 *
	 * @return  int  invoice number
	 * @since   1.4.0
	 */
	private function get_secuencial(): ?int {
		$pos = get_option( 'woo_contifico_pos_settings' );
		$env = ( (int) $this->woo_contifico->settings['ambiente'] === WOO_CONTIFICO_TEST ) ? 'test' : 'prod';
		$pos["{$env}_secuencial_factura"] = (int) $pos["{$env}_secuencial_factura"] + 1;

		return update_option( 'woo_contifico_pos_settings', $pos ) ? $pos["{$env}_secuencial_factura"] : null;
	}

	/**
	 * An error has occurred, revert to last number
	 *
	 * @since 1.4.0
	 * @see contifico_call
	 */
	private function secuencial_rollback() {
		$pos                       = get_option( 'woo_contifico_pos_settings' );
		$env = ( (int) $this->woo_contifico->settings['ambiente'] === WOO_CONTIFICO_TEST ) ? 'test' : 'prod';
		$pos["{$env}_secuencial_factura"] = (int) $pos["{$env}_secuencial_factura"] - 1;
		update_option( 'woo_contifico_pos_settings', $pos );
	}

	/**
	 * Update the plugin config status
	 *
	 * @since 2.1.0
	 */
	private function update_config_status() {
		$this->config_status['status'] = empty($this->config_status['errors']);
		update_option('woo_contifico_config_status', $this->config_status);
	}

	/**
	 * Remove notice code
	 *
	 * @since 2.1.0
	 *
	 * @param $notice_code
	 */
	private function remove_notice($notice_code) {

		global $wp_settings_errors;
		$remove_key = [];

		foreach ($wp_settings_errors as $key => $val) {
			if ($val['code'] == $notice_code ) {
				$remove_key[] = $key;
			}
		}

		foreach ($remove_key as $key) {
			unset( $wp_settings_errors[ $key ] );
		}

	}

	/**
	 * Get the IVA rate from the taxes send
	 * @param array $taxes
	 * @return float
	 */
	private function get_iva_rate($taxes) {
		$rates = WC_Tax::find_rates([ 'country' => 'ec' ]);
		$tax_array = array_intersect_key($rates, $taxes);
		$iva_rate = 0;
		foreach ($tax_array as $data) {
			/** @noinspection RegExpDuplicateCharacterInClass */
			$pattern = '/[i?v?a?]+/i';
			if( preg_match($pattern, $data['label']) !== false ) {
				$iva_rate = $data['rate'];
				break;
			}
		}
		return $iva_rate;
	}

}
