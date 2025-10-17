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
        <?php endif; ?>
            <?php if( $this->config_status['status'] && $is_active ): ?>
        <a href="?page=woo-contifico&tab=sincronizar" class="nav-tab <?php echo $active_tab == 'sincronizar' ? 'nav-tab-active' : ''; ?>"><?php _e('Sincronización manual', $this->plugin_name) ?></a>
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
            <div class="fetch-products">
                <h3><?php _e('Sincronizar manualmente desde Contífico', $this->plugin_name); ?></h3>
                <?php _e('<p>El plugin actualiza periódicamente el inventario de productos desde Contífico.</p>', $this->plugin_name); ?>
                    <?php _e('<p>La frecuencia de actualización se determina por el campo <strong>Frecuencia</strong> en la <a href="?page=woo-contifico&tab=contifico">pestaña de integración</a>.</p>', $this->plugin_name); ?>
                <button class="button"><?php _e('Sincronizar inventario manualmente', $this->plugin_name) ?></button>
                <img alt="loading" src="<?php echo esc_url( includes_url() . 'js/thickbox/loadingAnimation.gif' ); ?>" />
                <div class="result">
                    <ul>
                        <li><?php _e('Productos obtenidos desde Contífico: ', $this->plugin_name) ?><span class="fetched"></span></li>
                        <li><?php _e('Productos encontrados en WooCommerce: ', $this->plugin_name) ?><span class="found"></span></li>
                        <li><?php _e('Productos con inventario actualizado: ', $this->plugin_name) ?><span class="updated"></span></li>
                        <li><?php _e('Productos sin inventario: ', $this->plugin_name) ?><span class="outofstock"></span></li>
                    </ul>
                    <div class="sync-summary" hidden aria-live="polite">
                        <h4 class="sync-summary-heading"><?php esc_html_e( 'Resumen de actualizaciones', 'woo-contifico' ); ?></h4>
                        <p class="sync-summary-empty"><?php esc_html_e( 'No se registraron cambios durante la sincronización.', 'woo-contifico' ); ?></p>
                        <ul class="sync-summary-list"></ul>
                    </div>
                </div>
                <div class="fetch-single-product" data-empty-message="<?php echo esc_attr__( 'Ingresa un SKU para sincronizar el producto.', 'woo-contifico' ); ?>" data-generic-error="<?php echo esc_attr__( 'No fue posible sincronizar el producto. Intenta nuevamente.', 'woo-contifico' ); ?>">
                    <h4><?php esc_html_e( 'Sincronizar un producto específico', 'woo-contifico' ); ?></h4>
                    <p><?php esc_html_e( 'Introduce el SKU del producto que deseas actualizar con los datos de Contífico.', 'woo-contifico' ); ?></p>
                    <label>
                        <span class="screen-reader-text"><?php esc_html_e( 'SKU del producto', 'woo-contifico' ); ?></span>
                        <input type="text" name="woo_contifico_single_sku" placeholder="<?php echo esc_attr__( 'SKU de WooCommerce', 'woo-contifico' ); ?>" />
                    </label>
                    <button type="button" class="button button-secondary"><?php esc_html_e( 'Sincronizar producto', 'woo-contifico' ); ?></button>
                    <img alt="loading" src="<?php echo esc_url( includes_url() . 'js/thickbox/loadingAnimation.gif' ); ?>" />
                    <div class="result" aria-live="polite"></div>
                </div>
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
