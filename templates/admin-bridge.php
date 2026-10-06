<?php
/**
 * Data Bridge card on the edit screen.
 *
 * Rendered by Data_Bridge::render_card(), inside the edit form. Rows are
 * plain inputs plus one blank row, so the card works without JavaScript;
 * admin.js adds and removes rows by cloning the <template>s.
 *
 * @package BanzaiEmbed
 *
 * @var array                 $app     Record.
 * @var array                 $config  Data_Bridge::config().
 * @var array<string,string>  $sources Data_Bridge::sources().
 * @var array<string,string>  $fields  Data_Bridge::user_fields().
 * @var string                $env     wp_get_environment_type().
 * @var string                $usage   Example code.
 */

use BanzaiEmbed\Data_Bridge;

defined( 'ABSPATH' ) || exit;

$value_row = static function ( $i, array $row ) use ( $sources ) {
	$takes = Data_Bridge::takes_value( $row['source'] );
	?>
	<tr class="bzem-bridge-row">
		<td><input type="text" name="bridge_values[<?php echo esc_attr( $i ); ?>][key]" class="code" pattern="[A-Za-z_$][A-Za-z0-9_$]*" maxlength="64" placeholder="planName" aria-label="<?php esc_attr_e( 'Key', 'banzaiembed' ); ?>" value="<?php echo esc_attr( $row['key'] ); ?>"></td>
		<td>
			<select name="bridge_values[<?php echo esc_attr( $i ); ?>][source]" class="bzem-bridge-source" aria-label="<?php esc_attr_e( 'Source', 'banzaiembed' ); ?>">
				<?php foreach ( $sources as $source => $label ) : ?>
					<option value="<?php echo esc_attr( $source ); ?>" <?php selected( $row['source'], $source ); ?>
						<?php if ( Data_Bridge::takes_value( $source ) ) : ?>
							data-placeholder="<?php echo esc_attr( 'post.meta' === $source ? __( 'Custom field key', 'banzaiembed' ) : __( 'Value', 'banzaiembed' ) ); ?>"
						<?php endif; ?>
					><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<td><input type="text" name="bridge_values[<?php echo esc_attr( $i ); ?>][value]" class="bzem-bridge-value" aria-label="<?php esc_attr_e( 'Value', 'banzaiembed' ); ?>" placeholder="<?php echo esc_attr( 'post.meta' === $row['source'] ? __( 'Custom field key', 'banzaiembed' ) : __( 'Value', 'banzaiembed' ) ); ?>" value="<?php echo esc_attr( $row['value'] ); ?>"<?php echo $takes ? '' : ' hidden'; ?>></td>
		<td><button type="button" class="button-link bzem-bridge-remove" aria-label="<?php esc_attr_e( 'Remove', 'banzaiembed' ); ?>">&times;</button></td>
	</tr>
	<?php
};

$env_row = static function ( $i, array $row ) {
	?>
	<tr class="bzem-bridge-row">
		<td><input type="text" name="bridge_env[<?php echo esc_attr( $i ); ?>][key]" class="code" pattern="[A-Za-z_$][A-Za-z0-9_$]*" maxlength="64" placeholder="API_URL" aria-label="<?php esc_attr_e( 'Key', 'banzaiembed' ); ?>" value="<?php echo esc_attr( $row['key'] ); ?>"></td>
		<td><input type="text" name="bridge_env[<?php echo esc_attr( $i ); ?>][value]" aria-label="<?php esc_attr_e( 'Production value', 'banzaiembed' ); ?>" value="<?php echo esc_attr( $row['value'] ); ?>"></td>
		<td><input type="text" name="bridge_env[<?php echo esc_attr( $i ); ?>][staging]" aria-label="<?php esc_attr_e( 'Staging value', 'banzaiembed' ); ?>" placeholder="<?php esc_attr_e( 'Same as production', 'banzaiembed' ); ?>" value="<?php echo esc_attr( $row['staging'] ); ?>"></td>
		<td><button type="button" class="button-link bzem-bridge-remove" aria-label="<?php esc_attr_e( 'Remove', 'banzaiembed' ); ?>">&times;</button></td>
	</tr>
	<?php
};

