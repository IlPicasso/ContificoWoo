<?php

use Pahp\SDK\Contifico;

/**
 * Diagnostics helper to compare WooCommerce and Contifico products.
 *
 * @since 4.2.0
 */
class Woo_Contifico_Diagnostics {

    /**
     * Contifico SDK instance.
     *
     * @var Contifico
     */
    private $contifico;

    /**
     * Constructor.
     *
     * @param Contifico $contifico Contifico SDK instance.
     */
    public function __construct( Contifico $contifico ) {
        $this->contifico = $contifico;
    }

    /**
     * Build diagnostics data and cache it in a transient.
     *
     * @param bool $force_refresh Optional. Whether to bypass the cached transient.
     *
     * @return array{
     *     entries:array<int,array<string,mixed>>,
     *     summary:array<string,int>,
     *     generated_at:int
     * }
     */
    public function build_diagnostics( bool $force_refresh = false ) : array {
        if ( ! $force_refresh ) {
            $cached = get_transient( 'woo_contifico_diagnostics' );

            if ( is_array( $cached ) ) {
                return $cached;
            }
        }

        $contifico_inventory  = $this->load_contifico_inventory();
        $contifico_by_code    = $contifico_inventory['by_code'];
        $contifico_by_sku     = $contifico_inventory['by_sku'];
        $diagnostics          = [];
        $sku_registry         = [];
        $summary              = [
            'woocommerce_products'     => 0,
            'woocommerce_variations'   => 0,
            'entries_total'            => 0,
            'matched_entries'          => 0,
            'entries_with_errors'      => 0,
            'entries_needing_attention' => 0,
            'entries_synced'           => 0,
            'entries_without_match'    => 0,
            'contifico_items'          => $contifico_inventory['count'],
        ];
        $woocommerce_products = wc_get_products(
            [
                'limit'   => -1,
                'type'    => [ 'simple', 'variable' ],
                'orderby' => 'ID',
                'order'   => 'ASC',
                'status'  => array_keys( get_post_stati() ),
            ]
        );

        foreach ( $woocommerce_products as $product ) {
            if ( ! $product instanceof WC_Product ) {
                continue;
            }

            $entry = $this->build_product_entry( $product );

            $diagnostics[] = $entry;
            $index         = count( $diagnostics ) - 1;

            ++$summary['woocommerce_products'];

            if ( '' !== $entry['sku_detectado'] ) {
                $sku_registry[ $entry['sku_detectado'] ][] = $index;
            }

            if ( $product->is_type( 'variable' ) ) {
                $variation_ids = [];

                if ( function_exists( 'wc_get_product_variations' ) ) {
                    $variation_rows = wc_get_product_variations( $product->get_id(), [ 'limit' => -1 ] );

                    foreach ( (array) $variation_rows as $variation_row ) {
                        if ( empty( $variation_row['variation_id'] ) ) {
                            continue;
                        }

                        $variation_ids[] = (int) $variation_row['variation_id'];
                    }
                }

                $variation_ids = array_merge( $variation_ids, array_map( 'absint', (array) $product->get_children() ) );
                $variation_ids = array_values( array_filter( array_unique( $variation_ids ) ) );

                foreach ( $variation_ids as $variation_id ) {
                    $variation = wc_get_product( $variation_id );

                    if ( ! $variation instanceof WC_Product_Variation ) {
                        continue;
                    }

                    $variation_entry = $this->build_variation_entry( $variation, $product );

                    $diagnostics[] = $variation_entry;
                    $variation_idx = count( $diagnostics ) - 1;

                    ++$summary['woocommerce_variations'];

                    if ( '' !== $variation_entry['sku_detectado'] ) {
                        $sku_registry[ $variation_entry['sku_detectado'] ][] = $variation_idx;
                    }
                }
            }
        }

        $diagnostics = $this->flag_duplicate_and_missing_skus( $diagnostics, $sku_registry );
        $diagnostics = $this->attach_contifico_matches( $diagnostics, $contifico_by_code, $contifico_by_sku );
        $diagnostics = $this->annotate_parent_variation_mismatches( $diagnostics );

        foreach ( $diagnostics as $entry ) {
            ++$summary['entries_total'];

            $codigo_contifico = isset( $entry['codigo_contifico'] ) ? (string) $entry['codigo_contifico'] : '';
            $errores          = isset( $entry['error_detectado'] ) && is_array( $entry['error_detectado'] )
                ? $entry['error_detectado']
                : [];
            $tipo             = isset( $entry['tipo'] ) ? (string) $entry['tipo'] : '';
            $variaciones_sin  = isset( $entry['variaciones_sin_coincidencia'] ) && is_array( $entry['variaciones_sin_coincidencia'] )
                ? $entry['variaciones_sin_coincidencia']
                : [];
            $maneja_stock     = array_key_exists( 'managing_stock', $entry ) ? $entry['managing_stock'] : null;
            $variation_count  = isset( $entry['variation_count'] ) ? (int) $entry['variation_count'] : 0;
            $requiere_revision = false;

            if ( ! empty( $errores ) ) {
                $requiere_revision = true;
            }

            if ( 'variable' === $tipo && ! empty( $variaciones_sin ) ) {
                $requiere_revision = true;
            }

            if ( 'variation' === $tipo && false === $maneja_stock ) {
                $requiere_revision = true;
            }

            if (
                in_array( $tipo, [ 'simple', 'variable' ], true )
                && false === $maneja_stock
                && ( 'simple' === $tipo || $variation_count <= 0 )
            ) {
                $requiere_revision = true;
            }

            if ( '' !== $codigo_contifico ) {
                ++$summary['matched_entries'];

                if ( ! $requiere_revision ) {
                    ++$summary['entries_synced'];
                }
            } else {
                ++$summary['entries_without_match'];
                $requiere_revision = true;
            }

            if ( ! empty( $errores ) ) {
                ++$summary['entries_with_errors'];
            }
        }

        $summary['entries_needing_attention'] = max( 0, $summary['entries_total'] - $summary['entries_synced'] );

        $result = [
            'entries'      => $diagnostics,
            'summary'      => $summary,
            'generated_at' => time(),
        ];

        set_transient( 'woo_contifico_diagnostics', $result, 10 * MINUTE_IN_SECONDS );

        return $result;
    }

