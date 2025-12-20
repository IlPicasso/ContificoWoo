<?php

use Pahp\SDK\Contifico;

if ( ! class_exists( 'Woo_Contifico_Sku_Mismatch_Exception', false ) ) {
        class Woo_Contifico_Sku_Mismatch_Exception extends Exception {

                /**
                 * @var array
                 */
                private $error_data;

                public function __construct( string $message, array $error_data = [], int $code = 0, ?Throwable $previous = null ) {
                        parent::__construct( $message, $code, $previous );

                        $this->error_data = $error_data;
                }

                public function get_error_data() : array {
                        return $this->error_data;
                }
        }
}

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

	private const SYNC_DEBUG_TRANSIENT_KEY    = 'woo_contifico_sync_debug_entries';
	private const SYNC_RESULT_TRANSIENT_KEY   = 'woo_sync_result';
	private const MANUAL_SYNC_STATE_OPTION    = 'woo_contifico_manual_sync_state';
private const MANUAL_SYNC_HISTORY_OPTION  = 'woo_contifico_manual_sync_history';
private const INVENTORY_MOVEMENTS_STORAGE = 'woo_contifico_inventory_movements';
private const INVENTORY_MOVEMENTS_TRANSIENT = 'woo_contifico_inventory_movements';
    private const INVENTORY_MOVEMENTS_MAX_ENTRIES = 2000;
private const INVENTORY_MOVEMENTS_HISTORY_RUNS = 'woo_contifico_inventory_movements_history_runs';
private const INVENTORY_ALERTS_REVIEWED_AT = 'woo_contifico_inventory_alerts_reviewed_at';
private const MAX_INVOICE_SEQUENTIAL_RETRIES = 5;
private const MANUAL_SYNC_CANCEL_TRANSIENT = 'woo_contifico_manual_sync_cancel';
private const MANUAL_SYNC_KEEPALIVE_HOOK    = 'woo_contifico_manual_sync_keepalive';
private const PRODUCT_ID_META_KEY         = '_woo_contifico_product_id';
private const ORDER_ITEM_WAREHOUSE_META_KEY = '_woo_contifico_source_warehouse';
private const ORDER_ITEM_ALLOCATION_META_KEY = '_woo_contifico_source_allocations';

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
         * Cache of preferred warehouse codes configured by the admin.
         *
         * @var array|null
         */
        private $preferred_warehouse_codes = null;

        /**
         * Cache of product stock grouped by Contífico warehouse ID when evaluating preferred warehouses.
         *
         * @var array
         */
        private $preferred_warehouse_stock_cache = [];

        /**
         * Tracks how much stock from each warehouse has been allocated per order when resolving preferences.
         *
         * @var array
         */
        private $preferred_warehouse_allocations = [];

        /**
         * Tracks the preferred warehouse allocations per order item when multiple warehouses are needed.
         *
         * @var array
         */
        private $preferred_item_allocations = [];

        /**
         * Cache that indicates whether an order is using store pickup.
         *
         * @var array
         */
        private $order_pickup_cache = [];

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
                $current_post_type = $this->resolve_admin_post_type();
                $is_product_editor = $this->is_product_editor_screen();

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
                $current_post_type = $this->resolve_admin_post_type();
                $is_product_editor = $this->is_product_editor_screen();
                $should_enqueue_js = false;

                if ( $hook_suffix === "woocommerce_page_{$this->plugin_name}" || 'shop_order' === $current_post_type || $is_product_editor ) {
                        $should_enqueue_js = true;
                }

                if ( ! $should_enqueue_js ) {
                        return;
                }

                wp_enqueue_script( $this->plugin_name, WOO_CONTIFICO_URL . 'admin/js/woo-contifico-admin.js', [ 'jquery' ], $this->version, false );

                if ( $is_product_editor ) {
                        wp_enqueue_script(
                                "{$this->plugin_name}-product-sync",
                                WOO_CONTIFICO_URL . 'admin/js/woo-contifico-product-sync.js',
                                [ 'jquery', $this->plugin_name ],
                                $this->version,
                                true
                        );
                }

                if ( $hook_suffix === "woocommerce_page_{$this->plugin_name}" ) {
                        wp_enqueue_script( "{$this->plugin_name}-diagnostics", WOO_CONTIFICO_URL . 'admin/js/woo-contifico-diagnostics.js', [ 'jquery' ], $this->version, true );
                }

                $params = [
                        'plugin_name' => $this->plugin_name,
                        'woo_nonce'   => wp_create_nonce( 'woo_ajax_nonce' ),
                        'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
                        'messages'    => [
                                'stockUpdated'       => __( 'Inventario actualizado.', 'woo-contifico' ),
                                'priceUpdated'       => __( 'Precio actualizado.', 'woo-contifico' ),
                                'metaUpdated'        => __( 'Identificador de Contífico actualizado.', 'woo-contifico' ),
                                'outOfStock'         => __( 'Producto sin stock.', 'woo-contifico' ),
                                'noChanges'          => __( 'Sin cambios en inventario ni precio.', 'woo-contifico' ),
                                'wooSkuLabel'        => __( 'SKU en WooCommerce:', 'woo-contifico' ),
                                'contificoSkuLabel'  => __( 'SKU en Contífico:', 'woo-contifico' ),
                                'contificoIdLabel'   => __( 'ID de Contífico:', 'woo-contifico' ),
                                'stockLabel'         => __( 'Inventario disponible:', 'woo-contifico' ),
                                'priceLabel'         => __( 'Precio actual:', 'woo-contifico' ),
                                'changesLabel'       => __( 'Cambios detectados:', 'woo-contifico' ),
                                'changeDetailSummary' => __( 'Detalle sincronizado:', 'woo-contifico' ),
                                'changeDetailJoiner'  => __( ' · ', 'woo-contifico' ),
                                'stockChangeLabel'   => __( 'Inventario sincronizado', 'woo-contifico' ),
                                'priceChangeLabel'   => __( 'Precio sincronizado', 'woo-contifico' ),
                                'identifierLabel'    => __( 'Identificador de Contífico', 'woo-contifico' ),
                                'noIdentifier'       => __( 'Sin identificador registrado.', 'woo-contifico' ),
                                'noValue'            => __( 'N/D', 'woo-contifico' ),
                                'changeSeparator'    => __( '→', 'woo-contifico' ),
                                'changesHeading'     => __( 'Detalle de cambios', 'woo-contifico' ),
                                'variationsHeading'  => __( 'Detalle por variación', 'woo-contifico' ),
                                'variationLabel'     => __( 'Variación', 'woo-contifico' ),
                                'variationsCountLabel' => __( 'Variaciones sincronizadas', 'woo-contifico' ),
                                'variationsUpdatedLabel' => __( 'Actualizadas', 'woo-contifico' ),
                                'variationsOutOfStockLabel' => __( 'Sin stock', 'woo-contifico' ),
                                'variationsErrorLabel' => __( 'Errores', 'woo-contifico' ),
                                'syncing'            => __( 'Sincronizando producto…', 'woo-contifico' ),
                                'genericError'       => __( 'No fue posible sincronizar el producto. Intenta nuevamente.', 'woo-contifico' ),
                                'missingSku'         => __( 'Debes proporcionar un SKU para iniciar la sincronización.', 'woo-contifico' ),
                                'missingIdentifier'  => __( 'No hay identificador de Contífico guardado.', 'woo-contifico' ),
                                'skuMismatchPrompt'  => __( 'El SKU guardado en Contífico es diferente al SKU actual del producto. ¿Quieres corregir el enlace y continuar? Se limpiará el identificador almacenado antes de reintentar.', 'woo-contifico' ),
                                'pageReloadPending'  => __( 'Actualizando la página para reflejar los cambios…', 'woo-contifico' ),
                                'globalSyncHeading'  => __( 'Resumen de actualizaciones', 'woo-contifico' ),
                                'globalSyncEmpty'    => __( 'No se registraron cambios durante la sincronización.', 'woo-contifico' ),
                                'manualSyncIdle'     => __( 'No hay sincronizaciones manuales en curso.', 'woo-contifico' ),
                                'manualSyncQueued'   => __( 'Sincronización manual programada.', 'woo-contifico' ),
                                'manualSyncRunning'  => __( 'Sincronización en progreso.', 'woo-contifico' ),
                                'manualSyncCancelling' => __( 'Cancelación solicitada. La sincronización se detendrá pronto.', 'woo-contifico' ),
                                'manualSyncCancelled' => __( 'La sincronización fue cancelada.', 'woo-contifico' ),
                                'manualSyncCancelledBeforeStart' => __( 'Sincronización cancelada antes de iniciar.', 'woo-contifico' ),
                                'manualSyncCompleted' => __( 'Sincronización completada correctamente.', 'woo-contifico' ),
                                'manualSyncFailed'   => __( 'La sincronización manual encontró un error.', 'woo-contifico' ),
                                'manualSyncStarting' => __( 'Iniciando sincronización…', 'woo-contifico' ),
                                'manualSyncCanceling' => __( 'Cancelando sincronización…', 'woo-contifico' ),
                                'manualSyncError'    => __( 'No se pudo obtener el estado de la sincronización.', 'woo-contifico' ),
                                'manualSyncLastUpdated' => __( 'Última actualización: %s', 'woo-contifico' ),
                                'manualSyncViewHistory' => __( 'Revisa el historial para ver el reporte completo.', 'woo-contifico' ),
                                'manualSyncHistoryLinkLabel' => __( 'Ver historial de sincronizaciones', 'woo-contifico' ),
                                'manualSyncStatusUnknown' => __( 'Estado de sincronización desconocido.', 'woo-contifico' ),
                        ],
                        'manualSyncPollingInterval' => 5000,
                ];
                wp_localize_script( $this->plugin_name, 'woo_contifico_globals', $params );
        }

        /**
         * Determine if the current admin request is related to the product editor.
         *
         * @since 4.2.0
         *
         * @return bool
         */
        private function is_product_editor_screen() : bool {
                if ( 'product' === $this->resolve_admin_post_type() ) {
                        return true;
                }

                if ( function_exists( 'get_current_screen' ) ) {
                        $screen = get_current_screen();

                        if ( $screen && isset( $screen->post_type ) && 'product' === $screen->post_type ) {
                                return true;
                        }
                }

                return false;
        }

        /**
         * Resolve the current admin post type taking into account request context.
         *
         * @since 4.2.0
         *
         * @return string
         */
        private function resolve_admin_post_type() : string {
                $post_type = get_post_type();

                if ( is_string( $post_type ) && '' !== $post_type ) {
                        return $post_type;
                }

                if ( isset( $_GET['post_type'] ) ) {
                        $requested_post_type = sanitize_key( wp_unslash( (string) $_GET['post_type'] ) );

                        if ( '' !== $requested_post_type ) {
                                return $requested_post_type;
                        }
                }

                if ( isset( $_GET['post'] ) ) {
                        $post_id = absint( wp_unslash( $_GET['post'] ) );

                        if ( $post_id > 0 ) {
                                $post = get_post( $post_id );

                                if ( $post && isset( $post->post_type ) ) {
                                        return (string) $post->post_type;
                                }
                        }
                }

                return '';
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

                $contifico_id          = (string) $product->get_meta( self::PRODUCT_ID_META_KEY, true );
                $product_sku           = (string) $product->get_sku();
                $sync_variations       = $product->is_type( 'variable' );
                $generic_error         = __( 'No fue posible sincronizar el producto. Intenta nuevamente.', 'woo-contifico' );
                $missing_identifier    = __( 'No hay identificador de Contífico guardado.', 'woo-contifico' );
                $sync_button_label     = __( 'Sincronizar con Contífico', 'woo-contifico' );
                $page_reload_message   = __( 'Actualizando la página para reflejar los cambios…', 'woo-contifico' );
                $page_reload_delay_ms  = 2000;

                echo '<div class="options_group woo-contifico-product-id-field"';
                echo ' data-product-id="' . esc_attr( (string) $product->get_id() ) . '"';
                echo ' data-product-sku="' . esc_attr( $product_sku ) . '"';
                echo ' data-generic-error="' . esc_attr( $generic_error ) . '"';
                echo ' data-missing-identifier="' . esc_attr( $missing_identifier ) . '"';
                if ( $sync_variations ) {
                        echo ' data-sync-variations="1"';
                }
                echo ' data-reload-on-success="1"';
                echo ' data-reload-delay="' . esc_attr( (string) $page_reload_delay_ms ) . '"';
                echo ' data-reload-message="' . esc_attr( $page_reload_message ) . '"';
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
         * Register custom bulk action to clear Contífico identifiers.
         *
         * @since 4.1.52
         *
         * @param array $bulk_actions
         *
         * @return array
         */
        public function register_product_bulk_actions( array $bulk_actions ) : array {

                $bulk_actions['woo_contifico_reset_identifier'] = __( 'Corregir enlace con Contífico (SKU)', 'woo-contifico' );

                return $bulk_actions;
        }

        /**
         * Handle the custom bulk action that clears Contífico identifiers.
         *
         * @since 4.1.52
         *
         * @param string $redirect_to
         * @param string $doaction
         * @param array  $post_ids
         *
         * @return string
         */
        public function handle_product_bulk_actions( string $redirect_to, string $doaction, array $post_ids ) : string {

                if ( 'woo_contifico_reset_identifier' !== $doaction ) {
                        return $redirect_to;
                }

                $processed = 0;

                foreach ( $post_ids as $post_id ) {
                        $product = wc_get_product( $post_id );

                        if ( ! $product ) {
                                continue;
                        }

                        if ( $this->clear_contifico_product_identifier( $product ) ) {
                                ++$processed;
                        }
                }

                return add_query_arg(
                        [
                                'woo_contifico_reset_identifier' => $processed,
                                'woo_contifico_reset_identifier_total' => count( $post_ids ),
                        ],
                        $redirect_to
                );
        }

        /**
         * Render admin notice after running the bulk action.
         *
         * @since 4.1.52
         *
         * @return void
         */
        public function render_product_bulk_action_notice() : void {

                if ( ! isset( $_REQUEST['woo_contifico_reset_identifier'] ) ) {
                        return;
                }

                $updated = absint( wp_unslash( $_REQUEST['woo_contifico_reset_identifier'] ) );
                $total   = isset( $_REQUEST['woo_contifico_reset_identifier_total'] )
                        ? absint( wp_unslash( $_REQUEST['woo_contifico_reset_identifier_total'] ) )
                        : $updated;

                if ( $updated <= 0 ) {
                        return;
                }

                printf(
                        '<div class="notice notice-success"><p>%s</p></div>',
                        esc_html(
                                sprintf(
                                        /* translators: 1: processed count, 2: total selected */
                                        __( 'Se limpiaron %1$s de %2$s enlaces con Contífico. Vuelve a sincronizar para restablecer el vínculo con el SKU vigente.', 'woo-contifico' ),
                                        $updated,
                                        $total
                                )
                        )
                );
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

                $this->maybe_mark_inventory_alerts_reviewed();

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
         * Highlight recent Contífico issues that can affect inventory accuracy.
         *
         * @return void
         * @noinspection PhpUnused
         */
        public function admin_contifico_process_alerts() : void {

                if ( ! current_user_can( 'manage_woocommerce' ) ) {
                        return;
                }

                $this->handle_inventory_alerts_dismissal();

                $alerts = $this->get_recent_contifico_process_alerts();

                if ( empty( $alerts ) ) {
                        return;
                }

                $settings_url = esc_url( add_query_arg( [ 'page' => 'woo-contifico', 'tab' => 'movimientos' ], admin_url( 'admin.php' ) ) );
                $dismiss_url  = wp_nonce_url(
                        add_query_arg( 'woo-contifico-dismiss-inventory-alerts', 1 ),
                        'woo-contifico-dismiss-inventory-alerts',
                        '_wc_inv_alert_nonce'
                );

                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>' . esc_html__( 'Contífico: se detectaron problemas recientes en procesos de inventario.', 'woo-contifico' ) . '</strong></p>';
                echo '<ul>';

                foreach ( $alerts as $alert ) {
                        $product = isset( $alert['product_name'] ) ? (string) $alert['product_name'] : '';
                        $sku     = isset( $alert['sku'] ) ? (string) $alert['sku'] : '';
                        $reason  = isset( $alert['message'] ) ? (string) $alert['message'] : '';

                        $label_parts = array_filter( [ $product, $sku ? "({$sku})" : '' ] );
                        $label       = empty( $label_parts ) ? __( 'Producto no identificado', 'woo-contifico' ) : implode( ' ', $label_parts );

                        echo '<li>' . esc_html( sprintf( __( '%1$s: %2$s', 'woo-contifico' ), $label, $reason ) ) . '</li>';
                }

                echo '</ul>';
                echo '<p><a class="button button-primary" href="' . $settings_url . '">' . esc_html__( 'Revisar movimientos de inventario', 'woo-contifico' ) . '</a></p>';
                echo '<p><a class="button" href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Ocultar alertas de inventario', 'woo-contifico' ) . '</a></p>';
                echo '</div>';
        }

        /**
         * Dismiss the current inventory alerts and keep them hidden until new issues appear.
         *
         * @return void
         */
        public function handle_inventory_alerts_dismissal() : void {

                if ( ! isset( $_GET['woo-contifico-dismiss-inventory-alerts'] ) ) {
                        return;
                }

                if ( ! current_user_can( 'manage_woocommerce' ) ) {
                        return;
                }

                $nonce = isset( $_GET['_wc_inv_alert_nonce'] ) ? (string) $_GET['_wc_inv_alert_nonce'] : '';

                if ( ! wp_verify_nonce( $nonce, 'woo-contifico-dismiss-inventory-alerts' ) ) {
                        return;
                }

                $entries = $this->get_inventory_movements_storage();
                $latest  = 0;

                foreach ( $entries as $entry ) {
                        $timestamp = isset( $entry['timestamp'] ) ? (int) $entry['timestamp'] : 0;

                        if ( $timestamp > $latest ) {
                                $latest = $timestamp;
                        }
                }

                if ( 0 === $latest ) {
                        $latest = current_time( 'timestamp', true );
                }

                update_option( self::INVENTORY_ALERTS_REVIEWED_AT, $latest, false );

                wp_safe_redirect(
                        remove_query_arg(
                                [
                                        'woo-contifico-dismiss-inventory-alerts',
                                        '_wc_inv_alert_nonce',
                                ]
                        )
                );
                exit;
        }

        /**
         * Mark the current inventory alerts as reviewed when landing on the movements tab.
         *
         * @return void
         * @since 4.1.56
         */
        private function maybe_mark_inventory_alerts_reviewed() : void {

                $is_plugin_page = isset( $_GET['page'] ) && $this->plugin_name === (string) $_GET['page'];
                $active_tab     = isset( $_GET['tab'] ) ? (string) $_GET['tab'] : 'woocommerce';

                if ( ! $is_plugin_page || 'movimientos' !== $active_tab ) {
                        return;
                }

                $entries = $this->get_inventory_movements_storage();
                $latest  = 0;

                foreach ( $entries as $entry ) {
                        $timestamp = isset( $entry['timestamp'] ) ? (int) $entry['timestamp'] : 0;

                        if ( $timestamp > $latest ) {
                                $latest = $timestamp;
                        }
                }

                if ( 0 === $latest ) {
                        $latest = current_time( 'timestamp', true );
                }

                update_option( self::INVENTORY_ALERTS_REVIEWED_AT, $latest, false );
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
                        case 'warehouse_selector':
                                $this->contifico->fetch_warehouses();
                                $warehouses_map = $this->contifico->get_warehouses_map();
                                $labels_map = get_option( 'woo_contifico_warehouse_labels', [] );
                                if ( ! is_array( $labels_map ) ) {
                                        $labels_map = [];
                                }

                                $selected_codes = $this->get_item_visible_warehouse_codes();
                                $selected_codes = array_map( 'strtoupper', $selected_codes );
                                $selected_codes = array_values( array_unique( $selected_codes ) );

                                $friendly_labels = $this->woo_contifico->settings['bodegas_items_labels'] ?? [];
                                if ( ! is_array( $friendly_labels ) ) {
                                        $friendly_labels = [];
                                }

                                if ( empty( $warehouses_map ) ) {
                                        $field = "<em>" . esc_html__( 'No se encontraron bodegas en Contífico. Verifica la conexión e intenta nuevamente.', 'woo-contifico' ) . "</em>";
                                        break;
                                }

                                $rows = [];
                                foreach ( $warehouses_map as $warehouse_id => $warehouse_code ) {
                                        $warehouse_id = (string) $warehouse_id;
                                        $warehouse_code = strtoupper( (string) $warehouse_code );
                                        $label = '';

                                        if ( isset( $labels_map[ $warehouse_id ]['label'] ) ) {
                                                $label = (string) $labels_map[ $warehouse_id ]['label'];
                                        }

                                        $is_checked = in_array( $warehouse_code, $selected_codes, true );
                                        $checked_attr = checked( $is_checked, true, false );

                                        $friendly_label_value = isset( $friendly_labels[ $warehouse_code ] )
                                                ? (string) $friendly_labels[ $warehouse_code ]
                                                : '';

                                        $checkbox_name = "{$name}[]";
                                        $label_name = "{$args['setting_name']}[bodegas_items_labels][{$warehouse_code}]";
                                        $checkbox_id = "{$key}_{$warehouse_code}";

                                        $rows[] = [
                                                'code' => $warehouse_code,
                                                'label' => $label,
                                                'checkbox' => "<input type='checkbox' name='{$checkbox_name}' id='{$checkbox_id}' value='{$warehouse_code}' {$checked_attr} />",
                                                'friendly' => "<input type='text' class='input-text' name='{$label_name}' value='" . esc_attr( $friendly_label_value ) . "' size='30' />",
                                        ];
                                }

                                usort( $rows, static function( $left, $right ) {
                                        return strcmp( $left['code'], $right['code'] );
                                } );

                                $table = "<table class='widefat striped'><thead><tr>";
                                $table .= '<th>' . esc_html__( 'Mostrar', 'woo-contifico' ) . '</th>';
                                $table .= '<th>' . esc_html__( 'Código', 'woo-contifico' ) . '</th>';
                                $table .= '<th>' . esc_html__( 'Nombre en Contífico', 'woo-contifico' ) . '</th>';
                                $table .= '<th>' . esc_html__( 'Nombre amigable', 'woo-contifico' ) . '</th>';
                                $table .= '</tr></thead><tbody>';

                                foreach ( $rows as $row ) {
                                        $table .= '<tr>';
                                        $table .= "<td>{$row['checkbox']}</td>";
                                        $table .= '<td>' . esc_html( $row['code'] ) . '</td>';
                                        $table .= '<td>' . esc_html( $row['label'] ) . '</td>';
                                        $table .= "<td>{$row['friendly']}</td>";
                                        $table .= '</tr>';
                                }

                                $table .= '</tbody></table>';

                                $field = $table;
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
                        delete_transient( self::SYNC_RESULT_TRANSIENT_KEY );
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
         * Start a background manual synchronization.
         *
         * @since 4.3.0
         *
         * @return void
         */
        public function start_manual_sync() : void {

                check_ajax_referer( 'woo_ajax_nonce', 'security' );

                if ( $this->is_active() !== true ) {
                        wp_send_json_error(
                                [ 'message' => __( 'El conector no está activo.', 'woo-contifico' ) ],
                                400
                        );

                        return;
                }

                if ( ! function_exists( 'as_enqueue_async_action' ) ) {
                        wp_send_json_error(
                                [ 'message' => __( 'La sincronización manual requiere Action Scheduler.', 'woo-contifico' ) ],
                                500
                        );

                        return;
                }

                $state = $this->read_manual_sync_state();

                if ( $this->is_manual_sync_active_state( $state ) ) {
                        wp_send_json_error(
                                [ 'message' => __( 'Ya existe una sincronización manual en curso.', 'woo-contifico' ) ],
                                409
                        );

                        return;
                }

                $run_id    = $this->generate_manual_sync_run_id();
                $timestamp = current_time( 'mysql' );
                $state     = $this->get_default_manual_sync_state();

                $state['status']       = 'queued';
                $state['run_id']       = $run_id;
                $state['started_at']   = $timestamp;
                $state['last_updated'] = $timestamp;
                $state['message']      = __( 'Sincronización manual programada.', 'woo-contifico' );

                $this->clear_manual_sync_queue();
                $this->clear_manual_sync_cancellation_flag();
                $this->reset_manual_sync_environment();

                $job_id = as_enqueue_async_action( 'woo_contifico_manual_sync', [ 1 ], $this->plugin_name );

                if ( $job_id ) {
                        $state['job_id'] = (int) $job_id;
                }

                $this->write_manual_sync_state( $state );
                $this->schedule_manual_sync_keepalive();

                wp_send_json_success(
                        [ 'state' => $this->prepare_manual_sync_state_for_response( $state ) ]
                );
        }

        /**
         * Retrieve the current manual synchronization status.
         *
         * @since 4.3.0
         *
         * @return void
         */
        public function get_manual_sync_status() : void {

                check_ajax_referer( 'woo_ajax_nonce', 'security' );

                $state = $this->read_manual_sync_state();

                wp_send_json_success(
                        [ 'state' => $this->prepare_manual_sync_state_for_response( $state ) ]
                );
        }

        /**
         * Request the cancellation of the current manual synchronization.
         *
         * @since 4.3.0
         *
         * @return void
         */
        public function cancel_manual_sync() : void {

                check_ajax_referer( 'woo_ajax_nonce', 'security' );

                $state = $this->read_manual_sync_state();

                if ( ! $this->is_manual_sync_active_state( $state ) ) {
                        wp_send_json_error(
                                [ 'message' => __( 'No hay una sincronización manual en ejecución.', 'woo-contifico' ) ],
                                400
                        );

                        return;
                }

                $status = isset( $state['status'] ) ? (string) $state['status'] : 'idle';

                $this->clear_manual_sync_queue();

                if ( 'queued' === $status ) {
                        $state = $this->set_manual_sync_status(
                                $state,
                                'cancelled',
                                __( 'Sincronización cancelada antes de iniciar.', 'woo-contifico' )
                        );

                        $state['finished_at'] = current_time( 'mysql' );
                        $run_id               = isset( $state['run_id'] ) ? (string) $state['run_id'] : '';
                        $state['run_id']      = '';
                        $state['job_id']      = 0;

                        $this->write_manual_sync_state( $state );
                        $this->clear_manual_sync_keepalive();

                        if ( '' !== $run_id ) {
                                $this->append_manual_sync_history_entry(
                                        $this->build_manual_sync_history_entry(
                                                $run_id,
                                                $state,
                                                'cancelled',
                                                __( 'Sincronización cancelada antes de iniciar.', 'woo-contifico' )
                                        )
                                );
                        }

                        $this->clear_manual_sync_cancellation_flag();

                        wp_send_json_success(
                                [ 'state' => $this->prepare_manual_sync_state_for_response( $state ) ]
                        );

                        return;
                }

                $this->request_manual_sync_cancellation();

                $state = $this->set_manual_sync_status(
                        $state,
                        'cancelling',
                        __( 'Cancelación solicitada. La sincronización se detendrá pronto.', 'woo-contifico' )
                );

                $this->write_manual_sync_state( $state );
                $this->schedule_manual_sync_keepalive();

                wp_send_json_success(
                        [ 'state' => $this->prepare_manual_sync_state_for_response( $state ) ]
                );
        }

        /**
         * Background processor for manual synchronizations.
         *
         * @since 4.3.0
         *
         * @param int $step
         *
         * @return void
         */
        public function manual_sync_processing( int $step = 1 ) : void {

                $state  = $this->read_manual_sync_state();
                $run_id = isset( $state['run_id'] ) ? (string) $state['run_id'] : '';

                if ( '' === $run_id ) {
                        return;
                }

                $this->schedule_manual_sync_keepalive();

                if ( $this->is_manual_sync_cancellation_requested() ) {
                        $state = $this->finalize_manual_sync_run(
                                $state,
                                'cancelled',
                                __( 'Sincronización cancelada por el usuario.', 'woo-contifico' )
                        );

                        return;
                }

                if ( $step === 1 ) {
                        $this->reset_manual_sync_environment();
                }

                $state = $this->set_manual_sync_status(
                        $state,
                        'running',
                        __( 'Sincronización en progreso.', 'woo-contifico' )
                );

                $this->write_manual_sync_state( $state );

                try {
                        $result = $this->sync_stock( $step, $this->woo_contifico->settings['batch_size'] );
                }
                catch ( Exception $exception ) {
                        $this->finalize_manual_sync_run(
                                $state,
                                'failed',
                                $exception->getMessage()
                        );

                        throw $exception;
                }

                $state = $this->persist_manual_sync_progress( $state, $result, 'running' );

                $this->write_manual_sync_state( $state );

                if ( isset( $result['step'] ) && 'done' === $result['step'] ) {
                        $this->finalize_manual_sync_run(
                                $state,
                                'completed',
                                __( 'Sincronización completada correctamente.', 'woo-contifico' )
                        );

                        return;
                }

                if ( $this->is_manual_sync_cancellation_requested() ) {
                        $this->finalize_manual_sync_run(
                                $state,
                                'cancelled',
                                __( 'Sincronización cancelada por el usuario.', 'woo-contifico' )
                        );

                        return;
                }

                if ( function_exists( 'as_enqueue_async_action' ) ) {
                        as_enqueue_async_action( 'woo_contifico_manual_sync', [ (int) $result['step'] ], $this->plugin_name );
                }
        }

        /**
         * Initialize the scheduled synchronization accumulator.
         *
         * @since 4.1.34
         *
         * @return void
         */
        private function initialize_scheduled_sync_result() : void {

                $initial_result = $this->normalize_sync_result_defaults( [] );

                $initial_result['run_id']     = $this->generate_sync_run_id();
                $initial_result['started_at'] = current_time( 'mysql' );

                set_transient( self::SYNC_RESULT_TRANSIENT_KEY, $initial_result, HOUR_IN_SECONDS );
        }

        /**
         * Normalize sync result payload to ensure expected keys are present.
         *
         * @since 4.1.34
         *
         * @param array $result
         *
         * @return array
         */
        private function normalize_sync_result_defaults( array $result ) : array {

                $defaults = [
                        'run_id'      => '',
                        'status'      => 'running',
                        'started_at'  => '',
                        'finished_at' => '',
                        'fetched'     => 0,
                        'found'       => 0,
                        'updated'     => 0,
                        'outofstock'  => 0,
                        'updates'     => [],
                        'debug_log'   => '',
                ];

                $normalized = array_merge( $defaults, $result );

                $normalized['run_id']      = (string) $normalized['run_id'];
                $normalized['status']      = (string) $normalized['status'];
                $normalized['started_at']  = (string) $normalized['started_at'];
                $normalized['finished_at'] = (string) $normalized['finished_at'];
                $normalized['fetched']     = (int) $normalized['fetched'];
                $normalized['found']       = (int) $normalized['found'];
                $normalized['updated']     = (int) $normalized['updated'];
                $normalized['outofstock']  = (int) $normalized['outofstock'];
                $normalized['updates']     = isset( $normalized['updates'] ) && is_array( $normalized['updates'] ) ? $normalized['updates'] : [];
                $normalized['debug_log']   = (string) $normalized['debug_log'];

                return $normalized;
        }

        /**
         * Generate a unique identifier for sync runs.
         *
         * @since 4.1.34
         *
         * @return string
         */
        private function generate_sync_run_id() : string {

                if ( function_exists( 'wp_generate_uuid4' ) ) {
                        return (string) wp_generate_uuid4();
                }

                return uniqid( 'woo-contifico-sync-', true );
        }

        /**
         * Get the current manual synchronization state.
         *
         * @since 4.3.0
         *
         * @return array
         */
        public function get_manual_sync_state() : array {
                return $this->read_manual_sync_state();
        }

        /**
         * Retrieve the stored manual synchronization history entries.
         *
         * @since 4.3.0
         *
         * @return array
         */
        public function get_manual_sync_history() : array {

                $history = $this->get_manual_sync_history_option();

                return array_map(
                        function ( $entry ) {
                                if ( ! is_array( $entry ) ) {
                                        $entry = [];
                                }

                                $entry['id']          = isset( $entry['id'] ) ? (string) $entry['id'] : '';
                                $entry['status']      = isset( $entry['status'] ) ? (string) $entry['status'] : 'completed';
                                $entry['message']     = isset( $entry['message'] ) ? (string) $entry['message'] : '';
                                $entry['started_at']  = isset( $entry['started_at'] ) ? (string) $entry['started_at'] : '';
                                $entry['finished_at'] = isset( $entry['finished_at'] ) ? (string) $entry['finished_at'] : '';
                                $entry['fetched']     = isset( $entry['fetched'] ) ? (int) $entry['fetched'] : 0;
                                $entry['found']       = isset( $entry['found'] ) ? (int) $entry['found'] : 0;
                                $entry['updated']     = isset( $entry['updated'] ) ? (int) $entry['updated'] : 0;
                                $entry['outofstock']  = isset( $entry['outofstock'] ) ? (int) $entry['outofstock'] : 0;
                                $entry['debug_log']   = isset( $entry['debug_log'] ) ? (string) $entry['debug_log'] : '';
                                $entry['updates']     = isset( $entry['updates'] ) && is_array( $entry['updates'] ) ? array_values( $entry['updates'] ) : [];

                                return $this->normalize_manual_sync_data( $entry );
                        },
                        $history
                );
        }

        /**
         * Describe an individual product update for manual synchronization reports.
         *
         * @since 4.3.0
         *
         * @param array $entry
         *
         * @return array
         */
        public function describe_manual_sync_update_entry( array $entry ) : array {

                $entry = $this->normalize_manual_sync_data( $entry );

                if ( ! is_array( $entry ) ) {
                        $entry = [];
                }

                $title = isset( $entry['product_name'] ) ? (string) $entry['product_name'] : '';

                if ( '' === $title ) {
                        $title = __( 'Producto sin nombre', 'woo-contifico' );
                }

                $meta    = [];
                $changes = [];
                $sku     = isset( $entry['sku'] ) ? (string) $entry['sku'] : '';

                if ( '' !== $sku ) {
                        $meta[] = [
                                'label' => __( 'SKU en WooCommerce', 'woo-contifico' ),
                                'value' => $sku,
                        ];
                }

                $contifico_id = isset( $entry['contifico_id'] ) ? (string) $entry['contifico_id'] : '';

                if ( '' !== $contifico_id ) {
                        $meta[] = [
                                'label' => __( 'ID de Contífico', 'woo-contifico' ),
                                'value' => $contifico_id,
                        ];
                }

                $changes_data = isset( $entry['changes'] ) && is_array( $entry['changes'] ) ? $entry['changes'] : [];
                $separator    = __( '→', 'woo-contifico' );
                $fallback     = __( 'N/D', 'woo-contifico' );

                if ( isset( $changes_data['stock'] ) && is_array( $changes_data['stock'] ) ) {
                        $stock_change = $changes_data['stock'];

                        $changes[] = [
                                'label' => __( 'Inventario sincronizado', 'woo-contifico' ),
                                'value' => $this->format_sync_change_value(
                                        $stock_change['previous'] ?? null,
                                        $stock_change['current'] ?? null,
                                        $separator,
                                        $fallback
                                ),
                                'notes' => ! empty( $stock_change['outofstock'] ) ? __( 'Producto sin stock.', 'woo-contifico' ) : '',
                        ];
                }

                if ( isset( $changes_data['price'] ) && is_array( $changes_data['price'] ) ) {
                        $price_change = $changes_data['price'];

                        $changes[] = [
                                'label' => __( 'Precio sincronizado', 'woo-contifico' ),
                                'value' => $this->format_sync_change_value(
                                        $price_change['previous'] ?? null,
                                        $price_change['current'] ?? null,
                                        $separator,
                                        $fallback
                                ),
                                'notes' => '',
                        ];
                }

                if ( isset( $changes_data['identifier'] ) && is_array( $changes_data['identifier'] ) ) {
                        $identifier_change = $changes_data['identifier'];
                        $fallback_identifier = __( 'Sin identificador registrado.', 'woo-contifico' );

                        $changes[] = [
                                'label' => __( 'Identificador de Contífico', 'woo-contifico' ),
                                'value' => $this->format_sync_identifier_value(
                                        isset( $identifier_change['previous'] ) ? (string) $identifier_change['previous'] : '',
                                        isset( $identifier_change['current'] ) ? (string) $identifier_change['current'] : '',
                                        $separator,
                                        $fallback_identifier
                                ),
                                'notes' => '',
                        ];
                }

                return [
                        'title'   => $title,
                        'meta'    => $meta,
                        'changes' => $changes,
                ];
        }

        /**
         * Read the persisted manual synchronization state.
         *
         * @since 4.3.0
         *
         * @return array
         */
        private function read_manual_sync_state() : array {
                $state = get_option( self::MANUAL_SYNC_STATE_OPTION, [] );
                $state = $this->normalize_manual_sync_data( $state );

                if ( ! is_array( $state ) ) {
                        $state = [];
                }

                return $this->prepare_manual_sync_state_for_response( $state );
        }

        /**
         * Persist the manual synchronization state.
         *
         * @since 4.3.0
         *
         * @param array $state
         *
         * @return void
         */
        private function write_manual_sync_state( array $state ) : void {
                update_option( self::MANUAL_SYNC_STATE_OPTION, $this->prepare_manual_sync_state_for_response( $state ), false );
        }

        /**
         * Provide the default shape for manual synchronization state entries.
         *
         * @since 4.3.0
         *
         * @return array
         */
        private function get_default_manual_sync_state() : array {
                return [
                        'status'       => 'idle',
                        'run_id'       => '',
                        'started_at'   => '',
                        'finished_at'  => '',
                        'last_updated' => '',
                        'message'      => '',
                        'job_id'       => 0,
                        'progress'     => [
                                'step'       => 0,
                                'fetched'    => 0,
                                'found'      => 0,
                                'updated'    => 0,
                                'outofstock' => 0,
                                'updates'    => [],
                                'debug_log'  => '',
                        ],
                ];
        }

        /**
         * Determine if the provided state represents an active synchronization.
         *
         * @since 4.3.0
         *
         * @param array $state
         *
         * @return bool
         */
        private function is_manual_sync_active_state( array $state ) : bool {
                $run_id = isset( $state['run_id'] ) ? (string) $state['run_id'] : '';

                if ( '' === $run_id ) {
                        return false;
                }

                $status = isset( $state['status'] ) ? (string) $state['status'] : 'idle';

                return in_array( $status, [ 'queued', 'running', 'cancelling' ], true );
        }

        /**
         * Generate an identifier for a manual synchronization run.
         *
         * @since 4.3.0
         *
         * @return string
         */
        private function generate_manual_sync_run_id() : string {
                if ( function_exists( 'wp_generate_uuid4' ) ) {
                        return (string) wp_generate_uuid4();
                }

                return uniqid( 'woo_contifico_sync_', true );
        }

        /**
         * Remove scheduled manual synchronization tasks.
         *
         * @since 4.3.0
         *
         * @return void
         */
        private function clear_manual_sync_queue() : void {
                if ( function_exists( 'as_unschedule_all_actions' ) ) {
                        as_unschedule_all_actions( 'woo_contifico_manual_sync', null, $this->plugin_name );
                }
        }

        /**
         * Schedule a keepalive event for manual synchronizations.
         *
         * @since 4.3.0
         *
         * @param int $delay
         *
         * @return void
         */
        private function schedule_manual_sync_keepalive( int $delay = 30 ) : void {
                if ( ! function_exists( 'wp_schedule_single_event' ) ) {
                        return;
                }

                $delay = max( 15, (int) $delay );

                if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
                        wp_clear_scheduled_hook( self::MANUAL_SYNC_KEEPALIVE_HOOK );
                }

                wp_schedule_single_event( time() + $delay, self::MANUAL_SYNC_KEEPALIVE_HOOK );
        }

        /**
         * Cancel any pending keepalive events for manual synchronizations.
         *
         * @since 4.3.0
         *
         * @return void
         */
        private function clear_manual_sync_keepalive() : void {
                if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
                        wp_clear_scheduled_hook( self::MANUAL_SYNC_KEEPALIVE_HOOK );
                }
        }

        /**
         * Request a manual synchronization cancellation.
         *
         * @since 4.3.0
         *
         * @return void
         */
        private function request_manual_sync_cancellation() : void {
                set_transient( self::MANUAL_SYNC_CANCEL_TRANSIENT, 1, HOUR_IN_SECONDS );
        }

        /**
         * Clear the manual synchronization cancellation marker.
         *
         * @since 4.3.0
         *
         * @return void
         */
        private function clear_manual_sync_cancellation_flag() : void {
                delete_transient( self::MANUAL_SYNC_CANCEL_TRANSIENT );
        }

        /**
         * Determine whether a cancellation has been requested.
         *
         * @since 4.3.0
         *
         * @return bool
         */
        private function is_manual_sync_cancellation_requested() : bool {
                return (bool) get_transient( self::MANUAL_SYNC_CANCEL_TRANSIENT );
        }

        /**
         * Normalize manual synchronization state data before persisting or returning it.
         *
         * @since 4.3.0
         *
         * @param array $state
         *
         * @return array
         */
        private function prepare_manual_sync_state_for_response( array $state ) : array {

                $defaults = $this->get_default_manual_sync_state();
                $state    = array_merge( $defaults, $state );

                $state['status']       = isset( $state['status'] ) ? (string) $state['status'] : 'idle';
                $state['run_id']       = isset( $state['run_id'] ) ? (string) $state['run_id'] : '';
                $state['started_at']   = isset( $state['started_at'] ) ? (string) $state['started_at'] : '';
                $state['finished_at']  = isset( $state['finished_at'] ) ? (string) $state['finished_at'] : '';
                $state['last_updated'] = isset( $state['last_updated'] ) ? (string) $state['last_updated'] : '';
                $state['message']      = isset( $state['message'] ) ? (string) $state['message'] : '';
                $state['job_id']       = isset( $state['job_id'] ) ? (int) $state['job_id'] : 0;
                $state['progress']     = $this->normalize_manual_sync_progress(
                        isset( $state['progress'] ) && is_array( $state['progress'] ) ? $state['progress'] : []
                );

                return $state;
        }

        /**
         * Normalize the manual synchronization progress details.
         *
         * @since 4.3.0
         *
         * @param array $progress
         *
         * @return array
         */
        private function normalize_manual_sync_progress( array $progress ) : array {
                $defaults = [
                        'step'       => 0,
                        'fetched'    => 0,
                        'found'      => 0,
                        'updated'    => 0,
                        'outofstock' => 0,
                        'updates'    => [],
                        'debug_log'  => '',
                ];

                $progress = array_merge( $defaults, $progress );

                $progress['fetched']    = (int) $progress['fetched'];
                $progress['found']      = (int) $progress['found'];
                $progress['updated']    = (int) $progress['updated'];
                $progress['outofstock'] = (int) $progress['outofstock'];
                $progress['updates']    = isset( $progress['updates'] ) && is_array( $progress['updates'] ) ? array_values( $progress['updates'] ) : [];
                $progress['debug_log']  = isset( $progress['debug_log'] ) ? (string) $progress['debug_log'] : '';

                if ( is_numeric( $progress['step'] ) ) {
                        $progress['step'] = (int) $progress['step'];
                } else {
                        $progress['step'] = (string) $progress['step'];
                }

                return $progress;
        }

        /**
         * Update the state status helper.
         *
         * @since 4.3.0
         *
         * @param array  $state
         * @param string $status
         * @param string $message
         *
         * @return array
         */
        private function set_manual_sync_status( array $state, string $status, string $message = '' ) : array {
                $state['status']       = $status;
                $state['last_updated'] = current_time( 'mysql' );

                if ( '' !== $message ) {
                        $state['message'] = $message;
                }

                return $state;
        }

        /**
         * Persist manual synchronization progress counters.
         *
         * @since 4.3.0
         *
         * @param array  $state
         * @param array  $result
         * @param string $status
         *
         * @return array
         */
        private function persist_manual_sync_progress( array $state, array $result, string $status ) : array {
                $progress = isset( $state['progress'] ) && is_array( $state['progress'] ) ? $state['progress'] : [];

                if ( isset( $result['step'] ) ) {
                        $progress['step'] = $result['step'];
                }

                foreach ( [ 'fetched', 'found', 'updated', 'outofstock' ] as $counter ) {
                        if ( isset( $result[ $counter ] ) ) {
                                $progress[ $counter ] = (int) $result[ $counter ];
                        }
                }

                if ( isset( $result['updates'] ) && is_array( $result['updates'] ) ) {
                        $progress['updates'] = array_values( $result['updates'] );
                }

                if ( isset( $result['debug_log'] ) ) {
                        $progress['debug_log'] = (string) $result['debug_log'];
                }

                $state['progress'] = $this->normalize_manual_sync_progress( $progress );

                return $this->set_manual_sync_status( $state, $status );
        }

        /**
         * Finalize a manual synchronization run and store its history entry.
         *
         * @since 4.3.0
         *
         * @param array  $state
         * @param string $status
         * @param string $message
         *
         * @return array
         */
        private function finalize_manual_sync_run( array $state, string $status, string $message ) : array {
                $run_id = isset( $state['run_id'] ) ? (string) $state['run_id'] : '';

                $state = $this->set_manual_sync_status( $state, $status, $message );
                $state['finished_at'] = current_time( 'mysql' );
                $state['run_id']      = '';
                $state['job_id']      = 0;

                $this->write_manual_sync_state( $state );

                if ( '' !== $run_id ) {
                        $this->append_manual_sync_history_entry(
                                $this->build_manual_sync_history_entry( $run_id, $state, $status, $message )
                        );
                }

                $this->clear_manual_sync_cancellation_flag();
                $this->clear_manual_sync_queue();
                $this->clear_manual_sync_keepalive();

                return $state;
        }

        /**
         * Ensure manual synchronization background jobs continue running.
         *
         * @since 4.3.0
         *
         * @return void
         */
        public function run_manual_sync_keepalive() : void {
                if ( ! function_exists( 'as_next_scheduled_action' ) || ! function_exists( 'as_enqueue_async_action' ) ) {
                        $this->clear_manual_sync_keepalive();

                        return;
                }

                $state = $this->read_manual_sync_state();

                if ( ! $this->is_manual_sync_active_state( $state ) ) {
                        $this->clear_manual_sync_keepalive();

                        return;
                }

                $next_step = 1;

                if ( isset( $state['progress']['step'] ) ) {
                        $progress_step = $state['progress']['step'];

                        if ( is_numeric( $progress_step ) ) {
                                $next_step = max( 1, (int) $progress_step );
                        }
                }

                if ( ! as_next_scheduled_action( 'woo_contifico_manual_sync', null, $this->plugin_name ) ) {
                        as_enqueue_async_action( 'woo_contifico_manual_sync', [ $next_step ], $this->plugin_name );
                }

                $this->schedule_manual_sync_keepalive();
        }

        /**
         * Build a manual synchronization history entry.
         *
         * @since 4.3.0
         *
         * @param string $run_id
         * @param array  $state
         * @param string $status
         * @param string $message
         *
         * @return array
         */
        private function build_manual_sync_history_entry( string $run_id, array $state, string $status, string $message ) : array {
                $progress    = isset( $state['progress'] ) && is_array( $state['progress'] ) ? $this->normalize_manual_sync_progress( $state['progress'] ) : $this->get_default_manual_sync_state()['progress'];
                $recorded_at = current_time( 'mysql' );

                $started_at  = isset( $state['started_at'] ) && '' !== (string) $state['started_at'] ? (string) $state['started_at'] : $recorded_at;
                $finished_at = isset( $state['finished_at'] ) && '' !== (string) $state['finished_at'] ? (string) $state['finished_at'] : $started_at;
                $saved_at    = '' !== $finished_at ? $finished_at : $recorded_at;

                return [
                        'id'          => $run_id,
                        'status'      => $status,
                        'message'     => $message,
                        'started_at'  => $started_at,
                        'finished_at' => $finished_at,
                        'saved_at'    => $saved_at,
                        'fetched'     => $progress['fetched'] ?? 0,
                        'found'       => $progress['found'] ?? 0,
                        'updated'     => $progress['updated'] ?? 0,
                        'outofstock'  => $progress['outofstock'] ?? 0,
                        'updates'     => $progress['updates'] ?? [],
                        'debug_log'   => $progress['debug_log'] ?? '',
                ];
        }

        /**
         * Append a history entry to the stored manual synchronization log.
         *
         * @since 4.3.0
         *
         * @param array $entry
         *
         * @return void
         */
        private function append_manual_sync_history_entry( array $entry ) : void {
                $history = $this->get_manual_sync_history_option();

                array_unshift( $history, $entry );

                $max_entries = 20;

                if ( count( $history ) > $max_entries ) {
                        $history = array_slice( $history, 0, $max_entries );
                }

                $this->save_manual_sync_history( $history );
        }

        /**
         * Retrieve the raw manual synchronization history option.
         *
         * @since 4.3.0
         *
         * @return array
         */
        private function get_manual_sync_history_option() : array {
                $history = get_option( self::MANUAL_SYNC_HISTORY_OPTION, [] );
                $history = $this->normalize_manual_sync_data( $history );

                if ( ! is_array( $history ) ) {
                        return [];
                }

                $normalized_history = [];

                foreach ( $history as $entry ) {
                        $entry = $this->normalize_manual_sync_data( $entry );

                        if ( is_array( $entry ) ) {
                                if ( ! isset( $entry['saved_at'] ) || '' === (string) $entry['saved_at'] ) {
                                        $entry['saved_at'] = isset( $entry['finished_at'] ) && '' !== (string) $entry['finished_at']
                                                ? (string) $entry['finished_at']
                                                : ( isset( $entry['started_at'] ) ? (string) $entry['started_at'] : '' );

                                        if ( '' === $entry['saved_at'] ) {
                                                $entry['saved_at'] = current_time( 'mysql' );
                                        }
                                }

                                $normalized_history[] = $entry;
                        }
                }

                return $normalized_history;
        }

	/**
	 * Persist the manual synchronization history list.
	 *
	 * @since 4.3.0
	 *
	 * @param array $history
	 *
	 * @return void
	 */
	private function save_manual_sync_history( array $history ) : void {
		update_option( self::MANUAL_SYNC_HISTORY_OPTION, array_values( $history ), false );
		delete_transient( self::INVENTORY_MOVEMENTS_TRANSIENT );
	}

	/**
	 * Normalize manual synchronization data casting objects to arrays recursively.
	 *
	 * @since 4.3.1
	 *
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	private function normalize_manual_sync_data( $value ) {

		if ( is_object( $value ) ) {
			$value = get_object_vars( $value );
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $this->normalize_manual_sync_data( $item );
			}
		}

		return $value;
	}

	/**
	 * Retrieve the cached inventory movements list.
	 *
	 * @since 4.3.1
	 */
	private function get_inventory_movements_storage() : array {

		$cached = get_transient( self::INVENTORY_MOVEMENTS_TRANSIENT );

		if ( false !== $cached && is_array( $cached ) ) {
			$hydrated = $this->maybe_backfill_inventory_movements_from_history( $cached );

			if ( $hydrated !== $cached ) {
				set_transient( self::INVENTORY_MOVEMENTS_TRANSIENT, $hydrated, MINUTE_IN_SECONDS );
			}

			return $hydrated;
		}

		$entries   = get_option( self::INVENTORY_MOVEMENTS_STORAGE, [] );
		$entries   = $this->normalize_manual_sync_data( $entries );
		$processed = [];

		if ( ! is_array( $entries ) ) {
			$entries = [];
		}

		foreach ( $entries as $entry ) {
			$entry = $this->normalize_inventory_movement_entry( $entry );

			if ( ! empty( $entry ) ) {
				$processed[] = $entry;
			}
		}

		$processed = $this->maybe_backfill_inventory_movements_from_history( $processed );

		set_transient( self::INVENTORY_MOVEMENTS_TRANSIENT, $processed, MINUTE_IN_SECONDS );

		return $processed;
	}

        /**
         * Collect recent Contífico process errors to surface on the dashboard.
         *
         * @return array<int,array<string,mixed>>
         * @since 4.1.33
         */
        private function get_recent_contifico_process_alerts() : array {

                $entries          = $this->get_inventory_movements_storage();
                $last_reviewed_at = (int) get_option( self::INVENTORY_ALERTS_REVIEWED_AT, 0 );
                $threshold        = max( current_time( 'timestamp', true ) - DAY_IN_SECONDS, $last_reviewed_at + 1 );
                $aggregated       = [];

                foreach ( $entries as $entry ) {
                        $status    = isset( $entry['status'] ) ? (string) $entry['status'] : '';
                        $timestamp = isset( $entry['timestamp'] ) ? (int) $entry['timestamp'] : 0;

                        if ( 'error' !== $status || $timestamp < $threshold ) {
                                continue;
                        }

                        $product_id = (int) ( $entry['wc_product_id'] ?? 0 );
                        $key        = $product_id > 0 ? 'wc_' . $product_id : (string) ( $entry['product_id'] ?? '' );

                        if ( '' === $key ) {
                                $key = 'generic_' . md5( wp_json_encode( $entry ) );
                        }

                        if ( isset( $aggregated[ $key ] ) ) {
                                continue;
                        }

                        $aggregated[ $key ] = [
                                'product_name' => isset( $entry['product_name'] ) ? (string) $entry['product_name'] : '',
                                'sku'          => isset( $entry['sku'] ) ? (string) $entry['sku'] : '',
                                'message'      => isset( $entry['error_message'] ) ? (string) $entry['error_message'] : __( 'Error no especificado en Contífico.', 'woo-contifico' ),
                        ];
                }

                return array_values( $aggregated );
        }

