<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table to visualise WooCommerce/Contifico diagnostics entries.
 */
class Woo_Contifico_Diagnostics_Table extends WP_List_Table {

    /**
     * Raw diagnostics entries.
     *
     * @var array<int,array<string,mixed>>
     */
    private $entries = [];

    /**
     * Constructor.
     *
     * @param array<int,array<string,mixed>> $entries Diagnostics entries.
     */
    public function __construct( array $entries ) {
        parent::__construct(
            [
                'singular' => 'diagnostico',
                'plural'   => 'diagnosticos',
                'ajax'     => false,
            ]
        );

        $this->entries = array_values( $entries );
    }

    /**
     * Retrieve the columns for the table.
     *
     * @return array<string,string>
     */
    public function get_columns() : array {
        return [
            'cb'             => '<input type="checkbox" />',
            'producto'       => __( 'Producto', 'woo-contifico' ),
            'tipo'           => __( 'Tipo', 'woo-contifico' ),
            'estado'         => __( 'Estado', 'woo-contifico' ),
            'sku_actual'     => __( 'SKU actual', 'woo-contifico' ),
            'problema'       => __( 'Problema', 'woo-contifico' ),
            'sugerencia'     => __( 'Sugerencia', 'woo-contifico' ),
            'acciones'       => __( 'Acciones', 'woo-contifico' ),
        ];
    }

    /**
     * Prepare items for display.
     */
    public function prepare_items() : void {
        $this->_column_headers = [ $this->get_columns(), [], [] ];

        $this->process_bulk_action();

        $items   = array_map( [ $this, 'normalize_entry' ], $this->entries );
        $filter  = $this->get_current_filter();
        $per_page = $this->get_items_per_page( 'woo_contifico_diagnostics_per_page', 20 );
        $current_page = max( 1, $this->get_pagenum() );

        if ( 'all' !== $filter ) {
            $items = array_values(
                array_filter(
                    $items,
                    static function ( array $item ) use ( $filter ) : bool {
                        if ( 'synced' === $filter ) {
                            return isset( $item['sync_status'] ) && 'synced' === $item['sync_status'];
                        }

                        if ( 'needs_attention' === $filter ) {
                            return (
                                isset( $item['sync_status'] )
                                && in_array(
                                    $item['sync_status'],
                                    [ 'needs_attention', 'unmatched' ],
                                    true
                                )
                            );
                        }

                        if ( ! isset( $item['problem_types'] ) || ! is_array( $item['problem_types'] ) ) {
                            return false;
                        }

                        return in_array( $filter, $item['problem_types'], true );
                    }
                )
            );
        }

        $total_items = count( $items );

        $offset = ( $current_page - 1 ) * $per_page;
        $items  = array_slice( $items, $offset, $per_page );

        $this->items = $items;

        $this->set_pagination_args(
            [
                'total_items' => $total_items,
                'per_page'    => $per_page,
                'total_pages' => ( 0 === $per_page ) ? 0 : (int) ceil( $total_items / $per_page ),
            ]
        );
    }

    /**
     * Message displayed when there are no items.
     */
    public function no_items() : void {
        esc_html_e( 'No se encontraron diagnósticos para mostrar.', 'woo-contifico' );
    }

    /**
     * Checkbox column.
     *
     * @param array<string,mixed> $item Current row item.
     *
     * @return string
     */
    protected function column_cb( $item ) : string { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        return sprintf(
            '<input type="checkbox" name="diagnosticos[]" value="%d" />',
            isset( $item['post_id'] ) ? (int) $item['post_id'] : 0
        );
    }

    /**
     * Render the product column.
     *
     * @param array<string,mixed> $item Current row item.
     *
     * @return string
     */
    public function column_producto( array $item ) : string {
        $title     = isset( $item['nombre'] ) ? (string) $item['nombre'] : '';
        $edit_link = isset( $item['post_id'] ) ? get_edit_post_link( (int) $item['post_id'] ) : '';
        $view_link = isset( $item['post_id'] ) ? get_permalink( (int) $item['post_id'] ) : '';

        if ( empty( $title ) ) {
            $title = __( '(Sin título)', 'woo-contifico' );
        }

        $label = esc_html( $title );

        if ( $edit_link ) {
            $label = sprintf( '<a href="%s">%s</a>', esc_url( $edit_link ), $label );
        }

        $actions = [];

        if ( $edit_link ) {
            $actions['edit'] = sprintf( '<a href="%s">%s</a>', esc_url( $edit_link ), esc_html__( 'Editar', 'woo-contifico' ) );
        }

        if ( $view_link ) {
            $actions['view'] = sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                esc_url( $view_link ),
                esc_html__( 'Ver', 'woo-contifico' )
            );
        }

