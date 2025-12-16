<?php

namespace Pahp\SDK;

use Exception;

/**
 * Contífico SDK to let communication with the API
 *
 * Defines basic functionality to connect with the API
 *
 * @since      1.3.0
 * @package    Pahp/SDK
 * @subpackage Contifico
 * @author     Pablo Hernández (OtakuPahp) <pablo@otakupahp.com>
 */
class Contifico
{

        private const TRANSIENT_TTL = 15 * MINUTE_IN_SECONDS;

        private const INVENTORY_TRANSIENT_KEY = 'woo_contifico_full_inventory';

        private const INVENTORY_PAGE_SIZE = 200;

        private const INVENTORY_MAX_PAGES = 250;

        private const INVENTORY_TOTAL_TRANSIENT_KEY = 'woo_contifico_inventory_total';

    /**
     * @since 1.3.0
     * @access private
     * @var string $api_secret
     */
    private $api_secret;

	/**
	 * @since 1.3.0
	 * @access private
	 * @var array $products
	 */
        private $products;

        /**
         * Stores the amount of products fetched during the current synchronization.
         *
         * @since 4.1.5
         * @access private
         * @var int
         */
        private $inventory_total;

        /**
        * @since 1.5.0
        * @access private
        * @var array $warehouses
        */
        private $warehouses;

        /**
         * Cache of Contífico warehouse labels indexed by warehouse ID.
         *
         * @since 4.1.25
         * @access private
         * @var array<string,array{code:string,label:string}>
         */
        private $warehouse_labels;

        /**
         * Cache for product stock lookups grouped by warehouse.
         *
         * @since 4.1.3
         * @access private
         * @var array<string,array>
         */
        private $product_stock_cache;

        /**
         * Cache for individual Contífico product lookups keyed by identifier.
         *
         * @since 4.2.0
         * @access private
         * @var array<string,array<string,mixed>>
         */
        private $product_cache_by_id;

	/**
	 * @since 3.1.0
	 * @access private
	 * @var bool
	 */
	private $log_transactions;

	/**
	 * @since 3.1.0
	 * @access private
	 * @var string
	 */
	private $log_path;

    /**
     * Contifico constructor.
     *
     * @since 1.3.0
     *
     * @param string $api_secret
     * @param bool $log_transactions
     * @param string $log_path
     */
    public function __construct(string $api_secret, bool $log_transactions = false, string $log_path = '') {
        $this->api_secret = $api_secret;
            $this->products   = get_option('woo_contifico_products');
            if ( ! is_array( $this->products ) ) {
                    $this->products = [];
            }
            $this->warehouses = get_option('woo_contifico_warehouses');
            if ( ! is_array( $this->warehouses ) ) {
                    $this->warehouses = [];
            }
            $this->warehouse_labels = get_option( 'woo_contifico_warehouse_labels' );
            if ( ! is_array( $this->warehouse_labels ) ) {
                    $this->warehouse_labels = [];
            }
            $this->product_stock_cache = [];
            $this->product_cache_by_id = [];
            $this->log_transactions = $log_transactions;
            $this->log_path = $log_path;

            $cached_total = get_transient( self::INVENTORY_TOTAL_TRANSIENT_KEY );
            if ( false === $cached_total ) {
                    $cached_total = 0;
            }

            $this->inventory_total = (int) $cached_total;
    }

