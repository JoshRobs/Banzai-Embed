<?php
/**
 * Site-wide placement (Pro).
 *
 * This file is not in the free build: Freemius leaves out files whose names
 * contain __premium_only, and Plugin::run() loads it inside an
 * is__premium_only() block, which Freemius strips too.
 *
 * @package BanzaiEmbed
 */

namespace BanzaiEmbed;

defined( 'ABSPATH' ) || exit;

/**
 * Prints apps on every front-end page their rules match, with no shortcode
 * or block — chat widgets, feedback buttons, announcement bars.
 *
 * The mount point goes out in wp_footer through Embed::render(), so a
 * site-wide app gets the same window.banzaiEmbed data, unique mount IDs and
 * asset loading as an embedded one. Its styles are enqueued on
 * wp_enqueue_scripts so they land in <head>.
 *
 * Rendering checks that this code is present, not that the licence is
 * valid: an app already placed site-wide keeps showing if the licence lapses,
 * because a widget vanishing from a client's whole site overnight is the
 * worst way to find out. Changing placement or rules needs a licence.
 */
final class Site_Wide {

	const AUDIENCES = array( 'everyone', 'logged_in', 'logged_out' );

	/**
	 * @var App_Manager
	 */
	private $apps;

	/**
	 * @var Embed
	 */
	private $embed;

	/**
	 * Apps whose rules match this request; null until worked out.
	 *
	 * @var array[]|null
	 */
	private $matched = null;

	/**
	 * Constructor.
	 *
	 * @param App_Manager $apps  App store.
	 * @param Embed       $embed Renderer.
	 */
	public function __construct( App_Manager $apps, Embed $embed ) {
		$this->apps  = $apps;
		$this->embed = $embed;
	}

	/**
	 * Hook in.
	 */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'print_mounts' ), 5 );
		add_action( 'bzem/edit_placement', array( $this, 'render_settings' ), 10, 2 );
		add_filter( 'bzem/save_app', array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Whether this site may change placement and rules.
	 *
	 * @return bool
	 */
	public static function can_configure() {
		return bzem_has_valid_license();
	}

	/**
	 * Enqueue matching apps' assets before wp_head.
	 */
	public function enqueue() {
		foreach ( $this->matched() as $app ) {
			$this->embed->enqueue( $app );
		}
	}

	/**
	 * Print matching apps' mount points, before the footer scripts.
	 */
	public function print_mounts() {
		foreach ( $this->matched() as $app ) {
			echo $this->embed->render( $app['slug'], array( 'class' => 'bzem-site-wide' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Embed::render().
		}
	}

	/**
	 * Site-wide apps that are on, ready and whose rules match this request.
	 *
	 * @return array[]
	 */
	private function matched() {
		if ( null !== $this->matched ) {
			return $this->matched;
		}

		$this->matched = array();

		if ( is_admin() || is_feed() || is_embed() || wp_is_json_request() ) {
			return $this->matched;
		}

		foreach ( $this->apps->all() as $app ) {
			if ( App_Manager::is_site_wide( $app ) && App_Manager::is_active( $app ) && 'ready' === App_Manager::status( $app ) && $this->matches( $app ) ) {
				$this->matched[] = $app;
			}
		}

		return $this->matched;
	}

	/**
	 * Whether an app's rules match the current request.
	 *
	 * @param array $app Record.
	 * @return bool
	 */
	public function matches( array $app ) {
		$rules = App_Manager::rules( $app );
		$match = true;

		if ( 'logged_in' === $rules['audience'] ) {
			$match = is_user_logged_in();
		} elseif ( 'logged_out' === $rules['audience'] ) {
			$match = ! is_user_logged_in();
		}

		// Chosen post types mean their single views; empty means every page.
		if ( $match && $rules['post_types'] && ! is_singular( $rules['post_types'] ) ) {
			$match = false;
		}

		if ( $match && $rules['exclude'] && in_array( self::current_page_id(), $rules['exclude'], true ) ) {
			$match = false;
		}

		/**
		 * Filter whether a site-wide app shows on this request.
		 *
		 * @param bool  $match Whether its rules match.
		 * @param array $app   App record.
		 */
		return (bool) apply_filters( 'bzem/site_wide_matches', $match, $app );
	}

	/**
	 * The page or post being viewed, for exclusions; 0 on archives, search
	 * and 404s, so a term ID is never mistaken for a post ID.
	 *
	 * @return int
	 */
	private static function current_page_id() {
		if ( is_singular() ) {
			return (int) get_queried_object_id();
		}

		// The blog index on a site with a static front page is a page too.
		if ( is_home() && ! is_front_page() ) {
			return (int) get_option( 'page_for_posts' );
		}

		return 0;
	}

	/**
	 * Post types a site-wide app can be limited to.
	 *
	 * @return array<string,string> name => label.
	 */
	private static function post_types() {
		$out = array();

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( 'attachment' !== $type->name ) {
				$out[ $type->name ] = $type->labels->name;
			}
		}

		return $out;
	}

	/**
	 * Read placement and rules from the edit form.
	 *
	 * Moving an app back to its shortcode is always allowed. Placing it
	 * site-wide or changing its rules needs a licence; without one, what was
	 * saved before is kept.
	 *
	 * @param array $app    Record about to be saved.
	 * @param bool  $is_new Whether the app is being created.
	 * @return array
	 */
	public function save( $app, $is_new ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Admin::handle_save() verified the nonce.
		if ( ! isset( $_POST['placement'] ) ) {
			return $app;
		}

		if ( 'site_wide' !== sanitize_key( wp_unslash( $_POST['placement'] ) ) ) {
			$app['placement'] = 'shortcode';

			return $app;
		}

		if ( ! self::can_configure() ) {
			return $app;
		}

		$scope    = isset( $_POST['rules_scope'] ) ? sanitize_key( wp_unslash( $_POST['rules_scope'] ) ) : 'all';
		$types    = isset( $_POST['rules_post_types'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['rules_post_types'] ) ) : array();
		$exclude  = isset( $_POST['rules_exclude'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['rules_exclude'] ) ) : array();
		$audience = isset( $_POST['rules_audience'] ) ? sanitize_key( wp_unslash( $_POST['rules_audience'] ) ) : 'everyone';
		// phpcs:enable

		$app['placement'] = 'site_wide';
		$app['rules']     = App_Manager::rules(
			array(
				'rules' => array(
					// "Only these post types" with none ticked falls back to every page.
					'post_types' => 'post_types' === $scope ? array_values( array_intersect( $types, array_keys( self::post_types() ) ) ) : array(),
					'exclude'    => $exclude,
					'audience'   => in_array( $audience, self::AUDIENCES, true ) ? $audience : 'everyone',
				),
			)
		);

		return $app;
	}

	/**
	 * Print the Placement card on the edit screen.
	 *
	 * @param array $record App being edited.
	 * @param bool  $is_new Whether it is being created.
	 */
	public function render_settings( $record, $is_new ) {
		$licensed   = self::can_configure();
		$site_wide  = App_Manager::is_site_wide( $record );
		$rules      = App_Manager::rules( $record );
		$post_types = self::post_types();
		$pages      = get_pages(
			array(
				'sort_column' => 'menu_order,post_title',
				'post_status' => 'publish,private',
			)
		);

		include BZEM_PLUGIN_PATH . 'templates/admin-site-wide__premium_only.php';
	}
}
