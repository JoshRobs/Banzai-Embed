<?php
/**
 * Admin screens.
 *
 * @package BanzaiEmbed
 */

namespace BanzaiEmbed;

defined( 'ABSPATH' ) || exit;

/**
 * The BanzaiEmbed menu: the app list, the add/edit screen, and the two form
 * handlers behind them.
 *
 * Forms post to admin-post.php rather than AJAX. A zip upload is a full round
 * trip either way, and a plain post works with JavaScript off and gives every
 * error a page to land on.
 *
 * Who may manage apps: `manage_options` AND `unfiltered_html`. Uploading an
 * app is uploading JavaScript that runs on the front end for every visitor —
 * exactly what `unfiltered_html` exists to gate. On multisite, site admins do
 * not have it, and without the second check this screen would be a way round
 * that.
 */
final class Admin {

	const PAGE = 'banzaiembed';

	const PAGE_NEW = 'banzaiembed-new';

	const SAVE_ACTION = 'banzaiembed_save_app';

	const DELETE_ACTION = 'banzaiembed_delete_app';

	/**
	 * @var App_Manager
	 */
	private $apps;

	/**
	 * @var Uploader
	 */
	private $uploader;

	/**
	 * @var Asset_Detector
	 */
	private $detector;

	/**
	 * Constructor.
	 *
	 * @param App_Manager    $apps     App store.
	 * @param Uploader       $uploader Zip extraction.
	 * @param Asset_Detector $detector Entry detection.
	 */
	public function __construct( App_Manager $apps, Uploader $uploader, Asset_Detector $detector ) {
		$this->apps     = $apps;
		$this->uploader = $uploader;
		$this->detector = $detector;
	}

	/**
	 * Hook in.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'handle_save' ) );
		add_action( 'admin_post_' . self::DELETE_ACTION, array( $this, 'handle_delete' ) );
	}

	/**
	 * Whether the current user may manage apps.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' ) && current_user_can( 'unfiltered_html' );
	}

	/**
	 * Add the menu.
	 */
	public function add_pages() {
		add_menu_page(
			__( 'BanzaiEmbed', 'banzaiembed' ),
			__( 'BanzaiEmbed', 'banzaiembed' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_main' ),
			'dashicons-embed-generic',
			81
		);

		add_submenu_page(
			self::PAGE,
			__( 'All Apps', 'banzaiembed' ),
			__( 'All Apps', 'banzaiembed' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_main' )
		);

		add_submenu_page(
			self::PAGE,
			__( 'Add New App', 'banzaiembed' ),
			__( 'Add New', 'banzaiembed' ),
			'manage_options',
			self::PAGE_NEW,
			array( $this, 'render_new' )
		);
	}

	/**
	 * Admin assets, on our screens only.
	 *
	 * @param string $hook Screen hook suffix.
	 */
	public function enqueue( $hook ) {
		if ( false === strpos( $hook, self::PAGE ) ) {
			return;
		}

		wp_enqueue_style( 'bzem-admin', BZEM_PLUGIN_URL . 'assets/css/admin.css', array(), BZEM_VERSION );
		wp_enqueue_script( 'bzem-admin', BZEM_PLUGIN_URL . 'assets/js/admin.js', array( 'wp-i18n' ), BZEM_VERSION, true );
		wp_set_script_translations( 'bzem-admin', 'banzaiembed' );
	}

	/**
	 * URL of the list screen.
	 *
	 * @return string
	 */
	public static function list_url() {
		return admin_url( 'admin.php?page=' . self::PAGE );
	}

	/**
	 * URL of an app's edit screen.
	 *
	 * @param string $slug App slug.
	 * @return string
	 */
	public static function edit_url( $slug ) {
		return add_query_arg(
			array(
				'page'   => self::PAGE,
				'action' => 'edit',
				'app'    => $slug,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Nonced URL that deletes an app.
	 *
	 * @param string $slug App slug.
	 * @return string
	 */
	public static function delete_url( $slug ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::DELETE_ACTION,
					'app'    => $slug,
				),
				admin_url( 'admin-post.php' )
			),
			self::DELETE_ACTION . '_' . $slug
		);
	}

	/**
	 * The All Apps screen, or the edit screen when ?action=edit.
	 */
	public function render_main() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only routing.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$slug   = isset( $_GET['app'] ) ? App_Manager::sanitize_slug( wp_unslash( $_GET['app'] ) ) : '';
		// phpcs:enable

		if ( 'edit' === $action ) {
			$app = $this->apps->get( $slug );

			if ( ! $app ) {
				$this->render_template( 'admin-missing', array() );
				return;
			}

			$this->render_edit( $app );
			return;
		}

		$this->render_template( 'admin-list', array( 'apps' => $this->apps->all() ) );
	}

	/**
	 * The Add New screen.
	 */
	public function render_new() {
		$this->render_edit( null );
	}

	/**
	 * The add/edit form.
	 *
	 * @param array|null $app Record, or null for a new app.
	 */
	private function render_edit( $app ) {
		$vars = array(
			'app'      => $app,
			'is_new'   => null === $app,
			'max_size' => size_format( wp_max_upload_size() ),
			'assets'   => $app ? $this->apps->build_assets( $app ) : array(
				'scripts' => array(),
				'styles'  => array(),
			),
		);

		$this->render_template( 'admin-edit', $vars );
	}

	/**
	 * Include a template with the given variables, wrapped in the common
	 * chrome: capability gate and notices.
	 *
	 * @param string $name Template name, without .php.
	 * @param array  $vars Variables for the template.
	 */
	private function render_template( $name, array $vars ) {
		echo '<div class="wrap bzem-wrap">';

		if ( ! self::can_manage() ) {
			printf(
				'<h1>%s</h1><div class="notice notice-error"><p>%s</p></div></div>',
				esc_html__( 'BanzaiEmbed', 'banzaiembed' ),
				esc_html__( 'Managing apps uploads JavaScript that runs for every visitor, so it needs the unfiltered_html capability as well as manage_options. Your account does not have it — on multisite, ask a network administrator.', 'banzaiembed' )
			);
			return;
		}

		$this->print_notices();

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- template variables.
		extract( $vars );
		include BZEM_PLUGIN_PATH . 'templates/' . $name . '.php';

		echo '</div>';
	}

	/**
	 * Create or update an app, with an optional build upload.
	 */
	public function handle_save() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to manage BanzaiEmbed apps.', 'banzaiembed' ), 403 );
		}

