<?php
/**
 * Help topic: Routing.
 *
 * @package BanzaiEmbed
 *
 * @var string $slug App slug for examples.
 */

defined( 'ABSPATH' ) || exit;
?>
<p class="bzem-help-lead"><?php esc_html_e( 'Routing makes links to your app\'s inner screens work. Without it, opening or refreshing one shows "Page not found".', 'banzaiembed' ); ?></p>

<div class="bzem-help-test">
	<strong><?php esc_html_e( 'The quick test', 'banzaiembed' ); ?></strong>
	<?php esc_html_e( 'Click into one of your app\'s screens, copy the address from the address bar and open it in a new tab. If you see "Page not found", turn Routing on.', 'banzaiembed' ); ?>
</div>

<div class="bzem-help-when">
	<div>
		<h3><?php esc_html_e( 'Use it when', 'banzaiembed' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'Your app has several screens, and the address bar changes as you move between them — /games/ → /games/play/chess.', 'banzaiembed' ); ?></li>
			<li><?php esc_html_e( 'Your app uses a router: Vue Router, React Router or similar.', 'banzaiembed' ); ?></li>
		</ul>
	</div>
	<div>
		<h3><?php esc_html_e( 'Skip it when', 'banzaiembed' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'Your app is a single screen and the address never changes.', 'banzaiembed' ); ?></li>
			<li><?php esc_html_e( 'The address only changes after a # — /games/#/play/chess. Those links already work.', 'banzaiembed' ); ?></li>
		</ul>
	</div>
</div>

<h3><?php esc_html_e( 'Why it\'s needed', 'banzaiembed' ); ?></h3>
<p><?php esc_html_e( 'Your app is on one page, say /games/. When a visitor moves to another screen, the app changes the address to /games/play/chess by itself, without asking WordPress. That\'s fine until someone refreshes, bookmarks or shares it: then the browser asks WordPress for /games/play/chess, WordPress has no such page, and shows "Page not found".', 'banzaiembed' ); ?></p>
<p><?php esc_html_e( 'Routing tells WordPress: anything below /games/ that isn\'t a real page belongs to the app, so show the Games page. The app starts, reads the address and shows the right screen.', 'banzaiembed' ); ?></p>

<h3><?php esc_html_e( 'Setting it up', 'banzaiembed' ); ?></h3>
<ol>
	<li><?php esc_html_e( 'Turn on "This app has its own router".', 'banzaiembed' ); ?></li>
	<li><?php esc_html_e( 'Tick the page the app is on.', 'banzaiembed' ); ?></li>
	<li><?php esc_html_e( 'Then, depending on the app\'s Display:', 'banzaiembed' ); ?></li>
</ol>
<div class="bzem-help-when">
	<div>
		<h3><?php esc_html_e( 'Isolated', 'banzaiembed' ); ?></h3>
		<p><?php esc_html_e( 'Nothing else to do. The app keeps the addresses it was built with, and the page\'s address bar follows it.', 'banzaiembed' ); ?></p>
	</div>
	<div>
		<h3><?php esc_html_e( 'In the page', 'banzaiembed' ); ?></h3>
		<p><?php esc_html_e( 'Your router needs to know its screens start below the page. Give it the page\'s path:', 'banzaiembed' ); ?></p>
		<pre class="bzem-code">const cfg = window.banzaiEmbed['<?php echo esc_html( $slug ); ?>'];

// Vue Router
createWebHistory(cfg.basePath)

// React Router
&lt;BrowserRouter basename={cfg.basePath}&gt;</pre>
	</div>
</div>

<h3><?php esc_html_e( 'Good to know', 'banzaiembed' ); ?></h3>
<ul>
	<li><?php esc_html_e( 'Real pages below the app\'s page — /games/about/ — still open as normal.', 'banzaiembed' ); ?></li>
	<li><?php esc_html_e( 'Give your app a "not found" screen of its own: every address below the page now opens the app, even ones it doesn\'t know.', 'banzaiembed' ); ?></li>
	<li><?php esc_html_e( 'Not available on the front page or the posts page, and it needs pretty permalinks (Settings → Permalinks, anything but Plain).', 'banzaiembed' ); ?></li>
	<li><?php esc_html_e( 'Search engines see one page: every screen shares the page\'s address as its canonical URL.', 'banzaiembed' ); ?></li>
</ul>
