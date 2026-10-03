<?php
/**
 * Client-side routing (Pro).
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
 * Lets an app with its own router (React Router, Vue Router…) own the paths
 * below the page it is on: /portal/settings and /portal/orders/42 load the
 * /portal/ page, and the app's router renders the rest.
 *
 * No rewrite rules. WordPress resolves every request as usual; only when the
 * result would be a 404 does pre_handle_404 check whether the path sits under
 * a routed page, and if so re-run the main query for that page. So:
 *
 * - a real child page (/portal/help) is never swallowed — it is not a 404;
 * - nothing is stored or flushed, so renaming or moving the page just works;
 * - posts, archives and feeds under the same prefix keep resolving.
 *
 * The one path under a page that is not a 404 is /portal/42, which WordPress
 * reads as a page number; claim_page_number() turns that into a route too.
 *
 * Every route answers 200 with the page's own canonical URL; paths the app
 * does not know are for its router's "not found" screen. The app is told its
 * base as cfg.basePath ("/portal"), and the path below it as cfg.route.
 *
 * Like Site_Wide, routing keeps working if the licence lapses; changing it
 * needs a licence.
 */
final class Routing {

	/**
	 * @var App_Manager
	 */
	private $apps;

	/**
	 * The route this request was claimed for, or null.
	 *
	 * @var array{slug: string, page: int, route: string}|null
	 */
	private $match = null;

	/**
	 * Constructor.
	 *
	 * @param App_Manager $apps App store.
	 */
	public function __construct( App_Manager $apps ) {
		$this->apps = $apps;
	}

	/**
	 * Hook in.
	 */
	public function register() {
		add_filter( 'request', array( $this, 'claim_page_number' ) );
		add_filter( 'pre_handle_404', array( $this, 'claim_404' ), 10, 2 );
		add_filter( 'redirect_canonical', array( $this, 'keep_url' ) );
		add_filter( 'bzem/app_data', array( $this, 'app_data' ), 10, 2 );
		// After Site_Wide's Placement card (10): where the app lives, then how it routes.
		add_action( 'bzem/edit_placement', array( $this, 'render_card' ), 20, 2 );
		add_filter( 'bzem/save_app', array( $this, 'save' ), 10, 2 );
	}

	/**
	 * An app's routing settings, complete and well-typed whatever was stored.
	 *
	 * @param array $app Record.
	 * @return array{enabled: bool, pages: int[]}
	 */
	public static function config( array $app ) {
		$routing = isset( $app['routing'] ) && is_array( $app['routing'] ) ? $app['routing'] : array();

		return array(
			'enabled' => ! empty( $routing['enabled'] ),
			'pages'   => array_values( array_unique( array_filter( array_map( 'absint', isset( $routing['pages'] ) ? (array) $routing['pages'] : array() ) ) ) ),
		);
	}

	/**
	 * Whether this site's permalinks can carry routes at all.
	 *
	 * @return bool
	 */
	public static function permalinks_ok() {
		return '' !== (string) get_option( 'permalink_structure' );
	}

	/**
	 * /portal/42 is not a 404: WordPress reads the number as page 42 of the
	 * Portal page (as for posts split with <!--nextpage-->), then redirects to
	 * /portal/. On a routed page that is not split into pages, the number is
	 * a route instead.
	 *
	 * @param array $vars Query vars WordPress parsed from the URL.
	 * @return array
	 */
	public function claim_page_number( $vars ) {
		if ( empty( $vars['page'] ) || empty( $vars['pagename'] ) || ! self::permalinks_ok() ) {
			return $vars;
		}

		$page = get_page_by_path( $vars['pagename'] );

		if ( ! $page || false !== strpos( $page->post_content, '<!--nextpage-->' ) ) {
			return $vars;
		}

		$match = $this->match_path( trim( $vars['pagename'], '/' ) . '/' . trim( $vars['page'], '/' ) );

		if ( $match && $match['page'] === $page->ID ) {
			unset( $vars['page'] );
			$this->match = $match;
		}

		return $vars;
	}

