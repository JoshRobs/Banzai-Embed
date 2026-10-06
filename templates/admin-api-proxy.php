<?php
/**
 * API proxy card on the edit screen.
 *
 * Rows are plain inputs plus one blank row, so the card works without
 * JavaScript; admin.js adds and removes rows as on the Data Bridge card.
 *
 * @package BanzaiEmbed
 *
 * @var array $app    Record.
 * @var array $config Api_Proxy::config().
 */

defined( 'ABSPATH' ) || exit;

$rule_row = static function ( $i, array $row ) {
	?>
	<tr class="bzem-bridge-row">
		<td><input type="text" name="proxy_rules[<?php echo esc_attr( $i ); ?>][path]" class="code" maxlength="200" placeholder="/api" aria-label="<?php esc_attr_e( 'Path', 'banzaiembed' ); ?>" value="<?php echo esc_attr( $row['path'] ); ?>"></td>
		<td><input type="url" name="proxy_rules[<?php echo esc_attr( $i ); ?>][target]" class="code" maxlength="500" placeholder="https://my-app.netlify.app/api" aria-label="<?php esc_attr_e( 'Forward to', 'banzaiembed' ); ?>" value="<?php echo esc_attr( $row['target'] ); ?>"></td>
		<td><button type="button" class="button-link bzem-bridge-remove" aria-label="<?php esc_attr_e( 'Remove', 'banzaiembed' ); ?>">&times;</button></td>
	</tr>
	<?php
};

$blank = array(
	'path'   => '',
	'target' => '',
);
?>
<section class="bzem-card bzem-proxy">
	<header class="bzem-card-header">
		<h2><span class="bzem-card-icon dashicons dashicons-cloud" aria-hidden="true"></span><?php esc_html_e( 'API proxy', 'banzaiembed' ); ?><?php \BanzaiEmbed\Help::button( 'api-proxy' ); ?></h2>
	</header>
	<div class="bzem-card-body">
		<fieldset class="bzem-fieldset">
			<input type="hidden" name="bzem_proxy" value="1">
			<p class="description"><?php esc_html_e( 'Does your app call its own backend, like fetch(\'/api/…\')? Forward those paths to the server that answers them — a Netlify or Vercel function, or your own API — and your app\'s code stays as it is. The browser only talks to this site, so there is no CORS to set up.', 'banzaiembed' ); ?></p>
			<table class="widefat bzem-bridge-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Path on this site', 'banzaiembed' ); ?></th>
						<th><?php esc_html_e( 'Forward to', 'banzaiembed' ); ?></th>
						<th><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'banzaiembed' ); ?></span></th>
					</tr>
				</thead>
				<tbody class="bzem-bridge-rows">
					<?php
					foreach ( $config['rules'] as $i => $row ) {
						$rule_row( $i, $row );
					}
					$rule_row( count( $config['rules'] ), $blank );
					?>
				</tbody>
			</table>
			<template class="bzem-bridge-template"><?php $rule_row( '__i__', $blank ); ?></template>
			<p><button type="button" class="button bzem-bridge-add"><?php esc_html_e( 'Add rule', 'banzaiembed' ); ?></button></p>

			<details class="bzem-tip">
				<summary><?php esc_html_e( 'How forwarding works', 'banzaiembed' ); ?></summary>
				<p><?php esc_html_e( 'A rule takes its path and everything below it: /api forwards /api/judge?x=1 to https://my-app.netlify.app/api/judge?x=1. The longest matching path wins. Paths start at the domain root, as your app writes them.', 'banzaiembed' ); ?></p>
				<p><?php esc_html_e( 'The method, body, content type, Accept and Authorization headers and your app\'s own X- headers are forwarded. The visitor\'s cookies and WordPress login are not, and the API cannot set cookies on this site.', 'banzaiembed' ); ?></p>
				<p><?php esc_html_e( 'Moving from Netlify? A _redirects line like "/api/judge /.netlify/functions/judge 200" becomes the rule /api/judge → https://your-site.netlify.app/.netlify/functions/judge.', 'banzaiembed' ); ?></p>
			</details>
		</fieldset>
	</div>
</section>
