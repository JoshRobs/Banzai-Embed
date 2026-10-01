<?php
/**
 * Shortcode.
 *
 * @package BanzaiEmbed
 */

namespace BanzaiEmbed;

defined( 'ABSPATH' ) || exit;

/**
 * [banzai-embed app="my-app" id="" class="" style=""]
 */
final class Shortcode {

	const TAG = 'banzai-embed';

	/**
	 * @var Embed
	 */
	private $embed;

	/**
	 * Constructor.
	 *
	 * @param Embed $embed Renderer.
	 */
	public function __construct( Embed $embed ) {
		$this->embed = $embed;
	}

	/**
	 * Hook in.
	 */
	public function register() {
		add_action( 'init', array( $this, 'add' ) );
	}

	/**
	 * Register the shortcode.
	 */
	public function add() {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Render it.
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'app'   => '',
				'id'    => '',
				'class' => '',
				'style' => '',
			),
			$atts,
			self::TAG
		);

		return $this->embed->render( $atts['app'], $atts );
	}
}
