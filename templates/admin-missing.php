<?php
/**
 * Edit screen for an app that does not exist.
 *
 * @package BanzaiEmbed
 */

use BanzaiEmbed\Admin;

defined( 'ABSPATH' ) || exit;
?>
<h1><?php esc_html_e( 'App not found', 'banzaiembed' ); ?></h1>
<p><a href="<?php echo esc_url( Admin::list_url() ); ?>"><?php esc_html_e( '&larr; Back to all apps', 'banzaiembed' ); ?></a></p>