    /**
     * HTTP Request call
     *
     * @since 1.3.0
     *
     * @param string $endpoint
     * @param string $args
     * @param string $method
     * @return array
     * @throws Exception
     */
    public function call($endpoint, $args = '' ,$method = 'GET' ) {

    	# Check that API key is set
	    if( empty($this->api_secret) ) {
		    return [
			    'code' => 401,
			    'message' => 'Authorization Required.',
			    'response' => 'API Secret is required',
		    ];
	    }

	    # Populate the correct endpoint for the API request
        $url = "https://api.contifico.com/sistema/api/v1/{$endpoint}";

	    # Create header
        $headers = [
            'Authorization' => $this->api_secret,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json; charset=UTF-8',
        ];

	    # Initialize wp_args
        $wp_args = [
            'headers' => $headers,
            'method' => $method,
            'data_format' => 'body',
	        'timeout' => 50
        ];

        if( !empty($args) ) {
	        # Populate the args for use in the wp_remote_request call
            $wp_args['body'] = $args;
        }

	    # Make the call and store the response in $res
        $res = wp_remote_request($url, $wp_args);

	    # Log transaction if needed
	    if( $this->log_transactions ) {
		    $transaction = [
			    'url' => $url,
			    'request' => $wp_args,
			    'response' => $res
		    ];
		    $log_time = date("Y-m-d H:i:s");
		    $json_message = json_encode($transaction, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
		    error_log("[{$log_time}]: {$json_message}" . PHP_EOL, 3, $this->log_path);
	    }

	    # Check for success
        if(!is_wp_error($res) && ($res['response']['code'] == 200 || $res['response']['code'] == 201)) {
            return json_decode($res['body'], TRUE);
        }
        elseif ( !is_array($res) ) {
        	$call_reply = json_encode($res);
	        throw new Exception( "Unexpected reply: {$call_reply}" );
        }
        elseif ( !in_array( $res['response']['code'], ['200','2001'] ) ) {
        	$body = json_decode($res['body'], TRUE);
        	throw new Exception("API Error ({$res['response']['code']}): {$body['mensaje']}");
        }
        else {
            return [
            	'code' => $res['response']['code'],
	            'message' => $res['response']['message'],
	            'response' => json_decode($res['body'], TRUE),
            ];
        }
    }

	/**
	 * Fetch products from Contífico and save them in the database
	 *
	 * @since 1.3.0
	 *
	 * @param int $step
	 * @param int $batch_size
	 * @return array
	 * @throws Exception
	 */
        public function fetch_products( int $step, int $batch_size ) : array
    {
        $step       = max( 1, $step );
        $batch_size = max( 1, $batch_size );

        if ( 1 === $step ) {
                $this->inventory_total = 0;
                delete_transient( self::INVENTORY_TOTAL_TRANSIENT_KEY );
        }

        try {
                $productos = $this->call( "producto/?result_size={$batch_size}&result_page={$step}" );
        }
        catch ( Exception $exception ) {
                $productos = [];
        }

        $batch = [];

        if ( is_array( $productos ) ) {
                foreach ( $productos as $product ) {
                        if ( ! is_array( $product ) ) {
                                continue;
                        }

                        $product_id = isset( $product['id'] ) ? (string) $product['id'] : '';
                        $sku        = isset( $product['codigo'] ) ? (string) $product['codigo'] : '';

                        if ( '' === $product_id || '' === $sku ) {
                                continue;
                        }

                        $batch[] = [
                                'codigo' => $product_id,
                                'sku'    => $sku,
                                'pvp1'   => isset( $product['pvp1'] ) ? (float) $product['pvp1'] : 0.0,
                                'pvp2'   => isset( $product['pvp2'] ) ? (float) $product['pvp2'] : 0.0,
                                'pvp3'   => isset( $product['pvp3'] ) ? (float) $product['pvp3'] : 0.0,
                        ];
                }
        }

        $this->products = $batch;

        $batch_count = count( $batch );
        $processed   = ( ( $step - 1 ) * $batch_size ) + $batch_count;

        if ( $processed > $this->inventory_total ) {
                $this->inventory_total = $processed;
                set_transient( self::INVENTORY_TOTAL_TRANSIENT_KEY, $this->inventory_total, self::TRANSIENT_TTL );
        }

        if ( $batch_count < $batch_size ) {
                set_transient( 'woo_contifico_fetch_productos', 'yes', self::TRANSIENT_TTL );
        }

        return $batch;
    }

        /**
         * Reset cached inventory information for a fresh synchronization cycle.
         *
         * @since 4.1.5
         *
         * @return void
         */
        public function reset_inventory_cache() : void {

                $this->products        = [];
                $this->inventory_total = 0;
                $this->product_cache_by_id = [];

                delete_transient( self::INVENTORY_TRANSIENT_KEY );
                delete_transient( self::INVENTORY_TOTAL_TRANSIENT_KEY );
        }

	/**
	 * Return the size of the fetched products
	 *
	 * @return int
	 */
        public function count_fetched_products() {
                if ( $this->inventory_total > 0 ) {
                        return $this->inventory_total;
                }

                $cached_total = get_transient( self::INVENTORY_TOTAL_TRANSIENT_KEY );

                if ( false === $cached_total ) {
                        return 0;
                }

                $this->inventory_total = (int) $cached_total;

                return $this->inventory_total;
    }

	/**
	 * Fetch bodegas from Contífico and save them in the database
	 *
	 * @since 1.5.0
	 *
	 * @return int total of warehouses fetched
	 * @throws Exception
	 */
        public function fetch_warehouses() : int
        {
                # Check if warehouses were already fetched
                $fetched = get_transient('woo_contifico_fetch_warehouses');
                if( false === $fetched ) {
                        $bodegas          = $this->call( 'bodega/' );
                        $this->warehouses = array_column($bodegas, 'codigo', 'id');
                        $this->warehouse_labels = [];

                        foreach ( $bodegas as $bodega ) {
                                if ( ! is_array( $bodega ) ) {
                                        continue;
                                }

                                $id    = isset( $bodega['id'] ) ? (string) $bodega['id'] : '';
                                $code  = isset( $bodega['codigo'] ) ? (string) $bodega['codigo'] : '';
                                $label = isset( $bodega['nombre'] ) ? (string) $bodega['nombre'] : '';

                                if ( '' === $id && '' === $code ) {
                                        continue;
                                }

                                $this->warehouse_labels[ $id ] = [
                                        'code'  => $code,
                                        'label' => $label,
                                ];
                        }

                        update_option( 'woo_contifico_warehouses', $this->warehouses );
                        update_option( 'woo_contifico_warehouse_labels', $this->warehouse_labels );
                        set_transient('woo_contifico_fetch_warehouses','yes',self::TRANSIENT_TTL);
                }
                return count($this->warehouses);
        }

        /**
         * Return the cached warehouses map indexed by Contífico ID.
         *
         * @since 4.1.6
         *
         * @return array<string,string>
         */
        public function get_warehouses_map() : array
        {
                if ( ! is_array( $this->warehouses ) ) {
                        return [];
                }

                return $this->warehouses;
        }

        /**
         * Retrieve a warehouse label by its Contífico code.
         *
         * @since 4.1.25
         */
        public function get_warehouse_label_by_code( string $warehouse_code ) : string {
                $warehouse_code = strtoupper( trim( $warehouse_code ) );

                if ( '' === $warehouse_code ) {
                        return '';
                }

                $this->prime_warehouse_labels_cache();

                foreach ( $this->warehouse_labels as $warehouse ) {
                        if ( ! is_array( $warehouse ) ) {
                                continue;
                        }

                        $code  = isset( $warehouse['code'] ) ? strtoupper( (string) $warehouse['code'] ) : '';
                        $label = isset( $warehouse['label'] ) ? (string) $warehouse['label'] : '';

                        if ( '' !== $code && $warehouse_code === $code && '' !== $label ) {
                                return $label;
                        }
                }

                return '';
        }

        /**
         * Retrieve a warehouse label by its Contífico identifier.
         *
         * @since 4.1.25
         */
        public function get_warehouse_label_by_id( string $warehouse_id ) : string {
                $warehouse_id = trim( $warehouse_id );

                if ( '' === $warehouse_id ) {
                        return '';
                }

                $this->prime_warehouse_labels_cache();

                if ( isset( $this->warehouse_labels[ $warehouse_id ]['label'] ) ) {
                        $label = (string) $this->warehouse_labels[ $warehouse_id ]['label'];

                        if ( '' !== $label ) {
                                return $label;
                        }
                }

                return '';
        }

        /**
         * Load warehouse labels from cache or refresh from Contífico if missing.
         *
         * @since 4.1.25
         */
        private function prime_warehouse_labels_cache() : void {
                if ( ! is_array( $this->warehouse_labels ) ) {
                        $this->warehouse_labels = [];
                }

                if ( ! empty( $this->warehouse_labels ) ) {
                        return;
                }

                $labels = get_option( 'woo_contifico_warehouse_labels' );

                if ( is_array( $labels ) && ! empty( $labels ) ) {
                        $this->warehouse_labels = $labels;
                        return;
                }

                try {
                        $this->fetch_warehouses();
                } catch ( Exception $exception ) {
                        return;
                }
        }

        /**
         * Retrieve invoice metadata by its Contífico identifier.
         *
         * @since 4.1.29
         */
        public function get_invoice_document_by_id( string $invoice_id ) : array {
                $invoice_id = trim( $invoice_id );

                if ( '' === $invoice_id ) {
                        return [];
                }

                $cache_key = 'woo_contifico_invoice_doc_' . sanitize_key( $invoice_id );
                $cached    = get_transient( $cache_key );

                if ( is_array( $cached ) ) {
                        return $cached;
                }

                try {
                        $document = $this->call( "documento/{$invoice_id}/" );
                } catch ( Exception $exception ) {
                        return [];
                }

                if ( ! is_array( $document ) || empty( $document ) ) {
                        return [];
                }

                set_transient( $cache_key, $document, self::TRANSIENT_TTL );

                return $document;
        }

        /**
         * Fetch stock from Contífico for the register warehouse and save them in the database.
         *
         * Uses product level stock lookups (producto/{producto_id}/stock/) and caches the
         * filtered results for the requested warehouse in the existing transient.
         *
         * @param string|null $id_warehouse
         *
         * @return array
         * @throws Exception
         * @since 2.0.0
         *
         */
        public function get_stock(?string $id_warehouse) : array
        {
                $transient_suffix = '';
                if ( ! empty( $id_warehouse ) ) {
                        $transient_suffix = '_' . sanitize_key( (string) $id_warehouse );
                }

                # Check if stock were already fetched
                $fetched_stock = get_transient( "woo_contifico_fetch_stock{$transient_suffix}" );

                if ( false !== $fetched_stock ) {
                        return (array) $fetched_stock;
                }

                if ( empty( $id_warehouse ) ) {
                        return [];
                }

                $warehouse_id = (string) $id_warehouse;
                $products     = is_array( $this->products ) ? $this->products : [];
                $product_ids  = array_unique( array_map( 'strval', array_column( $products, 'codigo' ) ) );

                $stock_by_product = [];

                foreach ( $product_ids as $product_id ) {
                        if ( '' === $product_id ) {
                                continue;
                        }

                        $product_stock = $this->get_product_stock_by_warehouses( $product_id );

                        if ( isset( $product_stock[ $warehouse_id ] ) ) {
                                $stock_by_product[ $product_id ] = (float) $product_stock[ $warehouse_id ];
                        }
                }

                set_transient( "woo_contifico_fetch_stock{$transient_suffix}", $stock_by_product, self::TRANSIENT_TTL );

                return $stock_by_product;
        }

        /**
         * Fetch stock information for multiple warehouses at once.
         *
         * Relies on producto/{producto_id}/stock/ endpoint calls and reuses the per-product cache
         * created by get_product_stock_by_warehouses().
         *
         * @since 3.5.0
         *
         * @param array $warehouse_codes
         * @param array $product_ids
         * @param bool  $force_refresh Force a fresh API lookup instead of using cached stock.
         *
         * @return array<string,array>
         */
        public function get_warehouses_stock( array $warehouse_codes, array $product_ids = [], bool $force_refresh = false ) : array {
                $stocks = [];

                if ( empty( $warehouse_codes ) ) {
                        return $stocks;
                }

                $unique_codes = array_unique( array_map( 'strval', $warehouse_codes ) );
                $warehouse_ids = [];

                foreach ( $unique_codes as $warehouse_code ) {
                        if ( '' === $warehouse_code ) {
                                continue;
                        }

                        $warehouse_id = $this->get_id_bodega( $warehouse_code );

                        if ( empty( $warehouse_id ) ) {
                                $stocks[ $warehouse_code ] = [];
                                $warehouse_ids[ $warehouse_code ] = '';
                                continue;
                        }

                        $stocks[ $warehouse_code ]         = [];
                        $warehouse_ids[ $warehouse_code ] = (string) $warehouse_id;
                }

                if ( empty( $warehouse_ids ) ) {
                        return $stocks;
                }

                if ( empty( $product_ids ) ) {
                        $products    = is_array( $this->products ) ? $this->products : [];
                        $product_ids = array_column( $products, 'codigo' );
                }

                $product_ids = array_unique( array_map( 'strval', $product_ids ) );

                foreach ( $product_ids as $product_id ) {
                        if ( '' === $product_id ) {
                                continue;
                        }

                        $product_stock = $this->get_product_stock_by_warehouses( $product_id, $force_refresh );

                        if ( empty( $product_stock ) ) {
                                continue;
                        }

                        foreach ( $warehouse_ids as $warehouse_code => $warehouse_id ) {
                                if ( '' === $warehouse_id ) {
                                        continue;
                                }

                                if ( isset( $product_stock[ $warehouse_id ] ) ) {
                                        $stocks[ $warehouse_code ][ $product_id ] = (float) $product_stock[ $warehouse_id ];
                                }
                        }
                }

                return $stocks;
        }

        /**
         * Return the Contifico's product id
         *
         * @since 1.3.0
         *
         * @param string $sku
         * @return string
         * @throws
         */
        public function get_product_id($sku) : string
        {
                # Get an array with the SKU as index and the Contifico's id as value
                $sku_products = array_column($this->products,'codigo','sku');
                $product_id = isset($sku_products[$sku]) ? $sku_products[$sku] : '';

                # If no product ID is set, call Contifico to get it
                if( empty($product_id) ) {
                        $producto = $this->call( "producto/?codigo={$sku}" );
                        if( empty($producto) ) {
                                throw new Exception("API Error: {$sku} not found", 400);
			}
			else {
				$product_id = $producto[0]['id'];
			}
		}

                return $product_id;
        }

        /**
         * Retrieve a Contífico product by its identifier using the dedicated endpoint.
         *
         * @since 4.2.0
         *
         * @param string $product_id
         * @return array<string,mixed>
         */
        public function get_product_by_id( string $product_id, bool $force_refresh = false ) : array {

                $product_id = trim( $product_id );

                if ( '' === $product_id ) {
                        return [];
                }

                $transient_key = 'woo_contifico_product_' . md5( $product_id );
                $cached_product = [];

                if ( isset( $this->product_cache_by_id[ $product_id ] ) ) {
                        $cached_product = $this->product_cache_by_id[ $product_id ];

                        if ( ! $force_refresh ) {
                                return $cached_product;
                        }
                }

                if ( ! $force_refresh ) {
                        foreach ( $this->products as $product_entry ) {
                                if ( ! is_array( $product_entry ) ) {
                                        continue;
                                }

                                if (
                                        ( isset( $product_entry['codigo'] ) && (string) $product_entry['codigo'] === $product_id )
                                ) {
                                        $this->product_cache_by_id[ $product_id ] = $product_entry;

                                        return $product_entry;
                                }
                        }
                }

                $transient_product = get_transient( $transient_key );

                if ( is_array( $transient_product ) && isset( $transient_product['codigo'] ) ) {
                        if ( ! $force_refresh ) {
                                $this->product_cache_by_id[ $product_id ] = $transient_product;
                                $this->cache_single_product_entry( $transient_product );

                                return $transient_product;
                        }

                        if ( empty( $cached_product ) ) {
                                $cached_product = $transient_product;
                        }
                }

                if ( $force_refresh ) {
                        delete_transient( $transient_key );
                }

                try {
                        $encoded_id = rawurlencode( $product_id );
                        $product    = $this->call( "producto/{$encoded_id}/" );
                }
                catch ( Exception $exception ) {
                        $product = [];
                }

                if ( isset( $product[0] ) && is_array( $product[0] ) ) {
                        $product = $product[0];
                }

                if ( ! is_array( $product ) || empty( $product ) ) {
                        if ( $force_refresh && ! empty( $cached_product ) ) {
                                return $cached_product;
                        }

                        $inventory = $this->get_products();

                        if ( is_array( $inventory ) ) {
                                $product = $this->locate_product_in_inventory_by_id( $inventory, $product_id );

                                if ( is_array( $product ) && ! empty( $product ) ) {
                                        $this->product_cache_by_id[ $product_id ] = $product;

                                        return $product;
                                }
                        }

                        return [];
                }

                $normalized = $this->normalize_product_entry( $product );

                if ( empty( $normalized ) ) {
                        if ( $force_refresh && ! empty( $cached_product ) ) {
                                return $cached_product;
                        }

                        return [];
                }

                $this->product_cache_by_id[ $product_id ] = $normalized;
                $this->cache_single_product_entry( $normalized );
                set_transient( $transient_key, $normalized, self::TRANSIENT_TTL );

                return $normalized;
        }

        /**
         * Cache a single product entry into the local inventory store.
         *
         * @since 4.2.0
         *
         * @param array<string,mixed> $product
         * @return void
         */
        private function cache_single_product_entry( array $product ) : void {

                if ( empty( $product ) || ! isset( $product['codigo'] ) ) {
                        return;
                }

                $contifico_id  = (string) $product['codigo'];
                $sku           = isset( $product['sku'] ) ? (string) $product['sku'] : '';
                $needs_persist = false;
                $updated       = false;

                foreach ( $this->products as $index => $existing ) {
                        if ( ! is_array( $existing ) ) {
                                continue;
                        }

                        $existing_id = isset( $existing['codigo'] ) ? (string) $existing['codigo'] : '';
                        $existing_sku = isset( $existing['sku'] ) ? (string) $existing['sku'] : '';

                        if ( $existing_id === $contifico_id || ( '' !== $sku && $existing_sku === $sku ) ) {
                                if ( $this->products[ $index ] !== $product ) {
                                        $this->products[ $index ] = $product;
                                        $needs_persist             = true;
                                }

                                $updated = true;
                                break;
                        }
                }

                if ( ! $updated ) {
                        $this->products[] = $product;
                        $needs_persist    = true;
                }

                if ( $needs_persist ) {
                        update_option( 'woo_contifico_products', $this->products );
                        set_transient( self::INVENTORY_TRANSIENT_KEY, $this->products, self::TRANSIENT_TTL );
                }
        }

        /**
         * Normalize a product payload from Contífico into the internal structure.
         *
         * @since 4.2.0
         *
         * @param array<string,mixed> $product
         * @return array<string,mixed>
         */
        private function normalize_product_entry( array $product ) : array {

                if ( empty( $product ) ) {
                        return [];
                }

                $contifico_id = '';

                if ( isset( $product['id'] ) && '' !== $product['id'] ) {
                        $contifico_id = (string) $product['id'];
                } elseif ( isset( $product['codigo'] ) && '' !== $product['codigo'] ) {
                        $contifico_id = (string) $product['codigo'];
                }

                if ( '' === $contifico_id ) {
                        return [];
                }

                $sku = '';

                if ( isset( $product['sku'] ) && '' !== $product['sku'] ) {
                        $sku = (string) $product['sku'];
                }

                if ( '' === $sku && isset( $product['codigo'] ) && '' !== $product['codigo'] ) {
                        $sku = (string) $product['codigo'];
                }

                return [
                        'codigo' => $contifico_id,
                        'sku'    => $sku,
                        'pvp1'   => isset( $product['pvp1'] ) ? (float) $product['pvp1'] : 0.0,
                        'pvp2'   => isset( $product['pvp2'] ) ? (float) $product['pvp2'] : 0.0,
                        'pvp3'   => isset( $product['pvp3'] ) ? (float) $product['pvp3'] : 0.0,
                ];
        }

	/**
	 * Get stored products
	 *
	 * @since 1.5.0
	 *
	 * @return array
	 */
        public function get_products() : array {
                return $this->ensure_inventory_loaded();
        }

        /**
         * Ensure the full Contífico inventory is available locally.
         *
         * Downloads every product through the producto/ endpoint when the cache
         * is missing or a fresh read is required. Results are cached both in
         * memory and with a transient to share them across requests inside the
         * current synchronization window.
         *
         * @since 4.1.4
         *
         * @param bool $force_refresh Whether the API must be queried even when
         *                            a cached copy is present.
         *
         * @return array<int,array<string,mixed>>
         */
        private function ensure_inventory_loaded( bool $force_refresh = false ) : array {

                if ( ! $force_refresh && ! empty( $this->products ) ) {
                        return $this->products;
                }

                if ( ! $force_refresh ) {
                        $cached_inventory = get_transient( self::INVENTORY_TRANSIENT_KEY );

                        if ( is_array( $cached_inventory ) ) {
                                $this->products = $cached_inventory;

                                return $this->products;
                        }
                }

                $downloaded_inventory = $this->download_inventory_from_api();

                $this->products = $downloaded_inventory;

                update_option( 'woo_contifico_products', $this->products );
                set_transient( self::INVENTORY_TRANSIENT_KEY, $this->products, self::TRANSIENT_TTL );

                return $this->products;
        }

        /**
         * Download the complete inventory from Contífico via producto/ endpoint.
         *
         * @since 4.1.4
         *
         * @return array<int,array<string,mixed>>
         */
        private function download_inventory_from_api() : array {

                $inventory_by_id = [];

                $page       = 1;
                $page_size  = self::INVENTORY_PAGE_SIZE;
                $max_pages  = self::INVENTORY_MAX_PAGES;

                do {
                        try {
                                $fetched_products = $this->call( "producto/?result_size={$page_size}&result_page={$page}" );
                        }
                        catch ( Exception $exception ) {
                                break;
                        }

                        if ( empty( $fetched_products ) || ! is_array( $fetched_products ) ) {
                                break;
                        }

                        foreach ( $fetched_products as $product ) {
                                if ( ! is_array( $product ) ) {
                                        continue;
                                }

                                $product_id = isset( $product['id'] ) ? (string) $product['id'] : '';
                                $sku        = isset( $product['codigo'] ) ? (string) $product['codigo'] : '';

                                if ( '' === $product_id || '' === $sku ) {
                                        continue;
                                }

                                $inventory_by_id[ $product_id ] = [
                                        'codigo' => $product_id,
                                        'sku'    => $sku,
                                        'pvp1'   => isset( $product['pvp1'] ) ? (float) $product['pvp1'] : 0.0,
                                        'pvp2'   => isset( $product['pvp2'] ) ? (float) $product['pvp2'] : 0.0,
                                        'pvp3'   => isset( $product['pvp3'] ) ? (float) $product['pvp3'] : 0.0,
                                ];
                        }

                        if ( count( $fetched_products ) < $page_size ) {
                                break;
                        }

                        $page ++;

                        if ( $page > $max_pages ) {
                                break;
                        }
                } while ( true );

                if ( empty( $inventory_by_id ) ) {
                        return [];
                }

                return array_values( $inventory_by_id );
        }

	/**
	 * Get product stock
	 * @param string $codigo_producto
	 * @param string $codigo_bodega
	 *
	 * @return mixed
	 */
        public function get_product_stock($codigo_producto, $codigo_bodega) {

                $product_id   = (string) $codigo_producto;
                $warehouse_id = (string) $codigo_bodega;
                $product_stock = $this->get_product_stock_by_warehouses( $product_id );

                if ( isset( $product_stock[ $warehouse_id ] ) ) {
                        return $product_stock[ $warehouse_id ];
                }

                return null;
    }

        /**
         * Retrieve the stock of a product for all warehouses indexed by Contífico warehouse ID.
         *
         * @since 4.1.3
         *
         * @param string $codigo_producto Product identifier in Contífico.
         *
         * @return array<string,float>
         */
        public function get_product_stock_by_warehouses( string $codigo_producto, bool $force_refresh = false ) : array {

                $product_id = (string) $codigo_producto;

                if ( isset( $this->product_stock_cache[ $product_id ] ) ) {
                        if ( ! $force_refresh ) {
                                return $this->product_stock_cache[ $product_id ];
                        }
                }

                $transient_key = 'woo_contifico_product_stock_' . md5( $product_id );
                $cached_stock  = get_transient( $transient_key );

                if ( is_array( $cached_stock ) ) {
                        if ( ! $force_refresh ) {
                                $this->product_stock_cache[ $product_id ] = $cached_stock;

                                return $cached_stock;
                        }
                }

                if ( $force_refresh ) {
                        delete_transient( $transient_key );
                        unset( $this->product_stock_cache[ $product_id ] );
                }

                try {
                        $encoded_id     = rawurlencode( $product_id );
                        $producto_stock = $this->call( "producto/{$encoded_id}/stock/" );
                }
                catch ( Exception $exception ) {
                        $producto_stock = [];
                }

                $stock_by_warehouse = [];

                if ( is_array( $producto_stock ) ) {
                        foreach ( $producto_stock as $warehouse_entry ) {
                                if ( ! is_array( $warehouse_entry ) ) {
                                        continue;
                                }

                                $warehouse_id = '';

                                if ( isset( $warehouse_entry['bodega_id'] ) ) {
                                        $warehouse_id = (string) $warehouse_entry['bodega_id'];
                                } elseif ( isset( $warehouse_entry['bodega'] ) ) {
                                        $warehouse_id = (string) $warehouse_entry['bodega'];
                                }

                                if ( '' === $warehouse_id ) {
                                        continue;
                                }

                                $quantity = 0;

                                if ( isset( $warehouse_entry['cantidad_disponible'] ) ) {
                                        $quantity = $warehouse_entry['cantidad_disponible'];
                                } elseif ( isset( $warehouse_entry['cantidad_stock'] ) ) {
                                        $quantity = $warehouse_entry['cantidad_stock'];
                                } elseif ( isset( $warehouse_entry['cantidad'] ) ) {
                                        $quantity = $warehouse_entry['cantidad'];
                                }

                                $stock_by_warehouse[ $warehouse_id ] = (float) $quantity;
                        }
                }

                $this->product_stock_cache[ $product_id ] = $stock_by_warehouse;

                if ( ! empty( $stock_by_warehouse ) ) {
                        set_transient( $transient_key, $stock_by_warehouse, self::TRANSIENT_TTL );
                }

                return $stock_by_warehouse;
    }

	/**
	 * Return the bodega object from the $codigo_bodega
	 *
	 * @since 1.3.0
	 *
	 * @param string $codigo_bodega
	 * @return string|null
	 */
	public function get_id_bodega(string $codigo_bodega) : ?string
	{

		# Find $codigo_bodega
		$id_bodega = null;
		foreach ($this->warehouses as $key => $bodega) {
			if($bodega === $codigo_bodega) {
				$id_bodega = $key;
				break;
			}
		}

		return $id_bodega;

    }

	/**
	 * Update the stock
	 * @since 1.3.0
	 *
	 * @param string $request
	 * @return mixed
	 * @throws Exception
	 */
    public function transfer_stock($request) {

	    return $this->call('movimiento-inventario/', $request, 'POST');

    }

}