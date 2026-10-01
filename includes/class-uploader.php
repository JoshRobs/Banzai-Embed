<?php
/**
 * Build uploads.
 *
 * @package BanzaiEmbed
 */

namespace BanzaiEmbed;

defined( 'ABSPATH' ) || exit;

/**
 * Turns an uploaded zip into a build directory.
 *
 * The zip is written into uploads/, which is web-reachable, so what it is
 * allowed to put there is the security boundary of the whole plugin. Entries
 * are read one at a time and only allowlisted static files are ever written —
 * a .php file in the archive never touches the disk, not even briefly in a
 * temp directory, so there is no window in which it could be requested.
 *
 * Every entry path is checked for traversal before anything is written, and
 * the archive is capped by file count and total size so a zip bomb fails
 * cleanly instead of filling the disk.
 */
final class Uploader {

	/**
	 * Static file types a frontend build may contain.
	 */
	const ALLOWED_EXTENSIONS = array(
		'js', 'mjs', 'cjs', 'css', 'map', 'json', 'webmanifest', 'html', 'htm', 'txt', 'xml', 'csv',
		'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico', 'bmp',
		'woff', 'woff2', 'ttf', 'otf', 'eot',
		'wasm', 'glb', 'gltf', 'bin',
		'mp3', 'mp4', 'webm', 'ogg', 'ogv', 'wav', 'm4a',
	);

	const MAX_FILES = 5000;

	const MAX_BYTES = 209715200; // 200 MB uncompressed.

	/**
	 * @var App_Manager
	 */
	private $apps;

	/**
	 * Constructor.
	 *
	 * @param App_Manager $apps App store.
	 */
	public function __construct( App_Manager $apps ) {
		$this->apps = $apps;
	}

	/**
	 * Extract an uploaded zip into a new build directory for an app.
	 *
	 * Nothing about the app's current build changes here; the caller switches
	 * the record over once it is happy with the result, then calls prune().
	 *
	 * @param array  $file An entry from $_FILES.
	 * @param string $slug App slug.
	 * @return array|\WP_Error { build, dir, files, bytes, skipped[] }
	 */
	public function extract( array $file, $slug ) {
		$checked = $this->check_upload( $file );

		if ( is_wp_error( $checked ) ) {
			return $checked;
		}

		$reader = $this->open( $file['tmp_name'] );

		if ( is_wp_error( $reader ) ) {
			return $reader;
		}

		$plan = $this->plan( $reader['entries'] );

		if ( is_wp_error( $plan ) ) {
			return $plan;
		}

		$build = gmdate( 'YmdHis' ) . '-' . strtolower( wp_generate_password( 6, false, false ) );
		$dir   = $this->apps->build_dir( $slug, $build );

		if ( ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error( 'bzem_mkdir', __( 'Could not create the app directory in uploads. Check that wp-content/uploads is writable.', 'banzaiembed' ) );
		}

		foreach ( $plan['write'] as $index => $relative ) {
			$contents = call_user_func( $reader['read'], $index );

			if ( false === $contents || ! Filesystem::write( $dir . '/' . $relative, $contents ) ) {
				Filesystem::delete( $dir );

				return new \WP_Error(
					'bzem_write',
					/* translators: %s: file path inside the zip. */
					sprintf( __( 'Could not extract %s from the zip.', 'banzaiembed' ), $relative )
				);
			}
		}

		return array(
			'build'   => $build,
			'dir'     => $dir,
			'files'   => count( $plan['write'] ),
			'bytes'   => $plan['bytes'],
			'skipped' => $plan['skipped'],
		);
	}

	/**
	 * Delete every build of an app except the ones named.
	 *
	 * @param string   $slug App slug.
	 * @param string[] $keep Build IDs to keep.
	 */
	public function prune( $slug, array $keep ) {
		$app_dir = $this->apps->app_dir( $slug );

		foreach ( Filesystem::list_dirs( $app_dir ) as $build ) {
			if ( ! in_array( $build, $keep, true ) ) {
				Filesystem::delete( $app_dir . '/' . $build );
			}
		}
	}

	/**
	 * Delete one build directory — used to throw away a build the caller
	 * decided not to keep.
	 *
	 * @param string $slug  App slug.
	 * @param string $build Build ID.
	 */
	public function discard( $slug, $build ) {
		if ( '' !== $build ) {
			Filesystem::delete( $this->apps->build_dir( $slug, $build ) );
		}
	}

