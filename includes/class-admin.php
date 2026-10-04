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

	const TOGGLE_ACTION = 'banzaiembed_toggle_app';

	/**
	 * @var App_Manager
	 */
	private $apps;

	/**
	 * Hook suffixes of our two screens, from add_menu_page()/add_submenu_page().
	 *
	 * @var string[]
	 */
	private $hooks = array();

	/**
	 * @var Uploader
	 */
	private $uploader;

	/**
	 * @var Asset_Detector
	 */
	private $detector;

	/**
	 * @var Path_Rewriter
	 */
	private $rewriter;

	/**
	 * Constructor.
	 *
	 * @param App_Manager    $apps     App store.
	 * @param Uploader       $uploader Zip extraction.
	 * @param Asset_Detector $detector Entry detection.
	 * @param Path_Rewriter  $rewriter Base-path relocation.
	 */
	public function __construct( App_Manager $apps, Uploader $uploader, Asset_Detector $detector, Path_Rewriter $rewriter ) {
		$this->apps     = $apps;
		$this->uploader = $uploader;
		$this->detector = $detector;
		$this->rewriter = $rewriter;
	}

	/**
	 * Hook in.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'handle_save' ) );
		add_action( 'admin_post_' . self::DELETE_ACTION, array( $this, 'handle_delete' ) );
		add_action( 'admin_post_' . self::TOGGLE_ACTION, array( $this, 'handle_toggle' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
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
		$this->hooks[] = add_menu_page(
			__( 'BanzaiEmbed', 'banzaiembed' ),
			__( 'BanzaiEmbed', 'banzaiembed' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_main' ),
			self::menu_icon(),
			81
		);

		$this->hooks[] = add_submenu_page(
			self::PAGE,
			__( 'All Apps', 'banzaiembed' ),
			__( 'All Apps', 'banzaiembed' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_main' )
		);

		$this->hooks[] = add_submenu_page(
			self::PAGE,
			__( 'Add New App', 'banzaiembed' ),
			__( 'Add New', 'banzaiembed' ),
			'manage_options',
			self::PAGE_NEW,
			array( $this, 'render_new' )
		);

		$this->hooks[] = add_submenu_page(
			self::PAGE,
			__( 'BanzaiEmbed Help', 'banzaiembed' ),
			__( 'Help', 'banzaiembed' ),
			'manage_options',
			Help::PAGE,
			array( $this, 'render_help' )
		);

		$this->hooks = array_values( array_filter( array_unique( $this->hooks ) ) );
	}

	/**
	 * Whether the current admin screen is one of ours — not Freemius' pages,
	 * which share the menu.
	 *
	 * @return bool
	 */
	private function is_own_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && in_array( $screen->id, $this->hooks, true );
	}

	/**
	 * Mark our screens so the stylesheet can run the brand bar edge to edge.
	 *
	 * @param string $classes Space-separated body classes.
	 * @return string
	 */
	public function body_class( $classes ) {
		return $this->is_own_screen() ? $classes . ' bzem-admin-page' : $classes;
	}

	/**
	 * Admin assets, on our screens only.
	 *
	 * @param string $hook Screen hook suffix.
	 */
	public function enqueue( $hook ) {
		if ( ! in_array( $hook, $this->hooks, true ) ) {
			return;
		}

		wp_enqueue_style( 'bzem-admin', BZEM_PLUGIN_URL . 'assets/css/admin.css', array(), BZEM_VERSION );
		wp_enqueue_script( 'bzem-admin', BZEM_PLUGIN_URL . 'assets/js/admin.js', array( 'wp-i18n' ), BZEM_VERSION, true );
		wp_set_script_translations( 'bzem-admin', 'banzaiembed' );
	}

	/**
	 * URL of the list screen.
	 *
	 * @param array $args Query arguments: framework, status, s, orderby, order.
	 * @return string
	 */
	public static function list_url( array $args = array() ) {
		return add_query_arg( array_map( 'rawurlencode', $args ), admin_url( 'admin.php?page=' . self::PAGE ) );
	}

	/**
	 * URL of the Add New screen.
	 *
	 * @return string
	 */
	public static function new_url() {
		return admin_url( 'admin.php?page=' . self::PAGE_NEW );
	}

	/**
	 * The brand mark: the plugin icon's </> in a gradient frame.
	 *
	 * @param int $size Pixels.
	 * @return string SVG markup; static apart from the size.
	 */
	public static function logo( $size = 36 ) {
		static $n = 0;

		$id = 'bzem-logo-gradient-' . ++$n;

		return sprintf(
			'<svg class="bzem-logo" width="%1$d" height="%1$d" viewBox="0 0 32 32" aria-hidden="true" focusable="false">'
			. '<defs><linearGradient id="%2$s" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#14d4f8"/><stop offset="1" stop-color="#1af0a4"/></linearGradient></defs>'
			. '<rect width="32" height="32" rx="7" fill="#232323"/>'
			. '<rect x="6.5" y="6.5" width="19" height="19" rx="3.5" fill="none" stroke="url(#%2$s)" stroke-width="2"/>'
			. '<path d="M13.2 13 10.2 16l3 3M18.8 13l3 3-3 3M17.1 11.6l-2.2 8.8" fill="none" stroke="url(#%2$s)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>'
			. '</svg>',
			(int) $size,
			$id
		);
	}

	/**
	 * The admin menu icon: the logo as a single-colour shape, which WordPress
	 * recolours to match the admin colour scheme.
	 *
	 * @return string Data URI.
	 */
	private static function menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" fill-rule="evenodd" d="M5 2h10a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H5a3 3 0 0 1-3-3V5a3 3 0 0 1 3-3zm0 2a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1zm1.9 3.6.9.9L6.3 10l1.5 1.5-.9.9L4.9 10zm6.2 0 2 2.4-2 2.4-.9-.9 1.5-1.5-1.5-1.5zm-2.5-.8.9.3-2.1 6.1-.9-.3z"/></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- menu icon data URI, the format add_menu_page() takes.
	}

	/**
	 * A small framework mark for badges and the framework picker.
	 *
	 * @param string $framework One of App_Manager::FRAMEWORKS.
	 * @return string SVG markup; static.
	 */
	public static function framework_mark( $framework ) {
		$paths = array(
			'vue'   => '<path fill="#41b883" d="M2 3.5h4L12 14l6-10.5h4L12 21z"/><path fill="#34495e" d="M6 3.5h3.6L12 7.7l2.4-4.2H18L12 14z"/>',
			'react' => '<g fill="none" stroke="#149eca" stroke-width="1.3"><ellipse cx="12" cy="12" rx="10" ry="3.8"/><ellipse cx="12" cy="12" rx="10" ry="3.8" transform="rotate(60 12 12)"/><ellipse cx="12" cy="12" rx="10" ry="3.8" transform="rotate(120 12 12)"/></g><circle cx="12" cy="12" r="2" fill="#149eca"/>',
			'other' => '<rect x="2" y="2" width="20" height="20" rx="4" fill="#3c434a"/><path d="M9.5 8.5 6 12l3.5 3.5M14.5 8.5 18 12l-3.5 3.5" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
		);

		return '<svg class="bzem-fw-mark" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
			. ( isset( $paths[ $framework ] ) ? $paths[ $framework ] : $paths['other'] )
			. '</svg>';
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

		$this->render_list();
	}

	/**
	 * The All Apps screen: the current tab's apps, filtered and sorted.
	 */
	private function render_list() {
		$query  = $this->list_query();
		$in_tab = array_filter(
			$this->apps->all(),
			static function ( $app ) use ( $query ) {
				return '' === $query['framework'] || $query['framework'] === $app['framework'];
			}
		);

		$active = count( array_filter( $in_tab, array( App_Manager::class, 'is_active' ) ) );
		$counts = array(
			'all'      => count( $in_tab ),
			'active'   => $active,
			'inactive' => count( $in_tab ) - $active,
		);

		$apps = array_filter(
			$in_tab,
			static function ( $app ) use ( $query ) {
				if ( '' !== $query['status'] && ( 'active' === $query['status'] ) !== App_Manager::is_active( $app ) ) {
					return false;
				}

				return '' === $query['s']
					|| false !== stripos( $app['name'], $query['s'] )
					|| false !== stripos( $app['slug'], $query['s'] );
			}
		);

		uasort(
			$apps,
			static function ( $a, $b ) use ( $query ) {
				$cmp = 'modified' === $query['orderby']
					? App_Manager::modified( $a ) - App_Manager::modified( $b )
					: strcasecmp( $a['name'], $b['name'] );

				return 'desc' === $query['order'] ? -$cmp : $cmp;
			}
		);

		$this->render_template(
			'admin-list',
			array(
				'apps'   => $apps,
				'query'  => $query,
				'counts' => $counts,
				'total'  => count( $this->apps->all() ),
			),
			'' !== $query['framework'] ? $query['framework'] : 'all'
		);
	}

	/**
	 * The list screen's filters, from the URL.
	 *
	 * @return array { framework, status, s, orderby, order }
	 */
	private function list_query() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$framework = isset( $_GET['framework'] ) ? sanitize_key( wp_unslash( $_GET['framework'] ) ) : '';
		$status    = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$search    = isset( $_GET['s'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) : '';
		$orderby   = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
		$order     = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : '';
		// phpcs:enable

		$orderby = 'modified' === $orderby ? 'modified' : 'name';

		return array(
			'framework' => in_array( $framework, App_Manager::FRAMEWORKS, true ) ? $framework : '',
			'status'    => in_array( $status, array( 'active', 'inactive' ), true ) ? $status : '',
			's'         => $search,
			'orderby'   => $orderby,
			// Names A→Z, dates newest first, unless asked otherwise.
			'order'     => in_array( $order, array( 'asc', 'desc' ), true ) ? $order : ( 'modified' === $orderby ? 'desc' : 'asc' ),
		);
	}

	/**
	 * The Add New screen.
	 */
	public function render_new() {
		$this->render_edit( null );
	}

	/**
	 * The Help screen.
	 */
	public function render_help() {
		$this->render_template( 'admin-help', array(), 'help' );
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

		$this->render_template( 'admin-edit', $vars, null === $app ? 'new' : '' );
	}

	/**
	 * Include a template with the given variables, wrapped in the common
	 * chrome: brand bar and tabs, capability gate and notices.
	 *
	 * @param string $name Template name, without .php.
	 * @param array  $vars Variables for the template.
	 * @param string $tab  Highlighted tab: 'all', a framework, 'new' or ''.
	 */
	private function render_template( $name, array $vars, $tab = '' ) {
		$this->render_header( $tab );

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
	 * The brand bar and framework tabs.
	 *
	 * @param string $tab Highlighted tab.
	 */
	private function render_header( $tab ) {
		$counts = array( 'all' => 0 ) + array_fill_keys( App_Manager::FRAMEWORKS, 0 );

		foreach ( $this->apps->all() as $app ) {
			++$counts['all'];

			if ( isset( $counts[ $app['framework'] ] ) ) {
				++$counts[ $app['framework'] ];
			}
		}

		include BZEM_PLUGIN_PATH . 'templates/admin-header.php';
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
		$app['active']    = ! empty( $_POST['active'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		if ( isset( $_POST['display'] ) ) {
			$display = sanitize_key( wp_unslash( $_POST['display'] ) );
			$height  = isset( $_POST['frame_height'] ) ? sanitize_key( wp_unslash( $_POST['frame_height'] ) ) : 'auto';
			$pixels  = isset( $_POST['frame_px'] ) ? absint( wp_unslash( $_POST['frame_px'] ) ) : 0;

			$app['display']      = 'frame' === $display ? 'frame' : 'inline';
			$app['frame_height'] = 'fixed' === $height ? (string) min( 5000, max( 50, $pixels ? $pixels : 600 ) ) : ( 'viewport' === $height ? 'viewport' : 'auto' );
		}
		// phpcs:enable

		/**
		 * Filter an app record as the edit form saves it, after the nonce and
		 * capability checks. Pro modules read their own fields from $_POST here.
		 *
		 * @param array $app    Record about to be saved.
		 * @param bool  $is_new Whether the app is being created.
		 */
		$app = (array) apply_filters( 'bzem/save_app', $app, $is_new );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$has_zip = isset( $_FILES['build'] ) && is_array( $_FILES['build'] ) && ! empty( $_FILES['build']['name'] );

		if ( $has_zip ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified above; the uploader validates the file.
			$app = $this->apply_upload( $app, $_FILES['build'] );
		} elseif ( ! $is_new && '' !== $app['build'] ) {
			$app = $this->apply_entries( $app );
		}

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
		$app['base']              = $found['base'];
		$app['preload_relocated'] = false;

		if ( '' !== $found['base'] ) {
			$moved                    = $this->rewriter->rewrite( $result['dir'], $found['base'], $this->apps->build_ref( $app['slug'], $app['build'] ) );
			$app['preload_relocated'] = $moved['preload'];

			if ( $moved['references'] || $moved['preload'] ) {
				$message = sprintf(
					/* translators: 1: base path such as "/" or "/my-app/", 2: number of references, 3: number of files. */
					__( 'This build was compiled for the path %1$s, so %2$s references to its images, fonts and other files in %3$s JS/CSS files were pointed at their new location.', 'banzaiembed' ),
					$found['base'],
					number_format_i18n( $moved['references'] ),
					number_format_i18n( $moved['files'] )
				);

				if ( $moved['preload'] ) {
					$message .= ' ' . __( 'Its lazy-loaded chunks were pointed there too.', 'banzaiembed' );
				}

				$this->notice( 'info', $message );
			}
		}

		$app = $this->check_rebuild( $app );

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
			// Detection's complaints were about what it found; the user has
			// now chosen for themselves.
			$app['detected_by'] = 'manual';
			$app['warnings']    = array();
		}

		$app['scripts']     = $scripts;
		$app['styles']      = $styles;
		$app['script_type'] = 'classic' === $type ? 'classic' : 'module';

		return $this->check_rebuild( $app );
	}

	/**
	 * Add or drop the "rebuild with a relative base" warning, which depends
	 * on which scripts are entries and so can change when they do.
	 *
	 * @param array $app Record.
	 * @return array Updated record.
	 */
	private function check_rebuild( array $app ) {
		$warning         = Path_Rewriter::rebuild_warning( $app['base'] );
		$app['warnings'] = array_values( array_diff( $app['warnings'], array( $warning ) ) );

		if ( Path_Rewriter::needs_rebuild( $app, $this->apps->build_assets( $app ) ) ) {
			$app['warnings'][] = $warning;
		}

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
	 * Switch an app on or off from the list screen.
	 *
	 * A plain form post that redirects back, so the switch works without
	 * JavaScript; admin.js sends the same form with `ajax=1` and gets JSON.
	 */
	public function handle_toggle() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified below, before anything changes.
		$ajax = ! empty( $_POST['ajax'] );
		$slug = isset( $_POST['app'] ) ? App_Manager::sanitize_slug( wp_unslash( $_POST['app'] ) ) : '';
		$on   = ! empty( $_POST['active'] );
		// phpcs:enable

		if ( ! self::can_manage() ) {
			$message = __( 'You are not allowed to manage BanzaiEmbed apps.', 'banzaiembed' );

			if ( $ajax ) {
				wp_send_json_error( $message, 403 );
			}

			wp_die( esc_html( $message ), 403 );
		}

		if ( $ajax ) {
			if ( ! check_ajax_referer( self::TOGGLE_ACTION . '_' . $slug, '_wpnonce', false ) ) {
				wp_send_json_error( __( 'Your session has expired. Reload the page and try again.', 'banzaiembed' ), 403 );
			}
		} else {
			check_admin_referer( self::TOGGLE_ACTION . '_' . $slug );
		}

		$app  = $this->apps->get( $slug );
		$back = wp_get_referer() ? wp_get_referer() : self::list_url();

		if ( ! $app ) {
			if ( $ajax ) {
				wp_send_json_error( __( 'That app no longer exists.', 'banzaiembed' ), 404 );
			}

			$this->fail( $back, __( 'That app no longer exists.', 'banzaiembed' ) );
		}

		$app['active'] = $on;
		$this->apps->save( $app );

		$message = $on
			/* translators: %s: app name. */
			? sprintf( __( '"%s" activated.', 'banzaiembed' ), $app['name'] )
			/* translators: %s: app name. */
			: sprintf( __( '"%s" deactivated. Pages that embed it now show nothing to visitors.', 'banzaiembed' ), $app['name'] );

		if ( $ajax ) {
			wp_send_json_success(
				array(
					'active'  => $on,
					'message' => $message,
				)
			);
		}

		$this->notice( 'success', $message );
		wp_safe_redirect( $back );
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