		// Over post_max_size PHP throws the whole body away, nonce included,
		// and check_admin_referer() would show a baffling "link expired".
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nothing is read or changed.
		if ( empty( $_POST ) && ! empty( $_SERVER['CONTENT_LENGTH'] ) ) {
			$this->fail(
				wp_get_referer() ? wp_get_referer() : self::list_url(),
				/* translators: %s: maximum upload size, e.g. "64 MB". */
				sprintf( __( 'The zip is larger than this server accepts (%s). Raise upload_max_filesize and post_max_size, or make the build smaller.', 'banzaiembed' ), size_format( wp_max_upload_size() ) )
			);
		}

		check_admin_referer( self::SAVE_ACTION );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$original = isset( $_POST['original_slug'] ) ? App_Manager::sanitize_slug( wp_unslash( $_POST['original_slug'] ) ) : '';
		$name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$fw       = isset( $_POST['framework'] ) ? sanitize_key( wp_unslash( $_POST['framework'] ) ) : 'other';
		$mount    = isset( $_POST['mount_id'] ) ? Embed::sanitize_id( wp_unslash( $_POST['mount_id'] ) ) : '';
		// phpcs:enable

		$is_new = '' === $original;
		$back   = $is_new ? admin_url( 'admin.php?page=' . self::PAGE_NEW ) : self::edit_url( $original );

		if ( $is_new ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
			$wanted = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
			$slug   = App_Manager::sanitize_slug( '' !== $wanted ? $wanted : $name );
			$app    = App_Manager::defaults();

			if ( '' === $slug ) {
				$this->fail( $back, __( 'Give the app a name.', 'banzaiembed' ) );
			}

			if ( $this->apps->get( $slug ) ) {
				$this->fail(
					$back,
					/* translators: 1: taken slug, 2: suggested slug. */
					sprintf( __( 'An app with the slug "%1$s" already exists. Try "%2$s".', 'banzaiembed' ), $slug, $this->apps->unique_slug( $slug ) )
				);
			}

			$app['slug'] = $slug;
		} else {
			$app = $this->apps->get( $original );

			if ( ! $app ) {
				$this->fail( self::list_url(), __( 'That app no longer exists.', 'banzaiembed' ) );
			}
		}