/**
 * Persist the inventory movements list.
 *
 * @since 4.3.1
 */
        private function save_inventory_movements( array $entries ) : void {
                update_option( self::INVENTORY_MOVEMENTS_STORAGE, array_values( $entries ), false );
                delete_transient( self::INVENTORY_MOVEMENTS_TRANSIENT );
        }

/**
 * Retrieve the list of manual sync run IDs already converted into inventory movements.
 *
 * @since 4.3.1
 */
        private function get_inventory_movement_history_runs() : array {

                $runs = get_option( self::INVENTORY_MOVEMENTS_HISTORY_RUNS, [] );

                if ( ! is_array( $runs ) ) {
                        return [];
                }

                return $runs;
        }

/**
 * Persist the list of manual sync run IDs converted into movements.
 *
 * @since 4.3.1
 */
        private function save_inventory_movement_history_runs( array $runs ) : void {

                if ( count( $runs ) > 50 ) {
                        $runs = array_slice( $runs, -50, null, true );
                }

                update_option( self::INVENTORY_MOVEMENTS_HISTORY_RUNS, $runs, false );
        }

/**
 * Convert manual sync history rows into movement entries when needed.
 *
 * @since 4.3.1
 */
        private function maybe_backfill_inventory_movements_from_history( array $current_entries ) : array {

                $history = $this->get_manual_sync_history_option();

                if ( empty( $history ) ) {
                        return $current_entries;
                }

                $processed_runs = $this->get_inventory_movement_history_runs();
                $new_entries    = [];
                $updated_runs   = $processed_runs;

                foreach ( $history as $entry ) {
                        $entry  = $this->normalize_manual_sync_data( $entry );
                        $run_id = isset( $entry['id'] ) ? (string) $entry['id'] : '';

                        if ( '' === $run_id || isset( $processed_runs[ $run_id ] ) ) {
                                continue;
                        }

                        $movements_for_run = $this->build_inventory_movements_from_history_entry( $entry );

                        if ( ! empty( $movements_for_run ) ) {
                                $new_entries = array_merge( $movements_for_run, $new_entries );
                        }

                        $updated_runs[ $run_id ] = current_time( 'timestamp', true );
                }

                if ( empty( $new_entries ) ) {
                        if ( $updated_runs !== $processed_runs ) {
                                $this->save_inventory_movement_history_runs( $updated_runs );
                        }

                        return $current_entries;
                }

                $current_entries = array_merge( $new_entries, $current_entries );

                if ( count( $current_entries ) > self::INVENTORY_MOVEMENTS_MAX_ENTRIES ) {
                        $current_entries = array_slice( $current_entries, 0, self::INVENTORY_MOVEMENTS_MAX_ENTRIES );
                }

                $this->save_inventory_movements( $current_entries );
                $this->save_inventory_movement_history_runs( $updated_runs );

                return $current_entries;
        }

        /**
         * Append multiple inventory movement entries.
         *
         * @since 4.3.1
         */
        private function append_inventory_movement_entries( array $entries ) : void {

                if ( empty( $entries ) ) {
                        return;
                }

                $current = $this->get_inventory_movements_storage();
                $entries = array_map( [ $this, 'normalize_inventory_movement_entry' ], $entries );
                $entries = array_filter( $entries );

                if ( empty( $entries ) ) {
                        return;
                }

                $unique_entries = [];

                foreach ( $entries as $entry ) {
                        $is_duplicate = false;

                        foreach ( $current as $existing_entry ) {
                                if ( $this->is_duplicate_inventory_movement_entry( $entry, $existing_entry ) ) {
                                        $is_duplicate = true;

                                        break;
                                }
                        }

                        if ( ! $is_duplicate ) {
                                foreach ( $unique_entries as $existing_entry ) {
                                        if ( $this->is_duplicate_inventory_movement_entry( $entry, $existing_entry ) ) {
                                                $is_duplicate = true;

                                                break;
                                        }
                                }
                        }

                        if ( ! $is_duplicate ) {
                                $unique_entries[] = $entry;
                        }
                }

                if ( empty( $unique_entries ) ) {
                        return;
                }

                $current = array_merge( $unique_entries, $current );

                if ( count( $current ) > self::INVENTORY_MOVEMENTS_MAX_ENTRIES ) {
                        $current = array_slice( $current, 0, self::INVENTORY_MOVEMENTS_MAX_ENTRIES );
                }

                $this->save_inventory_movements( $current );
        }

        /**
         * Determine if two inventory movement entries represent the same manual sync change.
         *
         * @since 4.3.2
         *
         * @param array $entry
         * @param array $existing_entry
         *
         * @return bool
         */
        private function is_duplicate_inventory_movement_entry( array $entry, array $existing_entry ) : bool {

                $entry_context    = isset( $entry['context'] ) ? (string) $entry['context'] : '';
                $existing_context = isset( $existing_entry['context'] ) ? (string) $existing_entry['context'] : '';

                if ( 'manual_sync' !== $entry_context || 'manual_sync' !== $existing_context ) {
                        return false;
                }

                $entry_source    = isset( $entry['order_source'] ) ? (string) $entry['order_source'] : '';
                $existing_source = isset( $existing_entry['order_source'] ) ? (string) $existing_entry['order_source'] : '';

                if ( strpos( $entry_source, 'manual_sync' ) !== 0 || strpos( $existing_source, 'manual_sync' ) !== 0 ) {
                        return false;
                }

                if ( (int) ( $entry['wc_product_id'] ?? 0 ) !== (int) ( $existing_entry['wc_product_id'] ?? 0 ) ) {
                        return false;
                }

                $timestamp          = isset( $entry['timestamp'] ) ? (int) $entry['timestamp'] : 0;
                $existing_timestamp = isset( $existing_entry['timestamp'] ) ? (int) $existing_entry['timestamp'] : 0;

                if ( 0 === $timestamp || 0 === $existing_timestamp ) {
                        return false;
                }

                if ( abs( $timestamp - $existing_timestamp ) > 5 * MINUTE_IN_SECONDS ) {
                        return false;
                }

                $fields = [ 'event_type', 'sku', 'quantity', 'sync_type', 'status', 'order_id', 'order_trigger' ];

                foreach ( $fields as $field ) {
                        $entry_value    = $entry[ $field ] ?? null;
                        $existing_value = $existing_entry[ $field ] ?? null;

                        if ( is_numeric( $entry_value ) || is_numeric( $existing_value ) ) {
                                if ( (float) $entry_value !== (float) $existing_value ) {
                                        return false;
                                }

                                continue;
                        }

                        if ( (string) $entry_value !== (string) $existing_value ) {
                                return false;
                        }
                }

                $entry_from    = isset( $entry['warehouses']['from']['id'] ) ? (string) $entry['warehouses']['from']['id'] : '';
                $existing_from = isset( $existing_entry['warehouses']['from']['id'] ) ? (string) $existing_entry['warehouses']['from']['id'] : '';
                $entry_to      = isset( $entry['warehouses']['to']['id'] ) ? (string) $entry['warehouses']['to']['id'] : '';
                $existing_to   = isset( $existing_entry['warehouses']['to']['id'] ) ? (string) $existing_entry['warehouses']['to']['id'] : '';

                if ( $entry_from !== $existing_from || $entry_to !== $existing_to ) {
                        return false;
                }

                return true;
        }

       /**
        * Append a single inventory movement entry.
        *
        * @since 4.3.1
        */
       private function append_inventory_movement_entry( array $entry ) : void {
               $this->append_inventory_movement_entries( [ $entry ] );
       }

       /**
        * Normalize an inventory movement entry ensuring expected keys.
        *
        * @since 4.3.1
        */
       private function normalize_inventory_movement_entry( $entry ) : array {

               $entry = $this->normalize_manual_sync_data( $entry );

               if ( ! is_array( $entry ) ) {
                       return [];
               }

               $timestamp = null;

               if ( array_key_exists( 'timestamp', $entry ) ) {
                       $parsed_timestamp = $this->parse_inventory_movement_timestamp( $entry['timestamp'] );

                       if ( null !== $parsed_timestamp ) {
                               $timestamp = $this->normalize_inventory_movement_timestamp( $parsed_timestamp );
                       }
               }

               if ( null === $timestamp ) {
                       $timestamp = current_time( 'timestamp', true );
               }
               $event     = isset( $entry['event_type'] ) && in_array( $entry['event_type'], [ 'ingreso', 'egreso' ], true ) ? $entry['event_type'] : 'egreso';
               $status    = isset( $entry['status'] ) && in_array( $entry['status'], [ 'pending', 'success', 'error' ], true ) ? $entry['status'] : 'pending';
               $sync_type = isset( $entry['sync_type'] ) && in_array( $entry['sync_type'], [ 'global', 'product' ], true ) ? $entry['sync_type'] : 'global';

               $defaults = [
                       'timestamp'        => $timestamp,
                       'order_id'         => isset( $entry['order_id'] ) ? (int) $entry['order_id'] : 0,
                       'event_type'       => $event,
                       'product_id'       => isset( $entry['product_id'] ) ? (string) $entry['product_id'] : '',
                       'wc_product_id'    => isset( $entry['wc_product_id'] ) ? (int) $entry['wc_product_id'] : 0,
                       'sku'              => isset( $entry['sku'] ) ? (string) $entry['sku'] : '',
                       'product_name'     => isset( $entry['product_name'] ) ? (string) $entry['product_name'] : '',
                       'quantity'         => isset( $entry['quantity'] ) ? (float) $entry['quantity'] : 0.0,
                       'warehouses'       => [
                               'from' => [
                                       'id'             => isset( $entry['warehouses']['from']['id'] ) ? (string) $entry['warehouses']['from']['id'] : '',
                                       'code'           => isset( $entry['warehouses']['from']['code'] ) ? (string) $entry['warehouses']['from']['code'] : '',
                                       'label'          => isset( $entry['warehouses']['from']['label'] ) ? (string) $entry['warehouses']['from']['label'] : '',
                                       'location_id'    => isset( $entry['warehouses']['from']['location_id'] ) ? (string) $entry['warehouses']['from']['location_id'] : '',
                                       'location_label' => isset( $entry['warehouses']['from']['location_label'] ) ? (string) $entry['warehouses']['from']['location_label'] : '',
                                       'mapped'         => isset( $entry['warehouses']['from']['mapped'] ) ? (bool) $entry['warehouses']['from']['mapped'] : false,
                               ],
                               'to'   => [
                                       'id'             => isset( $entry['warehouses']['to']['id'] ) ? (string) $entry['warehouses']['to']['id'] : '',
                                       'code'           => isset( $entry['warehouses']['to']['code'] ) ? (string) $entry['warehouses']['to']['code'] : '',
                                       'label'          => isset( $entry['warehouses']['to']['label'] ) ? (string) $entry['warehouses']['to']['label'] : '',
                                       'location_id'    => isset( $entry['warehouses']['to']['location_id'] ) ? (string) $entry['warehouses']['to']['location_id'] : '',
                                       'location_label' => isset( $entry['warehouses']['to']['location_label'] ) ? (string) $entry['warehouses']['to']['location_label'] : '',
                                       'mapped'         => isset( $entry['warehouses']['to']['mapped'] ) ? (bool) $entry['warehouses']['to']['mapped'] : false,
                               ],
                       ],
                       'order_status'     => isset( $entry['order_status'] ) ? (string) $entry['order_status'] : '',
                       'order_trigger'    => isset( $entry['order_trigger'] ) ? (string) $entry['order_trigger'] : '',
                       'context'          => isset( $entry['context'] ) ? (string) $entry['context'] : '',
                       'order_source'     => isset( $entry['order_source'] ) ? (string) $entry['order_source'] : '',
                       'reason'           => isset( $entry['reason'] ) ? (string) $entry['reason'] : '',
                       'order_item_id'    => isset( $entry['order_item_id'] ) ? (int) $entry['order_item_id'] : 0,
                       'reference'        => isset( $entry['reference'] ) ? (string) $entry['reference'] : '',
                       'error_message'    => isset( $entry['error_message'] ) ? (string) $entry['error_message'] : '',
                       'status'           => $status,
                       'sync_type'        => $sync_type,
                       'location'         => [
                               'id'    => isset( $entry['location']['id'] ) ? (string) $entry['location']['id'] : '',
                               'label' => isset( $entry['location']['label'] ) ? (string) $entry['location']['label'] : '',
                       ],
               ];

               return $defaults;
       }

       /**
        * Parse a timestamp value into a UNIX timestamp, accepting numeric strings and date strings.
        *
        * @since 4.1.74
        */
       private function parse_inventory_movement_timestamp( $value ) : ?int {
               if ( is_numeric( $value ) ) {
                       $numeric_value = (int) $value;

                       if ( $numeric_value > 20000000000 ) { // millisecond epoch safeguard (~Sat May 18 2603)
                               return (int) round( $numeric_value / 1000 );
                       }

                       return $numeric_value;
               }

               if ( is_string( $value ) ) {
                       $value = trim( $value );

                       if ( '' === $value ) {
                               return null;
                       }

                       $datetime = date_create( $value, wp_timezone() );

                       if ( $datetime instanceof DateTimeInterface ) {
                               return $datetime->getTimestamp();
                       }

                       $fallback = strtotime( $value );

                       if ( false !== $fallback ) {
                               return (int) $fallback;
                       }
               }

               return null;
       }

       /**
        * Enrich inventory movement data with friendly location labels for PDF output.
        *
        * @since 4.1.15
        */
       private function hydrate_inventory_movement_for_report( array $movement ) : array {
               $location_id    = isset( $movement['location']['id'] ) ? (string) $movement['location']['id'] : '';
               $location_label = isset( $movement['location']['label'] ) ? (string) $movement['location']['label'] : '';

               foreach ( [ 'from', 'to' ] as $side ) {
                       if ( ! isset( $movement['warehouses'][ $side ] ) || ! is_array( $movement['warehouses'][ $side ] ) ) {
                               $movement['warehouses'][ $side ] = [];
                       }

                       $warehouse_location_id = isset( $movement['warehouses'][ $side ]['location_id'] )
                               ? (string) $movement['warehouses'][ $side ]['location_id']
                               : '';
                       $warehouse_location_label = isset( $movement['warehouses'][ $side ]['location_label'] )
                               ? (string) $movement['warehouses'][ $side ]['location_label']
                               : '';
                       $warehouse_label = isset( $movement['warehouses'][ $side ]['label'] )
                               ? (string) $movement['warehouses'][ $side ]['label']
                               : '';
                       $warehouse_code = isset( $movement['warehouses'][ $side ]['code'] )
                               ? (string) $movement['warehouses'][ $side ]['code']
                               : '';
                       $warehouse_id = isset( $movement['warehouses'][ $side ]['id'] )
                               ? (string) $movement['warehouses'][ $side ]['id']
                               : '';

                       if ( '' === $warehouse_code && '' !== $warehouse_id ) {
                               $warehouse_code = $warehouse_id;
                               $movement['warehouses'][ $side ]['code'] = $warehouse_code;
                       }

                       $resolved_label = $this->resolve_warehouse_location_label(
                               $warehouse_code,
                               $warehouse_location_id
                       );

                       if ( '' !== $resolved_label ) {
                               if (
                                       '' === $warehouse_label
                                       || $warehouse_label === $warehouse_code
                                       || $warehouse_label === $warehouse_id
                               ) {
                                       $warehouse_label = $resolved_label;
                               }

                               if ( '' === $warehouse_location_label ) {
                                       $warehouse_location_label = $resolved_label;
                               }

                               $movement['warehouses'][ $side ]['mapped'] = true;
                       }

                       if ( '' === $warehouse_label && isset( $movement['warehouses'][ $side ]['label'] ) ) {
                               $warehouse_label = (string) $movement['warehouses'][ $side ]['label'];
                       }

                       if ( '' === $warehouse_label && isset( $movement['warehouses'][ $side ]['code'] ) ) {
                               $warehouse_label = (string) $movement['warehouses'][ $side ]['code'];
                       }

                       $movement['warehouses'][ $side ]['label'] = $warehouse_label;

                       if ( ! isset( $movement['warehouses'][ $side ]['location_id'] ) || '' === $movement['warehouses'][ $side ]['location_id'] ) {
                               $movement['warehouses'][ $side ]['location_id'] = $warehouse_location_id;
                       }

                       if ( '' !== $warehouse_location_label ) {
                               $movement['warehouses'][ $side ]['location_label'] = $warehouse_location_label;
                       }
               }

               $location_context = [
                       'label'          => isset( $movement['warehouses']['from']['label'] ) ? (string) $movement['warehouses']['from']['label'] : '',
                       'code'           => isset( $movement['warehouses']['from']['code'] ) ? (string) $movement['warehouses']['from']['code'] : '',
                       'location_id'    => $location_id,
                       'location_label' => $location_label,
               ];

               $movement['location']['label'] = $this->describe_inventory_location_for_note( $location_context );
               $movement['location']['id']    = $location_id;

               return $movement;
       }

       /**
        * Helper to build a normalized inventory movement entry from data.
        *
        * @since 4.3.1
        */
       private function build_inventory_movement_entry( array $data ) : array {
               $data['timestamp'] = isset( $data['timestamp'] ) ? (int) $data['timestamp'] : current_time( 'timestamp', true );

               return $this->normalize_inventory_movement_entry( $data );
       }

       /**
        * Build an inventory entry for manual/global product synchronization.
        *
        * @since 4.3.1
        */
        private function build_manual_sync_inventory_movement_entry( array $product_entry, array $changes, string $sync_scope ) : ?array {

                if ( empty( $changes['stock_updated'] ) ) {
                        return null;
                }

                if ( ! isset( $product_entry['product'] ) || ! is_a( $product_entry['product'], 'WC_Product' ) ) {
                        return null;
                }

                $previous_stock = isset( $changes['previous_stock'] ) ? $changes['previous_stock'] : null;
                $new_stock      = isset( $changes['new_stock'] ) ? $changes['new_stock'] : null;

                if ( null === $previous_stock || null === $new_stock ) {
                        return null;
                }

                $delta = (float) $new_stock - (float) $previous_stock;

                if ( 0.0 === $delta ) {
                        return null;
                }

                $wc_product = $product_entry['product'];
                $event_type = $delta > 0 ? 'ingreso' : 'egreso';
                $quantity   = abs( $delta );
                $warehouses = $this->resolve_manual_sync_movement_warehouses( $event_type, $sync_scope );

               $scope_reason = 'product' === $sync_scope
                       ? __( 'sincronización manual por producto', $this->plugin_name )
                       : __( 'sincronización manual global', $this->plugin_name );

               return $this->build_inventory_movement_entry( [
                       'event_type'    => $event_type,
                       'product_id'    => isset( $product_entry['id'] ) ? (string) $product_entry['id'] : '',
                       'wc_product_id' => $wc_product->get_id(),
                       'sku'           => (string) $wc_product->get_sku(),
                       'product_name'  => $wc_product->get_name(),
                       'quantity'      => $quantity,
                       'warehouses'    => $warehouses,
                       'context'       => 'manual_sync',
                       'order_source'  => 'manual_sync_' . $sync_scope,
                       'order_trigger' => 'manual_sync',
                       'status'        => 'success',
                       'sync_type'     => in_array( $sync_scope, [ 'global', 'product' ], true ) ? $sync_scope : 'global',
                       'reason'        => $scope_reason,
               ] );
       }

        /**
         * Build inventory movement entries from stored manual sync history updates.
         *
         * @since 4.3.1
         */
        private function build_inventory_movements_from_history_entry( array $history_entry ) : array {

                $updates = isset( $history_entry['updates'] ) && is_array( $history_entry['updates'] ) ? $history_entry['updates'] : [];

                if ( empty( $updates ) ) {
                        return [];
                }

                $timestamp    = $this->resolve_manual_sync_history_timestamp( $history_entry );
                $run_status   = isset( $history_entry['status'] ) ? (string) $history_entry['status'] : 'completed';
                $entry_status = 'failed' === $run_status ? 'error' : 'success';
                $movements    = [];

                foreach ( $updates as $update ) {
                        $update = $this->normalize_manual_sync_data( $update );

                        if ( ! isset( $update['changes']['stock'] ) || ! is_array( $update['changes']['stock'] ) ) {
                                continue;
                        }

                        $stock_change   = $update['changes']['stock'];
                        $previous_stock = isset( $stock_change['previous'] ) ? (float) $stock_change['previous'] : null;
                        $current_stock  = isset( $stock_change['current'] ) ? (float) $stock_change['current'] : null;

                        if ( null === $previous_stock || null === $current_stock ) {
                                continue;
                        }

                        if ( $previous_stock === $current_stock ) {
                                continue;
                        }

                        $delta      = $current_stock - $previous_stock;
                        $event_type = $delta > 0 ? 'ingreso' : 'egreso';

                        $movements[] = $this->build_inventory_movement_entry( [
                                'timestamp'     => $timestamp,
                                'event_type'    => $event_type,
                                'product_id'    => isset( $update['contifico_id'] ) ? (string) $update['contifico_id'] : '',
                                'wc_product_id' => isset( $update['product_id'] ) ? (int) $update['product_id'] : 0,
                                'sku'           => isset( $update['sku'] ) ? (string) $update['sku'] : '',
                                'product_name'  => isset( $update['product_name'] ) ? (string) $update['product_name'] : '',
                                'quantity'      => abs( $delta ),
                                'warehouses'    => $this->resolve_manual_sync_movement_warehouses( $event_type, 'global' ),
                                'context'       => 'manual_sync',
                                'order_source'  => 'manual_sync_history',
                                'order_trigger' => 'manual_sync_summary',
                                'status'        => $entry_status,
                                'sync_type'     => 'global',
                                'reason'        => __( 'resumen de sincronización global', $this->plugin_name ),
                        ] );
                }

                return $movements;
        }

        /**
         * Resolve the timestamp to use for history-derived inventory movements.
         *
         * @since 4.3.1
         */
        private function resolve_manual_sync_history_timestamp( array $history_entry ) : int {

                foreach ( [ 'finished_at', 'started_at', 'saved_at' ] as $field ) {
                        if ( empty( $history_entry[ $field ] ) ) {
                                continue;
                        }

                        $datetime = date_create( (string) $history_entry[ $field ], wp_timezone() );

                        if ( $datetime instanceof DateTimeInterface ) {
                                return $datetime->getTimestamp();
                        }
                }

                return current_time( 'timestamp', true );
        }

        /**
         * Reuse the warehouse labels for manual sync movements.
         *
         * @since 4.3.1
         */
        private function resolve_manual_sync_movement_warehouses( string $event_type, string $sync_scope ) : array {

                $sync_scope       = in_array( $sync_scope, [ 'global', 'product' ], true ) ? $sync_scope : 'global';
                $scope_label      = 'product' === $sync_scope
                        ? __( 'Sincronización por producto', 'woo-contifico' )
                        : __( 'Sincronización global', 'woo-contifico' );
                $contifico_label  = sprintf( __( 'Contífico (%s)', 'woo-contifico' ), $scope_label );
                $woocommerce_node = [ 'id' => 'woocommerce', 'label' => __( 'WooCommerce', 'woo-contifico' ) ];
                $contifico_node   = [ 'id' => 'contifico-sync', 'label' => $contifico_label ];

                $billing_code  = isset( $this->woo_contifico->settings['bodega_facturacion'] )
                        ? (string) $this->woo_contifico->settings['bodega_facturacion']
                        : '';
                $billing_label = isset( $this->woo_contifico->settings['bodega_facturacion_label'] )
                        ? (string) $this->woo_contifico->settings['bodega_facturacion_label']
                        : '';
                $billing_name  = '' !== $billing_label ? $billing_label : $billing_code;

                if ( '' !== $billing_code ) {
                        $billing_node = [
                                'id'            => $billing_code,
                                'code'          => $billing_code,
                                'label'         => $billing_name,
                                'location_id'   => $billing_code,
                                'location_label'=> $billing_name,
                        ];

                        if ( 'ingreso' === $event_type ) {
                                return [
                                        'from' => $billing_node,
                                        'to'   => $woocommerce_node,
                                ];
                        }

                        return [
                                'from' => $woocommerce_node,
                                'to'   => $billing_node,
                        ];
                }

                if ( 'ingreso' === $event_type ) {
                        return [
                                'from' => $contifico_node,
                                'to'   => $woocommerce_node,
                        ];
                }

                return [
                        'from' => $woocommerce_node,
                        'to'   => $contifico_node,
                ];
        }

        /**
         * Resolve the Contífico warehouse context for an order.
         *
         * @since 4.4.0
         *
         * @param WC_Order $order        Order instance.
         * @param string   $default_code Default warehouse code configured in the settings.
         * @param string   $default_id   Contífico identifier for the default warehouse.
         *
         * @return array{id:string,code:string,label:string,location_id:string,location_label:string,mapped:bool}
         */
        private function resolve_order_location_inventory_context( WC_Order $order, string $default_code, string $default_id ) : array {
                return $this->build_default_inventory_context( $default_code, $default_id );
        }

        /**
         * Resolve the Contífico warehouse context for a specific order item.
         *
         * @since 4.4.0
         */
        private function resolve_order_item_location_inventory_context( WC_Order $order, WC_Order_Item $item, string $default_code, string $default_id ) : array {
                $context = $this->build_default_inventory_context( $default_code, $default_id );

                return $this->maybe_apply_preferred_warehouse_context( $context, $order, $item );
        }

        /**
         * Detect whether an order is using the store pickup option.
         *
         * @since 4.4.0
         */
        private function order_has_store_pickup( WC_Order $order ) : bool {
                $order_id = $order->get_id();

                if ( isset( $this->order_pickup_cache[ $order_id ] ) ) {
                        return (bool) $this->order_pickup_cache[ $order_id ];
                }

                $is_pickup = false;
                $shipping_methods = $order->get_shipping_methods();

                if ( ! empty( $shipping_methods ) ) {
                        foreach ( $shipping_methods as $shipping_item ) {
                                if ( ! is_a( $shipping_item, 'WC_Order_Item_Shipping' ) ) {
                                        continue;
                                }

                                $method_id    = strtolower( (string) $shipping_item->get_method_id() );
                                $method_title = '';

                                if ( method_exists( $shipping_item, 'get_method_title' ) ) {
                                        $method_title = strtolower( (string) $shipping_item->get_method_title() );
                                } else {
                                        $method_title = strtolower( (string) $shipping_item->get_name() );
                                }

                                if (
                                        false !== strpos( $method_id, 'local_pickup' )
                                        || false !== strpos( $method_id, 'store_pickup' )
                                        || false !== strpos( $method_id, 'retiro' )
                                        || false !== strpos( $method_id, 'pickup' )
                                        || false !== strpos( $method_title, 'retiro' )
                                        || false !== strpos( $method_title, 'tienda' )
                                        || false !== strpos( $method_title, 'recoger' )
                                ) {
                                        $is_pickup = true;
                                        break;
                                }
                        }
                }

                $this->order_pickup_cache[ $order_id ] = $is_pickup;

                return $is_pickup;
        }

        /**
         * Retrieve the configured list of preferred warehouse codes in order of priority.
         *
         * @since 4.4.0
         */
        private function get_preferred_warehouse_codes() : array {
                if ( null !== $this->preferred_warehouse_codes ) {
                        return $this->preferred_warehouse_codes;
                }

                $codes   = [];
                $options = [ 'bodega', 'bodega_secundaria', 'bodega_terciaria' ];

                foreach ( $options as $setting_key ) {
                        if ( ! isset( $this->woo_contifico->settings[ $setting_key ] ) ) {
                                continue;
                        }

                        $code = strtoupper( trim( (string) $this->woo_contifico->settings[ $setting_key ] ) );

                        if ( '' === $code ) {
                                continue;
                        }

                        if ( in_array( $code, $codes, true ) ) {
                                continue;
                        }

                        $codes[] = $code;
                }

                $this->preferred_warehouse_codes = $codes;

                return $this->preferred_warehouse_codes;
        }

        /**
         * Retrieve the registered warehouse codes that should be shown in items.
         *
         * @since 4.2.3
         *
         * @return string[]
         */
        private function get_item_visible_warehouse_codes() : array {
                $raw_codes = $this->woo_contifico->settings['bodegas_items'] ?? '';

                if ( is_array( $raw_codes ) ) {
                        $keys = array_keys( $raw_codes );
                        $is_assoc = array_keys( $keys ) !== $keys;
                        $raw_codes = $is_assoc ? implode( PHP_EOL, $keys ) : implode( PHP_EOL, $raw_codes );
                }

                $raw_codes = trim( (string) $raw_codes );

                if ( '' === $raw_codes ) {
                        return [];
                }

                $codes = preg_split( '/[\s,;]+/', $raw_codes, -1, PREG_SPLIT_NO_EMPTY );
                $codes = array_map( 'trim', $codes );
                $codes = array_filter( $codes, static function( $code ) {
                        return '' !== $code;
                } );
                $codes = array_map( 'strtoupper', $codes );
                $codes = array_values( array_unique( $codes ) );

                return $codes;
        }

        /**
         * Retrieve and cache the stock by warehouse for a Contífico product ID.
         *
         * @since 4.4.0
         */
        private function get_product_stock_for_preferred_allocation( string $product_id ) : array {
                $product_id = trim( $product_id );

                if ( '' === $product_id ) {
                        return [];
                }

                if ( isset( $this->preferred_warehouse_stock_cache[ $product_id ] ) ) {
                        return $this->preferred_warehouse_stock_cache[ $product_id ];
                }

                $stock = $this->contifico->get_product_stock_by_warehouses( $product_id );

                if ( ! is_array( $stock ) ) {
                        $stock = [];
                }

                $this->preferred_warehouse_stock_cache[ $product_id ] = $stock;

                return $this->preferred_warehouse_stock_cache[ $product_id ];
        }

        /**
         * Persist the origin warehouse code for an order item so refunds/cancellations honor the same source.
         *
         * @since 4.4.0
         */
        private function persist_order_item_origin_warehouse( WC_Order_Item $item, string $warehouse_code ) : void {
                if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
                        return;
                }

                $warehouse_code = strtoupper( trim( $warehouse_code ) );

                if ( '' === $warehouse_code ) {
                        return;
                }

                $current = (string) $item->get_meta( self::ORDER_ITEM_WAREHOUSE_META_KEY, true );

                if ( $current === $warehouse_code ) {
                        return;
                }

                if ( $item->get_id() > 0 ) {
                        $item->update_meta_data( self::ORDER_ITEM_WAREHOUSE_META_KEY, $warehouse_code );
                        $item->save();
                } else {
                        $item->add_meta_data( self::ORDER_ITEM_WAREHOUSE_META_KEY, $warehouse_code, true );
                }
        }

    /**
     * Cache and persist the preferred warehouse allocation breakdown for an order item.
     *
     * @since 4.4.0
     */
    private function store_item_preferred_allocations( WC_Order $order, WC_Order_Item $item, array $allocations ) : void {
            if ( empty( $allocations ) ) {
                    return;
            }

            $filtered = [];

            foreach ( $allocations as $allocation ) {
                    $code     = isset( $allocation['code'] ) ? (string) $allocation['code'] : '';
                    $quantity = isset( $allocation['quantity'] ) ? (float) $allocation['quantity'] : 0.0;

                    if ( '' === $code || $quantity <= 0.0 ) {
                            continue;
                    }

                    $filtered[] = [
                            'code'         => strtoupper( $code ),
                            'warehouse_id' => isset( $allocation['warehouse_id'] ) ? (string) $allocation['warehouse_id'] : '',
                            'quantity'     => $quantity,
                    ];
            }

            if ( empty( $filtered ) ) {
                    return;
            }

            $order_id = $order->get_id();
            $item_id  = $item->get_id();

            if ( ! isset( $this->preferred_item_allocations[ $order_id ] ) ) {
                    $this->preferred_item_allocations[ $order_id ] = [];
            }

            $this->preferred_item_allocations[ $order_id ][ $item_id ] = $filtered;

            if ( $item_id > 0 ) {
                    $item->update_meta_data( self::ORDER_ITEM_ALLOCATION_META_KEY, $filtered );
                    $item->save();
            } else {
                    $item->add_meta_data( self::ORDER_ITEM_ALLOCATION_META_KEY, $filtered, true );
            }
    }

    /**
     * Retrieve the stored preferred allocation map for an order item, hydrating refunds when needed.
     *
     * @since 4.4.0
     */
    private function get_item_preferred_allocations( WC_Order $order, WC_Order_Item $item ) : array {
            $order_id = $order->get_id();
            $item_id  = $item->get_id();

            if ( isset( $this->preferred_item_allocations[ $order_id ][ $item_id ] ) ) {
                    return $this->preferred_item_allocations[ $order_id ][ $item_id ];
            }

            $stored_allocations = $item->get_meta( self::ORDER_ITEM_ALLOCATION_META_KEY, true );

            if ( empty( $stored_allocations ) ) {
                    $refunded_item_id = (int) $item->get_meta( '_refunded_item_id', true );

                    if ( $refunded_item_id > 0 ) {
                            $refunded_item = $order->get_item( $refunded_item_id );

                            if ( $refunded_item instanceof WC_Order_Item ) {
                                    $stored_allocations = $refunded_item->get_meta( self::ORDER_ITEM_ALLOCATION_META_KEY, true );

                                    if ( '' === (string) $item->get_meta( self::ORDER_ITEM_WAREHOUSE_META_KEY, true ) ) {
                                            $refunded_origin = (string) $refunded_item->get_meta( self::ORDER_ITEM_WAREHOUSE_META_KEY, true );

                                            if ( '' !== $refunded_origin ) {
                                                    $item->add_meta_data( self::ORDER_ITEM_WAREHOUSE_META_KEY, $refunded_origin, true );
                                            }
                                    }

                                    if ( ! empty( $stored_allocations ) ) {
                                            $item->add_meta_data( self::ORDER_ITEM_ALLOCATION_META_KEY, $stored_allocations, true );
                                            $item->save();
                                    }
                            }
                    }
            }

            $parsed_allocations = [];

            if ( is_array( $stored_allocations ) ) {
                    foreach ( $stored_allocations as $allocation ) {
                            $code         = isset( $allocation['code'] ) ? (string) $allocation['code'] : '';
                            $warehouse_id = isset( $allocation['warehouse_id'] ) ? (string) $allocation['warehouse_id'] : '';
                            $quantity     = isset( $allocation['quantity'] ) ? (float) $allocation['quantity'] : 0.0;

                            if ( '' === $code || $quantity <= 0.0 ) {
                                    continue;
                            }

                            $parsed_allocations[] = [
                                    'code'         => strtoupper( $code ),
                                    'warehouse_id' => $warehouse_id,
                                    'quantity'     => $quantity,
                            ];
                    }
            }

            if ( empty( $parsed_allocations ) ) {
                    return [];
            }

            $this->preferred_item_allocations[ $order_id ][ $item_id ] = $parsed_allocations;

            return $parsed_allocations;
    }

        private function build_preferred_warehouse_allocations( int $order_id, string $product_id, float $quantity, array $preferred_codes, array $stock_by_warehouse ) : array {
                $allocations = [];
                $needed      = $quantity;

                $product_id = trim( $product_id );

                if ( '' === $product_id ) {
                        return [];
                }

                foreach ( $preferred_codes as $code ) {
                        $warehouse_id = (string) ( $this->contifico->get_id_bodega( $code ) ?? '' );

                        if ( '' === $warehouse_id ) {
                                continue;
                        }

                        if ( ! isset( $stock_by_warehouse[ $warehouse_id ] ) ) {
                                continue;
                        }

                        $available = (float) $stock_by_warehouse[ $warehouse_id ];
                        $allocated = isset( $this->preferred_warehouse_allocations[ $order_id ][ $product_id ][ $warehouse_id ] )
                                ? (float) $this->preferred_warehouse_allocations[ $order_id ][ $product_id ][ $warehouse_id ]
                                : 0.0;

                        $remaining = $available - $allocated;

                        if ( $remaining <= 0.0 ) {
                                continue;
                        }

                        $take = min( $remaining, $needed );

                        if ( $take <= 0.0 ) {
                                continue;
                        }

                        $allocations[] = [
                                'code'         => $code,
                                'warehouse_id' => $warehouse_id,
                                'quantity'     => $take,
                        ];

                        $this->preferred_warehouse_allocations[ $order_id ][ $product_id ][ $warehouse_id ] = $allocated + $take;

                        $needed -= $take;

                        if ( $needed <= 0.0 ) {
                                break;
                        }
                }

                return $allocations;
        }

        private function override_inventory_context_with_warehouse_code( array $context, string $warehouse_code ) : array {
                $warehouse_code = strtoupper( trim( $warehouse_code ) );

                if ( '' === $warehouse_code ) {
                        return $context;
                }

                $warehouse_id = (string) ( $this->contifico->get_id_bodega( $warehouse_code ) ?? '' );

                if ( '' === $warehouse_id ) {
                        return $context;
                }

                $context['id']    = $warehouse_id;
                $context['code']  = $warehouse_code;
                $context['label'] = $warehouse_code;
                $context['mapped']= true;

                return $context;
        }

        /**
         * Apply the preferred warehouse allocation logic to an inventory context.
         *
         * @since 4.4.0
         */
        private function maybe_apply_preferred_warehouse_context( array $context, WC_Order $order, WC_Order_Item $item ) : array {
                if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
                        return $context;
                }

                if ( $this->order_has_store_pickup( $order ) ) {
                        return $context;
                }

                if ( ! empty( $context['mapped'] ) ) {
                        return $context;
                }

                $preferred_codes = $this->get_preferred_warehouse_codes();

                if ( empty( $preferred_codes ) ) {
                        return $context;
                }

                $stored_code = (string) $item->get_meta( self::ORDER_ITEM_WAREHOUSE_META_KEY, true );

                if ( '' === $stored_code ) {
                        $refunded_item_id = (int) $item->get_meta( '_refunded_item_id', true );

                        if ( $refunded_item_id > 0 ) {
                                $refunded_item = $order->get_item( $refunded_item_id );

                                if ( $refunded_item instanceof WC_Order_Item ) {
                                        $stored_code = (string) $refunded_item->get_meta( self::ORDER_ITEM_WAREHOUSE_META_KEY, true );

                                        if ( '' !== $stored_code ) {
                                                $item->add_meta_data( self::ORDER_ITEM_WAREHOUSE_META_KEY, $stored_code, true );
                                                $item->save();
                                        }
                                }
                        }
                }

                $wc_product = $item->get_product();

                if ( ! $wc_product ) {
                        return $context;
                }

                $sku        = (string) $wc_product->get_sku();
                $product_id = $this->contifico->get_product_id( $sku );

                if ( '' === $product_id ) {
                        $product_id = (string) $wc_product->get_meta( self::PRODUCT_ID_META_KEY, true );
                }

                if ( '' === $product_id ) {
                        return $context;
                }

                $quantity = abs( (float) $item->get_quantity() );

                if ( $quantity <= 0 ) {
                        $quantity = 1.0;
                }

                $stock_by_warehouse = $this->get_product_stock_for_preferred_allocation( $product_id );
                $order_id           = $order->get_id();
                $allocations        = [];

                if ( '' !== $stored_code ) {
                        $warehouse_id = (string) ( $this->contifico->get_id_bodega( $stored_code ) ?? '' );

                        if ( '' !== $warehouse_id ) {
                                $this->preferred_warehouse_allocations[ $order_id ][ $product_id ][ $warehouse_id ] = (
                                        isset( $this->preferred_warehouse_allocations[ $order_id ][ $product_id ][ $warehouse_id ] )
                                                ? (float) $this->preferred_warehouse_allocations[ $order_id ][ $product_id ][ $warehouse_id ]
                                                : 0.0
                                ) + $quantity;

                                $allocations = [
                                        [
                                                'code'         => $stored_code,
                                                'warehouse_id' => $warehouse_id,
                                                'quantity'     => $quantity,
                                        ],
                                ];
                        }
                }

                if ( empty( $allocations ) ) {
                        $allocations = $this->build_preferred_warehouse_allocations( $order_id, $product_id, $quantity, $preferred_codes, $stock_by_warehouse );
                }

                if ( empty( $allocations ) ) {
                        return $context;
                }

                $this->store_item_preferred_allocations( $order, $item, $allocations );
                $this->persist_order_item_origin_warehouse( $item, $allocations[0]['code'] );

                return $this->override_inventory_context_with_warehouse_code( $context, $allocations[0]['code'] );
        }

        /**
         * Build a normalized context array for Contífico inventory calls.
         *
         * @since 4.4.0
         */
        private function build_default_inventory_context( string $default_code, string $default_id ) : array {
                return [
                        'id'             => $default_id,
                        'code'           => $default_code,
                        'label'          => $default_code,
                        'location_id'    => '',
                        'location_label' => '',
                        'mapped'         => false,
                ];
        }

        private function build_store_location_line() : string {
                $city          = (string) get_option( 'woocommerce_store_city' );
                $base_location = wc_get_base_location();
                $state         = isset( $base_location['state'] ) ? (string) $base_location['state'] : '';
                $country       = isset( $base_location['country'] ) ? (string) $base_location['country'] : '';

                $line = implode( ', ', array_filter( [ $city, $state ] ) );

                if ( '' !== $country ) {
                        $line = '' !== $line ? $line . ' ' . $country : $country;
                }

                return $line;
        }

        /**
         * Resolve a usable logo path for the order report PDF without relying on tracked binary assets.
         *
         * @since 4.4.0
         */
        private function get_report_logo_path() : string {
                $plugin_dir = plugin_dir_path( dirname( __FILE__ ) );

                $inline_logo = $this->get_pdf_logo_image();

                if ( is_array( $inline_logo ) && ! empty( $inline_logo['data'] ) ) {
                        return '@' . $inline_logo['data'];
                }

                $remote_logo_url = 'https://www.adams.com.ec/wp-content/uploads/2020/11/adam-.png';

                $remote_path = $this->download_remote_logo( $remote_logo_url );

                if ( $this->is_inline_logo_source( $remote_path ) ) {
                        return $remote_path;
                }

                if ( '' !== $remote_path && file_exists( $remote_path ) ) {
                        return $remote_path;
                }

                $preferred_logo = $plugin_dir . 'assets/adams-logo.png';

                if ( file_exists( $preferred_logo ) ) {
                        return $preferred_logo;
                }

                $embedded_candidates = array(
                        $plugin_dir . 'assets/adams-logo.base64.txt',
                        $plugin_dir . 'assets/contifico-logo.base64.txt',
                );

                foreach ( $embedded_candidates as $embedded_logo ) {
                        if ( ! file_exists( $embedded_logo ) ) {
                                continue;
                        }

                        $encoded = trim( (string) file_get_contents( $embedded_logo ) );

                        if ( '' === $encoded || ! $this->is_valid_image_bytes( (string) base64_decode( $encoded, true ) ) ) {
                                continue;
                        }

                        $binary    = base64_decode( $encoded, true );
                        $temp_path = $this->get_temp_logo_path();

                        if ( '' !== $temp_path ) {
                                $result = @file_put_contents( $temp_path, $binary );

                                if ( false !== $result && $this->is_valid_image_file( $temp_path ) ) {
                                        return $temp_path;
                                }
                        }

                        return '@' . $binary;
                }

                $fallback_logo = $plugin_dir . 'assets/contifico-logo.png';

                if ( file_exists( $fallback_logo ) ) {
                        return $fallback_logo;
                }

                $fallback_logo = $plugin_dir . 'assets/contifico-logo.png';

                if ( file_exists( $fallback_logo ) ) {
                        return $fallback_logo;
                }

                return '';
        }

        /**
         * Download a remote logo and persist it to a writable location.
         *
         * @since 4.4.0
         */
        private function download_remote_logo( $logo_url ) : string {
                if ( '' === (string) $logo_url ) {
                        return '';
                }

                $temp_path = $this->get_temp_logo_path();

                if ( '' === $temp_path ) {
                        return '';
                }

                if ( file_exists( $temp_path ) && $this->is_valid_image_file( $temp_path ) ) {
                        return $temp_path;
                }

                $response = wp_remote_get( $logo_url, array( 'timeout' => 15 ) );

                if ( is_wp_error( $response ) ) {
                        return '';
                }

                $code = (int) wp_remote_retrieve_response_code( $response );
                $body = (string) wp_remote_retrieve_body( $response );

                if ( 200 !== $code || '' === $body || ! $this->is_valid_image_bytes( $body ) ) {
                        return '';
                }

                $written = @file_put_contents( $temp_path, $body );

                if ( false === $written || ! $this->is_valid_image_file( $temp_path ) ) {
                        if ( file_exists( $temp_path ) ) {
                                @unlink( $temp_path );
                        }

                        return '@' . $body;
                }

                return $temp_path;
        }

        /**
         * Confirm that a stored image can be read by getimagesize.
         *
         * @since 4.4.3
         */
        private function is_valid_image_file( string $path ) : bool {
                if ( '' === $path || ! file_exists( $path ) || ! is_readable( $path ) ) {
                        return false;
                }

                return $this->is_valid_image_bytes( (string) file_get_contents( $path ) );
        }

        /**
         * Confirm that a raw string represents a valid image.
         *
         * @since 4.4.3
         */
        private function is_valid_image_bytes( string $content ) : bool {
                if ( '' === $content ) {
                        return false;
                }

                $image_info = @getimagesizefromstring( $content );

                return is_array( $image_info ) && ! empty( $image_info[0] ) && ! empty( $image_info[1] );
        }

        /**
         * Determine whether the logo source uses inline image data for FPDF.
         *
         * @since 4.4.3
         */
        private function is_inline_logo_source( string $source ) : bool {
                return 0 === strpos( $source, '@' );
        }

        /**
         * Provide a writable path for the decoded logo image.
         *
         * @since 4.4.0
         */
        private function get_temp_logo_path() : string {
                $upload_dir = wp_upload_dir();
                $base_dir   = isset( $upload_dir['basedir'] ) ? (string) $upload_dir['basedir'] : '';
                $temp_dir   = '';

                if ( '' !== $base_dir ) {
                        $candidate = trailingslashit( $base_dir ) . 'contifico';

                        if ( ! is_dir( $candidate ) ) {
                                wp_mkdir_p( $candidate );
                        }

                        if ( is_dir( $candidate ) && is_writable( $candidate ) ) {
                                $temp_dir = $candidate;
                        }
                }

                if ( '' === $temp_dir ) {
                        $fallback = sys_get_temp_dir();

                        if ( is_writable( $fallback ) ) {
                                $temp_dir = $fallback;
                        }
                }

                if ( '' === $temp_dir ) {
                        return '';
                }

                return rtrim( $temp_dir, '/\\' ) . '/adams-logo.png';
        }

        private function build_shipping_city_line( WC_Order $order ) : string {
                $city    = (string) $order->get_shipping_city();
                $state   = (string) $order->get_shipping_state();
                $country = (string) $order->get_shipping_country();

                if ( '' === $city && '' === $state && '' === $country ) {
                        $city    = (string) $order->get_billing_city();
                        $state   = (string) $order->get_billing_state();
                        $country = (string) $order->get_billing_country();
                }

                $line = implode( ', ', array_filter( [ $city, $state ] ) );

                if ( '' !== $country ) {
                        $line = '' !== $line ? $line . ' ' . $country : $country;
                }

                return $line;
        }

        private function build_item_attribute_lines( WC_Order_Item_Product $item ) : array {
                $lines = [];
                $meta  = $item->get_formatted_meta_data( '', false );

                foreach ( $meta as $meta_item ) {
                        $label = isset( $meta_item->display_key ) ? wp_strip_all_tags( (string) $meta_item->display_key ) : '';
                        $value = isset( $meta_item->display_value ) ? wp_strip_all_tags( (string) $meta_item->display_value ) : '';

                        if ( '' === $value || '' === $label || '_' === substr( $label, 0, 1 ) ) {
                                continue;
                        }

                        $lines[] = sprintf( '%s: %s', $label, $value );
                }

                return $lines;
        }

        /**
         * Build a human readable label for inventory notes based on the context.
         *
        * @since 4.4.0
         */
       private function describe_inventory_location_for_note( array $context ) : string {
                $location_label = isset( $context['location_label'] ) ? (string) $context['location_label'] : '';
                $location_id    = isset( $context['location_id'] ) ? (string) $context['location_id'] : '';

                $warehouse_code = isset( $context['code'] ) ? (string) $context['code'] : '';

                if ( '' === $location_label || $warehouse_code === $location_label ) {
                        $fallback_label = $this->resolve_warehouse_location_label( $warehouse_code, $location_id );

                        if ( '' !== $fallback_label ) {
                                $location_label = $fallback_label;
                        }
                }

                if ( '' !== $location_label ) {
                        return $location_label;
                }

                $warehouse_label = isset( $context['label'] ) ? (string) $context['label'] : '';

                if ( '' !== $warehouse_label ) {
                        return $warehouse_label;
                }

                return __( 'Ubicación predeterminada', 'woo-contifico' );
        }

       private function format_warehouse_label_with_code( array $warehouse ) : string {
               $label          = isset( $warehouse['label'] ) ? (string) $warehouse['label'] : '';
               $code           = isset( $warehouse['code'] ) ? (string) $warehouse['code'] : '';
               $id             = isset( $warehouse['id'] ) ? (string) $warehouse['id'] : '';
               $location_id    = isset( $warehouse['location_id'] ) ? (string) $warehouse['location_id'] : '';
               $location_label = isset( $warehouse['location_label'] ) ? (string) $warehouse['location_label'] : '';
               $is_mapped      = isset( $warehouse['mapped'] ) ? (bool) $warehouse['mapped'] : false;

               $contifico_label = $this->resolve_contifico_warehouse_label( $code, $id );

               if ( '' !== $contifico_label ) {
                       $label = $contifico_label;
               } elseif ( '' !== $location_label ) {
                       if ( '' === $label ) {
                               $label = $location_label;
                       } elseif ( $is_mapped && ( $label === $code || $label === $id ) ) {
                               $label = $location_label;
                       }
               }

               if ( '' !== $code ) {
                       $mapped_label = $this->resolve_warehouse_location_label( $code, $location_id );

                       if (
                               '' !== $mapped_label
                               && ( '' === $label || $label === $code || $label === $id )
                       ) {
                               $label = $mapped_label;
                       }
               }

               if ( '' === $label ) {
                       $label = '' !== $code ? $code : $id;
               }

               if ( '' === $label ) {
                       return '';
               }

               if ( '' !== $code && false === stripos( $label, '(' . $code . ')' ) ) {
                       return sprintf( '%s (%s)', $label, $code );
               }

               return $label;
       }

       private function resolve_contifico_warehouse_label( string $code, string $id ) : string {
               $code = trim( $code );
               $id   = trim( $id );

               if ( '' !== $code && method_exists( $this->contifico, 'get_warehouse_label_by_code' ) ) {
                       $label = (string) $this->contifico->get_warehouse_label_by_code( $code );

                       if ( '' !== $label ) {
                               return $label;
                       }
               }

               if ( '' !== $id && method_exists( $this->contifico, 'get_warehouse_label_by_id' ) ) {
                       $label = (string) $this->contifico->get_warehouse_label_by_id( $id );

                       if ( '' !== $label ) {
                               return $label;
                       }
               }

               return '';
       }

       /**
        * Format warehouse labels ensuring origin and destination stay distinct.
        *
        * @since 4.1.20
        */
       private function format_distinct_warehouse_labels( array $from, array $to ) : array {
               $from_label = $this->format_warehouse_label_with_code( $from );
               $to_label   = $this->format_warehouse_label_with_code( $to );

               if ( '' === $from_label && '' === $to_label ) {
                       return [ 'from' => '', 'to' => '' ];
               }

               if ( $from_label !== $to_label ) {
                       return [ 'from' => $from_label, 'to' => $to_label ];
               }

               $from_code = isset( $from['code'] ) ? (string) $from['code'] : '';
               $to_code   = isset( $to['code'] ) ? (string) $to['code'] : '';
               $from_id   = isset( $from['id'] ) ? (string) $from['id'] : '';
               $to_id     = isset( $to['id'] ) ? (string) $to['id'] : '';

               $from_suffix = '' !== $from_code ? $from_code : $from_id;
               $to_suffix   = '' !== $to_code ? $to_code : $to_id;

               if ( '' !== $from_suffix ) {
                       $from_label = sprintf( '%s (%s)', $from_label, $from_suffix );
               }

               if ( '' !== $to_suffix && $to_suffix !== $from_suffix ) {
                       $to_label = sprintf( '%s (%s)', $to_label, $to_suffix );
               } elseif ( '' !== $to_suffix ) {
                       $to_label = sprintf( '%s (%s)', $to_label, $to_suffix ?: $to_label );
               }

               return [ 'from' => $from_label, 'to' => $to_label ];
       }

       /**
        * Describe why a specific inventory movement occurred.
        *
        * @since 4.1.27
        */
       private function describe_inventory_movement_reason_for_report( array $movement ) : string {
               $reason = isset( $movement['reason'] ) ? trim( (string) $movement['reason'] ) : '';

               if ( '' !== $reason ) {
                       return $reason;
               }

               $context      = isset( $movement['context'] ) ? (string) $movement['context'] : '';
               $order_source = isset( $movement['order_source'] ) ? (string) $movement['order_source'] : '';
               $order_trigger = isset( $movement['order_trigger'] ) ? (string) $movement['order_trigger'] : '';
               $order_id     = isset( $movement['order_id'] ) ? (int) $movement['order_id'] : 0;

               if ( 'manual_sync' === $context || 0 === strpos( $order_source, 'manual_sync' ) ) {
                       $scope = str_replace( 'manual_sync_', '', $order_source );

                       if ( 'product' === $scope ) {
                               return __( 'sincronización manual por producto', $this->plugin_name );
                       }

                       return __( 'sincronización manual de inventario', $this->plugin_name );
               }

               if (
                       'woocommerce_restore_order_stock' === $order_trigger
                       || 'woocommerce_order_refunded' === $order_trigger
                       || 'restore' === $context
               ) {
                       return $order_id
                               ? sprintf( __( 'reintegro de stock de la orden #%d', $this->plugin_name ), $order_id )
                               : __( 'reintegro de stock de la orden', $this->plugin_name );
               }

               if (
                       'transfer' === $context
                       || 'woocommerce_reduce_order_stock' === $order_trigger
                       || 'order' === $order_source
               ) {
                       return $order_id
                               ? sprintf( __( 'despacho de la orden #%d', $this->plugin_name ), $order_id )
                               : __( 'despacho de pedido en línea', $this->plugin_name );
               }

               return '';
       }

        /**
         * Group order items by their resolved location context.
         *
         * @since 4.4.0
         */
        private function group_order_items_by_location_context( WC_Order $order, array $items, string $default_code, string $default_id ) : array {
                $groups = [];

                foreach ( $items as $item ) {
                        if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
                                continue;
                        }

                        $wc_product = $item->get_product();

                        if ( ! $wc_product ) {
                                continue;
                        }

                        $context   = $this->resolve_order_item_location_inventory_context( $order, $item, $default_code, $default_id );
                        $allocations = $this->get_item_preferred_allocations( $order, $item );
                        $group_key = $context['id'] ?: $context['code'];

                        $item_quantity = (float) $item->get_quantity();

                        if ( $item_quantity <= 0 ) {
                                $item_quantity = 1.0;
                        }

                        if ( ! empty( $allocations ) ) {
                                foreach ( $allocations as $allocation ) {
                                        $allocation_context = $this->override_inventory_context_with_warehouse_code( $context, $allocation['code'] );
                                        $allocation_quantity = isset( $allocation['quantity'] ) ? (float) $allocation['quantity'] : $item_quantity;

                                        $group_key = $allocation_context['id'] ?: $allocation_context['code'];

                                        if ( '' === $group_key ) {
                                                $group_key = 'default';
                                        }

                                        if ( ! isset( $groups[ $group_key ] ) ) {
                                                $groups[ $group_key ] = [
                                                        'context'         => $allocation_context,
                                                        'items'           => [],
                                                        'item_contexts'   => [],
                                                        'item_quantities' => [],
                                                        'locations'       => [],
                                                ];
                                        }

                                        $groups[ $group_key ]['items'][]           = $item;
                                        $groups[ $group_key ]['item_contexts'][]   = $allocation_context;
                                        $groups[ $group_key ]['item_quantities'][] = $allocation_quantity;

                                        $location_id    = '' !== $allocation_context['location_id'] ? $allocation_context['location_id'] : sprintf( 'default-%s', $group_key );
                                        $location_label = '' !== $allocation_context['location_label'] ? $allocation_context['location_label'] : $this->describe_inventory_location_for_note( $allocation_context );

                                        $groups[ $group_key ]['locations'][ $location_id ] = $location_label;
                                }

                                continue;
                        }

                        if ( '' === $group_key ) {
                                $group_key = 'default';
                        }

                        if ( ! isset( $groups[ $group_key ] ) ) {
                                $groups[ $group_key ] = [
                                        'context'         => $context,
                                        'items'           => [],
                                        'item_contexts'   => [],
                                        'item_quantities' => [],
                                        'locations'       => [],
                                ];
                        }

                        $groups[ $group_key ]['items'][]                    = $item;
                        $groups[ $group_key ]['item_contexts'][]            = $context;
                        $groups[ $group_key ]['item_quantities'][]         = $item_quantity;

                        $location_id    = '' !== $context['location_id'] ? $context['location_id'] : sprintf( 'default-%s', $group_key );
                        $location_label = '' !== $context['location_label'] ? $context['location_label'] : $this->describe_inventory_location_for_note( $context );

                        $groups[ $group_key ]['locations'][ $location_id ] = $location_label;
                }

                foreach ( $groups as &$group ) {
                        $group['context']['location_label'] = $this->summarize_group_location_labels( $group['locations'], $group['context'] );

                        if ( count( $group['locations'] ) > 1 ) {
                                $group['context']['location_id'] = 'mixed';
                        }
                }
                unset( $group );

                return $groups;
        }

        /**
         * Group restore items using the same warehouse allocations recorded during the transfer.
         *
         * @since 4.1.63
         */
        private function group_restore_items_from_movements( WC_Order $order, array $items, string $default_code, string $default_id, string $default_origin_code, string $default_origin_id ) : array {
                $order_id      = $order->get_id();
                $items_by_id   = [];
                $item_ids      = [];
                $groups        = [];
                $default_ctx   = $this->build_default_inventory_context( $default_code, $default_id );

                foreach ( $items as $item ) {
                        if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
                                continue;
                        }

                        $items_by_id[ $item->get_id() ] = $item;
                        $item_ids[]                      = $item->get_id();
                }

                $movements = array_filter(
                        $this->get_inventory_movements_storage(),
                        static function ( array $movement ) use ( $order_id, $item_ids ) : bool {
                                $movement_order_id = isset( $movement['order_id'] ) ? (int) $movement['order_id'] : 0;
                                $order_item_id     = isset( $movement['order_item_id'] ) ? (int) $movement['order_item_id'] : 0;
                                $context           = isset( $movement['context'] ) ? (string) $movement['context'] : '';
                                $status            = isset( $movement['status'] ) ? (string) $movement['status'] : '';
                                $event_type        = isset( $movement['event_type'] ) ? (string) $movement['event_type'] : '';

                                if ( $movement_order_id !== $order_id || 'transfer' !== $context ) {
                                        return false;
                                }

                                if ( 'success' !== $status || 'egreso' !== $event_type ) {
                                        return false;
                                }

                                if ( ! empty( $item_ids ) && ! in_array( $order_item_id, $item_ids, true ) ) {
                                        return false;
                                }

                                return $order_item_id > 0;
                        }
                );

                if ( empty( $movements ) ) {
                        return [];
                }

                foreach ( $movements as $movement ) {
                        $order_item_id = (int) ( $movement['order_item_id'] ?? 0 );

                        if ( ! isset( $items_by_id[ $order_item_id ] ) ) {
                                continue;
                        }

                        $item      = $items_by_id[ $order_item_id ];
                        $quantity  = isset( $movement['quantity'] ) ? (float) $movement['quantity'] : 0.0;
                        $group_ctx = $default_ctx;

                        if ( $quantity <= 0.0 ) {
                                continue;
                        }

                        $from_warehouse = isset( $movement['warehouses']['from'] ) && is_array( $movement['warehouses']['from'] )
                                ? $movement['warehouses']['from']
                                : [];

                        $warehouse_code = isset( $from_warehouse['code'] ) ? (string) $from_warehouse['code'] : '';
                        $warehouse_id   = isset( $from_warehouse['id'] ) ? (string) $from_warehouse['id'] : '';
                        $location_id    = isset( $from_warehouse['location_id'] ) ? (string) $from_warehouse['location_id'] : '';
                        $location_label = isset( $from_warehouse['location_label'] ) ? (string) $from_warehouse['location_label'] : '';
                        $origin_ctx     = $this->build_default_inventory_context( $default_origin_code, $default_origin_id );
                        $origin_from    = isset( $movement['warehouses']['to'] ) && is_array( $movement['warehouses']['to'] ) ? $movement['warehouses']['to'] : [];
                        $origin_code    = isset( $origin_from['code'] ) ? (string) $origin_from['code'] : '';
                        $origin_id      = isset( $origin_from['id'] ) ? (string) $origin_from['id'] : '';
                        $origin_loc_id  = isset( $origin_from['location_id'] ) ? (string) $origin_from['location_id'] : '';
                        $origin_loc_lbl = isset( $origin_from['location_label'] ) ? (string) $origin_from['location_label'] : '';

                        if ( '' !== $origin_code || '' !== $origin_id ) {
                                $origin_ctx = $this->override_inventory_context_with_warehouse_code( $origin_ctx, $origin_code );

                                if ( '' === $origin_ctx['id'] && '' !== $origin_id ) {
                                        $origin_ctx['id'] = $origin_id;
                                }

                                if ( '' === $origin_ctx['code'] && '' !== $origin_code ) {
                                        $origin_ctx['code'] = $origin_code;
                                }
                        }

                        if ( '' !== $origin_loc_id ) {
                                $origin_ctx['location_id'] = $origin_loc_id;
                        }

                        if ( '' !== $origin_loc_lbl ) {
                                $origin_ctx['location_label'] = $origin_loc_lbl;
                        }

                        if ( '' === $origin_ctx['label'] && '' !== $origin_ctx['code'] ) {
                                $origin_ctx['label'] = $origin_ctx['code'];
                        }

                        if ( '' !== $warehouse_code || '' !== $warehouse_id ) {
                                $group_ctx = $this->override_inventory_context_with_warehouse_code( $group_ctx, $warehouse_code );

                                if ( '' === $group_ctx['id'] && '' !== $warehouse_id ) {
                                        $group_ctx['id'] = $warehouse_id;
                                }

                                if ( '' === $group_ctx['code'] && '' !== $warehouse_code ) {
                                        $group_ctx['code'] = $warehouse_code;
                                }
                        }

                        if ( '' !== $location_id ) {
                                $group_ctx['location_id'] = $location_id;
                        }

                        if ( '' !== $location_label ) {
                                $group_ctx['location_label'] = $location_label;
                        }

                        if ( '' === $group_ctx['label'] && '' !== $group_ctx['code'] ) {
                                $group_ctx['label'] = $group_ctx['code'];
                        }

                        $group_key = $group_ctx['id'] ?: $group_ctx['code'];

                        if ( '' === $group_key ) {
                                $group_key = 'default';
                        }

                        if ( ! isset( $groups[ $group_key ] ) ) {
                                $groups[ $group_key ] = [
                                        'context'         => $group_ctx,
                                        'items'           => [],
                                        'item_contexts'   => [],
                                        'item_quantities' => [],
                                        'locations'       => [],
                                        'origin_contexts' => [],
                                        'origin_locations' => [],
                                ];
                        }

                        $groups[ $group_key ]['items'][]           = $item;
                        $groups[ $group_key ]['item_contexts'][]   = $group_ctx;
                        $groups[ $group_key ]['item_quantities'][] = $quantity;
                        $groups[ $group_key ]['origin_contexts'][] = $origin_ctx;

                        $location_id    = '' !== $group_ctx['location_id'] ? $group_ctx['location_id'] : sprintf( 'default-%s', $group_key );
                        $location_label = '' !== $group_ctx['location_label']
                                ? $group_ctx['location_label']
                                : $this->describe_inventory_location_for_note( $group_ctx );

                        $groups[ $group_key ]['locations'][ $location_id ] = $location_label;

                        $origin_location_id = '' !== $origin_ctx['location_id'] ? $origin_ctx['location_id'] : sprintf( 'default-origin-%s', $group_key );
                        $origin_location_label = '' !== $origin_ctx['location_label']
                                ? $origin_ctx['location_label']
                                : $this->describe_inventory_location_for_note( $origin_ctx );

                        $groups[ $group_key ]['origin_locations'][ $origin_location_id ] = $origin_location_label;
                }

                foreach ( $groups as &$group ) {
                        $group['context']['location_label'] = $this->summarize_group_location_labels( $group['locations'], $group['context'] );

                        if ( count( $group['locations'] ) > 1 ) {
                                $group['context']['location_id'] = 'mixed';
                        }

                        $origin_contexts = array_values( $group['origin_contexts'] );
                        $origin_ctx      = ! empty( $origin_contexts ) ? $origin_contexts[0] : $this->build_default_inventory_context( $default_origin_code, $default_origin_id );

                        $origin_ctx['location_label'] = $this->summarize_group_location_labels( $group['origin_locations'], $origin_ctx );

                        if ( count( $group['origin_locations'] ) > 1 ) {
                                $origin_ctx['location_id'] = 'mixed';
                        }

                        $group['origin_context'] = $origin_ctx;
                }
                unset( $group );

                return $groups;
        }

        /**
         * Summarize location labels belonging to a grouped warehouse context.
         *
         * @since 4.4.0
         */
        private function summarize_group_location_labels( array $locations, array $context ) : string {
                $labels = array_values( array_unique( array_filter( array_map( 'trim', $locations ) ) ) );

                if ( empty( $labels ) ) {
                        return $this->describe_inventory_location_for_note( $context );
                }

                if ( 1 === count( $labels ) ) {
                        return $labels[0];
                }

                $warehouse_label = isset( $context['label'] ) ? (string) $context['label'] : '';

                if ( '' !== $warehouse_label ) {
                        return sprintf(
                                __( '%1$s · Ubicaciones: %2$s', $this->plugin_name ),
                                $warehouse_label,
                                implode( ', ', $labels )
                        );
                }

                return sprintf( __( 'Ubicaciones: %s', $this->plugin_name ), implode( ', ', $labels ) );
        }

        /**
         * Format the annotation displayed when an inventory location is available.
         *
         * @since 4.4.0
         *
         * @param array $context
         * @return string
         */
        private function format_inventory_location_annotation( array $context ) : string {
                return '';
        }

        /**
         * Resolve a user-friendly label for a warehouse code.
         *
         * @since 4.4.0
         */
        private function resolve_warehouse_location_label( string $warehouse_code, string $location_id = '' ) : string {
                $warehouse_code = trim( $warehouse_code );
                $location_id    = trim( $location_id );

                $invoice_label = $this->resolve_invoice_warehouse_label( $warehouse_code, $location_id );

                if ( '' !== $invoice_label ) {
                        return $invoice_label;
                }

                return '';
        }

        /**
         * Resolve a friendly label for the Contífico invoice warehouse.
         *
         * @since 4.1.17
         */
        private function resolve_invoice_warehouse_label( string $warehouse_code, string $location_id ) : string {
                $invoice_code  = isset( $this->woo_contifico->settings['bodega_facturacion'] ) ? trim( (string) $this->woo_contifico->settings['bodega_facturacion'] ) : '';
                $invoice_label = isset( $this->woo_contifico->settings['bodega_facturacion_label'] ) ? trim( (string) $this->woo_contifico->settings['bodega_facturacion_label'] ) : '';

                if ( '' === $invoice_code || '' === $invoice_label ) {
                        return '';
                }

                if ( '' !== $warehouse_code && strcasecmp( $warehouse_code, $invoice_code ) === 0 ) {
                        return $invoice_label;
                }

                if ( '' !== $location_id && strcasecmp( $location_id, $invoice_code ) === 0 ) {
                        return $invoice_label;
                }

                return '';
        }

