<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://otakupahp.com/quien-es-pablo-hernandez-otakupahp
 * @since      1.0.0
 *
 * @package    Woo_Contifico
 * @subpackage Woo_Contifico/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Woo_Contifico
 * @subpackage Woo_Contifico/includes
 * @author     Pablo Hernández (OtakuPahp) <pablo@otakupahp.com>
 */
class Woo_Contifico_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'woo-contifico',
			false,
			WOO_CONTIFICO_PATH . 'languages/'
		);

	}



}
