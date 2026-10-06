<?php
/**
 * Plugin Name: BanzaiEmbed — Vue & React App Embedder
 * Plugin URI:  https://github.com/JoshRobs/Banzai-Embed
 * Description: Embed pre-built Vue, React or vanilla JavaScript apps in any page or post. Upload your build as a zip, then drop it in with a shortcode or block — no Node.js on the server.
 * Version:     1.0.0
 * Author:      BanzaiEmbed
 * Text Domain: banzaiembed
 * Requires at least: 6.0
 * Tested up to: 7.1
 * Requires PHP: 7.4
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package BanzaiEmbed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BZEM_VERSION', '1.0.0' );
define( 'BZEM_PLUGIN_FILE', __FILE__ );
define( 'BZEM_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'BZEM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Map BanzaiEmbed\Foo_Bar to includes/class-foo-bar.php.
 *
 * @param string $class Fully qualified class name.
 */
function bzem_autoload( $class ) {
	$prefix = 'BanzaiEmbed\\';

	if ( 0 !== strpos( $class, $prefix ) ) {
		return;
	}

	$name = str_replace( '_', '-', strtolower( substr( $class, strlen( $prefix ) ) ) );
	$file = BZEM_PLUGIN_PATH . 'includes/class-' . $name . '.php';

	if ( is_readable( $file ) ) {
		require_once $file;
	}
}
spl_autoload_register( 'bzem_autoload' );

/**
 * Boot once all plugins are loaded.
 */
function bzem_bootstrap() {
	\BanzaiEmbed\Plugin::instance()->run();
}
add_action( 'plugins_loaded', 'bzem_bootstrap' );

/**
 * Remove every app record and every uploaded build when the plugin is
 * deleted — never on deactivation, which must not cost anyone their apps.
 *
 * WordPress loads this file before calling the hook, so the autoloader is
 * there for it.
 */
function bzem_uninstall() {
	delete_option( \BanzaiEmbed\App_Manager::OPTION );
	\BanzaiEmbed\Filesystem::delete( ( new \BanzaiEmbed\App_Manager() )->base_dir() );
}

/**
 * Record the uninstall hook. Done on activation, as WordPress recommends —
 * it is stored in an option, so it need not be registered on every request.
 */
function bzem_activate() {
	register_uninstall_hook( BZEM_PLUGIN_FILE, 'bzem_uninstall' );
}
register_activation_hook( __FILE__, 'bzem_activate' );