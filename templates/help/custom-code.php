<?php
/**
 * Help topic: Custom CSS & JS.
 *
 * @package BanzaiEmbed
 *
 * @var string $slug App slug for examples.
 */

defined( 'ABSPATH' ) || exit;
?>
<p class="bzem-help-lead"><?php esc_html_e( 'Small changes to how your app looks or starts, without rebuilding it.', 'banzaiembed' ); ?></p>

<div class="bzem-help-when">
	<div>
		<h3><?php esc_html_e( 'Use it when', 'banzaiembed' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'The app needs a size, spacing or colour tweak on this site.', 'banzaiembed' ); ?></li>
			<li><?php esc_html_e( 'You want to set something before the app starts, or run tracking once it has appeared.', 'banzaiembed' ); ?></li>
		</ul>
	</div>
	<div>
		<h3><?php esc_html_e( 'Skip it when', 'banzaiembed' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'The change is large or permanent. It belongs in the app\'s own code.', 'banzaiembed' ); ?></li>
		</ul>
	</div>
</div>

<h3><?php esc_html_e( 'Three boxes', 'banzaiembed' ); ?></h3>
<ul>
	<li><strong><?php esc_html_e( 'CSS', 'banzaiembed' ); ?></strong> — <?php esc_html_e( 'loads after the app\'s own styles. Start selectors with the app\'s class so they only reach this app:', 'banzaiembed' ); ?></li>
</ul>
<pre class="bzem-code">.bzem-app-<?php echo esc_html( $slug ); ?> { max-width: 900px; margin: 0 auto; }</pre>
<ul>
	<li><strong><?php esc_html_e( 'JavaScript before the app', 'banzaiembed' ); ?></strong> — <?php esc_html_e( 'runs just before the app starts, with its settings available as cfg.', 'banzaiembed' ); ?></li>
	<li><strong><?php esc_html_e( 'JavaScript after the app', 'banzaiembed' ); ?></strong> — <?php esc_html_e( 'runs once the app has appeared on the page.', 'banzaiembed' ); ?></li>
</ul>

<h3><?php esc_html_e( 'Good to know', 'banzaiembed' ); ?></h3>
<ul>
	<li><?php esc_html_e( 'For an isolated app, the CSS and JavaScript run inside its frame, next to the app.', 'banzaiembed' ); ?></li>
	<li><?php esc_html_e( 'Up to about 50 KB per box.', 'banzaiembed' ); ?></li>
</ul>
