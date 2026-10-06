<?php
/**
 * API proxy.
 *
 * @package BanzaiEmbed
 */

namespace BanzaiEmbed;

defined( 'ABSPATH' ) || exit;

/**
 * Forwards an app's same-origin API calls to the server that really answers
 * them.
 *
 * An app built for Netlify, Vercel or its own dev server calls its backend
 * as `fetch('/api/judge')` — a path on whatever site it is served from. On
 * WordPress that path is a 404. With a rule `/api` → `https://my-app.netlify.app/api`,
 * the request is forwarded there and the answer passed back, so the app's
 * code doesn't change, and since the browser only ever talks to its own
 * site there is no CORS to set up.
 *
 * A rule matches its path and everything below it, by whole segments:
 * `/api` takes /api and /api/judge but not /apiary. What follows the rule's
 * path, and the query string, are appended to the target.
 *
 * Runs on do_parse_request, before WordPress resolves the request, so a
 * forwarded call costs no query. The rule paths it may take are guarded at
 * save: nothing under wp-admin, wp-content, wp-includes or the REST API, and
 * never a path that is already a page.
 *
 * What is forwarded is what an API needs and nothing that belongs to
 * WordPress: the method, body, content type, Accept, Authorization and the
 * app's own X- headers go; cookies (the visitor's WordPress login) and
 * X-WP-Nonce never do. Set-Cookie never comes back, for the same reason.
 */
final class Api_Proxy {

	/**
	 * Most rules one app may have.
	 */
	const MAX_RULES = 20;

	/**
	 * Request headers forwarded, beyond the app's own X- headers.
	 */
	const REQUEST_HEADERS = array( 'accept', 'accept-language', 'authorization', 'content-type', 'if-match', 'if-none-match', 'if-modified-since', 'if-unmodified-since', 'range', 'user-agent' );

	/**
	 * Request headers never forwarded, even though they start with X-.
	 */
	const PRIVATE_HEADERS = array( 'x-wp-nonce', 'x-forwarded-for', 'x-forwarded-host', 'x-forwarded-proto', 'x-real-ip' );

	/**
	 * Response headers passed back to the browser.
	 */
	const RESPONSE_HEADERS = array( 'cache-control', 'content-disposition', 'content-language', 'content-range', 'content-type', 'etag', 'expires', 'last-modified', 'location', 'retry-after', 'vary', 'www-authenticate' );

