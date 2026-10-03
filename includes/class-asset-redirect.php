<?php
/**
 * Requests for an app's files at the site root.
 *
 * @package BanzaiEmbed
 */

namespace BanzaiEmbed;

defined( 'ABSPATH' ) || exit;

/**
 * Sends a request for one of an app's files at the wrong path — almost always
 * the site root — to where the file really is.
 *
 * Path_Rewriter fixes the references it can see at upload. What it cannot see
 * is a URL the app assembles at runtime: `import.meta.env.BASE_URL + 'pig.png'`
 * (Vite compiles BASE_URL to a bare "/"), `` `/sounds/${name}` ``, a hard-coded
 * `setDecoderPath('/draco/')`. Those reach the server as /pig.png and
 * /sounds/win.mp3, which WordPress answers with a 404.
 *
 * So when a request is about to 404, and its path names a file in an enabled
 * app's build, this redirects to that file. It only ever acts on requests
 * that would have been a 404 — a page, post, feed, sitemap or robots.txt at
 * the same path is never touched — and only for files already public in
 * uploads/, so it exposes nothing new.
 *
 * Paths are tried as they are, and with the app's compiled-for base removed,
 * for builds made for a path like /my-app/. The current build is tried
 * before the previous one, which is kept for cached pages.
 */
final class Asset_Redirect {

	/**
	 * How long browsers may cache a redirect, in seconds. Short: the target
	 * changes with every upload, and builds older than the previous one are
	 * deleted.
	 */
	const MAX_AGE = 600;

	/**
	 * Files at the site root that belong to the site, not an app, even when
	 * a build happens to contain one and WordPress has none to serve.
	 */
	const SITE_FILES = array( 'robots.txt', 'favicon.ico', 'ads.txt', 'app-ads.txt', 'humans.txt', 'sitemap.xml', 'sitemap_index.xml', 'manifest.json', 'site.webmanifest', 'sw.js', 'service-worker.js' );

