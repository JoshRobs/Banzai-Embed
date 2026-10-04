<?php
/**
 * Help topic: API proxy (Pro).
 *
 * @package BanzaiEmbed
 *
 * @var string $slug App slug for examples.
 */

defined( 'ABSPATH' ) || exit;
?>
<p class="bzem-help-lead"><?php esc_html_e( 'Pass your app\'s calls to its own backend on to wherever that backend really runs.', 'banzaiembed' ); ?></p>

<div class="bzem-help-when">
	<div>
		<h3><?php esc_html_e( 'Use it when', 'banzaiembed' ); ?></h3>
		<ul>
			<li><?php echo wp_kses( __( 'Your app calls an address on its own site, like <code>fetch(\'/api/score\')</code>, and those calls fail with a 404 on WordPress.', 'banzaiembed' ), array( 'code' => array() ) ); ?></li>
			<li><?php esc_html_e( 'The backend is a Netlify or Vercel function, or a server of your own, that answered those calls where the app was hosted before.', 'banzaiembed' ); ?></li>
		</ul>
	</div>
	<div>
		<h3><?php esc_html_e( 'Skip it when', 'banzaiembed' ); ?></h3>
		<ul>
			<li><?php echo wp_kses( __( 'Your app calls full addresses, like <code>https://api.example.com/score</code>. Those already go straight there.', 'banzaiembed' ), array( 'code' => array() ) ); ?></li>
			<li><?php esc_html_e( 'Your app has no backend.', 'banzaiembed' ); ?></li>
		</ul>
	</div>
</div>

<h3><?php esc_html_e( 'Setting it up', 'banzaiembed' ); ?></h3>
<p><?php esc_html_e( 'Add a rule: the path your app calls, and the address to forward it to. A rule covers its path and everything below it, and the rest of the address is carried over:', 'banzaiembed' ); ?></p>
<pre class="bzem-code">/api  →  https://my-app.netlify.app/api

/api/score?day=3  is answered by  https://my-app.netlify.app/api/score?day=3</pre>
<p><?php esc_html_e( 'Moving from Netlify? Each line of your _redirects file that points at a function becomes a rule:', 'banzaiembed' ); ?></p>
<pre class="bzem-code">_redirects:  /api/judge  /.netlify/functions/judge  200
rule:        /api/judge  →  https://my-app.netlify.app/.netlify/functions/judge</pre>

<h3><?php esc_html_e( 'Good to know', 'banzaiembed' ); ?></h3>
<ul>
	<li><?php esc_html_e( 'Your app\'s code doesn\'t change, and there is no CORS to set up: the browser only ever talks to your WordPress site.', 'banzaiembed' ); ?></li>
	<li><?php esc_html_e( 'The visitor\'s cookies and WordPress login are never passed on, and the backend can\'t set cookies on your site.', 'banzaiembed' ); ?></li>
	<li><?php esc_html_e( 'A rule can\'t use a path that belongs to WordPress (such as /wp-json) or to an existing page.', 'banzaiembed' ); ?></li>
</ul>
