<?php
/**
 * Builds the upload fixtures in tests/output/.
 *
 * Runs inside the wp-env CLI container, which has ZipArchive:
 *
 *     npx @wordpress/env run cli php wp-content/plugins/Banzai-Embed/tests/make-zips.php
 *
 * The real builds come from `npm create vite` (Vue and React templates),
 * built with the default `base: '/'` (vue-rooted), with `--base=./ --manifest`
 * (vue-relative, react-relative), with `--base=/old_staging/wp-content/plugins/old-plugin/`
 * plus a 12 KB @font-face file (vue-subbase), and with a dynamic import()
 * (vue-lazy-rooted), and copied into tests/fixtures/.
 * Everything else is synthesised here.
 *
 * @package BanzaiEmbed
 */

$root = dirname( __DIR__ );
$out  = $root . '/tests/output';

if ( ! is_dir( $out ) ) {
	mkdir( $out, 0777, true );
}

/**
 * Zip a directory, optionally under a wrapper folder and with Windows-style
 * separators, as the Explorer and old Compress-Archive produce.
 *
 * @param string $dir       Source.
 * @param string $zip_path  Destination.
 * @param string $wrapper   Folder to nest everything under, or ''.
 * @param bool   $backslash Use \ separators.
 */
function bzem_zip_dir( $dir, $zip_path, $wrapper = '', $backslash = false ) {
	$zip = new ZipArchive();
	$zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );

	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );

	foreach ( $it as $file ) {
		$name = ( '' !== $wrapper ? $wrapper . '/' : '' ) . substr( $file->getPathname(), strlen( $dir ) + 1 );
		$name = str_replace( '\\', '/', $name );

		if ( $backslash ) {
			$name = str_replace( '/', '\\', $name );
		}

		$zip->addFile( $file->getPathname(), $name );
	}

	$zip->close();
}

/**
 * Zip literal name => contents pairs.
 *
 * @param string $zip_path Destination.
 * @param array  $files    Entry name => contents.
 */
function bzem_zip_files( $zip_path, array $files ) {
	$zip = new ZipArchive();
	$zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );

	foreach ( $files as $name => $contents ) {
		$zip->addFromString( $name, $contents );
	}

	$zip->close();
}

$fixtures = $root . '/tests/fixtures';

// Real Vite builds.
bzem_zip_dir( $fixtures . '/vue-rooted', $out . '/vue-rooted.zip' );
bzem_zip_dir( $fixtures . '/vue-relative', $out . '/vue-relative-wrapped.zip', 'dist' );
bzem_zip_dir( $fixtures . '/react-relative', $out . '/react-relative-backslash.zip', '', true );

// Compiled for somewhere else: `--base=/old_staging/wp-content/plugins/old-plugin/`,
// with a >4 KB font in the CSS so Vite emits it as a file. Every image, font
// and public/ reference must be rewritten; no warning, as there are no chunks.
bzem_zip_dir( $fixtures . '/vue-subbase', $out . '/vue-subbase.zip' );

// Default `base: '/'` plus a dynamic import(): references are rewritten, but
// the lazy chunk keeps the "rebuild with base './'" warning.
bzem_zip_dir( $fixtures . '/vue-lazy-rooted', $out . '/vue-lazy-rooted.zip' );

// The same React build without index.html, so the Vite manifest is used.
$zip = new ZipArchive();
copy( $out . '/react-relative-backslash.zip', $out . '/react-manifest-only.zip' );
$zip->open( $out . '/react-manifest-only.zip' );
$zip->deleteName( 'index.html' );
$zip->close();

$app_js = "const el=document.getElementById(window.banzaiEmbed&&window.banzaiEmbed['%s']?window.banzaiEmbed['%s'].mounts[0]:'root');el.textContent='%s mounted';el.setAttribute('data-mounted','1');";

// Everything a hostile or careless zip might hold.
bzem_zip_files(
	$out . '/hostile.zip',
	array(
		'index.html'         => '<!doctype html><html><head><script type="module" src="./main.js"></script></head><body><div id="root"></div></body></html>',
		'main.js'            => sprintf( $app_js, 'hostile', 'hostile', 'hostile' ),
		'../escape.js'       => 'alert(1)',
		'sub/../../escape2.js' => 'alert(1)',
		'shell.php'          => '<?php echo "pwned";',
		'img.php.png'        => '<?php echo "pwned";',
		'Shell.PHTML'        => '<?php echo "pwned";',
		'.htaccess'          => 'AddHandler application/x-httpd-php .png',
		'sub/.user.ini'      => 'auto_prepend_file=img.php.png',
		'C:/win.js'          => 'alert(1)',
		'notes.md'           => '# notes',
		'__MACOSX/._main.js' => 'junk',
		'ok.css'             => '#root{color:red}',
	)
);

// Create React App: asset-manifest.json, classic scripts, no index.html.
bzem_zip_files(
	$out . '/cra.zip',
	array(
		'asset-manifest.json'          => json_encode(
			array(
				'files'       => array( 'main.js' => '/static/js/main.1a2b.js' ),
				'entrypoints' => array( 'static/css/main.3c4d.css', 'static/js/main.1a2b.js' ),
			)
		),
		'manifest.json'                => json_encode( array( 'short_name' => 'PWA manifest, not Vite' ) ),
		'static/js/main.1a2b.js'       => '(function(){' . sprintf( $app_js, 'cra', 'cra', 'cra' ) . '})();',
		'static/css/main.3c4d.css'     => '#root{color:green}',
		'static/js/787.aaaa.chunk.js'  => '"use strict";',
	)
);

// No index.html and no manifest: filename patterns. The larger index-*.js is
// the entry; the small one is a lazy chunk that must not be picked.
bzem_zip_files(
	$out . '/pattern.zip',
	array(
		'assets/index-small.js' => 'export default 1;',
		'assets/index-big.js'   => 'import "./vendor-v1.js";' . sprintf( $app_js, 'pattern', 'pattern', 'pattern' ) . str_repeat( '//pad', 200 ),
		'assets/vendor-v1.js'   => 'export const v=1;',
		'assets/style.css'      => 'body{}',
	)
);

// Nothing usable.
bzem_zip_files( $out . '/nothing.zip', array( 'README.md' => '# hi', 'run.sh' => 'echo' ) );

foreach ( glob( $out . '/*.zip' ) as $file ) {
	echo basename( $file ), ' ', filesize( $file ), "\n";
}
