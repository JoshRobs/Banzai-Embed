<?php
/**
 * Help topic: Site-wide placement (Pro).
 *
 * @package BanzaiEmbed
 *
 * @var string $slug App slug for examples.
 */

defined( 'ABSPATH' ) || exit;
?>
<p class="bzem-help-lead"><?php esc_html_e( 'Show an app on every page without adding a shortcode to each one.', 'banzaiembed' ); ?></p>

<div class="bzem-help-when">
	<div>
		<h3><?php esc_html_e( 'Use it when', 'banzaiembed' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'The app floats over the site or sits at its edge: a chat button, a feedback tab, a cookie or announcement bar.', 'banzaiembed' ); ?></li>
			<li><?php esc_html_e( 'It should appear on all pages, or all pages of one kind — every product, every post.', 'banzaiembed' ); ?></li>
		</ul>
	</div>
	<div>
		<h3><?php esc_html_e( 'Skip it when', 'banzaiembed' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'The app belongs in a page\'s content, in a particular spot. Use the shortcode or block there.', 'banzaiembed' ); ?></li>
		</ul>
	</div>
</div>

<h3><?php esc_html_e( 'Choosing where it shows', 'banzaiembed' ); ?></h3>
<ul>
	<li><strong><?php esc_html_e( 'Show on', 'banzaiembed' ); ?></strong> — <?php esc_html_e( 'every page, or only the single pages of the post types you tick (Posts means each post, not the blog list).', 'banzaiembed' ); ?></li>
	<li><strong><?php esc_html_e( 'Never show on these pages', 'banzaiembed' ); ?></strong> — <?php esc_html_e( 'pages to leave it off, such as checkout or a landing page.', 'banzaiembed' ); ?></li>
	<li><strong><?php esc_html_e( 'Visitors', 'banzaiembed' ); ?></strong> — <?php esc_html_e( 'everyone, only logged-in visitors, or only logged-out visitors.', 'banzaiembed' ); ?></li>
</ul>

<h3><?php esc_html_e( 'Good to know', 'banzaiembed' ); ?></h3>
<ul>
	<li><?php esc_html_e( 'The app is added at the end of the page. Position it with your app\'s own CSS — for example position: fixed in a corner.', 'banzaiembed' ); ?></li>
	<li><?php esc_html_e( 'Site-wide apps are always shown in the page, never isolated, so they can sit over it.', 'banzaiembed' ); ?></li>
</ul>
