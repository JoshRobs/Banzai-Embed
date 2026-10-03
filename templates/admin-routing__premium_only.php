<?php
/**
 * Routing card on the edit screen (Pro). Not in the free build.
 *
 * @package BanzaiEmbed
 *
 * @var array             $record    App being edited.
 * @var bool              $is_new    Whether it is being created.
 * @var bool              $licensed  Whether routing may be changed.
 * @var array             $config    Routing::config().
 * @var array[]           $choices   { page: \WP_Post, depth: int, embeds: bool }.
 * @var array<int,string> $taken     Page ID => name of the app already routing it.
 * @var bool              $permalink Whether the site has pretty permalinks.
 */

defined( 'ABSPATH' ) || exit;

$bzem_slug = '' !== $record['slug'] ? $record['slug'] : 'my-app';
?>
<section class="bzem-card bzem-card-pro bzem-routing-card">
	<header class="bzem-card-header">
		<h2><span class="bzem-card-icon dashicons dashicons-randomize" aria-hidden="true"></span><?php esc_html_e( 'Routing', 'banzaiembed' ); ?></h2>
		<span class="bzem-pro-badge"><span class="dashicons dashicons-star-filled" aria-hidden="true"></span><?php esc_html_e( 'Pro', 'banzaiembed' ); ?></span>
	</header>
	<div class="bzem-card-body">
		<?php if ( ! $licensed ) : ?>
			<div class="notice notice-info inline">
				<p>
					<?php
					echo $config['enabled']
						? esc_html__( 'This app\'s routes keep working. Changing routing needs an active BanzaiEmbed Pro licence.', 'banzaiembed' )
						: esc_html__( 'Client-side routing needs an active BanzaiEmbed Pro licence.', 'banzaiembed' );
					?>
					<a href="<?php echo esc_url( bzem_fs()->get_upgrade_url() ); ?>"><?php esc_html_e( 'View plans', 'banzaiembed' ); ?></a>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( ! $permalink ) : ?>
			<div class="notice notice-warning inline">
				<p>
					<?php esc_html_e( 'Routing needs pretty permalinks; this site uses plain ones (?page_id=…), so there are no paths to route.', 'banzaiembed' ); ?>
					<a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>"><?php esc_html_e( 'Permalink settings', 'banzaiembed' ); ?></a>
				</p>
			</div>
		<?php endif; ?>

		<?php // Disabled, nothing here is posted — not even the marker — so the saved settings stay. ?>
		<fieldset class="bzem-fieldset" <?php disabled( ! $licensed ); ?>>
			<input type="hidden" name="bzem_routing" value="1">

			<label class="bzem-switch-field">
				<input type="checkbox" name="routing_enabled" value="1" class="bzem-switch-input" data-bzem-routing-toggle <?php checked( $config['enabled'] ); ?>>
				<span class="bzem-switch" aria-hidden="true"></span>
				<span class="bzem-switch-text"><?php esc_html_e( 'This app has its own router', 'banzaiembed' ); ?></span>
			</label>
			<p class="description"><?php esc_html_e( 'For apps using React Router, Vue Router or similar. Paths below the page the app is on — /portal/settings, /portal/orders/42 — load that page instead of a "not found" page, and your app\'s router shows the right screen.', 'banzaiembed' ); ?></p>

			<div class="bzem-routing-details" data-bzem-routing-details>
				<h3 class="bzem-section-title"><?php esc_html_e( 'Pages it routes under', 'banzaiembed' ); ?></h3>
				<?php if ( ! $choices ) : ?>
					<p class="description"><?php esc_html_e( 'This site has no pages that can carry routes yet. The front page and posts page cannot.', 'banzaiembed' ); ?></p>
				<?php else : ?>
					<label class="screen-reader-text" for="bzem-routing-filter"><?php esc_html_e( 'Filter pages', 'banzaiembed' ); ?></label>
					<input type="search" id="bzem-routing-filter" class="bzem-page-filter" placeholder="<?php esc_attr_e( 'Filter pages…', 'banzaiembed' ); ?>" hidden>
					<div class="bzem-checklist" data-bzem-routing-pages>
						<?php foreach ( $choices as $bzem_choice ) : ?>
							<?php
							$bzem_page  = $bzem_choice['page'];
							$bzem_taken = isset( $taken[ $bzem_page->ID ] ) ? $taken[ $bzem_page->ID ] : '';
							?>
							<label style="<?php echo esc_attr( 'padding-inline-start:' . ( 4 + 18 * $bzem_choice['depth'] ) . 'px' ); ?>">
								<input type="checkbox" name="routing_pages[]" value="<?php echo esc_attr( $bzem_page->ID ); ?>" <?php checked( in_array( $bzem_page->ID, $config['pages'], true ) ); ?> <?php disabled( '' !== $bzem_taken ); ?>>
								<?php echo esc_html( '' !== $bzem_page->post_title ? $bzem_page->post_title : __( '(no title)', 'banzaiembed' ) ); ?>
								<code class="bzem-route-path"><?php echo esc_html( '/' . get_page_uri( $bzem_page ) . '/' ); ?></code>
								<?php if ( $bzem_choice['embeds'] ) : ?>
									<span class="bzem-route-tag"><?php esc_html_e( 'Embeds this app', 'banzaiembed' ); ?></span>
								<?php endif; ?>
								<?php if ( '' !== $bzem_taken ) : ?>
									<span class="bzem-optional">
										<?php
										/* translators: %s: another app's name. */
										echo esc_html( sprintf( __( 'Routed by "%s"', 'banzaiembed' ), $bzem_taken ) );
										?>
									</span>
								<?php endif; ?>
								<?php if ( 'private' === $bzem_page->post_status ) : ?>
									<span class="bzem-optional"><?php esc_html_e( 'Private', 'banzaiembed' ); ?></span>
								<?php endif; ?>
							</label>
						<?php endforeach; ?>
					</div>
					<p class="description"><?php esc_html_e( 'Choose the page your app is embedded on. Real child pages of it still load as normal; any other path below it shows this page.', 'banzaiembed' ); ?></p>
				<?php endif; ?>

				<h3 class="bzem-section-title"><?php esc_html_e( 'In your app', 'banzaiembed' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Give your router the page\'s path as its base, so its routes are relative to it:', 'banzaiembed' ); ?></p>
				<pre class="bzem-code">const cfg = window.banzaiEmbed['<?php echo esc_html( $bzem_slug ); ?>'];
<?php if ( 'vue' === $record['framework'] ) : ?>
const router = createRouter({
  history: createWebHistory(cfg.basePath), // e.g. "/portal"
  routes,
});
<?php elseif ( 'react' === $record['framework'] ) : ?>
&lt;BrowserRouter basename={cfg.basePath}&gt;  {/* e.g. "/portal" */}
  &lt;App /&gt;
&lt;/BrowserRouter&gt;
<?php else : ?>
cfg.basePath // e.g. "/portal" — your router's base
cfg.route    // the path below it, e.g. "orders/42"
<?php endif; ?></pre>
				<div class="bzem-tip bzem-tip-static">
					<p><?php esc_html_e( 'Every path answers with the page itself, so give your router a catch-all "not found" route. Search engines see one page: every route shares the page\'s canonical URL.', 'banzaiembed' ); ?></p>
				</div>
			</div>
		</fieldset>
	</div>
</section>
