<?php
/**
 * All Apps screen.
 *
 * @package BanzaiEmbed
 *
 * @var array[] $apps   Records to show, filtered and sorted.
 * @var array   $query  { framework, status, s, orderby, order } from the URL.
 * @var int[]   $counts { all, active, inactive } in the current tab.
 * @var int     $total  Apps in every tab.
 */

use BanzaiEmbed\Admin;
use BanzaiEmbed\App_Manager;

defined( 'ABSPATH' ) || exit;

// Every link keeps the current tab. Choosing a view clears the search;
// searching keeps the view.
$tab_args = array_filter( array( 'framework' => $query['framework'] ) );
$views    = array(
	'all'      => __( 'All', 'banzaiembed' ),
	'active'   => __( 'Active', 'banzaiembed' ),
	'inactive' => __( 'Inactive', 'banzaiembed' ),
);
$filtered = '' !== $query['status'] || '' !== $query['s'];

/**
 * A sortable column heading.
 *
 * @param string $column  orderby value.
 * @param string $label   Heading text.
 * @param string $first   Order on the first click.
 */
$bzem_sort_link = static function ( $column, $label, $first ) use ( $query, $tab_args ) {
	$current = $column === $query['orderby'];
	$order   = $current ? ( 'asc' === $query['order'] ? 'desc' : 'asc' ) : $first;
	$args    = $tab_args + array_filter(
		array(
			'status'  => $query['status'],
			's'       => $query['s'],
			'orderby' => $column,
			'order'   => $order,
		)
	);

	printf(
		'<a class="bzem-sort%s" href="%s"><span>%s</span><span class="bzem-sort-arrow dashicons dashicons-arrow-%s" aria-hidden="true"></span></a>',
		$current ? ' is-sorted' : '',
		esc_url( Admin::list_url( $args ) ),
		esc_html( $label ),
		$current && 'desc' === $query['order'] ? 'down' : 'up'
	);
};
?>
<h1 class="screen-reader-text"><?php esc_html_e( 'All Apps', 'banzaiembed' ); ?></h1>
<hr class="wp-header-end">

