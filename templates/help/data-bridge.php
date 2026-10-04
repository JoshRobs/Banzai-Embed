<?php
/**
 * Help topic: Data Bridge and environment variables (Pro).
 *
 * @package BanzaiEmbed
 *
 * @var string $slug App slug for examples.
 */

defined( 'ABSPATH' ) || exit;
?>
<p class="bzem-help-lead"><?php esc_html_e( 'Give your app information from WordPress — about the page it\'s on, the site, or the visitor — without writing any PHP.', 'banzaiembed' ); ?></p>

<div class="bzem-help-when">
	<div>
		<h3><?php esc_html_e( 'Use it when', 'banzaiembed' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'One app is used on many pages and should behave differently on each — a calculator that starts from each property\'s price.', 'banzaiembed' ); ?></li>
			<li><?php esc_html_e( 'The app greets or serves the logged-in visitor, or calls the WordPress REST API as them.', 'banzaiembed' ); ?></li>
			<li><?php esc_html_e( 'Settings such as an API address differ between your staging and live sites.', 'banzaiembed' ); ?></li>
		</ul>
	</div>
	<div>
		<h3><?php esc_html_e( 'Skip it when', 'banzaiembed' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'The app needs nothing from WordPress.', 'banzaiembed' ); ?></li>
			<li><?php esc_html_e( 'The value is a secret, like an API key. Page data and environment variables are visible in the page source.', 'banzaiembed' ); ?></li>
		</ul>
	</div>
</div>

<h3><?php esc_html_e( 'Three kinds of data', 'banzaiembed' ); ?></h3>
<ul>
	<li><strong><?php esc_html_e( 'Page data', 'banzaiembed' ); ?></strong> — <?php esc_html_e( 'the current post\'s ID, title, address or custom fields, site details, or text you type. Read it from cfg.data.', 'banzaiembed' ); ?></li>
	<li><strong><?php esc_html_e( 'Environment variables', 'banzaiembed' ); ?></strong> — <?php esc_html_e( 'text values with a separate staging value. Read them from cfg.env.', 'banzaiembed' ); ?></li>
	<li><strong><?php esc_html_e( 'User data', 'banzaiembed' ); ?></strong> — <?php esc_html_e( 'the logged-in visitor\'s name, email, roles or a REST API nonce, only the fields you tick. Fetched fresh for each visitor, so a page cache can never show one visitor\'s details to another.', 'banzaiembed' ); ?></li>
</ul>
<pre class="bzem-code">const cfg = window.banzaiEmbed['<?php echo esc_html( $slug ); ?>'];

cfg.data.price          // page data
cfg.env.API_URL         // environment variable
const user = await cfg.user();  // user data
if (user.loggedIn) { … }</pre>
