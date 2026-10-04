<?php
/**
 * Help topic: Getting started.
 *
 * @package BanzaiEmbed
 *
 * @var string $slug App slug for examples.
 */

defined( 'ABSPATH' ) || exit;
?>
<p class="bzem-help-lead"><?php esc_html_e( 'BanzaiEmbed puts an app you have already built — Vue, React or plain JavaScript — on any page. Nothing is built on the server: you upload the files your build tool produced.', 'banzaiembed' ); ?></p>

<h3><?php esc_html_e( 'Four steps', 'banzaiembed' ); ?></h3>
<ol>
	<li><?php echo wp_kses( __( 'Build your app as you normally would, for example with <code>npm run build</code>.', 'banzaiembed' ), array( 'code' => array() ) ); ?></li>
	<li><?php echo wp_kses( __( 'Zip the output folder — usually <code>dist/</code> or <code>build/</code>. Zipping the folder itself or just its contents both work.', 'banzaiembed' ), array( 'code' => array() ) ); ?></li>
	<li><?php esc_html_e( 'Go to BanzaiEmbed → Add New, give the app a name and upload the zip. BanzaiEmbed works out which files start your app.', 'banzaiembed' ); ?></li>
	<li><?php esc_html_e( 'Put the shortcode on any page, or add the BanzaiEmbed App block and pick your app.', 'banzaiembed' ); ?></li>
</ol>
<pre class="bzem-code">[banzai-embed app="<?php echo esc_html( $slug ); ?>"]</pre>

<h3><?php esc_html_e( 'Build settings', 'banzaiembed' ); ?></h3>
<p><?php esc_html_e( 'Your files are served from your uploads folder, not the root of your site. Most builds work as they are: if yours was made for the site root (Vite\'s default), BanzaiEmbed points its images, fonts and lazy-loaded screens at the right place when you upload, and sends any file your app asks for at the site root to where it really is.', 'banzaiembed' ); ?></p>
<p><?php esc_html_e( 'If anything still fails to load, build with a relative base and upload again:', 'banzaiembed' ); ?></p>
<ul>
	<li><?php esc_html_e( 'Vite:', 'banzaiembed' ); ?> <code>base: './'</code></li>
	<li><?php esc_html_e( 'Create React App:', 'banzaiembed' ); ?> <code>"homepage": "."</code></li>
	<li><?php esc_html_e( 'webpack 5:', 'banzaiembed' ); ?> <code>output.publicPath: 'auto'</code></li>
</ul>

<h3><?php esc_html_e( 'Good to know', 'banzaiembed' ); ?></h3>
<ul>
	<li><?php esc_html_e( 'To update an app, upload the new zip under Replace build. Pages update straight away; the previous build stays online for anyone with an old copy of the page cached.', 'banzaiembed' ); ?></li>
	<li><?php esc_html_e( 'Turn an app off from the app list and it disappears from every page it is on, until you turn it back on.', 'banzaiembed' ); ?></li>
	<li><?php esc_html_e( 'If your app changes the look of your site, or your theme changes your app, see Display.', 'banzaiembed' ); ?></li>
</ul>