    /**
     * Load Contifico inventory and build quick lookup tables.
     *
     * @return array{by_code:array<string,array>,by_sku:array<string,array<int,array>>,count:int}
     */
    private function load_contifico_inventory() : array {
        $inventory = $this->contifico->get_products();

        if ( ! is_array( $inventory ) ) {
            $inventory = [];
        }
        $by_code   = [];
        $by_sku    = [];
        $count     = 0;

        foreach ( $inventory as $product ) {
            if ( ! is_array( $product ) ) {
                continue;
            }

            ++$count;

            $codigo       = isset( $product['codigo'] ) ? (string) $product['codigo'] : '';
            $sku          = isset( $product['sku'] ) ? (string) $product['sku'] : $codigo;
            $codigo_index = $this->normalize_identifier( $codigo );
            $sku_index    = $this->normalize_identifier( $sku );

            if ( '' !== $codigo_index && ! isset( $by_code[ $codigo_index ] ) ) {
                $by_code[ $codigo_index ] = $product;
            }

            if ( '' === $sku_index ) {
                continue;
            }

            if ( ! isset( $by_sku[ $sku_index ] ) ) {
                $by_sku[ $sku_index ] = [];
            }

            $by_sku[ $sku_index ][] = $product;
        }

        return [
            'by_code' => $by_code,
            'by_sku'  => $by_sku,
            'count'   => $count,
        ];
    }