$blank_value = array(
	'key'    => '',
	'source' => 'static',
	'value'  => '',
);
$blank_env   = array(
	'key'     => '',
	'value'   => '',
	'staging' => '',
);
?>
<section class="bzem-card bzem-bridge">
	<header class="bzem-card-header">
		<h2><span class="bzem-card-icon dashicons dashicons-database-export" aria-hidden="true"></span><?php esc_html_e( 'Data Bridge', 'banzaiembed' ); ?><?php \BanzaiEmbed\Help::button( 'data-bridge' ); ?></h2>
	</header>
	<div class="bzem-card-body">
	<fieldset class="bzem-fieldset">
	<input type="hidden" name="bzem_bridge" value="1">
	<p class="description">
		<?php
		/* translators: %s: app slug. */
		echo esc_html( sprintf( __( 'WordPress data your app reads from window.banzaiEmbed[\'%s\'].', 'banzaiembed' ), $app['slug'] ) );
		?>
	</p>

	<h3 class="bzem-section-title"><?php esc_html_e( 'Page data', 'banzaiembed' ); ?> <code>cfg.data</code></h3>
	<p class="description"><?php esc_html_e( 'Written into the page, so the same for every visitor and visible in the page source. Post values are for the post being viewed; elsewhere they are null.', 'banzaiembed' ); ?></p>
	<table class="widefat bzem-bridge-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Key', 'banzaiembed' ); ?></th>
				<th><?php esc_html_e( 'Source', 'banzaiembed' ); ?></th>
				<th><?php esc_html_e( 'Value', 'banzaiembed' ); ?></th>
				<th><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'banzaiembed' ); ?></span></th>
			</tr>
		</thead>
		<tbody class="bzem-bridge-rows">
			<?php
			foreach ( $config['values'] as $i => $row ) {
				$value_row( $i, $row );
			}
			$value_row( count( $config['values'] ), $blank_value );
			?>
		</tbody>
	</table>
	<template class="bzem-bridge-template"><?php $value_row( '__i__', $blank_value ); ?></template>
	<p><button type="button" class="button bzem-bridge-add"><?php esc_html_e( 'Add value', 'banzaiembed' ); ?></button></p>

	<h3 class="bzem-section-title"><?php esc_html_e( 'Environment variables', 'banzaiembed' ); ?> <code>cfg.env</code></h3>
	<p class="description">
		<?php
		/* translators: %s: environment type, e.g. "production". */
		echo esc_html( sprintf( __( 'Text values, written into the page. The staging value is used when the environment type (WP_ENVIRONMENT_TYPE) is not production. This site is: %s.', 'banzaiembed' ), $env ) );
		?>
	</p>
	<table class="widefat bzem-bridge-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Key', 'banzaiembed' ); ?></th>
				<th><?php esc_html_e( 'Production', 'banzaiembed' ); ?></th>
				<th><?php esc_html_e( 'Staging', 'banzaiembed' ); ?></th>
				<th><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'banzaiembed' ); ?></span></th>
			</tr>
		</thead>
		<tbody class="bzem-bridge-rows">
			<?php
			foreach ( $config['env'] as $i => $row ) {
				$env_row( $i, $row );
			}
			$env_row( count( $config['env'] ), $blank_env );
			?>
		</tbody>
	</table>
	<template class="bzem-bridge-template"><?php $env_row( '__i__', $blank_env ); ?></template>
	<p><button type="button" class="button bzem-bridge-add"><?php esc_html_e( 'Add variable', 'banzaiembed' ); ?></button></p>

	<h3 class="bzem-section-title"><?php esc_html_e( 'User data', 'banzaiembed' ); ?> <code>cfg.user()</code></h3>
	<p class="description"><?php esc_html_e( 'Fetched by your app when it calls cfg.user(), never written into the page, so page caches cannot show one visitor\'s data to another. Each visitor only ever receives their own. loggedIn is always included.', 'banzaiembed' ); ?></p>
	<fieldset class="bzem-bridge-user">
		<legend class="screen-reader-text"><?php esc_html_e( 'User fields', 'banzaiembed' ); ?></legend>
		<?php foreach ( $fields as $field => $label ) : ?>
			<label><input type="checkbox" name="bridge_user[]" value="<?php echo esc_attr( $field ); ?>" <?php checked( in_array( $field, $config['user'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
		<?php endforeach; ?>
	</fieldset>
	<p class="description"><?php esc_html_e( 'Email and roles are personal data: mention them in your privacy policy. The REST API nonce lets the app call the REST API as the logged-in user — send it as the X-WP-Nonce header.', 'banzaiembed' ); ?></p>

	<details class="bzem-tip">
		<summary><?php esc_html_e( 'Usage in your app', 'banzaiembed' ); ?></summary>
		<pre class="bzem-code"><?php echo esc_html( $usage ); ?></pre>
		<p class="description"><?php esc_html_e( 'Reflects the saved settings. Save to update it.', 'banzaiembed' ); ?></p>
	</details>
	</fieldset>
	</div>
</section>
