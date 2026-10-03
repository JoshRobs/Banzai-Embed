<?php
/**
 * Edit screen for an app that does not exist.
 *
 * @package BanzaiEmbed
 */

use BanzaiEmbed\Admin;

defined( 'ABSPATH' ) || exit;
?>
<hr class="wp-header-end">
<div class="bzem-empty">
	<h1><?php esc_html_e( 'App not found', 'banzaiembed' ); ?></h1>
	<p><?php esc_html_e( 'It may have been deleted.', 'banzaiembed' ); ?></p>
	<a class="button" href="<?php echo esc_url( Admin::list_url() ); ?>"><?php esc_html_e( 'Back to all apps', 'banzaiembed' ); ?></a>
</div>