    /**
     * Build the diagnostics entry for a standard product.
     *
     * @param WC_Product $product Product instance.
     *
     * @return array<string,mixed>
     */
    private function build_product_entry( WC_Product $product ) : array {
        $managing_stock = method_exists( $product, 'managing_stock' )
            ? (bool) $product->managing_stock()
            : (bool) $product->get_manage_stock();
        $variation_count = $product->is_type( 'variable' )
            ? count( (array) $product->get_children() )
            : 0;

        return [
            'post_id'                => $product->get_id(),
            'nombre'                 => $product->get_name(),
            'tipo'                   => $product->get_type(),
            'sku_detectado'          => trim( (string) $product->get_sku() ),
            'error_detectado'        => [],
            'codigo_contifico'       => '',
            'coincidencias_posibles' => [],
            'variaciones_sin_coincidencia' => [],
            'managing_stock'         => $managing_stock,
            'variation_count'        => $variation_count,
        ];
    }

    /**
     * Build the diagnostics entry for a product variation.
     *
     * @param WC_Product_Variation $variation Variation instance.
     * @param WC_Product           $parent    Parent product instance.
     *
     * @return array<string,mixed>
     */
    private function build_variation_entry( WC_Product_Variation $variation, WC_Product $parent ) : array {
        $size_slug      = $this->find_size_slug( $variation );
        $parent_sku     = trim( (string) $parent->get_sku() );
        $variation_sku  = trim( (string) $variation->get_sku() );
        $candidate_sku  = '';
        $inferred_size  = '';

        if ( '' !== $parent_sku && '' !== $size_slug ) {
            $candidate_sku = sprintf( '%s/%s', $parent_sku, $size_slug );
        }

        if ( '' === $candidate_sku && '' !== $parent_sku ) {
            $inferred_size = $this->infer_variation_suffix_from_sku( $variation_sku, $parent_sku );

            if ( '' !== $inferred_size ) {
                $candidate_sku = sprintf( '%s/%s', $parent_sku, $inferred_size );
            }
        }

        $managing_stock = method_exists( $variation, 'managing_stock' )
            ? (bool) $variation->managing_stock()
            : (bool) $variation->get_manage_stock();

        return [
            'post_id'                => $variation->get_id(),
            'nombre'                 => $variation->get_name(),
            'tipo'                   => $variation->get_type(),
            'sku_detectado'          => $variation_sku,
            'error_detectado'        => [],
            'codigo_contifico'       => '',
            'coincidencias_posibles' => [],
            'parent_id'              => $parent->get_id(),
            'managing_stock'         => $managing_stock,
            '_variation_meta'        => [
                'candidate_sku' => $candidate_sku,
                'size_slug'     => $size_slug,
                'inferred_size' => $inferred_size,
            ],
        ];
    }

    /**
     * Locate the size slug for a variation.
     *
     * @param WC_Product_Variation $variation Variation instance.
     *
     * @return string
     */
    private function find_size_slug( WC_Product_Variation $variation ) : string {
        $attributes = $variation->get_attributes();

        if ( empty( $attributes ) ) {
            return '';
        }

        foreach ( $attributes as $key => $value ) {
            $key = strtolower( (string) $key );
            $key = str_replace( 'attribute_', '', $key );

            if ( false === strpos( $key, 'talla' ) ) {
                continue;
            }

            return sanitize_title( (string) $value );
        }

        return '';
    }

    /**
     * Infer a Contifico-ready size suffix from a variation SKU when attributes are unavailable.
     *
     * @param string $variation_sku Variation SKU.
     * @param string $parent_sku    Parent SKU.
     *
     * @return string
     */
    private function infer_variation_suffix_from_sku( string $variation_sku, string $parent_sku ) : string {
        $variation_sku = trim( $variation_sku );
        $parent_sku    = trim( $parent_sku );

        if ( '' === $variation_sku || '' === $parent_sku ) {
            return '';
        }

        if ( 0 !== strpos( $variation_sku, $parent_sku ) ) {
            return '';
        }

        $suffix = substr( $variation_sku, strlen( $parent_sku ) );
        $suffix = ltrim( $suffix );

        if ( '' === $suffix ) {
            return '';
        }

        $suffix = ltrim( $suffix, "-_/\t " );

        if ( '' === $suffix ) {
            return '';
        }

        return $suffix;
    }

