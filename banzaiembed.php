<?php
/**
 * Plugin Name: BanzaiEmbed — Vue & React App Embedder
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
 * The free build ships to wp.org as `banzaiembed/`; the paid one downloads from
 * Freemius as `banzaiembed-premium/`. WordPress lets both be active at once —
 * typically for a few seconds while someone who has just paid installs the
 * premium copy over the free one.
 *
 * Freemius deactivates one of them, and set_basename() is how it learns which
 * file is which. The second copy to load must not run the rest of this file:
 * everything in the `else` declares bare functions, and declaring any of them
 * twice in one request is a fatal error.
 */
if ( function_exists( 'bzem_fs' ) ) {
	bzem_fs()->set_basename( true, __FILE__ );
} else {
	/**
	 * DO NOT REMOVE THIS IF, IT IS ESSENTIAL FOR THE
	 * `function_exists` CALL ABOVE TO PROPERLY WORK.
	 */
	if ( ! function_exists( 'bzem_fs' ) ) {
		// Create a helper function for easy SDK access.
		function bzem_fs() {
			global $bzem_fs;

			if ( ! isset( $bzem_fs ) ) {
				// Include Freemius SDK.
				require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';

				$bzem_fs = fs_dynamic_init( array(
					'id'                  => '40624',
					'slug'                => 'banzaiembed',
					'type'                => 'plugin',
					'public_key'          => 'pk_a4b2b0962a7e37bce64e5d4be345a',
					'is_premium'          => true,
					'premium_suffix'      => 'Professional',
					// If your plugin is a serviceware, set this option to false.
					'has_premium_version' => true,
					'has_addons'          => false,
					'has_paid_plans'      => true,
					'is_org_compliant'    => true,
					// Automatically removed in the free version. If you're not using the
					// auto-generated free version, delete this line before uploading to wp.org.
					'wp_org_gatekeeper'   => 'OA7#BoRiBNqdf52FvzEf!!074aRLPs8fspif$7K1#4u4Csys1fQlCecVcUTOs2mcpeVHi#C2j9d09fOTvbC0HloPT7fFee5WdS3G',
					'menu'                => array(
						'slug'    => 'banzaiembed',
						'support' => false,
					),
				) );
			}

			return $bzem_fs;
		}

		// Init Freemius.
		bzem_fs();
		// Signal that SDK was initiated.
		do_action( 'bzem_fs_loaded' );
	}

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

	/**
	 * Remove every app record and every uploaded build when the plugin is
	 * deleted — never on deactivation, which must not cost anyone their apps.
	 *
	 * This replaces uninstall.php. WordPress runs that file instead of any
	 * registered uninstall hook, and Freemius reports uninstalls through one,
	 * so with the file present Freemius never hears about them.
	 */
	function bzem_uninstall() {
		delete_option( \BanzaiEmbed\App_Manager::OPTION );
		\BanzaiEmbed\Filesystem::delete( ( new \BanzaiEmbed\App_Manager() )->base_dir() );
	}
	bzem_fs()->add_action( 'after_uninstall', 'bzem_uninstall' );
}