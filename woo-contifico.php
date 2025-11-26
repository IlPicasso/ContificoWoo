<?php
/**
 *
 * @link              https://github.com/IlPicasso/ContificoWoo
 * @since             1.0.0
 * @package           Woo_Contifico
 *
 * @wordpress-plugin
 * Plugin Name:       Woocommerce - Facturación Electrónica - Contífico
 * Plugin URI:        https://github.com/IlPicasso/ContificoWoo
 * Description:       Integración simple de facturación electrónica para woocommerce a través del servicio de Contífico. Servicio solo válido en Ecuador
 * Version:           4.1.35
 * Author:            IlPicasso
 * Author URI:        https://github.com/IlPicasso
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       woo-contifico
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Tested up to:      6.8
 * Requires PHP:      7.4
 * WC requires at least: 5.8
 * WC tested up to:   10.3
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
define( 'WOO_CONTIFICO_VERSION', '4.1.35' );

# GitHub repository metadata for auto-updates
if ( ! defined( 'WOO_CONTIFICO_REPO_OWNER' ) ) {
	define( 'WOO_CONTIFICO_REPO_OWNER', apply_filters( 'woo_contifico_repo_owner', 'IlPicasso' ) );
}

if ( ! defined( 'WOO_CONTIFICO_REPO_NAME' ) ) {
	define( 'WOO_CONTIFICO_REPO_NAME', apply_filters( 'woo_contifico_repo_name', 'ContificoWoo' ) );
}

if ( ! defined( 'WOO_CONTIFICO_REPO_BRANCH' ) ) {
	define( 'WOO_CONTIFICO_REPO_BRANCH', apply_filters( 'woo_contifico_repo_branch', 'main' ) );
}

if ( ! defined( 'WOO_CONTIFICO_REPO_ACCESS_TOKEN' ) ) {
        define( 'WOO_CONTIFICO_REPO_ACCESS_TOKEN', apply_filters( 'woo_contifico_repo_access_token', '' ) );
}

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
require_once WOO_CONTIFICO_PATH . 'includes/class-woo-contifico-updater.php';

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

/**
 * Bootstraps the GitHub-based plugin updater.
 */
function woo_contifico_bootstrap_updater() {
        if ( ! is_admin() ) {
                return;
        }

        $updater = new Woo_Contifico_Updater(
                WOO_CONTIFICO_FILE,
                WOO_CONTIFICO_REPO_OWNER,
                WOO_CONTIFICO_REPO_NAME,
                WOO_CONTIFICO_REPO_BRANCH,
                WOO_CONTIFICO_REPO_ACCESS_TOKEN
        );

        $updater->init();
}
add_action( 'admin_init', 'woo_contifico_bootstrap_updater' );
