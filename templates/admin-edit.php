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
$methods   = array(
	'index-html'     => __( 'Detected from index.html', 'banzaiembed' ),
	'vite-manifest'  => __( 'Detected from the Vite manifest', 'banzaiembed' ),
	'asset-manifest' => __( 'Detected from asset-manifest.json', 'banzaiembed' ),
	'pattern'        => __( 'Guessed from filenames', 'banzaiembed' ),
	'manual'         => __( 'Chosen manually', 'banzaiembed' ),
	''               => __( 'Not detected', 'banzaiembed' ),
);
$default_mount = '' !== $record['detected_mount_id'] ? $record['detected_mount_id'] : 'bzem-' . ( '' !== $record['slug'] ? $record['slug'] : 'my-app' );
?>
<h1 class="wp-heading-inline">
	<?php
	if ( $is_new ) {
		esc_html_e( 'Add New App', 'banzaiembed' );
	} else {
		/* translators: %s: app name. */
		echo esc_html( sprintf( __( 'Edit %s', 'banzaiembed' ), $record['name'] ) );
	}
	?>
</h1>
<?php if ( ! $is_new ) : ?>
	<span class="bzem-status bzem-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( App_Manager::status_label( $status ) ); ?></span>
<?php endif; ?>
<hr class="wp-header-end">
<p><a href="<?php echo esc_url( Admin::list_url() ); ?>"><?php esc_html_e( '&larr; All apps', 'banzaiembed' ); ?></a></p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="bzem-form">
	<input type="hidden" name="action" value="<?php echo esc_attr( Admin::SAVE_ACTION ); ?>">
	<input type="hidden" name="original_slug" value="<?php echo esc_attr( $is_new ? '' : $record['slug'] ); ?>">
	<?php wp_nonce_field( Admin::SAVE_ACTION ); ?>

	<div class="bzem-columns">
		<div class="bzem-main">

			<div class="bzem-card">
				<h2><?php esc_html_e( 'Details', 'banzaiembed' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="bzem-name"><?php esc_html_e( 'App name', 'banzaiembed' ); ?></label></th>
						<td><input type="text" id="bzem-name" name="name" class="regular-text" required value="<?php echo esc_attr( $record['name'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="bzem-slug"><?php esc_html_e( 'Slug', 'banzaiembed' ); ?></label></th>
						<td>
							<?php if ( $is_new ) : ?>
								<input type="text" id="bzem-slug" name="slug" class="regular-text code" pattern="[a-z0-9-]*" maxlength="60" value="">
								<p class="description"><?php esc_html_e( 'Used in the shortcode. Generated from the name; lowercase letters, numbers and hyphens. It cannot be changed later.', 'banzaiembed' ); ?></p>
							<?php else : ?>
								<code><?php echo esc_html( $record['slug'] ); ?></code>
								<p class="description"><?php esc_html_e( 'Slugs cannot be changed, because shortcodes and blocks already on your pages refer to it.', 'banzaiembed' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bzem-framework"><?php esc_html_e( 'Framework', 'banzaiembed' ); ?></label></th>
						<td>
							<select id="bzem-framework" name="framework">
								<?php foreach ( App_Manager::FRAMEWORKS as $fw ) : ?>
									<option value="<?php echo esc_attr( $fw ); ?>" <?php selected( $record['framework'], $fw ); ?>><?php echo esc_html( App_Manager::framework_label( $fw ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bzem-mount"><?php esc_html_e( 'Mount element ID', 'banzaiembed' ); ?></label></th>
						<td>
							<input type="text" id="bzem-mount" name="mount_id" class="regular-text code" pattern="[A-Za-z0-9_-]*" placeholder="<?php echo esc_attr( $default_mount ); ?>" value="<?php echo esc_attr( $record['mount_id'] ); ?>">
							<p class="description">
								<?php esc_html_e( 'The ID your app mounts to — what you pass to app.mount() or createRoot(). Leave blank to use the one in your build\'s index.html (app for Vite + Vue, root for Vite + React and Create React App).', 'banzaiembed' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<div class="bzem-card">
				<h2><?php echo $has_build ? esc_html__( 'Replace build', 'banzaiembed' ) : esc_html__( 'Upload build', 'banzaiembed' ); ?></h2>
				<label class="bzem-dropzone" for="bzem-build">
					<input type="file" id="bzem-build" name="build" accept=".zip,application/zip">
					<span class="bzem-dropzone-text"><?php esc_html_e( 'Drop a .zip here, or click to choose one', 'banzaiembed' ); ?></span>
					<span class="bzem-dropzone-file"></span>
				</label>
				<p class="description">
					<?php
					/* translators: %s: maximum upload size. */
					echo esc_html( sprintf( __( 'Zip the contents of your dist/ or build/ folder (zipping the folder itself works too). Maximum size: %s.', 'banzaiembed' ), $max_size ) );
					?>
				</p>
				<p class="description">
					<?php esc_html_e( 'Build with a relative base so lazy-loaded chunks and assets resolve under WordPress: base: \'./\' in vite.config, or "homepage": "." in package.json for Create React App.', 'banzaiembed' ); ?>
				</p>
				<?php if ( $has_build ) : ?>
					<p class="description"><?php esc_html_e( 'Uploading replaces the current build and re-detects the entry files below.', 'banzaiembed' ); ?></p>
				<?php endif; ?>
			</div>

			<?php if ( $has_build ) : ?>
				<div class="bzem-card">
					<h2><?php esc_html_e( 'Entry files', 'banzaiembed' ); ?></h2>
					<p><span class="bzem-method"><?php echo esc_html( isset( $methods[ $record['detected_by'] ] ) ? $methods[ $record['detected_by'] ] : $record['detected_by'] ); ?></span></p>

					<?php foreach ( $record['warnings'] as $warning ) : ?>
						<div class="notice notice-warning inline"><p><?php echo esc_html( $warning ); ?></p></div>
					<?php endforeach; ?>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="bzem-scripts"><?php esc_html_e( 'Scripts', 'banzaiembed' ); ?></label></th>
							<td>
								<textarea id="bzem-scripts" name="scripts" rows="4" class="large-text code"><?php echo esc_textarea( implode( "\n", $record['scripts'] ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'One per line, loaded in this order. Paths are relative to the build; http(s) URLs are loaded as-is.', 'banzaiembed' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="bzem-script-type"><?php esc_html_e( 'Script type', 'banzaiembed' ); ?></label></th>
							<td>
								<select id="bzem-script-type" name="script_type">
									<option value="module" <?php selected( $record['script_type'], 'module' ); ?>><?php esc_html_e( 'ES module (Vite, modern builds)', 'banzaiembed' ); ?></option>
									<option value="classic" <?php selected( $record['script_type'], 'classic' ); ?>><?php esc_html_e( 'Classic, deferred (webpack, Create React App)', 'banzaiembed' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="bzem-styles"><?php esc_html_e( 'Stylesheets', 'banzaiembed' ); ?></label></th>
							<td>
								<textarea id="bzem-styles" name="styles" rows="3" class="large-text code"><?php echo esc_textarea( implode( "\n", $record['styles'] ) ); ?></textarea>
							</td>
						</tr>
					</table>

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
			<?php endif; ?>

			<?php submit_button( $is_new ? __( 'Create App', 'banzaiembed' ) : __( 'Save App', 'banzaiembed' ) ); ?>
		</div>

		<?php if ( ! $is_new ) : ?>
			<div class="bzem-side">
				<div class="bzem-card">
					<h2><?php esc_html_e( 'Embed', 'banzaiembed' ); ?></h2>
					<p><?php esc_html_e( 'Shortcode:', 'banzaiembed' ); ?></p>
					<p class="bzem-shortcode-row">
						<code class="bzem-shortcode"><?php echo esc_html( App_Manager::shortcode( $record ) ); ?></code>
						<button type="button" class="button bzem-copy" data-copy="<?php echo esc_attr( App_Manager::shortcode( $record ) ); ?>"><?php esc_html_e( 'Copy', 'banzaiembed' ); ?></button>
					</p>
					<p class="description"><?php esc_html_e( 'Optional attributes: id, class, style. Or add the "BanzaiEmbed App" block in the block editor.', 'banzaiembed' ); ?></p>
					<p>
						<?php esc_html_e( 'Mounts to:', 'banzaiembed' ); ?>
						<code>#<?php echo esc_html( App_Manager::mount_id( $record ) ); ?></code>
					</p>
					<details>
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

				<?php if ( $has_build ) : ?>
					<div class="bzem-card">
						<h2><?php esc_html_e( 'Build', 'banzaiembed' ); ?></h2>
						<p>
							<?php esc_html_e( 'Uploaded:', 'banzaiembed' ); ?>
							<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $record['uploaded'] ) ); ?>
						</p>
						<p><?php esc_html_e( 'ID:', 'banzaiembed' ); ?> <code><?php echo esc_html( $record['build'] ); ?></code></p>
					</div>
				<?php endif; ?>

				<p><a class="submitdelete bzem-delete" href="<?php echo esc_url( Admin::delete_url( $record['slug'] ) ); ?>" data-name="<?php echo esc_attr( $record['name'] ); ?>"><?php esc_html_e( 'Delete app', 'banzaiembed' ); ?></a></p>
			</div>
		<?php endif; ?>
	</div>
</form>
