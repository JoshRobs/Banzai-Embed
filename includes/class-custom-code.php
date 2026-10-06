<?php
/**
 * Per-app custom CSS and JS.
 *
 * @package BanzaiEmbed
 */

namespace BanzaiEmbed;

defined( 'ABSPATH' ) || exit;

/**
 * Admin-written CSS and JavaScript that load with one app.
 *
 * - CSS prints after the app's own stylesheets, so equal-specificity rules
 *   win. It is not scoped automatically; every mount element has the class
 *   `.bzem-app-{slug}` to scope it by hand.
 * - "Before" JS runs ahead of the app's first script, after
 *   window.banzaiEmbed[slug] (and the Data Bridge's cfg.user) exist.
 * - "After" JS runs once the app has rendered. A plain inline script after
 *   the app's tag would run first — module and deferred scripts execute after
 *   parsing — so it waits for DOMContentLoaded (by which point the entry has
 *   run) and then for the first mount element to get content.
 *
 * Both JS snippets run inside a function with `cfg` as its argument, once per
 * page however many instances there are. Only users with unfiltered_html can
 * save any of it (Admin::can_manage()).
 */
final class Custom_Code {

	/**
	 * Cap per field, in bytes — it all lives in an autoloaded option.
	 */
	const MAX_BYTES = 50000;

	/**
	 * Runs after-code once the app has rendered. %1$s is the JSON-encoded
	 * slug, %2$s the wrapped code.
	 */
	const AFTER_JS = '(function(cfg){var run=function(){%2$s};function wait(){var el=document.getElementById(cfg.mounts[0]||cfg.mountId);if(!el||el.childNodes.length){run();return;}var o=new MutationObserver(function(){o.disconnect();run();});o.observe(el,{childList:true});}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",wait);}else{wait();}})(window.banzaiEmbed[%1$s]);';

