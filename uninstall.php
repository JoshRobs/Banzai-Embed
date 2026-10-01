<?php
/**
 * Uninstall: remove every app record and every uploaded build.
 *
 * Runs only when the plugin is deleted from the Plugins screen, not on
 * deactivation — deactivating must never cost anyone their uploaded apps.
 *
 * @package BanzaiEmbed
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'banzaiembed_apps' );

$bzem_uploads = wp_upload_dir( null, false );
$bzem_dir     = trailingslashit( $bzem_uploads['basedir'] ) . 'banzaiembed';

if ( is_dir( $bzem_dir ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

	( new WP_Filesystem_Direct( null ) )->delete( $bzem_dir, true );
}