	/**
	 * @var App_Manager
	 */
	private $apps;

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
		// Before Routing (10): a file beats a route. pre_handle_404 also runs
		// before redirect_canonical, which would otherwise "guess" a post
		// from the file name and redirect there.
		add_filter( 'pre_handle_404', array( $this, 'maybe_redirect' ), 5, 2 );
	}

	/**
	 * Redirect a would-be 404 that names one of an app's files.
	 *
	 * @param bool      $preempt  Whether another plugin already handled it.
	 * @param \WP_Query $wp_query Main query.
	 * @return bool
	 */
	public function maybe_redirect( $preempt, $wp_query ) {
		if ( $preempt || ! $wp_query->is_main_query() ) {
			return $preempt;
		}

		// Same test as Routing::claim_404(): only a request that found nothing.
		if ( ( $wp_query->posts && ! $wp_query->is_404() ) || $wp_query->is_feed() || $wp_query->is_robots() || $wp_query->is_favicon() ) {
			return $preempt;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';

		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			return $preempt;
		}

		$url = $this->find( $this->request_path() );

		if ( null === $url ) {
			return $preempt;
		}

		header( 'Cache-Control: public, max-age=' . self::MAX_AGE );
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- the target is one of this site's uploads, possibly on an offload host.
		wp_redirect( $url, 302, 'BanzaiEmbed' );
		exit;
	}

	/**
	 * The request's path from the domain root, decoded, without a query.
	 *
	 * @return string
	 */
	private function request_path() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only ever compared against files on disk, by find().
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );

		return rawurldecode( $path );
	}

	/**
	 * URL of the build file a root-absolute path names, if any.
	 *
	 * @param string $path Request path from the domain root.
	 * @return string|null
	 */
	public function find( $path ) {
		if ( '' === $path || false !== strpos( $path, "\0" ) || false !== strpos( $path, '\\' ) ) {
			return null;
		}

		// A file, not a directory: only the last segment may name it, and
		// WordPress slugs never contain a dot, so this skips every page path.
		if ( false === strpos( basename( $path ), '.' ) ) {
			return null;
		}

		if ( in_array( strtolower( ltrim( $path, '/' ) ), self::SITE_FILES, true ) ) {
			return null;
		}

		foreach ( $this->candidates() as $app ) {
			foreach ( $this->relative_to( $path, $app['base'] ) as $relative ) {
				foreach ( array_filter( array( $app['build'], $app['previous_build'] ) ) as $build ) {
					if ( is_file( $this->apps->build_dir( $app['slug'], $build ) . '/' . $relative ) ) {
						return $this->apps->build_url( $app['slug'], $build ) . implode( '/', array_map( 'rawurlencode', explode( '/', $relative ) ) );
					}
				}
			}
		}

		return null;
	}

	/**
	 * Enabled, embeddable apps, in the order to try them: the app whose own
	 * file asked (a stylesheet's url(), a chunk's import) first, then the
	 * most recently uploaded — two apps rarely share a path like /logo.png,
	 * and when they do, the newer one is likelier to be the one being used.
	 *
	 * @return array[]
	 */
	private function candidates() {
		$apps = array_filter(
			$this->apps->all(),
			static function ( $app ) {
				return App_Manager::is_active( $app ) && 'ready' === App_Manager::status( $app );
			}
		);

		uasort(
			$apps,
			static function ( $a, $b ) {
				return $b['uploaded'] - $a['uploaded'];
			}
		);

		$from = $this->referring_slug();

		if ( null !== $from && isset( $apps[ $from ] ) ) {
			$apps = array( $from => $apps[ $from ] ) + $apps;
		}

		/**
		 * Filter which apps a root-absolute request for a missing file may be
		 * redirected into, in the order they are tried. Return an empty array
		 * to switch the redirect off.
		 *
		 * @param array[] $apps Records keyed by slug.
		 */
		return (array) apply_filters( 'bzem/asset_redirect_apps', $apps );
	}

	/**
	 * The app whose build folder the Referer is in, if it is.
	 *
	 * @return string|null
	 */
	private function referring_slug() {
		$referer = wp_get_raw_referer();

		if ( ! $referer ) {
			return null;
		}

		$prefix = (string) wp_parse_url( $this->apps->base_url(), PHP_URL_PATH ) . '/';
		$path   = (string) wp_parse_url( $referer, PHP_URL_PATH );

		if ( 0 !== strpos( $path, $prefix ) ) {
			return null;
		}

		$slug = strtok( substr( $path, strlen( $prefix ) ), '/' );

		return false === $slug ? null : rawurldecode( $slug );
	}

	/**
	 * The paths inside a build that $path could mean: with the base the
	 * build was compiled for removed, then from the domain root.
	 *
	 * @param string $path Request path from the domain root.
	 * @param string $base The app's compiled-for base, or ''.
	 * @return string[]
	 */
	private function relative_to( $path, $base ) {
		$tries = array();

		if ( '' !== $base && '/' !== $base && 0 === strpos( $path, $base ) ) {
			$tries[] = '/' . substr( $path, strlen( $base ) );
		}

		$tries[] = $path;

		return array_values( array_filter( array_map( array( $this, 'safe_relative' ), $tries ) ) );
	}

	/**
	 * A root path as a build-relative one, or null if any segment could step
	 * outside the build or names something a build never serves: dotfiles,
	 * Vite's .vite/ manifest, and the build's own index.html, which is not
	 * a page that works on its own.
	 *
	 * @param string $path Path from the domain root.
	 * @return string|null
	 */
	private function safe_relative( $path ) {
		$segments = explode( '/', ltrim( $path, '/' ) );

		foreach ( $segments as $segment ) {
			if ( '' === $segment || '.' === $segment[0] ) {
				return null;
			}
		}

		$relative = implode( '/', $segments );

		return 'index.html' === $relative ? null : $relative;
	}
}
