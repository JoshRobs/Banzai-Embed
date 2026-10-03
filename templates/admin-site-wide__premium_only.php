<?php
/**
 * Placement card on the edit screen (Pro). Not in the free build.
 *
 * @package BanzaiEmbed
 *
 * @var array      $record     App being edited.
 * @var bool       $is_new     Whether it is being created.
 * @var bool       $licensed   Whether placement and rules may be changed.
 * @var bool       $site_wide  Whether the app is placed site-wide now.
 * @var array      $rules      App_Manager::rules() for it.
 * @var string[]   $post_types name => label.
 * @var \WP_Post[] $pages      Published and private pages, in menu order.
 */

defined( 'ABSPATH' ) || exit;

// Indent child pages under their parents.
$bzem_depth = array();

foreach ( $pages as $bzem_page ) {
	$bzem_depth[ $bzem_page->ID ] = isset( $bzem_depth[ $bzem_page->post_parent ] ) ? $bzem_depth[ $bzem_page->post_parent ] + 1 : 0;
}

$bzem_scope = $rules['post_types'] ? 'post_types' : 'all';
?>
<section class="bzem-card bzem-placement-card">
	<header class="bzem-card-header">
		<h2><?php esc_html_e( 'Placement', 'banzaiembed' ); ?></h2>
		<span class="bzem-pro-badge"><?php esc_html_e( 'Pro', 'banzaiembed' ); ?></span>
	</header>
	<div class="bzem-card-body">
		<?php if ( ! $licensed ) : ?>
			<div class="notice notice-info inline">
				<p>
					<?php
					echo $site_wide
						? esc_html__( 'This app keeps showing site-wide. Changing where it shows needs an active BanzaiEmbed Pro licence.', 'banzaiembed' )
						: esc_html__( 'Showing an app site-wide needs an active BanzaiEmbed Pro licence.', 'banzaiembed' );
					?>
					<a href="<?php echo esc_url( bzem_fs()->get_upgrade_url() ); ?>"><?php esc_html_e( 'View plans', 'banzaiembed' ); ?></a>
				</p>
			</div>
		<?php endif; ?>

		<fieldset class="bzem-field">
			<legend class="screen-reader-text"><?php esc_html_e( 'Placement', 'banzaiembed' ); ?></legend>
			<div class="bzem-choice-grid">
				<label class="bzem-choice">
					<input type="radio" name="placement" value="shortcode" <?php checked( ! $site_wide ); ?>>
					<span class="bzem-choice-tile">
						<span class="dashicons dashicons-shortcode" aria-hidden="true"></span>
						<span class="bzem-choice-name"><?php esc_html_e( 'Shortcode or block', 'banzaiembed' ); ?></span>
						<span class="bzem-choice-hint"><?php esc_html_e( 'Shows only where you place it.', 'banzaiembed' ); ?></span>
					</span>
				</label>
				<label class="bzem-choice">
					<input type="radio" name="placement" value="site_wide" <?php checked( $site_wide ); ?> <?php disabled( ! $licensed && ! $site_wide ); ?>>
					<span class="bzem-choice-tile">
						<span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>
						<span class="bzem-choice-name"><?php esc_html_e( 'Site-wide', 'banzaiembed' ); ?></span>
						<span class="bzem-choice-hint"><?php esc_html_e( 'Shows on every page its rules match — no shortcode needed.', 'banzaiembed' ); ?></span>
					</span>
				</label>
			</div>
		</fieldset>

		<div class="bzem-rules" data-bzem-rules>
			<fieldset class="bzem-field" <?php disabled( ! $licensed ); ?>>
				<legend><?php esc_html_e( 'Show on', 'banzaiembed' ); ?></legend>
				<div class="bzem-radio-list">
					<label><input type="radio" name="rules_scope" value="all" <?php checked( 'all', $bzem_scope ); ?>> <?php esc_html_e( 'Every page', 'banzaiembed' ); ?></label>
					<label><input type="radio" name="rules_scope" value="post_types" <?php checked( 'post_types', $bzem_scope ); ?>> <?php esc_html_e( 'Only on these post types', 'banzaiembed' ); ?></label>
				</div>
				<div class="bzem-checklist bzem-checklist-inline" data-bzem-scope-types>
					<?php foreach ( $post_types as $name => $label ) : ?>
						<label><input type="checkbox" name="rules_post_types[]" value="<?php echo esc_attr( $name ); ?>" <?php checked( in_array( $name, $rules['post_types'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
					<?php endforeach; ?>
				</div>
				<p class="description"><?php esc_html_e( 'Post types mean their single pages: Posts shows the app on each post, not on the blog index or archives.', 'banzaiembed' ); ?></p>
			</fieldset>

			<fieldset class="bzem-field" <?php disabled( ! $licensed ); ?>>
				<legend><?php esc_html_e( 'Never show on these pages', 'banzaiembed' ); ?> <span class="bzem-optional"><?php esc_html_e( 'Optional', 'banzaiembed' ); ?></span></legend>
				<?php if ( ! $pages ) : ?>
					<p class="description"><?php esc_html_e( 'This site has no pages yet.', 'banzaiembed' ); ?></p>
				<?php else : ?>
					<label class="screen-reader-text" for="bzem-page-filter"><?php esc_html_e( 'Filter pages', 'banzaiembed' ); ?></label>
					<input type="search" id="bzem-page-filter" class="bzem-page-filter" placeholder="<?php esc_attr_e( 'Filter pages…', 'banzaiembed' ); ?>" hidden>
					<div class="bzem-checklist" data-bzem-pages>
						<?php foreach ( $pages as $bzem_page ) : ?>
							<label style="<?php echo esc_attr( 'padding-inline-start:' . ( 4 + 18 * $bzem_depth[ $bzem_page->ID ] ) . 'px' ); ?>">
								<input type="checkbox" name="rules_exclude[]" value="<?php echo esc_attr( $bzem_page->ID ); ?>" <?php checked( in_array( $bzem_page->ID, $rules['exclude'], true ) ); ?>>
								<?php echo esc_html( '' !== $bzem_page->post_title ? $bzem_page->post_title : __( '(no title)', 'banzaiembed' ) ); ?>
								<?php if ( 'private' === $bzem_page->post_status ) : ?>
									<span class="bzem-optional"><?php esc_html_e( 'Private', 'banzaiembed' ); ?></span>
								<?php endif; ?>
							</label>
						<?php endforeach; ?>
					</div>
					<p class="description"><?php esc_html_e( 'A checkout or login page, for example.', 'banzaiembed' ); ?></p>
				<?php endif; ?>
			</fieldset>

			<fieldset class="bzem-field" <?php disabled( ! $licensed ); ?>>
				<legend><?php esc_html_e( 'Visitors', 'banzaiembed' ); ?></legend>
				<div class="bzem-radio-list">
					<label><input type="radio" name="rules_audience" value="everyone" <?php checked( 'everyone', $rules['audience'] ); ?>> <?php esc_html_e( 'Everyone', 'banzaiembed' ); ?></label>
					<label><input type="radio" name="rules_audience" value="logged_in" <?php checked( 'logged_in', $rules['audience'] ); ?>> <?php esc_html_e( 'Logged-in visitors only', 'banzaiembed' ); ?></label>
					<label><input type="radio" name="rules_audience" value="logged_out" <?php checked( 'logged_out', $rules['audience'] ); ?>> <?php esc_html_e( 'Logged-out visitors only', 'banzaiembed' ); ?></label>
				</div>
			</fieldset>

			<div class="bzem-tip bzem-tip-static">
				<p><?php esc_html_e( 'The mount point is added at the end of each page, so position the app with its own CSS — position: fixed suits chat widgets and buttons.', 'banzaiembed' ); ?></p>
				<p><?php esc_html_e( 'If a page also embeds this app by shortcode or block, the site-wide copy gets a suffixed ID; your app mounts once unless it loops over window.banzaiEmbed[…].mounts.', 'banzaiembed' ); ?></p>
			</div>
		</div>
	</div>
</section>