    /**
     * Normalize an identifier for consistent comparisons.
     *
     * @param string $value Identifier value.
     *
     * @return string
     */
    private function normalize_identifier( string $value ) : string {
        $value = trim( $value );

        if ( '' === $value ) {
            return '';
        }

        if ( function_exists( 'mb_strtoupper' ) ) {
            $value = mb_strtoupper( $value );
        } else {
            $value = strtoupper( $value );
        }

        return $value;
    }

    /**
     * Mark duplicate and missing SKUs in the diagnostics set.
     *
     * @param array<int,array<string,mixed>> $diagnostics Diagnostics entries.
     * @param array<string,array<int>>       $sku_registry Indexes grouped by SKU.
     *
     * @return array<int,array<string,mixed>>
     */
    private function flag_duplicate_and_missing_skus( array $diagnostics, array $sku_registry ) : array {
        foreach ( $diagnostics as $index => $entry ) {
            if ( '' === $entry['sku_detectado'] ) {
                $diagnostics[ $index ]['error_detectado'][] = 'missing_sku';
            }
        }

        foreach ( $sku_registry as $indexes ) {
            if ( count( $indexes ) <= 1 ) {
                continue;
            }

            foreach ( $indexes as $index ) {
                $diagnostics[ $index ]['error_detectado'][] = 'duplicate_sku';
            }
        }

        return $diagnostics;
    }

    /**
     * Attach matching Contifico information and remove helper metadata.
     *
     * @param array<int,array<string,mixed>>    $diagnostics       Diagnostics entries.
     * @param array<string,array<string,mixed>> $contifico_by_code Contifico products indexed by code.
     * @param array<string,array<int,array>>    $contifico_by_sku  Contifico products indexed by SKU.
     *
     * @return array<int,array<string,mixed>>
     */
    private function attach_contifico_matches( array $diagnostics, array $contifico_by_code, array $contifico_by_sku ) : array {
        foreach ( $diagnostics as $index => $entry ) {
            $code_matches          = [];
            $sku_only_matches      = [];
            $candidate_matches     = [];
            $coincidencias         = [];
            $sku_detectado         = $entry['sku_detectado'];
            $sku_key               = $this->normalize_identifier( $sku_detectado );
            $variation_meta        = isset( $entry['_variation_meta'] ) && is_array( $entry['_variation_meta'] )
                ? $entry['_variation_meta']
                : [];
            $candidate_from_talla  = isset( $variation_meta['candidate_sku'] ) ? (string) $variation_meta['candidate_sku'] : '';
            $candidate_key         = $this->normalize_identifier( $candidate_from_talla );

            if ( '' !== $sku_key ) {
                if ( isset( $contifico_by_code[ $sku_key ] ) ) {
                    $code_matches[] = $contifico_by_code[ $sku_key ];
                }

                if ( isset( $contifico_by_sku[ $sku_key ] ) ) {
                    foreach ( $contifico_by_sku[ $sku_key ] as $match ) {
                        if ( ! is_array( $match ) ) {
                            continue;
                        }

                        $codigo = isset( $match['codigo'] ) ? (string) $match['codigo'] : '';
                        $codigo_key = $this->normalize_identifier( $codigo );

                        if ( '' === $codigo ) {
                            continue;
                        }

                        if ( $codigo_key === $sku_key ) {
                            $code_matches[] = $match;
                        } else {
                            $sku_only_matches[] = $match;
                        }
                    }
                }
            }

            if ( '' !== $candidate_from_talla ) {
                $coincidencias[] = $candidate_from_talla;

                if ( '' !== $candidate_key && isset( $contifico_by_code[ $candidate_key ] ) ) {
                    $candidate_matches[] = $contifico_by_code[ $candidate_key ];
                }

                if ( '' !== $candidate_key && isset( $contifico_by_sku[ $candidate_key ] ) ) {
                    $candidate_matches = array_merge( $candidate_matches, $contifico_by_sku[ $candidate_key ] );
                }
            }

            $matched_codes = $this->collect_unique_codes( $code_matches );

            if ( empty( $matched_codes ) ) {
                $sku_codes = $this->collect_unique_codes( $sku_only_matches );

                if ( 1 === count( $sku_codes ) ) {
                    $matched_codes = $sku_codes;
                }
            }

            if ( ! empty( $matched_codes ) ) {
                $diagnostics[ $index ]['codigo_contifico']       = $matched_codes[0];
                $diagnostics[ $index ]['coincidencias_posibles'] = $matched_codes;
            } else {
                $suggestion_codes = $this->collect_unique_codes( array_merge( $sku_only_matches, $candidate_matches ) );

                if ( ! empty( $suggestion_codes ) ) {
                    $coincidencias = array_merge( $coincidencias, $suggestion_codes );
                }

                if ( ! empty( $coincidencias ) ) {
                    $diagnostics[ $index ]['coincidencias_posibles'] = array_values( array_unique( $coincidencias ) );
                }
            }

            if ( isset( $diagnostics[ $index ]['_variation_meta'] ) ) {
                unset( $diagnostics[ $index ]['_variation_meta'] );
            }

            if ( ! empty( $diagnostics[ $index ]['error_detectado'] ) ) {
                $diagnostics[ $index ]['error_detectado'] = array_values( array_unique( $diagnostics[ $index ]['error_detectado'] ) );
            } else {
                $diagnostics[ $index ]['error_detectado'] = [];
            }
        }

        return $diagnostics;
    }

