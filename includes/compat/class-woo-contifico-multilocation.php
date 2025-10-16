<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Compatibility layer for MultiLoca Lite multi location plugin.
 */
class Woo_Contifico_MultiLocation_Compatibility {

    /**
     * Holds the MultiLoca Lite plugin instance when available.
     *
     * @var object|null
     */
    protected $instance;

    /**
     * Cache the activation flag.
     *
     * @var bool
     */
    protected $is_active = false;

    /**
     * Whether the integration has been manually enabled.
     *
     * @var bool
     */
    protected $manual_activation = false;

    /**
     * Locations provided manually from the WooCommerce settings.
     *
     * @var array
     */
    protected $manual_locations = [];

    /**
     * Cache of the resolved locations list.
     *
     * @var array|null
     */
    protected $locations_cache = null;

    /**
     * Constructor.
     */
    public function __construct() {
        if ( did_action( 'plugins_loaded' ) ) {
            $this->bootstrap();
        } else {
            add_action( 'plugins_loaded', [ $this, 'bootstrap' ], 20 );
        }

        if ( did_action( 'init' ) ) {
            $this->refresh_after_init();

            if ( doing_action( 'init' ) ) {
                add_action( 'init', [ $this, 'refresh_after_init' ], PHP_INT_MAX );
            }
        } else {
            add_action( 'init', [ $this, 'refresh_after_init' ], 50 );
        }
    }

    /**
     * Perform the runtime initialization once plugins are loaded.
     *
     * @return void
     */
    public function bootstrap() : void {
        $this->instance  = $this->locate_instance();
        $this->is_active = $this->determine_is_active();
    }

    /**
     * Re-evaluate the integration once WordPress finished running `init`.
     *
     * This ensures MultiLoca taxonomies and helpers that are registered on
     * `init` are taken into account when determining availability.
     *
     * @return void
     */
    public function refresh_after_init() : void {
        $this->instance        = $this->locate_instance();
        $this->is_active       = $this->determine_is_active();
        $this->locations_cache = null;
    }

