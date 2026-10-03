<?php
/**
 * Front-end rendering.
 *
 * @package BanzaiEmbed
 */

namespace BanzaiEmbed;

defined( 'ABSPATH' ) || exit;

/**
 * Renders an app's mount point and loads its assets — the one implementation
 * behind both the shortcode and the block, so the two cannot drift.
 *
 * Assets are enqueued from render(), so they load wherever the embed ends up
 * being rendered: post content, widgets, page-builder HTML, block templates.
 * Rendering usually happens after wp_head, though, which would push the app's
 * CSS into the footer and flash unstyled content. prescan() covers the common
 * case by finding embeds in the queried post's content before wp_head and
 * enqueueing their styles early.
 *
 * Each app gets a `window.banzaiEmbed[slug]` object, printed before its first
 * script, that tells the app where it was mounted and where its files live:
 *
 *     { slug, baseUrl, mountId, mounts: [ 'app', 'app-2' ] }
 *
 * `mounts` lists every instance on the page, which is how an app that wants
 * to support more than one instance finds them all.
 */
final class Embed {

	/**
	 * @var App_Manager
	 */
	private $apps;

	/**
	 * Script handle => 'module' | 'classic', for every handle we register.
	 *
	 * @var array<string,string>
	 */
	private $script_types = array();

	/**
	 * Slugs whose assets are already enqueued this request.
	 *
	 * @var array<string,string> slug => handle of the first script.
	 */
	private $enqueued = array();