    /**
     * Attach variation mismatch context to parent products.
     *
     * @param array<int,array<string,mixed>> $diagnostics Diagnostics entries.
     *
     * @return array<int,array<string,mixed>>
     */
    private function annotate_parent_variation_mismatches( array $diagnostics ) : array {
        $missing_by_parent = [];

        foreach ( $diagnostics as $entry ) {
            if ( ! isset( $entry['tipo'] ) || 'variation' !== $entry['tipo'] ) {
                continue;
            }

            $parent_id = isset( $entry['parent_id'] ) ? (int) $entry['parent_id'] : 0;

            if ( $parent_id <= 0 ) {
                continue;
            }

            $codigo_contifico = isset( $entry['codigo_contifico'] ) ? (string) $entry['codigo_contifico'] : '';

            if ( '' !== $codigo_contifico ) {
                continue;
            }

            $label = isset( $entry['nombre'] ) ? (string) $entry['nombre'] : '';
            $sku   = isset( $entry['sku_detectado'] ) ? (string) $entry['sku_detectado'] : '';

            if ( '' === $label && '' !== $sku ) {
                /* translators: %s: variation SKU. */
                $label = sprintf( __( 'Variación con SKU %s', 'woo-contifico' ), $sku );
            } elseif ( '' === $label ) {
                $label = __( 'Variación sin nombre', 'woo-contifico' );
            }

            $missing_by_parent[ $parent_id ][] = $label;
        }

        foreach ( $diagnostics as $index => $entry ) {
            if ( ! isset( $entry['tipo'] ) || 'variable' !== $entry['tipo'] ) {
                continue;
            }

            $parent_id = isset( $entry['post_id'] ) ? (int) $entry['post_id'] : 0;
            $labels    = isset( $missing_by_parent[ $parent_id ] ) ? $missing_by_parent[ $parent_id ] : [];

            $diagnostics[ $index ]['variaciones_sin_coincidencia'] = array_values( array_unique( $labels ) );
        }

        return $diagnostics;
    }

    /**
     * Extract the unique Contifico codes from a match list.
     *
     * @param array<int,mixed> $matches Match rows to inspect.
     *
     * @return array<int,string>
     */
    private function collect_unique_codes( array $matches ) : array {
        $codes = [];

        foreach ( $matches as $match ) {
            if ( ! is_array( $match ) ) {
                continue;
            }

            $codigo = isset( $match['codigo'] ) ? (string) $match['codigo'] : '';

            if ( '' === $codigo || isset( $codes[ $codigo ] ) ) {
                continue;
            }

            $codes[ $codigo ] = $codigo;
        }

        return array_values( $codes );
    }
}
