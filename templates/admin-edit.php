<?php
/**
 * Add New / Edit App screen.
 *
 * @package BanzaiEmbed
 *
 * @var array|null $app      Record, or null when adding.
 * @var bool       $is_new   Whether this is the Add New screen.
 * @var string     $max_size Upload limit, formatted.
 * @var array      $assets   { scripts: string[], styles: string[] } in the current build.
 */

use BanzaiEmbed\Admin;
use BanzaiEmbed\App_Manager;

defined( 'ABSPATH' ) || exit;

$record    = $is_new ? App_Manager::defaults() : $app;
$has_build = ! $is_new && '' !== $record['build'];
$status    = App_Manager::status( $record );
$active    = App_Manager::is_active( $record );
$datetime  = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
$methods   = array(
	'index-html'     => __( 'Detected from index.html', 'banzaiembed' ),
	'vite-manifest'  => __( 'Detected from the Vite manifest', 'banzaiembed' ),
	'asset-manifest' => __( 'Detected from asset-manifest.json', 'banzaiembed' ),
	'pattern'        => __( 'Guessed from filenames', 'banzaiembed' ),
	'manual'         => __( 'Chosen manually', 'banzaiembed' ),
	''               => __( 'Not detected', 'banzaiembed' ),
);
$frameworks = array(
	'vue'   => __( 'Vite, Vue CLI', 'banzaiembed' ),
	'react' => __( 'Vite, Create React App', 'banzaiembed' ),
	'other' => __( 'Any other build', 'banzaiembed' ),
);
$default_mount = '' !== $record['detected_mount_id'] ? $record['detected_mount_id'] : 'bzem-' . ( '' !== $record['slug'] ? $record['slug'] : 'my-app' );
?>
<div class="bzem-page-head">
	<div>
		<p class="bzem-breadcrumb">
			<a href="<?php echo esc_url( Admin::list_url() ); ?>"><?php esc_html_e( 'All Apps', 'banzaiembed' ); ?></a>
			<span aria-hidden="true">/</span>
			<span><?php echo $is_new ? esc_html__( 'Add New', 'banzaiembed' ) : esc_html__( 'Edit', 'banzaiembed' ); ?></span>
		</p>
		<h1><?php echo $is_new ? esc_html__( 'Add New App', 'banzaiembed' ) : esc_html( $record['name'] ); ?></h1>
	</div>
	<?php if ( ! $is_new ) : ?>
		<div class="bzem-page-badges">
			<span class="bzem-fw bzem-fw-<?php echo esc_attr( $record['framework'] ); ?>">
				<?php echo Admin::framework_mark( $record['framework'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				<?php echo esc_html( App_Manager::framework_label( $record['framework'] ) ); ?>
			</span>
			<span class="bzem-status bzem-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( App_Manager::status_label( $status ) ); ?></span>
			<?php if ( ! $active ) : ?>
				<span class="bzem-status bzem-status-inactive"><?php esc_html_e( 'Inactive', 'banzaiembed' ); ?></span>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
<hr class="wp-header-end">

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="bzem-form">
	<input type="hidden" name="action" value="<?php echo esc_attr( Admin::SAVE_ACTION ); ?>">
	<input type="hidden" name="original_slug" value="<?php echo esc_attr( $is_new ? '' : $record['slug'] ); ?>">
	<?php wp_nonce_field( Admin::SAVE_ACTION ); ?>

	<div class="bzem-columns">
		<div class="bzem-main">

			<section class="bzem-card">
				<header class="bzem-card-header">
					<h2><?php esc_html_e( 'App details', 'banzaiembed' ); ?></h2>
				</header>
				<div class="bzem-card-body">
					<div class="bzem-field">
						<label for="bzem-name"><?php esc_html_e( 'App name', 'banzaiembed' ); ?></label>
						<input type="text" id="bzem-name" name="name" class="widefat" required value="<?php echo esc_attr( $record['name'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Mortgage Calculator', 'banzaiembed' ); ?>">
					</div>

					<div class="bzem-field">
						<label for="bzem-slug"><?php esc_html_e( 'Slug', 'banzaiembed' ); ?></label>
						<?php if ( $is_new ) : ?>
							<input type="text" id="bzem-slug" name="slug" class="widefat code" pattern="[a-z0-9-]*" maxlength="60" value="" placeholder="my-app">
							<p class="description">
								<?php esc_html_e( 'Generated from the name: lowercase letters, numbers and hyphens. It cannot be changed later.', 'banzaiembed' ); ?>
								<?php esc_html_e( 'Shortcode:', 'banzaiembed' ); ?>
								<code id="bzem-slug-preview">[banzai-embed app="my-app"]</code>
							</p>
						<?php else : ?>
							<p class="bzem-static"><code><?php echo esc_html( $record['slug'] ); ?></code></p>
							<p class="description"><?php esc_html_e( 'Slugs cannot be changed, because shortcodes and blocks already on your pages refer to it.', 'banzaiembed' ); ?></p>
						<?php endif; ?>
					</div>

					<fieldset class="bzem-field">
						<legend><?php esc_html_e( 'Framework', 'banzaiembed' ); ?></legend>
						<div class="bzem-fw-picker">
							<?php foreach ( App_Manager::FRAMEWORKS as $fw ) : ?>
								<label class="bzem-fw-option">
									<input type="radio" name="framework" value="<?php echo esc_attr( $fw ); ?>" <?php checked( $record['framework'], $fw ); ?>>
									<span class="bzem-fw-tile">
										<?php echo Admin::framework_mark( $fw ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
										<span class="bzem-fw-name"><?php echo esc_html( App_Manager::framework_label( $fw ) ); ?></span>
										<span class="bzem-fw-hint"><?php echo esc_html( $frameworks[ $fw ] ); ?></span>
									</span>
								</label>
							<?php endforeach; ?>
						</div>
					</fieldset>

					<div class="bzem-field">
						<label for="bzem-mount"><?php esc_html_e( 'Mount element ID', 'banzaiembed' ); ?> <span class="bzem-optional"><?php esc_html_e( 'Optional', 'banzaiembed' ); ?></span></label>
						<span class="bzem-input-prefix">
							<span aria-hidden="true">#</span>
							<input type="text" id="bzem-mount" name="mount_id" class="widefat code" pattern="[A-Za-z0-9_-]*" placeholder="<?php echo esc_attr( $default_mount ); ?>" value="<?php echo esc_attr( $record['mount_id'] ); ?>">
						</span>
						<p class="description">
							<?php esc_html_e( 'The ID your app mounts to — what you pass to app.mount() or createRoot(). Leave blank to use the one in your build\'s index.html (app for Vite + Vue, root for Vite + React and Create React App).', 'banzaiembed' ); ?>
						</p>
					</div>
				</div>
			</section>

			<section class="bzem-card">
				<header class="bzem-card-header">
					<h2><?php echo $has_build ? esc_html__( 'Replace build', 'banzaiembed' ) : esc_html__( 'Upload build', 'banzaiembed' ); ?></h2>
				</header>
				<div class="bzem-card-body">
					<label class="bzem-dropzone" for="bzem-build">
						<input type="file" id="bzem-build" name="build" accept=".zip,application/zip">
						<span class="bzem-dropzone-icon dashicons dashicons-upload" aria-hidden="true"></span>
						<span class="bzem-dropzone-text"><?php esc_html_e( 'Drop your build .zip here', 'banzaiembed' ); ?></span>
						<span class="bzem-dropzone-sub">
							<?php
							/* translators: %s: maximum upload size. */
							echo esc_html( sprintf( __( 'or click to choose one · up to %s', 'banzaiembed' ), $max_size ) );
							?>
						</span>
						<span class="bzem-dropzone-file"></span>
					</label>
					<?php if ( $has_build ) : ?>
						<p class="description"><?php esc_html_e( 'Uploading replaces the current build and re-detects the entry files. The previous build stays online for pages cached before the change.', 'banzaiembed' ); ?></p>
					<?php endif; ?>

					<details class="bzem-tip">
						<summary><?php esc_html_e( 'Preparing a build for WordPress', 'banzaiembed' ); ?></summary>
						<p><?php esc_html_e( 'Zip the contents of your dist/ or build/ folder — zipping the folder itself works too. A relative base is the most robust choice, and the only one that keeps lazy-loaded chunks working:', 'banzaiembed' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'Vite:', 'banzaiembed' ); ?> <code>base: './'</code></li>
							<li><?php esc_html_e( 'Create React App:', 'banzaiembed' ); ?> <code>"homepage": "."</code></li>
							<li><?php esc_html_e( 'webpack 5:', 'banzaiembed' ); ?> <code>output.publicPath: 'auto'</code></li>
						</ul>
						<p><?php esc_html_e( 'Builds made for the site root or another path are relocated on upload, and you are told if lazy chunks still need a rebuild.', 'banzaiembed' ); ?></p>
					</details>
				</div>
			</section>

			<?php if ( $has_build ) : ?>
				<section class="bzem-card">
					<header class="bzem-card-header">
						<h2><?php esc_html_e( 'Entry files', 'banzaiembed' ); ?></h2>
						<span class="bzem-method"><?php echo esc_html( isset( $methods[ $record['detected_by'] ] ) ? $methods[ $record['detected_by'] ] : $record['detected_by'] ); ?></span>
					</header>
					<div class="bzem-card-body">
						<?php foreach ( $record['warnings'] as $warning ) : ?>
							<div class="notice notice-warning inline"><p><?php echo esc_html( $warning ); ?></p></div>
						<?php endforeach; ?>

						<div class="bzem-field">
							<label for="bzem-scripts"><?php esc_html_e( 'Scripts', 'banzaiembed' ); ?></label>
							<textarea id="bzem-scripts" name="scripts" rows="4" class="large-text code"><?php echo esc_textarea( implode( "\n", $record['scripts'] ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One per line, loaded in this order. Paths are relative to the build; http(s) URLs are loaded as-is.', 'banzaiembed' ); ?></p>
						</div>

						<div class="bzem-field">
							<label for="bzem-script-type"><?php esc_html_e( 'Script type', 'banzaiembed' ); ?></label>
							<select id="bzem-script-type" name="script_type">
								<option value="module" <?php selected( $record['script_type'], 'module' ); ?>><?php esc_html_e( 'ES module (Vite, modern builds)', 'banzaiembed' ); ?></option>
								<option value="classic" <?php selected( $record['script_type'], 'classic' ); ?>><?php esc_html_e( 'Classic, deferred (webpack, Create React App)', 'banzaiembed' ); ?></option>
							</select>
						</div>

						<div class="bzem-field">
							<label for="bzem-styles"><?php esc_html_e( 'Stylesheets', 'banzaiembed' ); ?></label>
							<textarea id="bzem-styles" name="styles" rows="3" class="large-text code"><?php echo esc_textarea( implode( "\n", $record['styles'] ) ); ?></textarea>
						</div>

						<details class="bzem-files">
							<summary><?php esc_html_e( 'Files in this build', 'banzaiembed' ); ?></summary>
							<?php
							foreach (
								array(
									'scripts' => array( __( 'JavaScript', 'banzaiembed' ), 'bzem-scripts' ),
									'styles'  => array( __( 'CSS', 'banzaiembed' ), 'bzem-styles' ),
								) as $kind => $meta
							) :
								?>
								<h4><?php echo esc_html( $meta[0] ); ?></h4>
								<?php if ( ! $assets[ $kind ] ) : ?>
									<p class="description"><?php esc_html_e( 'None.', 'banzaiembed' ); ?></p>
								<?php else : ?>
									<ul>
										<?php foreach ( $assets[ $kind ] as $file ) : ?>
											<li>
												<code><?php echo esc_html( $file ); ?></code>
												<button type="button" class="button-link bzem-add-entry" data-target="<?php echo esc_attr( $meta[1] ); ?>" data-path="<?php echo esc_attr( $file ); ?>"><?php esc_html_e( 'Add', 'banzaiembed' ); ?></button>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							<?php endforeach; ?>
						</details>
					</div>
				</section>
			<?php endif; ?>
		</div>

		<div class="bzem-side">
			<section class="bzem-card bzem-publish">
				<header class="bzem-card-header">
					<h2><?php esc_html_e( 'Status', 'banzaiembed' ); ?></h2>
				</header>
				<div class="bzem-card-body">
					<label class="bzem-switch-field">
						<input type="checkbox" name="active" value="1" class="bzem-switch-input" <?php checked( $active ); ?>>
						<span class="bzem-switch" aria-hidden="true"></span>
						<span class="bzem-switch-text"><?php esc_html_e( 'Active', 'banzaiembed' ); ?></span>
					</label>
					<p class="description"><?php esc_html_e( 'Inactive apps show nothing to visitors. Editors see a notice where they are embedded.', 'banzaiembed' ); ?></p>

					<?php if ( ! $is_new ) : ?>
						<dl class="bzem-facts">
							<dt><?php esc_html_e( 'Build', 'banzaiembed' ); ?></dt>
							<dd><span class="bzem-status bzem-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( App_Manager::status_label( $status ) ); ?></span></dd>
							<?php if ( $has_build ) : ?>
								<dt><?php esc_html_e( 'Uploaded', 'banzaiembed' ); ?></dt>
								<dd><?php echo esc_html( wp_date( $datetime, $record['uploaded'] ) ); ?></dd>
								<dt><?php esc_html_e( 'Build ID', 'banzaiembed' ); ?></dt>
								<dd><code><?php echo esc_html( $record['build'] ); ?></code></dd>
							<?php endif; ?>
							<?php if ( App_Manager::modified( $record ) ) : ?>
								<dt><?php esc_html_e( 'Last updated', 'banzaiembed' ); ?></dt>
								<dd><?php echo esc_html( wp_date( $datetime, App_Manager::modified( $record ) ) ); ?></dd>
							<?php endif; ?>
						</dl>
					<?php endif; ?>
				</div>
				<footer class="bzem-card-footer">
					<?php if ( ! $is_new ) : ?>
						<a class="submitdelete bzem-delete" href="<?php echo esc_url( Admin::delete_url( $record['slug'] ) ); ?>" data-name="<?php echo esc_attr( $record['name'] ); ?>"><?php esc_html_e( 'Delete app', 'banzaiembed' ); ?></a>
					<?php endif; ?>
					<button type="submit" class="button button-primary button-large"><?php echo $is_new ? esc_html__( 'Create App', 'banzaiembed' ) : esc_html__( 'Save App', 'banzaiembed' ); ?></button>
				</footer>
			</section>

			<?php if ( ! $is_new ) : ?>
				<section class="bzem-card">
					<header class="bzem-card-header">
						<h2><?php esc_html_e( 'Embed', 'banzaiembed' ); ?></h2>
					</header>
					<div class="bzem-card-body">
						<p class="bzem-label"><?php esc_html_e( 'Shortcode', 'banzaiembed' ); ?></p>
						<span class="bzem-chip bzem-chip-wide">
							<code class="bzem-shortcode"><?php echo esc_html( App_Manager::shortcode( $record ) ); ?></code>
							<button type="button" class="bzem-copy bzem-icon-button" data-copy="<?php echo esc_attr( App_Manager::shortcode( $record ) ); ?>" aria-label="<?php esc_attr_e( 'Copy shortcode', 'banzaiembed' ); ?>" title="<?php esc_attr_e( 'Copy shortcode', 'banzaiembed' ); ?>">
								<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
							</button>
						</span>
						<p class="description"><?php esc_html_e( 'Optional attributes: id, class, style. Or add the "BanzaiEmbed App" block in the block editor.', 'banzaiembed' ); ?></p>
						<p class="bzem-label"><?php esc_html_e( 'Mounts to', 'banzaiembed' ); ?></p>
						<p><code>#<?php echo esc_html( App_Manager::mount_id( $record ) ); ?></code></p>
						<details class="bzem-tip">
							<summary><?php esc_html_e( 'More than one instance per page', 'banzaiembed' ); ?></summary>
							<p><?php esc_html_e( 'A second copy gets a suffixed ID (app-2), but your entry script only runs once. To mount every copy, mount to each element BanzaiEmbed lists:', 'banzaiembed' ); ?></p>
							<pre class="bzem-code">const cfg = window.banzaiEmbed?.['<?php echo esc_html( $record['slug'] ); ?>'];
for (const id of cfg?.mounts ?? ['<?php echo esc_html( App_Manager::mount_id( $record ) ); ?>']) {
<?php if ( 'react' === $record['framework'] ) : ?>
  createRoot(document.getElementById(id)).render(&lt;App /&gt;);
<?php elseif ( 'vue' === $record['framework'] ) : ?>
  createApp(App).mount('#' + id);
<?php else : ?>
  init(document.getElementById(id));
<?php endif; ?>
}</pre>
							<p><?php esc_html_e( 'cfg.baseUrl is the URL of your build folder, for files you reference by path at runtime.', 'banzaiembed' ); ?></p>
						</details>
					</div>
				</section>
			<?php endif; ?>
		</div>
	</div>
</form>