<?php if ( ! $total ) : ?>

	<div class="bzem-empty">
		<?php echo Admin::logo( 64 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
		<h2><?php esc_html_e( 'Embed your first app', 'banzaiembed' ); ?></h2>
		<p><?php esc_html_e( 'Bring a Vue, React or vanilla JavaScript app into any page or post — no Node.js on the server.', 'banzaiembed' ); ?></p>
		<ol class="bzem-steps">
			<li><strong><?php esc_html_e( 'Build', 'banzaiembed' ); ?></strong> <?php esc_html_e( 'your app as you normally would.', 'banzaiembed' ); ?></li>
			<li><strong><?php esc_html_e( 'Zip', 'banzaiembed' ); ?></strong> <?php esc_html_e( 'the contents of dist/ or build/.', 'banzaiembed' ); ?></li>
			<li><strong><?php esc_html_e( 'Upload', 'banzaiembed' ); ?></strong> <?php esc_html_e( 'it, then drop the shortcode or block on any page.', 'banzaiembed' ); ?></li>
		</ol>
		<a class="button button-primary button-hero" href="<?php echo esc_url( Admin::new_url() ); ?>"><?php esc_html_e( 'Add your first app', 'banzaiembed' ); ?></a>
	</div>

<?php else : ?>

	<div class="bzem-toolbar">
		<ul class="bzem-views">
			<?php foreach ( $views as $key => $label ) : ?>
				<?php
				$is_current = ( 'all' === $key ? '' : $key ) === $query['status'];
				$args       = $tab_args + array_filter( array( 'status' => 'all' === $key ? '' : $key ) );
				?>
				<li>
					<a href="<?php echo esc_url( Admin::list_url( $args ) ); ?>" class="<?php echo $is_current ? 'is-current' : ''; ?>" <?php echo $is_current ? 'aria-current="page"' : ''; ?>>
						<?php echo esc_html( $label ); ?>
						<span class="bzem-view-count" data-bzem-count="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( number_format_i18n( $counts[ $key ] ) ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<form class="bzem-search" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" role="search">
			<input type="hidden" name="page" value="<?php echo esc_attr( Admin::PAGE ); ?>">
			<?php foreach ( array( 'framework', 'status' ) as $key ) : ?>
				<?php if ( '' !== $query[ $key ] ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $query[ $key ] ); ?>">
				<?php endif; ?>
			<?php endforeach; ?>
			<label class="screen-reader-text" for="bzem-search-input"><?php esc_html_e( 'Search apps', 'banzaiembed' ); ?></label>
			<span class="bzem-search-field">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<input type="search" id="bzem-search-input" name="s" value="<?php echo esc_attr( $query['s'] ); ?>" placeholder="<?php esc_attr_e( 'Search by name or slug', 'banzaiembed' ); ?>">
			</span>
			<button type="submit" class="button"><?php esc_html_e( 'Search Apps', 'banzaiembed' ); ?></button>
		</form>
	</div>

	<table class="wp-list-table widefat fixed bzem-apps">
		<thead>
			<tr>
				<th scope="col" class="column-primary bzem-col-name"><?php $bzem_sort_link( 'name', __( 'Name', 'banzaiembed' ), 'asc' ); ?></th>
				<th scope="col" class="bzem-col-framework"><?php esc_html_e( 'Framework', 'banzaiembed' ); ?></th>
				<th scope="col" class="bzem-col-shortcode"><?php esc_html_e( 'Shortcode', 'banzaiembed' ); ?></th>
				<th scope="col" class="bzem-col-mount"><?php esc_html_e( 'Mounts to', 'banzaiembed' ); ?></th>
				<th scope="col" class="bzem-col-build"><?php esc_html_e( 'Build', 'banzaiembed' ); ?></th>
				<th scope="col" class="bzem-col-updated"><?php $bzem_sort_link( 'modified', __( 'Last updated', 'banzaiembed' ), 'desc' ); ?></th>
				<th scope="col" class="bzem-col-active"><?php esc_html_e( 'Active', 'banzaiembed' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! $apps ) : ?>
				<tr class="no-items">
					<td colspan="7" class="bzem-no-results">
						<?php esc_html_e( 'No apps match.', 'banzaiembed' ); ?>
						<?php if ( $filtered ) : ?>
							<a href="<?php echo esc_url( Admin::list_url( $tab_args ) ); ?>"><?php esc_html_e( 'Clear filters', 'banzaiembed' ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endif; ?>

			<?php foreach ( $apps as $app ) : ?>
				<?php
				$status    = App_Manager::status( $app );
				$active    = App_Manager::is_active( $app );
				$shortcode = App_Manager::shortcode( $app );
				$modified  = App_Manager::modified( $app );
				?>
				<tr class="<?php echo $active ? '' : 'is-inactive'; ?>">
					<td class="column-primary bzem-col-name">
						<strong><a class="row-title" href="<?php echo esc_url( Admin::edit_url( $app['slug'] ) ); ?>"><?php echo esc_html( $app['name'] ); ?></a></strong>
						<div class="row-actions">
							<span class="edit"><a href="<?php echo esc_url( Admin::edit_url( $app['slug'] ) ); ?>"><?php esc_html_e( 'Edit', 'banzaiembed' ); ?></a> | </span>
							<span class="copy"><button type="button" class="button-link bzem-copy" data-copy="<?php echo esc_attr( $shortcode ); ?>"><?php esc_html_e( 'Copy Shortcode', 'banzaiembed' ); ?></button> | </span>
							<span class="trash"><a class="submitdelete bzem-delete" href="<?php echo esc_url( Admin::delete_url( $app['slug'] ) ); ?>" data-name="<?php echo esc_attr( $app['name'] ); ?>"><?php esc_html_e( 'Delete', 'banzaiembed' ); ?></a></span>
						</div>
						<button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'banzaiembed' ); ?></span></button>
					</td>
					<td class="bzem-col-framework" data-colname="<?php esc_attr_e( 'Framework', 'banzaiembed' ); ?>">
						<span class="bzem-fw bzem-fw-<?php echo esc_attr( $app['framework'] ); ?>">
							<?php echo Admin::framework_mark( $app['framework'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
							<?php echo esc_html( App_Manager::framework_label( $app['framework'] ) ); ?>
						</span>
					</td>
					<td class="bzem-col-shortcode" data-colname="<?php esc_attr_e( 'Shortcode', 'banzaiembed' ); ?>">
						<span class="bzem-chip">
							<code class="bzem-shortcode"><?php echo esc_html( $shortcode ); ?></code>
							<button type="button" class="bzem-copy bzem-icon-button" data-copy="<?php echo esc_attr( $shortcode ); ?>" aria-label="<?php esc_attr_e( 'Copy shortcode', 'banzaiembed' ); ?>" title="<?php esc_attr_e( 'Copy shortcode', 'banzaiembed' ); ?>">
								<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
							</button>
						</span>
					</td>
					<td class="bzem-col-mount" data-colname="<?php esc_attr_e( 'Mounts to', 'banzaiembed' ); ?>">
						<code>#<?php echo esc_html( App_Manager::mount_id( $app ) ); ?></code>
					</td>
					<td class="bzem-col-build" data-colname="<?php esc_attr_e( 'Build', 'banzaiembed' ); ?>">
						<span class="bzem-status bzem-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( App_Manager::status_label( $status ) ); ?></span>
						<?php if ( $app['warnings'] ) : ?>
							<a class="bzem-warning-count" href="<?php echo esc_url( Admin::edit_url( $app['slug'] ) ); ?>">
								<span class="dashicons dashicons-warning" aria-hidden="true"></span>
								<?php
								/* translators: %s: number of warnings. */
								echo esc_html( sprintf( _n( '%s warning', '%s warnings', count( $app['warnings'] ), 'banzaiembed' ), number_format_i18n( count( $app['warnings'] ) ) ) );
								?>
							</a>
						<?php endif; ?>
					</td>
					<td class="bzem-col-updated" data-colname="<?php esc_attr_e( 'Last updated', 'banzaiembed' ); ?>">
						<?php if ( $modified ) : ?>
							<span class="bzem-date"><?php echo esc_html( wp_date( get_option( 'date_format' ), $modified ) ); ?></span>
							<span class="bzem-time"><?php echo esc_html( wp_date( get_option( 'time_format' ), $modified ) ); ?></span>
						<?php else : ?>
							&mdash;
						<?php endif; ?>
					</td>
					<td class="bzem-col-active" data-colname="<?php esc_attr_e( 'Active', 'banzaiembed' ); ?>">
						<form class="bzem-toggle-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="<?php echo esc_attr( Admin::TOGGLE_ACTION ); ?>">
							<input type="hidden" name="app" value="<?php echo esc_attr( $app['slug'] ); ?>">
							<input type="hidden" name="active" value="<?php echo $active ? '0' : '1'; ?>">
							<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( Admin::TOGGLE_ACTION . '_' . $app['slug'] ) ); ?>">
							<?php wp_referer_field(); ?>
							<button
								type="submit"
								class="bzem-switch"
								role="switch"
								aria-checked="<?php echo $active ? 'true' : 'false'; ?>"
								<?php /* translators: %s: app name. */ ?>
								aria-label="<?php echo esc_attr( sprintf( __( '%s is active', 'banzaiembed' ), $app['name'] ) ); ?>"
								title="<?php echo $active ? esc_attr__( 'Deactivate', 'banzaiembed' ) : esc_attr__( 'Activate', 'banzaiembed' ); ?>"
							></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

<?php endif; ?>
