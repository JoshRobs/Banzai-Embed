<?php
/**
 * Relocating builds compiled for another path.
 *
 * @package BanzaiEmbed
 */

namespace BanzaiEmbed;

defined( 'ABSPATH' ) || exit;

/**
 * Points a build's internal asset references at where it now lives.
 *
 * A build compiled with a root-absolute base — Vite's default `base: '/'`,
 * or a path like `/old-site/wp-content/plugins/my-app/` left over from
 * wherever it was hosted before — has that base baked into its JS and CSS:
 *
 *     url(/old-site/wp-content/plugins/my-app/assets/Font.woff2)
 *     "/assets/hero-3f2a.png"
 *
 * Under WordPress those 404, because the files are in uploads/banzaiembed.
 * This rewrites them in place, at upload time.
 *
 * Only exact `{base}{file}` references to files that are actually in the
 * build are touched. A bare base, or a path that is not one of the build's
 * own files, is left alone: `/app/` might be a router basename and
 * `/assets/data` an API route on the site, and rewriting those would break
 * the app in ways much harder to diagnose than a missing image.
 *
 * One runtime use of the bare base is recognisable, and the one that matters
 * most: Vite's preload helper, which every lazy chunk goes through —
 *
 *     il=`modulepreload`,al=function(e){return`/`+e}
 *
 * relocate_preload() points that at the build too. What is left — other
 * URLs assembled at runtime from the base, such as `import.meta.env.BASE_URL`
 * — Asset_Redirect catches when the browser requests them.
 */
final class Path_Rewriter {

	/**
	 * Files whose contents are rewritten.
	 */
	const TEXT_FILES = '/\.(m?js|cjs|css)$/i';

	/**
	 * Rewrite every reference to `{base}{file}` in the build's JS and CSS,
	 * and the base in Vite's preload helper.
	 *
	 * @param string $dir    Build directory.
	 * @param string $base   Base the build was compiled for: '/' or '/x/y/'.
	 * @param string $target Base the files now live at, with trailing slash.
	 * @return array{references: int, files: int, preload: bool}
	 */
	public function rewrite( $dir, $base, $target ) {
		$files      = Filesystem::list_files( $dir );
		$known      = array_flip( $files );
		$references = 0;
		$changed    = 0;
		$preload    = false;

		// Opened by a quote, backtick or `url(`; the path runs to the next
		// character that cannot be part of one (a quote, paren, ?, # or space).
		$pattern = '#(?<=["\'`(])' . preg_quote( $base, '#' ) . '([A-Za-z0-9_@~%+.\-/]+)#';

		foreach ( $files as $file ) {
			if ( ! preg_match( self::TEXT_FILES, $file ) || 0 === strpos( $file, '.vite/' ) ) {
				continue;
			}

			$count  = 0;
			$source = Filesystem::read( $dir . '/' . $file );
			$result = preg_replace_callback(
				$pattern,
				static function ( $m ) use ( $known, $target, &$count ) {
					if ( ! isset( $known[ $m[1] ] ) && ! isset( $known[ rawurldecode( $m[1] ) ] ) ) {
						return $m[0];
					}

					++$count;

					return $target . $m[1];
				},
				$source
			);

			$helpers = 0;

			if ( null !== $result && preg_match( '/\.m?js$/i', $file ) ) {
				$result = $this->relocate_preload( $result, $base, $target, $helpers );
			}

			if ( ( $count || $helpers ) && null !== $result && Filesystem::write( $dir . '/' . $file, $result ) ) {
				$references += $count;
				$preload     = $preload || $helpers > 0;
				++$changed;
			}
		}

		return array(
			'references' => $references,
			'files'      => $changed,
			'preload'    => $preload,
		);
	}

	/**
	 * Point Vite's preload helper at the build. Vite (Rollup or Rolldown,
	 * minified or not) emits it right after the "modulepreload" string:
	 *
	 *     const scriptRel = 'modulepreload';const assetsURL = function(dep) { return "/"+dep };
	 *     il=`modulepreload`,al=function(e){return`/`+e}
	 *
	 * Anchoring on that keeps every other `"/"+x` in the bundle — a router
	 * joining paths, say — untouched.
	 *
	 * @param string $source JS.
	 * @param string $base   Base the build was compiled for.
	 * @param string $target Base the files now live at.
	 * @param int    $count  Set to the number of helpers rewritten.
	 * @return string|null
	 */
	private function relocate_preload( $source, $base, $target, &$count ) {
		$q       = '["\'`]';
		$pattern = '#(' . $q . 'modulepreload' . $q . '\s*[,;]\s*(?:(?:const|let|var)\s+)?[\w$]+\s*=\s*'
			. '(?|function\s*\(\s*([\w$]+)\s*\)\s*\{\s*return\s*|\(?\s*([\w$]+)\s*\)?\s*=>\s*))'
			. '(' . $q . ')' . preg_quote( $base, '#' ) . '\3(\s*\+\s*\2(?![\w$]))#';

		return preg_replace_callback(
			$pattern,
			static function ( $m ) use ( $target ) {
				return $m[1] . $m[3] . $target . $m[3] . $m[4];
			},
			$source,
			-1,
			$count
		);
	}

	/**
	 * Whether a relocated build may still have broken lazy loading: it was
	 * compiled for a root-absolute base, has JS beyond its entry files, and
	 * its preload helper could not be relocated.
	 *
	 * @param array $app    Record.
	 * @param array $assets App_Manager::build_assets() for it.
	 * @return bool
	 */
	public static function needs_rebuild( array $app, array $assets ) {
		return '' !== $app['base'] && ! $app['preload_relocated'] && (bool) array_diff( $assets['scripts'], $app['scripts'] );
	}

	/**
	 * Explanation shown when needs_rebuild() is true.
	 *
	 * @param string $base The base the build was compiled for.
	 * @return string
	 */
	public static function rebuild_warning( $base ) {
		return sprintf(
			/* translators: %s: base path such as "/" or "/my-app/". */
			__( 'This build was compiled for the path %s. Its images, fonts and other files have been pointed at their new location, but it also has lazy-loaded chunks, which build their URLs at runtime and may fail to load. Rebuild with base: \'./\' in vite.config (or "homepage": "." for Create React App) and upload again.', 'banzaiembed' ),
			$base
		);
	}
}
