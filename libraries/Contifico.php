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
         * @since 1.5.0
         * @access private
         * @var array $warehouses
         */
        private $warehouses;

        /**
         * Cache for product stock lookups grouped by warehouse.
         *
         * @since 4.1.3
         * @access private
         * @var array<string,array>
         */
        private $product_stock_cache;

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
            $this->warehouses = get_option('woo_contifico_warehouses');
            $this->product_stock_cache = [];
	    $this->log_transactions = $log_transactions;
	    $this->log_path = $log_path;
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
    	# Check if products were already fetched
	    $productos = [];
	    $fetched = get_transient('woo_contifico_fetch_productos');
	    if( false === $fetched ) {

	    	# Fetch the current batch
		    $fetched_products = $this->call( "producto/?result_size={$batch_size}&result_page={$step}" );

		    # If no more products are get, then the batch has finished. Set the transient to notify that it finished
		    if(empty($fetched_products) ) {
			    set_transient('woo_contifico_fetch_productos','yes',self::TRANSIENT_TTL);
		    }
		    else {
			    # If is the first step, clear current products list
			    if( $step === 1 ) {
				    $this->products = [];
			    }

			    # Get SKU and PVP info to store
			    $skus = array_column($fetched_products, 'codigo', 'id');
			    $pvps1 = array_column($fetched_products, 'pvp1', 'id');
			    $pvps2 = array_column($fetched_products, 'pvp2', 'id');
			    $pvps3 = array_column($fetched_products, 'pvp3', 'id');
			    foreach ($skus as $key => $sku) {
			    	$productos[] = [
			    		'codigo' => $key,
			    		'sku' => $sku,
					    'pvp1' => $pvps1[$key],
					    'pvp2' => $pvps2[$key],
					    'pvp3' => $pvps3[$key],
				    ];
			    }

			    # Update products stored
			    $this->products = array_merge( $this->products, $productos );
			    update_option( 'woo_contifico_products', $this->products );
		    }
	    }
	    else {
	    	$productos = $this->products;
	    }
	    return $productos;
    }

	/**
	 * Return the size of the fetched products
	 *
	 * @return int
	 */
	public function count_fetched_products() {
    	return count($this->products);
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
			update_option( 'woo_contifico_warehouses', $this->warehouses );
			set_transient('woo_contifico_fetch_warehouses','yes',self::TRANSIENT_TTL);
		}
		return count($this->warehouses);
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
         *
         * @return array<string,array>
         */
        public function get_warehouses_stock( array $warehouse_codes, array $product_ids = [] ) : array {
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

                        $product_stock = $this->get_product_stock_by_warehouses( $product_id );

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
	 * Get stored products
	 *
	 * @since 1.5.0
	 *
	 * @return array
	 */
	public function get_products() : array {
		return $this->products;
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
        public function get_product_stock_by_warehouses( string $codigo_producto ) : array {

                $product_id = (string) $codigo_producto;

                if ( isset( $this->product_stock_cache[ $product_id ] ) ) {
                        return $this->product_stock_cache[ $product_id ];
                }

                $transient_key = 'woo_contifico_product_stock_' . md5( $product_id );
                $cached_stock  = get_transient( $transient_key );

                if ( is_array( $cached_stock ) ) {
                        $this->product_stock_cache[ $product_id ] = $cached_stock;

                        return $cached_stock;
                }

                try {
                        $producto_stock = $this->call( "producto/{$product_id}/stock/" );
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

                                $warehouse_id = isset( $warehouse_entry['bodega_id'] ) ? (string) $warehouse_entry['bodega_id'] : '';

                                if ( '' === $warehouse_id ) {
                                        continue;
                                }

                                $quantity = 0;

                                if ( isset( $warehouse_entry['cantidad'] ) ) {
                                        $quantity = $warehouse_entry['cantidad'];
                                } elseif ( isset( $warehouse_entry['cantidad_stock'] ) ) {
                                        $quantity = $warehouse_entry['cantidad_stock'];
                                } elseif ( isset( $warehouse_entry['cantidad_disponible'] ) ) {
                                        $quantity = $warehouse_entry['cantidad_disponible'];
                                }

                                $stock_by_warehouse[ $warehouse_id ] = (float) $quantity;
                        }
                }

                $this->product_stock_cache[ $product_id ] = $stock_by_warehouse;
                set_transient( $transient_key, $stock_by_warehouse, self::TRANSIENT_TTL );

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