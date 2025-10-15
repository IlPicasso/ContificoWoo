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
     * Constructor.
     */
    public function __construct() {
        $this->instance  = $this->locate_instance();
        $this->is_active = $this->determine_is_active();
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

        if ( taxonomy_exists( 'multiloca_location' ) ) {
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
        return $this->is_active;
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

        $function_sources = [
            'multiloca_lite_get_locations',
            'multiloca_get_locations',
        ];

        foreach ( $function_sources as $function_name ) {
            if ( function_exists( $function_name ) ) {
                $locations = call_user_func( $function_name );
                if ( is_array( $locations ) ) {
                    return $locations;
                }
            }
        }

        if ( isset( $this->instance->locations ) && is_object( $this->instance->locations ) ) {
            $manager = $this->instance->locations;
            if ( is_callable( [ $manager, 'get_locations' ] ) ) {
                $locations = call_user_func( [ $manager, 'get_locations' ] );
                if ( is_array( $locations ) ) {
                    return $locations;
                }
            }
        }

        if ( method_exists( $this->instance, 'get_locations' ) ) {
            $locations = $this->instance->get_locations();
            if ( is_array( $locations ) ) {
                return $locations;
            }
        }

        if ( taxonomy_exists( 'multiloca_location' ) ) {
            $terms = get_terms(
                [
                    'taxonomy'   => 'multiloca_location',
                    'hide_empty' => false,
                ]
            );

            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                $locations = [];

                foreach ( $terms as $term ) {
                    $locations[ $term->term_id ] = [
                        'id'   => $term->term_id,
                        'name' => $term->name,
                    ];
                }

                return $locations;
            }
        }

        return [];
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

        if ( isset( $this->instance->inventory ) && is_object( $this->instance->inventory ) ) {
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

        if ( is_callable( [ $this->instance, 'update_stock' ] ) ) {
            $result = call_user_func( [ $this->instance, 'update_stock' ], $product_id, $location_id, $quantity );
            if ( null !== $result ) {
                return (bool) $result;
            }
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