	/**
	 * Validate the $_FILES entry itself.
	 *
	 * @param array $file An entry from $_FILES.
	 * @return true|\WP_Error
	 */
	private function check_upload( array $file ) {
		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error ) {
			return new \WP_Error(
				'bzem_too_big',
				/* translators: %s: maximum upload size, e.g. "64 MB". */
				sprintf( __( 'The zip is larger than this server accepts (%s). Raise upload_max_filesize and post_max_size, or make the build smaller.', 'banzaiembed' ), size_format( wp_max_upload_size() ) )
			);
		}

		if ( UPLOAD_ERR_OK !== $error ) {
			/* translators: %d: PHP upload error code. */
			return new \WP_Error( 'bzem_upload', sprintf( __( 'The upload failed (PHP error %d).', 'banzaiembed' ), $error ) );
		}

		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new \WP_Error( 'bzem_upload', __( 'The upload failed.', 'banzaiembed' ) );
		}

		$name = isset( $file['name'] ) ? (string) $file['name'] : '';

		if ( ! preg_match( '/\.zip$/i', $name ) ) {
			return new \WP_Error( 'bzem_not_zip', __( 'Upload a .zip file containing your build output.', 'banzaiembed' ) );
		}

		return true;
	}

	/**
	 * Open a zip with whichever library the server has.
	 *
	 * Returns a uniform reader: a list of entries and a function that returns
	 * one entry's bytes by index.
	 *
	 * @param string $path Zip path.
	 * @return array|\WP_Error { entries: array{index:int,name:string,size:int,dir:bool}[], read: callable }
	 */
	private function open( $path ) {
		if ( class_exists( '\ZipArchive' ) ) {
			$zip = new \ZipArchive();

			if ( true !== $zip->open( $path ) ) {
				return new \WP_Error( 'bzem_bad_zip', __( 'That file is not a readable zip archive.', 'banzaiembed' ) );
			}

			$entries = array();

			for ( $i = 0; $i < $zip->numFiles; $i++ ) {
				$stat = $zip->statIndex( $i );

				if ( false === $stat ) {
					continue;
				}

				$entries[] = array(
					'index' => $i,
					'name'  => $stat['name'],
					'size'  => (int) $stat['size'],
					'dir'   => '/' === substr( $stat['name'], -1 ),
				);
			}

			return array(
				'entries' => $entries,
				'read'    => static function ( $index ) use ( $zip ) {
					return $zip->getFromIndex( $index );
				},
			);
		}

		// No ZipArchive: fall back to the PclZip copy WordPress bundles.
		require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';

		$zip  = new \PclZip( $path );
		$list = $zip->listContent();

		if ( ! is_array( $list ) ) {
			return new \WP_Error( 'bzem_bad_zip', __( 'That file is not a readable zip archive.', 'banzaiembed' ) );
		}

		$entries = array();

		foreach ( $list as $item ) {
			$entries[] = array(
				'index' => (int) $item['index'],
				'name'  => $item['filename'],
				'size'  => (int) $item['size'],
				'dir'   => ! empty( $item['folder'] ),
			);
		}

		return array(
			'entries' => $entries,
			'read'    => static function ( $index ) use ( $zip ) {
				$out = $zip->extract( PCLZIP_OPT_BY_INDEX, (string) $index, PCLZIP_OPT_EXTRACT_AS_STRING );

				return ( is_array( $out ) && isset( $out[0]['content'] ) ) ? $out[0]['content'] : false;
			},
		);
	}

	/**
	 * Decide which entries are written, and where.
	 *
	 * @param array $entries From open().
	 * @return array|\WP_Error { write: array<int,string>, skipped: string[], bytes: int }
	 */
	private function plan( array $entries ) {
		$allowed = (array) apply_filters( 'bzem/allowed_extensions', self::ALLOWED_EXTENSIONS );
		$files   = array();
		$skipped = array();

		foreach ( $entries as $entry ) {
			if ( $entry['dir'] ) {
				continue;
			}

			$name = str_replace( '\\', '/', $entry['name'] );

			// Finder litter, not part of the build.
			if ( 0 === strpos( $name, '__MACOSX/' ) || '.DS_Store' === basename( $name ) ) {
				continue;
			}

			$path = $this->safe_path( $name );

			if ( null === $path ) {
				$skipped[] = $name;
				continue;
			}

			$files[ $entry['index'] ] = array(
				'path' => $path,
				'size' => $entry['size'],
			);
		}

		if ( ! $files ) {
			return new \WP_Error( 'bzem_empty', __( 'The zip is empty.', 'banzaiembed' ) );
		}

		if ( count( $files ) > (int) apply_filters( 'bzem/max_files', self::MAX_FILES ) ) {
			return new \WP_Error( 'bzem_too_many', __( 'The zip contains too many files. Upload only your build output (dist/ or build/), not node_modules or source.', 'banzaiembed' ) );
		}

		$files = $this->strip_wrapper( $files );
		$write = array();
		$bytes = 0;

		foreach ( $files as $index => $file ) {
			if ( ! $this->is_allowed( $file['path'], $allowed ) ) {
				$skipped[] = $file['path'];
				continue;
			}

			$bytes          += $file['size'];
			$write[ $index ] = $file['path'];
		}

		if ( $bytes > (int) apply_filters( 'bzem/max_bytes', self::MAX_BYTES ) ) {
			return new \WP_Error( 'bzem_too_big', __( 'The build is too large once extracted. Upload only your build output (dist/ or build/).', 'banzaiembed' ) );
		}

		if ( ! $write ) {
			return new \WP_Error( 'bzem_nothing', __( 'The zip contains no files that can be served as part of a web app.', 'banzaiembed' ) );
		}

		return array(
			'write'   => $write,
			'skipped' => $skipped,
			'bytes'   => $bytes,
		);
	}

	/**
	 * Normalise an entry name, or return null if it could escape the build
	 * directory or is otherwise unsafe to write.
	 *
	 * @param string $name Forward-slashed entry name.
	 * @return string|null
	 */
	private function safe_path( $name ) {
		if ( false !== strpos( $name, "\0" ) || false !== strpos( $name, ':' ) ) {
			return null;
		}

		$segments = array();

		foreach ( explode( '/', ltrim( $name, '/' ) ) as $segment ) {
			if ( '' === $segment || '.' === $segment ) {
				continue;
			}

			if ( '..' === $segment ) {
				return null;
			}

			// Dotfiles (.htaccess, .user.ini, .env) are never build output.
			// .vite/ is the exception: Vite keeps its manifest there.
			if ( '.' === $segment[0] && '.vite' !== $segment ) {
				return null;
			}

			$segments[] = $segment;
		}

		return $segments ? implode( '/', $segments ) : null;
	}

	/**
	 * Most people zip the dist/ folder rather than its contents. When every
	 * file sits under one top-level directory, drop that directory.
	 *
	 * @param array $files index => { path, size }.
	 * @return array
	 */
	private function strip_wrapper( array $files ) {
		$top = null;

		foreach ( $files as $file ) {
			$slash = strpos( $file['path'], '/' );

			if ( false === $slash ) {
				return $files;
			}

			$first = substr( $file['path'], 0, $slash );

			if ( null === $top ) {
				$top = $first;
			} elseif ( $top !== $first ) {
				return $files;
			}
		}

		$cut = strlen( $top ) + 1;

		foreach ( $files as $index => $file ) {
			$files[ $index ]['path'] = substr( $file['path'], $cut );
		}

		return $files;
	}

	/**
	 * Whether a file may be written into uploads/.
	 *
	 * The extension must be allowlisted, and no part of the name may look like
	 * a PHP extension: `shell.php.png` is an image to the allowlist but runs
	 * as PHP on an Apache with a loose AddHandler.
	 *
	 * @param string   $path    Relative path.
	 * @param string[] $allowed Extensions.
	 * @return bool
	 */
	private function is_allowed( $path, array $allowed ) {
		$base = strtolower( basename( $path ) );

		if ( preg_match( '/\.(php\d*|phtml|phar|pht|phps|cgi|pl|asp|aspx|jsp|sh)(\.|$)/', $base ) ) {
			return false;
		}

		$ext = pathinfo( $base, PATHINFO_EXTENSION );

		return '' !== $ext && in_array( $ext, $allowed, true );
	}
}
