<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( ! class_exists( 'Woo_Contifico_Inventory_Movements_Table' ) ) {
class Woo_Contifico_Inventory_Movements_Table extends WP_List_Table {

/**
 * Raw rows to render in the list table.
 *
 * @var array[]
 */
protected $prepared_items = [];

                public function __construct() {
                        parent::__construct( [
                                'plural'   => 'inventory-movements',
                                'singular' => 'inventory-movement',
                                'ajax'     => false,
                        ] );
                }

public function set_table_items( array $items ) {
$this->prepared_items = $items;
}

public function prepare_items() {
$columns               = $this->get_columns();
$hidden                = [];
$sortable              = [];
$this->_column_headers = [ $columns, $hidden, $sortable ];
$this->items           = $this->prepared_items;
}

                public function get_columns() : array {
                        return [
                                'product_name'  => __( 'Producto', 'woo-contifico' ),
                                'sku'           => __( 'SKU', 'woo-contifico' ),
                                'ingresos'      => __( 'Ingresos', 'woo-contifico' ),
                                'egresos'       => __( 'Egresos', 'woo-contifico' ),
                                'balance'       => __( 'Balance', 'woo-contifico' ),
                                'last_movement' => __( 'Último movimiento', 'woo-contifico' ),
                        ];
                }

                public function no_items() {
                        esc_html_e( 'No se encontraron movimientos con los filtros seleccionados.', 'woo-contifico' );
                }

                protected function column_default( $item, $column_name ) {
                        switch ( $column_name ) {
                                case 'product_name':
                                        return esc_html( $item['product_name'] ?? __( 'Sin nombre', 'woo-contifico' ) );
                                case 'sku':
                                        return esc_html( $item['sku'] ?? '' );
                                case 'ingresos':
                                case 'egresos':
                                case 'balance':
                                        return esc_html( number_format_i18n( (float) ( $item[ $column_name ] ?? 0 ) ) );
                                case 'last_movement':
                                        $timestamp = isset( $item['last_movement'] ) ? (int) $item['last_movement'] : 0;
                                        if ( ! $timestamp ) {
                                                return '—';
                                        }
                                        return esc_html( wp_date( get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i' ), $timestamp ) );
                                default:
                                        return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
                        }
                }
        }
}

$raw_filters = [
        'start_date' => isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : '',
        'end_date'   => isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : '',
        'product_id' => isset( $_GET['product_id'] ) ? absint( wp_unslash( $_GET['product_id'] ) ) : 0,
        'sku'        => isset( $_GET['sku'] ) ? sanitize_text_field( wp_unslash( $_GET['sku'] ) ) : '',
        'period'     => isset( $_GET['period'] ) ? sanitize_key( wp_unslash( $_GET['period'] ) ) : 'day',
        'scope'      => isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : 'all',
];

$report           = $this->get_inventory_movements_report( $raw_filters );
$filters          = $report['filters'];
$product_choices  = $this->get_inventory_movement_product_choices();
$table            = new Woo_Contifico_Inventory_Movements_Table();
$table->set_table_items( $report['totals_by_product'] );
$table->prepare_items();
$summary_totals   = $report['totals'];
$chart_attributes = [
        'periods'  => $report['chart_data']['periods'],
        'products' => $report['chart_data']['products'],
];
$chart_data_attr = esc_attr( wp_json_encode( $chart_attributes ) );
$nonce           = wp_create_nonce( 'woo_contifico_export_inventory_movements' );
$export_base     = [
        'action'     => 'woo_contifico_export_inventory_movements',
        'nonce'      => $nonce,
        'start_date' => $filters['start_date'],
        'end_date'   => $filters['end_date'],
        'product_id' => $filters['product_id'],
        'sku'        => $filters['sku'],
        'period'     => $filters['period'],
        'scope'      => $filters['scope'],
];
$csv_url  = esc_url( add_query_arg( array_merge( $export_base, [ 'format' => 'csv' ] ), admin_url( 'admin-post.php' ) ) );
$json_url = esc_url( add_query_arg( array_merge( $export_base, [ 'format' => 'json' ] ), admin_url( 'admin-post.php' ) ) );
?>
<div class="woo-contifico-inventory-report">
        <h3><?php esc_html_e( 'Reporte de movimientos de inventario', 'woo-contifico' ); ?></h3>
        <p><?php esc_html_e( 'Combina los reportes de sincronización global y por producto para detectar incidencias entre bodegas.', 'woo-contifico' ); ?></p>
        <form method="get" class="woo-contifico-inventory-report__filters">
                <input type="hidden" name="page" value="woo-contifico" />
                <input type="hidden" name="tab" value="movimientos" />
                <div class="woo-contifico-inventory-report__filters-row">
                        <label>
                                <?php esc_html_e( 'Fecha inicial', 'woo-contifico' ); ?>
                                <input type="date" name="start_date" value="<?php echo esc_attr( $filters['start_date'] ); ?>" />
                        </label>
                        <label>
                                <?php esc_html_e( 'Fecha final', 'woo-contifico' ); ?>
                                <input type="date" name="end_date" value="<?php echo esc_attr( $filters['end_date'] ); ?>" />
                        </label>
                        <label>
                                <?php esc_html_e( 'Periodo', 'woo-contifico' ); ?>
                                <select name="period">
                                        <option value="day" <?php selected( $filters['period'], 'day' ); ?>><?php esc_html_e( 'Día', 'woo-contifico' ); ?></option>
                                        <option value="week" <?php selected( $filters['period'], 'week' ); ?>><?php esc_html_e( 'Semana', 'woo-contifico' ); ?></option>
                                        <option value="month" <?php selected( $filters['period'], 'month' ); ?>><?php esc_html_e( 'Mes', 'woo-contifico' ); ?></option>
                                </select>
                        </label>
                        <label>
                                <?php esc_html_e( 'Cobertura del reporte', 'woo-contifico' ); ?>
                                <select name="scope">
                                        <option value="all" <?php selected( $filters['scope'], 'all' ); ?>><?php esc_html_e( 'Global y por producto', 'woo-contifico' ); ?></option>
                                        <option value="global" <?php selected( $filters['scope'], 'global' ); ?>><?php esc_html_e( 'Solo sincronización global', 'woo-contifico' ); ?></option>
                                        <option value="product" <?php selected( $filters['scope'], 'product' ); ?>><?php esc_html_e( 'Solo sincronización por producto', 'woo-contifico' ); ?></option>
                                </select>
                        </label>
                </div>
                <div class="woo-contifico-inventory-report__filters-row">
                        <label>
                                <?php esc_html_e( 'Producto', 'woo-contifico' ); ?>
                                <select name="product_id">
                                        <option value="0"><?php esc_html_e( 'Todos los productos', 'woo-contifico' ); ?></option>
                                        <?php foreach ( $product_choices as $choice ) : ?>
                                                <option value="<?php echo esc_attr( $choice['id'] ); ?>" <?php selected( $filters['product_id'], $choice['id'] ); ?>><?php echo esc_html( $choice['label'] ); ?></option>
                                        <?php endforeach; ?>
                                </select>
                        </label>
                        <label>
                                <?php esc_html_e( 'SKU', 'woo-contifico' ); ?>
                                <input type="text" name="sku" value="<?php echo esc_attr( $filters['sku'] ); ?>" placeholder="ABC-001" />
                        </label>
                        <div class="woo-contifico-inventory-report__filters-actions">
                                <button type="submit" class="button button-primary"><?php esc_html_e( 'Aplicar filtros', 'woo-contifico' ); ?></button>
                                <a class="button" href="<?php echo esc_url( add_query_arg( [ 'page' => 'woo-contifico', 'tab' => 'movimientos' ], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Limpiar', 'woo-contifico' ); ?></a>
                        </div>
                </div>
        </form>

        <div class="woo-contifico-inventory-report__summary" data-chart="<?php echo $chart_data_attr; ?>">
                <div class="woo-contifico-inventory-report__summary-card">
                        <span><?php esc_html_e( 'Ingresos', 'woo-contifico' ); ?></span>
                        <strong><?php echo esc_html( number_format_i18n( (float) $summary_totals['ingresos'] ) ); ?></strong>
                </div>
                <div class="woo-contifico-inventory-report__summary-card">
                        <span><?php esc_html_e( 'Egresos', 'woo-contifico' ); ?></span>
                        <strong><?php echo esc_html( number_format_i18n( (float) $summary_totals['egresos'] ) ); ?></strong>
                </div>
                <div class="woo-contifico-inventory-report__summary-card">
                        <span><?php esc_html_e( 'Balance', 'woo-contifico' ); ?></span>
                        <strong><?php echo esc_html( number_format_i18n( (float) $summary_totals['balance'] ) ); ?></strong>
                </div>
                <div class="woo-contifico-inventory-report__summary-export">
                        <a href="<?php echo $csv_url; ?>" class="button button-secondary"><?php esc_html_e( 'Descargar CSV', 'woo-contifico' ); ?></a>
                        <a href="<?php echo $json_url; ?>" class="button button-secondary"><?php esc_html_e( 'Descargar JSON', 'woo-contifico' ); ?></a>
                </div>
        </div>

        <div class="woo-contifico-inventory-report__table">
                <?php $table->display(); ?>
        </div>

        <details class="woo-contifico-inventory-report__details" <?php echo empty( $report['entries'] ) ? 'open' : ''; ?>>
                <summary><?php esc_html_e( 'Detalle crudo de movimientos', 'woo-contifico' ); ?></summary>
                <?php if ( empty( $report['entries'] ) ) : ?>
                        <p><?php esc_html_e( 'No existen registros para mostrar.', 'woo-contifico' ); ?></p>
                <?php else : ?>
                        <table class="widefat striped">
                                <thead>
                                        <tr>
                                                <th><?php esc_html_e( 'Fecha', 'woo-contifico' ); ?></th>
                                                <th><?php esc_html_e( 'Orden', 'woo-contifico' ); ?></th>
                                                <th><?php esc_html_e( 'Evento', 'woo-contifico' ); ?></th>
                                                <th><?php esc_html_e( 'Producto / SKU', 'woo-contifico' ); ?></th>
                                                <th><?php esc_html_e( 'Cantidad', 'woo-contifico' ); ?></th>
                                                <th><?php esc_html_e( 'Bodegas', 'woo-contifico' ); ?></th>
                                                <th><?php esc_html_e( 'Estado', 'woo-contifico' ); ?></th>
                                        </tr>
                                </thead>
                                <tbody>
                                        <?php foreach ( $report['entries'] as $entry ) : ?>
                                                <tr>
                                                        <td><?php echo esc_html( wp_date( get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i' ), (int) $entry['timestamp'] ) ); ?></td>
                                                        <td><?php echo esc_html( $entry['order_id'] ?? '—' ); ?></td>
                                                        <td><?php echo esc_html( ucfirst( $entry['event_type'] ?? '' ) ); ?></td>
                                                        <td>
                                                                <strong><?php echo esc_html( $entry['product_name'] ?? '' ); ?></strong><br />
                                                                <small><?php echo esc_html( $entry['sku'] ?? '' ); ?></small>
                                                        </td>
                                                        <td><?php echo esc_html( number_format_i18n( (float) ( $entry['quantity'] ?? 0 ) ) ); ?></td>
                                                        <td>
                                                                <small><?php echo esc_html( sprintf( '%s → %s', $entry['warehouses']['from']['label'] ?? '', $entry['warehouses']['to']['label'] ?? '' ) ); ?></small>
                                                        </td>
                                                        <td>
                                                                <span class="status-<?php echo esc_attr( $entry['status'] ?? 'pending' ); ?>"><?php echo esc_html( $entry['status'] ?? 'pending' ); ?></span>
                                                                <?php if ( ! empty( $entry['error_message'] ) ) : ?>
                                                                        <br /><small><?php echo esc_html( $entry['error_message'] ); ?></small>
                                                                <?php endif; ?>
                                                        </td>
                                                </tr>
                                        <?php endforeach; ?>
                                </tbody>
                        </table>
                <?php endif; ?>
        </details>
</div>
