<?php
/**
 * Help: the Help screen and the "?" pop-ups on the edit screen.
 *
 * @package BanzaiEmbed
 */

namespace BanzaiEmbed;

defined( 'ABSPATH' ) || exit;

/**
 * One source for every help topic, shown two ways: all together on the
 * Help screen, organised by what someone is trying to fix, and one at a
 * time in a pop-up from the "?" on the card it explains — where the
 * decision is made, without leaving an unsaved form.
 *
 * Each topic is a template in templates/help/{id}.php. They hold no logic
 * beyond the app slug for code examples, so the pop-up and the page cannot
 * say different things.
 *
 * Pro topics are listed in the free build too, marked Pro, while there is
 * a Pro to buy (License::is_pro_available()).
 */
final class Help {

	/**
	 * Admin page slug.
	 */
	const PAGE = 'banzaiembed-help';

	/**
	 * Topics, in the order the Help screen lists them.
	 *
	 * @return array<string, array{title: string, icon: string, pro: bool}>
	 */
	public static function topics() {
		$topics = array(
			'getting-started' => array(
				'title' => __( 'Getting started', 'banzaiembed' ),
				'icon'  => 'dashicons-flag',
				'pro'   => false,
			),
			'display'         => array(
				'title' => __( 'Display: in the page or isolated', 'banzaiembed' ),
				'icon'  => 'dashicons-desktop',
				'pro'   => false,
			),
			'entry-files'     => array(
				'title' => __( 'Entry files', 'banzaiembed' ),
				'icon'  => 'dashicons-media-code',
				'pro'   => false,
			),
			'routing'         => array(
				'title' => __( 'Routing', 'banzaiembed' ),
				'icon'  => 'dashicons-randomize',
				'pro'   => true,
			),
			'placement'       => array(
				'title' => __( 'Site-wide placement', 'banzaiembed' ),
				'icon'  => 'dashicons-location',
				'pro'   => true,
			),
			'data-bridge'     => array(
				'title' => __( 'Data Bridge and environment variables', 'banzaiembed' ),
				'icon'  => 'dashicons-database-export',
				'pro'   => true,
			),
			'api-proxy'       => array(
				'title' => __( 'API proxy', 'banzaiembed' ),
				'icon'  => 'dashicons-cloud',
				'pro'   => true,
			),
			'custom-code'     => array(
				'title' => __( 'Custom CSS & JS', 'banzaiembed' ),
				'icon'  => 'dashicons-editor-code',
				'pro'   => true,
			),
		);

		if ( ! License::is_pro_available() ) {
			$topics = array_filter(
				$topics,
				static function ( $topic ) {
					return ! $topic['pro'];
				}
			);
		}

		return $topics;
	}

	/**
	 * Problems people arrive with, each pointing at the topic that fixes it.
	 *
	 * @return array<int, array{0: string, 1: string}> [ symptom, topic ID ].
	 */
	public static function symptoms() {
		$symptoms = array(
			array( __( 'Nothing appears where I put the shortcode or block', 'banzaiembed' ), 'entry-files' ),
			array( __( 'Images, fonts or some screens of my app don\'t load', 'banzaiembed' ), 'getting-started' ),
			array( __( 'My app changed my site\'s fonts, colours or spacing', 'banzaiembed' ), 'display' ),
			array( __( 'My theme\'s styles break the way my app looks', 'banzaiembed' ), 'display' ),
			array( __( 'Opening or refreshing a link to one of my app\'s screens shows "Page not found"', 'banzaiembed' ), 'routing' ),
			array( __( 'My app\'s calls to /api/… fail with a 404', 'banzaiembed' ), 'api-proxy' ),
			array( __( 'My app needs to know which page it\'s on, or who is logged in', 'banzaiembed' ), 'data-bridge' ),
			array( __( 'I want my app on every page, like a chat button', 'banzaiembed' ), 'placement' ),
			array( __( 'I need to tweak my app\'s size or add tracking without rebuilding it', 'banzaiembed' ), 'custom-code' ),
		);
		$topics   = self::topics();

		return array_values(
			array_filter(
				$symptoms,
				static function ( $symptom ) use ( $topics ) {
					return isset( $topics[ $symptom[1] ] );
				}
			)
		);
	}

	/**
	 * URL of the Help screen, optionally at a topic.
	 *
	 * @param string $topic Topic ID, or ''.
	 * @return string
	 */
	public static function url( $topic = '' ) {
		return admin_url( 'admin.php?page=' . self::PAGE ) . ( '' !== $topic ? '#' . $topic : '' );
	}

	/**
	 * The "?" that opens a topic in a pop-up. Without JavaScript it is a
	 * link to the topic on the Help screen.
	 *
	 * @param string $topic Topic ID.
	 */
	public static function button( $topic ) {
		$topics = self::topics();

		if ( ! isset( $topics[ $topic ] ) ) {
			return;
		}

		printf(
			'<a class="bzem-help-button" href="%1$s" target="_blank" rel="noopener" data-bzem-help="%2$s" aria-label="%3$s" title="%4$s"><span class="dashicons dashicons-editor-help" aria-hidden="true"></span></a>',
			esc_url( self::url( $topic ) ),
			esc_attr( $topic ),
			/* translators: %s: help topic title. */
			esc_attr( sprintf( __( 'Help: %s', 'banzaiembed' ), $topics[ $topic ]['title'] ) ),
			esc_attr__( 'When to use this', 'banzaiembed' )
		);
	}

	/**
	 * Print one topic's body.
	 *
	 * @param string $topic Topic ID.
	 * @param string $slug  App slug for code examples.
	 */
	public static function render_topic( $topic, $slug = 'my-app' ) {
		$file = BZEM_PLUGIN_PATH . 'templates/help/' . sanitize_key( $topic ) . '.php';

		if ( isset( self::topics()[ $topic ] ) && is_file( $file ) ) {
			include $file;
		}
	}

	/**
	 * The pop-up, and every topic ready to go in it, for the edit screen.
	 *
	 * @param string $slug App slug for code examples.
	 */
	public static function print_dialog( $slug ) {
		$slug = '' !== $slug ? $slug : 'my-app';
		?>
		<dialog class="bzem-help-dialog" aria-labelledby="bzem-help-dialog-title">
			<div class="bzem-help-dialog-head">
				<h2 id="bzem-help-dialog-title"></h2>
				<button type="button" class="bzem-help-dialog-close" data-bzem-help-close aria-label="<?php esc_attr_e( 'Close', 'banzaiembed' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
			</div>
			<div class="bzem-help-dialog-body bzem-help-topic"></div>
			<div class="bzem-help-dialog-foot">
				<a class="bzem-help-dialog-more" href="<?php echo esc_url( self::url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open the full help page', 'banzaiembed' ); ?> <span class="dashicons dashicons-external" aria-hidden="true"></span></a>
			</div>
		</dialog>
		<?php foreach ( self::topics() as $id => $topic ) : ?>
			<template data-bzem-help-topic="<?php echo esc_attr( $id ); ?>" data-title="<?php echo esc_attr( $topic['title'] ); ?>" data-pro="<?php echo $topic['pro'] ? '1' : ''; ?>" data-url="<?php echo esc_url( self::url( $id ) ); ?>">
				<?php self::render_topic( $id, $slug ); ?>
			</template>
			<?php
		endforeach;
	}
}
