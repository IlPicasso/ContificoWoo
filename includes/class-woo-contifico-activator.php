<?php

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @link       https://otakupahp.com/quien-es-pablo-hernandez-otakupahp
 *
 * @since      1.0.0
 * @package    Woo_Contifico
 * @subpackage Woo_Contifico/includes
 * @author     Pablo Hernández (OtakuPahp) <pablo@otakupahp.com>
 */
class Woo_Contifico_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {

        $error_message = '';

        //Check for Woocommerce
        if( is_plugin_active('woocommerce/woocommerce.php') ) {

            global $woocommerce;
            $wc_version = $woocommerce->version;
            $wp_version = get_bloginfo( 'version' );
            $php_version = phpversion();

            if( version_compare( $php_version, '7.2', '>=' ) && version_compare( $wp_version, '5.0', '>=' ) && version_compare( $wc_version, '5.0', '>=' ) ) {

                    # Create basic configuration
                    add_option( 'woo_contifico_woocommerce_settings',
                            [
                                    'etapa_envio'          => '',
			            'borrar_configuracion' => 0,
		            ]
	            );
	            add_option( 'woo_contifico_integration_settings',
		            [
			            'ambiente'         => '',
			            'test_api_key'     => '',
			            'test_api_token'   => '',
			            'prod_api_key'     => '',
			            'prod_api_token'   => '',
			            'shipping_code'    => '',
			            'sync_price'       => false,
			            'bodega'           => '',
			            'actualizar_stock' => 'daily',
			            'batch_size'       => 200,
		            ]
	            );
	            add_option( 'woo_contifico_sender_settings',
		            [
			            'emisor_ruc'          => '',
			            'emisor_razon_social' => '',
			            'emisor_telefonos'    => '',
			            'emisor_direccion'    => '',
			            'emisor_email'        => '',
			            'tipo_contribuyente'  => 'N',
			            'emisor_extranjero'   => 0,
		            ]
	            );
	            add_option( 'woo_contifico_pos_settings',
		            [

			            'establecimiento_direccion'   => '',
			            'test_establecimiento_punto'  => '',
			            'test_establecimiento_codigo' => '',
			            'test_secuencial_factura'     => '',
			            'prod_establecimiento_punto'  => '',
			            'prod_establecimiento_codigo' => '',
			            'prod_secuencial_factura'     => '',
		            ]
	            );



	            # Configuration status to know if all the info stored is correct
                    $config_status = [
                            'status' => false,
                            'errors' => []
                    ];
	            update_option('woo_contifico_config_status', $config_status);

            }
            else {
                $error_message = '<p style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Oxygen-Sans,Ubuntu,Cantarell,\'Helvetica Neue\',sans-serif;font-size: 13px;line-height: 1.5;color:#444;">' . __('Este plugin funciona con PHP 7.2 o superior, la versión de Wordpress 5.0 o superior y la versión de Woocommerce 5.0 o superior', 'woo-contifico') . '</p>';
            }
        }
        else {
            $error_message = '<p style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Oxygen-Sans,Ubuntu,Cantarell,\'Helvetica Neue\',sans-serif;font-size: 13px;line-height: 1.5;color:#444;">' . __('Es necesario tener Woocommerce instalado y activado para usar este plugin', 'woo-contifico') . '</p>';
        }

        # Prevent activation in case of errors
        if( !empty($error_message)) {
            # Deactivate the plugin
            deactivate_plugins(plugin_basename( __FILE__ ));
            die( $error_message );
        }

	}

}