        return sprintf( '<strong>%1$s</strong>%2$s', $label, $this->row_actions( $actions ) );
    }

    /**
     * Render the type column.
     *
     * @param array<string,mixed> $item Current row item.
     *
     * @return string
     */
    public function column_tipo( array $item ) : string {
        $type = isset( $item['tipo'] ) ? (string) $item['tipo'] : '';

        if ( function_exists( 'wc_get_product_type_label' ) && '' !== $type ) {
            $label = wc_get_product_type_label( $type );

            if ( $label ) {
                return esc_html( $label );
            }
        }

        if ( '' === $type ) {
            return '&mdash;';
        }

        return esc_html( ucfirst( $type ) );
    }

    /**
     * Render the sync status column.
     *
     * @param array<string,mixed> $item Current row item.
     *
     * @return string
     */
    public function column_estado( array $item ) : string {
        $status = isset( $item['sync_status'] ) ? (string) $item['sync_status'] : '';
        $class  = 'woo-contifico-diagnostics__status-tag';

        switch ( $status ) {
            case 'synced':
                $class .= ' status-synced';
                $label  = __( 'Sincronizado', 'woo-contifico' );
                break;
            case 'unmatched':
                $class .= ' status-unmatched';
                $label  = __( 'Sin coincidencia', 'woo-contifico' );
                break;
            case 'parent_placeholder':
                $class .= ' status-parent';
                $label  = __( 'Producto madre', 'woo-contifico' );
                break;
            case 'needs_attention':
            default:
                $class .= ' status-needs-attention';
                $label  = __( 'Requiere revisión', 'woo-contifico' );
                break;
        }

        if ( '' === $status ) {
            return '&mdash;';
        }

        return sprintf( '<span class="%1$s">%2$s</span>', esc_attr( $class ), esc_html( $label ) );
    }

    /**
     * Render the SKU column.
     *
     * @param array<string,mixed> $item Current row item.
     *
     * @return string
     */
    public function column_sku_actual( array $item ) : string {
        $sku = isset( $item['sku_detectado'] ) ? (string) $item['sku_detectado'] : '';

        if ( '' === $sku ) {
            return '<span class="woo-contifico-diagnostics__missing">&mdash;</span>';
        }

        return esc_html( $sku );
    }

    /**
     * Render the problem description column.
     *
     * @param array<string,mixed> $item Current row item.
     *
     * @return string
     */
    public function column_problema( array $item ) : string {
        $messages = isset( $item['problem_messages'] ) && is_array( $item['problem_messages'] )
            ? $item['problem_messages']
            : [];

        if ( empty( $messages ) ) {
            $messages = [ __( 'Sin inconsistencias detectadas.', 'woo-contifico' ) ];
        }

        $messages = array_map( 'wp_kses_post', $messages );

        return '<span class="woo-contifico-diagnostics__problem">' . implode( '<br />', $messages ) . '</span>';
    }

    /**
     * Render the suggestion column.
     *
     * @param array<string,mixed> $item Current row item.
     *
     * @return string
     */
    public function column_sugerencia( array $item ) : string {
        $messages = isset( $item['suggestion_messages'] ) && is_array( $item['suggestion_messages'] )
            ? $item['suggestion_messages']
            : [];

        if ( empty( $messages ) ) {
            $messages = [ __( 'No se requiere acción.', 'woo-contifico' ) ];
        }

        $messages = array_map( 'wp_kses_post', $messages );

        return '<span class="woo-contifico-diagnostics__suggestion">' . implode( '<br />', $messages ) . '</span>';
    }

    /**
     * Render the actions column.
     *
     * @param array<string,mixed> $item Current row item.
     *
     * @return string
     */
    public function column_acciones( array $item ) : string {
        $actions = [];

        $target_id = ! empty( $item['post_id'] ) ? (int) $item['post_id'] : 0;
        $type      = isset( $item['tipo'] ) ? (string) $item['tipo'] : '';

        if ( 'variation' === $type && ! empty( $item['parent_id'] ) ) {
            $target_id = (int) $item['parent_id'];
        }

        if ( $target_id > 0 ) {
            $edit_link = get_edit_post_link( $target_id );

            if ( $edit_link ) {
                $actions[] = sprintf(
                    '<a class="button button-small" href="%s">%s</a>',
                    esc_url( $edit_link ),
                    esc_html__( 'Editar producto', 'woo-contifico' )
                );
            }
        }

        if ( ! empty( $item['coincidencias_posibles'] ) && is_array( $item['coincidencias_posibles'] ) ) {
            $matches = array_slice( array_map( 'esc_html', $item['coincidencias_posibles'] ), 0, 3 );

            if ( ! empty( $matches ) ) {
                $actions[] = sprintf(
                    '<span class="woo-contifico-diagnostics__matches">%s <strong>%s</strong></span>',
                    esc_html__( 'Coincidencias sugeridas:', 'woo-contifico' ),
                    esc_html( implode( ', ', $matches ) )
                );
            }
        }

        if ( empty( $actions ) ) {
            return '<span class="woo-contifico-diagnostics__no-action">&mdash;</span>';
        }

        return implode( '<br />', $actions );
    }

    /**
     * Default column handler.
     *
     * @param array<string,mixed> $item        Current row item.
     * @param string              $column_name Column name.
     *
     * @return string
     */
    protected function column_default( $item, $column_name ) : string { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        if ( isset( $item[ $column_name ] ) && ! is_array( $item[ $column_name ] ) ) {
            return esc_html( (string) $item[ $column_name ] );
        }

        return '&mdash;';
    }

    /**
     * Return bulk actions.
     *
     * @return array<string,string>
     */
    protected function get_bulk_actions() : array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        return [
            'mark_reviewed' => __( 'Marcar como revisado', 'woo-contifico' ),
        ];
    }

    /**
     * Process the bulk actions.
     */
    protected function process_bulk_action() : void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        if ( 'mark_reviewed' !== $this->current_action() ) {
            return;
        }

        $selected = isset( $_REQUEST['diagnosticos'] ) ? (array) $_REQUEST['diagnosticos'] : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $selected = array_map( 'intval', $selected );
        $selected = array_filter( $selected );
        $count    = count( $selected );

        if ( 0 === $count ) {
            add_settings_error(
                'woo_contifico_diagnostics',
                'woo_contifico_diagnostics_none',
                __( 'Selecciona al menos un elemento para marcarlo como revisado.', 'woo-contifico' )
            );

            return;
        }

        add_settings_error(
            'woo_contifico_diagnostics',
            'woo_contifico_diagnostics_marked',
            sprintf(
                _n(
                    'Se marcó %d elemento como revisado.',
                    'Se marcaron %d elementos como revisados.',
                    $count,
                    'woo-contifico'
                ),
                $count
            ),
            'updated'
        );
    }

    /**
     * Display extra controls such as filters.
     *
     * @param string $which Location (top/bottom).
     */
    protected function extra_tablenav( $which ) : void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        if ( 'top' !== $which ) {
            return;
        }

        $filters = $this->get_problem_filters();
        $current = $this->get_current_filter();

        echo '<div class="alignleft actions woo-contifico-diagnostics__filters">';
        echo '<label class="screen-reader-text" for="woo-contifico-diagnostics-filter">' . esc_html__( 'Filtrar por tipo de problema', 'woo-contifico' ) . '</label>';
        echo '<select name="diagnostic_filter" id="woo-contifico-diagnostics-filter">';

        foreach ( $filters as $value => $label ) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr( $value ),
                selected( $current, $value, false ),
                esc_html( $label )
            );
        }

        echo '</select>';
        submit_button( __( 'Filtrar', 'woo-contifico' ), 'button', 'woo-contifico-diagnostics-apply', false );
        echo '</div>';
    }

    /**
     * Table classes.
     *
     * @return array<int,string>
     */
    protected function get_table_classes() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        $classes   = parent::get_table_classes();
        $classes[] = 'woo-contifico-diagnostics-table';

        return array_unique( $classes );
    }

    /**
     * Output a custom row to include severity classes.
     *
     * @param array<string,mixed> $item Current row item.
     */
    public function single_row( $item ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        $classes   = [ 'woo-contifico-diagnostic-row' ];
        $severity  = isset( $item['severity'] ) ? (string) $item['severity'] : '';
        $problems  = isset( $item['problem_types'] ) && is_array( $item['problem_types'] ) ? $item['problem_types'] : [];

        if ( '' !== $severity ) {
            $classes[] = 'severity-' . sanitize_html_class( $severity );
        }

        if ( empty( $problems ) ) {
            $classes[] = 'problem-none';
        } else {
            foreach ( $problems as $problem ) {
                $classes[] = 'problem-' . sanitize_html_class( (string) $problem );
            }
        }

        printf(
            '<tr class="%1$s" data-problem-types="%2$s">',
            esc_attr( implode( ' ', array_unique( $classes ) ) ),
            esc_attr( implode( ',', array_map( 'sanitize_title', $problems ) ) )
        );
        $this->single_row_columns( $item );
        echo '</tr>';
    }

    /**
     * Retrieve the available filters.
     *
     * @return array<string,string>
     */
    private function get_problem_filters() : array {
        return [
            'all'               => __( 'Todos los problemas', 'woo-contifico' ),
            'synced'            => __( 'Sin incidencias (sincronizado)', 'woo-contifico' ),
            'needs_attention'   => __( 'Requieren revisión', 'woo-contifico' ),
            'missing_sku'       => __( 'Sin SKU', 'woo-contifico' ),
            'duplicate_sku'     => __( 'SKU duplicado', 'woo-contifico' ),
            'no_contifico_match' => __( 'Sin coincidencia en Contífico', 'woo-contifico' ),
            'child_no_contifico_match' => __( 'Variaciones sin coincidencia', 'woo-contifico' ),
            'variation_stock_disabled'  => __( 'Variación sin manejo de inventario', 'woo-contifico' ),
            'product_stock_disabled'    => __( 'Producto sin manejo de inventario', 'woo-contifico' ),
        ];
    }

    /**
     * Resolve the current filter.
     *
     * @return string
     */
    private function get_current_filter() : string {
        $filters = $this->get_problem_filters();
        $value   = isset( $_REQUEST['diagnostic_filter'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
            ? sanitize_key( wp_unslash( (string) $_REQUEST['diagnostic_filter'] ) )
            : 'all';

        if ( ! isset( $filters[ $value ] ) ) {
            return 'all';
        }

        return $value;
    }

    /**
     * Normalize a diagnostics entry.
     *
     * @param array<string,mixed> $entry Diagnostics entry.
     *
     * @return array<string,mixed>
     */
    private function normalize_entry( array $entry ) : array {
        $defaults = [
            'post_id'                => 0,
            'nombre'                 => '',
            'tipo'                   => '',
            'sku_detectado'          => '',
            'error_detectado'        => [],
            'codigo_contifico'       => '',
            'coincidencias_posibles' => [],
            'variaciones_sin_coincidencia' => [],
            'parent_id'              => 0,
            'managing_stock'         => null,
            'variation_count'        => 0,
            'is_parent_placeholder'  => false,
            'sync_status'            => '',
        ];

        $entry = wp_parse_args( $entry, $defaults );

        $entry['post_id']                = (int) $entry['post_id'];
        $entry['nombre']                 = (string) $entry['nombre'];
        $entry['tipo']                   = (string) $entry['tipo'];
        $entry['sku_detectado']          = (string) $entry['sku_detectado'];
        $entry['codigo_contifico']       = (string) $entry['codigo_contifico'];
        $entry['error_detectado']        = is_array( $entry['error_detectado'] ) ? array_values( $entry['error_detectado'] ) : [];
        $entry['coincidencias_posibles'] = is_array( $entry['coincidencias_posibles'] ) ? array_values( $entry['coincidencias_posibles'] ) : [];
        $entry['variaciones_sin_coincidencia'] = is_array( $entry['variaciones_sin_coincidencia'] ) ? array_values( $entry['variaciones_sin_coincidencia'] ) : [];
        $entry['parent_id']              = (int) $entry['parent_id'];
        $entry['managing_stock']         = is_null( $entry['managing_stock'] ) ? null : (bool) $entry['managing_stock'];
        $entry['variation_count']        = (int) $entry['variation_count'];
        $entry['is_parent_placeholder']  = (bool) $entry['is_parent_placeholder'];

        $entry['problem_types']       = $this->detect_problem_types( $entry );
        $entry['sync_status']         = $this->determine_sync_status( $entry );
        $entry['severity']            = $this->determine_severity( $entry['problem_types'] );
        $entry['problem_messages']    = $this->build_problem_messages( $entry );
        $entry['suggestion_messages'] = $this->build_suggestion_messages( $entry );

        return $entry;
    }

    /**
     * Determine the problem types for the entry.
     *
     * @param array<string,mixed> $entry Diagnostics entry.
     *
     * @return array<int,string>
     */
    private function detect_problem_types( array $entry ) : array {
        $types = [];

        foreach ( $entry['error_detectado'] as $code ) {
            $types[] = (string) $code;
        }

        $is_variable = ( 'variable' === $entry['tipo'] );

        if ( $is_variable && ! empty( $entry['variaciones_sin_coincidencia'] ) ) {
            $types[] = 'child_no_contifico_match';
        } elseif ( '' === $entry['codigo_contifico'] && ! $is_variable ) {
            $types[] = 'no_contifico_match';
        }

        if ( 'variation' === $entry['tipo'] && false === $entry['managing_stock'] ) {
            $types[] = 'variation_stock_disabled';
        }

        if (
            in_array( $entry['tipo'], [ 'simple', 'variable' ], true )
            && false === $entry['managing_stock']
            && ( 'simple' === $entry['tipo'] || $entry['variation_count'] <= 0 )
        ) {
            $types[] = 'product_stock_disabled';
        }

        $types = array_filter( array_map( 'sanitize_key', $types ) );

        return array_values( array_unique( $types ) );
    }

    /**
     * Determine the severity label for the entry.
     *
     * @param array<int,string> $types Problem types.
     *
     * @return string
     */
    private function determine_severity( array $types ) : string {
        if ( array_intersect( [ 'missing_sku', 'duplicate_sku' ], $types ) ) {
            return 'critical';
        }

        if ( array_intersect( [ 'no_contifico_match', 'child_no_contifico_match', 'variation_stock_disabled', 'product_stock_disabled' ], $types ) ) {
            return 'warning';
        }

        return 'ok';
    }

    /**
     * Determine the sync status for the entry.
     *
     * @param array<string,mixed> $entry Diagnostics entry.
     *
     * @return string
     */
    private function determine_sync_status( array $entry ) : string {
        $codigo_contifico = isset( $entry['codigo_contifico'] ) ? (string) $entry['codigo_contifico'] : '';
        $problemas        = isset( $entry['problem_types'] ) && is_array( $entry['problem_types'] )
            ? $entry['problem_types']
            : [];

        if ( '' === $codigo_contifico ) {
            if ( ! empty( $entry['is_parent_placeholder'] ) ) {
                return 'parent_placeholder';
            }

            return 'unmatched';
        }

        if ( empty( $problemas ) ) {
            return 'synced';
        }

        return 'needs_attention';
    }

    /**
     * Build problem messages for the entry.
     *
     * @param array<string,mixed> $entry Diagnostics entry.
     *
     * @return array<int,string>
     */
    private function build_problem_messages( array $entry ) : array {
        $messages = [];
        $types    = isset( $entry['problem_types'] ) ? (array) $entry['problem_types'] : [];

        foreach ( $types as $type ) {
            switch ( $type ) {
                case 'missing_sku':
                    $messages[] = __( 'El producto no tiene un SKU definido.', 'woo-contifico' );
                    break;
                case 'duplicate_sku':
                    $messages[] = __( 'El SKU detectado está duplicado en WooCommerce.', 'woo-contifico' );
                    break;
                case 'no_contifico_match':
                    if ( ! empty( $entry['coincidencias_posibles'] ) ) {
                        $messages[] = sprintf(
                            '%s %s',
                            __( 'Sin coincidencia exacta en Contífico.', 'woo-contifico' ),
                            sprintf(
                                /* translators: %s: comma separated list of possible matches. */
                                __( 'Coincidencias sugeridas: %s.', 'woo-contifico' ),
                                esc_html( implode( ', ', array_slice( (array) $entry['coincidencias_posibles'], 0, 3 ) ) )
                            )
                        );
                    } else {
                        $messages[] = __( 'No se encontraron coincidencias en Contífico para el SKU actual.', 'woo-contifico' );
                    }
                    break;
                case 'child_no_contifico_match':
                    $labels = isset( $entry['variaciones_sin_coincidencia'] ) ? (array) $entry['variaciones_sin_coincidencia'] : [];
                    $labels = array_filter( array_map( 'trim', $labels ) );

                    if ( empty( $labels ) ) {
                        $messages[] = __( 'Algunas variaciones no tienen coincidencia en Contífico.', 'woo-contifico' );
                        break;
                    }

                    $limit        = 5;
                    $display      = array_slice( $labels, 0, $limit );
                    $display      = array_map( 'esc_html', $display );
                    $hidden_count = count( $labels ) - count( $display );

                    if ( $hidden_count > 0 ) {
                        $display[] = sprintf(
                            /* translators: %d: number of additional variations. */
                            _n( 'y %d más', 'y %d más', $hidden_count, 'woo-contifico' ),
                            $hidden_count
                        );
                    }

                    $messages[] = sprintf(
                        /* translators: %s: comma-separated list of variation names. */
                        __( 'Variaciones sin coincidencia en Contífico: %s.', 'woo-contifico' ),
                        implode( ', ', $display )
                    );
                    break;
                case 'variation_stock_disabled':
                    $messages[] = __( 'La variación no tiene habilitado el manejo de inventario, por lo que no se sincronizará el stock.', 'woo-contifico' );
                    break;
                case 'product_stock_disabled':
                    $messages[] = __( 'El producto no tiene habilitado el manejo de inventario, por lo que no se sincronizará el stock.', 'woo-contifico' );
                    break;
            }
        }

        return array_values( array_filter( $messages ) );
    }

    /**
     * Build suggestion messages for the entry.
     *
     * @param array<string,mixed> $entry Diagnostics entry.
     *
     * @return array<int,string>
     */
    private function build_suggestion_messages( array $entry ) : array {
        $messages = [];
        $types    = isset( $entry['problem_types'] ) ? (array) $entry['problem_types'] : [];

        foreach ( $types as $type ) {
            switch ( $type ) {
                case 'missing_sku':
                    $messages[] = __( 'Asigna un SKU único al producto y guarda los cambios.', 'woo-contifico' );
                    break;
                case 'duplicate_sku':
                    $messages[] = __( 'Actualiza el SKU para que sea único y sincroniza nuevamente con Contífico.', 'woo-contifico' );
                    break;
                case 'no_contifico_match':
                    if ( ! empty( $entry['coincidencias_posibles'] ) ) {
                        $messages[] = __( 'Verifica las coincidencias sugeridas y ajusta el SKU en WooCommerce si corresponde.', 'woo-contifico' );
                    } else {
                        $messages[] = __( 'Verifica que el producto exista en Contífico o crea uno con el mismo SKU.', 'woo-contifico' );
                    }
                    break;
                case 'child_no_contifico_match':
                    $messages[] = __( 'Revisa las variaciones listadas y sincroniza cada SKU con un producto en Contífico.', 'woo-contifico' );
                    break;
                case 'variation_stock_disabled':
                    $messages[] = __( 'Activa la opción "Gestionar inventario" en la variación y guarda los cambios antes de sincronizar.', 'woo-contifico' );
                    break;
                case 'product_stock_disabled':
                    $messages[] = __( 'Activa la opción "Gestionar inventario" en el producto y guarda los cambios antes de sincronizar.', 'woo-contifico' );
                    break;
            }
        }

        if ( ! empty( $entry['is_parent_placeholder'] ) ) {
            $messages[] = __( 'Este producto madre usa únicamente los SKU de sus variaciones en Contífico.', 'woo-contifico' );
        }

        return array_values( array_filter( $messages ) );
    }
}