	/**
	 * Methods forwarded.
	 */
	const METHODS = array( 'GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS' );

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
		add_filter( 'do_parse_request', array( $this, 'maybe_forward' ), 1 );
		// Between the Data Bridge (10) and Custom CSS & JS (20).
		add_action( 'bzem/edit_cards', array( $this, 'render_card' ), 15 );
		add_filter( 'bzem/save_app', array( $this, 'save' ) );
	}

	/**
	 * An app's rules, well-typed whatever was stored.
	 *
	 * @param array $app Record.
	 * @return array{rules: array<int, array{path: string, target: string}>}
	 */
	public static function config( array $app ) {
		$proxy = isset( $app['proxy'] ) && is_array( $app['proxy'] ) ? $app['proxy'] : array();
		$rules = array();

		foreach ( isset( $proxy['rules'] ) ? (array) $proxy['rules'] : array() as $rule ) {
			if ( is_array( $rule ) && ! empty( $rule['path'] ) && ! empty( $rule['target'] ) ) {
				$rules[] = array(
					'path'   => (string) $rule['path'],
					'target' => (string) $rule['target'],
				);
			}
		}

		return array( 'rules' => $rules );
	}

	/**
	 * Forward the request if a rule takes its path.
	 *
	 * @param bool $parse Whether WordPress should parse the request.
	 * @return bool
	 */
	public function maybe_forward( $parse ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- forwarded as-is after match() and target() validate it.
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );

		if ( '' === $path ) {
			return $parse;
		}

		$match = $this->match( $path );

		if ( ! $match ) {
			return $parse;
		}

		$query = (string) wp_parse_url( $uri, PHP_URL_QUERY );
		$url   = self::target( $match['target'], $match['rest'], $query );

		if ( null === $url ) {
			$this->send_error( 400, __( 'Bad request path.', 'banzaiembed' ) );
		}

		$this->forward( $url, $match );
		exit;
	}

	/**
	 * The rule whose path is the longest prefix of $path, by whole segments,
	 * among enabled apps.
	 *
	 * @param string $path Raw request path from the domain root.
	 * @return array{slug: string, path: string, target: string, rest: string}|null
	 */
	public function match( $path ) {
		$best = null;

		foreach ( $this->apps->all() as $app ) {
			if ( ! App_Manager::is_active( $app ) || 'ready' !== App_Manager::status( $app ) ) {
				continue;
			}

			foreach ( self::config( $app )['rules'] as $rule ) {
				$prefix = $rule['path'];

				if ( $path !== $prefix && 0 !== strpos( $path, $prefix . '/' ) ) {
					continue;
				}

				if ( ! $best || strlen( $prefix ) > strlen( $best['path'] ) ) {
					$best = array(
						'slug'   => $app['slug'],
						'path'   => $prefix,
						'target' => $rule['target'],
						'rest'   => (string) substr( $path, strlen( $prefix ) ),
					);
				}
			}
		}

		return $best;
	}

	/**
	 * The URL a request is forwarded to, or null if what follows the rule's
	 * path could climb out of the target's path (`/api/../admin`, encoded
	 * or not).
	 *
	 * @param string $target Rule target, no trailing slash.
	 * @param string $rest   Raw path after the rule's path: '' or '/…'.
	 * @param string $query  Raw query string, or ''.
	 * @return string|null
	 */
	public static function target( $target, $rest, $query ) {
		foreach ( explode( '/', $rest ) as $segment ) {
			$decoded = rawurldecode( $segment );

			if ( '.' === $decoded || '..' === $decoded || false !== strpbrk( $decoded, "\\\0" ) ) {
				return null;
			}
		}

		$url = $target . $rest . ( '' !== $query ? '?' . $query : '' );

		// Belt and braces: whatever was appended, the request stays on the
		// target's host.
		if ( wp_parse_url( $url, PHP_URL_HOST ) !== wp_parse_url( $target, PHP_URL_HOST ) ) {
			return null;
		}

		return $url;
	}

	/**
	 * Make the request upstream and relay the answer.
	 *
	 * @param string $url   Where to.
	 * @param array  $match From match().
	 */
	private function forward( $url, array $match ) {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';

		if ( ! in_array( $method, self::METHODS, true ) ) {
			$this->send_error( 405, __( 'Method not allowed.', 'banzaiembed' ) );
		}

		/**
		 * Filter the largest request body forwarded, in bytes.
		 *
		 * @param int   $bytes Default 10 MB.
		 * @param array $match { slug, path, target, rest }.
		 */
		$max    = (int) apply_filters( 'bzem/proxy_max_body', 10 * MB_IN_BYTES, $match );
		$length = isset( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;

		if ( $length > $max ) {
			$this->send_error( 413, __( 'Request body too large.', 'banzaiembed' ) );
		}

		$body = in_array( $method, array( 'GET', 'HEAD' ), true ) ? null : file_get_contents( 'php://input', false, null, 0, $max + 1 );

		if ( is_string( $body ) && strlen( $body ) > $max ) {
			$this->send_error( 413, __( 'Request body too large.', 'banzaiembed' ) );
		}

		$headers = $this->request_headers();
		$args    = array(
			'method'      => $method,
			'headers'     => $headers,
			'body'        => $body,
			// Redirects go back to the app, which follows them through the proxy.
			'redirection' => 0,
			/**
			 * Filter how long to wait for the target, in seconds.
			 *
			 * @param int   $seconds Default 30.
			 * @param array $match   { slug, path, target, rest }.
			 */
			'timeout'     => (int) apply_filters( 'bzem/proxy_timeout', 30, $match ),
			'user-agent'  => isset( $headers['user-agent'] ) ? $headers['user-agent'] : 'BanzaiEmbed/' . BZEM_VERSION,
		);

		/**
		 * Filter the request about to be forwarded — to add an API key the
		 * browser must never see, say. Return a WP_Error to refuse it.
		 *
		 * @param array  $args  wp_remote_request() arguments.
		 * @param string $url   Target URL.
		 * @param array  $match { slug, path, target, rest }.
		 */
		$args = apply_filters( 'bzem/proxy_request', $args, $url, $match );

		if ( is_wp_error( $args ) ) {
			$this->send_error( 403, $args->get_error_message() );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->send_error( 502, __( 'The API could not be reached.', 'banzaiembed' ) );
		}

		$this->relay( $response, $match, 'HEAD' === $method );
	}

	/**
	 * Headers to send upstream: the allowlisted ones, the app's own X-
	 * headers, and who the request was really from.
	 *
	 * @return array<string,string>
	 */
	private function request_headers() {
		$out = array();

		foreach ( $_SERVER as $key => $value ) {
			if ( ! is_string( $value ) ) {
				continue;
			}

			if ( 0 === strpos( $key, 'HTTP_' ) ) {
				$name = strtolower( str_replace( '_', '-', substr( $key, 5 ) ) );
			} elseif ( 'CONTENT_TYPE' === $key ) {
				$name = 'content-type';
			} else {
				continue;
			}

			$forward = in_array( $name, self::REQUEST_HEADERS, true )
				|| ( 0 === strpos( $name, 'x-' ) && ! in_array( $name, self::PRIVATE_HEADERS, true ) );

			if ( $forward ) {
				// Header values travel as given; only line breaks are refused.
				$out[ $name ] = str_replace( array( "\r", "\n" ), '', wp_unslash( $value ) );
			}
		}

		// Apache CGI/FastCGI hides Authorization from HTTP_*.
		if ( ! isset( $out['authorization'] ) && isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) && is_string( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			$out['authorization'] = str_replace( array( "\r", "\n" ), '', wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
		}

		$out['x-forwarded-for']   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$out['x-forwarded-host']  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$out['x-forwarded-proto'] = is_ssl() ? 'https' : 'http';

		return $out;
	}

	/**
	 * Send the upstream response to the browser.
	 *
	 * @param array $response wp_remote_request() result.
	 * @param array $match    From match().
	 * @param bool  $head     Whether to leave the body out.
	 */
	private function relay( array $response, array $match, $head ) {
		$code    = (int) wp_remote_retrieve_response_code( $response );
		$headers = wp_remote_retrieve_headers( $response );

		http_response_code( $code ? $code : 502 );

		foreach ( self::RESPONSE_HEADERS as $name ) {
			$value = isset( $headers[ $name ] ) ? $headers[ $name ] : '';

			// Requests_Utility_CaseInsensitiveDictionary joins repeats with ","
			// in recent WordPress, but an array is possible.
			$value = is_array( $value ) ? implode( ', ', $value ) : (string) $value;

			if ( '' === $value ) {
				continue;
			}

			// A redirect within the API stays within the proxy.
			if ( 'location' === $name && ( $value === $match['target'] || 1 === preg_match( '#^' . preg_quote( $match['target'], '#' ) . '[/?]#', $value ) ) ) {
				$value = $match['path'] . substr( $value, strlen( $match['target'] ) );
			}

			header( $name . ': ' . str_replace( array( "\r", "\n" ), '', $value ) );
		}

		// An API answer is per-request unless the API says otherwise. Page
		// caches that honour the convention skip it too.
		if ( empty( $headers['cache-control'] ) ) {
			header( 'Cache-Control: no-store' );

			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
		}

		header( 'X-Content-Type-Options: nosniff' );

		if ( ! $head ) {
			echo wp_remote_retrieve_body( $response ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- relayed API response, served with the API's own content type.
		}
	}

	/**
	 * End the request with a JSON error, as an API client expects.
	 *
	 * @param int    $code    HTTP status.
	 * @param string $message Explanation.
	 */
	private function send_error( $code, $message ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		nocache_headers();
		wp_send_json( array( 'error' => $message ), $code );
	}

	/**
	 * Paths a rule may never take: WordPress's own.
	 *
	 * @return string[]
	 */
	private static function reserved() {
		$home = untrailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );
		$out  = array();

		foreach ( array( 'wp-admin', 'wp-content', 'wp-includes', 'wp-json', rest_get_url_prefix(), 'wp-login.php', 'wp-cron.php', 'wp-signup.php', 'wp-activate.php', 'xmlrpc.php' ) as $path ) {
			$out[] = $home . '/' . trim( $path, '/' );
		}

		return array_unique( $out );
	}

	/**
	 * A rule path as typed, normalised to `/a/b`, or a reason it can't be one.
	 *
	 * @param string $path Typed path.
	 * @return string|\WP_Error
	 */
	private static function clean_path( $path ) {
		$path = '/' . trim( trim( $path ), '/' );

		if ( '/' === $path ) {
			return new \WP_Error( 'root', __( 'the site root can\'t be forwarded', 'banzaiembed' ) );
		}

		if ( ! preg_match( '#^(/[A-Za-z0-9._~!$&\'()*+,;=:@-]+)+$#', $path ) ) {
			return new \WP_Error( 'chars', __( 'use letters, numbers and - _ . ~ only, e.g. /api', 'banzaiembed' ) );
		}

		foreach ( explode( '/', ltrim( $path, '/' ) ) as $segment ) {
			if ( '.' === $segment || '..' === $segment ) {
				return new \WP_Error( 'dots', __( '. and .. are not allowed', 'banzaiembed' ) );
			}
		}

		foreach ( self::reserved() as $reserved ) {
			if ( 0 === strcasecmp( $path, $reserved ) || 0 === stripos( $path, $reserved . '/' ) || 0 === stripos( $reserved, $path . '/' ) ) {
				return new \WP_Error( 'reserved', __( 'that path belongs to WordPress', 'banzaiembed' ) );
			}
		}

		$home = untrailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );

		if ( '' !== $home && 0 !== strpos( $path . '/', $home . '/' ) ) {
			/* translators: %s: the site's path, e.g. "/blog". */
			return new \WP_Error( 'outside', sprintf( __( 'WordPress only receives requests below %s', 'banzaiembed' ), $home ) );
		}

		$page = get_page_by_path( substr( $path, strlen( $home ) + 1 ) );

		if ( $page && 'trash' !== $page->post_status ) {
			/* translators: %s: page title. */
			return new \WP_Error( 'page', sprintf( __( 'the page "%s" is at that path', 'banzaiembed' ), get_the_title( $page ) ) );
		}

		return $path;
	}

	/**
	 * A rule target as typed, normalised to `https://host/path` with no
	 * trailing slash, or a reason it can't be one.
	 *
	 * @param string $target Typed URL.
	 * @return string|\WP_Error
	 */
	private static function clean_target( $target ) {
		$target = trim( $target );
		$parts  = wp_parse_url( $target );

		if ( ! $parts || empty( $parts['host'] ) || ! in_array( strtolower( isset( $parts['scheme'] ) ? $parts['scheme'] : '' ), array( 'http', 'https' ), true ) ) {
			return new \WP_Error( 'url', __( 'forward to a full http:// or https:// URL', 'banzaiembed' ) );
		}

		if ( isset( $parts['query'] ) || isset( $parts['fragment'] ) || isset( $parts['user'] ) ) {
			return new \WP_Error( 'url', __( 'leave out ?query, #fragment and user:password@', 'banzaiembed' ) );
		}

		if ( self::origin( $target ) === self::origin( home_url() ) ) {
			return new \WP_Error( 'self', __( 'that URL is this site', 'banzaiembed' ) );
		}

		return untrailingslashit( esc_url_raw( $target, array( 'http', 'https' ) ) );
	}

	/**
	 * host:port of a URL — what decides whether a target would send the
	 * request back to this site. With no port given, just the host, so
	 * http:// and https:// on the site's own domain both count.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function origin( $url ) {
		$parts = wp_parse_url( $url );
		$host  = strtolower( isset( $parts['host'] ) ? $parts['host'] : '' );

		return isset( $parts['port'] ) ? $host . ':' . (int) $parts['port'] : $host;
	}

	/**
	 * Rule paths already taken by other apps.
	 *
	 * @param string $slug This app's slug.
	 * @return array<string,string> path => app name.
	 */
	private function taken( $slug ) {
		$taken = array();

		foreach ( $this->apps->all() as $other ) {
			if ( $other['slug'] !== $slug ) {
				foreach ( self::config( $other )['rules'] as $rule ) {
					$taken[ $rule['path'] ] = $other['name'];
				}
			}
		}

		return $taken;
	}

	/**
	 * Print the API proxy card.
	 *
	 * @param array $app Record.
	 */
	public function render_card( $app ) {
		$config = self::config( $app );

		include BZEM_PLUGIN_PATH . 'templates/admin-api-proxy.php';
	}

	/**
	 * Read the card. Runs inside Admin::handle_save(), after its capability
	 * and nonce checks.
	 *
	 * @param array $app Record.
	 * @return array
	 */
	public function save( $app ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in Admin::handle_save().
		// Not on the form: keep what was saved.
		if ( ! isset( $_POST['bzem_proxy'] ) ) {
			return $app;
		}

		$rows = isset( $_POST['proxy_rules'] ) && is_array( $_POST['proxy_rules'] ) ? wp_unslash( $_POST['proxy_rules'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is validated below.
		// phpcs:enable

		$taken    = $this->taken( $app['slug'] );
		$rules    = array();
		$problems = array();

		foreach ( $rows as $row ) {
			$path   = isset( $row['path'] ) && is_string( $row['path'] ) ? sanitize_text_field( $row['path'] ) : '';
			// Validated, then passed through esc_url_raw(), by clean_target().
			$target = isset( $row['target'] ) && is_string( $row['target'] ) ? trim( wp_check_invalid_utf8( $row['target'] ) ) : '';

			if ( '' === $path && '' === $target ) {
				continue;
			}

			$clean_path   = self::clean_path( $path );
			$clean_target = self::clean_target( $target );

			if ( is_wp_error( $clean_path ) ) {
				$problems[] = $path . ': ' . $clean_path->get_error_message();
			} elseif ( is_wp_error( $clean_target ) ) {
				$problems[] = $path . ': ' . $clean_target->get_error_message();
			} elseif ( isset( $taken[ $clean_path ] ) ) {
				/* translators: %s: another app's name. */
				$problems[] = $clean_path . ': ' . sprintf( __( 'already forwarded by "%s"', 'banzaiembed' ), $taken[ $clean_path ] );
			} elseif ( isset( $rules[ $clean_path ] ) ) {
				$problems[] = $clean_path . ': ' . __( 'listed twice', 'banzaiembed' );
			} elseif ( count( $rules ) >= self::MAX_RULES ) {
				/* translators: %d: maximum number of rules. */
				$problems[] = $clean_path . ': ' . sprintf( __( 'an app can have up to %d rules', 'banzaiembed' ), self::MAX_RULES );
			} else {
				$rules[ $clean_path ] = array(
					'path'   => $clean_path,
					'target' => $clean_target,
				);
			}
		}

		$app['proxy'] = array( 'rules' => array_values( $rules ) );

		if ( $problems ) {
			Admin::notice( 'warning', __( 'Some API proxy rules were not saved:', 'banzaiembed' ), $problems );
		}

		return $app;
	}
}