	/**
	 * Register hooks.
	 */
	public function register() {
		// After the Data Bridge (10), so before-code can call cfg.user().
		add_action( 'bzem/enqueued', array( $this, 'enqueue' ), 20, 2 );
		add_action( 'bzem/edit_cards', array( $this, 'render_card' ), 20 );
		add_filter( 'bzem/save_app', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor' ), 20 );
	}

	/**
	 * An app's code with every field present.
	 *
	 * @param array $app Record.
	 * @return array{css: string, js_before: string, js_after: string}
	 */
	public static function config( array $app ) {
		$code = isset( $app['custom_code'] ) && is_array( $app['custom_code'] ) ? $app['custom_code'] : array();

		return array_merge(
			array(
				'css'       => '',
				'js_before' => '',
				'js_after'  => '',
			),
			$code
		);
	}

	/**
	 * Add the app's CSS and JS alongside its assets.
	 *
	 * @param array  $app    Record.
	 * @param string $handle First script handle.
	 */
	public function enqueue( $app, $handle ) {
		$code = self::config( $app );
		$slug = wp_json_encode( $app['slug'] );

		if ( '' !== trim( $code['css'] ) ) {
			// Depend on the app's stylesheets (Embed::enqueue() names them
			// bzem-{slug}-{i}) so ours print after them.
			$deps = array();

			foreach ( array_keys( array_values( $app['styles'] ) ) as $i ) {
				$deps[] = 'bzem-' . $app['slug'] . '-' . $i;
			}

			$css_handle = 'bzem-' . $app['slug'] . '-custom';
			wp_register_style( $css_handle, false, $deps, null );
			wp_enqueue_style( $css_handle );
			wp_add_inline_style( $css_handle, self::neutralise( $code['css'], 'style' ) );
		}

		if ( '' !== trim( $code['js_before'] ) ) {
			wp_add_inline_script(
				$handle,
				sprintf( '(function(cfg){%s}).call(window,window.banzaiEmbed[%s]);', self::wrap( $code['js_before'] ), $slug ),
				'before'
			);
		}

		if ( '' !== trim( $code['js_after'] ) ) {
			// Before, not after: it only registers a listener, and an 'after'
			// script would be on the last handle — not $handle.
			wp_add_inline_script(
				$handle,
				sprintf( self::AFTER_JS, $slug, sprintf( '(function(cfg){%s}).call(window,cfg);', self::wrap( $code['js_after'] ) ) ),
				'before'
			);
		}
	}

	/**
	 * Admin code as a function body: newlines around it so a trailing //
	 * comment cannot swallow the closing brace.
	 *
	 * @param string $js Code.
	 * @return string
	 */
	private static function wrap( $js ) {
		return "\n" . self::neutralise( $js, 'script' ) . "\n";
	}

	/**
	 * Stop code closing the tag it is printed in. `<\/script` means the same
	 * inside a JS string, regex or comment, and `<\/style` inside a CSS
	 * string; anywhere else the original was broken anyway.
	 *
	 * @param string $code Code.
	 * @param string $tag  'script' or 'style'.
	 * @return string
	 */
	private static function neutralise( $code, $tag ) {
		return preg_replace( '#</(' . $tag . ')#i', '<\\/$1', $code );
	}

	/**
	 * Use WordPress's code editor for the textareas, on our screens only.
	 * Respects the user's "disable syntax highlighting" preference: with it
	 * off these are plain textareas.
	 *
	 * @param string $hook Screen hook suffix.
	 */
	public function enqueue_editor( $hook ) {
		if ( false === strpos( $hook, Admin::PAGE ) || ! function_exists( 'wp_enqueue_code_editor' ) ) {
			return;
		}

		$settings = array(
			'css' => wp_enqueue_code_editor( array( 'type' => 'text/css' ) ),
			'js'  => wp_enqueue_code_editor( array( 'type' => 'text/javascript' ) ),
		);

		if ( $settings['css'] && $settings['js'] ) {
			wp_add_inline_script( 'bzem-admin', 'window.bzemCodeEditor=' . wp_json_encode( $settings ) . ';', 'before' );
		}
	}

	/**
	 * The Custom CSS & JS card on the edit screen.
	 *
	 * @param array $app Record.
	 */
	public function render_card( $app ) {
		$code = self::config( $app );

		include BZEM_PLUGIN_PATH . 'templates/admin-custom-code.php';
	}

	/**
	 * Read the card into the record. Runs inside Admin::handle_save(), after
	 * its capability and nonce checks.
	 *
	 * @param array $app Record.
	 * @return array
	 */
	public function save( $app ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in Admin::handle_save(); code is stored as written, for users with unfiltered_html only.
		// Not on the form: keep what was saved.
		if ( ! isset( $_POST['bzem_custom_code'] ) ) {
			return $app;
		}

		$code    = self::config( $app );
		$labels  = array(
			'css'       => __( 'CSS', 'banzaiembed' ),
			'js_before' => __( 'JavaScript before the app', 'banzaiembed' ),
			'js_after'  => __( 'JavaScript after the app', 'banzaiembed' ),
		);
		$too_big = array();

		foreach ( array_keys( $code ) as $field ) {
			$value = isset( $_POST[ 'custom_' . $field ] ) && is_string( $_POST[ 'custom_' . $field ] ) ? wp_check_invalid_utf8( wp_unslash( $_POST[ 'custom_' . $field ] ) ) : '';
			$value = str_replace( "\r\n", "\n", $value );

			if ( strlen( $value ) > self::MAX_BYTES ) {
				// Keep what was saved rather than cut code off mid-statement.
				$too_big[] = $labels[ $field ];
				continue;
			}

			$code[ $field ] = $value;
		}
		// phpcs:enable

		$app['custom_code'] = $code;

		if ( $too_big ) {
			Admin::notice(
				'warning',
				/* translators: %s: size, e.g. "49 KB". */
				sprintf( __( 'Custom code over %s was not saved; the previous version is kept:', 'banzaiembed' ), size_format( self::MAX_BYTES ) ),
				$too_big
			);
		}

		return $app;
	}
}
