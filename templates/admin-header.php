<?php
/**
 * Brand bar and framework tabs, above every BanzaiEmbed screen.
 *
 * @package BanzaiEmbed
 *
 * @var string $tab    Highlighted tab: 'all', a framework, 'new', 'help' or ''.
 * @var int[]  $counts Apps per framework, plus 'all'.
 */

use BanzaiEmbed\Admin;
use BanzaiEmbed\App_Manager;
use BanzaiEmbed\Help;

defined( 'ABSPATH' ) || exit;

$tabs = array( 'all' => __( 'All Apps', 'banzaiembed' ) );

foreach ( App_Manager::FRAMEWORKS as $fw ) {
	$tabs[ $fw ] = App_Manager::framework_label( $fw );
}
?>
<div class="bzem-header">
	<a class="bzem-brand" href="<?php echo esc_url( Admin::list_url() ); ?>">
		<?php echo Admin::logo( 36 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
		<span class="bzem-brand-name">Banzai<span>Embed</span></span>
	</a>
	<div class="bzem-header-actions">
		<?php
		/**
		 * Print controls at the right of the brand bar. Pro shows the
		 * licence status here.
		 */
		do_action( 'bzem/header_actions' );
		?>
		<a class="bzem-header-help<?php echo 'help' === $tab ? ' is-current' : ''; ?>" href="<?php echo esc_url( Help::url() ); ?>"<?php echo 'help' === $tab ? ' aria-current="page"' : ''; ?>>
			<span class="dashicons dashicons-editor-help" aria-hidden="true"></span><?php esc_html_e( 'Help', 'banzaiembed' ); ?>
		</a>
		<span class="bzem-version">v<?php echo esc_html( BZEM_VERSION ); ?></span>
	</div>
</div>

<nav class="bzem-nav" aria-label="<?php esc_attr_e( 'BanzaiEmbed apps', 'banzaiembed' ); ?>">
	<div class="bzem-nav-tabs">
		<?php foreach ( $tabs as $key => $label ) : ?>
			<a
				class="bzem-nav-tab<?php echo $tab === $key ? ' is-current' : ''; ?>"
				href="<?php echo esc_url( Admin::list_url( 'all' === $key ? array() : array( 'framework' => $key ) ) ); ?>"
				<?php echo $tab === $key ? 'aria-current="page"' : ''; ?>
			>
				<?php echo esc_html( $label ); ?>
				<span class="bzem-nav-count"><?php echo esc_html( number_format_i18n( $counts[ $key ] ) ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
	<a
		class="button button-primary bzem-nav-add"
		href="<?php echo esc_url( Admin::new_url() ); ?>"
		<?php echo 'new' === $tab ? 'aria-current="page"' : ''; ?>
	>
		<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
		<?php esc_html_e( 'Add New App', 'banzaiembed' ); ?>
	</a>
</nav>