		if ( '' === $name ) {
			$this->fail( $back, __( 'Give the app a name.', 'banzaiembed' ) );
		}

		$app['name']      = $name;
		$app['framework'] = in_array( $fw, App_Manager::FRAMEWORKS, true ) ? $fw : 'other';
		$app['mount_id']  = $mount;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$has_zip = isset( $_FILES['build'] ) && is_array( $_FILES['build'] ) && ! empty( $_FILES['build']['name'] );

		if ( $has_zip ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified above; the uploader validates the file.
			$app = $this->apply_upload( $app, $_FILES['build'] );
		} elseif ( ! $is_new && '' !== $app['build'] ) {
			$app = $this->apply_entries( $app );
		}

		/**
		 * Filter a record before the edit form saves it — where pro modules
		 * read the fields of the cards they added. Capability and nonce are
		 * already checked.
		 *
		 * @param array $app    Record.
		 * @param bool  $is_new Whether the app is being created.
		 */
		$app = (array) apply_filters( 'bzem/save_app', $app, $is_new );

		$this->apps->save( $app );

		if ( $is_new ) {
			/* translators: %s: app name. */
			$this->notice( 'success', sprintf( __( '"%s" created.', 'banzaiembed' ), $app['name'] ) );
		} elseif ( ! $has_zip ) {
			$this->notice( 'success', __( 'App saved.', 'banzaiembed' ) );
		}

