<?php
/**
 * Help topic: Entry files.
 *
 * @package BanzaiEmbed
 *
 * @var string $slug App slug for examples.
 */

defined( 'ABSPATH' ) || exit;
?>
<p class="bzem-help-lead"><?php esc_html_e( 'Entry files are the scripts and stylesheets that start your app. BanzaiEmbed finds them for you when you upload; you only need this if your app doesn\'t appear.', 'banzaiembed' ); ?></p>

<h3><?php esc_html_e( 'How they are found', 'banzaiembed' ); ?></h3>
<p><?php echo wp_kses( __( 'From your build\'s <code>index.html</code> first — exactly what your build tool would load — then from Vite\'s or Create React App\'s manifest, then by file name. The edit screen says which was used.', 'banzaiembed' ), array( 'code' => array() ) ); ?></p>

<h3><?php esc_html_e( 'If nothing appears', 'banzaiembed' ); ?></h3>
<ol>
	<li><?php esc_html_e( 'Check the app is switched on and its status is Ready. While you\'re logged in, the page shows a message where the app should be if something is wrong.', 'banzaiembed' ); ?></li>
	<li><?php esc_html_e( 'Open your browser\'s developer console (F12) on the page. A red error usually names the file or the problem.', 'banzaiembed' ); ?></li>
	<li><?php echo wp_kses( __( 'Check the mount element ID matches the one your code mounts to — what you pass to <code>app.mount()</code> or <code>createRoot()</code>.', 'banzaiembed' ), array( 'code' => array() ) ); ?></li>
	<li><?php esc_html_e( 'If the wrong files were picked, list the right ones under Entry files, one per line, in the order they should load. "Files in this build" lists them all.', 'banzaiembed' ); ?></li>
</ol>

<h3><?php esc_html_e( 'Script type', 'banzaiembed' ); ?></h3>
<p><?php esc_html_e( 'Vite and most modern builds need "ES module". Older webpack and Create React App builds need "Classic". If the console says "Cannot use import statement outside a module", switch to ES module; if it complains about "export", switch to Classic.', 'banzaiembed' ); ?></p>