    /**
     * Decide if MultiLoca should be considered active.
     *
     * This fallback allows the integration to work even when the plugin
     * exposes helper functions but does not keep a global instance
     * accessible.
     *
     * @return bool
     */
    protected function determine_is_active() : bool {
        if ( $this->manual_activation ) {
            return true;
        }

        if ( ! is_object( $this->instance ) ) {
            $this->instance = $this->locate_instance();
        }

        if ( is_object( $this->instance ) ) {
            return true;
        }

        $helper_functions = [
            'multiloca_lite_get_locations',
            'multiloca_get_locations',
            'multiloca_lite_update_stock',
            'multiloca_update_stock',
        ];

        foreach ( $helper_functions as $function_name ) {
            if ( function_exists( $function_name ) ) {
                return true;
            }
        }

        foreach ( $this->get_supported_taxonomies() as $taxonomy ) {
            if ( taxonomy_exists( $taxonomy ) ) {
                return true;
            }
        }

        if ( taxonomy_exists( 'multiloca_location' ) ) {
            return true;
        }

        if ( taxonomy_exists( 'locations-lite' ) ) {
            return true;
        }

        $detected_classes = [
            '\\MultiLocaLite\\Plugin',
            '\\MultiLocaLite\\MultiLoca',
            '\\MultiLoca\\Plugin',
            '\\MultiLoca\\MultiLoca',
            'Multiloca_Lite_Plugin',
            'Multiloca_Lite',
            'MultiLoca_Lite',
            'MultiLoca',
        ];

        foreach ( $detected_classes as $class_name ) {
            if ( class_exists( $class_name ) ) {
                return true;
            }
        }

        if ( defined( 'MULTILOCA_LITE_VERSION' ) || defined( 'MULTILOCA_VERSION' ) ) {
            return true;
        }

        foreach ( $this->get_supported_post_types() as $post_type ) {
            if ( post_type_exists( $post_type ) ) {
                return true;
            }
        }

        if ( post_type_exists( 'multiloca_location' ) ) {
            return true;
        }

        $option_locations = get_option( 'multiloca_locations' );
        if ( is_array( $option_locations ) && ! empty( $option_locations ) ) {
            return true;
        }

        $alternative_locations = get_option( 'wcmlim_locations' );
        if ( is_array( $alternative_locations ) && ! empty( $alternative_locations ) ) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the MultiLoca Lite plugin is available.
     *
     * @return bool
     */
    public function is_active() : bool {
        if ( $this->manual_activation ) {
            return true;
        }

        return $this->is_active;
    }

    /**
     * Allow the WooCommerce settings to force the integration to be active.
     *
     * @param bool $enabled
     */
    public function set_manual_activation( bool $enabled ) : void {
        $this->manual_activation = $enabled;

        if ( $enabled ) {
            $this->is_active = true;
        } else {
            $this->is_active = $this->determine_is_active();
        }

        $this->locations_cache = null;
    }

    /**
     * Receive the locations that were entered manually in the settings page.
     *
     * @param array $locations
     */
    public function set_manual_locations( array $locations ) : void {
        $this->manual_locations = $locations;
        $this->locations_cache  = null;
    }

    /**
     * Retrieve all registered locations from MultiLoca.
     *
     * @return array
     */
    public function get_locations() : array {
        if ( ! $this->is_active() ) {
            return [];
        }

        if ( is_array( $this->locations_cache ) ) {
            return $this->locations_cache;
        }

        $function_sources = [
            'multiloca_lite_get_locations',
            'multiloca_get_locations',
        ];

        foreach ( $function_sources as $function_name ) {
            if ( function_exists( $function_name ) ) {
                $locations = call_user_func( $function_name );
                if ( is_array( $locations ) ) {
                    return $this->locations_cache = $locations;
                }
            }
        }

        if ( is_object( $this->instance ) && isset( $this->instance->locations ) && is_object( $this->instance->locations ) ) {
            $manager = $this->instance->locations;
            if ( is_callable( [ $manager, 'get_locations' ] ) ) {
                $locations = call_user_func( [ $manager, 'get_locations' ] );
                if ( is_array( $locations ) ) {
                    return $this->locations_cache = $locations;
                }
            }
        }

        if ( is_object( $this->instance ) && method_exists( $this->instance, 'get_locations' ) ) {
            $locations = $this->instance->get_locations();
            if ( is_array( $locations ) ) {
                return $this->locations_cache = $locations;
            }
        }

        if ( ! did_action( 'init' ) ) {
            $locations = $this->get_locations_from_database();
            if ( ! empty( $locations ) ) {
                return $this->locations_cache = $locations;
            }
        }

        $locations = $this->get_locations_from_taxonomy();
        if ( ! empty( $locations ) ) {
            return $this->locations_cache = $locations;
        }

        $locations = $this->get_locations_from_posts();
        if ( ! empty( $locations ) ) {
            return $this->locations_cache = $locations;
        }

        $locations = $this->get_locations_from_option();
        if ( ! empty( $locations ) ) {
            return $this->locations_cache = $locations;
        }

        if ( $this->manual_activation && ! empty( $this->manual_locations ) ) {
            return $this->locations_cache = $this->manual_locations;
        }

        $this->locations_cache = [];

        return $this->locations_cache;
    }

    /**
     * Retrieve locations from the registered taxonomy when available.
     *
     * @return array
     */
    protected function get_locations_from_taxonomy() : array {
        $locations = [];

        foreach ( $this->get_supported_taxonomies() as $taxonomy ) {
            if ( ! taxonomy_exists( $taxonomy ) ) {
                continue;
            }

            $terms = get_terms(
                [
                    'taxonomy'   => $taxonomy,
                    'hide_empty' => false,
                ]
            );

            if ( is_wp_error( $terms ) || empty( $terms ) ) {
                continue;
            }

            foreach ( $terms as $term ) {
                $term_id = $term->term_id;
                $locations[ $term_id ] = [
                    'id'   => $term_id,
                    'name' => $term->name,
                ];
            }
        }

        return $locations;
    }

    /**
     * Retrieve locations from a dedicated post type used by some MultiLoca builds.
     *
     * @return array
     */
    protected function get_locations_from_posts() : array {
        if ( ! post_type_exists( 'multiloca_location' ) ) {
            return [];
        }

        $posts = get_posts(
            [
                'post_type'      => 'multiloca_location',
                'posts_per_page' => -1,
                'post_status'    => [ 'publish', 'private' ],
                'fields'         => 'ids',
            ]
        );

        if ( empty( $posts ) ) {
            return [];
        }

        $locations = [];

        foreach ( $posts as $post_id ) {
            $name = get_the_title( $post_id );
            if ( '' === $name ) {
                $name = get_post_field( 'post_name', $post_id );
            }

            if ( '' === $name ) {
                $name = (string) $post_id;
            }

            $locations[ $post_id ] = [
                'id'   => $post_id,
                'name' => $name,
            ];
        }

        return $locations;
    }

    /**
     * Retrieve locations stored in a serialized option.
     *
     * @return array
     */
    protected function get_locations_from_option() : array {
        $stored_locations = get_option( 'multiloca_locations' );

        if ( ! is_array( $stored_locations ) || empty( $stored_locations ) ) {
            $stored_locations = get_option( 'wcmlim_locations' );

            if ( ! is_array( $stored_locations ) || empty( $stored_locations ) ) {
                return [];
            }
        }

        $locations = [];

        foreach ( $stored_locations as $location_id => $location ) {
            if ( is_string( $location ) ) {
                $name = $location;
            } elseif ( is_array( $location ) ) {
                $name = $location['name'] ?? '';
                $location_id = $location['id'] ?? $location_id;
            } elseif ( is_object( $location ) ) {
                $name        = $location->name ?? '';
                $location_id = $location->id ?? $location_id;
            } else {
                continue;
            }

            $name = trim( (string) $name );
            if ( '' === $name ) {
                $name = (string) $location_id;
            }

            $locations[ $location_id ] = [
                'id'   => $location_id,
                'name' => $name,
            ];
        }

        return $locations;
    }

    /**
     * Retrieve locations directly from the terms tables when the taxonomy isn't registered yet.
     *
     * @return array
     */
    protected function get_locations_from_database() : array {
        global $wpdb;

        if ( ! isset( $wpdb ) ) {
            return [];
        }

        $taxonomies = array_filter( $this->get_supported_taxonomies() );

        if ( empty( $taxonomies ) ) {
            return [];
        }

        $placeholders = implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) );

