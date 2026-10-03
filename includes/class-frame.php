<?php
/**
 * Isolated display: an app in its own document.
 *
 * @package BanzaiEmbed
 */

namespace BanzaiEmbed;

defined( 'ABSPATH' ) || exit;

/**
 * Serves a framed app's own document — the page an app built for its own
 * host expects to be in.
 *
 * Inline, an app shares the page with the theme, so its `body`, `h1` and `p`
 * rules restyle the site (and the theme's restyle it), and its router sees
 * /games/play/x when it was written for /play/x. In a frame it gets its own
 * document: its CSS stays in, the theme's stays out, and the frame's address
 * is at the site root — /play/x?bzem_frame=games.17 — so a router built for
 * `/` matches its routes unchanged.
 *
 * The frame is identified by the `bzem_frame=slug.post` query arg: the app,
 * and the page it is on (for the Data Bridge's post values). frame-client.js
 * keeps it on every URL the app's router pushes, so a reload inside the
 * frame — some apps call location.reload() — loads the app again. A full
 * navigation inside the frame (a plain link, location.href) loses it, so
 * such a request is recognised by its Referer, the frame's own URL, when
 * the browser says it is loading into an iframe (Sec-Fetch-Dest).
 *
 * Served on do_parse_request, before WordPress resolves the path, which
 * would usually be a 404 or the front page.
 */
final class Frame {

	/**
	 * The query arg that marks a frame request.
	 */
	const ARG = 'bzem_frame';

	/**
	 * @var App_Manager
	 */
	private $apps;

	/**
	 * @var Embed
	 */
	private $embed;

	/**
	 * Constructor.
	 *
	 * @param App_Manager $apps  App store.
	 * @param Embed       $embed Asset loading.
	 */
	public function __construct( App_Manager $apps, Embed $embed ) {
		$this->apps  = $apps;
		$this->embed = $embed;
	}

	/**
	 * Hook in.
	 */
	public function register() {
		// After the API proxy (1): an API path is never a frame.
		add_filter( 'do_parse_request', array( $this, 'maybe_serve' ), 5 );
	}

	/**
	 * Where a framed app's paths start: the base it was built for, else the
	 * site's own root.
	 *
	 * @param array $app Record.
	 * @return string With leading and trailing slashes.
	 */
	public static function root( array $app ) {
		if ( '' !== $app['base'] ) {
			return $app['base'];
		}

		return trailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
	}

	/**
	 * The frame's URL: the app's route under its root, marked.
	 *
	 * @param array  $app   Record.
	 * @param string $route Path below the root, e.g. 'play/x', or ''.
	 * @param int    $post  ID of the post the app is on, or 0.
	 * @return string Root-relative URL.
	 */
	public static function src( array $app, $route, $post ) {
		return self::root( $app ) . ltrim( $route, '/' ) . '?' . self::ARG . '=' . rawurlencode( $app['slug'] . '.' . (int) $post );
	}

	/**
	 * Serve the frame document if this request is for one.
	 *
	 * @param bool $parse Whether WordPress should parse the request.
	 * @return bool
	 */
	public function maybe_serve( $parse ) {
		$marker = $this->marker();

		if ( null === $marker ) {
			return $parse;
		}

		$app = $this->apps->get( $marker['slug'] );

		if ( ! $app || ! App_Manager::is_active( $app ) || 'ready' !== App_Manager::status( $app ) || ! App_Manager::is_framed( $app ) ) {
			return $parse;
		}

		$this->serve( $app, $marker['post'] );
		exit;
	}

