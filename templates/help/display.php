<?php
/**
 * Help topic: Display.
 *
 * @package BanzaiEmbed
 *
 * @var string $slug App slug for examples.
 */

defined( 'ABSPATH' ) || exit;
?>
<p class="bzem-help-lead"><?php esc_html_e( 'Choose whether your app shares the page with your theme, or gets a page of its own inside a frame.', 'banzaiembed' ); ?></p>

<div class="bzem-help-when">
	<div>
		<h3><?php esc_html_e( 'In the page — use when', 'banzaiembed' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'It\'s a widget — a calculator, a form, a map — that only styles its own elements.', 'banzaiembed' ); ?></li>
			<li><?php esc_html_e( 'You want it to pick up your theme\'s fonts and colours.', 'banzaiembed' ); ?></li>
			<li><?php esc_html_e( 'It was written with WordPress in mind.', 'banzaiembed' ); ?></li>
		</ul>
	</div>
	<div>
		<h3><?php esc_html_e( 'Isolated — use when', 'banzaiembed' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'Adding the app changed your site\'s fonts, colours, background or spacing.', 'banzaiembed' ); ?></li>
			<li><?php esc_html_e( 'Your theme makes the app look wrong.', 'banzaiembed' ); ?></li>
			<li><?php esc_html_e( 'It was built to run on its own site — Netlify, Vercel or similar — especially if it has several screens.', 'banzaiembed' ); ?></li>
		</ul>
	</div>
</div>

<h3><?php esc_html_e( 'Why it matters', 'banzaiembed' ); ?></h3>
<p><?php esc_html_e( 'Apps built to run on their own usually style the whole page — the background, every heading, every paragraph. In the page, those styles apply to your whole site, and your theme\'s styles apply to the app. Isolated, each keeps its own.', 'banzaiembed' ); ?></p>
<p><?php esc_html_e( 'Isolated also helps apps with several screens: inside the frame, the app\'s addresses start at the site root (/, /settings), just as on its own host, so its router works without changes. Turn on Routing too and the page\'s address bar follows the app.', 'banzaiembed' ); ?></p>

<h3><?php esc_html_e( 'Height of an isolated app', 'banzaiembed' ); ?></h3>
<ul>
	<li><strong><?php esc_html_e( 'Fit the content', 'banzaiembed' ); ?></strong> — <?php esc_html_e( 'the frame grows and shrinks with the app. The usual choice.', 'banzaiembed' ); ?></li>
	<li><strong><?php esc_html_e( 'Fill the window', 'banzaiembed' ); ?></strong> — <?php esc_html_e( 'the frame is as tall as the browser window and the app scrolls inside it. Good for games and full-screen tools.', 'banzaiembed' ); ?></li>
	<li><strong><?php esc_html_e( 'Fixed', 'banzaiembed' ); ?></strong> — <?php esc_html_e( 'a height you choose, in pixels.', 'banzaiembed' ); ?></li>
</ul>

<h3><?php esc_html_e( 'Good to know', 'banzaiembed' ); ?></h3>
<ul>
	<li><?php esc_html_e( 'The frame is as wide as the spot you put it in. For a full-width app, place it in a wide or full-width section of your page.', 'banzaiembed' ); ?></li>
	<li><?php esc_html_e( 'Search engines index the page around the frame, not the text inside the app.', 'banzaiembed' ); ?></li>
	<li><?php esc_html_e( 'Pop-ups and menus inside the app can\'t reach outside the frame.', 'banzaiembed' ); ?></li>
	<li><?php esc_html_e( 'Apps placed site-wide are always shown in the page.', 'banzaiembed' ); ?></li>
</ul>
