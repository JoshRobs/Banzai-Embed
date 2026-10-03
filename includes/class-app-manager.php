<?php
/**
 * App records.
 *
 * @package BanzaiEmbed
 */

namespace BanzaiEmbed;

defined( 'ABSPATH' ) || exit;

/**
 * CRUD for app records, and where each app's files live.
 *
 * Every app is one entry in the `banzaiembed_apps` option, keyed by slug. Its
 * files live at uploads/banzaiembed/{slug}/{build}/ — one directory per
 * upload, so every rebuild gets new URLs. That is the cache-busting: hashed or
 * not, nothing a browser or CDN has cached can be served for the new build,
 * including lazy chunks and CSS url() assets a version query string would
 * never reach. The previous build is kept so a page cached before the upload
 * keeps working until the cache turns over.
 */
final class App_Manager {

	const OPTION = 'banzaiembed_apps';

	const DIR = 'banzaiembed';

	const FRAMEWORKS = array( 'vue', 'react', 'other' );

	/**
	 * Request-level cache of the option.
	 *
	 * @var array|null
	 */
	private $apps = null;

	/**
	 * The shape of a record. Anything missing from a stored record is filled
	 * from here, so old records survive new fields.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'name'              => '',
			'slug'              => '',
			'framework'         => 'other',
			// Empty means "use the detected one, else bzem-{slug}".
			'mount_id'          => '',
			'detected_mount_id' => '',
			'build'             => '',
			'previous_build'    => '',
			'uploaded'          => 0,
			// 'module' or 'classic' — how every script tag is printed.
			'script_type'       => 'module',
			// Paths relative to the build directory, or absolute http(s) URLs.
			'scripts'           => array(),
			'styles'            => array(),
			// index-html, vite-manifest, asset-manifest, pattern, manual or ''.
			'detected_by'       => '',
			'warnings'          => array(),
			// Root-absolute base the build was compiled for ('/' or '/x/y/'),
			// whose references Path_Rewriter relocated; '' if relative.
			'base'              => '',
			// Inactive apps render nothing on the front end.
			'active'            => true,
			'created'           => 0,
			'modified'          => 0,
		);
	}

	/**
	 * All apps, keyed by slug, sorted by name.
	 *
	 * @return array[]
	 */
	public function all() {
		if ( null === $this->apps ) {
			$stored = get_option( self::OPTION, array() );
			$apps   = array();

			foreach ( is_array( $stored ) ? $stored : array() as $slug => $app ) {
				if ( is_array( $app ) ) {
					$apps[ $slug ] = array_merge( self::defaults(), $app );
				}
			}

			uasort(
				$apps,
				static function ( $a, $b ) {
					return strcasecmp( $a['name'], $b['name'] );
				}
			);

			$this->apps = $apps;
		}

		return $this->apps;
	}

	/**
	 * One app, or null.
	 *
	 * @param string $slug App slug.
	 * @return array|null
	 */
	public function get( $slug ) {
		$apps = $this->all();

		return isset( $apps[ $slug ] ) ? $apps[ $slug ] : null;
	}

	/**
	 * Insert or replace a record.
	 *
	 * @param array $app Record; `slug` is the key.
	 */
	public function save( array $app ) {
		$app  = array_merge( self::defaults(), $app );
		$apps = $this->all();

		if ( ! $app['created'] ) {
			$app['created'] = time();
		}

		$app['modified']      = time();
		$apps[ $app['slug'] ] = $app;
		$this->write( $apps );
	}

	/**
	 * Remove a record and every file it owns.
	 *
	 * @param string $slug App slug.
	 */
	public function delete( $slug ) {
		$apps = $this->all();

		unset( $apps[ $slug ] );
		$this->write( $apps );

		Filesystem::delete( $this->app_dir( $slug ) );
	}

	/**
	 * Persist. Autoloaded: the front end reads it on any page with an embed.
	 *
	 * @param array $apps All records.
	 */
	private function write( array $apps ) {
		update_option( self::OPTION, $apps, true );
		$this->apps = null;
	}

	/**
	 * Turn a name into a slug that is safe as a directory, a shortcode
	 * attribute, an object key and part of a script handle.
	 *
	 * @param string $text Source text.
	 * @return string
	 */
	public static function sanitize_slug( $text ) {
		$slug = sanitize_title( $text );
		$slug = preg_replace( '/[^a-z0-9-]/', '', $slug );

		return substr( trim( $slug, '-' ), 0, 60 );
	}

	/**
	 * A slug based on $base that no app has yet.
	 *
	 * @param string $base Desired slug.
	 * @return string
	 */
	public function unique_slug( $base ) {
		$slug = $base;
		$n    = 2;

		while ( $this->get( $slug ) ) {
			$slug = $base . '-' . $n++;
		}

		return $slug;
	}

	/**
	 * The ID the app mounts to: the one set on the app, else the one its
	 * index.html used (which is what its code targets), else a generated one.
	 *
	 * @param array $app Record.
	 * @return string
	 */
	public static function mount_id( array $app ) {
		if ( '' !== $app['mount_id'] ) {
			return $app['mount_id'];
		}

		if ( '' !== $app['detected_mount_id'] ) {
			return $app['detected_mount_id'];
		}

		return 'bzem-' . $app['slug'];
	}