        $query = sprintf(
            'SELECT t.term_id, t.name FROM %1$s AS t INNER JOIN %2$s AS tt ON t.term_id = tt.term_id WHERE tt.taxonomy IN (%3$s)',
            $wpdb->terms,
            $wpdb->term_taxonomy,
            $placeholders
        );

        $prepared = $wpdb->prepare( $query, ...$taxonomies );

        if ( false === $prepared ) {
            return [];
        }

        $results = $wpdb->get_results( $prepared );

        if ( empty( $results ) ) {
            return [];
        }

        $locations = [];

        foreach ( $results as $row ) {
            $term_id = isset( $row->term_id ) ? (int) $row->term_id : 0;
            if ( $term_id <= 0 ) {
                continue;
            }

            $name = isset( $row->name ) ? trim( (string) $row->name ) : '';
            if ( '' === $name ) {
                $name = (string) $term_id;
            }

            $locations[ $term_id ] = [
                'id'   => $term_id,
                'name' => $name,
            ];
        }

        return $locations;
    }

    /**
     * Retrieve a list of taxonomies used by MultiLoca variants.
     *
     * @return array
     */
    protected function get_supported_taxonomies() : array {
        return [
            'multiloca_location',
            'locations-lite',
        ];
    }

    /**
     * Retrieve a list of post types used by MultiLoca variants.
     *
     * @return array
     */
    protected function get_supported_post_types() : array {
        return [
            'multiloca_location',
        ];
    }

    /**
     * Update stock information directly through product meta when no helper is available.
     *
     * @param int        $product_id  Product identifier.
     * @param string|int $location_id Location identifier.
     * @param float      $quantity    Quantity to set.
     *
     * @return bool
     */
    protected function update_stock_via_post_meta( int $product_id, $location_id, float $quantity ) : bool {
        $meta_location_id = $this->normalize_location_meta_id( $location_id );

        if ( '' === $meta_location_id ) {
            return false;
        }

        if ( ! function_exists( 'wc_get_product' ) ) {
            return false;
        }

        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            return false;
        }

        if ( function_exists( 'wc_stock_amount' ) ) {
            $quantity = wc_stock_amount( $quantity );
        } else {
            $quantity = (float) $quantity;
        }

        $meta_key = sprintf( 'wcmlim_stock_at_%s', $meta_location_id );

        update_post_meta( $product_id, $meta_key, $quantity );

        return true;
    }

    /**
     * Normalize a location identifier to be used with stock meta keys.
     *
     * @param mixed $location_id Raw location identifier.
     *
     * @return string
     */
    protected function normalize_location_meta_id( $location_id ) : string {
        if ( is_object( $location_id ) ) {
            if ( isset( $location_id->term_id ) ) {
                $location_id = $location_id->term_id;
            } elseif ( isset( $location_id->id ) ) {
                $location_id = $location_id->id;
            } elseif ( isset( $location_id->ID ) ) {
                $location_id = $location_id->ID;
            }
        }

        $location_id = trim( (string) $location_id );

        if ( '' === $location_id ) {
            return '';
        }

        $numeric = preg_replace( '/[^0-9]/', '', $location_id );

        if ( '' !== $numeric ) {
            return $numeric;
        }

        return preg_replace( '/[^A-Za-z0-9_-]/', '', $location_id );
    }

    /**
     * Obtain the location identifier associated with an order.
     *
     * @param int|WC_Order $order Order ID or object.
     *
     * @return string
     */
    public function get_order_location( $order ) : string {
        if ( ! $this->is_active() ) {
            return '';
        }

        $order_id = $order;
        if ( is_object( $order ) && is_a( $order, 'WC_Order' ) ) {
            $order_id = $order->get_id();
        } else {
            $order_id = absint( $order );
        }

        if ( empty( $order_id ) ) {
            return '';
        }

        if ( function_exists( 'multiloca_lite_get_order_location' ) ) {
            $location = multiloca_lite_get_order_location( $order_id );
            if ( ! empty( $location ) ) {
                return (string) $location;
            }
        }

        $meta_keys = [
            '_multiloca_location',
            '_multiloca_location_id',
            'multiloca_location',
        ];

        foreach ( $meta_keys as $meta_key ) {
            $meta_value = get_post_meta( $order_id, $meta_key, true );
            if ( ! empty( $meta_value ) ) {
                return (string) $meta_value;
            }
        }

        return '';
    }

    /**
     * Update the stock of a product for a given location.
     *
     * @param int         $product_id Product ID.
     * @param string|int  $location_id Location identifier.
     * @param float|int   $quantity Quantity to set.
     *
     * @return bool
     */
    public function update_stock( int $product_id, $location_id, $quantity ) : bool {
        if ( ! $this->is_active() ) {
            return false;
        }

        $quantity = floatval( $quantity );

        if ( function_exists( 'multiloca_lite_update_stock' ) ) {
            $result = multiloca_lite_update_stock( $product_id, $location_id, $quantity );
            if ( null !== $result ) {
                return (bool) $result;
            }
        }

        if ( is_object( $this->instance ) && isset( $this->instance->inventory ) && is_object( $this->instance->inventory ) ) {
            $inventory = $this->instance->inventory;
            if ( is_callable( [ $inventory, 'update_stock' ] ) ) {
                $result = call_user_func( [ $inventory, 'update_stock' ], $product_id, $location_id, $quantity );
                if ( null !== $result ) {
                    return (bool) $result;
                }
            }
            if ( is_callable( [ $inventory, 'set_stock' ] ) ) {
                $result = call_user_func( [ $inventory, 'set_stock' ], $product_id, $location_id, $quantity );
                if ( null !== $result ) {
                    return (bool) $result;
                }
            }
        }

        if ( is_object( $this->instance ) && is_callable( [ $this->instance, 'update_stock' ] ) ) {
            $result = call_user_func( [ $this->instance, 'update_stock' ], $product_id, $location_id, $quantity );
            if ( null !== $result ) {
                return (bool) $result;
            }
        }

        if ( $this->update_stock_via_post_meta( $product_id, $location_id, $quantity ) ) {
            return true;
        }

        /**
         * Allow third-parties to handle the stock update.
         */
        do_action( 'multiloca_lite_update_stock', $product_id, $location_id, $quantity );

        return true;
    }

    /**
     * Update the stock for a WooCommerce product in a specific MultiLoca location.
     *
     * @param WC_Product $product     Product instance.
     * @param string|int $location_id Location identifier.
     * @param float|int  $quantity    Quantity to set.
     *
     * @return bool
     */
    public function update_location_stock( $product, $location_id, $quantity ) : bool {
        if ( ! $this->is_active() ) {
            return false;
        }

        if ( ! is_object( $product ) || ! is_a( $product, 'WC_Product' ) ) {
            return false;
        }

        return $this->update_stock( $product->get_id(), $location_id, $quantity );
    }

    /**
     * Try to locate the MultiLoca Lite plugin instance.
     *
     * @return object|null
     */
    protected function locate_instance() {
        if ( function_exists( 'multiloca_lite' ) ) {
            $instance = multiloca_lite();
            if ( is_object( $instance ) ) {
                return $instance;
            }
        }

        $possible_classes = [
            '\\MultiLocaLite\\Plugin',
            '\\MultiLocaLite\\MultiLoca',
            '\\MultiLoca\\Plugin',
            '\\MultiLoca\\MultiLoca',
            'MultiLoca_Lite',
            'MultiLoca',
            'Multiloca_Lite_Plugin',
        ];

        foreach ( $possible_classes as $class_name ) {
            if ( class_exists( $class_name ) ) {
                if ( is_callable( [ $class_name, 'instance' ] ) ) {
                    $instance = call_user_func( [ $class_name, 'instance' ] );
                    if ( is_object( $instance ) ) {
                        return $instance;
                    }
                }

                if ( is_callable( [ $class_name, 'get_instance' ] ) ) {
                    $instance = call_user_func( [ $class_name, 'get_instance' ] );
                    if ( is_object( $instance ) ) {
                        return $instance;
                    }
                }

                $object = new $class_name();
                if ( is_object( $object ) ) {
                    return $object;
                }
            }
        }

        if ( isset( $GLOBALS['multiloca_lite'] ) && is_object( $GLOBALS['multiloca_lite'] ) ) {
            return $GLOBALS['multiloca_lite'];
        }

        return null;
    }
}
