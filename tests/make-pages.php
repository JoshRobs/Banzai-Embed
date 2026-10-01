<?php
/**
 * Creates the front-end test pages for the apps tests/e2e.sh uploads.
 *
 *     npx @wordpress/env run cli wp eval-file wp-content/plugins/Banzai-Embed/tests/make-pages.php
 *
 * Prints "slug<TAB>URL" per page. Re-running replaces the pages.
 *
 * @package BanzaiEmbed
 */

$pages = array(
	// Two different apps on one page, plus hostile attribute values.
	'bzem-two-apps' => '<p>before</p>[banzai-embed app="vue-relative-wrapped"][banzai-embed app="react-relative-backslash" class="wide x&quot;y" style="min-height:300px;background:url(javascript:alert(1))"]<p>after</p>',
	// The block nested in a group, and the same app again via shortcode.
	'bzem-cra-twice' => '<!-- wp:group --><div class="wp-block-group"><!-- wp:banzaiembed/app {"app":"cra","inlineStyle":"min-height:50px","className":"my-x"} /--></div><!-- /wp:group -->[banzai-embed app="cra"]',
	// Built with base '/'. The first mounts; the second, with a custom id,
	// must not — the app's code targets #app and does not read `mounts`.
	'bzem-rooted' => '[banzai-embed app="vue-rooted"][banzai-embed app="vue-rooted" id="custom-id"]',
	// An unknown slug and an app with no build.
	'bzem-missing' => 'x[banzai-embed app="nope"][banzai-embed app="nothing"]y',
	// Filename-pattern detection, and the Vite manifest with no index.html.
	'bzem-pattern' => '[banzai-embed app="pattern"][banzai-embed app="react-manifest-only"]',
);

foreach ( $pages as $name => $content ) {
	$existing = get_page_by_path( $name, OBJECT, 'page' );

	if ( $existing ) {
		wp_delete_post( $existing->ID, true );
	}

	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $name,
			'post_name'    => $name,
			'post_content' => $content,
		)
	);

	echo $name, "\t", get_permalink( $id ), "\n";
}