	/**
	 * Machine status: 'ready', 'needs-entry' or 'no-build'.
	 *
	 * @param array $app Record.
	 * @return string
	 */
	public static function status( array $app ) {
		if ( '' === $app['build'] ) {
			return 'no-build';
		}

		return empty( $app['scripts'] ) ? 'needs-entry' : 'ready';
	}

	/**
	 * Whether the app is switched on. Separate from status(), which is about
	 * whether the build can be embedded at all.
	 *
	 * @param array $app Record.
	 * @return bool
	 */
	public static function is_active( array $app ) {
		return ! empty( $app['active'] );
	}

	/**
	 * When the record last changed, for records saved before `modified`
	 * existed too.
	 *
	 * @param array $app Record.
	 * @return int Timestamp, or 0.
	 */
	public static function modified( array $app ) {
		return (int) max( $app['modified'], $app['uploaded'], $app['created'] );
	}

	/**
	 * Human label for a status.
	 *
	 * @param string $status From status().
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			'ready'       => __( 'Ready', 'banzaiembed' ),
			'needs-entry' => __( 'Needs entry files', 'banzaiembed' ),
			'no-build'    => __( 'No build uploaded', 'banzaiembed' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Human label for a framework.
	 *
	 * @param string $framework One of FRAMEWORKS.
	 * @return string
	 */
	public static function framework_label( $framework ) {
		$labels = array(
			'vue'   => __( 'Vue 3', 'banzaiembed' ),
			'react' => __( 'React 18+', 'banzaiembed' ),
			'other' => __( 'Other / Vanilla', 'banzaiembed' ),
		);

		return isset( $labels[ $framework ] ) ? $labels[ $framework ] : $framework;
	}

	/**
	 * The shortcode that embeds an app.
	 *
	 * @param array $app Record.
	 * @return string
	 */
	public static function shortcode( array $app ) {
		return sprintf( '[%s app="%s"]', Shortcode::TAG, $app['slug'] );
	}

	/**
	 * uploads/banzaiembed, as a path.
	 *
	 * @return string
	 */
	public function base_dir() {
		$uploads = wp_upload_dir( null, false );

		return trailingslashit( $uploads['basedir'] ) . self::DIR;
	}

	/**
	 * uploads/banzaiembed, as a URL.
	 *
	 * @return string
	 */
	public function base_url() {
		$uploads = wp_upload_dir( null, false );

		return set_url_scheme( trailingslashit( $uploads['baseurl'] ) . self::DIR );
	}

	/**
	 * Directory holding every build of an app.
	 *
	 * @param string $slug App slug.
	 * @return string
	 */
	public function app_dir( $slug ) {
		return $this->base_dir() . '/' . $slug;
	}

	/**
	 * Directory of one build.
	 *
	 * @param string $slug  App slug.
	 * @param string $build Build ID.
	 * @return string
	 */
	public function build_dir( $slug, $build ) {
		return $this->app_dir( $slug ) . '/' . $build;
	}

	/**
	 * URL of one build, with a trailing slash.
	 *
	 * @param string $slug  App slug.
	 * @param string $build Build ID.
	 * @return string
	 */
	public function build_url( $slug, $build ) {
		return $this->base_url() . '/' . rawurlencode( $slug ) . '/' . rawurlencode( $build ) . '/';
	}

	/**
	 * How a build's own files should refer to the build folder once
	 * Path_Rewriter has relocated them: root-relative, so the same files keep
	 * working over http and https and on any domain pointing at this site.
	 * When uploads are served from another host (a CDN or offload plugin),
	 * only the full URL works.
	 *
	 * @param string $slug  App slug.
	 * @param string $build Build ID.
	 * @return string With a trailing slash.
	 */
	public function build_ref( $slug, $build ) {
		$url = $this->build_url( $slug, $build );

		if ( wp_parse_url( $url, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
			return $url;
		}

		return (string) wp_parse_url( $url, PHP_URL_PATH );
	}

	/**
	 * URL for an entry path stored on an app: absolute URLs pass through,
	 * anything else is relative to the current build.
	 *
	 * @param array  $app  Record.
	 * @param string $path Stored path.
	 * @return string
	 */
	public function asset_url( array $app, $path ) {
		if ( preg_match( '#^https?://#i', $path ) ) {
			return $path;
		}

		$parts = array_map( 'rawurlencode', explode( '/', $path ) );

		return $this->build_url( $app['slug'], $app['build'] ) . implode( '/', $parts );
	}

	/**
	 * Every JS and CSS file in the current build, relative paths, sorted.
	 * Feeds the manual entry picker.
	 *
	 * @param array $app Record.
	 * @return array{scripts: string[], styles: string[]}
	 */
	public function build_assets( array $app ) {
		$out = array(
			'scripts' => array(),
			'styles'  => array(),
		);

		if ( '' === $app['build'] ) {
			return $out;
		}

		foreach ( Filesystem::list_files( $this->build_dir( $app['slug'], $app['build'] ) ) as $file ) {
			if ( preg_match( '/\.(m?js|cjs)$/i', $file ) ) {
				$out['scripts'][] = $file;
			} elseif ( preg_match( '/\.css$/i', $file ) ) {
				$out['styles'][] = $file;
			}
		}

		sort( $out['scripts'] );
		sort( $out['styles'] );

		return $out;
	}
}
