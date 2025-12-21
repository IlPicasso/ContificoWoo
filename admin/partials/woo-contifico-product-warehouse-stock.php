<?php
/**
 * Admin meta box content for product warehouse stock.
 *
 * @since 4.2.12
 *
 * @var string $contifico_id
 * @var array  $warehouse_rows
 * @var array  $stock_groups
 * @var string $empty_message
 * @var string $stock_title
 * @var string $contifico_label
 * @var string $variation_title
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}
?>
<div class="woo-contifico-product-warehouse-stock">
        <p>
                <strong><?php echo esc_html( $contifico_label ); ?></strong>
                <?php if ( '' !== $contifico_id ) : ?>
                        <span><?php echo esc_html( $contifico_id ); ?></span>
                <?php else : ?>
                        <span><?php echo esc_html__( 'Sin ID', 'woo-contifico' ); ?></span>
                <?php endif; ?>
        </p>

        <?php if ( '' !== $empty_message ) : ?>
                <p class="description"><?php echo esc_html( $empty_message ); ?></p>
        <?php elseif ( ! empty( $stock_groups ) ) : ?>
                <p><strong><?php echo esc_html( $stock_title ); ?></strong></p>
                <?php foreach ( $stock_groups as $group ) : ?>
                        <p>
                                <strong><?php echo esc_html( $variation_title ); ?>:</strong>
                                <?php echo esc_html( (string) $group['label'] ); ?>
                        </p>
                        <table class="widefat striped">
                                <thead>
                                <tr>
                                        <th><?php echo esc_html__( 'Bodega', 'woo-contifico' ); ?></th>
                                        <th><?php echo esc_html__( 'Cantidad', 'woo-contifico' ); ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ( $group['rows'] as $row ) : ?>
                                        <tr>
                                                <td><?php echo esc_html( (string) $row['label'] ); ?></td>
                                                <td><?php echo esc_html( number_format_i18n( (float) $row['quantity'] ) ); ?></td>
                                        </tr>
                                <?php endforeach; ?>
                                </tbody>
                        </table>
                <?php endforeach; ?>
        <?php else : ?>
                <p><strong><?php echo esc_html( $stock_title ); ?></strong></p>
                <table class="widefat striped">
                        <thead>
                        <tr>
                                <th><?php echo esc_html__( 'Bodega', 'woo-contifico' ); ?></th>
                                <th><?php echo esc_html__( 'Cantidad', 'woo-contifico' ); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $warehouse_rows as $row ) : ?>
                                <tr>
                                        <td><?php echo esc_html( (string) $row['label'] ); ?></td>
                                        <td><?php echo esc_html( number_format_i18n( (float) $row['quantity'] ) ); ?></td>
                                </tr>
                        <?php endforeach; ?>
                        </tbody>
                </table>
        <?php endif; ?>
</div>