		wp_safe_redirect( self::edit_url( $app['slug'] ) );
		exit;
	}

	/**
	 * Extract an uploaded build, detect its entries and switch the app to it.
	 * On failure the app keeps the build it had.
	 *
	 * @param array $app  Record.
	 * @param array $file $_FILES entry.
	 * @return array Updated record.
	 */
	private function apply_upload( array $app, array $file ) {
		$result = $this->uploader->extract( $file, $app['slug'] );

		if ( is_wp_error( $result ) ) {
			$this->notice( 'error', $result->get_error_message() );

			return $app;
		}

		$found = $this->detector->detect( $result['dir'] );

		$app['previous_build']    = $app['build'];
		$app['build']             = $result['build'];
		$app['uploaded']          = time();
		$app['scripts']           = $found['scripts'];
		$app['styles']            = $found['styles'];
		$app['script_type']       = $found['script_type'];
		$app['detected_by']       = $found['detected_by'];
		$app['detected_mount_id'] = $found['mount_id'];
		$app['warnings']          = $found['warnings'];

		// Keep the new build and the one before it, for pages cached in between.
		$this->uploader->prune( $app['slug'], array_filter( array( $app['build'], $app['previous_build'] ) ) );

		$this->notice(
			'success',
			sprintf(
				/* translators: 1: number of files, 2: total size. */
				__( 'Build uploaded: %1$s files, %2$s.', 'banzaiembed' ),
				number_format_i18n( $result['files'] ),
				size_format( $result['bytes'] )
			)
		);

		if ( $result['skipped'] ) {
			$this->notice(
				'warning',
				__( 'These files were left out because they are not static web assets, or their path was unsafe:', 'banzaiembed' ),
				array_slice( $result['skipped'], 0, 50 )
			);
		}

		return $app;
	}

	/**
	 * Apply manually chosen entry files from the edit form.
	 *
	 * @param array $app Record.
	 * @return array Updated record.
	 */
	private function apply_entries( array $app ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in handle_save().
		$scripts = isset( $_POST['scripts'] ) ? sanitize_textarea_field( wp_unslash( $_POST['scripts'] ) ) : '';
		$styles  = isset( $_POST['styles'] ) ? sanitize_textarea_field( wp_unslash( $_POST['styles'] ) ) : '';
		$type    = isset( $_POST['script_type'] ) ? sanitize_key( wp_unslash( $_POST['script_type'] ) ) : 'module';
		// phpcs:enable

		$dir      = $this->apps->build_dir( $app['slug'], $app['build'] );
		$rejected = array();
		$scripts  = $this->parse_entries( $scripts, $dir, '/\.(m?js|cjs)$/i', $rejected );
		$styles   = $this->parse_entries( $styles, $dir, '/\.css$/i', $rejected );

		if ( $rejected ) {
			$this->notice( 'warning', __( 'These entries were ignored because they are not a JS/CSS file in this build or an http(s) URL:', 'banzaiembed' ), $rejected );
		}

		if ( $scripts !== $app['scripts'] || $styles !== $app['styles'] ) {
			$app['detected_by'] = 'manual';

			// Detection's complaints were about what it found; the user has
			// now chosen for themselves. The base-path problem is still real.
			$app['warnings'] = array_values( array_intersect( $app['warnings'], array( Asset_Detector::rooted_warning() ) ) );
		}

		$app['scripts']     = $scripts;
		$app['styles']      = $styles;
		$app['script_type'] = 'classic' === $type ? 'classic' : 'module';

		return $app;
	}

	/**
	 * One entry per line → validated list.
	 *
	 * @param string   $text     Textarea contents.
	 * @param string   $dir      Build directory.
	 * @param string   $pattern  Required extension pattern.
	 * @param string[] $rejected Collects rejected lines.
	 * @return string[]
	 */
	private function parse_entries( $text, $dir, $pattern, array &$rejected ) {
		$out = array();

		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			if ( preg_match( '#^https?://#i', $line ) ) {
				$out[] = esc_url_raw( $line );
				continue;
			}

			$line = ltrim( preg_replace( '#^\./#', '', $line ), '/' );

			if ( $this->detector->is_relative_file( $line ) && preg_match( $pattern, $line ) && is_file( $dir . '/' . $line ) ) {
				$out[] = $line;
			} else {
				$rejected[] = $line;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Delete an app and its files.
	 */
	public function handle_delete() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to manage BanzaiEmbed apps.', 'banzaiembed' ), 403 );
		}

		$slug = isset( $_GET['app'] ) ? App_Manager::sanitize_slug( wp_unslash( $_GET['app'] ) ) : '';
		check_admin_referer( self::DELETE_ACTION . '_' . $slug );

		$app = $this->apps->get( $slug );

		if ( $app ) {
			$this->apps->delete( $slug );
			/* translators: %s: app name. */
			$this->notice( 'success', sprintf( __( '"%s" deleted.', 'banzaiembed' ), $app['name'] ) );
		}

		wp_safe_redirect( self::list_url() );
		exit;
	}

	/**
	 * Queue an error and go back.
	 *
	 * @param string $url     Where to go.
	 * @param string $message Plain text.
	 */
	private function fail( $url, $message ) {
		$this->notice( 'error', $message );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Queue a notice for the next screen this user sees. Public for pro
	 * modules saving through handle_save().
	 *
	 * @param string   $type    success | error | warning | info.
	 * @param string   $message Plain text.
	 * @param string[] $items   Optional list shown under it.
	 */
	public static function notice( $type, $message, array $items = array() ) {
		$key       = 'bzem_notices_' . get_current_user_id();
		$notices   = get_transient( $key );
		$notices   = is_array( $notices ) ? $notices : array();
		$notices[] = array(
			'type'    => $type,
			'message' => $message,
			'items'   => $items,
		);

		set_transient( $key, $notices, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Print and clear queued notices.
	 */
	private function print_notices() {
		$key     = 'bzem_notices_' . get_current_user_id();
		$notices = get_transient( $key );

		if ( ! is_array( $notices ) ) {
			return;
		}

		delete_transient( $key );

		foreach ( $notices as $notice ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p>', esc_attr( $notice['type'] ), esc_html( $notice['message'] ) );

			if ( ! empty( $notice['items'] ) ) {
				echo '<ul class="bzem-notice-list">';

				foreach ( $notice['items'] as $item ) {
					printf( '<li><code>%s</code></li>', esc_html( $item ) );
				}

				echo '</ul>';
			}

			echo '</div>';
		}
	}
}