	/**
	 * The frame marker on this request, from its query string or, for a
	 * navigation inside a frame that dropped it, from its Referer.
	 *
	 * @return array{slug: string, post: int}|null
	 */
	private function marker() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a public, read-only page view.
		$raw = isset( $_GET[ self::ARG ] ) && is_string( $_GET[ self::ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::ARG ] ) ) : '';

		if ( '' === $raw && $this->is_frame_navigation() ) {
			wp_parse_str( (string) wp_parse_url( (string) wp_get_raw_referer(), PHP_URL_QUERY ), $query );
			$raw = isset( $query[ self::ARG ] ) && is_string( $query[ self::ARG ] ) ? sanitize_text_field( $query[ self::ARG ] ) : '';
		}

		if ( ! preg_match( '/^([a-z0-9-]+)\.(\d+)$/', $raw, $m ) ) {
			return null;
		}

		return array(
			'slug' => $m[1],
			'post' => (int) $m[2],
		);
	}

	/**
	 * Whether the browser says this is a same-site navigation inside an
	 * iframe, coming from one of this site's pages. Browsers too old to send
	 * Sec-Fetch-Dest never match, so a frame they navigate shows whatever
	 * WordPress has at that path.
	 *
	 * @return bool
	 */
	private function is_frame_navigation() {
		$dest    = isset( $_SERVER['HTTP_SEC_FETCH_DEST'] ) ? sanitize_key( wp_unslash( $_SERVER['HTTP_SEC_FETCH_DEST'] ) ) : '';
		$referer = (string) wp_get_raw_referer();

		return 'iframe' === $dest && '' !== $referer && wp_parse_url( $referer, PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST );
	}

	/**
	 * Print the app's document.
	 *
	 * @param array $app  Record.
	 * @param int   $post ID of the post the app is on, or 0.
	 */
	private function serve( array $app, $post ) {
		$this->set_post( $post );

		$root   = self::root( $app );
		$marker = $app['slug'] . '.' . (int) $post;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared and trimmed only.
		$path  = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );
		$route = 0 === strpos( $path, $root ) ? (string) substr( $path, strlen( $root ) ) : '';

		// Inside the frame, the app's paths start at its root, whatever the
		// page around it is called.
		add_filter(
			'bzem/app_data',
			static function ( $data, $for ) use ( $app, $root, $route ) {
				if ( $for['slug'] === $app['slug'] ) {
					$data['basePath'] = untrailingslashit( $root );
					$data['route']    = $route;
					$data['framed']   = true;
				}

				return $data;
			},
			100,
			2
		);

		$styles_before  = wp_styles()->queue;
		$scripts_before = wp_scripts()->queue;
		$handle         = $this->embed->enqueue( $app );
		$mount          = App_Manager::mount_id( $app );

		wp_add_inline_script( $handle, sprintf( 'window.banzaiEmbed[%s].mounts.push(%s);', wp_json_encode( $app['slug'] ), wp_json_encode( $mount ) ), 'before' );

		// Only what this app brought — nothing another plugin queued early.
		$styles  = array_values( array_diff( wp_styles()->queue, $styles_before ) );
		$scripts = array_values( array_diff( wp_scripts()->queue, $scripts_before ) );

		status_header( 200 );
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		// Framed by this site only, even where a security plugin denies framing.
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( "Content-Security-Policy: frame-ancestors 'self'" );
		// The page the frame is on is what search engines should index.
		header( 'X-Robots-Tag: noindex' );

		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php echo esc_attr( get_option( 'blog_charset' ) ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $app['name'] ); ?></title>
<meta name="bzem-frame" content="<?php echo esc_attr( $marker ); ?>">
		<?php
		wp_print_script_tag(
			array(
				'src' => BZEM_PLUGIN_URL . 'assets/js/frame-client.js?ver=' . rawurlencode( BZEM_VERSION ),
				'id'  => 'bzem-frame-client-js',
			)
		);
		wp_print_styles( $styles );
		?>
</head>
<body>
<div id="<?php echo esc_attr( $mount ); ?>" class="<?php echo esc_attr( 'bzem-app bzem-app-' . $app['slug'] ); ?>" data-bzem-app="<?php echo esc_attr( $app['slug'] ); ?>"></div>
		<?php wp_print_scripts( $scripts ); ?>
</body>
</html>
		<?php
	}

	/**
	 * Make the post the app is on the queried object, as it is on the page,
	 * so the Data Bridge's post values match. Only a post this visitor may
	 * read: a private post needs its capability, a password-protected one
	 * its password.
	 *
	 * @param int $post_id Post ID, or 0.
	 */
	private function set_post( $post_id ) {
		global $wp_query;

		$post = $post_id ? get_post( $post_id ) : null;

		if ( ! $post || post_password_required( $post ) ) {
			return;
		}

		if ( ! is_post_publicly_viewable( $post ) && ! current_user_can( 'read_post', $post->ID ) ) {
			return;
		}

		$wp_query->query(
			array(
				'p'         => $post->ID,
				'post_type' => $post->post_type,
			)
		);
	}
}
