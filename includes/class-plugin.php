<?php
/**
 * Plugin orchestration.
 *
 * @package BanzaiEmbed
 */

namespace BanzaiEmbed;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the pieces together.
 *
 * Construction has no side effects — everything happens in run() — so the
 * plugin can be instantiated in a test without registering hooks.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * @var App_Manager
	 */
	private $apps;

	/**
	 * @var Embed
	 */
	private $embed;

	/**
	 * @var Admin
	 */
	private $admin;

	/**
	 * Get the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->apps  = new App_Manager();
		$this->embed = new Embed( $this->apps );
		$this->admin = new Admin( $this->apps, new Uploader( $this->apps ), new Asset_Detector(), new Path_Rewriter() );
	}

	/**
	 * Register everything.
	 *
	 * @return bool Whether the plugin started.
	 */
	public function run() {
		$this->embed->register();
		( new Shortcode( $this->embed ) )->register();
		( new Block( $this->embed, $this->apps ) )->register();

		if ( is_admin() ) {
			$this->admin->register();
		}

		// Stripped from the free build by Freemius, files and all.
		if ( bzem_fs()->is__premium_only() ) {
			require_once BZEM_PLUGIN_PATH . 'includes/class-site-wide__premium_only.php';
			require_once BZEM_PLUGIN_PATH . 'includes/class-data-bridge__premium_only.php';
			require_once BZEM_PLUGIN_PATH . 'includes/class-custom-code__premium_only.php';
			( new Site_Wide( $this->apps, $this->embed ) )->register();
			( new Data_Bridge( $this->apps ) )->register();
			( new Custom_Code() )->register();
		}

		/**
		 * Fires once the plugin is active. Pro modules hook here.
		 *
		 * @param Plugin $plugin The running plugin.
		 */
		do_action( 'bzem/init', $this );

		return true;
	}

	/**
	 * The app store, for add-ons.
	 *
	 * @return App_Manager
	 */
	public function apps() {
		return $this->apps;
	}
}