	/**
	 * Turn a would-be 404 under a routed page into that page.
	 *
	 * @param bool      $preempt  Whether another plugin already handled it.
	 * @param \WP_Query $wp_query Main query.
	 * @return bool True when the request was claimed.
	 */
	public function claim_404( $preempt, $wp_query ) {
		global $wp;

		if ( $preempt || ! $wp_query->is_main_query() || ! self::permalinks_ok() || ! $wp instanceof \WP ) {
			return $preempt;
		}

		// pre_handle_404 runs on every request, before WordPress decides. Only
		// a request that found nothing is a 404 in the making; anything that
		// resolved (a child page, a post, an attachment) is left alone, as are
		// feeds, robots.txt and the favicon. A path no rewrite rule matched is
		// already flagged 404 but still ran WordPress's default query, which
		// found the latest posts — so is_404() wins over $posts.
		if ( ( $wp_query->posts && ! $wp_query->is_404() ) || $wp_query->is_feed() || $wp_query->is_robots() || $wp_query->is_favicon() ) {
			return $preempt;
		}

		$match = $this->match_path( trim( (string) $wp->request, '/' ) );

		if ( ! $match ) {
			return $preempt;
		}

		$wp_query->query( array( 'page_id' => $match['page'] ) );

		// Not visible to this visitor (a private page, logged out): let the
		// query above fall through to WordPress's normal 404.
		if ( ! $wp_query->have_posts() ) {
			return $preempt;
		}

		$this->match = $match;

		// A path no rewrite rule matched carries error=404, and newer
		// WordPress sends headers after this filter: the error var would turn
		// the status back to 404 and add no-cache headers. Older WordPress
		// sent them before, so the 200 is set here too.
		unset( $wp->query_vars['error'] );
		status_header( 200 );

		return true;
	}

	/**
	 * The routed page whose path is the longest prefix of $path, if any.
	 *
	 * @param string $path Request path relative to home, no slashes at the ends.
	 * @return array{slug: string, page: int, route: string}|null
	 */
	private function match_path( $path ) {
		if ( '' === $path ) {
			return null;
		}

		$best = null;

		foreach ( $this->apps->all() as $app ) {
			$config = self::config( $app );

			if ( ! $config['enabled'] || ! App_Manager::is_active( $app ) || 'ready' !== App_Manager::status( $app ) ) {
				continue;
			}

			foreach ( $config['pages'] as $page ) {
				$uri = 'page' === get_post_type( $page ) ? get_page_uri( $page ) : '';

				if ( ! $uri || ! self::routable( $page ) ) {
					continue;
				}

				$prefix = trim( $uri, '/' ) . '/';

				if ( 0 === strpos( $path, $prefix ) && strlen( $path ) > strlen( $prefix ) && ( ! $best || strlen( $prefix ) > strlen( $best['prefix'] ) ) ) {
					$best = array(
						'slug'   => $app['slug'],
						'page'   => (int) $page,
						'route'  => substr( $path, strlen( $prefix ) ),
						'prefix' => $prefix,
					);
				}
			}
		}

		if ( $best ) {
			unset( $best['prefix'] );
		}

		/**
		 * Filter which routed page, if any, claims a path that would 404.
		 * Return null to let it 404 — for example, to keep a section of an
		 * app's paths out of reach.
		 *
		 * @param array|null $best { slug, page, route } or null.
		 * @param string     $path Request path, no slashes at the ends.
		 */
		return apply_filters( 'bzem/route_match', $best, $path );
	}

	/**
	 * Whether a page can carry routes: not the front page (routing under the
	 * site root would claim every 404 on the site) or the posts page.
	 *
	 * @param int $page Page ID.
	 * @return bool
	 */
	public static function routable( $page ) {
		$page = (int) $page;

		return ! in_array( $page, array( (int) get_option( 'page_on_front' ), (int) get_option( 'page_for_posts' ) ), true )
			|| 'page' !== get_option( 'show_on_front' );
	}

	/**
	 * Keep a claimed URL as the visitor typed it — the app's router reads it.
	 *
	 * @param string|false $redirect Where WordPress would redirect.
	 * @return string|false
	 */
	public function keep_url( $redirect ) {
		return $this->match ? false : $redirect;
	}

	/**
	 * Tell a routed app on one of its pages where its routes start.
	 *
	 * @param array $data Object the app sees.
	 * @param array $app  Record.
	 * @return array
	 */
	public function app_data( $data, $app ) {
		$config = self::config( $app );

		if ( ! $config['enabled'] || ! is_page() ) {
			return $data;
		}

		$page = (int) get_queried_object_id();

		if ( ! in_array( $page, $config['pages'], true ) ) {
			return $data;
		}

		// No trailing slash: what React Router's basename and Vue Router's
		// createWebHistory() both take.
		$data['basePath'] = untrailingslashit( (string) wp_parse_url( get_permalink( $page ), PHP_URL_PATH ) );
		$data['route']    = $this->match && $this->match['page'] === $page ? $this->match['route'] : '';

		return $data;
	}