/**
 * Update a list of entries with the final status returned by the API.
        *
        * @since 4.3.1
        */
       private function finalize_inventory_movement_entries( array $entries, string $status, string $reference = '', string $message = '' ) : array {
               if ( ! in_array( $status, [ 'pending', 'success', 'error' ], true ) ) {
                       $status = 'pending';
               }

               foreach ( $entries as &$entry ) {
                       if ( ! is_array( $entry ) ) {
                               continue;
                       }

                       $entry['status']    = $status;
                       $entry['reference'] = $reference;

                       if ( 'error' === $status ) {
                               $entry['error_message'] = $message;
                       } elseif ( 'success' === $status ) {
                               $entry['error_message'] = '';
                       }
               }
               unset( $entry );

               return array_map( [ $this, 'normalize_inventory_movement_entry' ], $entries );
       }

	/**
	 * Prepare sanitized filters for the inventory movement report.
	 *
	 * @since 4.3.1
	 */
        private function prepare_inventory_movement_filters( array $args ) : array {
                $filters = wp_parse_args( $args, [
                        'start_date' => '',
                        'end_date'   => '',
                        'product_id' => 0,
                        'category_id' => 0,
                        'sku'        => '',
                        'period'     => 'day',
                        'scope'      => 'all',
                        'location'   => '',
                ] );

                $filters['start_date'] = $this->sanitize_inventory_report_date( $filters['start_date'] );
                $filters['end_date']   = $this->sanitize_inventory_report_date( $filters['end_date'] );
                $filters['product_id'] = absint( $filters['product_id'] );
                $filters['category_id'] = absint( $filters['category_id'] );
                $filters['sku']        = strtoupper( sanitize_text_field( (string) $filters['sku'] ) );
                $filters['period']     = in_array( $filters['period'], [ 'day', 'week', 'month' ], true ) ? $filters['period'] : 'day';
                $filters['scope']      = in_array( $filters['scope'], [ 'all', 'global', 'product' ], true ) ? $filters['scope'] : 'all';
                $filters['location']   = sanitize_text_field( (string) $filters['location'] );

                $filters['start_timestamp'] = '' !== $filters['start_date']
                        ? $this->get_utc_timestamp_from_local_date( $filters['start_date'] . ' 00:00:00' )
                        : null;
                $filters['end_timestamp']   = '' !== $filters['end_date']
                        ? $this->get_utc_timestamp_from_local_date( $filters['end_date'] . ' 23:59:59' )
                        : null;

                if ( null !== $filters['start_timestamp'] && null !== $filters['end_timestamp'] && $filters['start_timestamp'] > $filters['end_timestamp'] ) {
                        $filters['end_timestamp'] = $filters['start_timestamp'];
                        $filters['end_date']      = $filters['start_date'];
                }

                return $filters;
        }

        /**
         * Retrieve the product categories for a WooCommerce product id.
         *
         * @since 4.3.1
         */
        private function get_inventory_movement_product_categories( int $product_id ) : array {
                static $cache = [];

                if ( isset( $cache[ $product_id ] ) ) {
                        return $cache[ $product_id ];
                }

                if ( $product_id <= 0 ) {
                        return [];
                }

                $terms = get_the_terms( $product_id, 'product_cat' );

                if ( ( is_wp_error( $terms ) || empty( $terms ) ) && function_exists( 'wc_get_product' ) ) {
                        $product = wc_get_product( $product_id );

                        if ( $product && $product->is_type( 'variation' ) ) {
                                $terms = get_the_terms( $product->get_parent_id(), 'product_cat' );
                        }
                }

                if ( is_wp_error( $terms ) || empty( $terms ) ) {
                        $cache[ $product_id ] = [];

                        return $cache[ $product_id ];
                }

                $cache[ $product_id ] = array_map(
                        static function ( $term ) {
                                return (string) $term->name;
                        },
                        $terms
                );

                return $cache[ $product_id ];
        }

        /**
         * Retrieve the product category IDs (including ancestors) for a WooCommerce product id.
         *
         * @since 4.4.1
         */
        private function get_inventory_movement_product_category_ids( int $product_id ) : array {
                static $cache = [];

                if ( isset( $cache[ $product_id ] ) ) {
                        return $cache[ $product_id ];
                }

                if ( $product_id <= 0 ) {
                        return [];
                }

                $terms = get_the_terms( $product_id, 'product_cat' );

                if ( ( is_wp_error( $terms ) || empty( $terms ) ) && function_exists( 'wc_get_product' ) ) {
                        $product = wc_get_product( $product_id );

                        if ( $product && $product->is_type( 'variation' ) ) {
                                $terms = get_the_terms( $product->get_parent_id(), 'product_cat' );
                        }
                }

                if ( is_wp_error( $terms ) || empty( $terms ) ) {
                        $cache[ $product_id ] = [];

                        return $cache[ $product_id ];
                }

                $ids = [];

                foreach ( $terms as $term ) {
                        $term_id = (int) $term->term_id;
                        $ids[]   = $term_id;

                        foreach ( get_ancestors( $term_id, 'product_cat' ) as $ancestor_id ) {
                                $ids[] = (int) $ancestor_id;
                        }
                }

                $cache[ $product_id ] = array_values( array_unique( $ids ) );

                return $cache[ $product_id ];
        }

	/**
	 * Sanitize incoming date values for the inventory report filters.
	 *
	 * @since 4.3.1
	 */
        private function sanitize_inventory_report_date( $value ) : string {
                $value = trim( (string) $value );

                if ( '' === $value ) {
                        return '';
                }

                $parsed = $this->parse_inventory_report_date_value( $value );

                if ( ! $parsed instanceof DateTimeInterface ) {
                        return '';
                }

                return $parsed->format( 'Y-m-d' );
        }

        /**
         * Parse incoming inventory report dates supporting multiple common formats.
         *
         * @since 4.1.47
         */
        private function parse_inventory_report_date_value( string $value ) : ?DateTimeInterface {
                $value    = trim( $value );
                $timezone = wp_timezone();

                if ( '' === $value ) {
                        return null;
                }

                $formats = array_unique(
                        [
                                'Y-m-d',
                                'd/m/Y',
                                'd-m-Y',
                                (string) get_option( 'date_format', 'Y-m-d' ),
                        ]
                );

                foreach ( $formats as $format ) {
                        $date = date_create_from_format( $format, $value, $timezone );

                        if ( $date instanceof DateTimeInterface ) {
                                return $date;
                        }
                }

                $fallback = date_create( $value, $timezone );

                if ( $fallback instanceof DateTimeInterface ) {
                        return $fallback;
                }

                return null;
        }

        /**
         * Convert a local date string into a UTC timestamp.
         *
         * @since 4.1.39
         */
        private function get_utc_timestamp_from_local_date( string $date_string ) : ?int {
                $timezone = wp_timezone();
                $datetime = date_create( $date_string, $timezone );

                if ( ! $datetime instanceof DateTimeInterface ) {
                        return null;
                }

                return $datetime->getTimestamp();
        }

        /**
         * Retrieve a localized DateTime instance for a UTC timestamp.
         *
         * @since 4.1.46
         */
        private function get_localized_datetime_from_timestamp( int $timestamp ) : ?DateTimeInterface {
                if ( $timestamp <= 0 ) {
                        return null;
                }

                $datetime = date_create( '@' . $timestamp );

                if ( ! $datetime instanceof DateTimeInterface ) {
                        return null;
                }

                $datetime->setTimezone( wp_timezone() );

                return $datetime;
        }

        /**
         * Normalize timestamps to UTC to avoid applying the site offset twice when rendering dates.
         *
         * @since 4.1.39
         */
        private function normalize_inventory_movement_timestamp( int $timestamp ) : int {
                $offset = (int) ( get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS );

                if ( $timestamp <= 0 || 0 === $offset ) {
                        return $timestamp;
                }

                $utc_now          = current_time( 'timestamp', true );
                $offset_timestamp = $timestamp - $offset;

                if ( abs( $offset_timestamp - $utc_now ) < abs( $timestamp - $utc_now ) ) {
                        return $offset_timestamp;
                }

                return $timestamp;
        }

	/**
	 * Generate a summarized inventory movement report including totals and raw entries.
	 *
	 * @since 4.3.1
	 */
        public function get_inventory_movements_report( array $args = [] ) : array {
                $filters          = $this->prepare_inventory_movement_filters( $args );
                $entries          = $this->get_inventory_movements_storage();
                $filtered_entries = [];
                $totals           = [ 'ingresos' => 0.0, 'egresos' => 0.0, 'balance' => 0.0 ];
                $period_totals    = [];
                $product_totals   = [];
                $category_totals  = [];

                foreach ( $entries as $entry ) {
                        $timestamp           = isset( $entry['timestamp'] ) ? (int) $entry['timestamp'] : 0;

                        if ( null !== $filters['start_timestamp'] && $timestamp < $filters['start_timestamp'] ) {
                                continue;
                        }

                        if ( null !== $filters['end_timestamp'] && $timestamp > $filters['end_timestamp'] ) {
                                continue;
                        }

                        if ( $filters['product_id'] && $filters['product_id'] !== (int) $entry['wc_product_id'] ) {
                                continue;
                        }

                        if ( '' !== $filters['sku'] && strtoupper( (string) $entry['sku'] ) !== $filters['sku'] ) {
                                continue;
                        }

                        if ( $filters['category_id'] ) {
                                $product_categories = $this->get_inventory_movement_product_category_ids( (int) $entry['wc_product_id'] );

                                if ( ! in_array( $filters['category_id'], $product_categories, true ) ) {
                                        continue;
                                }
                        }

                        if ( 'all' !== $filters['scope'] && $filters['scope'] !== $entry['sync_type'] ) {
                                continue;
                        }

                        if ( '' !== $filters['location'] && ! $this->inventory_movement_matches_location_filter( $entry, $filters['location'] ) ) {
                                continue;
                        }

                        $filtered_entries[] = $entry;

                        $quantity = isset( $entry['quantity'] ) ? (float) $entry['quantity'] : 0.0;
                        $event    = isset( $entry['event_type'] ) ? $entry['event_type'] : 'egreso';

                        if ( 'ingreso' === $event ) {
                                $totals['ingresos'] += $quantity;
                        } else {
                                $totals['egresos'] += $quantity;
                        }

                        list( $period_key, $period_label ) = $this->resolve_inventory_movement_period_key( $timestamp, $filters['period'] );

                        if ( ! isset( $period_totals[ $period_key ] ) ) {
                                $period_totals[ $period_key ] = [
                                        'key'      => $period_key,
                                        'label'    => $period_label,
                                        'ingresos' => 0.0,
                                        'egresos'  => 0.0,
                                        'balance'  => 0.0,
                                ];
                        }

                        if ( 'ingreso' === $event ) {
                                $period_totals[ $period_key ]['ingresos'] += $quantity;
                        } else {
                                $period_totals[ $period_key ]['egresos']  += $quantity;
                        }

                        $period_totals[ $period_key ]['balance'] = $period_totals[ $period_key ]['ingresos'] - $period_totals[ $period_key ]['egresos'];

                        $product_key = $entry['wc_product_id'] . ':' . strtoupper( (string) $entry['sku'] );

                        if ( ! isset( $product_totals[ $product_key ] ) ) {
                                $product_totals[ $product_key ] = [
                                        'wc_product_id' => $entry['wc_product_id'],
                                        'product_id'    => $entry['product_id'],
                                        'product_name'  => $entry['product_name'],
                                        'sku'           => $entry['sku'],
                                        'ingresos'      => 0.0,
                                        'egresos'       => 0.0,
                                        'balance'       => 0.0,
                                        'last_movement' => $timestamp,
                                ];
                        }

                        if ( 'ingreso' === $event ) {
                                $product_totals[ $product_key ]['ingresos'] += $quantity;
                        } else {
                                $product_totals[ $product_key ]['egresos']  += $quantity;
                        }

                        $product_totals[ $product_key ]['balance']       = $product_totals[ $product_key ]['ingresos'] - $product_totals[ $product_key ]['egresos'];
                        $product_totals[ $product_key ]['last_movement'] = max( $product_totals[ $product_key ]['last_movement'], $timestamp );

                        $categories = $this->get_inventory_movement_product_categories( (int) $entry['wc_product_id'] );

                        if ( empty( $categories ) ) {
                                $categories = [ __( 'Sin categoría', 'woo-contifico' ) ];
                        }

                        foreach ( $categories as $category_label ) {
                                if ( ! isset( $category_totals[ $category_label ] ) ) {
                                        $category_totals[ $category_label ] = [
                                                'category'      => $category_label,
                                                'ingresos'      => 0.0,
                                                'egresos'       => 0.0,
                                                'balance'       => 0.0,
                                                'last_movement' => $timestamp,
                                        ];
                                }

                                if ( 'ingreso' === $event ) {
                                        $category_totals[ $category_label ]['ingresos'] += $quantity;
                                } else {
                                        $category_totals[ $category_label ]['egresos']  += $quantity;
                                }

                                $category_totals[ $category_label ]['balance']       = $category_totals[ $category_label ]['ingresos'] - $category_totals[ $category_label ]['egresos'];
                                $category_totals[ $category_label ]['last_movement'] = max( $category_totals[ $category_label ]['last_movement'], $timestamp );
                        }
                }

                usort( $filtered_entries, static function ( $a, $b ) {
                        return ( $b['timestamp'] ?? 0 ) <=> ( $a['timestamp'] ?? 0 );
                } );

                $totals['balance'] = $totals['ingresos'] - $totals['egresos'];

                usort( $period_totals, static function ( $a, $b ) {
                        return strcmp( $a['key'], $b['key'] );
                } );

                usort( $product_totals, static function ( $a, $b ) {
                        return ( $b['last_movement'] ?? 0 ) <=> ( $a['last_movement'] ?? 0 );
                } );

                usort( $category_totals, static function ( $a, $b ) {
                        return ( $b['last_movement'] ?? 0 ) <=> ( $a['last_movement'] ?? 0 );
                } );

                $chart_periods = [];

                foreach ( $period_totals as $period_total ) {
                        $chart_periods[] = [
                                'label'    => $period_total['label'],
                                'ingresos' => $period_total['ingresos'],
                                'egresos'  => $period_total['egresos'],
                        ];
                }

                $chart_products = array_slice( $product_totals, 0, 10 );

                return [
                        'filters'           => $filters,
                        'entries'           => $filtered_entries,
                        'totals'            => $totals,
                        'totals_by_period'  => $period_totals,
                        'totals_by_product'  => $product_totals,
                        'totals_by_category' => $category_totals,
                        'chart_data'        => [
                                'periods'  => $chart_periods,
                                'products' => $chart_products,
                        ],
                ];
        }

        /**
         * Determine whether an entry matches the requested location filter.
         *
         * @since 4.4.0
         */
        private function inventory_movement_matches_location_filter( array $entry, string $location_filter ) : bool {
                $location_filter = (string) $location_filter;

                if ( '' === $location_filter ) {
                        return true;
                }

                $location_id    = isset( $entry['location']['id'] ) ? (string) $entry['location']['id'] : '';
                $location_label = isset( $entry['location']['label'] ) ? (string) $entry['location']['label'] : '';

                if ( '' !== $location_id && $location_filter === $location_id ) {
                        return true;
                }

                if ( '' !== $location_label && $location_filter === $location_label ) {
                        return true;
                }

                foreach ( [ 'from', 'to' ] as $side ) {
                        $warehouse_location_id    = isset( $entry['warehouses'][ $side ]['location_id'] ) ? (string) $entry['warehouses'][ $side ]['location_id'] : '';
                        $warehouse_location_label = isset( $entry['warehouses'][ $side ]['location_label'] ) ? (string) $entry['warehouses'][ $side ]['location_label'] : '';

                        if ( '' !== $warehouse_location_id && $location_filter === $warehouse_location_id ) {
                                return true;
                        }

                        if ( '' !== $warehouse_location_label && $location_filter === $warehouse_location_label ) {
                                return true;
                        }
                }

                return false;
        }

	/**
	 * Export the inventory movement report as CSV or JSON.
	 *
	 * @since 4.3.1
	 */
	public function export_inventory_movements() : void {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
	wp_die( esc_html__( 'No tienes permisos suficientes para descargar este informe.', 'woo-contifico' ) );
	}

	$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'woo_contifico_export_inventory_movements' ) ) {
	wp_die( esc_html__( 'Solicitud no válida.', 'woo-contifico' ) );
	}

	$format  = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : 'csv';
