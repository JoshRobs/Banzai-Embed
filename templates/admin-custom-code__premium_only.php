<?php
/**
 * Custom CSS & JS card on the edit screen (Pro). Not in the free build.
 *
 * Rendered by Custom_Code::render_card(), inside the edit form. admin.js
 * upgrades the textareas to WordPress's code editor when it is available.
 *
 * @package BanzaiEmbed
 *
 * @var array $app      Record.
 * @var array $code     Custom_Code::config().
 * @var bool  $licensed Whether the code may be changed.
 */

defined( 'ABSPATH' ) || exit;

$scope = '.bzem-app-' . $app['slug'];
?>
<section class="bzem-card bzem-card-pro bzem-custom-code">
	<header class="bzem-card-header">
		<h2><span class="bzem-card-icon dashicons dashicons-editor-code" aria-hidden="true"></span><?php esc_html_e( 'Custom CSS & JS', 'banzaiembed' ); ?><?php \BanzaiEmbed\Help::button( 'custom-code' ); ?></h2>
		<span class="bzem-pro-badge"><span class="dashicons dashicons-star-filled" aria-hidden="true"></span><?php esc_html_e( 'Pro', 'banzaiembed' ); ?></span>
	</header>
	<div class="bzem-card-body">
		<?php if ( ! $licensed ) : ?>
			<div class="notice notice-info inline">
				<p>
					<?php esc_html_e( 'This code keeps loading with your app. Changing it needs an active BanzaiEmbed Pro licence.', 'banzaiembed' ); ?>
					<a href="<?php echo esc_url( bzem_fs()->get_upgrade_url() ); ?>"><?php esc_html_e( 'View plans', 'banzaiembed' ); ?></a>
				</p>
			</div>
		<?php endif; ?>

		<?php // Disabled, nothing here is posted — not even the marker — so the saved code stays. ?>
		<fieldset class="bzem-fieldset" <?php disabled( ! $licensed ); ?>>
			<input type="hidden" name="bzem_custom_code" value="1">

			<h3 class="bzem-section-title"><label for="bzem-custom-css"><?php esc_html_e( 'CSS', 'banzaiembed' ); ?></label></h3>
			<p class="description">
				<?php
				/* translators: %s: CSS class, e.g. ".bzem-app-my-app". */
				echo esc_html( sprintf( __( 'Loaded with the app, after its own stylesheets. Not scoped for you: every mount element has the class %s, so start your selectors with it.', 'banzaiembed' ), $scope ) );
				?>
			</p>
			<textarea id="bzem-custom-css" name="custom_css" rows="8" class="large-text code bzem-code-editor" data-mode="css" placeholder="<?php echo esc_attr( $scope . " {\n  min-height: 400px;\n}" ); ?>"><?php echo esc_textarea( $code['css'] ); ?></textarea>

			<h3 class="bzem-section-title"><label for="bzem-custom-js-before"><?php esc_html_e( 'JavaScript before the app', 'banzaiembed' ); ?></label></h3>
			<p class="description"><?php esc_html_e( 'Runs before the app\'s first script — for configuration the app reads when it starts. cfg is window.banzaiEmbed for this app.', 'banzaiembed' ); ?></p>
			<textarea id="bzem-custom-js-before" name="custom_js_before" rows="6" class="large-text code bzem-code-editor" data-mode="js" placeholder="cfg.theme = 'dark';"><?php echo esc_textarea( $code['js_before'] ); ?></textarea>

			<h3 class="bzem-section-title"><label for="bzem-custom-js-after"><?php esc_html_e( 'JavaScript after the app', 'banzaiembed' ); ?></label></h3>
			<p class="description"><?php esc_html_e( 'Runs once the app has rendered into its mount element (or when the page has loaded, if the element is missing or already filled) — for event listeners and analytics. Runs once per page, however many copies of the app there are. cfg is available here too.', 'banzaiembed' ); ?></p>
			<textarea id="bzem-custom-js-after" name="custom_js_after" rows="6" class="large-text code bzem-code-editor" data-mode="js" placeholder="document.getElementById(cfg.mountId).addEventListener('click', ...);"><?php echo esc_textarea( $code['js_after'] ); ?></textarea>

			<p class="description"><?php esc_html_e( 'This code runs for every visitor to a page with the app on it. Both scripts run inside a function, so their variables stay local; assign to window to share something.', 'banzaiembed' ); ?></p>
		</fieldset>
	</div>
</section>
