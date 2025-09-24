<?php

/**
 * Fired during plugin deactivation
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @link       https://otakupahp.com/quien-es-pablo-hernandez-otakupahp
 * @since      1.0.0
 *
 * @package    Woo_Contifico
 * @subpackage Woo_Contifico/includes
 *
 * @author     Pablo Hernández (OtakuPahp) <pablo@otakupahp.com>
 */
class Woo_Contifico_Deactivator {

	/**
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {

		$woo_contifico = new Woo_Contifico(FALSE);

		$settings = get_option('woo_contifico_woocommerce_settings', [] );

		# Remove plugin data
		if( $settings['borrar_configuracion'] == 1 ) {
			delete_option('woo_contifico_plugin_settings');
			delete_option('woo_contifico_woocommerce_settings');
			delete_option('woo_contifico_integration_settings');
			delete_option('woo_contifico_sender_settings');
			delete_option('woo_contifico_pos_settings');
			delete_option('woo_contifico_products');
			delete_option('woo_contifico_warehouses');
			delete_option('woo-contifico-is-valid');
			delete_option('external_updates-woo-contifico');
		}

		# Remove crons
		as_unschedule_action( 'woo_contifico_sync_stock', [1]);
	}

}
