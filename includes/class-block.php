<?php
/**
 * Block.
 *
 * @package BanzaiEmbed
 */

namespace BanzaiEmbed;

defined( 'ABSPATH' ) || exit;

/**
 * The BanzaiEmbed block: a dynamic block whose front end is the shortcode's
 * output, and whose editor view is a placeholder with an app picker.
 *
 * There is no build step, so the editor script is plain ES5 against the
 * wp.* globals and its handle is registered here rather than via a
 * `file:` path in block.json (which needs a generated .asset.php).
 */
final class Block {

	const NAME = 'banzaiembed/app';

	const EDITOR_HANDLE = 'bzem-block-editor';

	/**
	 * @var Embed
	 */
	private $embed;

	/**
	 * @var App_Manager
	 */
	private $apps;

	/**
	 * Constructor.
	 *
	 * @param Embed       $embed Renderer.
	 * @param App_Manager $apps  App store.
	 */
	public function __construct( Embed $embed, App_Manager $apps ) {
		$this->embed = $embed;
		$this->apps  = $apps;
	}

	/**
	 * Hook in.
	 */
	public function register() {
		add_action( 'init', array( $this, 'add' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_data' ) );
	}

	/**
	 * Register the editor assets and the block.
	 */
	public function add() {
		wp_register_script(
			self::EDITOR_HANDLE,
			BZEM_PLUGIN_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n' ),
			BZEM_VERSION,
			true
		);
		wp_set_script_translations( self::EDITOR_HANDLE, 'banzaiembed' );

		wp_register_style(
			self::EDITOR_HANDLE,
			BZEM_PLUGIN_URL . 'assets/css/block-editor.css',
			array(),
			BZEM_VERSION
		);

		register_block_type(
			BZEM_PLUGIN_PATH . 'blocks/app',
			array( 'render_callback' => array( $this, 'render' ) )
		);
	}

	/**
	 * Give the editor the list of apps to pick from.
	 */
	public function editor_data() {
		$apps = array();

		foreach ( $this->apps->all() as $app ) {
			$status = App_Manager::status( $app );
			$apps[] = array(
				'slug'        => $app['slug'],
				'name'        => $app['name'],
				'framework'   => App_Manager::framework_label( $app['framework'] ),
				'mountId'     => App_Manager::mount_id( $app ),
				'status'      => $status,
				'statusLabel' => App_Manager::status_label( $status ),
			);
		}

		$data = array(
			'apps'     => $apps,
			'adminUrl' => current_user_can( 'manage_options' ) ? admin_url( 'admin.php?page=' . Admin::PAGE ) : '',
		);

		wp_add_inline_script( self::EDITOR_HANDLE, 'window.bzemBlockData=' . wp_json_encode( $data ) . ';', 'before' );
	}

	/**
	 * Front-end output.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render( $attributes ) {
		$attributes = is_array( $attributes ) ? $attributes : array();

		return $this->embed->render(
			isset( $attributes['app'] ) ? $attributes['app'] : '',
			array(
				'id'    => isset( $attributes['mountId'] ) ? $attributes['mountId'] : '',
				'class' => isset( $attributes['className'] ) ? $attributes['className'] : '',
				'style' => isset( $attributes['inlineStyle'] ) ? $attributes['inlineStyle'] : '',
			)
		);
	}
}