	/**
	 * Pages that can be chosen, in menu order, with each one's depth and
	 * whether it already embeds this app.
	 *
	 * @param array $app Record.
	 * @return array[] { page: \WP_Post, depth: int, embeds: bool }
	 */
	private static function choices( array $app ) {
		$pages = get_pages(
			array(
				'sort_column' => 'menu_order,post_title',
				'post_status' => 'publish,private',
			)
		);
		$depth = array();
		$out   = array();

		foreach ( $pages as $page ) {
			$depth[ $page->ID ] = isset( $depth[ $page->post_parent ] ) ? $depth[ $page->post_parent ] + 1 : 0;

			if ( ! self::routable( $page->ID ) ) {
				continue;
			}

			$out[] = array(
				'page'   => $page,
				'depth'  => $depth[ $page->ID ],
				'embeds' => '' !== $app['slug'] && in_array( $app['slug'], Embed::find_slugs( $page->post_content ), true ),
			);
		}

		return $out;
	}

	/**
	 * Routed pages already taken by other apps.
	 *
	 * @param string $slug This app's slug.
	 * @return array<int,string> page ID => app name.
	 */
	private function taken( $slug ) {
		$taken = array();

		foreach ( $this->apps->all() as $other ) {
			$config = self::config( $other );

			if ( $other['slug'] !== $slug && $config['enabled'] ) {
				foreach ( $config['pages'] as $page ) {
					$taken[ $page ] = $other['name'];
				}
			}
		}

		return $taken;
	}

	/**
	 * Print the Routing card on the edit screen, below Placement.
	 *
	 * @param array $record App being edited.
	 * @param bool  $is_new Whether it is being created.
	 */
	public function render_card( $record, $is_new ) {
		$licensed  = bzem_has_valid_license();
		$config    = self::config( $record );
		$choices   = self::choices( $record );
		$taken     = $this->taken( $record['slug'] );
		$permalink = self::permalinks_ok();

		include BZEM_PLUGIN_PATH . 'templates/admin-routing__premium_only.php';
	}

	/**
	 * Read the Routing card. Runs inside Admin::handle_save(), after its
	 * capability and nonce checks.
	 *
	 * @param array $app    Record about to be saved.
	 * @param bool  $is_new Whether the app is being created.
	 * @return array
	 */
	public function save( $app, $is_new ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in Admin::handle_save().
		// Not on the form, or no licence to change it: keep what was saved.
		if ( ! isset( $_POST['bzem_routing'] ) || ! bzem_has_valid_license() ) {
			return $app;
		}

		$enabled = ! empty( $_POST['routing_enabled'] );
		$wanted  = isset( $_POST['routing_pages'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['routing_pages'] ) ) : array();
		// phpcs:enable

		$taken    = $this->taken( $app['slug'] );
		$pages    = array();
		$problems = array();

		foreach ( array_unique( array_filter( $wanted ) ) as $page ) {
			$title = get_the_title( $page );

			if ( 'page' !== get_post_type( $page ) || ! in_array( get_post_status( $page ), array( 'publish', 'private' ), true ) ) {
				continue;
			}

			if ( ! self::routable( $page ) ) {
				/* translators: %s: page title. */
				$problems[] = sprintf( __( '%s: the front page and posts page cannot carry routes', 'banzaiembed' ), $title );
			} elseif ( isset( $taken[ $page ] ) ) {
				/* translators: 1: page title, 2: other app's name. */
				$problems[] = sprintf( __( '%1$s: already routed by "%2$s"', 'banzaiembed' ), $title, $taken[ $page ] );
			} else {
				$pages[] = $page;
			}
		}

		$app['routing'] = array(
			'enabled' => $enabled,
			'pages'   => $pages,
		);

		if ( $problems ) {
			Admin::notice( 'warning', __( 'Some routing pages were not saved:', 'banzaiembed' ), $problems );
		}

		if ( $enabled && ! $pages ) {
			Admin::notice( 'warning', __( 'Routing is on, but no page is chosen, so no routes are served yet. Choose the page this app is embedded on.', 'banzaiembed' ) );
		}

		return $app;
	}
}
