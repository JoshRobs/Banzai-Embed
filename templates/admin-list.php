<?php
/**
 * All Apps screen.
 *
 * @package BanzaiEmbed
 *
 * @var array[] $apps Records keyed by slug.
 */

use BanzaiEmbed\Admin;
use BanzaiEmbed\App_Manager;

defined( 'ABSPATH' ) || exit;
?>
<h1 class="wp-heading-inline"><?php esc_html_e( 'Apps', 'banzaiembed' ); ?></h1>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Admin::PAGE_NEW ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New App', 'banzaiembed' ); ?></a>
<hr class="wp-header-end">

<?php if ( ! $apps ) : ?>
	<div class="bzem-empty">
		<h2><?php esc_html_e( 'Embed your first app', 'banzaiembed' ); ?></h2>
		<p><?php esc_html_e( 'Build your Vue or React app as usual, zip the contents of dist/ or build/, and upload it. You will get a shortcode and a block to place it anywhere.', 'banzaiembed' ); ?></p>
		<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Admin::PAGE_NEW ) ); ?>"><?php esc_html_e( 'Add New App', 'banzaiembed' ); ?></a></p>
	</div>
<?php else : ?>
	<table class="wp-list-table widefat fixed striped bzem-apps">
		<thead>
			<tr>
				<th scope="col" class="column-primary"><?php esc_html_e( 'Name', 'banzaiembed' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Framework', 'banzaiembed' ); ?></th>
				<th scope="col" class="bzem-col-shortcode"><?php esc_html_e( 'Shortcode', 'banzaiembed' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Uploaded', 'banzaiembed' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'banzaiembed' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $apps as $app ) : ?>
				<?php
				$status    = App_Manager::status( $app );
				$shortcode = App_Manager::shortcode( $app );
				?>
				<tr>
					<td class="column-primary" data-colname="<?php esc_attr_e( 'Name', 'banzaiembed' ); ?>">
						<strong><a class="row-title" href="<?php echo esc_url( Admin::edit_url( $app['slug'] ) ); ?>"><?php echo esc_html( $app['name'] ); ?></a></strong>
						<div class="row-actions">
							<span class="edit"><a href="<?php echo esc_url( Admin::edit_url( $app['slug'] ) ); ?>"><?php esc_html_e( 'Edit', 'banzaiembed' ); ?></a> | </span>
							<span class="copy"><button type="button" class="button-link bzem-copy" data-copy="<?php echo esc_attr( $shortcode ); ?>"><?php esc_html_e( 'Copy Shortcode', 'banzaiembed' ); ?></button> | </span>
							<span class="trash"><a class="submitdelete bzem-delete" href="<?php echo esc_url( Admin::delete_url( $app['slug'] ) ); ?>" data-name="<?php echo esc_attr( $app['name'] ); ?>"><?php esc_html_e( 'Delete', 'banzaiembed' ); ?></a></span>
						</div>
						<button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'banzaiembed' ); ?></span></button>
					</td>
					<td data-colname="<?php esc_attr_e( 'Framework', 'banzaiembed' ); ?>"><?php echo esc_html( App_Manager::framework_label( $app['framework'] ) ); ?></td>
					<td data-colname="<?php esc_attr_e( 'Shortcode', 'banzaiembed' ); ?>"><code class="bzem-shortcode"><?php echo esc_html( $shortcode ); ?></code></td>
					<td data-colname="<?php esc_attr_e( 'Uploaded', 'banzaiembed' ); ?>">
						<?php echo $app['uploaded'] ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $app['uploaded'] ) ) : '&mdash;'; ?>
					</td>
					<td data-colname="<?php esc_attr_e( 'Status', 'banzaiembed' ); ?>">
						<span class="bzem-status bzem-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( App_Manager::status_label( $status ) ); ?></span>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
