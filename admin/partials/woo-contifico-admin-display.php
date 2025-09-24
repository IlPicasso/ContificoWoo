<div id="<?php echo $this->plugin_name ?>-settings-page" class="wrap <?php echo $this->is_active() ? 'active' : 'inactive' ?>">
    <h2 class="wp-heading-inline"><?php _e('Facturación Electrónica Contífico',$this->plugin_name) ?></h2>

    <?php settings_errors('woo_contifico_settings'); ?>

    <?php $active_tab = $_GET['tab'] ?? 'woocommerce'; ?>

    <h2 class="nav-tab-wrapper">
        <a href="?page=woo-contifico&tab=woocommerce" class="nav-tab <?php echo $active_tab == 'woocommerce' ? 'nav-tab-active' : ''; ?>"><?php _e('WooCommerce', $this->plugin_name) ?></a>
        <a href="?page=woo-contifico&tab=contifico" class="nav-tab <?php echo $active_tab == 'contifico' ? 'nav-tab-active' : ''; ?>"><?php _e('Contífico', $this->plugin_name) ?></a>
        <a href="?page=woo-contifico&tab=emisor" class="nav-tab <?php echo $active_tab == 'emisor' ? 'nav-tab-active' : ''; ?>"><?php _e('Emisor', $this->plugin_name) ?></a>
        <a href="?page=woo-contifico&tab=establecimiento" class="nav-tab <?php echo $active_tab == 'establecimiento' ? 'nav-tab-active' : ''; ?>"><?php _e('Establecimiento', $this->plugin_name) ?></a>
	    <?php if( $this->config_status['status'] && $this->is_active() ): ?>
        <a href="?page=woo-contifico&tab=sincronizar" class="nav-tab <?php echo $active_tab == 'sincronizar' ? 'nav-tab-active' : ''; ?>"><?php _e('Sincronización manual', $this->plugin_name) ?></a>
        <?php endif; ?>
    </h2>

    <div class="container">
        <!--suppress HtmlUnknownTarget -->
        <form action="options.php"  method="post">
            <?php

                # Button text
                $guardar = __( 'Guardar opciones', $this->plugin_name );

                if( $active_tab === 'woocommerce' ) {
                    settings_fields( 'woo_contifico_woocommerce_settings' );
                    do_settings_sections( 'woo_contifico_woocommerce_settings' );

	                if( $this->woo_contifico->settings['activar_registro'] && file_exists($this->log_path)) {
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

                if ($active_tab !== 'sincronizar') {
	                submit_button( $guardar );
                }
             ?>
        </form>

        <?php if( $this->config_status['status'] && $this->is_active() && $active_tab === 'sincronizar' ): ?>
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
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>