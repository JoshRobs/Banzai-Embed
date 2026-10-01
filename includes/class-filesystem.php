<?php
/**
 * File access.
 *
 * @package BanzaiEmbed
 */

namespace BanzaiEmbed;

defined( 'ABSPATH' ) || exit;

/**
 * The plugin's only route to the disk.
 *
 * Always the direct filesystem. Everything this plugin writes lives under
 * uploads/, which PHP can already write to on any site where media uploads
 * work; going through WP_Filesystem's FTP/SSH methods would put a credentials
 * prompt in the middle of a form post on hosts that are configured for them.
 */
final class Filesystem {

	/**
	 * @var \WP_Filesystem_Direct|null
	 */
	private static $fs = null;

	/**
	 * The direct filesystem instance.
	 *
	 * @return \WP_Filesystem_Direct
	 */
	public static function fs() {
		if ( null === self::$fs ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

			self::$fs = new \WP_Filesystem_Direct( null );

			// WP_Filesystem() defines these; constructing the class directly
			// skips it. Same derivation as core.
			if ( ! defined( 'FS_CHMOD_DIR' ) ) {
				define( 'FS_CHMOD_DIR', ( fileperms( ABSPATH ) & 0777 | 0755 ) );
			}

			if ( ! defined( 'FS_CHMOD_FILE' ) ) {
				define( 'FS_CHMOD_FILE', ( fileperms( ABSPATH . 'index.php' ) & 0777 | 0644 ) );
			}
		}

		return self::$fs;
	}

	/**
	 * Write a file, creating parent directories.
	 *
	 * @param string $path     Absolute path.
	 * @param string $contents Bytes.
	 * @return bool
	 */
	public static function write( $path, $contents ) {
		if ( ! wp_mkdir_p( dirname( $path ) ) ) {
			return false;
		}

		$fs = self::fs();

		return $fs->put_contents( $path, $contents, FS_CHMOD_FILE );
	}

	/**
	 * Read a file, or return '' if it cannot be read.
	 *
	 * @param string $path Absolute path.
	 * @return string
	 */
	public static function read( $path ) {
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return '';
		}

		$contents = self::fs()->get_contents( $path );

		return false === $contents ? '' : $contents;
	}

	/**
	 * Delete a file or a directory tree. Missing paths are fine.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	public static function delete( $path ) {
		if ( ! file_exists( $path ) ) {
			return true;
		}

		return self::fs()->delete( $path, true );
	}

	/**
	 * Every file under a directory, as forward-slashed paths relative to it.
	 *
	 * @param string $dir Absolute path.
	 * @return string[]
	 */
	public static function list_files( $dir ) {
		$out = array();

		if ( ! is_dir( $dir ) ) {
			return $out;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS )
		);
		$prefix   = strlen( wp_normalize_path( trailingslashit( $dir ) ) );

		foreach ( $iterator as $file ) {
			if ( $file->isFile() ) {
				$out[] = substr( wp_normalize_path( $file->getPathname() ), $prefix );
			}
		}

		return $out;
	}

	/**
	 * Every directory directly inside $dir, by name.
	 *
	 * @param string $dir Absolute path.
	 * @return string[]
	 */
	public static function list_dirs( $dir ) {
		$out = array();

		if ( ! is_dir( $dir ) ) {
			return $out;
		}

		foreach ( new \DirectoryIterator( $dir ) as $entry ) {
			if ( $entry->isDir() && ! $entry->isDot() ) {
				$out[] = $entry->getFilename();
			}
		}

		return $out;
	}
}