$filters = [
'start_date' => isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : '',
'end_date'   => isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : '',
'product_id' => isset( $_GET['product_id'] ) ? absint( wp_unslash( $_GET['product_id'] ) ) : 0,
'category_id' => isset( $_GET['category_id'] ) ? absint( wp_unslash( $_GET['category_id'] ) ) : 0,
'sku'        => isset( $_GET['sku'] ) ? sanitize_text_field( wp_unslash( $_GET['sku'] ) ) : '',
'period'     => isset( $_GET['period'] ) ? sanitize_key( wp_unslash( $_GET['period'] ) ) : 'day',
'scope'      => isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : 'all',
'location'   => isset( $_GET['location'] ) ? sanitize_text_field( wp_unslash( $_GET['location'] ) ) : '',
];

	$report = $this->get_inventory_movements_report( $filters );

	if ( 'json' === $format ) {
        wp_send_json( [
        'filters'           => $report['filters'],
        'totals'            => $report['totals'],
        'entries'           => $report['entries'],
        'totals_by_product' => $report['totals_by_product'],
        'totals_by_category' => $report['totals_by_category'],
        ] );
        }

	nocache_headers();
	$filename = sprintf( 'woo-contifico-inventory-movements-%s.csv', gmdate( 'Ymd-His' ) );
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	$output = fopen( 'php://output', 'w' );

        $headers = [
        __( 'Fecha', 'woo-contifico' ),
        __( 'Evento', 'woo-contifico' ),
        __( 'Orden', 'woo-contifico' ),
        __( 'Producto', 'woo-contifico' ),
        __( 'SKU', 'woo-contifico' ),
        __( 'Cantidad', 'woo-contifico' ),
        __( 'Ubicación', 'woo-contifico' ),
        __( 'Bodega origen', 'woo-contifico' ),
        __( 'Bodega destino', 'woo-contifico' ),
        __( 'Estado', 'woo-contifico' ),
        __( 'Referencia API', 'woo-contifico' ),
        __( 'Mensaje', 'woo-contifico' ),
        ];

	fputcsv( $output, $headers );

	foreach ( $report['entries'] as $entry ) {
        $location_label = isset( $entry['location']['label'] ) ? $entry['location']['label'] : '';
        $location_id    = isset( $entry['location']['id'] ) ? $entry['location']['id'] : '';
        $location_value = $location_label;

        if ( '' === $location_value ) {
        $location_value = $location_id;
        } elseif ( '' !== $location_id && $location_id !== $location_label ) {
        $location_value = sprintf( '%s (%s)', $location_label, $location_id );
        }

        fputcsv( $output, [
        wp_date( 'Y-m-d H:i:s', (int) $entry['timestamp'] ),
        isset( $entry['event_type'] ) ? $entry['event_type'] : '',
        isset( $entry['order_id'] ) ? (int) $entry['order_id'] : 0,
        isset( $entry['product_name'] ) ? $entry['product_name'] : '',
        isset( $entry['sku'] ) ? $entry['sku'] : '',
        isset( $entry['quantity'] ) ? (float) $entry['quantity'] : 0,
        $location_value,
        isset( $entry['warehouses']['from']['label'] ) ? $entry['warehouses']['from']['label'] : '',
        isset( $entry['warehouses']['to']['label'] ) ? $entry['warehouses']['to']['label'] : '',
        isset( $entry['status'] ) ? $entry['status'] : 'pending',
        isset( $entry['reference'] ) ? $entry['reference'] : '',
        isset( $entry['error_message'] ) ? $entry['error_message'] : '',
        ] );
        }

	fclose( $output );
	exit;
	}

	/**
	 * Resolve the grouping key and label based on the selected period granularity.
	 *
	 * @since 4.3.1
	 */
        private function resolve_inventory_movement_period_key( int $timestamp, string $period ) : array {
        switch ( $period ) {
        case 'week':
        $key   = wp_date( 'o-\WW', $timestamp );
        $label = sprintf(
	/* translators: 1: ISO week number. 2: year. */
	__( 'Semana %1$s · %2$s', 'woo-contifico' ),
	wp_date( 'W', $timestamp ),
	wp_date( 'Y', $timestamp )
	);
	break;
	case 'month':
	$key   = wp_date( 'Y-m', $timestamp );
	$label = wp_date( _x( 'F Y', 'inventory report month label', 'woo-contifico' ), $timestamp );
	break;
	case 'day':
	default:
	$key   = wp_date( 'Y-m-d', $timestamp );
	$label = wp_date( get_option( 'date_format', 'Y-m-d' ), $timestamp );
	break;
	}

	return [ $key, $label ];
        }

        /**
         * Build a list of product choices discovered in the movement log.
         *
         * @since 4.3.1
         */
        private function get_inventory_movement_product_choices() : array {
                $choices = [];

                foreach ( $this->get_inventory_movements_storage() as $entry ) {
                        $wc_product_id = isset( $entry['wc_product_id'] ) ? (int) $entry['wc_product_id'] : 0;

                        if ( ! $wc_product_id ) {
                                continue;
                        }

                        if ( isset( $choices[ $wc_product_id ] ) ) {
                                continue;
                        }

                        $label = $entry['product_name'];

                        if ( '' === $label ) {
                                $label = sprintf( __( 'Producto #%d', 'woo-contifico' ), $wc_product_id );
                        }

                        if ( ! empty( $entry['sku'] ) ) {
                                $label .= sprintf( ' (%s)', $entry['sku'] );
                        }

                        $choices[ $wc_product_id ] = [
                                'id'    => $wc_product_id,
                                'label' => $label,
                                'sku'   => $entry['sku'],
                        ];
                }

                uasort( $choices, static function ( $a, $b ) {
                        return strcmp( $a['label'], $b['label'] );
                } );

                return array_values( $choices );
        }

        /**
         * Build a list of category choices with hierarchical labels.
         *
         * @since 4.4.1
         */
        private function get_inventory_movement_category_choices() : array {
                $choices = [];

                foreach ( get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] ) as $term ) {
                        if ( is_wp_error( $term ) ) {
                                continue;
                        }

                        $ancestors = array_reverse( get_ancestors( $term->term_id, 'product_cat' ) );
                        $label     = [];

                        foreach ( $ancestors as $ancestor_id ) {
                                $ancestor = get_term( $ancestor_id, 'product_cat' );

                                if ( is_wp_error( $ancestor ) || ! $ancestor ) {
                                        continue;
                                }

                                $label[] = $ancestor->name;
                        }

                        $label[] = $term->name;

                        $choices[ $term->term_id ] = [
                                'id'    => (int) $term->term_id,
                                'label' => implode( ' › ', $label ),
                        ];
                }

                if ( empty( $choices ) ) {
                        return [];
                }

                uasort( $choices, static function ( $a, $b ) {
                        return strcasecmp( $a['label'], $b['label'] );
                } );

                return array_values( $choices );
        }

        /**
         * Build a list of location choices discovered in the movement log.
         *
         * @since 4.4.0
         *
         * @return array<int,array{id:string,label:string}>
         */
        private function get_inventory_movement_location_choices() : array {
                $choices = [];

                foreach ( $this->get_inventory_movements_storage() as $entry ) {
                        $location_id    = isset( $entry['location']['id'] ) ? (string) $entry['location']['id'] : '';
                        $location_label = isset( $entry['location']['label'] ) ? (string) $entry['location']['label'] : '';
                        $value          = '' !== $location_id ? $location_id : $location_label;

                        if ( '' === $value ) {
                                continue;
                        }

                        $display = $location_label;

                        if ( '' === $display ) {
                                $display = $location_id;
                        } elseif ( '' !== $location_id && $location_label !== $location_id ) {
                                $display = sprintf( '%s (%s)', $location_label, $location_id );
                        }

                        if ( isset( $choices[ $value ] ) ) {
                                continue;
                        }

                        $choices[ $value ] = [
                                'id'    => $value,
                                'label' => $display,
                        ];
                }

                if ( empty( $choices ) ) {
                        return [];
                }

                uasort( $choices, static function ( $a, $b ) {
                        return strcasecmp( $a['label'], $b['label'] );
                } );

                return array_values( $choices );
        }

        /**
         * Determine if a warehouse entry matches the configured billing warehouse code.
         *
         * @param array  $warehouse
         * @param string $billing_code
         *
         * @return bool
         * @since 4.1.33
         */
        private function warehouse_matches_billing( array $warehouse, string $billing_code ) : bool {

                if ( '' === $billing_code ) {
                        return false;
                }

                $candidates = array_filter( [
                        isset( $warehouse['code'] ) ? (string) $warehouse['code'] : '',
                        isset( $warehouse['id'] ) ? (string) $warehouse['id'] : '',
                        isset( $warehouse['location_id'] ) ? (string) $warehouse['location_id'] : '',
                        isset( $warehouse['label'] ) ? (string) $warehouse['label'] : '',
                ] );

                foreach ( $candidates as $candidate ) {
                        if ( '' !== $candidate && strcasecmp( $candidate, $billing_code ) === 0 ) {
                                return true;
                        }
                }

                return false;
        }

        /**
         * Build a unique balance key for a web warehouse entry.
         *
         * @param array $entry
         *
         * @return string
         */
        private function get_web_warehouse_balance_key( array $entry ) : string {

                $product_id = (int) ( $entry['wc_product_id'] ?? 0 );

                if ( $product_id > 0 ) {
                        return 'wc_' . $product_id;
                }

                $contifico_id = isset( $entry['product_id'] ) ? (string) $entry['product_id'] : '';
                $sku          = isset( $entry['sku'] ) ? (string) $entry['sku'] : '';

                return md5( wp_json_encode( [ $contifico_id, $sku ] ) );
        }


        /**
         * Get products that still have balance assigned to the billing/web warehouse.
         *
         * @return array<int,array<string,mixed>>
         * @since 4.1.33
         */
        private function get_web_warehouse_pending_products() : array {

                $warehouse_code = isset( $this->woo_contifico->settings['bodega_facturacion'] ) ? trim( (string) $this->woo_contifico->settings['bodega_facturacion'] ) : '';

                if ( '' === $warehouse_code ) {
                        return [];
                }

                $entries  = $this->get_inventory_movements_storage();
                $balances = [];
                $allowed_contexts = [ 'transfer', 'restore' ];

                foreach ( $entries as $entry ) {
                        $context    = isset( $entry['context'] ) ? (string) $entry['context'] : '';
                        $status     = isset( $entry['status'] ) ? (string) $entry['status'] : '';
                        $event_type = isset( $entry['event_type'] ) ? (string) $entry['event_type'] : '';
                        $quantity   = (float) ( $entry['quantity'] ?? 0 );

                        if ( ! in_array( $context, $allowed_contexts, true ) ) {
                                continue;
                        }

                        if ( 'success' !== $status || $quantity <= 0.0 || '' === $event_type ) {
                                continue;
                        }

                        $from_matches = $this->warehouse_matches_billing( $entry['warehouses']['from'] ?? [], $warehouse_code );
                        $to_matches   = $this->warehouse_matches_billing( $entry['warehouses']['to'] ?? [], $warehouse_code );

                        if ( ! $from_matches && ! $to_matches ) {
                                continue;
                        }

                        $key = $this->get_web_warehouse_balance_key( $entry );

                        if ( ! isset( $balances[ $key ] ) ) {
                                $balances[ $key ] = [
                                        'product_name'  => isset( $entry['product_name'] ) ? (string) $entry['product_name'] : __( 'Producto sin nombre', 'woo-contifico' ),
                                        'sku'           => isset( $entry['sku'] ) ? (string) $entry['sku'] : '',
                                        'pending'       => 0.0,
                                        'last_movement' => isset( $entry['timestamp'] ) ? (int) $entry['timestamp'] : 0,
                                        'key'           => $key,
                                ];
                        }

                        if ( $to_matches && 'egreso' === $event_type ) {
                                $balances[ $key ]['pending'] += $quantity;
                        } elseif ( $from_matches && 'ingreso' === $event_type ) {
                                $balances[ $key ]['pending'] -= $quantity;
                        }

                        if ( isset( $entry['timestamp'] ) && (int) $entry['timestamp'] > $balances[ $key ]['last_movement'] ) {
                                $balances[ $key ]['last_movement'] = (int) $entry['timestamp'];
                        }
                }

                $balances = array_filter(
                        $balances,
                        static function ( $entry ) {
                                return isset( $entry['pending'] ) && (float) $entry['pending'] > 0.0;
                        }
                );

                uasort(
                        $balances,
                        static function ( $a, $b ) {
                                return (float) $b['pending'] <=> (float) $a['pending'];
                        }
                );

                return array_values( $balances );
        }

        /**
         * Remove cached inventory movements that contribute to a specific web warehouse balance key.
         *
         * @param string $balance_key
         *
         * @return int Number of removed entries.
         */
        private function delete_web_warehouse_balance_entries( string $balance_key ) : int {

                $current_entries = $this->get_inventory_movements_storage();

                if ( empty( $current_entries ) ) {
                        return 0;
                }

                $filtered = [];
                $removed  = 0;

                foreach ( $current_entries as $entry ) {
                        $entry_key = $this->get_web_warehouse_balance_key( $entry );

                        if ( $entry_key === $balance_key ) {
                                $removed++;

                                continue;
                        }

                        $filtered[] = $entry;
                }

                if ( $removed > 0 ) {
                        $this->save_inventory_movements( $filtered );
                }

                return $removed;
        }

        /**
         * Process manual cleanup requests for pending web warehouse entries.
         *
         * @return void
         */
        private function maybe_handle_web_warehouse_cleanup_request() : void {

                if ( ! isset( $_GET['woo_contifico_clear_web_warehouse'] ) ) {
                        return;
                }

                if ( ! current_user_can( 'manage_woocommerce' ) ) {
                        add_settings_error(
                                'woo_contifico_web_warehouse',
                                'woo_contifico_web_warehouse_permissions',
                                __( 'No tienes permisos para administrar el almacén web.', 'woo-contifico' )
                        );

                        return;
                }

                if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'woo_contifico_clear_web_warehouse' ) ) {
                        add_settings_error(
                                'woo_contifico_web_warehouse',
                                'woo_contifico_web_warehouse_nonce',
                                __( 'No se pudo validar tu solicitud. Inténtalo nuevamente.', 'woo-contifico' ),
                                'error'
                        );

                        return;
                }

                $balance_key = sanitize_text_field( wp_unslash( $_GET['woo_contifico_clear_web_warehouse'] ) );

                if ( '' === $balance_key ) {
                        return;
                }

                $removed = $this->delete_web_warehouse_balance_entries( $balance_key );

                if ( $removed > 0 ) {
                        add_settings_error(
                                'woo_contifico_web_warehouse',
                                'woo_contifico_web_warehouse_cleared',
                                sprintf(
                                        _n(
                                                'Se eliminó %d movimiento asociado al producto de la bodega web.',
                                                'Se eliminaron %d movimientos asociados al producto de la bodega web.',
                                                $removed,
                                                'woo-contifico'
                                        ),
                                        $removed
                                ),
                                'updated'
                        );

                        return;
                }

                add_settings_error(
                        'woo_contifico_web_warehouse',
                        'woo_contifico_web_warehouse_not_found',
                        __( 'No se encontraron movimientos para limpiar en la bodega web.', 'woo-contifico' )
                );
        }

        /**
         * Reset the environment before running a manual synchronization.
         *
         * @since 4.3.0
         *
         * @return void
         */
        private function reset_manual_sync_environment() : void {
                delete_transient( 'woo_contifico_fetch_productos' );
                delete_transient( 'woo_contifico_full_inventory' );
                delete_transient( self::SYNC_RESULT_TRANSIENT_KEY );
                $this->reset_sync_debug_log();

                if ( method_exists( $this->contifico, 'reset_inventory_cache' ) ) {
                        $this->contifico->reset_inventory_cache();
                }
        }

        /**
         * Format numeric synchronization changes for reports.
         *
         * @since 4.3.0
         *
         * @param mixed  $previous
         * @param mixed  $current
         * @param string $separator
         * @param string $fallback
         *
         * @return string
         */
        private function format_sync_change_value( $previous, $current, string $separator, string $fallback ) : string {
                $has_previous = is_numeric( $previous );
                $has_current  = is_numeric( $current );

                if ( $has_previous && $has_current ) {
                        $previous_formatted = wc_format_decimal( (float) $previous, 2 );
                        $current_formatted  = wc_format_decimal( (float) $current, 2 );

                        if ( $previous_formatted === $current_formatted ) {
                                return $current_formatted;
                        }

                        return $previous_formatted . ' ' . $separator . ' ' . $current_formatted;
                }

                if ( $has_current ) {
                        return wc_format_decimal( (float) $current, 2 );
                }

                if ( $has_previous ) {
                        return wc_format_decimal( (float) $previous, 2 );
                }

                return $fallback;
        }

        /**
         * Format identifier changes for manual synchronization reports.
         *
         * @since 4.3.0
         *
         * @param string $previous
         * @param string $current
         * @param string $separator
         * @param string $fallback
         *
         * @return string
         */
        private function format_sync_identifier_value( string $previous, string $current, string $separator, string $fallback ) : string {
                $previous = trim( $previous );
                $current  = trim( $current );

                if ( '' !== $previous && '' !== $current ) {
                        if ( $previous === $current ) {
                                return $current;
                        }

                        return $previous . ' ' . $separator . ' ' . $current;
                }

                if ( '' !== $current ) {
                        return $current;
                }

                if ( '' !== $previous ) {
                        return $previous . ' ' . $separator . ' ' . $fallback;
                }

                return $fallback;
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
                $skip_movement_log          = false;
                $reset_identifier_on_mismatch = false;
                $sync_variations            = false;

                if ( isset( $_POST['skip_inventory_movement'] ) ) {
                        $skip_movement_log = wc_string_to_bool( wp_unslash( $_POST['skip_inventory_movement'] ) );
                }

                if ( isset( $_POST['reset_identifier_on_mismatch'] ) ) {
                        $reset_identifier_on_mismatch = wc_string_to_bool( wp_unslash( $_POST['reset_identifier_on_mismatch'] ) );
                }

                if ( isset( $_POST['sync_variations'] ) ) {
                        $sync_variations = wc_string_to_bool( wp_unslash( $_POST['sync_variations'] ) );
                }

                if ( $product_id <= 0 && '' === $sku ) {
                        wp_send_json_error(
                                [ 'message' => __( 'Debes proporcionar un SKU para iniciar la sincronización.', 'woo-contifico' ) ],
                                400
                        );

                        return;
                }

                try {
                        if ( $product_id > 0 ) {
                                $result = $this->sync_single_product_by_product_id(
                                        $product_id,
                                        $sku,
                                        ! $skip_movement_log,
                                        $reset_identifier_on_mismatch,
                                        $sync_variations
                                );
                        } else {
                                $result = $this->sync_single_product_by_sku( $sku, ! $skip_movement_log, $reset_identifier_on_mismatch );
                        }

                        wp_send_json_success( $result );
                }
                catch ( Woo_Contifico_Sku_Mismatch_Exception $exception ) {
                        wp_send_json_error(
                                array_merge(
                                        [
                                                'message' => $exception->getMessage(),
                                                'code'    => 'woo_contifico_sku_mismatch',
                                        ],
                                        $exception->get_error_data()
                                ),
                                409
                        );
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
                        delete_transient( self::SYNC_RESULT_TRANSIENT_KEY );
                        $this->reset_sync_debug_log();

                        $this->initialize_scheduled_sync_result();
                }

                $result = $this->sync_stock($step, $this->woo_contifico->settings['batch_size']);

                if ( isset( $result['step'] ) && 'done' === $result['step'] ) {
                        $this->record_scheduled_sync_history( $result );
                }

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

                $result = $this->normalize_sync_result_defaults( (array) get_transient( self::SYNC_RESULT_TRANSIENT_KEY ) );
                $updates_map = isset( $result['updates'] ) && is_array( $result['updates'] ) ? $result['updates'] : [];

                if ( '' === $result['started_at'] ) {
                        $result['started_at'] = current_time( 'mysql' );
                }

		# Check is plugin is active
		if ( $this->is_active() === true ) {

                        # Fetch warehouse stock
                        $this->contifico->fetch_warehouses();
                        $manage_stock      = wc_string_to_bool( get_option( 'woocommerce_manage_stock' ) );
                        $id_warehouse      = $this->contifico->get_id_bodega( $this->woo_contifico->settings['bodega'] );
                        $warehouses_map    = $this->contifico->get_warehouses_map();
                        $debug_log_entries = get_transient( self::SYNC_DEBUG_TRANSIENT_KEY );

                        if ( ! is_array( $debug_log_entries ) ) {
                                $debug_log_entries = [];
                        }

                        # Get products of this batch
                        $fetched_products = $this->contifico->fetch_products( $step, $batch_size );

                        $products_by_sku = [];
                        $products_by_id  = [];
                        $contifico_skus  = [];

                        foreach ( $fetched_products as $product_data ) {
                                if ( ! is_array( $product_data ) ) {
                                        continue;
                                }

                                $contifico_id = isset( $product_data['codigo'] ) ? (string) $product_data['codigo'] : '';

                                if ( '' !== $contifico_id ) {
                                        $products_by_id[ $contifico_id ] = $product_data;
                                }

                                $sku = isset( $product_data['sku'] ) ? (string) $product_data['sku'] : '';

                                if ( '' === $sku ) {
                                        continue;
                                }

                                $products_by_sku[ $sku ] = $product_data;
                                $contifico_skus[]         = $sku;
                        }

                        if ( ! empty( $contifico_skus ) ) {
                                $contifico_skus = array_values( array_unique( $contifico_skus ) );
                        }

                        # Check if the batch processing is finished
                        if ( empty( $fetched_products ) )
                        {
                                $this->write_sync_debug_log( $debug_log_entries );
                                delete_transient( self::SYNC_DEBUG_TRANSIENT_KEY );

                                $result['step']       = 'done';
                                $result['debug_log']  = $this->sync_debug_log_url;
                                $result['updates']    = array_values( $updates_map );
                                $result['finished_at'] = current_time( 'mysql' );

                                delete_transient( self::SYNC_RESULT_TRANSIENT_KEY );
                        }
                        else {

                                # Results of the synchronization
                                $result['fetched']    = $this->contifico->count_fetched_products();
                                $result['step']       = $step + 1;
                                $result['finished_at'] = '';

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
                                        $contifico_sku     = isset( $contifico_product['sku'] ) ? (string) $contifico_product['sku'] : $sku;

                                        $resolved_product = $this->resolve_wc_product_for_contifico_sku(
                                                $wc_product,
                                                $contifico_sku,
                                                $contifico_id
                                        );

                                        if ( ! $resolved_product ) {
                                                continue;
                                        }

                                        $stored_contifico_id = $this->resolve_contifico_product_identifier(
                                                $resolved_product,
                                                ! $resolved_product->is_type( 'variation' )
                                        );

                                        if ( '' !== $stored_contifico_id && isset( $products_by_id[ $stored_contifico_id ] ) ) {
                                                $contifico_product = $products_by_id[ $stored_contifico_id ];
                                                $new_sku           = isset( $contifico_product['sku'] ) ? (string) $contifico_product['sku'] : '';

                                                if ( '' !== $new_sku && $new_sku !== $contifico_sku ) {
                                                        $contifico_sku    = $new_sku;
                                                        $resolved_product = $this->resolve_wc_product_for_contifico_sku(
                                                                $wc_product,
                                                                $contifico_sku,
                                                                $contifico_id
                                                        );

                                                        if ( ! $resolved_product ) {
                                                                continue;
                                                        }
                                                }

                                                $contifico_id = $stored_contifico_id;
                                        }

                                        if ( '' === $contifico_id ) {
                                                continue;
                                        }

                                        $resolved_product = $this->ensure_product_matches_contifico_identifier( $resolved_product, $contifico_id );

                                        if ( ! $resolved_product ) {
                                                continue;
                                        }

                                        if ( isset( $matched_contifico_ids[ $contifico_id ] ) ) {
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

                                        if ( '' === $contifico_id ) {
                                                continue;
                                        }

                                        if ( isset( $matched_contifico_ids[ $contifico_id ] ) ) {
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

                                        $resolved_product = $this->resolve_wc_product_for_contifico_sku(
                                                $wc_product,
                                                $contifico_sku,
                                                $contifico_id
                                        );

                                        if ( ! $resolved_product ) {
                                                continue;
                                        }

                                        $effective_product_data = $product_data;
                                        $effective_id           = $contifico_id;
                                        $effective_sku          = $contifico_sku;

                                        $stored_contifico_id = $this->resolve_contifico_product_identifier(
                                                $resolved_product,
                                                ! $resolved_product->is_type( 'variation' )
                                        );

                                        if ( '' !== $stored_contifico_id && isset( $products_by_id[ $stored_contifico_id ] ) ) {
                                                $effective_product_data = $products_by_id[ $stored_contifico_id ];
                                                $effective_id           = $stored_contifico_id;
                                                $new_sku                = isset( $effective_product_data['sku'] ) ? (string) $effective_product_data['sku'] : '';

                                                if ( '' !== $new_sku && $new_sku !== $effective_sku ) {
                                                        $effective_sku    = $new_sku;
                                                        $resolved_product = $this->resolve_wc_product_for_contifico_sku(
                                                                $wc_product,
                                                                $effective_sku,
                                                                $effective_id
                                                        );

                                                        if ( ! $resolved_product ) {
                                                                continue;
                                                        }
                                                }
                                        }

                                        $resolved_product = $this->ensure_product_matches_contifico_identifier( $resolved_product, $effective_id );

                                        if ( ! $resolved_product ) {
                                                continue;
                                        }

                                        if ( isset( $matched_contifico_ids[ $effective_id ] ) ) {
                                                continue;
                                        }

                                        $products[] = [
                                                'id'      => $effective_id,
                                                'pvp1'    => isset( $effective_product_data['pvp1'] ) ? (float) $effective_product_data['pvp1'] : 0.0,
                                                'pvp2'    => isset( $effective_product_data['pvp2'] ) ? (float) $effective_product_data['pvp2'] : 0.0,
                                                'pvp3'    => isset( $effective_product_data['pvp3'] ) ? (float) $effective_product_data['pvp3'] : 0.0,
                                                'product' => $resolved_product,
                                        ];

                                        $matched_contifico_ids[ $effective_id ] = true;
                                }

                                $result['found'] = $result['found'] + count( $products );

                                # Update new stock and price
                                $product_stock_cache        = [];
                                $warehouse_id_cache         = [];
                                $inventory_movement_entries = [];

                                foreach ( $products as $product ) {

                                        $changes = $this->update_product_from_contifico_data(
                                                $product,
                                                $result,
                                                $debug_log_entries,
                                                $product_stock_cache,
                                                $warehouse_id_cache,
                                                $warehouses_map,
                                                (string) $id_warehouse
                                        );

                                        $summary_entry = $this->build_batch_sync_summary_entry( $product, $changes );

                                        if ( null !== $summary_entry ) {
                                                $summary_key                  = $this->generate_batch_sync_summary_key( $summary_entry );
                                                $updates_map[ $summary_key ] = $summary_entry;
                                        }

                                        $movement_entry = $this->build_manual_sync_inventory_movement_entry( $product, $changes, 'global' );

                                        if ( null !== $movement_entry ) {
                                                $inventory_movement_entries[] = $movement_entry;
                                        }

                                }

                                if ( ! empty( $inventory_movement_entries ) ) {
                                        $this->append_inventory_movement_entries( $inventory_movement_entries );
                                }

                                $result['updates'] = $updates_map;

                                # Store results in a transient to get it in the next batch
                                set_transient( self::SYNC_RESULT_TRANSIENT_KEY, $result, HOUR_IN_SECONDS );

                                $result['updates'] = array_values( $updates_map );

                                set_transient( self::SYNC_DEBUG_TRANSIENT_KEY, $debug_log_entries, HOUR_IN_SECONDS );
                        }

                }

                return $result;

        }

        /**
         * Store a summary of the scheduled synchronization in history.
         *
         * @since 4.1.34
         *
         * @param array $result
         *
         * @return void
         */
        private function record_scheduled_sync_history( array $result ) : void {

                $entry = $this->build_scheduled_sync_history_entry( $result );

                if ( empty( $entry['id'] ) ) {
                        return;
                }

                $this->append_manual_sync_history_entry( $entry );
        }

        /**
         * Build a history entry for scheduled stock synchronizations.
         *
         * @since 4.1.34
         *
         * @param array $result
         *
         * @return array
         */
        private function build_scheduled_sync_history_entry( array $result ) : array {

                $result         = $this->normalize_manual_sync_data( $result );
                $run_id         = isset( $result['run_id'] ) ? (string) $result['run_id'] : '';
                $started_at     = isset( $result['started_at'] ) ? (string) $result['started_at'] : '';
                $finished_at    = isset( $result['finished_at'] ) ? (string) $result['finished_at'] : '';
                $history_updates = isset( $result['updates'] ) && is_array( $result['updates'] ) ? array_values( $result['updates'] ) : [];

                if ( '' === $run_id ) {
                        $run_id = $this->generate_sync_run_id();
                }

                if ( '' === $started_at ) {
                        $started_at = current_time( 'mysql' );
                }

                if ( '' === $finished_at ) {
                        $finished_at = current_time( 'mysql' );
                }

                return [
                        'id'          => $run_id,
                        'status'      => 'completed',
                        'message'     => __( 'Sincronización programada completada correctamente.', 'woo-contifico' ),
                        'started_at'  => $started_at,
                        'finished_at' => $finished_at,
                        'fetched'     => isset( $result['fetched'] ) ? (int) $result['fetched'] : 0,
                        'found'       => isset( $result['found'] ) ? (int) $result['found'] : 0,
                        'updated'     => isset( $result['updated'] ) ? (int) $result['updated'] : 0,
                        'outofstock'  => isset( $result['outofstock'] ) ? (int) $result['outofstock'] : 0,
                        'updates'     => $history_updates,
                        'debug_log'   => isset( $result['debug_log'] ) ? (string) $result['debug_log'] : '',
                ];
        }

        /**
         * Synchronize a single product identified by product ID.
         *
         * @since 4.2.0
         *
         * @param int    $product_id
         * @param string $fallback_sku
         * @param bool   $log_inventory_movement
         *
         * @return array
         * @throws Exception
         */
        private function sync_single_product_by_product_id(
                int $product_id,
                string $fallback_sku = '',
                bool $log_inventory_movement = true,
                bool $reset_identifier_on_mismatch = false,
                bool $sync_variations = false
        ) : array {

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

                if ( $sync_variations && $wc_product->is_type( 'variable' ) ) {
                        return $this->sync_variable_product_variations(
                                $wc_product,
                                $environment,
                                $log_inventory_movement,
                                $reset_identifier_on_mismatch
                        );
                }

                return $this->execute_single_product_sync( $wc_product, $environment, $lookup_sku, $log_inventory_movement, $reset_identifier_on_mismatch );
        }

        /**
         * Synchronize all variations for a variable product.
         *
         * @since 4.1.86
         *
         * @param WC_Product $parent_product
         * @param array      $environment
         * @param bool       $log_inventory_movement
         * @param bool       $reset_identifier_on_mismatch
         *
         * @return array
         * @throws Exception
         */
        private function sync_variable_product_variations(
                $parent_product,
                array $environment,
                bool $log_inventory_movement = true,
                bool $reset_identifier_on_mismatch = false
        ) : array {

                if ( ! $parent_product || ! is_a( $parent_product, 'WC_Product' ) || ! $parent_product->is_type( 'variable' ) ) {
                        throw new Exception( __( 'El producto no es variable o no se pudo cargar.', 'woo-contifico' ) );
                }

                $variation_ids = (array) $parent_product->get_children();

                if ( empty( $variation_ids ) ) {
                        throw new Exception( __( 'No se encontraron variaciones para sincronizar.', 'woo-contifico' ) );
                }

                $items   = [];
                $summary = [
                        'total'     => 0,
                        'updated'   => 0,
                        'outofstock'=> 0,
                        'errors'    => 0,
                ];
                $result  = [
                        'found'      => 0,
                        'updated'    => 0,
                        'outofstock' => 0,
                ];

                foreach ( $variation_ids as $variation_id ) {
                        $variation = wc_get_product( $variation_id );

                        if ( ! $variation || ! is_a( $variation, 'WC_Product' ) ) {
                                continue;
                        }

                        $summary['total']++;
                        $variation_sku = (string) $variation->get_sku();

                        try {
                                $item = $this->execute_single_product_sync(
                                        $variation,
                                        $environment,
                                        $variation_sku,
                                        $log_inventory_movement,
                                        $reset_identifier_on_mismatch
                                );

                                $items[] = $item;

                                $item_result = isset( $item['result'] ) && is_array( $item['result'] ) ? $item['result'] : [];
                                $result['found']      += isset( $item_result['found'] ) ? (int) $item_result['found'] : 0;
                                $result['updated']    += isset( $item_result['updated'] ) ? (int) $item_result['updated'] : 0;
                                $result['outofstock'] += isset( $item_result['outofstock'] ) ? (int) $item_result['outofstock'] : 0;

                                if ( ! empty( $item['changes']['stock_updated'] ) || ! empty( $item['changes']['price_updated'] ) ) {
                                        $summary['updated']++;
                                }

                                if ( ! empty( $item['changes']['outofstock'] ) ) {
                                        $summary['outofstock']++;
                                }
                        }
                        catch ( Exception $exception ) {
                                $summary['errors']++;

                                $items[] = [
                                        'message'             => __( 'No se pudo sincronizar la variación.', 'woo-contifico' ),
                                        'error'               => $exception->getMessage(),
                                        'contifico_id'         => (string) $variation->get_meta( self::PRODUCT_ID_META_KEY, true ),
                                        'contifico_sku'        => $variation_sku,
                                        'woocommerce_sku'      => $variation_sku,
                                        'woocommerce_product'  => $variation->get_id(),
                                        'changes'              => [],
                                ];
                        }
                }

                $message = sprintf(
                        /* translators: 1: total variations, 2: errors */
                        __( 'Sincronización de variaciones finalizada. Variaciones sincronizadas: %1$d. Errores: %2$d.', 'woo-contifico' ),
                        (int) $summary['total'],
                        (int) $summary['errors']
                );

                return [
                        'message'             => $message,
                        'woocommerce_product' => $parent_product->get_id(),
                        'woocommerce_sku'     => (string) $parent_product->get_sku(),
                        'contifico_id'        => (string) $parent_product->get_meta( self::PRODUCT_ID_META_KEY, true ),
                        'items'               => $items,
                        'summary'             => $summary,
                        'result'              => $result,
                ];
        }

        /**
         * Prepare shared context for single-product synchronization.
         *
         * @since 4.2.0
         *
         * @return array{manage_stock:bool,default_warehouse_id:string,warehouses_map:array}
         */
        private function prepare_single_product_sync_environment() : array {
                $this->contifico->fetch_warehouses();

                $manage_stock   = wc_string_to_bool( get_option( 'woocommerce_manage_stock' ) );
                $id_warehouse   = $this->contifico->get_id_bodega( $this->woo_contifico->settings['bodega'] );
                $warehouses_map = $this->contifico->get_warehouses_map();

                return [
                        'manage_stock'         => $manage_stock,
                        'default_warehouse_id' => is_scalar( $id_warehouse ) ? (string) $id_warehouse : '',
                        'warehouses_map'       => is_array( $warehouses_map ) ? $warehouses_map : [],
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
         * @param bool       $log_inventory_movement
         *
         * @return array
         * @throws Exception
         */
        private function execute_single_product_sync( $resolved_product, array $environment, string $lookup_sku = '', bool $log_inventory_movement = true, bool $reset_identifier_on_mismatch = false ) : array {

                if ( ! $resolved_product || ! is_a( $resolved_product, 'WC_Product' ) ) {
                        throw new Exception( __( 'No se pudo cargar el producto de WooCommerce.', 'woo-contifico' ) );
                }

                $original_product = $resolved_product;

                $lookup_sku = trim( $lookup_sku );

                if ( '' === $lookup_sku ) {
                        $lookup_sku = (string) $resolved_product->get_sku();
                }

                $woocommerce_sku = (string) $resolved_product->get_sku();

                $contifico_id  = $this->resolve_contifico_product_identifier(
                        $resolved_product,
                        ! $resolved_product->is_type( 'variation' )
                );
                $contifico_product = [];

                if ( '' !== $contifico_id ) {
                        $contifico_product = $this->contifico->get_product_by_id( $contifico_id, true );
                }

                if ( empty( $contifico_product ) || ! is_array( $contifico_product ) ) {
                        $contifico_product = $this->get_contifico_product_data_for_product( $resolved_product, $lookup_sku, true );
                }

                if ( ( empty( $contifico_product ) || ! is_array( $contifico_product ) ) && $original_product->is_type( 'variable' ) ) {
                        $variation_candidate = $this->resolve_variation_sync_candidate(
                                $original_product,
                                '' !== $lookup_sku ? $lookup_sku : (string) $original_product->get_sku(),
                                true
                        );

                        if ( $variation_candidate ) {
                                $resolved_product  = $variation_candidate['product'];
                                $contifico_product = $variation_candidate['contifico_product'];
                                $lookup_candidate  = isset( $variation_candidate['lookup_sku'] )
                                        ? (string) $variation_candidate['lookup_sku']
                                        : '';
                                $candidate_id      = isset( $variation_candidate['contifico_id'] )
                                        ? (string) $variation_candidate['contifico_id']
                                        : '';
                                $candidate_sku     = isset( $variation_candidate['contifico_sku'] )
                                        ? (string) $variation_candidate['contifico_sku']
                                        : '';

                                if ( '' !== $lookup_candidate ) {
                                        $lookup_sku = $lookup_candidate;
                                }

                                if ( '' === $contifico_id && '' !== $candidate_id ) {
                                        $contifico_id = $candidate_id;
                                }

                                if ( '' !== $candidate_sku && ! isset( $contifico_product['sku'] ) ) {
                                        $contifico_product['sku'] = $candidate_sku;
                                }
                        }
                }

                if ( empty( $contifico_product ) || ! is_array( $contifico_product ) ) {
                        if ( '' !== $contifico_id ) {
                                throw new Exception(
                                        sprintf(
                                                __( 'No se encontró el producto con el identificador de Contífico "%s" en Contífico.', 'woo-contifico' ),
                                                $contifico_id
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

                if ( isset( $contifico_product['codigo'] ) && '' === $contifico_id ) {
                        $contifico_id = (string) $contifico_product['codigo'];
                }

                $contifico_id  = isset( $contifico_product['codigo'] ) ? (string) $contifico_product['codigo'] : $contifico_id;
                $contifico_sku = isset( $contifico_product['sku'] ) ? (string) $contifico_product['sku'] : $lookup_sku;

                $parent_for_resolution = $resolved_product;

                if ( $resolved_product->is_type( 'variation' ) ) {
                        $parent_id = $resolved_product->get_parent_id();

                        if ( $parent_id ) {
                                $parent_product = wc_get_product( $parent_id );

                                if ( $parent_product && is_a( $parent_product, 'WC_Product' ) ) {
                                        $parent_for_resolution = $parent_product;
                                } else {
                                        $parent_for_resolution = null;
                                }
                        } else {
                                $parent_for_resolution = null;
                        }
                }

                if (
                        $parent_for_resolution
                        && $parent_for_resolution->is_type( 'variable' )
                        && '' !== $contifico_sku
                ) {
                        $matched_product = $this->resolve_wc_product_for_contifico_sku(
                                $parent_for_resolution,
                                $contifico_sku,
                                $contifico_id
                        );

                        if ( $matched_product && is_a( $matched_product, 'WC_Product' ) ) {
                                $resolved_product = $matched_product;

                                if ( $resolved_product->get_id() !== $original_product->get_id() ) {
                                        $refreshed_product = $this->get_contifico_product_data_for_product(
                                                $resolved_product,
                                                '' !== $contifico_sku ? $contifico_sku : $lookup_sku,
                                                true
                                        );

                                        if ( ! empty( $refreshed_product ) && is_array( $refreshed_product ) ) {
                                                $contifico_product = $refreshed_product;
                                                $contifico_id      = isset( $contifico_product['codigo'] ) ? (string) $contifico_product['codigo'] : $contifico_id;
                                                $contifico_sku     = isset( $contifico_product['sku'] ) ? (string) $contifico_product['sku'] : $contifico_sku;
                                        } else {
                                                $stored_contifico_id = $this->resolve_contifico_product_identifier(
                                                        $resolved_product,
                                                        ! $resolved_product->is_type( 'variation' )
                                                );

                                                if ( '' !== $stored_contifico_id ) {
                                                        $contifico_id = $stored_contifico_id;
                                                }
                                        }
                                }
                        }
                }

                if ( '' === $contifico_id ) {
                        throw new Exception( __( 'El producto de Contífico no tiene un identificador válido.', 'woo-contifico' ) );
                }

                $resolved_product = $this->ensure_product_matches_contifico_identifier( $resolved_product, $contifico_id );

                if ( ! $resolved_product || ! is_a( $resolved_product, 'WC_Product' ) ) {
                        throw new Exception(
                                __( 'No se pudo resolver la variación del producto para el identificador de Contífico proporcionado.', 'woo-contifico' )
                        );
                }

                $woocommerce_sku = (string) $resolved_product->get_sku();

                if ( '' === $lookup_sku && '' !== $woocommerce_sku ) {
                        $lookup_sku = $woocommerce_sku;
                }

                if ( '' === $lookup_sku && '' !== $contifico_sku ) {
                        $lookup_sku = $contifico_sku;
                }

                if (
                        '' !== $contifico_sku
                        && '' !== $woocommerce_sku
                        && $contifico_sku !== $woocommerce_sku
                ) {
                        $mismatch_exception = new Woo_Contifico_Sku_Mismatch_Exception(
                                sprintf(
                                        /* translators: 1: Contífico product identifier, 2: Contífico SKU, 3: WooCommerce SKU */
                                        __( 'El ID de Contífico "%1$s" está asociado al SKU "%2$s" en Contífico, pero este producto de WooCommerce usa el SKU "%3$s". Usa "Corregir y continuar" para limpiar el enlace guardado o aplica la acción masiva "Corregir enlace con Contífico (SKU)" antes de sincronizar.', 'woo-contifico' ),
                                        $contifico_id,
                                        $contifico_sku,
                                        $woocommerce_sku
                                ),
                                [
                                        'product_id'      => $resolved_product->get_id(),
                                        'contifico_id'    => $contifico_id,
                                        'contifico_sku'   => $contifico_sku,
                                        'woocommerce_sku' => $woocommerce_sku,
                                ]
                        );

                        if ( ! $reset_identifier_on_mismatch ) {
                                throw $mismatch_exception;
                        }

                        $this->clear_contifico_product_identifier( $resolved_product );
                        $contifico_product = $this->get_contifico_product_data_for_product( $resolved_product, $woocommerce_sku, true );
                        $contifico_id      = isset( $contifico_product['codigo'] ) ? (string) $contifico_product['codigo'] : '';
                        $contifico_sku     = isset( $contifico_product['sku'] ) ? (string) $contifico_product['sku'] : $contifico_sku;

                        if (
                                '' !== $contifico_sku
                                && '' !== $woocommerce_sku
                                && $contifico_sku !== $woocommerce_sku
                        ) {
                                throw $mismatch_exception;
                        }
                }

                $managing_stock = method_exists( $resolved_product, 'managing_stock' )
                        ? (bool) $resolved_product->managing_stock()
                        : (bool) $resolved_product->get_manage_stock();

                if ( $resolved_product->is_type( 'variation' ) && ! $managing_stock ) {
                        throw new Exception(
                                __( 'La variación no tiene habilitada la opción "Gestionar inventario". Actívala y guarda los cambios antes de sincronizar.', 'woo-contifico' )
                        );
                }

                $managing_stock = method_exists( $resolved_product, 'managing_stock' )
                        ? (bool) $resolved_product->managing_stock()
                        : (bool) $resolved_product->get_manage_stock();

                if ( $resolved_product->is_type( 'variation' ) && ! $managing_stock ) {
                        throw new Exception(
                                __( 'La variación no tiene habilitada la opción "Gestionar inventario". Actívala y guarda los cambios antes de sincronizar.', 'woo-contifico' )
                        );
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

                $warehouses_map = isset( $environment['warehouses_map'] ) && is_array( $environment['warehouses_map'] )
                        ? $environment['warehouses_map']
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
                        $warehouses_map,
                        $default_warehouse,
                        true
                );

                $product_cache_key = isset( $product_entry['id'] ) ? (string) $product_entry['id'] : '';

                $location_summary = $this->build_single_product_location_summary(
                        $product_cache_key,
                        $warehouses_map
                );

                if ( $log_inventory_movement ) {
                        $movement_entry = $this->build_manual_sync_inventory_movement_entry( $product_entry, $changes, 'product' );

                        if ( null !== $movement_entry ) {
                                $this->append_inventory_movement_entry( $movement_entry );
                        }
                }

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
                        'woocommerce_sku'     => $woocommerce_sku,
                        'woocommerce_product' => $resolved_product->get_id(),
                        'changes'             => $changes,
                        'result'              => $result,
                        'stock_quantity'      => $resolved_product->get_manage_stock() ? (int) $resolved_product->get_stock_quantity() : null,
                        'price'               => (float) $resolved_product->get_price(),
                        'location_stock'      => $location_summary,
                ];
        }

        /**
         * Build a per-warehouse stock summary for a single product sync.
         *
         * @param string $product_id     Contífico product identifier.
         * @param array  $warehouses_map Mapping of Contífico warehouse IDs to codes.
         *
         * @return array
         */
        private function build_single_product_location_summary( string $product_id, array $warehouses_map ) : array {
                $product_id = trim( $product_id );

                if ( '' === $product_id ) {
                        return [];
                }

                $stock_by_warehouse = $this->contifico->get_product_stock_by_warehouses( $product_id, true );

                if ( ! is_array( $stock_by_warehouse ) || empty( $stock_by_warehouse ) ) {
                        return [];
                }

                $visible_codes = $this->get_item_visible_warehouse_codes();
                $apply_filter  = ! empty( $visible_codes );
                $friendly_labels = $this->woo_contifico->settings['bodegas_items_labels'] ?? [];
                if ( ! is_array( $friendly_labels ) ) {
                        $friendly_labels = [];
                }

                $summary = [];

                foreach ( $stock_by_warehouse as $warehouse_id => $quantity ) {
                        $warehouse_id   = (string) $warehouse_id;
                        $warehouse_code = isset( $warehouses_map[ $warehouse_id ] ) ? (string) $warehouses_map[ $warehouse_id ] : $warehouse_id;
                        $extra_label    = '' !== $warehouse_id && $warehouse_code !== $warehouse_id ? $warehouse_id : '';

                        if ( $apply_filter ) {
                                $normalized_id   = strtoupper( $warehouse_id );
                                $normalized_code = strtoupper( $warehouse_code );

                                if (
                                        ! in_array( $normalized_id, $visible_codes, true )
                                        && ! in_array( $normalized_code, $visible_codes, true )
                                ) {
                                        continue;
                                }
                        }

                        $friendly_label = '';
                        if ( '' !== $warehouse_code && isset( $friendly_labels[ strtoupper( $warehouse_code ) ] ) ) {
                                $friendly_label = (string) $friendly_labels[ strtoupper( $warehouse_code ) ];
                        }
                        if ( '' === $friendly_label && '' !== $warehouse_code ) {
                                $friendly_label = $warehouse_code;
                        }

                        $summary[] = [
                                'location_id'    => $warehouse_id,
                                'location_label' => $friendly_label,
                                'warehouse_code' => $extra_label,
                                'quantity'       => (float) $quantity,
                        ];
                }

                return $summary;
        }

        /**
         * Synchronize a single product identified by SKU.
         *
         * @since 4.2.0
         *
         * @param string $sku
         * @param bool   $log_inventory_movement
         *
         * @return array
         * @throws Exception
         */
        private function sync_single_product_by_sku( string $sku, bool $log_inventory_movement = true, bool $reset_identifier_on_mismatch = false ) : array {

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

                return $this->execute_single_product_sync( $resolved_product, $environment, $sku, $log_inventory_movement, $reset_identifier_on_mismatch );
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
         * @param array $warehouses_map
         * @param string $default_warehouse_id
         *
         * @return array{
         *     stock_updated:bool,
         *     price_updated:bool,
         *     meta_updated:bool,
         *     outofstock:bool,
         *     previous_stock:?int,
         *     new_stock:?int,
         *     previous_price:?float,
         *     new_price:?float,
         *     previous_identifier:?string,
         *     new_identifier:?string
         * }
         */
        private function update_product_from_contifico_data(
                array $product_entry,
                array &$result,
                array &$debug_log_entries,
                array &$product_stock_cache,
                array &$warehouse_id_cache,
                array $warehouses_map,
                string $default_warehouse_id,
                bool $force_refresh = false
        ) : array {

                $changes = [
                        'stock_updated'        => false,
                        'price_updated'        => false,
                        'meta_updated'         => false,
                        'outofstock'           => false,
                        'previous_stock'       => null,
                        'new_stock'            => null,
                        'previous_price'       => null,
                        'new_price'            => null,
                        'previous_identifier'  => null,
                        'new_identifier'       => null,
                ];

                if ( ! isset( $product_entry['product'] ) || ! is_a( $product_entry['product'], 'WC_Product' ) ) {
                        return $changes;
                }

                $product           = $product_entry['product'];
                $product_cache_key = isset( $product_entry['id'] ) ? (string) $product_entry['id'] : '';

                if ( '' === $product_cache_key ) {
                        return $changes;
                }

                if ( $force_refresh ) {
                        $product_stock_cache[ $product_cache_key ] = $this->contifico->get_product_stock_by_warehouses( $product_cache_key, true );
                }
                elseif ( ! array_key_exists( $product_cache_key, $product_stock_cache ) ) {
                        $product_stock_cache[ $product_cache_key ] = $this->contifico->get_product_stock_by_warehouses( $product_cache_key );
                }

                $stock_by_warehouse = (array) $product_stock_cache[ $product_cache_key ];

                $managing_stock = method_exists( $product, 'managing_stock' ) ? (bool) $product->managing_stock() : (bool) $product->get_manage_stock();

                if ( $managing_stock ) {
                        $old_stock                    = (int) $product->get_stock_quantity();
                        $changes['previous_stock']    = $old_stock;
                        $changes['new_stock']         = $old_stock;

                        $new_stock = 0;

                        if ( '' !== $default_warehouse_id ) {
                                if ( isset( $stock_by_warehouse[ $default_warehouse_id ] ) ) {
                                        $new_stock = (int) $stock_by_warehouse[ $default_warehouse_id ];
                                }

                                if ( 0 === $new_stock && isset( $warehouses_map[ $default_warehouse_id ] ) ) {
                                        $default_warehouse_code = (string) $warehouses_map[ $default_warehouse_id ];

                                        if ( '' !== $default_warehouse_code && isset( $stock_by_warehouse[ $default_warehouse_code ] ) ) {
                                                $new_stock = (int) $stock_by_warehouse[ $default_warehouse_code ];
                                        }
                                }
                        }

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
                                $changes['new_stock']     = $new_stock;
                        }
                        else {
                                $changes['new_stock'] = $old_stock;
                        }
                }
                else {
                        $stock_quantity = $product->get_stock_quantity();

                        if ( '' !== $stock_quantity ) {
                                $stock_quantity             = (int) $stock_quantity;
                                $changes['previous_stock']  = $stock_quantity;
                                $changes['new_stock']       = $stock_quantity;
                        }
                }

               $updated_price = false;
               $current_price = (float) $product->get_price();

               $changes['previous_price'] = $current_price;
               $changes['new_price']      = $current_price;

               if ( $this->woo_contifico->settings['sync_price'] !== 'no' ) {
                       $price_key = $this->woo_contifico->settings['sync_price'];

                       if ( isset( $product_entry[ $price_key ] ) ) {
                               $new_price = (float) $product_entry[ $price_key ];

                               $changes['new_price'] = $new_price;

                               if ( $new_price !== $current_price ) {
                                       $product->set_regular_price( $new_price );
                                       $updated_price = true;
                               }
                       }
               }

                if ( $updated_price ) {
                        $changes['price_updated'] = true;
                }

                $current_meta_id = (string) $product->get_meta( self::PRODUCT_ID_META_KEY, true );
                $changes['previous_identifier'] = '' !== $current_meta_id ? $current_meta_id : null;
                $changes['new_identifier']      = $product_cache_key;

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
                $visible_codes = $this->get_item_visible_warehouse_codes();
                $apply_filter  = ! empty( $visible_codes );

                foreach ( $stock_by_warehouse as $warehouse_id => $quantity ) {
                        $warehouse_code = isset( $warehouses_map[ $warehouse_id ] ) ? (string) $warehouses_map[ $warehouse_id ] : (string) $warehouse_id;

                        if ( $apply_filter ) {
                                $normalized_id   = strtoupper( (string) $warehouse_id );
                                $normalized_code = strtoupper( $warehouse_code );

                                if (
                                        ! in_array( $normalized_id, $visible_codes, true )
                                        && ! in_array( $normalized_code, $visible_codes, true )
                                ) {
                                        continue;
                                }
                        }

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
         * Build a summary entry for products updated during batch synchronization.
         *
         * @since 4.2.0
         *
         * @param array $product_entry
         * @param array $changes
         *
         * @return array|null
         */
        private function build_batch_sync_summary_entry( array $product_entry, array $changes ) : ?array {

                if ( empty( $changes ) ) {
                        return null;
                }

                if ( ! isset( $product_entry['product'] ) || ! is_a( $product_entry['product'], 'WC_Product' ) ) {
                        return null;
                }

                if ( empty( $changes['stock_updated'] ) && empty( $changes['price_updated'] ) && empty( $changes['meta_updated'] ) && empty( $changes['outofstock'] ) ) {
                        return null;
                }

                $product       = $product_entry['product'];
                $contifico_id  = isset( $product_entry['id'] ) ? (string) $product_entry['id'] : '';
                $summary_changes = [];

                if ( ! empty( $changes['stock_updated'] ) || ! empty( $changes['outofstock'] ) ) {
                        $summary_changes['stock'] = [
                                'previous'   => $changes['previous_stock'] ?? null,
                                'current'    => $changes['new_stock'] ?? null,
                                'outofstock' => ! empty( $changes['outofstock'] ),
                        ];
                }

                if ( ! empty( $changes['price_updated'] ) ) {
                        $summary_changes['price'] = [
                                'previous' => $changes['previous_price'] ?? null,
                                'current'  => $changes['new_price'] ?? null,
                        ];
                }

                if ( ! empty( $changes['meta_updated'] ) ) {
                        $summary_changes['identifier'] = [
                                'previous' => $changes['previous_identifier'] ?? null,
                                'current'  => $changes['new_identifier'] ?? null,
                        ];
                }

                if ( empty( $summary_changes ) ) {
                        return null;
                }

                return [
                        'product_id'   => (int) $product->get_id(),
                        'product_name' => $product->get_name(),
                        'sku'          => (string) $product->get_sku(),
                        'contifico_id' => $contifico_id,
                        'changes'      => $summary_changes,
                ];
        }

        /**
         * Generate a consistent key for summary entries to avoid duplicates during aggregation.
         *
         * @since 4.2.0
         *
         * @param array $summary_entry
         *
         * @return string
         */
        private function generate_batch_sync_summary_key( array $summary_entry ) : string {

                if ( ! empty( $summary_entry['contifico_id'] ) ) {
                        return 'contifico:' . $summary_entry['contifico_id'];
                }

                if ( ! empty( $summary_entry['product_id'] ) ) {
                        return 'product:' . (string) $summary_entry['product_id'];
                }

                if ( ! empty( $summary_entry['sku'] ) ) {
                        return 'sku:' . (string) $summary_entry['sku'];
                }

                if ( function_exists( 'wp_generate_uuid4' ) ) {
                        return 'item:' . wp_generate_uuid4();
                }

                return 'item:' . uniqid( 'summary_', true );
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
        private function get_contifico_product_data_for_product( $product, string $sku, bool $force_refresh = false ) {

                $contifico_id = $this->resolve_contifico_product_identifier(
                        $product,
                        ! ( is_a( $product, 'WC_Product' ) && $product->is_type( 'variation' ) )
                );

                if ( '' !== $contifico_id ) {
                        $product_data = $this->get_contifico_product_data_by_id( $contifico_id, $force_refresh );

                        if ( ! empty( $product_data ) ) {
                                return $product_data;
                        }
                }

                return $this->get_contifico_product_data_by_sku( $sku, $force_refresh );
        }

        /**
         * Retrieve the Contífico identifier stored on a WooCommerce product entry.
         *
         * Uses the variation parent identifier if the variation does not store its own value
         * and inheritance is allowed.
         *
         * @since 4.2.1
         *
         * @param WC_Product|mixed $product
         * @param bool             $inherit_from_parent Whether to read the parent identifier for variations.
         *
         * @return string
         */
        private function resolve_contifico_product_identifier( $product, bool $inherit_from_parent = true ) : string {

                if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
                        return '';
                }

                $contifico_id = (string) $product->get_meta( self::PRODUCT_ID_META_KEY, true );

                if ( '' !== $contifico_id ) {
                        return $contifico_id;
                }

                if ( ! $inherit_from_parent || ! $product->is_type( 'variation' ) ) {
                        return '';
                }

                $parent_id = $product->get_parent_id();

                if ( ! $parent_id ) {
                        return '';
                }

                $parent_product = wc_get_product( $parent_id );

                if ( ! $parent_product || ! is_a( $parent_product, 'WC_Product' ) ) {
                        return '';
                }

                return (string) $parent_product->get_meta( self::PRODUCT_ID_META_KEY, true );
        }

        /**
         * Remove the stored Contífico identifier from a WooCommerce product.
         *
         * Also clears the parent identifier for variations to avoid inherited mismatches.
         *
         * @since 4.1.52
         *
         * @param WC_Product $product
         *
         * @return bool True when at least one identifier was removed.
         */
        private function clear_contifico_product_identifier( $product ) : bool {

                if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
                        return false;
                }

                $cleared = false;

                if ( '' !== (string) $product->get_meta( self::PRODUCT_ID_META_KEY, true ) ) {
                        $product->delete_meta_data( self::PRODUCT_ID_META_KEY );
                        $product->save();
                        $cleared = true;
                }

                if ( $product->is_type( 'variation' ) ) {
                        $parent_id = $product->get_parent_id();

                        if ( $parent_id ) {
                                $parent_product = wc_get_product( $parent_id );

                                if (
                                        $parent_product
                                        && is_a( $parent_product, 'WC_Product' )
                                        && '' !== (string) $parent_product->get_meta( self::PRODUCT_ID_META_KEY, true )
                                ) {
                                        $parent_product->delete_meta_data( self::PRODUCT_ID_META_KEY );
                                        $parent_product->save();
                                        $cleared = true;
                                }
                        }
                }

                return $cleared;
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
        private function get_contifico_product_data_by_id( string $contifico_id, bool $force_refresh = false ) {

                $contifico_id = trim( $contifico_id );

                if ( '' === $contifico_id ) {
                        return null;
                }

                $product = $this->contifico->get_product_by_id( $contifico_id, $force_refresh );

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
        private function get_contifico_product_data_by_sku( string $sku, bool $force_refresh = false ) {

                $sku = trim( $sku );

                if ( '' === $sku ) {
                        return null;
                }

                try {
                        $contifico_id = (string) $this->contifico->get_product_id( $sku );
                }
                catch ( Exception $exception ) {
                        $contifico_id = '';
                }

                if ( '' !== $contifico_id ) {
                        $product = $this->contifico->get_product_by_id( $contifico_id, $force_refresh );

                        if ( ! empty( $product ) && is_array( $product ) ) {
                                $contifico_sku = isset( $product['sku'] ) ? (string) $product['sku'] : '';

                                if ( '' === $contifico_sku || $contifico_sku === $sku ) {
                                        return $product;
                                }
                        }
                }

                $inventory = $this->contifico->get_products();

                if ( is_array( $inventory ) ) {
                        $product = $this->locate_product_in_inventory_by_sku( $inventory, $sku );

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
         * @param array  $inventory
         * @param string $sku
         *
         * @return array|null
         */
        private function locate_product_in_inventory_by_sku( array $inventory, string $sku ) {

                $sku = trim( $sku );

                if ( '' === $sku || empty( $inventory ) ) {
                        return null;
                }

                foreach ( $inventory as $product ) {
                        if ( ! is_array( $product ) ) {
                                continue;
                        }

                        $product_sku = isset( $product['sku'] ) ? (string) $product['sku'] : '';

                        if ( '' === $product_sku ) {
                                continue;
                        }

                        if ( $product_sku === $sku ) {
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
         * Log Contífico API transactions when API logging is enabled.
         *
         * @since 4.1.65
         */
        private function log_api_transaction( string $action, array $request, $response = null ) : void {

                $logging_enabled = ! empty( $this->woo_contifico->settings['activar_registro'] );

                if ( ! $logging_enabled || empty( $this->log_path ) ) {
                        return;
                }

                $entry = [
                        'action'  => $action,
                        'request' => $request,
                ];

                if ( null !== $response ) {
                        $entry['response'] = $response;
                }

                $json_message = wp_json_encode( $entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK );

                if ( false === $json_message ) {
                        return;
                }

                $log_time = current_time( 'mysql' );

                error_log( "[{$log_time}]: {$json_message}" . PHP_EOL, 3, $this->log_path );

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
         * Determine if two SKU representations should be considered equivalent.
         *
         * @since 4.2.2
         *
         * @param string $first
         * @param string $second
         * @return bool
         */
        private function skus_are_equivalent( string $first, string $second ) : bool {

                $first  = trim( $first );
                $second = trim( $second );

                if ( '' === $first || '' === $second ) {
                        return false;
                }

                return $first === $second;
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

                $product_id = wc_get_product_id_by_sku( $sku );

                return $product_id ? (int) $product_id : 0;
        }

        /**
         * Resolve the WooCommerce product that should receive stock updates for a Contífico SKU.
         *
         * @since 4.1.7
         *
         * @param WC_Product $product
         * @param string     $contifico_sku
         * @param string     $contifico_id
         * @return WC_Product|null
         */
        private function resolve_wc_product_for_contifico_sku( $product, string $contifico_sku, string $contifico_id = '' ) {

                if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
                        return null;
                }

                if ( $product->is_type( 'variation' ) ) {
                        return $product;
                }

                if ( $product->is_type( 'variable' ) ) {
                        $variation = $this->locate_variation_for_contifico_sku( $product, $contifico_sku, $contifico_id );

                        if ( $variation ) {
                                return $variation;
                        }
                }

                return $product;
        }

        /**
         * Locate a variation candidate to synchronize when the parent product has no Contífico match.
         *
         * @since 4.2.1
         *
         * @param WC_Product $parent_product
         * @param string     $lookup_sku
         * @param bool       $force_refresh
         * @return array|null
         */
        private function resolve_variation_sync_candidate( $parent_product, string $lookup_sku, bool $force_refresh = false ) {

                if ( ! $parent_product || ! is_a( $parent_product, 'WC_Product' ) || ! $parent_product->is_type( 'variable' ) ) {
                        return null;
                }

                $children = (array) $parent_product->get_children();

                if ( empty( $children ) ) {
                        return null;
                }

                $lookup_sku        = trim( $lookup_sku );
                $lookup_candidates = '' !== $lookup_sku ? [ $lookup_sku ] : [];

                foreach ( $children as $child_id ) {
                        $variation = wc_get_product( $child_id );

                        if ( ! $variation || ! is_a( $variation, 'WC_Product' ) ) {
                                continue;
                        }

                        $variation_identifier = $this->resolve_contifico_product_identifier( $variation, false );

                        if ( '' !== $variation_identifier ) {
                                $variation_data = $this->get_contifico_product_data_by_id( $variation_identifier, $force_refresh );

                                if ( ! empty( $variation_data ) && is_array( $variation_data ) ) {
                                        $variation_sku = (string) $variation->get_sku();

                                        return [
                                                'product'           => $variation,
                                                'contifico_product' => $variation_data,
                                                'contifico_id'      => isset( $variation_data['codigo'] )
                                                        ? (string) $variation_data['codigo']
                                                        : $variation_identifier,
                                                'contifico_sku'     => isset( $variation_data['sku'] )
                                                        ? (string) $variation_data['sku']
                                                        : ( '' !== $variation_sku ? $variation_sku : '' ),
                                                'lookup_sku'        => '' !== $variation_sku
                                                        ? $variation_sku
                                                        : ( isset( $variation_data['sku'] ) ? (string) $variation_data['sku'] : $lookup_sku ),
                                        ];
                                }
                        }

                        $variation_sku  = (string) $variation->get_sku();
                        $candidate_skus = [];

                        if ( '' !== $variation_sku ) {
                                $candidate_skus[] = $variation_sku;
                        }

                        foreach ( $lookup_candidates as $candidate_lookup ) {
                                if ( '' !== $candidate_lookup && $candidate_lookup !== $variation_sku ) {
                                        $candidate_skus[] = $candidate_lookup;
                                }
                        }

                        $candidate_skus = array_values( array_unique( array_filter( $candidate_skus, 'strlen' ) ) );

                        foreach ( $candidate_skus as $candidate_sku ) {
                                $variation_data = $this->get_contifico_product_data_by_sku( $candidate_sku, $force_refresh );

                                if ( empty( $variation_data ) || ! is_array( $variation_data ) ) {
                                        continue;
                                }

                                $contifico_sku = isset( $variation_data['sku'] ) ? (string) $variation_data['sku'] : $candidate_sku;

                                return [
                                        'product'           => $variation,
                                        'contifico_product' => $variation_data,
                                        'contifico_id'      => isset( $variation_data['codigo'] )
                                                ? (string) $variation_data['codigo']
                                                : '',
                                        'contifico_sku'     => $contifico_sku,
                                        'lookup_sku'        => '' !== $variation_sku ? $variation_sku : $contifico_sku,
                                ];
                        }
                }

                return null;
        }

        /**
         * Locate a variation that matches a Contífico SKU pattern.
         *
         * @since 4.1.7
         *
         * @param WC_Product $parent_product
         * @param string     $contifico_sku
         * @param string     $contifico_id
         * @return WC_Product|null
         */
        private function locate_variation_for_contifico_sku( $parent_product, string $contifico_sku, string $contifico_id = '' ) {

                if ( ! $parent_product || ! is_a( $parent_product, 'WC_Product' ) || ! $parent_product->is_type( 'variable' ) ) {
                        return null;
                }

                $contifico_sku = trim( $contifico_sku );
                $contifico_id  = trim( $contifico_id );

                if ( '' === $contifico_sku ) {
                        return null;
                }

                foreach ( (array) $parent_product->get_children() as $child_id ) {
                        $variation = wc_get_product( $child_id );

                        if ( ! $variation ) {
                                continue;
                        }

                        if ( '' !== $contifico_id ) {
                                $stored_identifier = (string) $variation->get_meta( self::PRODUCT_ID_META_KEY, true );

                                if ( '' !== $stored_identifier && $stored_identifier !== $contifico_id ) {
                                        continue;
                                }
                        }

                        $variation_sku = trim( (string) $variation->get_sku() );

                        if ( '' === $variation_sku ) {
                                if ( '' !== $contifico_id ) {
                                        $stored_identifier = (string) $variation->get_meta( self::PRODUCT_ID_META_KEY, true );

                                        if ( '' !== $stored_identifier && $stored_identifier === $contifico_id ) {
                                                return $variation;
                                        }
                                }

                                continue;
                        }

                        if ( $variation_sku === $contifico_sku ) {
                                return $variation;
                        }
                }

                return null;
        }

        /**
         * Locate a variation that already stores a Contífico identifier.
         *
         * @since 4.2.3
         *
         * @param WC_Product $parent_product
         * @param string     $contifico_id
         * @return WC_Product|null
         */
        private function locate_variation_for_contifico_id( $parent_product, string $contifico_id ) {

                if ( ! $parent_product || ! is_a( $parent_product, 'WC_Product' ) || ! $parent_product->is_type( 'variable' ) ) {
                        return null;
                }

                $contifico_id = trim( $contifico_id );

                if ( '' === $contifico_id ) {
                        return null;
                }

                foreach ( (array) $parent_product->get_children() as $child_id ) {
                        $variation = wc_get_product( $child_id );

                        if ( ! $variation || ! is_a( $variation, 'WC_Product' ) ) {
                                continue;
                        }

                        $stored_identifier = (string) $variation->get_meta( self::PRODUCT_ID_META_KEY, true );

                        if ( '' === $stored_identifier ) {
                                continue;
                        }

                        if ( $stored_identifier === $contifico_id ) {
                                return $variation;
                        }
                }

                return null;
        }

        /**
         * Ensure the resolved WooCommerce product matches the Contífico identifier being synchronized.
         *
         * @since 4.2.3
         *
         * @param WC_Product|mixed $product
         * @param string           $contifico_id
         * @return WC_Product|null
         */
        private function ensure_product_matches_contifico_identifier( $product, string $contifico_id ) {

                if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
                        return null;
                }

                $contifico_id = trim( $contifico_id );

                if ( '' === $contifico_id ) {
                        return $product;
                }

                if ( $product->is_type( 'variation' ) ) {
                        $stored_identifier = (string) $product->get_meta( self::PRODUCT_ID_META_KEY, true );

                        if ( '' === $stored_identifier || $stored_identifier === $contifico_id ) {
                                return $product;
                        }

                        $parent_id = $product->get_parent_id();

                        if ( $parent_id ) {
                                $parent_product = wc_get_product( $parent_id );

                                if ( $parent_product && is_a( $parent_product, 'WC_Product' ) && $parent_product->is_type( 'variable' ) ) {
                                        $matched_variation = $this->locate_variation_for_contifico_id( $parent_product, $contifico_id );

                                        if ( $matched_variation ) {
                                                return $matched_variation;
                                        }
                                }
                        }

                        return $product;
                }

                if ( $product->is_type( 'variable' ) ) {
                        $matched_variation = $this->locate_variation_for_contifico_id( $product, $contifico_id );

                        if ( $matched_variation ) {
                                return $matched_variation;
                        }

                        return $product;
                }

                return $product;
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
                $order_id      = $order->get_id();
                $stock_reduced = wc_string_to_bool( $order->get_meta( '_woo_contifico_stock_reduced', true ) );

                if (
                        ! $stock_reduced &&
                        isset( $this->woo_contifico->settings['bodega_facturacion'] ) &&
                        ! empty( $this->woo_contifico->settings['bodega_facturacion'] )
                ) {
                        $origin_code              = isset( $this->woo_contifico->settings['bodega'] ) ? (string) $this->woo_contifico->settings['bodega'] : '';
                        $destination_code         = isset( $this->woo_contifico->settings['bodega_facturacion'] ) ? (string) $this->woo_contifico->settings['bodega_facturacion'] : '';
                        $default_origin_id        = (string) ( $this->contifico->get_id_bodega( $origin_code ) ?? '' );
                        $id_destination_warehouse = (string) ( $this->contifico->get_id_bodega( $destination_code ) ?? '' );
                        $env                      = ( (int) $this->woo_contifico->settings['ambiente'] === WOO_CONTIFICO_TEST ) ? 'test' : 'prod';
                        $movement_entries         = [];
                        $grouped_items            = $this->group_order_items_by_location_context( $order, $order->get_items(), $origin_code, $default_origin_id );
                        $processed_groups         = 0;
                        $successful_groups        = 0;

                        foreach ( $grouped_items as $group ) {
                                $group_context  = $group['context'];
                                $transfer_stock = [
                                        'tipo'              => 'TRA',
                                        'fecha'             => date( 'd/m/Y' ),
                                        'bodega_id'         => $group_context['id'],
                                        'bodega_destino_id' => $id_destination_warehouse,
                                        'detalles'          => [],
                                        'descripcion'       => sprintf(
                                                __( 'Referencia: Tienda online Orden %d', $this->plugin_name ),
                                                $order_id
                                        ),
                                        'codigo_interno'    => null,
                                        'pos'               => $this->woo_contifico->settings["{$env}_api_token"],
                                ];

                                $group_entries = [];

                                foreach ( $group['items'] as $index => $item ) {
                                        $wc_product = $item->get_product();

                                        if ( ! $wc_product ) {
                                                continue;
                                        }

                                        $item_context = isset( $group['item_contexts'][ $index ] ) ? $group['item_contexts'][ $index ] : $group_context;
                                        $sku          = (string) $wc_product->get_sku();
                                        $product_id   = $this->contifico->get_product_id( $sku );
                                        $quantity     = isset( $group['item_quantities'][ $index ] ) ? (float) $group['item_quantities'][ $index ] : (float) $item->get_quantity();

                                       $transfer_stock['detalles'][] = [
                                               'producto_id' => $product_id,
                                               'cantidad'    => $quantity,
                                       ];

                                       $movement_reason = $order_id
                                               ? sprintf( __( 'despacho de la orden #%d', $this->plugin_name ), $order_id )
                                               : __( 'despacho de pedido en línea', $this->plugin_name );

                                                $group_entries[] = $this->build_inventory_movement_entry( [
                                                        'order_id'      => $order_id,
                                                        'event_type'    => 'egreso',
                                                        'product_id'    => $product_id,
                                                        'wc_product_id' => $wc_product->get_id(),
                                                        'sku'           => $sku,
                                                        'product_name'  => $wc_product->get_name(),
                                                        'quantity'      => $quantity,
                                                        'warehouses'    => [
                                                                'from' => [
                                                                        'id'             => (string) $group_context['id'],
                                                                        'code'           => (string) $group_context['code'],
                                                                        'label'          => $group_context['label'],
                                                                        'location_id'    => (string) $group_context['location_id'],
                                                                        'location_label' => (string) $group_context['location_label'],
                                                                        'mapped'         => (bool) $group_context['mapped'],
                                                                ],
                                                                'to'   => [
                                                                        'id'    => (string) $id_destination_warehouse,
                                                                        'code'  => (string) $destination_code,
                                                                        'label' => $destination_code,
                                                                ],
                                                        ],
                                                        'order_status'  => $order->get_status(),
                                                        'order_trigger' => 'woocommerce_reduce_order_stock',
                                                        'context'       => 'transfer',
                                                        'order_source'  => 'order',
                                                        'reason'        => $movement_reason,
                                                'order_item_id' => $item->get_id(),
                                                'sync_type'     => 'global',
                                                'location'      => [
                                                        'id'    => isset( $item_context['location_id'] ) ? $item_context['location_id'] : $group_context['location_id'],
                                                        'label' => isset( $item_context['location_label'] ) ? $item_context['location_label'] : $group_context['location_label'],
                                                ],
                                        ] );
                                }

                                if ( empty( $transfer_stock['detalles'] ) ) {
                                        continue;
                                }

                                $processed_groups++;
                                $status         = 'pending';
                                $reference_code = '';
                                $error_message  = '';
                                $location_label = $this->describe_inventory_location_for_note( $group_context );
                                $api_request    = $transfer_stock;

                                try {
                                        $result        = $this->contifico->transfer_stock( json_encode( $transfer_stock ) );
                                        $reference_code = isset( $result['codigo'] ) ? (string) $result['codigo'] : '';
                                        $status         = 'success';
                                        $successful_groups++;
                                        $order->add_order_note( sprintf(
                                                __( '<b>Contífico: </b><br> Inventario trasladado desde %1$s a la bodega web. Código: %2$s', $this->plugin_name ),
                                                $location_label,
                                                $reference_code
                                        ) );
                                        $this->log_api_transaction(
                                                'transfer_stock_dispatch',
                                                [
                                                        'order_id'       => $order_id,
                                                        'payload'        => $api_request,
                                                        'location_label' => $location_label,
                                                ],
                                                $result
                                        );
                                } catch ( Exception $exception ) {
                                        $status        = 'error';
                                        $error_message = $exception->getMessage();
                                        $order->add_order_note( sprintf(
                                                __( '<b>Contífico retornó un error al transferir inventario desde %1$s</b><br>%2$s', $this->plugin_name ),
                                                $location_label,
                                                $error_message
                                        ) );
                                        $this->log_api_transaction(
                                                'transfer_stock_dispatch_error',
                                                [
                                                        'order_id'       => $order_id,
                                                        'payload'        => $api_request,
                                                        'location_label' => $location_label,
                                                ],
                                                $error_message
                                        );
                                }

                                if ( ! empty( $group_entries ) ) {
                                        $movement_entries = array_merge(
                                                $movement_entries,
                                                $this->finalize_inventory_movement_entries( $group_entries, $status, $reference_code, $error_message )
                                        );
                                }
                        }

                        if ( $processed_groups > 0 && $successful_groups === $processed_groups ) {
                                $order->update_meta_data( '_woo_contifico_stock_reduced', wc_bool_to_string( true ) );
                                $order->save();
                        }

                        if ( ! empty( $movement_entries ) ) {
                                $this->append_inventory_movement_entries( $movement_entries );
                        }

                        if ( $order_id ) {
                                unset( $this->preferred_warehouse_allocations[ $order_id ] );
                                unset( $this->preferred_item_allocations[ $order_id ] );
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
                if (
                        wc_string_to_bool( $order->get_meta( '_woo_contifico_stock_reduced', true ) ) &&
                        isset( $this->woo_contifico->settings['bodega_facturacion'] )
                ) {
                        # Get refund data
                        $refund = null;
                        if ( ! empty( $refund_id ) ) {
                                $refund = new WC_Order_Refund( $refund_id );
                        }

                        $origin_code            = isset( $this->woo_contifico->settings['bodega_facturacion'] ) ? (string) $this->woo_contifico->settings['bodega_facturacion'] : '';
                        $destination_code       = isset( $this->woo_contifico->settings['bodega'] ) ? (string) $this->woo_contifico->settings['bodega'] : '';
                        $id_origin_warehouse    = (string) ( $this->contifico->get_id_bodega( $origin_code ) ?? '' );
                        $default_destination_id = (string) ( $this->contifico->get_id_bodega( $destination_code ) ?? '' );
                        $env                    = ( (int) $this->woo_contifico->settings['ambiente'] === WOO_CONTIFICO_TEST ) ? 'test' : 'prod';

                        # Get items to restore
                        $items = empty( $refund_id ) ? $order->get_items() : $refund->get_items();
                        if ( empty( $items ) && ! empty( $refund ) ) {
                                $items  = $order->get_items();
                                $refund = null;
                        }

                        $movement_entries  = [];
                        $trigger           = empty( $refund_id ) ? 'woocommerce_restore_order_stock' : 'woocommerce_order_refunded';
                        $order_source      = empty( $refund_id ) ? 'order' : 'refund';
                        $reason_label      = empty( $refund )
                                ? ( empty( $refund_id ) ? 'cancelación de la orden' : "reembolso #{$refund_id}" )
                                : "reembolso total o parcial #{$refund_id}";
                        $grouped_items     = $this->group_restore_items_from_movements( $order, $items, $destination_code, $default_destination_id, $origin_code, $id_origin_warehouse );

                        $logging_enabled = ! empty( $this->woo_contifico->settings['activar_registro'] );
                        $log_noted       = (bool) $order->get_meta( '_woo_contifico_restore_log_destination_noted', true );

                        if ( $logging_enabled && ! empty( $this->log_path ) && ! $log_noted ) {
                                $log_destination = ! empty( $this->log_route ) ? $this->log_route : $this->log_path;

                                $order->add_order_note( sprintf(
                                        __( '<b>Contífico:</b><br>Los intentos de devolución se están registrando en %s', $this->plugin_name ),
                                        $log_destination
                                ) );

                                $this->log_api_transaction(
                                        'transfer_stock_restore_log_destination',
                                        [
                                                'order_id'      => $order_id,
                                                'trigger'       => $trigger,
                                                'reason'        => $reason_label,
                                                'log_path'      => $this->log_path,
                                                'log_url'       => $this->log_route,
                                                'logging_state' => 'enabled',
                                        ]
                                );

                                $order->update_meta_data( '_woo_contifico_restore_log_destination_noted', true );
                                $order->save();
                        }

                        if ( empty( $grouped_items ) ) {
                                $grouped_items = $this->group_order_items_by_location_context( $order, $items, $destination_code, $default_destination_id );
                        }
                        $processed_groups   = 0;
                        $successful_groups  = 0;
                        $origin_stock       = [];
                        $product_id_cache   = [];
                        $restore_plan_groups = [];

                        $resolve_product_id = function ( WC_Order_Item_Product $item, WC_Product $wc_product ) use ( &$product_id_cache, $order ) : string {
                                $sku = (string) $wc_product->get_sku();

                                if ( isset( $product_id_cache[ $sku ] ) ) {
                                        return $product_id_cache[ $sku ];
                                }

                                try {
                                        $product_id_cache[ $sku ] = (string) $this->contifico->get_product_id( $sku );
                                } catch ( Exception $exception ) {
                                        $order->add_order_note( sprintf(
                                                __( '<b>Contífico:</b><br>No se pudo obtener el identificador de Contífico para el SKU %1$s: %2$s', $this->plugin_name ),
                                                $sku,
                                                $exception->getMessage()
                                        ) );

                                        $product_id_cache[ $sku ] = '';
                                }

                                return $product_id_cache[ $sku ];
                        };

                        $product_ids_for_stock           = [];
                        $origin_codes_for_stock          = [];
                        $origin_code_id_map              = [];
                        $stock_lookup_products           = [];
                        $stock_lookup_origin_breakdown   = [];

                        foreach ( $grouped_items as $group ) {
                                foreach ( $group['items'] as $item ) {
                                        if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
                                                continue;
                                        }

                                        $wc_product = $item->get_product();

                                        if ( ! $wc_product ) {
                                                continue;
                                        }

                                        $product_id = $resolve_product_id( $item, $wc_product );
                                        $sku        = (string) $wc_product->get_sku();

                                        if ( '' !== $product_id ) {
                                                $product_ids_for_stock[] = $product_id;

                                                if ( ! isset( $stock_lookup_products[ $product_id ] ) ) {
                                                        $stock_lookup_products[ $product_id ] = [
                                                                'skus'                      => [],
                                                                'order_item_ids'            => [],
                                                                'total_requested_quantity'  => 0.0,
                                                                'item_quantities'           => [],
                                                        ];
                                                }

                                                $stock_lookup_products[ $product_id ]['skus'][ $sku ]             = true;
                                                $stock_lookup_products[ $product_id ]['order_item_ids'][]         = $item->get_id();
                                        }
                                }

                                $origin_context = isset( $group['origin_context'] ) && is_array( $group['origin_context'] ) ? $group['origin_context'] : [];
                                $group_origin_code = isset( $origin_context['code'] ) ? (string) $origin_context['code'] : '';
                                $group_origin_id   = isset( $origin_context['id'] ) ? (string) $origin_context['id'] : '';

                                if ( '' === $group_origin_code ) {
                                        $group_origin_code = $origin_code;
                                }

                                if ( '' === $group_origin_id ) {
                                        $group_origin_id = $id_origin_warehouse;
                                }

                                if ( '' !== $group_origin_code ) {
                                        $origin_codes_for_stock[]              = $group_origin_code;
                                        $origin_code_id_map[ $group_origin_code ] = $group_origin_id;
                                }

                                $group_context    = isset( $group['context'] ) && is_array( $group['context'] ) ? $group['context'] : [];
                                $origin_context   = isset( $group['origin_context'] ) && is_array( $group['origin_context'] ) ? $group['origin_context'] : [];
                                $group_items_plan = [];

                                foreach ( $group['items'] as $index => $group_item ) {
                                        if ( ! is_a( $group_item, 'WC_Order_Item_Product' ) ) {
                                                continue;
                                        }

                                        $wc_product = $group_item->get_product();
                                        $quantity   = isset( $group['item_quantities'][ $index ] )
                                                ? (float) $group['item_quantities'][ $index ]
                                                : (float) $group_item->get_quantity();

                                        if ( ! $wc_product ) {
                                                continue;
                                        }

                                        $group_items_plan[] = [
                                                'order_item_id' => $group_item->get_id(),
                                                'product_id'    => $resolve_product_id( $group_item, $wc_product ),
                                                'sku'           => (string) $wc_product->get_sku(),
                                                'quantity'      => $quantity,
                                                'price'         => (float) $wc_product->get_price(),
                                                'origin_context'=> isset( $group['origin_contexts'][ $index ] ) && is_array( $group['origin_contexts'][ $index ] )
                                                        ? $group['origin_contexts'][ $index ]
                                                        : $origin_context,
                                        ];

                                        $resolved_product_id = $group_items_plan[ array_key_last( $group_items_plan ) ]['product_id'];
                                        $resolved_sku         = $group_items_plan[ array_key_last( $group_items_plan ) ]['sku'];

                                        if ( '' !== $resolved_product_id ) {
                                                $stock_lookup_products[ $resolved_product_id ]['total_requested_quantity'] += $quantity;
                                                $stock_lookup_products[ $resolved_product_id ]['item_quantities'][]         = [
                                                        'order_item_id' => $group_item->get_id(),
                                                        'sku'           => $resolved_sku,
                                                        'quantity'      => $quantity,
                                                        'origin_code'   => $group_origin_code,
                                                ];

                                                if ( '' !== $group_origin_code ) {
                                                        if ( ! isset( $stock_lookup_origin_breakdown[ $group_origin_code ] ) ) {
                                                                $stock_lookup_origin_breakdown[ $group_origin_code ] = [];
                                                        }

                                                        if ( ! isset( $stock_lookup_origin_breakdown[ $group_origin_code ][ $resolved_product_id ] ) ) {
                                                                $stock_lookup_origin_breakdown[ $group_origin_code ][ $resolved_product_id ] = 0.0;
                                                        }

                                                        $stock_lookup_origin_breakdown[ $group_origin_code ][ $resolved_product_id ] += $quantity;
                                                }
                                        }
                                }

                                if ( ! empty( $group_items_plan ) ) {
                                        $restore_plan_groups[] = [
                                                'destination_context' => $group_context,
                                                'origin_context'      => $origin_context,
                                                'items'               => $group_items_plan,
                                        ];
                                }

                                if ( ! empty( $group_items_plan ) ) {
                                        $restore_plan_groups[] = [
                                                'destination_context' => $group_context,
                                                'origin_context'      => $origin_context,
                                                'items'               => $group_items_plan,
                                        ];
                                }

                                $origin_context = isset( $group['origin_context'] ) && is_array( $group['origin_context'] ) ? $group['origin_context'] : [];
                                $group_origin_code = isset( $origin_context['code'] ) ? (string) $origin_context['code'] : '';
                                $group_origin_id   = isset( $origin_context['id'] ) ? (string) $origin_context['id'] : '';

                                if ( '' === $group_origin_code ) {
                                        $group_origin_code = $origin_code;
                                }

                                if ( '' === $group_origin_id ) {
                                        $group_origin_id = $id_origin_warehouse;
                                }

                                if ( '' !== $group_origin_code ) {
                                        $origin_codes_for_stock[]              = $group_origin_code;
                                        $origin_code_id_map[ $group_origin_code ] = $group_origin_id;
                                }

                                $group_context    = isset( $group['context'] ) && is_array( $group['context'] ) ? $group['context'] : [];
                                $origin_context   = isset( $group['origin_context'] ) && is_array( $group['origin_context'] ) ? $group['origin_context'] : [];
                                $group_items_plan = [];

                                foreach ( $group['items'] as $index => $group_item ) {
                                        if ( ! is_a( $group_item, 'WC_Order_Item_Product' ) ) {
                                                continue;
                                        }

                                        $wc_product = $group_item->get_product();
                                        $quantity   = isset( $group['item_quantities'][ $index ] )
                                                ? (float) $group['item_quantities'][ $index ]
                                                : (float) $group_item->get_quantity();

                                        if ( ! $wc_product ) {
                                                continue;
                                        }

                                        $group_items_plan[] = [
                                                'order_item_id' => $group_item->get_id(),
                                                'product_id'    => $resolve_product_id( $group_item, $wc_product ),
                                                'sku'           => (string) $wc_product->get_sku(),
                                                'quantity'      => $quantity,
                                                'price'         => (float) $wc_product->get_price(),
                                                'origin_context'=> isset( $group['origin_contexts'][ $index ] ) && is_array( $group['origin_contexts'][ $index ] )
                                                        ? $group['origin_contexts'][ $index ]
                                                        : $origin_context,
                                        ];
                                }

                                if ( ! empty( $group_items_plan ) ) {
                                        $restore_plan_groups[] = [
                                                'destination_context' => $group_context,
                                                'origin_context'      => $origin_context,
                                                'items'               => $group_items_plan,
                                        ];
                                }
                        }

                        if ( ! empty( $restore_plan_groups ) ) {
                                $this->log_api_transaction(
                                        'transfer_stock_restore_plan',
                                        [
                                                'order_id'                => $order_id,
                                                'trigger'                 => $trigger,
                                                'reason'                  => $reason_label,
                                                'default_origin_code'     => $origin_code,
                                                'default_origin_id'       => $id_origin_warehouse,
                                                'default_destination'     => $destination_code,
                                                'group_count'             => count( $restore_plan_groups ),
                                                'grouped_items'           => $restore_plan_groups,
                                        ]
                                );
                        }

                        $product_ids_for_stock   = array_values( array_unique( $product_ids_for_stock ) );
                        $origin_codes_for_stock  = array_values( array_unique( array_filter( array_map( 'trim', $origin_codes_for_stock ) ) ) );
                        $stock_lookup_failed     = false;

                        foreach ( $stock_lookup_products as $product_id => $product_context ) {
                                $stock_lookup_products[ $product_id ]['skus']             = array_values( array_unique( array_keys( $product_context['skus'] ) ) );
                                $stock_lookup_products[ $product_id ]['order_item_ids']    = array_values( array_unique( $product_context['order_item_ids'] ) );
                                $stock_lookup_products[ $product_id ]['total_requested_quantity'] = (float) $product_context['total_requested_quantity'];
                        }

                        if ( ! empty( $product_ids_for_stock ) && ! empty( $origin_codes_for_stock ) ) {
                                $stock_lookup_request = [
                                        'order_id'           => $order_id,
                                        'warehouses'         => $origin_codes_for_stock,
                                        'warehouse_id_map'   => $origin_code_id_map,
                                        'product_ids'        => $product_ids_for_stock,
                                        'products'           => $stock_lookup_products,
                                        'origin_breakdown'   => $stock_lookup_origin_breakdown,
                                        'force_refresh'      => true,
                                ];

                                try {
                                        $warehouse_stock = $this->contifico->get_warehouses_stock( $origin_codes_for_stock, $product_ids_for_stock, true );
                                        $origin_stock    = is_array( $warehouse_stock ) ? $warehouse_stock : [];

                                        $this->log_api_transaction( 'transfer_stock_restore_stock_lookup', $stock_lookup_request, $origin_stock );
                                } catch ( Exception $exception ) {
                                        $stock_lookup_failed = true;
                                        $order->add_order_note( sprintf(
                                                __( '<b>Contífico:</b><br>No se pudo consultar el stock disponible en la bodega de facturación: %s', $this->plugin_name ),
                                                $exception->getMessage()
                                        ) );

                                        $this->log_api_transaction(
                                                'transfer_stock_restore_stock_lookup_error',
                                                $stock_lookup_request,
                                                $exception->getMessage()
                                        );
                                }
                        }

                        if ( ! $stock_lookup_failed ) {
                                foreach ( $origin_codes_for_stock as $stock_code ) {
                                        $has_stock = isset( $origin_stock[ $stock_code ] ) && is_array( $origin_stock[ $stock_code ] );

                                        if ( $has_stock ) {
                                                continue;
                                        }

                                        $fallback_id = isset( $origin_code_id_map[ $stock_code ] ) ? (string) $origin_code_id_map[ $stock_code ] : '';

                                        if ( '' === $fallback_id ) {
                                                continue;
                                        }

                                        try {
                                                $origin_stock[ $stock_code ] = $this->contifico->get_stock( $fallback_id );

                                                $this->log_api_transaction(
                                                        'transfer_stock_restore_stock_fallback',
                                                        [
                                                                'order_id'       => $order_id,
                                                                'warehouse_code' => $stock_code,
                                                                'warehouse_id'   => $fallback_id,
                                                        ],
                                                        $origin_stock[ $stock_code ]
                                                );
                                        } catch ( Exception $exception ) {
                                                $order->add_order_note( sprintf(
                                                        __( '<b>Contífico:</b><br>No se pudo consultar el stock disponible en la bodega de facturación: %s', $this->plugin_name ),
                                                        $exception->getMessage()
                                                ) );

                                                $this->log_api_transaction(
                                                        'transfer_stock_restore_stock_fallback_error',
                                                        [
                                                                'order_id'       => $order_id,
                                                                'warehouse_code' => $stock_code,
                                                                'warehouse_id'   => $fallback_id,
                                                        ],
                                                        $exception->getMessage()
                                                );
                                        }
                                }
                        }

                        foreach ( $grouped_items as $group ) {
                                $group_context = $group['context'];
                                $group_entries  = [];
                                $origin_context = isset( $group['origin_context'] ) && is_array( $group['origin_context'] ) ? $group['origin_context'] : [];
                                $group_origin_code = isset( $origin_context['code'] ) ? (string) $origin_context['code'] : $origin_code;
                                $group_origin_id   = isset( $origin_context['id'] ) ? (string) $origin_context['id'] : $id_origin_warehouse;

                                $restore_stock = [
                                        'tipo'              => 'TRA',
                                        'fecha'             => date( 'd/m/Y' ),
                                        'bodega_id'         => $group_origin_id,
                                        'bodega_destino_id' => $group_context['id'],
                                        'detalles'          => [],
                                        'descripcion'       => sprintf(
                                                __( 'Referencia: Tienda online reembolso Orden %d', $this->plugin_name ),
                                                $order_id
                                        ),
                                        'codigo_interno'    => null,
                                        'pos'               => $this->woo_contifico->settings["{$env}_api_token"],
                                ];

                                foreach ( $group['items'] as $index => $item ) {
                                        $wc_product = $item->get_product();

                                        if ( ! $wc_product ) {
                                                continue;
                                        }

                                        $item_context  = isset( $group['item_contexts'][ $index ] ) ? $group['item_contexts'][ $index ] : $group_context;
                                        $sku           = (string) $wc_product->get_sku();
                                        $price         = $wc_product->get_price();
                                        $item_quantity = 0.0;
                                        $product_id    = $resolve_product_id( $item, $wc_product );
                                        $allocated_qty = isset( $group['item_quantities'][ $index ] ) ? (float) $group['item_quantities'][ $index ] : null;

                                        if ( empty( $refund ) ) {
                                                $item_stock_reduced = $item->get_meta( '_reduced_stock', true );
                                                $item_quantity      = empty( $item_stock_reduced ) ? $item->get_quantity() : $item_stock_reduced;
                                        } else {
                                                $item_quantity = abs( $item->get_quantity() );
                                        }

                                        $item_quantity = (float) $item_quantity;

                                        if ( null !== $allocated_qty ) {
                                                $item_quantity = min( $item_quantity, $allocated_qty );
                                        }

                                        if ( 0.0 === $item_quantity ) {
                                                continue;
                                        }

                                        if ( '' === (string) $product_id ) {
                                                $order->add_order_note( sprintf(
                                                        __( '<b>Contífico:</b><br>Se omitió la restitución de stock para el SKU %s porque no tiene identificador en Contífico.', $this->plugin_name ),
                                                        $sku
                                                ) );

                                                continue;
                                        }

                                        $origin_stock_for_group = isset( $origin_stock[ $group_origin_code ] ) && is_array( $origin_stock[ $group_origin_code ] )
                                                ? $origin_stock[ $group_origin_code ]
                                                : [];
                                        $available_stock = isset( $origin_stock_for_group[ $product_id ] ) ? (float) $origin_stock_for_group[ $product_id ] : null;
                                        $origin_code_label = '' !== $group_origin_code ? $group_origin_code : $origin_code;

                                        $transfer_quantity = $item_quantity;
                                        $decision          = 'full';

                                        if ( null === $available_stock ) {
                                                $decision = 'unverifiable';

                                                $order->add_order_note( sprintf(
                                                        __( '<b>Contífico:</b><br>No se pudo verificar el stock del SKU %1$s en la bodega de facturación (%2$s); se omite la restitución.', $this->plugin_name ),
                                                        $sku,
                                                        $origin_code_label
                                                ) );

                                                $this->log_api_transaction(
                                                        'transfer_stock_restore_unverifiable',
                                                        [
                                                                'order_id'          => $order_id,
                                                                'product_id'        => $product_id,
                                                                'sku'               => $sku,
                                                                'origin_warehouse'  => $origin_code_label,
                                                                'available_stock'   => null,
                                                                'requested_quantity'=> $item_quantity,
                                                        ]
                                                );

                                                $this->log_api_transaction(
                                                        'transfer_stock_restore_stock_check',
                                                        [
                                                                'order_id'           => $order_id,
                                                                'product_id'         => $product_id,
                                                                'sku'                => $sku,
                                                                'origin_warehouse'   => $origin_code_label,
                                                                'available_stock'    => null,
                                                                'requested_quantity' => $item_quantity,
                                                                'decision'           => $decision,
                                                                'transfer_quantity'  => 0,
                                                        ]
                                                );

                                                continue;
                                        }

                                        if ( $available_stock <= 0.0 ) {
                                                $decision = 'no_stock';

                                                $order->add_order_note( sprintf(
                                                        __( '<b>Contífico:</b><br>No hay stock disponible en la bodega de facturación (%2$s) para devolver el SKU %1$s.', $this->plugin_name ),
                                                        $sku,
                                                        $origin_code_label
                                                ) );

                                                $this->log_api_transaction(
                                                        'transfer_stock_restore_no_stock',
                                                        [
                                                                'order_id'          => $order_id,
                                                                'product_id'        => $product_id,
                                                                'sku'               => $sku,
                                                                'origin_warehouse'  => $origin_code_label,
                                                                'available_stock'   => $available_stock,
                                                                'requested_quantity'=> $item_quantity,
                                                        ]
                                                );

                                                $this->log_api_transaction(
                                                        'transfer_stock_restore_stock_check',
                                                        [
                                                                'order_id'           => $order_id,
                                                                'product_id'         => $product_id,
                                                                'sku'                => $sku,
                                                                'origin_warehouse'   => $origin_code_label,
                                                                'available_stock'    => $available_stock,
                                                                'requested_quantity' => $item_quantity,
                                                                'decision'           => $decision,
                                                                'transfer_quantity'  => 0,
                                                        ]
                                                );

                                                continue;
                                        }

                                        if ( $available_stock < $item_quantity ) {
                                                $decision          = 'partial';
                                                $transfer_quantity = $available_stock;

                                                $order->add_order_note( sprintf(
                                                        __( '<b>Contífico:</b><br>Solo se devolverán %1$s unidades del SKU %2$s desde la bodega de facturación (%3$s) por stock insuficiente.', $this->plugin_name ),
                                                        wc_format_decimal( $transfer_quantity ),
                                                        $sku,
                                                        $origin_code
                                                ) );

                                                $this->log_api_transaction(
                                                        'transfer_stock_restore_partial',
                                                        [
                                                                'order_id'          => $order_id,
                                                                'product_id'        => $product_id,
                                                                'sku'               => $sku,
                                                                'origin_warehouse'  => $origin_code_label,
                                                                'available_stock'   => $available_stock,
                                                                'requested_quantity'=> $item_quantity,
                                                                'transfer_quantity' => $transfer_quantity,
                                                        ]
                                                );
                                        }

                                        $this->log_api_transaction(
                                                'transfer_stock_restore_stock_check',
                                                [
                                                        'order_id'           => $order_id,
                                                        'product_id'         => $product_id,
                                                        'sku'                => $sku,
                                                        'origin_warehouse'   => $origin_code_label,
                                                        'available_stock'    => $available_stock,
                                                        'requested_quantity' => $item_quantity,
                                                        'decision'           => $decision,
                                                        'transfer_quantity'  => $transfer_quantity,
                                                ]
                                        );

                                        $restore_stock['detalles'][] = [
                                                'producto_id' => $product_id,
                                                'precio'      => $price,
                                                'cantidad'    => $transfer_quantity,
                                        ];

                                                        $group_entries[] = $this->build_inventory_movement_entry( [
                                                                'order_id'      => $order_id,
                                                                'event_type'    => 'ingreso',
                                                                'product_id'    => $product_id,
                                                                'wc_product_id' => $wc_product->get_id(),
                                                        'sku'           => $sku,
                                                        'product_name'  => $wc_product->get_name(),
                                                        'quantity'      => $transfer_quantity,
                                                                'warehouses'    => [
                                                                        'from' => [
                                                                                'id'    => (string) $group_origin_id,
                                                                                'code'  => (string) $group_origin_code,
                                                                                'label' => $group_origin_code,
                                                                        ],
                                                                        'to'   => [
                                                                                'id'             => (string) $group_context['id'],
                                                                                'code'           => (string) $group_context['code'],
                                                                                'label'          => $group_context['label'],
                                                                        'location_id'    => (string) $group_context['location_id'],
                                                                        'location_label' => (string) $group_context['location_label'],
                                                                        'mapped'         => (bool) $group_context['mapped'],
                                                                ],
                                                        ],
                                                        'order_status'  => $order->get_status(),
                                                        'order_trigger' => $trigger,
                                                        'context'       => 'restore',
                                                        'order_source'  => $order_source,
                                                'order_item_id' => $item->get_id(),
                                                'sync_type'     => 'global',
                                                'location'      => [
                                                        'id'    => isset( $item_context['location_id'] ) ? $item_context['location_id'] : $group_context['location_id'],
                                                        'label' => isset( $item_context['location_label'] ) ? $item_context['location_label'] : $group_context['location_label'],
                                                ],
                                                'reason'        => $reason_label,
                                        ] );
                                }

                                if ( empty( $restore_stock['detalles'] ) ) {
                                        continue;
                                }

                                $processed_groups++;
                                $status         = 'pending';
                                $reference_code = '';
                                $error_message  = '';
                                $location_label = $this->describe_inventory_location_for_note( $group_context );
                                $api_request    = $restore_stock;

                                try {
                                        $result        = $this->contifico->transfer_stock( json_encode( $restore_stock ) );
                                        $reference_code = isset( $result['codigo'] ) ? (string) $result['codigo'] : '';
                                        $status         = 'success';
                                        $successful_groups++;
                                        $order->add_order_note( sprintf(
                                                __( '<b>Contífico: </b><br> Inventario restaurado hacia %1$s debido a %2$s. Código: %3$s', $this->plugin_name ),
                                                $location_label,
                                                $reason_label,
                                                $reference_code
                                        ) );
                                        $this->log_api_transaction(
                                                'transfer_stock_restore',
                                                [
                                                        'order_id'       => $order_id,
                                                        'payload'        => $api_request,
                                                        'location_label' => $location_label,
                                                        'reason'         => $reason_label,
                                                ],
                                                $result
                                        );
                                } catch ( Exception $exception ) {
                                        $status        = 'error';
                                        $error_message = $exception->getMessage();
                                        $order->add_order_note( sprintf(
                                                __( '<b>Contífico retornó un error al restituir inventario hacia %1$s</b><br>%2$s', $this->plugin_name ),
                                                $location_label,
                                                $error_message
                                        ) );
                                        $this->log_api_transaction(
                                                'transfer_stock_restore_error',
                                                [
                                                        'order_id'       => $order_id,
                                                        'payload'        => $api_request,
                                                        'location_label' => $location_label,
                                                        'reason'         => $reason_label,
                                                ],
                                                $error_message
                                        );
                                }

                                if ( ! empty( $group_entries ) ) {
                                        $movement_entries = array_merge(
                                                $movement_entries,
                                                $this->finalize_inventory_movement_entries( $group_entries, $status, $reference_code, $error_message )
                                        );
                                }
                        }

                        if ( $processed_groups > 0 && $successful_groups === $processed_groups ) {
                                $order->update_meta_data( '_woo_contifico_stock_reduced', wc_bool_to_string( false ) );
                                $order->add_order_note(
                                        __( '<b>Contífico: </b><br> Inventario marcado como disponible nuevamente en la bodega principal.', $this->plugin_name )
                                );
                                $order->save();
                        }

                        if ( ! empty( $movement_entries ) ) {
                                $this->append_inventory_movement_entries( $movement_entries );
                        }

                        if ( $order_id ) {
                                unset( $this->preferred_warehouse_allocations[ $order_id ] );
                                unset( $this->preferred_item_allocations[ $order_id ] );
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
	 * Validate POS data before saving.
	 *
	 * @param array $input
	 * @return array
	 * @since 2.0.3
	 * @see register_setting()
	 * @noinspection PhpUnused
	 */
	public function validate_pos( $input ) : array {
		$message = '';

		if ( in_array( $this->woo_contifico->settings['tipo_documento'], ['FAC', 'PRE'], true ) ) {
			$environment_is_test = ( (int) $this->woo_contifico->settings['ambiente'] === WOO_CONTIFICO_TEST );
			$prefix             = $environment_is_test ? 'test' : 'prod';

			$required_fields = [
				"{$prefix}_establecimiento_punto"   => __( '<br> - Código del establecimiento de prueba requerido', $this->plugin_name ),
				"{$prefix}_establecimiento_codigo" => __( '<br> - Código del punto de emisión de prueba requerido', $this->plugin_name ),
				"{$prefix}_secuencial_factura"     => __( '<br> - Número secuencial de la factura de prueba requerido', $this->plugin_name ),
			];

			foreach ( $required_fields as $field_key => $error_message ) {
				if ( empty( $input[ $field_key ] ) ) {
					$message .= $error_message;
				}
			}
		}

		if ( empty( $message ) ) {
			unset( $this->config_status['errors']['establecimiento'] );
			add_settings_error(
				'woo_contifico_settings',
				'settings_updated',
				__( 'Opciones almacenadas correctamente', $this->plugin_name ),
				'updated'
			);
		} else {
			$this->config_status['errors']['establecimiento'] = sprintf(
				__( 'Existen errores que deben ser corregidos: %s', $this->plugin_name ),
				$message
			);
		}

		$this->update_config_status();
		return $input;
	}

	/**
	 * Show saved settings notice.
	 *
	 * @since 1.3.0
	 * @see register_setting()
	 * @noinspection PhpUnused
	 *
	 * @param array $input
	 * @return array
	 */
	public function save_settings( $input ) : array {
		add_settings_error(
			'woo_contifico_settings',
			'settings_updated',
			__( 'Opciones almacenadas correctamente', $this->plugin_name ),
			'updated'
		);

		// Remove contifico log notice.
		$this->remove_notice( 'woo_contifico_log' );

		// Remove log file if "Activar registro" is not set.
		if ( ! isset( $input['activar_registro'] ) && file_exists( $this->log_path ) ) {
			unlink( $this->log_path );
			add_settings_error(
				'woo_contifico_settings',
				'settings_updated',
				__( 'El registro de transacciones ha sido eliminado', $this->plugin_name ),
				'updated'
			);
		}

		unset( $this->config_status['errors']['woocommerce'] );
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

		$invoice_warehouse_code = isset( $this->woo_contifico->settings['bodega_facturacion'] ) ? (string) $this->woo_contifico->settings['bodega_facturacion'] : '';
		$invoice_warehouse_id   = '';

		if ( '' !== $invoice_warehouse_code ) {
			$invoice_warehouse_id = (string) ( $this->contifico->get_id_bodega( $invoice_warehouse_code ) ?? '' );
		}

		$invoice_location_context    = $this->resolve_order_location_inventory_context( $order, $invoice_warehouse_code, $invoice_warehouse_id );
		$invoice_location_annotation = $this->format_inventory_location_annotation( $invoice_location_context );

		# Generate document number
		if ( 'FAC' === $this->woo_contifico->settings['tipo_documento'] ) {
			try {
				[ $secuencial, $documento ] = $this->generate_unique_document_number( $order, $env );
			}
			catch ( Exception $exception ) {
				$order->add_order_note( $exception->getMessage() );
				return;
			}
		}
		else {
			$documento = date( 'Ymd' ) . $order_id;
		}

		$document_description = sprintf( __( 'Referencia: Orden %d', $this->plugin_name ), $order_id);

		if ( '' !== $invoice_location_annotation ) {
			$document_description .= $invoice_location_annotation;
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
			'descripcion'    => $document_description,
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

                                $order_note = sprintf(
                                        __( 'Contífico retornó errores en la petición. La respuesta del servidor es: %s', $this->plugin_name ),
                                        $json_error
                                );

                                if ( '' !== $invoice_location_annotation ) {
                                        $order_note .= $invoice_location_annotation;
                                }

                                $order->add_order_note( $order_note );
                        } else {
                                $invoice_id_value        = isset( $documento_electronico['id'] ) ? (string) $documento_electronico['id'] : '';
                                $invoice_number_value    = isset( $documento_electronico['documento'] ) ? (string) $documento_electronico['documento'] : '';
                                $invoice_ride_url_value  = isset( $documento_electronico['url_ride'] ) ? (string) $documento_electronico['url_ride'] : '';

                                if ( '' !== $invoice_id_value ) {
                                        $order->update_meta_data( '_id_factura', $invoice_id_value );
                                }

                                if ( '' !== $invoice_number_value ) {
                                        $order->update_meta_data( '_numero_factura', $invoice_number_value );
                                }

                                if ( '' !== $invoice_ride_url_value ) {
                                        $order->update_meta_data( '_contifico_invoice_ride_url', esc_url_raw( $invoice_ride_url_value ) );
                                }

                                $order->save();

                                $order_note = __( 'El documento fue generado correctamente.<br><br>', $this->plugin_name );

                                switch ( $this->woo_contifico->settings['tipo_documento'] ) {
                                        case 'FAC':
                                                $order_note .= sprintf(
                                                        __( 'El número de la factura es: %s', $this->plugin_name ),
                                                        $invoice_number_value
                                                );
                                                break;
                                        case 'PRE':
                                                $order_note .= sprintf(
                                                        __( 'El número de la pre factura es: %s', $this->plugin_name ),
                                                        $invoice_number_value
                                                );
                                                break;
                                }

                                if ( '' !== $invoice_ride_url_value ) {
                                        $order_note .= '<br>' . sprintf( __( 'RIDE: %s', $this->plugin_name ), esc_url( $invoice_ride_url_value ) );
                                }

                                if ( '' !== $invoice_location_annotation ) {
                                        $order_note .= $invoice_location_annotation;
                                }

                                $order->add_order_note( $order_note, 1 );
                        }
                }
                catch (Exception $exception) {
			if ( 'FAC' === $this->woo_contifico->settings['tipo_documento'] ) {
				$this->secuencial_rollback();
			}
$order_note = sprintf(
                                __('<b>Contífico retornó un error</b><br>%s', $this->plugin_name),
                                $exception->getMessage()
                        );

                if ( '' !== $invoice_location_annotation ) {
                        $order_note .= $invoice_location_annotation;
                }

                $order->add_order_note( $order_note );
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

                $invoice_details = $this->resolve_order_invoice_report_details( $order );
                $invoice_number  = $invoice_details['number'];
                $ride_url        = $invoice_details['ride_url'];

                if ( '' !== $invoice_number || '' !== $ride_url ) {
                        $ride_link = '' !== $ride_url
                                ? sprintf(
                                        '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
                                        esc_url( $ride_url ),
                                        '' !== $invoice_number
                                                ? esc_html( $invoice_number )
                                                : esc_html__( 'Ver RIDE de factura', $this->plugin_name )
                                )
                                : esc_html( $invoice_number );

                        printf(
                                '<p><strong>%1$s</strong><br>%2$s</p>',
                                esc_html__( 'Factura Contífico', $this->plugin_name ),
                                $ride_link
                        );
                }
        }

        private function get_order_pdf_download_url( WC_Order $order ) : string {
                $order_id = $order->get_id();

                return wp_nonce_url(
                        add_query_arg(
                                [
                                        'action'   => 'woo_contifico_order_pdf',
                                        'order_id' => $order_id,
                                ],
                                admin_url( 'admin-post.php' )
                        ),
                        'woo_contifico_order_pdf_' . $order_id,
                        '_wccontifico_nonce'
                );
        }

        /**
         * Render the PDF download button within the order details meta box.
         *
         * @since 4.4.0
         */
        public function render_order_pdf_download_button( $order ) {
                if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
                        return;
                }

                if ( ! current_user_can( 'edit_shop_orders' ) ) {
                        return;
                }

                $url = $this->get_order_pdf_download_url( $order );

                printf(
                        '<p class="form-field"><a class="button button-primary" href="%1$s">%2$s</a></p>',
                        esc_url( $url ),
                        esc_html__( 'Descargar reporte Contífico (PDF)', $this->plugin_name )
                );
        }

        /**
         * Stream the Contífico order PDF report to the browser.
         *
         * @since 4.4.0
         */
        public function download_order_inventory_report() {
                if ( ! current_user_can( 'edit_shop_orders' ) ) {
                        wp_die( esc_html__( 'No tiene permisos para descargar este documento.', $this->plugin_name ) );
                }

                $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
                $nonce    = isset( $_GET['_wccontifico_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wccontifico_nonce'] ) ) : '';

                if ( ! $order_id || ! wp_verify_nonce( $nonce, 'woo_contifico_order_pdf_' . $order_id ) ) {
                        wp_die( esc_html__( 'Solicitud inválida para generar el PDF del pedido.', $this->plugin_name ) );
                }

                $order = wc_get_order( $order_id );

                if ( ! $order ) {
                        wp_die( esc_html__( 'No se encontró el pedido solicitado.', $this->plugin_name ) );
                }

                try {
                        $pdf_content = $this->generate_order_inventory_report_pdf( $order );
                } catch ( Exception $exception ) {
                        wp_die( sprintf( esc_html__( 'No se pudo generar el PDF: %s', $this->plugin_name ), esc_html( $exception->getMessage() ) ) );
                }

                $filename = sprintf( 'contifico-order-%s.pdf', $order->get_order_number() );

                nocache_headers();
                $content_length = function_exists( 'mb_strlen' )
                        ? (int) mb_strlen( $pdf_content, '8bit' )
                        : strlen( $pdf_content );

                header( 'Content-Type: application/pdf' );
                header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
                header( 'Content-Length: ' . $content_length );
                echo $pdf_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                exit;
        }

        public function register_shop_order_invoice_column( array $columns ) : array {
                $insertion_point = 'order_status';
                $new_columns     = [];

                foreach ( $columns as $key => $label ) {
                        $new_columns[ $key ] = $label;

                        if ( $insertion_point === $key ) {
                                $new_columns['woo_contifico_invoice'] = __( 'Contífico', $this->plugin_name );
                        }
                }

                if ( ! isset( $new_columns['woo_contifico_invoice'] ) ) {
                        $new_columns['woo_contifico_invoice'] = __( 'Contífico', $this->plugin_name );
                }

                return $new_columns;
        }

        public function render_shop_order_invoice_column( string $column, int $post_id ) : void {
                if ( 'woo_contifico_invoice' !== $column ) {
                        return;
                }

                $order = wc_get_order( $post_id );

                if ( ! $order ) {
                        echo '&mdash;';
                        return;
                }

                $invoice_details = $this->resolve_order_invoice_report_details( $order, false );
                $ride_url        = $invoice_details['ride_url'];
                $invoice_ref     = $invoice_details['number'] ?: $invoice_details['id'];

                $actions = [];

                if ( '' !== $ride_url && '' !== $invoice_ref ) {
                        $actions[] = sprintf(
                                '<a class="button button-small" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
                                esc_url( $ride_url ),
                                esc_html( sprintf( __( 'Factura %s', $this->plugin_name ), $invoice_ref ) )
                        );
                } elseif ( '' !== $ride_url ) {
                        $actions[] = sprintf(
                                '<a class="button button-small" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
                                esc_url( $ride_url ),
                                esc_html__( 'Ver RIDE', $this->plugin_name )
                        );
                } elseif ( '' !== $invoice_ref ) {
                        $actions[] = esc_html( $invoice_ref );
                }

                $pdf_url = $this->get_order_pdf_download_url( $order );
                $actions[] = sprintf(
                        '<a class="button button-small" href="%1$s">%2$s</a>',
                        esc_url( $pdf_url ),
                        esc_html__( 'Reporte Contífico', $this->plugin_name )
                );

                echo wp_kses_post( implode( '<br>', $actions ) );
        }

        /**
         * Retrieve invoice identifiers and the RIDE URL for PDF rendering.
         *
         * @since 4.1.29
         *
         * @param bool $hydrate_missing Whether to fetch missing RIDE links using the Contífico API when absent.
         *
         * @return array{number:string,id:string,ride_url:string}
         */
        private function resolve_order_invoice_report_details( WC_Order $order, bool $hydrate_missing = true ) : array {
                $invoice_number = trim( (string) $order->get_meta( '_numero_factura' ) );
                $invoice_id     = trim( (string) $order->get_meta( '_id_factura' ) );
                $ride_url       = trim( (string) $order->get_meta( '_contifico_invoice_ride_url' ) );

                if ( $hydrate_missing && '' === $ride_url && '' !== $invoice_id ) {
                        try {
                                $document = $this->contifico->get_invoice_document_by_id( $invoice_id );
                        } catch ( Exception $exception ) {
                                $document = [];
                        }

                        if ( is_array( $document ) && ! empty( $document ) ) {
                                $fetched_number = isset( $document['documento'] ) ? trim( (string) $document['documento'] ) : '';
                                $fetched_ride   = isset( $document['url_ride'] ) ? trim( (string) $document['url_ride'] ) : '';
                                $fetched_id     = isset( $document['id'] ) ? trim( (string) $document['id'] ) : '';

                                $invoice_number = $invoice_number ?: $fetched_number;
                                $ride_url       = $fetched_ride ?: $ride_url;
                                $invoice_id     = $invoice_id ?: $fetched_id;

                                if ( '' !== $invoice_number ) {
                                        $order->update_meta_data( '_numero_factura', $invoice_number );
                                }

                                if ( '' !== $ride_url ) {
                                        $order->update_meta_data( '_contifico_invoice_ride_url', esc_url_raw( $ride_url ) );
                                }

                                if ( '' !== $invoice_id ) {
                                        $order->update_meta_data( '_id_factura', $invoice_id );
                                }

                                $order->save();
                        }
                }

                return [
                        'number'   => $invoice_number,
                        'id'       => $invoice_id,
                        'ride_url' => $ride_url,
                ];
        }

        /**
         * Build the PDF payload summarizing the order and its inventory movements.
         *
         * @since 4.4.0
         */
        private function generate_order_inventory_report_pdf( WC_Order $order ) : string {
                $pdf = new Woo_Contifico_Order_Report_Pdf();
                $order_number      = $order->get_order_number();
                $date_format       = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
                $order_date        = $order->get_date_created();
                $date_label        = $order_date ? wc_format_datetime( $order_date, $date_format ) : __( 'Sin fecha registrada', $this->plugin_name );
                $status            = $order->get_status();
                $status_label      = wc_get_order_status_name( $status );
                $invoice_details   = $this->resolve_order_invoice_report_details( $order );
                $invoice_number    = $invoice_details['number'];
                $invoice_id        = $invoice_details['id'];
                $invoice_reference = $invoice_number ?: $invoice_id;
                $invoice_ride_url  = $invoice_details['ride_url'];
                $shipping_methods = $order->get_shipping_methods();
                $shipping_label   = empty( $shipping_methods )
                        ? __( 'Sin método de envío', $this->plugin_name )
                        : implode( ', ', array_map( static function ( $method ) {
                                return wp_strip_all_tags( $method->get_name() );
                        }, $shipping_methods ) );
                $fulfillment_label = $this->order_has_store_pickup( $order )
                        ? __( 'Retiro en tienda', $this->plugin_name )
                        : __( 'Despacho / entrega', $this->plugin_name );
                $payment_label = $order->get_payment_method_title();

                $store_name   = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
                $store_lines  = array_filter( array(
                        get_option( 'woocommerce_store_address' ),
                        get_option( 'woocommerce_store_address_2' ),
                        $this->build_store_location_line(),
                ) );

                $pdf->set_branding( $store_name, $store_lines );

                $logo_path = $this->get_report_logo_path();

                if ( '' !== $logo_path ) {
                        $pdf->set_brand_logo_path( $logo_path );
                }
                $pdf->set_document_title( __( 'Resumen del pedido', $this->plugin_name ) );

                $shipping_name_parts = array_filter( [ $order->get_shipping_first_name(), $order->get_shipping_last_name() ] );
                $shipping_name       = trim( implode( ' ', $shipping_name_parts ) );

                if ( '' === $shipping_name ) {
                        $billing_name_parts = array_filter( [ $order->get_billing_first_name(), $order->get_billing_last_name() ] );
                        $shipping_name      = trim( implode( ' ', $billing_name_parts ) );
                }

                $shipping_name  = $shipping_name ?: __( 'Destinatario no definido', $this->plugin_name );
                $recipient_lines = array_filter( [
                        $order->get_shipping_company(),
                        $order->get_shipping_address_1(),
                        $order->get_shipping_address_2(),
                        $this->build_shipping_city_line( $order ),
                        $order->get_billing_email(),
                        $order->get_billing_phone(),
                ] );

                $pdf->set_recipient_block( $shipping_name, $recipient_lines );

                $order_summary = [
                        [ 'label' => __( 'Número de pedido', $this->plugin_name ), 'value' => $order_number ],
                        [ 'label' => __( 'Fecha de pedido', $this->plugin_name ), 'value' => $date_label ],
                        [ 'label' => __( 'Estado', $this->plugin_name ), 'value' => $status_label ],
                        [ 'label' => __( 'Método de pago', $this->plugin_name ), 'value' => $payment_label ],
                        [ 'label' => __( 'Método de envío', $this->plugin_name ), 'value' => $shipping_label ],
                        [ 'label' => __( 'Modalidad de entrega', $this->plugin_name ), 'value' => $fulfillment_label ],
                ];

                if ( 'completed' === $status && '' !== $invoice_reference ) {
                        $invoice_label = '' !== $invoice_reference
                                ? sprintf( __( 'Pedido completado y factura generada (%s).', $this->plugin_name ), $invoice_reference )
                                : __( 'Pedido completado y factura generada.', $this->plugin_name );

                        $summary_row = [
                                'label' => __( 'Facturación', $this->plugin_name ),
                                'value' => $invoice_label,
                        ];

                        if ( '' !== $invoice_ride_url ) {
                                $summary_row['value_link']       = $invoice_ride_url;
                                $summary_row['value_link_label'] = '' !== $invoice_reference
                                        ? sprintf( __( 'Factura %s (RIDE)', $this->plugin_name ), $invoice_reference )
                                        : __( 'RIDE de factura', $this->plugin_name );
                        }

                        $order_summary[] = $summary_row;
                }

                $pdf->set_order_summary( $order_summary );

                $origin_code       = isset( $this->woo_contifico->settings['bodega'] ) ? (string) $this->woo_contifico->settings['bodega'] : '';
                $default_origin_id = (string) ( $this->contifico->get_id_bodega( $origin_code ) ?? '' );
                $movements         = array_map( [ $this, 'hydrate_inventory_movement_for_report' ], $this->get_order_inventory_movements_for_order( $order->get_id() ) );
                $movement_labels   = [];

                foreach ( $movements as $movement_entry ) {
                        $item_id = isset( $movement_entry['order_item_id'] ) ? (int) $movement_entry['order_item_id'] : 0;

                        if ( $item_id <= 0 ) {
                                continue;
                        }

                        $labels = $this->format_distinct_warehouse_labels(
                                isset( $movement_entry['warehouses']['from'] ) ? $movement_entry['warehouses']['from'] : [],
                                isset( $movement_entry['warehouses']['to'] ) ? $movement_entry['warehouses']['to'] : []
                        );

                        if ( ! isset( $movement_labels[ $item_id ] ) ) {
                                $movement_labels[ $item_id ] = $labels;
                        }
                }

                $items_added = false;

                foreach ( $order->get_items() as $item ) {
                        if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
                                continue;
                        }

                        $wc_product = $item->get_product();

                        if ( ! $wc_product ) {
                                continue;
                        }

                        $context         = $this->resolve_order_item_location_inventory_context( $order, $item, $origin_code, $default_origin_id );
                        $warehouse_label = $this->format_warehouse_label_with_code( $context );
                        $location_label  = $this->describe_inventory_location_for_note( $context );
                        $quantity_label  = wc_format_decimal( $item->get_quantity(), 2 );
                        $sku_label       = $wc_product->get_sku() ?: __( 'Sin SKU', $this->plugin_name );
                        $product_name    = wp_strip_all_tags( $item->get_name() );

                        $details = [];

                        if ( '' !== $sku_label ) {
                                $details[] = sprintf( __( 'SKU: %s', $this->plugin_name ), $sku_label );
                        }

                        $meta_lines = $this->build_item_attribute_lines( $item );

                        if ( ! empty( $meta_lines ) ) {
                                $details[] = implode( ' · ', $meta_lines );
                        }

                        if ( '' !== $warehouse_label ) {
                                $details[] = sprintf( __( 'Bodega: %s', $this->plugin_name ), $warehouse_label );
                        }

                        if ( '' !== $location_label ) {
                                $details[] = sprintf( __( 'Ubicación: %s', $this->plugin_name ), $location_label );
                        }

                        if ( isset( $movement_labels[ $item->get_id() ] ) ) {
                                $from_label = $movement_labels[ $item->get_id() ]['from'];
                                $to_label   = $movement_labels[ $item->get_id() ]['to'];

                                if ( '' !== $from_label ) {
                                        $details[] = sprintf( __( 'Bodega origen: %s', $this->plugin_name ), $from_label );
                                }

                                if ( '' !== $to_label ) {
                                        $details[] = sprintf( __( 'Bodega destino: %s', $this->plugin_name ), $to_label );
                                }
                        }

                $pdf->add_product_row( $product_name, $quantity_label, $details );
                $items_added = true;
        }

                if ( ! $items_added ) {
                        $pdf->add_product_row( __( 'No hay productos asociados al pedido.', $this->plugin_name ), '—' );
                }

                if ( empty( $movements ) ) {
                        $pdf->add_inventory_movement_line( __( 'Aún no hay movimientos registrados para este pedido.', $this->plugin_name ) );
                } else {
                        foreach ( $movements as $movement ) {
                                $timestamp     = isset( $movement['timestamp'] ) ? (int) $movement['timestamp'] : 0;
                                $movement_date = $timestamp ? date_i18n( $date_format, $timestamp ) : __( 'Sin fecha', $this->plugin_name );
                                $event_label   = 'ingreso' === $movement['event_type'] ? __( 'Ingreso', $this->plugin_name ) : __( 'Egreso', $this->plugin_name );
                               $labels        = $this->format_distinct_warehouse_labels(
                                       isset( $movement['warehouses']['from'] ) ? $movement['warehouses']['from'] : [],
                                       isset( $movement['warehouses']['to'] ) ? $movement['warehouses']['to'] : []
                               );
                               $from_label    = $labels['from'];
                               $to_label      = $labels['to'];
                                $reference     = $movement['reference'] ?: __( 'Sin referencia', $this->plugin_name );
                                $location      = isset( $movement['location']['label'] ) && '' !== $movement['location']['label']
                                        ? $movement['location']['label']
                                        : '';
                                $product_name  = wp_strip_all_tags( (string) $movement['product_name'] );
                                $sku_label     = $movement['sku'] ?: __( 'Sin SKU', $this->plugin_name );
                                $quantity      = wc_format_decimal( $movement['quantity'], 2 );
                                $reason        = $this->describe_inventory_movement_reason_for_report( $movement );

                                $location_fragment = '' !== $location
                                        ? sprintf( __( ' en la ubicación %s', $this->plugin_name ), $location )
                                        : '';
                                $reason_fragment = '' !== $reason
                                        ? ' ' . sprintf( __( 'Motivo: %s', $this->plugin_name ), $reason )
                                        : '';

                                $pdf->add_inventory_movement_line(
                                        sprintf(
                                                __( 'El %1$s se registró un %2$s de %3$s unidades de %4$s (SKU %5$s) desde %6$s hacia %7$s%8$s%9$s. Ref: %10$s', $this->plugin_name ),
                                                $movement_date,
                                                strtolower( $event_label ),
                                                $quantity,
                                                $product_name,
                                                $sku_label,
                                                $from_label ?: __( 'bodega no especificada', $this->plugin_name ),
                                                $to_label ?: __( 'bodega no especificada', $this->plugin_name ),
                                                $location_fragment,
                                                $reason_fragment,
                                                $reference
                                        )
                                );
                        }
                }

                $transfer_summaries = $this->build_order_transfer_summaries( $movements );

                if ( empty( $transfer_summaries ) ) {
                        $pdf->add_transfer_summary_line( __( 'Aún no se registran transferencias en Contífico para este pedido.', $this->plugin_name ) );
                } else {
                        foreach ( $transfer_summaries as $summary ) {
                               $pair      = $this->format_distinct_warehouse_labels(
                                       [ 'label' => $summary['from'] ],
                                       [ 'label' => $summary['to'] ]
                               );
                               $from_label = $pair['from'];
                               $to_label   = $pair['to'];

                                $pdf->add_transfer_summary_line(
                                        sprintf(
                                                __( 'Transferido de %1$s a %2$s. Ref: %3$s. Productos: %4$s', $this->plugin_name ),
                                                $from_label ?: __( 'bodega no especificada', $this->plugin_name ),
                                                $to_label ?: __( 'bodega no especificada', $this->plugin_name ),
                                                $summary['reference'],
                                                implode( '; ', $summary['products'] )
                                        )
                                );
                        }
                }

                return $pdf->render();
        }

        /**
         * Resolve the logo image to embed in the PDF, prioritizing the WooCommerce email header image.
         *
         * @since 4.4.2
         */
        private function get_pdf_logo_image() : ?array {
                $logo_url = (string) get_option( 'woocommerce_email_header_image', '' );

                if ( '' === $logo_url ) {
                        $custom_logo_id = get_theme_mod( 'custom_logo' );
                        if ( $custom_logo_id ) {
                                $custom_logo = wp_get_attachment_image_src( $custom_logo_id, 'full' );
                                if ( $custom_logo && ! empty( $custom_logo[0] ) ) {
                                        $logo_url = $custom_logo[0];
                                }
                        }
                }

                if ( '' === $logo_url ) {
                        return null;
                }

                $image_bytes = $this->get_image_bytes( $logo_url );

                if ( '' === $image_bytes ) {
                        return null;
                }

                $image_size = @getimagesizefromstring( $image_bytes );

                if ( ! $image_size || empty( $image_size[0] ) || empty( $image_size[1] ) ) {
                        return null;
                }

                $mime = isset( $image_size['mime'] ) ? (string) $image_size['mime'] : '';

                if ( 'image/jpeg' !== $mime ) {
                        if ( ! function_exists( 'imagecreatefromstring' ) || ! function_exists( 'imagejpeg' ) ) {
                                return null;
                        }

                        $image_resource = @imagecreatefromstring( $image_bytes );

                        if ( false === $image_resource ) {
                                return null;
                        }

                        ob_start();
                        imagejpeg( $image_resource, null, 90 );
                        $image_bytes = (string) ob_get_clean();
                        imagedestroy( $image_resource );

                        $image_size = @getimagesizefromstring( $image_bytes );

                        if ( ! $image_size || empty( $image_size[0] ) || empty( $image_size[1] ) ) {
                                return null;
                        }
                }

                return [
                        'data'   => $image_bytes,
                        'width'  => (int) $image_size[0],
                        'height' => (int) $image_size[1],
                ];
        }

        /**
         * Retrieve the store address formatted for the PDF header.
         *
         * @since 4.4.2
         */
        private function get_store_address_label() : string {
                $store_name = get_bloginfo( 'name' );
                $address_1  = (string) get_option( 'woocommerce_store_address', '' );
                $address_2  = (string) get_option( 'woocommerce_store_address_2', '' );
                $city       = (string) get_option( 'woocommerce_store_city', '' );
                $postcode   = (string) get_option( 'woocommerce_store_postcode', '' );
                $country    = (string) get_option( 'woocommerce_default_country', '' );

                $country_label = '';
                if ( ! empty( $country ) && function_exists( 'wc' ) && wc()->countries ) {
                        $countries = wc()->countries->countries;
                        if ( isset( $countries[ $country ] ) ) {
                                $country_label = $countries[ $country ];
                        }
                }

                $location_parts = array_filter( [ $city, $postcode, $country_label ] );
                $address_parts  = array_filter( [ $address_1, $address_2, implode( ', ', $location_parts ) ] );
                $label_parts    = array_filter( [ $store_name, implode( ' · ', $address_parts ) ] );

                return trim( implode( ' — ', $label_parts ) );
        }

        /**
         * Safely download an image for PDF embedding.
         *
         * @since 4.4.2
         */
        private function get_image_bytes( string $url ) : string {
                $response = wp_remote_get( $url, [ 'timeout' => 10 ] );

                if ( is_wp_error( $response ) ) {
                        return '';
                }

                $status_code = wp_remote_retrieve_response_code( $response );

                if ( 200 !== $status_code ) {
                        return '';
                }

                $body = wp_remote_retrieve_body( $response );

                if ( ! is_string( $body ) ) {
                        return '';
                }

                if ( strlen( $body ) > 2 * 1024 * 1024 ) {
                        return '';
                }

                return $body;
        }

        /**
         * Retrieve normalized inventory movement entries for a given order.
         *
         * @since 4.4.0
         */
        private function get_order_inventory_movements_for_order( int $order_id ) : array {
                $entries  = $this->get_inventory_movements_storage();
                $filtered = [];

                foreach ( $entries as $entry ) {
                        $entry = $this->normalize_inventory_movement_entry( $entry );

                        if ( (int) ( $entry['order_id'] ?? 0 ) !== $order_id ) {
                                continue;
                        }

                        $filtered[] = $entry;
                }

                usort( $filtered, static function ( $a, $b ) {
                        $a_time = isset( $a['timestamp'] ) ? (int) $a['timestamp'] : 0;
                        $b_time = isset( $b['timestamp'] ) ? (int) $b['timestamp'] : 0;

                        if ( $a_time === $b_time ) {
                                return 0;
                        }

                        return ( $a_time < $b_time ) ? -1 : 1;
                } );

                return $filtered;
        }

        /**
         * Build summarized transfer descriptions for the PDF.
         *
         * @since 4.4.0
         */
        private function build_order_transfer_summaries( array $movements ) : array {
                if ( empty( $movements ) ) {
                        return [];
                }

                $summaries = [];

                foreach ( $movements as $movement ) {
                        $reference  = $movement['reference'] ?: __( 'Sin código', $this->plugin_name );
                       $labels     = $this->format_distinct_warehouse_labels(
                               isset( $movement['warehouses']['from'] ) ? $movement['warehouses']['from'] : [],
                               isset( $movement['warehouses']['to'] ) ? $movement['warehouses']['to'] : []
                       );
                       $from_label = $labels['from'];
                       $to_label   = $labels['to'];
                        $key        = md5( implode( '|', [ $reference, $from_label, $to_label, $movement['event_type'] ] ) );

                        if ( ! isset( $summaries[ $key ] ) ) {
                                $summaries[ $key ] = [
                                        'reference' => $reference,
                                        'from'      => $from_label,
                                        'to'        => $to_label,
                                        'products'  => [],
                                ];
                        }

                        $product_name = wp_strip_all_tags( (string) $movement['product_name'] );
                        $quantity     = wc_format_decimal( $movement['quantity'], 2 );
                        $summaries[ $key ]['products'][] = sprintf( '%s (%s)', $product_name, $quantity );
                }

                return array_values( $summaries );
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
	 * Generate a unique document number for invoices.
	 *
	 * @since 4.3.0
	 *
	 * @param \WC_Order $order
	 * @param string    $env
	 * @return array{0:string,1:string}
	 * @throws Exception
	 */
	private function generate_unique_document_number( \WC_Order $order, string $env ): array {
		$attempts = 0;

		do {
			$secuencial_value = $this->get_secuencial();
			if ( null === $secuencial_value ) {
				throw new Exception( __( 'No se pudo obtener el secuencial para la factura.', $this->plugin_name ) );
			}

			$secuencial = str_pad( $secuencial_value, 9, '0', STR_PAD_LEFT );
			$documento = $this->woo_contifico->settings["{$env}_establecimiento_punto"] . '-' . $this->woo_contifico->settings["{$env}_establecimiento_codigo"] . '-' . $secuencial;

			if ( ! $this->invoice_document_exists( $documento ) ) {
				return [ $secuencial, $documento ];
			}

			$order->add_order_note(
				sprintf(
					__( 'El número de factura %s ya existe. Se intentará con el siguiente secuencial.', $this->plugin_name ),
					$documento
				)
			);
			$attempts++;
		} while ( $attempts < self::MAX_INVOICE_SEQUENTIAL_RETRIES );

		throw new Exception( __( 'No se pudo generar un número de factura único. Revise la configuración del punto de venta.', $this->plugin_name ) );
	}

	/**
	 * Check if the given document number already exists in WooCommerce orders.
	 *
	 * @since 4.3.0
	 *
	 * @param string $documento
	 * @return bool
	 */
        private function invoice_document_exists( string $documento ): bool {
                global $wpdb;

                $meta_table = $wpdb->postmeta;
                $invoice_exists = $wpdb->get_var(
                        $wpdb->prepare(
                                "SELECT post_id FROM {$meta_table} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                                '_numero_factura',
                                $documento
                        )
                );

                return null !== $invoice_exists;
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
