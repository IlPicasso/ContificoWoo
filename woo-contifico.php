<?php
/**
 *
 * @link              https://otakupahp.com/quien-es-pablo-hernandez-otakupahp
 * @since             1.0.0
 * @package           Woo_Contifico
 *
 * @wordpress-plugin
 * Plugin Name:       Woocommerce - Facturación Electrónica - Contífico
 * Plugin URI:        https://otakupahp.com/producto/woocommerce-factura-electronica-con-contifico/
 * Description:       Integración simple de facturación electrónica para woocommerce a través del servicio de Contífico. Servicio solo válido en Ecuador
 * Version:           4.1.2
 * Author:            Pablo Hernández (OtakuPahp)
 * Author URI:        https://otakupahp.com/quien-es-pablo-hernandez-otakupahp
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       woo-contifico
 * Domain Path:       /languages
 * Requires at least: 5.0
 * Tested up to:      5.9
 * Requires PHP:      7.2
 * WC requires at least: 5.0
 * WC tested up to:   6.4
 */

# If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Define global path constants
 */
define( 'WOO_CONTIFICO_FILE', __FILE__ );
define( 'WOO_CONTIFICO_PATH', plugin_dir_path( __FILE__ ) );
define( 'WOO_CONTIFICO_URL', plugin_dir_url( __FILE__ ) );

# Currently plugin version
define( 'WOO_CONTIFICO_VERSION', '4.1.2' );

# Environment states
define( 'WOO_CONTIFICO_TEST', 1 );
define( 'WOO_CONTIFICO_PRODUCTION', 2 );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-woo-contifico-activator.php
 */
function activate_woo_contifico() {
	require_once WOO_CONTIFICO_PATH . 'includes/class-woo-contifico-activator.php';
	Woo_Contifico_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-woo-contifico-deactivator.php
 */
function deactivate_woo_contifico() {
	require_once WOO_CONTIFICO_PATH . 'includes/class-woo-contifico-deactivator.php';
	Woo_Contifico_Deactivator::deactivate();
}

register_activation_hook( WOO_CONTIFICO_FILE, 'activate_woo_contifico' );
register_deactivation_hook( WOO_CONTIFICO_FILE, 'deactivate_woo_contifico' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require WOO_CONTIFICO_PATH . 'includes/class-woo-contifico.php';

add_action( 'before_woocommerce_init', static function() {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
} );

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_woo_contifico() {

        $plugin = new Woo_Contifico();
        $plugin->run();

}
add_action( 'init', 'run_woo_contifico', 0 );
