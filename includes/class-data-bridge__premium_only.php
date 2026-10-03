<?php
/**
 * Data Bridge (Pro).
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
 * Hands WordPress data to an embedded app, configured per app in the admin.
 *
 * Two channels, split by whether a page cache may store the value:
 *
 * - Page data (`cfg.data`) and environment variables (`cfg.env`) are the
 *   same for every visitor to a URL, so they are inlined into
 *   window.banzaiEmbed[slug] through the `bzem/app_data` filter.
 * - User data (`cfg.user()`) differs per visitor. Inlined, a page cache would
 *   serve one user's email to the next visitor, and a cached REST nonce goes
 *   stale. So the app fetches it from an uncached admin-ajax endpoint. Not a
 *   REST route: REST treats a cookie without a nonce as logged out, and the
 *   nonce is one of the things being fetched.
 *
 * Like Site_Wide, output depends on this code being present, not on the
 * licence being valid: an app built to read cfg.data would break overnight if
 * a lapsed licence took it away. Changing the configuration needs a licence.
 */
final class Data_Bridge {

	const USER_ACTION = 'bzem_user';

	/**
	 * Cap on rows per section — the whole config lives in an autoloaded option.
	 */
	const MAX_ROWS = 50;

	/**
	 * What a key must look like: a JS identifier, so `cfg.data.key` works.
	 */
	const KEY_PATTERN = '/^[A-Za-z_$][A-Za-z0-9_$]{0,63}$/';