	/**
	 * Mount IDs already printed this request.
	 *
	 * @var array<string,bool>
	 */
	private $used_ids = array();

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
		add_action( 'wp_enqueue_scripts', array( $this, 'prescan' ) );
		add_filter( 'script_loader_tag', array( $this, 'script_tag' ), 10, 2 );
	}

	/**
	 * Enqueue apps embedded in the queried post before wp_head runs.
	 */
	public function prescan() {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();

		if ( ! $post instanceof \WP_Post || '' === $post->post_content ) {
			return;
		}

		foreach ( self::find_slugs( $post->post_content ) as $slug ) {
			$app = $this->apps->get( $slug );

			if ( $app && 'ready' === App_Manager::status( $app ) && App_Manager::is_active( $app ) ) {
				$this->enqueue( $app );
			}
		}
	}

	/**
	 * App slugs referenced by shortcodes and blocks in some content.
	 *
	 * @param string $content Post content.
	 * @return string[]
	 */
	public static function find_slugs( $content ) {
		$slugs = array();

		if ( false !== strpos( $content, '[' . Shortcode::TAG ) ) {
			preg_match_all( '/' . get_shortcode_regex( array( Shortcode::TAG ) ) . '/', $content, $matches );

			foreach ( $matches[3] as $raw ) {
				$atts = shortcode_parse_atts( $raw );

				if ( is_array( $atts ) && ! empty( $atts['app'] ) ) {
					$slugs[] = App_Manager::sanitize_slug( $atts['app'] );
				}
			}
		}

		if ( function_exists( 'has_block' ) && has_block( Block::NAME, $content ) ) {
			$slugs = array_merge( $slugs, self::block_slugs( parse_blocks( $content ) ) );
		}

		return array_values( array_unique( array_filter( $slugs ) ) );
	}

	/**
	 * App slugs in a parsed block tree, nested blocks included.
	 *
	 * @param array $blocks From parse_blocks().
	 * @return string[]
	 */
	private static function block_slugs( array $blocks ) {
		$slugs = array();

		foreach ( $blocks as $block ) {
			if ( Block::NAME === $block['blockName'] && ! empty( $block['attrs']['app'] ) ) {
				$slugs[] = App_Manager::sanitize_slug( $block['attrs']['app'] );
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$slugs = array_merge( $slugs, self::block_slugs( $block['innerBlocks'] ) );
			}
		}

		return $slugs;
	}

	/**
	 * Render one embed.
	 *
	 * @param string $slug App slug.
	 * @param array  $args { id?: string, class?: string, style?: string }.
	 * @return string HTML.
	 */
	public function render( $slug, array $args = array() ) {
		$slug = App_Manager::sanitize_slug( (string) $slug );
		$app  = '' === $slug ? null : $this->apps->get( $slug );

		if ( ! $app ) {
			/* translators: %s: app slug. */
			return $this->problem( sprintf( __( 'No app with the slug "%s" exists.', 'banzaiembed' ), $slug ) );
		}

		if ( 'ready' !== App_Manager::status( $app ) ) {
			/* translators: 1: app name, 2: status such as "No build uploaded". */
			return $this->problem( sprintf( __( '"%1$s" is not ready to embed: %2$s.', 'banzaiembed' ), $app['name'], App_Manager::status_label( App_Manager::status( $app ) ) ) );
		}

		if ( ! App_Manager::is_active( $app ) ) {
			/* translators: %s: app name. */
			return $this->problem( sprintf( __( '"%s" is inactive, so visitors see nothing here. Activate it under BanzaiEmbed → All Apps.', 'banzaiembed' ), $app['name'] ) );
		}

		$id = isset( $args['id'] ) ? self::sanitize_id( $args['id'] ) : '';
		$id = $this->unique_id( '' !== $id ? $id : App_Manager::mount_id( $app ) );

		$classes = array( 'bzem-app', 'bzem-app-' . $slug );

		foreach ( preg_split( '/\s+/', isset( $args['class'] ) ? (string) $args['class'] : '' ) as $class ) {
			$class = sanitize_html_class( $class );

			if ( '' !== $class ) {
				$classes[] = $class;
			}
		}

		$style = isset( $args['style'] ) ? safecss_filter_attr( (string) $args['style'] ) : '';

		$handle = $this->enqueue( $app );
		wp_add_inline_script(
			$handle,
			sprintf( 'window.banzaiEmbed[%s].mounts.push(%s);', wp_json_encode( $slug ), wp_json_encode( $id ) ),
			'before'
		);

		$html = sprintf(
			'<div id="%s" class="%s" data-bzem-app="%s"%s></div>',
			esc_attr( $id ),
			esc_attr( implode( ' ', array_unique( $classes ) ) ),
			esc_attr( $slug ),
			'' !== $style ? ' style="' . esc_attr( $style ) . '"' : ''
		);

		/**
		 * Filter the mount point markup.
		 *
		 * @param string $html Markup.
		 * @param array  $app  App record.
		 * @param string $id   Mount element ID.
		 */
		return (string) apply_filters( 'bzem/mount_html', $html, $app, $id );
	}

	/**
	 * Register and enqueue an app's assets, once per request.
	 *
	 * @param array $app Record; must be ready.
	 * @return string Handle of the first script.
	 */
	public function enqueue( array $app ) {
		$slug = $app['slug'];

		if ( isset( $this->enqueued[ $slug ] ) ) {
			return $this->enqueued[ $slug ];
		}

		/*
		 * Every asset gets a null version — no `?ver=`. The build directory
		 * changes on every upload, which already busts every cache, and a
		 * query string would break modules: the browser keys them by full
		 * URL, so `index.js?ver=x` from our tag and `index.js` from another
		 * chunk's `import` would be two instances and the app would boot twice.
		 */
		foreach ( array_values( $app['styles'] ) as $i => $path ) {
			$handle = 'bzem-' . $slug . '-' . $i;
			wp_enqueue_style( $handle, $this->apps->asset_url( $app, $path ), array(), null );
		}

		$first    = '';
		$previous = array();

		foreach ( array_values( $app['scripts'] ) as $i => $path ) {
			$handle = 'bzem-' . $slug . '-' . $i;

			// Each script depends on the one before, so they print in order.
			wp_enqueue_script( $handle, $this->apps->asset_url( $app, $path ), $previous, null, true );

			$this->script_types[ $handle ] = 'classic' === $app['script_type'] ? 'classic' : 'module';
			$previous                      = array( $handle );

			if ( '' === $first ) {
				$first = $handle;
			}
		}

		$data = array(
			'slug'    => $slug,
			'baseUrl' => $this->apps->build_url( $slug, $app['build'] ),
			'mountId' => App_Manager::mount_id( $app ),
			'mounts'  => array(),
		);

		/**
		 * Filter the object an app sees as window.banzaiEmbed[slug].
		 *
		 * The seam the Data Bridge and environment variables plug into.
		 *
		 * @param array $data Data.
		 * @param array $app  App record.
		 */
		$data = apply_filters( 'bzem/app_data', $data, $app );

		// Tags hex-escaped: values can come from post meta that authors write,
		// and "<!--" or "<script" in an inline script derails the HTML parser.
		wp_add_inline_script(
			$first,
			sprintf( 'window.banzaiEmbed=window.banzaiEmbed||{};window.banzaiEmbed[%s]=%s;', wp_json_encode( $slug ), wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP ) ),
			'before'
		);

		/**
		 * Fires once an app's assets and data object are enqueued. Inline
		 * scripts added 'before' $handle now run after the data object and
		 * before the app.
		 *
		 * @param array  $app    App record.
		 * @param string $handle Handle of the app's first script.
		 */
		do_action( 'bzem/enqueued', $app, $first );

		$this->enqueued[ $slug ] = $first;

		return $first;
	}

	/**
	 * Print our scripts as modules, or deferred classic scripts.
	 *
	 * The tag WordPress builds carries any inline before/after scripts too;
	 * only the tag with our handle's `-js` id is touched.
	 *
	 * @param string $tag    Script markup.
	 * @param string $handle Handle.
	 * @return string
	 */
	public function script_tag( $tag, $handle ) {
		if ( ! isset( $this->script_types[ $handle ] ) ) {
			return $tag;
		}

		$module  = 'module' === $this->script_types[ $handle ];
		$pattern = '#<script\b([^>]*\bid=([\'"])' . preg_quote( $handle, '#' ) . '-js\2[^>]*)>#';

		return preg_replace_callback(
			$pattern,
			static function ( $m ) use ( $module ) {
				$attrs = preg_replace( '#\stype=([\'"])[^\'"]*\1#', '', $m[1] );

				if ( $module ) {
					return '<script type="module"' . $attrs . '>';
				}

				if ( ! preg_match( '#\sdefer(\s|=|$)#', $attrs ) ) {
					$attrs .= ' defer';
				}

				return '<script' . $attrs . '>';
			},
			$tag,
			1
		);
	}

	/**
	 * An ID nothing else on this page has used.
	 *
	 * @param string $id Wanted ID.
	 * @return string
	 */
	private function unique_id( $id ) {
		$candidate = $id;
		$n         = 2;

		while ( isset( $this->used_ids[ $candidate ] ) ) {
			$candidate = $id . '-' . $n++;
		}

		$this->used_ids[ $candidate ] = true;

		return $candidate;
	}

	/**
	 * Strip anything that is not safe in an id attribute and CSS selector.
	 *
	 * @param mixed $id Raw ID.
	 * @return string
	 */
	public static function sanitize_id( $id ) {
		return substr( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $id ), 0, 100 );
	}

	/**
	 * A visible explanation for people who can fix it; nothing for visitors.
	 *
	 * @param string $message Plain-text message.
	 * @return string
	 */
	private function problem( $message ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}

		return sprintf(
			'<div class="bzem-app-problem" style="padding:1em;border:1px dashed #d63638;color:#d63638;">%s %s</div>',
			esc_html__( 'BanzaiEmbed:', 'banzaiembed' ),
			esc_html( $message )
		);
	}
}
