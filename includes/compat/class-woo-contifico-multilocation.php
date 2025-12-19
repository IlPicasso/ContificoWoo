<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Compatibility layer for MultiLoca Lite multi location plugin.
 */
class Woo_Contifico_MultiLocation_Compatibility {

private const ORDER_ITEM_LOCATION_META_KEY = '_woo_contifico_multiloca_location';

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
     * Cache for resolved location identifiers used in stock meta keys.
     *
     * @var array
     */
    protected $location_meta_id_cache = [];

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
        $this->location_meta_id_cache = [];
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
        $this->location_meta_id_cache = [];
    }

    /**
     * Receive the locations that were entered manually in the settings page.
     *
     * @param array $locations
     */
    public function set_manual_locations( array $locations ) : void {
        $this->manual_locations = $locations;
        $this->locations_cache        = null;
        $this->location_meta_id_cache = [];
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
     * Retrieve the human-readable label for a given location identifier.
     *
     * @since 4.4.0
     *
     * @param string|int $location_id Location identifier.
     *
     * @return string
     */
    public function get_location_label( $location_id ) : string {
        $location_id = trim( (string) $location_id );

        if ( '' === $location_id ) {
            return '';
        }

        $locations = $this->get_locations();

        if ( isset( $locations[ $location_id ] ) ) {
            return $this->extract_location_label_from_entry( $locations[ $location_id ] );
        }

        foreach ( $locations as $entry ) {
            $entry_id = $this->extract_location_identifier_from_entry( $entry );

            if ( '' === $entry_id ) {
                continue;
            }

            if ( $entry_id === $location_id ) {
                return $this->extract_location_label_from_entry( $entry );
            }
        }

        return '';
    }

    /**
     * Extract a location identifier from a MultiLoca entry payload.
     *
     * @param mixed $entry Location entry data.
     *
     * @return string
     */
    protected function extract_location_identifier_from_entry( $entry ) : string {
        if ( is_array( $entry ) ) {
            return isset( $entry['id'] )
                ? (string) $entry['id']
                : (string) ( $entry['location_id'] ?? '' );
        }

        if ( is_object( $entry ) ) {
            if ( isset( $entry->id ) ) {
                return (string) $entry->id;
            }

            if ( isset( $entry->location_id ) ) {
                return (string) $entry->location_id;
            }

            if ( isset( $entry->ID ) ) {
                return (string) $entry->ID;
            }
        }

        return trim( (string) $entry );
    }

    /**
     * Resolve a readable label for a location entry.
     *
     * @param mixed $entry Location entry data.
     *
     * @return string
     */
    protected function extract_location_label_from_entry( $entry ) : string {
        if ( is_array( $entry ) ) {
            $label = $entry['name'] ?? $entry['title'] ?? $entry['slug'] ?? '';

            if ( '' !== $label ) {
                return wp_strip_all_tags( (string) $label );
            }

            return wp_strip_all_tags( (string) ( $entry['id'] ?? $entry['location_id'] ?? '' ) );
        }

        if ( is_object( $entry ) ) {
            $label = $entry->name ?? $entry->title ?? $entry->post_title ?? '';

            if ( '' !== $label ) {
                return wp_strip_all_tags( (string) $label );
            }

            return wp_strip_all_tags(
                (string) ( $entry->id ?? $entry->location_id ?? $entry->ID ?? '' )
            );
        }

        return wp_strip_all_tags( (string) $entry );
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
            'locations',
            'locations-lite',
            'multiloca_location',
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

        if ( function_exists( 'multiloca_link_location_to_product_if_exists' ) ) {
            multiloca_link_location_to_product_if_exists( $product_id, $meta_location_id );

            if ( $product->is_type( 'variation' ) ) {
                $parent_id = $product->get_parent_id();

                if ( $parent_id ) {
                    multiloca_link_location_to_product_if_exists( $parent_id, $meta_location_id );
                }
            }
        }

        update_post_meta( $product_id, $meta_key, $quantity );

        if ( function_exists( 'manage_stock' ) ) {
            manage_stock( $product, $meta_location_id, $quantity );
        }

        if ( function_exists( 'update_availability' ) ) {
            update_availability( $product, $meta_location_id, $quantity );
        }

        if ( function_exists( 'wcmlim_calculate_and_update_total_stock' ) ) {
            wcmlim_calculate_and_update_total_stock( $product_id );
        }

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

        if ( isset( $this->location_meta_id_cache[ $location_id ] ) ) {
            return $this->location_meta_id_cache[ $location_id ];
        }

        if ( ctype_digit( $location_id ) ) {
            $term_id          = (int) $location_id;
            $primary_taxonomy = taxonomy_exists( 'locations' ) ? 'locations' : '';

            if ( $primary_taxonomy ) {
                $primary_term = get_term( $term_id, $primary_taxonomy );

                if ( $primary_term && ! is_wp_error( $primary_term ) ) {
                    return $this->location_meta_id_cache[ $location_id ] = (string) $term_id;
                }
            }

            if ( $primary_taxonomy ) {
                foreach ( $this->get_supported_taxonomies() as $taxonomy ) {
                    if ( $taxonomy === $primary_taxonomy || ! taxonomy_exists( $taxonomy ) ) {
                        continue;
                    }

                    $term = get_term( $term_id, $taxonomy );

                    if ( ! $term || is_wp_error( $term ) ) {
                        continue;
                    }

                    $slug = isset( $term->slug ) ? (string) $term->slug : '';
                    $name = isset( $term->name ) ? (string) $term->name : '';

                    if ( '' !== $slug ) {
                        $resolved = get_term_by( 'slug', $slug, $primary_taxonomy );

                        if ( $resolved && ! is_wp_error( $resolved ) && isset( $resolved->term_id ) ) {
                            return $this->location_meta_id_cache[ $location_id ] = (string) $resolved->term_id;
                        }
                    }

                    if ( '' !== $name ) {
                        $resolved = get_term_by( 'name', $name, $primary_taxonomy );

                        if ( $resolved && ! is_wp_error( $resolved ) && isset( $resolved->term_id ) ) {
                            return $this->location_meta_id_cache[ $location_id ] = (string) $resolved->term_id;
                        }
                    }
                }
            }
        }

        $resolved = $this->resolve_location_meta_id_from_taxonomies( $location_id );

        if ( '' !== $resolved ) {
            return $this->location_meta_id_cache[ $location_id ] = $resolved;
        }

        $numeric = preg_replace( '/[^0-9]/', '', $location_id );

        if ( '' !== $numeric ) {
            return $this->location_meta_id_cache[ $location_id ] = $numeric;
        }

        $normalized = preg_replace( '/[^A-Za-z0-9_-]/', '', $location_id );

        return $this->location_meta_id_cache[ $location_id ] = $normalized;
    }

    /**
     * Attempt to resolve a location identifier to an existing taxonomy term ID.
     *
     * @param string $location_identifier Location identifier from the mapping table.
     *
     * @return string
     */
    protected function resolve_location_meta_id_from_taxonomies( string $location_identifier ) : string {
        $slug = sanitize_title( $location_identifier );

        foreach ( $this->get_supported_taxonomies() as $taxonomy ) {
            if ( ! taxonomy_exists( $taxonomy ) ) {
                continue;
            }

            if ( '' !== $slug ) {
                $term = get_term_by( 'slug', $slug, $taxonomy );

                if ( $term && ! is_wp_error( $term ) && isset( $term->term_id ) ) {
                    return (string) $term->term_id;
                }
            }

            $term = get_term_by( 'name', $location_identifier, $taxonomy );

            if ( $term && ! is_wp_error( $term ) && isset( $term->term_id ) ) {
                return (string) $term->term_id;
            }
        }

        return '';
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
     * Retrieve the location identifier associated with a specific order item.
     *
     * @since 4.4.0
     *
     * @param WC_Order_Item $item Order item instance.
     *
     * @return string
     */
    public function get_order_item_location( $item ) : string {
        if ( ! $this->is_active() ) {
            return '';
        }

        if ( ! is_object( $item ) || ! is_a( $item, 'WC_Order_Item' ) ) {
            return '';
        }

        foreach ( $this->get_order_item_location_meta_keys() as $meta_key ) {
            $meta_value = $item->get_meta( $meta_key, true );
            $meta_value = $this->sanitize_order_item_location_id( $meta_value );

            if ( '' === $meta_value ) {
                continue;
            }

            if ( self::ORDER_ITEM_LOCATION_META_KEY !== $meta_key ) {
                $this->persist_order_item_location_meta( $item, $meta_value );
            }

            return $meta_value;
        }

        $order = method_exists( $item, 'get_order' ) ? $item->get_order() : null;

        $refunded_item_id = 0;
        if ( method_exists( $item, 'get_meta' ) ) {
            $refunded_item_id = absint( $item->get_meta( '_refunded_item_id', true ) );
        }

        if ( $refunded_item_id > 0 && $order && method_exists( $order, 'get_item' ) ) {
            $refunded_item = $order->get_item( $refunded_item_id );

            if ( $refunded_item && is_a( $refunded_item, 'WC_Order_Item' ) ) {
                $refunded_location = $this->sanitize_order_item_location_id( $refunded_item->get_meta( self::ORDER_ITEM_LOCATION_META_KEY, true ) );

                if ( '' !== $refunded_location ) {
                    $this->persist_order_item_location_meta( $item, $refunded_location );

                    return $refunded_location;
                }

                if ( $refunded_item->get_id() !== $item->get_id() ) {
                    $refunded_location = $this->sanitize_order_item_location_id( $this->get_order_item_location( $refunded_item ) );

                    if ( '' !== $refunded_location ) {
                        $this->persist_order_item_location_meta( $item, $refunded_location );

                        return $refunded_location;
                    }
                }
            }
        }

        if ( $order && is_a( $order, 'WC_Order' ) ) {
            $location_id = $this->sanitize_order_item_location_id( $this->get_order_location( $order ) );

            if ( '' !== $location_id ) {
                $this->persist_order_item_location_meta( $item, $location_id );

                return $location_id;
            }
        }

        return '';
    }

    /**
     * Persist the detected location identifier as order item metadata.        return '';
    }

    /**
     * Persist the detected location identifier as order item metadata.
     *
     * @since 4.4.0
     */
    protected function persist_order_item_location_meta( $item, string $location_id ) : void {
        if ( ! is_object( $item ) || ! is_a( $item, 'WC_Order_Item' ) ) {
            return;
        }

        $location_id = $this->sanitize_order_item_location_id( $location_id );

        if ( '' === $location_id ) {
            return;
        }

        $current = $this->sanitize_order_item_location_id( $item->get_meta( self::ORDER_ITEM_LOCATION_META_KEY, true ) );

        if ( $current === $location_id ) {
            return;
        }

        if ( $item->get_id() > 0 ) {
            $item->update_meta_data( self::ORDER_ITEM_LOCATION_META_KEY, $location_id );
            $item->save();
        } else {
            $item->add_meta_data( self::ORDER_ITEM_LOCATION_META_KEY, $location_id, true );
        }
    }

    /**
     * Capture the location identifier for an order item using checkout data.
     *
     * @since 4.4.0
     *
     * @param WC_Order_Item      $item   Order item instance.
     * @param array              $values Cart item values passed by WooCommerce.
     * @param WC_Order|int|null  $order  Optional order context.
     *
     * @return void
     */
    public function store_order_item_location_from_checkout_values( $item, array $values, $order = null ) : void {
        if ( ! $this->is_active() ) {
            return;
        }

        if ( ! is_object( $item ) || ! is_a( $item, 'WC_Order_Item' ) ) {
            return;
        }

        $location_id = $this->extract_location_from_order_item_values( $values );

        if ( '' === $location_id && $order && is_a( $order, 'WC_Order' ) ) {
            $location_id = $this->sanitize_order_item_location_id( $this->get_order_location( $order ) );
        }

        if ( '' === $location_id ) {
            return;
        }

        $this->persist_order_item_location_meta( $item, $location_id );
    }

    /**
     * Extract a MultiLoca location identifier from a cart item data array.
     *
     * @since 4.4.0
     */
    protected function extract_location_from_order_item_values( array $values ) : string {
        $location_keys = [
            'multiloca_location',
            '_multiloca_location',
            '_multiloca_location_id',
            'multiloca_location_id',
            'location_id',
            'location',
            'wcmlim_location_id',
            'wcmlim_location',
        ];

        foreach ( $location_keys as $key ) {
            if ( isset( $values[ $key ] ) ) {
                $location = $this->sanitize_order_item_location_id( $values[ $key ] );

                if ( '' !== $location ) {
                    return $location;
                }
            }
        }

        $filtered = apply_filters( 'woo_contifico_multilocation_order_item_checkout_location', '', $values );
        $filtered = $this->sanitize_order_item_location_id( $filtered );

        return $filtered;
    }

    /**
     * Normalize raw identifiers pulled from order or cart context.
     *
     * @since 4.4.0
     */
    protected function sanitize_order_item_location_id( $location_id ) : string {
        if ( is_scalar( $location_id ) ) {
            $location_id = trim( (string) $location_id );
        } else {
            $location_id = '';
        }

        return $location_id;
    }

    /**
     * Return the metadata keys that may contain item level locations.
     *
     * @since 4.4.0
     */
    protected function get_order_item_location_meta_keys() : array {
        return [
            self::ORDER_ITEM_LOCATION_META_KEY,
            '_multiloca_location',
            '_multiloca_location_id',
            'multiloca_location',
            'wcmlim_location_id',
            'wcmlim_location',
        ];
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
        if ( ! $this->should_allow_stock_updates() ) {
            return false;
        }

        $quantity = floatval( $quantity );

        if ( function_exists( 'multiloca_lite_update_stock' ) ) {
            $result = multiloca_lite_update_stock( $product_id, $location_id, $quantity );
            if ( null !== $result ) {
                if ( (bool) $result ) {
                    return true;
                }
            }
        }

        if ( is_object( $this->instance ) && isset( $this->instance->inventory ) && is_object( $this->instance->inventory ) ) {
            $inventory = $this->instance->inventory;
            if ( is_callable( [ $inventory, 'update_stock' ] ) ) {
                $result = call_user_func( [ $inventory, 'update_stock' ], $product_id, $location_id, $quantity );
                if ( null !== $result ) {
                    if ( (bool) $result ) {
                        return true;
                    }
                }
            }
            if ( is_callable( [ $inventory, 'set_stock' ] ) ) {
                $result = call_user_func( [ $inventory, 'set_stock' ], $product_id, $location_id, $quantity );
                if ( null !== $result ) {
                    if ( (bool) $result ) {
                        return true;
                    }
                }
            }
        }

        if ( is_object( $this->instance ) && is_callable( [ $this->instance, 'update_stock' ] ) ) {
            $result = call_user_func( [ $this->instance, 'update_stock' ], $product_id, $location_id, $quantity );
            if ( null !== $result ) {
                if ( (bool) $result ) {
                    return true;
                }
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
        if ( ! $this->should_allow_stock_updates() ) {
            return false;
        }

        if ( ! is_object( $product ) || ! is_a( $product, 'WC_Product' ) ) {
            return false;
        }

        $updated = $this->update_stock( $product->get_id(), $location_id, $quantity );

        if ( ! $updated ) {
            return false;
        }

        if ( $product->is_type( 'variation' ) ) {
            $this->sync_parent_location_stock( $product, $location_id );
        }

        return true;
    }

    /**
     * Recalculate and persist the aggregate stock for the variable parent when a variation changes.
     *
     * @param WC_Product $variation    Variation product instance.
     * @param mixed      $location_id  Raw location identifier.
     *
     * @return void
     */
    protected function sync_parent_location_stock( $variation, $location_id ) : void {
        if ( ! is_object( $variation ) || ! is_a( $variation, 'WC_Product' ) ) {
            return;
        }

        if ( ! $variation->is_type( 'variation' ) ) {
            return;
        }

        $parent_id = $variation->get_parent_id();

        if ( $parent_id <= 0 ) {
            return;
        }

        $meta_location_id = $this->normalize_location_meta_id( $location_id );

        if ( '' === $meta_location_id ) {
            return;
        }

        if ( ! function_exists( 'wc_get_product' ) ) {
            return;
        }

        $parent = wc_get_product( $parent_id );

        if ( ! $parent || ! $parent->is_type( 'variable' ) ) {
            return;
        }

        $meta_key = sprintf( 'wcmlim_stock_at_%s', $meta_location_id );
        $total    = 0.0;

        foreach ( (array) $parent->get_children() as $child_id ) {
            $child_stock = get_post_meta( $child_id, $meta_key, true );

            if ( '' === $child_stock ) {
                continue;
            }

            $total += (float) $child_stock;
        }

        if ( function_exists( 'wc_stock_amount' ) ) {
            $total = wc_stock_amount( $total );
        }

        $current = get_post_meta( $parent_id, $meta_key, true );

        if ( '' === $current ) {
            $current = null;
        } else {
            $current = (float) $current;

            if ( function_exists( 'wc_stock_amount' ) ) {
                $current = wc_stock_amount( $current );
            }
        }

        if ( null !== $current && (float) $current === (float) $total ) {
            return;
        }

        update_post_meta( $parent_id, $meta_key, $total );
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
            'Multiloca_Lite_Taxonomy',
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

    /**
     * Determine if we should attempt stock updates even if the plugin instance is not detected.
     *
     * @return bool
     */
    protected function should_allow_stock_updates() : bool {
        if ( $this->is_active() ) {
            return true;
        }

        if ( function_exists( 'multiloca_link_location_to_product_if_exists' ) ) {
            return true;
        }

        if ( function_exists( 'wcmlim_calculate_and_update_total_stock' ) ) {
            return true;
        }

        if ( function_exists( 'manage_stock' ) || function_exists( 'update_availability' ) ) {
            return true;
        }

        foreach ( $this->get_supported_taxonomies() as $taxonomy ) {
            if ( taxonomy_exists( $taxonomy ) ) {
                return true;
            }
        }

        return false;
    }
}
