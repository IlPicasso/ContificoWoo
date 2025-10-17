<?php $is_active = method_exists( $this, 'is_active' ) ? $this->is_active() : true; ?>
<div id="<?php echo $this->plugin_name ?>-settings-page" class="wrap <?php echo $is_active ? 'active' : 'inactive' ?>">
    <h2 class="wp-heading-inline"><?php _e('Facturación Electrónica Contífico',$this->plugin_name) ?></h2>

    <?php settings_errors('woo_contifico_settings'); ?>

    <?php
    $active_tab    = $_GET['tab'] ?? 'woocommerce';
    $settings_tabs = [ 'woocommerce', 'contifico', 'emisor', 'establecimiento' ];
    ?>

    <h2 class="nav-tab-wrapper">
        <a href="?page=woo-contifico&tab=woocommerce" class="nav-tab <?php echo $active_tab == 'woocommerce' ? 'nav-tab-active' : ''; ?>"><?php _e('WooCommerce', $this->plugin_name) ?></a>
        <a href="?page=woo-contifico&tab=contifico" class="nav-tab <?php echo $active_tab == 'contifico' ? 'nav-tab-active' : ''; ?>"><?php _e('Contífico', $this->plugin_name) ?></a>
        <a href="?page=woo-contifico&tab=emisor" class="nav-tab <?php echo $active_tab == 'emisor' ? 'nav-tab-active' : ''; ?>"><?php _e('Emisor', $this->plugin_name) ?></a>
        <a href="?page=woo-contifico&tab=establecimiento" class="nav-tab <?php echo $active_tab == 'establecimiento' ? 'nav-tab-active' : ''; ?>"><?php _e('Establecimiento', $this->plugin_name) ?></a>
        <?php if ( $this->config_status['status'] && $is_active ) : ?>
        <a href="?page=woo-contifico&tab=diagnostico" class="nav-tab <?php echo $active_tab == 'diagnostico' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Diagnóstico de ítems', $this->plugin_name ); ?></a>
        <a href="?page=woo-contifico&tab=sincronizar" class="nav-tab <?php echo $active_tab == 'sincronizar' ? 'nav-tab-active' : ''; ?>"><?php _e('Sincronización manual', $this->plugin_name) ?></a>
        <a href="?page=woo-contifico&tab=historial" class="nav-tab <?php echo $active_tab == 'historial' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Historial de sincronizaciones', $this->plugin_name ); ?></a>
        <?php endif; ?>
    </h2>

    <div class="container">
        <?php if ( in_array( $active_tab, $settings_tabs, true ) ) : ?>
            <!--suppress HtmlUnknownTarget -->
            <form action="options.php"  method="post">
                <?php

                    # Button text
                    $guardar = __( 'Guardar opciones', $this->plugin_name );

                    if( $active_tab === 'woocommerce' ) {
                        settings_fields( 'woo_contifico_woocommerce_settings' );
                        do_settings_sections( 'woo_contifico_woocommerce_settings' );

                    $activar_registro = ! empty( $this->woo_contifico->settings['activar_registro'] );

                    if( $activar_registro && file_exists($this->log_path)) {
                                    /** @noinspection HtmlUnknownTarget */
                                    printf( __('<p><a class="button button-small button-secondary" target="_blank" href="%s">%s</a></p>', $this->plugin_name), $this->log_route, 'Descargar registro de llamadas al API');
                            }

                    }
                    elseif( $active_tab === 'contifico' ) {
                        settings_fields('woo_contifico_integration_settings');
                        do_settings_sections('woo_contifico_integration_settings');
                    }
                    elseif( $active_tab === 'emisor' ) {
                        settings_fields('woo_contifico_sender_settings');
                        do_settings_sections('woo_contifico_sender_settings');
                    }
                    elseif( $active_tab === 'establecimiento' ) {
                        settings_fields('woo_contifico_pos_settings');
                        do_settings_sections('woo_contifico_pos_settings');
                    }

                    submit_button( $guardar );
                 ?>
            </form>
        <?php endif; ?>

        <?php if( $this->config_status['status'] && $is_active && $active_tab === 'sincronizar' ): ?>
            <?php $manual_state = $this->get_manual_sync_state(); ?>
            <?php $manual_progress = isset( $manual_state['progress'] ) && is_array( $manual_state['progress'] ) ? $manual_state['progress'] : []; ?>
            <?php $manual_updates = isset( $manual_progress['updates'] ) && is_array( $manual_progress['updates'] ) ? $manual_progress['updates'] : []; ?>
            <div class="fetch-products manual-sync-section"
                 data-start-action="woo_contifico_start_manual_sync"
                 data-status-action="woo_contifico_get_manual_sync_status"
                 data-cancel-action="woo_contifico_cancel_manual_sync"
                 data-history-url="<?php echo esc_url( add_query_arg( [ 'page' => 'woo-contifico', 'tab' => 'historial' ], admin_url( 'admin.php' ) ) ); ?>"
                 data-initial-state="<?php echo esc_attr( wp_json_encode( $manual_state ) ); ?>">
                <h3><?php _e('Sincronizar manualmente desde Contífico', $this->plugin_name); ?></h3>
                <p><?php esc_html_e( 'El plugin actualiza periódicamente el inventario de productos desde Contífico.', 'woo-contifico' ); ?></p>
                <p><?php printf( wp_kses_post( __( 'La frecuencia de actualización se determina por el campo <strong>Frecuencia</strong> en la <a href="%s">pestaña de integración</a>.', 'woo-contifico' ) ), esc_url( add_query_arg( [ 'page' => 'woo-contifico', 'tab' => 'contifico' ], admin_url( 'admin.php' ) ) ) ); ?></p>
                <p><?php esc_html_e( 'Inicia una sincronización manual cuando necesites actualizar tu inventario al instante. Puedes salir de esta página y volver más tarde para revisar el progreso.', 'woo-contifico' ); ?></p>
                <div class="manual-sync-actions">
                    <button type="button" class="button button-primary manual-sync-start"><?php esc_html_e( 'Iniciar sincronización manual', 'woo-contifico' ); ?></button>
                    <button type="button" class="button manual-sync-cancel"><?php esc_html_e( 'Cancelar sincronización', 'woo-contifico' ); ?></button>
                    <span class="spinner manual-sync-spinner"></span>
                </div>
                <p class="manual-sync-status-message" aria-live="polite"></p>
                <div class="result" <?php echo empty( $manual_progress['fetched'] ) && empty( $manual_progress['found'] ) && empty( $manual_progress['updated'] ) && empty( $manual_progress['outofstock'] ) ? 'hidden' : ''; ?>>
                    <ul>
                        <li><?php _e('Productos obtenidos desde Contífico: ', $this->plugin_name) ?><span class="fetched"><?php echo esc_html( number_format_i18n( isset( $manual_progress['fetched'] ) ? (int) $manual_progress['fetched'] : 0 ) ); ?></span></li>
                        <li><?php _e('Productos encontrados en WooCommerce: ', $this->plugin_name) ?><span class="found"><?php echo esc_html( number_format_i18n( isset( $manual_progress['found'] ) ? (int) $manual_progress['found'] : 0 ) ); ?></span></li>
                        <li><?php _e('Productos con inventario actualizado: ', $this->plugin_name) ?><span class="updated"><?php echo esc_html( number_format_i18n( isset( $manual_progress['updated'] ) ? (int) $manual_progress['updated'] : 0 ) ); ?></span></li>
                        <li><?php _e('Productos sin inventario: ', $this->plugin_name) ?><span class="outofstock"><?php echo esc_html( number_format_i18n( isset( $manual_progress['outofstock'] ) ? (int) $manual_progress['outofstock'] : 0 ) ); ?></span></li>
                    </ul>
                    <div class="sync-summary" <?php echo empty( $manual_updates ) ? 'hidden' : ''; ?> aria-live="polite">
                        <h4 class="sync-summary-heading"><?php esc_html_e( 'Resumen de actualizaciones', 'woo-contifico' ); ?></h4>
                        <p class="sync-summary-empty" <?php echo empty( $manual_updates ) ? '' : 'hidden'; ?>><?php esc_html_e( 'No se registraron cambios durante la sincronización.', 'woo-contifico' ); ?></p>
                        <ul class="sync-summary-list">
                            <?php foreach ( $manual_updates as $update ) :
                                $description = $this->describe_manual_sync_update_entry( $update );
                                ?>
                                <li class="sync-summary-item">
                                    <span class="sync-summary-title"><?php echo esc_html( $description['title'] ); ?></span>
                                    <?php if ( ! empty( $description['meta'] ) ) : ?>
                                        <ul class="sync-summary-meta">
                                            <?php foreach ( $description['meta'] as $meta ) : ?>
                                                <li><span class="label"><?php echo esc_html( $meta['label'] ); ?></span> <span class="value"><?php echo esc_html( $meta['value'] ); ?></span></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $description['changes'] ) ) : ?>
                                        <ul class="sync-summary-change-list">
                                            <?php foreach ( $description['changes'] as $change ) : ?>
                                                <li>
                                                    <span class="label"><?php echo esc_html( $change['label'] ); ?></span>
                                                    <span class="value"><?php echo esc_html( $change['value'] ); ?></span>
                                                    <?php if ( ! empty( $change['notes'] ) ) : ?>
                                                        <span class="notes"><?php echo esc_html( $change['notes'] ); ?></span>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ( $this->config_status['status'] && $is_active && $active_tab === 'historial' ) : ?>
            <?php $history_entries = $this->get_manual_sync_history(); ?>
            <?php $status_labels = [
                'idle'       => __( 'Sin ejecutar', 'woo-contifico' ),
                'queued'     => __( 'Programada', 'woo-contifico' ),
                'running'    => __( 'En progreso', 'woo-contifico' ),
                'cancelling' => __( 'Cancelando', 'woo-contifico' ),
                'cancelled'  => __( 'Cancelada', 'woo-contifico' ),
                'completed'  => __( 'Completada', 'woo-contifico' ),
                'failed'     => __( 'Fallida', 'woo-contifico' ),
            ]; ?>
            <div class="manual-sync-history">
                <h3><?php esc_html_e( 'Historial de sincronizaciones manuales', 'woo-contifico' ); ?></h3>
                <?php if ( empty( $history_entries ) ) : ?>
                    <p><?php esc_html_e( 'Aún no se registran sincronizaciones manuales.', 'woo-contifico' ); ?></p>
                <?php else : ?>
                    <table class="widefat fixed striped manual-sync-history-table">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e( 'Inicio', 'woo-contifico' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Finalización', 'woo-contifico' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Estado', 'woo-contifico' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Resultados', 'woo-contifico' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Reporte', 'woo-contifico' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $history_entries as $entry ) :
                                $status         = isset( $entry['status'] ) ? (string) $entry['status'] : 'completed';
                                $status_label   = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status;
                                $results        = [
                                    __( 'Productos obtenidos', 'woo-contifico' )   => isset( $entry['fetched'] ) ? (int) $entry['fetched'] : 0,
                                    __( 'Productos encontrados', 'woo-contifico' ) => isset( $entry['found'] ) ? (int) $entry['found'] : 0,
                                    __( 'Productos actualizados', 'woo-contifico' ) => isset( $entry['updated'] ) ? (int) $entry['updated'] : 0,
                                    __( 'Sin inventario', 'woo-contifico' )        => isset( $entry['outofstock'] ) ? (int) $entry['outofstock'] : 0,
                                ];
                                $updates        = isset( $entry['updates'] ) && is_array( $entry['updates'] ) ? $entry['updates'] : [];
                                $debug_log      = isset( $entry['debug_log'] ) ? (string) $entry['debug_log'] : '';
                                $history_message = isset( $entry['message'] ) ? (string) $entry['message'] : '';
                                ?>
                                <tr>
                                    <td><?php echo esc_html( $entry['started_at'] ? $entry['started_at'] : '—' ); ?></td>
                                    <td><?php echo esc_html( $entry['finished_at'] ? $entry['finished_at'] : '—' ); ?></td>
                                    <td><span class="manual-sync-status-label manual-sync-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
                                    <td>
                                        <?php if ( $history_message ) : ?>
                                            <p class="manual-sync-history-message"><?php echo esc_html( $history_message ); ?></p>
                                        <?php endif; ?>
                                        <ul class="manual-sync-history-results">
                                            <?php foreach ( $results as $label => $value ) : ?>
                                                <li><span class="label"><?php echo esc_html( $label ); ?></span> <span class="value"><?php echo esc_html( number_format_i18n( $value ) ); ?></span></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </td>
                                    <td>
                                        <?php if ( empty( $updates ) ) : ?>
                                            <p><?php esc_html_e( 'Sin cambios registrados.', 'woo-contifico' ); ?></p>
                                        <?php else : ?>
                                            <details>
                                                <summary><?php esc_html_e( 'Ver detalles de la sincronización', 'woo-contifico' ); ?></summary>
                                                <ul class="manual-sync-history-updates">
                                                    <?php foreach ( $updates as $update ) :
                                                        $description = $this->describe_manual_sync_update_entry( $update );
                                                        ?>
                                                        <li>
                                                            <strong><?php echo esc_html( $description['title'] ); ?></strong>
                                                            <?php if ( ! empty( $description['meta'] ) ) : ?>
                                                                <ul class="manual-sync-history-meta">
                                                                    <?php foreach ( $description['meta'] as $meta ) : ?>
                                                                        <li><span class="label"><?php echo esc_html( $meta['label'] ); ?>:</span> <span class="value"><?php echo esc_html( $meta['value'] ); ?></span></li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            <?php endif; ?>
                                                            <?php if ( ! empty( $description['changes'] ) ) : ?>
                                                                <ul class="manual-sync-history-changes">
                                                                    <?php foreach ( $description['changes'] as $change ) : ?>
                                                                        <li>
                                                                            <span class="label"><?php echo esc_html( $change['label'] ); ?>:</span>
                                                                            <span class="value"><?php echo esc_html( $change['value'] ); ?></span>
                                                                            <?php if ( ! empty( $change['notes'] ) ) : ?>
                                                                                <span class="notes"><?php echo esc_html( $change['notes'] ); ?></span>
                                                                            <?php endif; ?>
                                                                        </li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </details>
                                        <?php endif; ?>
                                        <?php if ( $debug_log ) : ?>
                                            <p><a class="button-link" href="<?php echo esc_url( $debug_log ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Descargar registro técnico', 'woo-contifico' ); ?></a></p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ( $this->config_status['status'] && $is_active && $active_tab === 'diagnostico' ) : ?>
            <div class="woo-contifico-diagnostics">
                <?php
                settings_errors( 'woo_contifico_diagnostics' );

                $diagnostics_data = get_transient( 'woo_contifico_diagnostics' );

                if ( false === $diagnostics_data ) {
                    $diagnostics_helper = new Woo_Contifico_Diagnostics( $this->contifico );
                    $diagnostics_data   = $diagnostics_helper->build_diagnostics();
                }

                if ( ! class_exists( 'Woo_Contifico_Diagnostics_Table' ) ) {
                    require_once plugin_dir_path( __DIR__ ) . 'class-woo-contifico-diagnostics-table.php';
                }

                $table = new Woo_Contifico_Diagnostics_Table( is_array( $diagnostics_data ) ? $diagnostics_data : [] );

                $table->prepare_items();
                ?>
                <form method="get">
                    <input type="hidden" name="page" value="woo-contifico" />
                    <input type="hidden" name="tab" value="diagnostico" />
                    <?php $table->display(); ?>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
