<?php
/**
 * Plugin Name: BanzaiEmbed — Vue & React Apps
 * Description: Embed pre-built Vue, React or vanilla JavaScript apps in any page or post. Upload your build as a zip, then drop it in with a shortcode or block — no Node.js on the server.
 * Version:     0.1.0
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

/*
 * Freemius is not wired in yet — there is no product ID or public key for
 * BanzaiEmbed. When there is, follow BanzaiStyle's main file exactly: the
 * banzaiembed_fs() helper and fs_dynamic_init() go at the top, and EVERYTHING
 * below this comment moves into the `else` branch of
 * `if ( function_exists( 'banzaiembed_fs' ) ) { ...->set_basename(...) }`.
 * Without that branch, having the free and premium copies active at once
 * redeclares every function here and white-screens the site.
 */

define( 'BZEM_VERSION', '0.1.0' );
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
 * Whether this site's license unlocks the pro features.
 *
 * The single gate check — every pro feature goes through here. A plain
 * function so it is callable before the autoloader has loaded License.
 *
 * @return bool
 */
function bzem_has_valid_license() {
	return \BanzaiEmbed\License::is_valid();
}

/**
 * Boot once all plugins are loaded.
 */
function bzem_bootstrap() {
	\BanzaiEmbed\Plugin::instance()->run();
}
add_action( 'plugins_loaded', 'bzem_bootstrap' );