	/**
	 * Defines cfg.user() on the app's object. %s is the JSON-encoded slug.
	 *
	 * The promise is shared so every caller makes one request, refetched after
	 * an hour (well inside a nonce's 12-hour tick), on cfg.user(true), or after
	 * a failure.
	 */
	const HELPER_JS = '(function(c){if(!c||!c.userUrl)return;var p,t=0;c.user=function(fresh){if(!p||fresh||Date.now()-t>36e5){t=Date.now();p=fetch(c.userUrl,{credentials:"same-origin",cache:"no-store"}).then(function(r){if(!r.ok)throw new Error("BanzaiEmbed: user data request failed ("+r.status+")");return r.json();});p.catch(function(){p=null;});}return p;};})(window.banzaiEmbed[%s]);';

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
		add_filter( 'bzem/app_data', array( $this, 'app_data' ), 10, 2 );
		add_action( 'bzem/enqueued', array( $this, 'add_user_helper' ), 10, 2 );
		add_action( 'wp_ajax_' . self::USER_ACTION, array( $this, 'handle_user' ) );
		add_action( 'wp_ajax_nopriv_' . self::USER_ACTION, array( $this, 'handle_user' ) );
		add_action( 'bzem/edit_cards', array( $this, 'render_card' ) );
		add_filter( 'bzem/save_app', array( $this, 'save' ) );
	}

	/**
	 * Where a page value can come from. `static` and `post.meta` take a value
	 * (the text, the meta key); the rest do not.
	 *
	 * @return array<string,string> source => label.
	 */
	public static function sources() {
		return array(
			'static'        => __( 'Text', 'banzaiembed' ),
			'post.id'       => __( 'Post: ID', 'banzaiembed' ),
			'post.title'    => __( 'Post: title', 'banzaiembed' ),
			'post.url'      => __( 'Post: URL', 'banzaiembed' ),
			'post.slug'     => __( 'Post: slug', 'banzaiembed' ),
			'post.type'     => __( 'Post: type', 'banzaiembed' ),
			'post.meta'     => __( 'Post: custom field', 'banzaiembed' ),
			'site.name'     => __( 'Site: name', 'banzaiembed' ),
			'site.url'      => __( 'Site: home URL', 'banzaiembed' ),
			'site.rest'     => __( 'Site: REST API root', 'banzaiembed' ),
			'site.language' => __( 'Site: language', 'banzaiembed' ),
		);
	}

	/**
	 * Whether a source takes a value.
	 *
	 * @param string $source From sources().
	 * @return bool
	 */
	public static function takes_value( $source ) {
		return 'static' === $source || 'post.meta' === $source;
	}

	/**
	 * User fields an app can be given. The key is also the property name.
	 *
	 * @return array<string,string> field => label.
	 */
	public static function user_fields() {
		return array(
			'id'          => __( 'User ID', 'banzaiembed' ),
			'displayName' => __( 'Display name', 'banzaiembed' ),
			'email'       => __( 'Email', 'banzaiembed' ),
			'roles'       => __( 'Roles', 'banzaiembed' ),
			'nonce'       => __( 'REST API nonce', 'banzaiembed' ),
		);
	}

	/**
	 * An app's bridge config with every section present.
	 *
	 * @param array $app Record.
	 * @return array{values: array[], env: array[], user: string[]}
	 */
	public static function config( array $app ) {
		$bridge = isset( $app['bridge'] ) && is_array( $app['bridge'] ) ? $app['bridge'] : array();

		return array_merge(
			array(
				'values' => array(),
				'env'    => array(),
				'user'   => array(),
			),
			$bridge
		);
	}

	/**
	 * Add data, env and the user endpoint to window.banzaiEmbed[slug].
	 *
	 * @param array $data Object the app sees.
	 * @param array $app  Record.
	 * @return array
	 */
	public function app_data( $data, $app ) {
		$config = self::config( $app );
		$post   = self::current_post();
		$values = array();

		foreach ( $config['values'] as $row ) {
			$values[ $row['key'] ] = self::resolve( $row, $post );
		}

		// Objects, so an empty section is {} in JS rather than [].
		$data['data'] = (object) $values;
		$data['env']  = (object) self::env_values( $config['env'] );

		if ( $config['user'] ) {
			$data['userUrl'] = add_query_arg(
				array(
					'action' => self::USER_ACTION,
					'app'    => $app['slug'],
				),
				// Relative: same origin as the page, so the login cookie goes with it.
				admin_url( 'admin-ajax.php', 'relative' )
			);
		}

		return $data;
	}

	/**
	 * Print cfg.user() after the app's object, before its first script.
	 *
	 * @param array  $app    Record.
	 * @param string $handle First script handle.
	 */
	public function add_user_helper( $app, $handle ) {
		$config = self::config( $app );

		if ( $config['user'] ) {
			wp_add_inline_script( $handle, sprintf( self::HELPER_JS, wp_json_encode( $app['slug'] ) ), 'before' );
		}
	}

	/**
	 * The post being viewed, or null off single views. Embeds in a widget on
	 * an archive get no post data rather than whichever post was in the loop.
	 *
	 * @return \WP_Post|null
	 */
	private static function current_post() {
		if ( ! is_singular() ) {
			return null;
		}

		$post = get_queried_object();

		return $post instanceof \WP_Post ? $post : null;
	}

	/**
	 * One page value.
	 *
	 * @param array         $row  { key, source, value }.
	 * @param \WP_Post|null $post Current post.
	 * @return mixed
	 */
	private static function resolve( array $row, $post ) {
		switch ( $row['source'] ) {
			case 'static':
				return $row['value'];
			case 'site.name':
				// Stored HTML-escaped; the app wants text.
				return wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			case 'site.url':
				return home_url( '/' );
			case 'site.rest':
				return rest_url();
			case 'site.language':
				return get_bloginfo( 'language' );
		}

		if ( ! $post ) {
			return null;
		}

		switch ( $row['source'] ) {
			case 'post.id':
				return (int) $post->ID;
			case 'post.title':
				return wp_strip_all_tags( $post->post_title );
			case 'post.url':
				return get_permalink( $post );
			case 'post.slug':
				return $post->post_name;
			case 'post.type':
				return $post->post_type;
			case 'post.meta':
				// Custom fields are behind the password like the content is.
				// Protected keys were refused on save; check again in case a
				// plugin has since registered the key as protected.
				if ( post_password_required( $post ) || is_protected_meta( $row['value'], 'post' ) || ! metadata_exists( 'post', $post->ID, $row['value'] ) ) {
					return null;
				}

				return get_post_meta( $post->ID, $row['value'], true );
		}

		return null;
	}

	/**
	 * Environment variables for this site's environment type: the staging
	 * value anywhere but production, when one is set.
	 *
	 * @param array[] $rows { key, value, staging }.
	 * @return array<string,string>
	 */
	private static function env_values( array $rows ) {
		$staging = 'production' !== wp_get_environment_type();
		$out     = array();

		foreach ( $rows as $row ) {
			$out[ $row['key'] ] = $staging && '' !== $row['staging'] ? $row['staging'] : $row['value'];
		}

		return $out;
	}

	/**
	 * GET admin-ajax.php?action=bzem_user&app={slug} — the current visitor's
	 * fields, as enabled for that app. Never cached.
	 */
	public function handle_user() {
		nocache_headers();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read of the requester's own data; the same-origin policy keeps other sites from reading it.
		$slug   = isset( $_GET['app'] ) ? App_Manager::sanitize_slug( wp_unslash( $_GET['app'] ) ) : '';
		$app    = '' === $slug ? null : $this->apps->get( $slug );
		$config = $app ? self::config( $app ) : null;

		if ( ! $config || ! $config['user'] ) {
			wp_send_json( array( 'error' => 'not_found' ), 404 );
		}

		wp_send_json( self::user_payload( $config['user'], $app ) );
	}

	/**
	 * The current visitor's enabled fields. `loggedIn` is always included.
	 *
	 * @param string[] $fields From user_fields().
	 * @param array    $app    Record.
	 * @return array
	 */
	private static function user_payload( array $fields, array $app ) {
		$user = wp_get_current_user();
		$in   = $user->exists();
		$out  = array( 'loggedIn' => $in );

		foreach ( $fields as $field ) {
			switch ( $field ) {
				case 'id':
					$out['id'] = $in ? (int) $user->ID : null;
					break;
				case 'displayName':
					$out['displayName'] = $in ? $user->display_name : null;
					break;
				case 'email':
					$out['email'] = $in ? $user->user_email : null;
					break;
				case 'roles':
					$out['roles'] = $in ? array_values( $user->roles ) : array();
					break;
				case 'nonce':
					// Logged-out REST requests need no nonce.
					$out['nonce'] = $in ? wp_create_nonce( 'wp_rest' ) : null;
					break;
			}
		}

		/**
		 * Filter what cfg.user() resolves to — the place to add per-user data
		 * such as user meta. Runs on every request, never cached.
		 *
		 * @param array    $out  Payload.
		 * @param array    $app  App record.
		 * @param \WP_User $user Current user; ID 0 when logged out.
		 */
		return (array) apply_filters( 'bzem/user_data', $out, $app, $user );
	}

	/**
	 * The Data Bridge card on the edit screen.
	 *
	 * @param array $app Record.
	 */
	public function render_card( $app ) {
		$licensed = bzem_has_valid_license();
		$config   = self::config( $app );
		$sources = self::sources();
		$fields  = self::user_fields();
		$env     = wp_get_environment_type();
		$usage   = self::usage( $app, $config );

		include BZEM_PLUGIN_PATH . 'templates/admin-bridge__premium_only.php';
	}

	/**
	 * Example code for the app, from its current config.
	 *
	 * @param array $app    Record.
	 * @param array $config From config().
	 * @return string
	 */
	private static function usage( array $app, array $config ) {
		$sources = self::sources();
		$rest    = "'/wp-json/'";
		$lines   = array( sprintf( "const cfg = window.banzaiEmbed['%s'];", $app['slug'] ) );

		foreach ( $config['values'] as $row ) {
			$lines[] = sprintf( 'cfg.data.%s // %s', $row['key'], $sources[ $row['source'] ] );

			if ( 'site.rest' === $row['source'] ) {
				$rest = 'cfg.data.' . $row['key'];
			}
		}

		foreach ( $config['env'] as $row ) {
			$lines[] = sprintf( 'cfg.env.%s', $row['key'] );
		}

		if ( $config['user'] ) {
			$lines[] = '';
			$lines[] = 'const user = await cfg.user();';
			$lines[] = 'user.loggedIn';

			foreach ( $config['user'] as $field ) {
				$lines[] = 'user.' . $field;
			}

			if ( in_array( 'nonce', $config['user'], true ) ) {
				$lines[] = '';
				$lines[] = 'fetch(' . $rest . " + 'wp/v2/users/me', {";
				$lines[] = "  headers: { 'X-WP-Nonce': user.nonce },";
				$lines[] = '});';
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Read the card's fields into the record. Runs inside Admin::handle_save(),
	 * after its capability and nonce checks.
	 *
	 * @param array $app Record.
	 * @return array
	 */
	public function save( $app ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in Admin::handle_save(); every field is validated below.
		// Not on the form, or no licence to change it: keep what was saved.
		if ( ! isset( $_POST['bzem_bridge'] ) || ! bzem_has_valid_license() ) {
			return $app;
		}

		$problems = array();
		$values   = self::parse_values( isset( $_POST['bridge_values'] ) ? wp_unslash( $_POST['bridge_values'] ) : array(), $problems );
		$env      = self::parse_env( isset( $_POST['bridge_env'] ) ? wp_unslash( $_POST['bridge_env'] ) : array(), $problems );
		$user     = isset( $_POST['bridge_user'] ) ? (array) wp_unslash( $_POST['bridge_user'] ) : array();
		// phpcs:enable

		$app['bridge'] = array(
			'values' => $values,
			'env'    => $env,
			// Kept in user_fields() order so the payload is stable.
			'user'   => array_values( array_intersect( array_keys( self::user_fields() ), $user ) ),
		);

		if ( $problems ) {
			Admin::notice( 'warning', __( 'Some Data Bridge rows were not saved:', 'banzaiembed' ), $problems );
		}

		return $app;
	}

	/**
	 * Page-data rows from the form.
	 *
	 * @param mixed    $rows     Posted rows.
	 * @param string[] $problems Collects reasons rows were dropped.
	 * @return array[]
	 */
	private static function parse_values( $rows, array &$problems ) {
		$out     = array();
		$sources = self::sources();

		foreach ( self::rows( $rows ) as $row ) {
			$key    = self::text( $row, 'key' );
			$source = self::text( $row, 'source' );
			$value  = self::text( $row, 'value', false );

			if ( ! isset( $sources[ $source ] ) ) {
				$source = 'static';
			}

			if ( ! self::takes_value( $source ) ) {
				$value = '';
			} elseif ( 'post.meta' === $source ) {
				$value = trim( $value );
			}

			if ( '' === $key && '' === $value ) {
				continue; // An empty row, or one cleared to delete it.
			}

			$problem = self::key_problem( $key, $out );

			if ( '' === $problem && 'post.meta' === $source ) {
				if ( '' === $value ) {
					$problem = __( 'no custom field key', 'banzaiembed' );
				} elseif ( is_protected_meta( $value, 'post' ) ) {
					$problem = __( 'protected custom fields (starting with _) are private to WordPress', 'banzaiembed' );
				}
			}

			if ( '' !== $problem ) {
				$problems[] = sprintf( '%s: %s', '' !== $key ? $key : __( '(no key)', 'banzaiembed' ), $problem );
				continue;
			}

			$out[ $key ] = array(
				'key'    => $key,
				'source' => $source,
				'value'  => $value,
			);
		}

		return self::cap( $out, $problems );
	}

	/**
	 * Environment-variable rows from the form.
	 *
	 * @param mixed    $rows     Posted rows.
	 * @param string[] $problems Collects reasons rows were dropped.
	 * @return array[]
	 */
	private static function parse_env( $rows, array &$problems ) {
		$out = array();

		foreach ( self::rows( $rows ) as $row ) {
			$key     = self::text( $row, 'key' );
			$value   = self::text( $row, 'value', false );
			$staging = self::text( $row, 'staging', false );

			if ( '' === $key && '' === $value && '' === $staging ) {
				continue;
			}

			$problem = self::key_problem( $key, $out );

			if ( '' !== $problem ) {
				$problems[] = sprintf( '%s: %s', '' !== $key ? $key : __( '(no key)', 'banzaiembed' ), $problem );
				continue;
			}

			$out[ $key ] = array(
				'key'     => $key,
				'value'   => $value,
				'staging' => $staging,
			);
		}

		return self::cap( $out, $problems );
	}

	/**
	 * Why a key cannot be used, or ''.
	 *
	 * @param string $key   Key.
	 * @param array  $taken Rows accepted so far, keyed by key.
	 * @return string
	 */
	private static function key_problem( $key, array $taken ) {
		if ( '' === $key ) {
			return __( 'no key', 'banzaiembed' );
		}

		if ( ! preg_match( self::KEY_PATTERN, $key ) ) {
			return __( 'keys must be letters, numbers, _ or $, not starting with a number', 'banzaiembed' );
		}

		if ( isset( $taken[ $key ] ) ) {
			return __( 'the key is used twice', 'banzaiembed' );
		}

		return '';
	}

	/**
	 * Posted rows, arrays only.
	 *
	 * @param mixed $rows Posted value.
	 * @return array[]
	 */
	private static function rows( $rows ) {
		return array_filter( is_array( $rows ) ? $rows : array(), 'is_array' );
	}

	/**
	 * A string field of a posted row. Values are kept as typed (no tag
	 * stripping): only users with unfiltered_html get here, the result is JSON
	 * printed with tags escaped, and an app may want a literal "<".
	 *
	 * @param array  $row  Posted row.
	 * @param string $name Field.
	 * @param bool   $trim Trim whitespace.
	 * @return string
	 */
	private static function text( array $row, $name, $trim = true ) {
		$text = isset( $row[ $name ] ) && is_string( $row[ $name ] ) ? wp_check_invalid_utf8( $row[ $name ] ) : '';
		$text = substr( $text, 0, 2000 );

		return $trim ? trim( $text ) : $text;
	}

	/**
	 * Keep at most MAX_ROWS rows.
	 *
	 * @param array    $rows     Rows keyed by key.
	 * @param string[] $problems Collects dropped rows.
	 * @return array[] List.
	 */
	private static function cap( array $rows, array &$problems ) {
		foreach ( array_slice( array_keys( $rows ), self::MAX_ROWS ) as $key ) {
			/* translators: %d: maximum number of rows. */
			$problems[] = sprintf( '%s: %s', $key, sprintf( __( 'more than %d rows', 'banzaiembed' ), self::MAX_ROWS ) );
		}

		return array_values( array_slice( $rows, 0, self::MAX_ROWS ) );
	}
}
