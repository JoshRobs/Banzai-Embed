<?php
/**
 * Custom CSS & JS card on the edit screen (pro).
 *
 * Rendered by Custom_Code::render_card(), inside the edit form. admin.js
 * upgrades the textareas to WordPress's code editor when it is available.
 *
 * @package BanzaiEmbed
 *
 * @var array $app  Record.
 * @var array $code Custom_Code::config().
 */

defined( 'ABSPATH' ) || exit;

$scope = '.bzem-app-' . $app['slug'];
?>
<div class="bzem-card bzem-custom-code">
	<input type="hidden" name="bzem_custom_code" value="1">
	<h2><?php esc_html_e( 'Custom CSS & JS', 'banzaiembed' ); ?></h2>

	<h3><label for="bzem-custom-css"><?php esc_html_e( 'CSS', 'banzaiembed' ); ?></label></h3>
	<p class="description">
		<?php
		/* translators: %s: CSS class, e.g. ".bzem-app-my-app". */
		echo esc_html( sprintf( __( 'Loaded with the app, after its own stylesheets. Not scoped for you: every mount element has the class %s, so start your selectors with it.', 'banzaiembed' ), $scope ) );
		?>
	</p>
	<textarea id="bzem-custom-css" name="custom_css" rows="8" class="large-text code bzem-code-editor" data-mode="css" placeholder="<?php echo esc_attr( $scope . " {\n  min-height: 400px;\n}" ); ?>"><?php echo esc_textarea( $code['css'] ); ?></textarea>

	<h3><label for="bzem-custom-js-before"><?php esc_html_e( 'JavaScript before the app', 'banzaiembed' ); ?></label></h3>
	<p class="description"><?php esc_html_e( 'Runs before the app\'s first script — for configuration the app reads when it starts. cfg is window.banzaiEmbed for this app.', 'banzaiembed' ); ?></p>
	<textarea id="bzem-custom-js-before" name="custom_js_before" rows="6" class="large-text code bzem-code-editor" data-mode="js" placeholder="cfg.theme = 'dark';"><?php echo esc_textarea( $code['js_before'] ); ?></textarea>

	<h3><label for="bzem-custom-js-after"><?php esc_html_e( 'JavaScript after the app', 'banzaiembed' ); ?></label></h3>
	<p class="description"><?php esc_html_e( 'Runs once the app has rendered into its mount element (or when the page has loaded, if the element is missing or already filled) — for event listeners and analytics. Runs once per page, however many copies of the app there are. cfg is available here too.', 'banzaiembed' ); ?></p>
	<textarea id="bzem-custom-js-after" name="custom_js_after" rows="6" class="large-text code bzem-code-editor" data-mode="js" placeholder="document.getElementById(cfg.mountId).addEventListener('click', ...);"><?php echo esc_textarea( $code['js_after'] ); ?></textarea>

	<p class="description"><?php esc_html_e( 'This code runs for every visitor to a page with the app on it. Both scripts run inside a function, so their variables stay local; assign to window to share something.', 'banzaiembed' ); ?></p>
</div>
