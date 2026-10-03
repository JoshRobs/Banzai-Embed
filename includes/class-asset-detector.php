<?php
/**
 * Entry file detection.
 *
 * @package BanzaiEmbed
 */

namespace BanzaiEmbed;

defined( 'ABSPATH' ) || exit;

/**
 * Works out which JS and CSS files boot an uploaded build.
 *
 * Tried in order, first hit wins:
 *
 * 1. index.html — the page the build tool itself generated, so its <script>
 *    and <link> tags are exactly what the tool intended to load, in order.
 * 2. A manifest — Vite's .vite/manifest.json (manifest.json before Vite 5),
 *    or the asset-manifest.json Create React App and webpack plugins write.
 * 3. Filename patterns — runtime, vendor and index/main/app bundles.
 * 4. Nothing — the user picks by hand on the edit screen.
 *
 * index.html also yields the mount element ID (`<div id="app">` for Vite +
 * Vue, `<div id="root">` for Vite + React and CRA). Defaulting to that ID is
 * what lets an unmodified app mount: its code targets that ID, not anything
 * this plugin makes up.
 */
final class Asset_Detector {

	/**
	 * Detect the entry files of a build.
	 *
	 * @param string $dir Absolute path of the build directory.
	 * @return array { scripts: string[], styles: string[], script_type: string,
	 *                 detected_by: string, mount_id: string, warnings: string[],
	 *                 base: string } `base` is the root-absolute path the build
	 *                 was compiled for ('/' or '/some/path/'), or '' when its
	 *                 references are relative or unknown.
	 */
	public function detect( $dir ) {
		$html   = $this->read_index_html( $dir );
		$result = null;

		if ( '' !== $html ) {
			$result = $this->from_index_html( $dir, $html );
		}

		if ( ! $result ) {
			$result = $this->from_vite_manifest( $dir );
		}

		if ( ! $result ) {
			$result = $this->from_asset_manifest( $dir );
		}

		if ( ! $result ) {
			$result = $this->from_patterns( $dir );
		}

		if ( ! $result ) {
			$result = array(
				'scripts'     => array(),
				'styles'      => array(),
				'script_type' => 'module',
				'detected_by' => '',
				'warnings'    => array( __( 'No entry files could be detected. Choose them below.', 'banzaiembed' ) ),
			);
		}

		$result['mount_id'] = '' !== $html ? $this->mount_id( $html ) : '';
		$result            += array( 'base' => '' );

		return $result;
	}

	/**
	 * The build's index.html, or ''.
	 *
	 * @param string $dir Build directory.
	 * @return string
	 */
	private function read_index_html( $dir ) {
		foreach ( array( 'index.html', 'index.htm' ) as $name ) {
			$html = Filesystem::read( $dir . '/' . $name );

			if ( '' !== $html ) {
				return $html;
			}
		}

		return '';
	}

	/**
	 * Strategy 1: the tags in index.html.
	 *
	 * @param string $dir  Build directory.
	 * @param string $html index.html contents.
	 * @return array|null
	 */
	private function from_index_html( $dir, $html ) {
		$scripts  = array();
		$styles   = array();
		$modules  = 0;
		$classics = 0;
		$warnings = array();
		$base     = '';
		$missing  = array();

		// Comments can hold commented-out tags.
		$html = preg_replace( '/<!--.*?-->/s', '', $html );

		preg_match_all( '/<script\b([^>]*)>/i', $html, $tags );

		foreach ( $tags[1] as $raw ) {
			$attrs = $this->attributes( $raw );

			// The legacy-browser half of @vitejs/plugin-legacy; modern
			// browsers ignore it, and so do we.
			if ( isset( $attrs['nomodule'] ) ) {
				continue;
			}

			if ( empty( $attrs['src'] ) ) {
				$type = isset( $attrs['type'] ) ? strtolower( $attrs['type'] ) : '';

				if ( '' === $type || 'module' === $type || 'text/javascript' === $type ) {
					$warnings[] = __( 'index.html contains an inline script, which is not loaded. If the app depends on it, move it into a file.', 'banzaiembed' );
				}

				continue;
			}

			$path = $this->resolve( $dir, $attrs['src'], $base );

			if ( null === $path ) {
				$missing[] = $attrs['src'];
				continue;
			}

			$scripts[] = $path;

			if ( isset( $attrs['type'] ) && 'module' === strtolower( $attrs['type'] ) ) {
				++$modules;
			} else {
				++$classics;
			}
		}

		preg_match_all( '/<link\b([^>]*)>/i', $html, $tags );

		foreach ( $tags[1] as $raw ) {
			$attrs = $this->attributes( $raw );
			$rel   = isset( $attrs['rel'] ) ? strtolower( $attrs['rel'] ) : '';

			if ( empty( $attrs['href'] ) || ! in_array( 'stylesheet', preg_split( '/\s+/', $rel ), true ) ) {
				continue;
			}

			$path = $this->resolve( $dir, $attrs['href'], $base );

			if ( null === $path ) {
				$missing[] = $attrs['href'];
				continue;
			}

			$styles[] = $path;
		}

		if ( ! $scripts ) {
			return null;
		}

		if ( $modules && $classics ) {
			$warnings[] = __( 'index.html mixes module and classic scripts. All scripts will be loaded as modules; switch the script type below if the app breaks.', 'banzaiembed' );
		}

		foreach ( array_unique( $missing ) as $ref ) {
			/* translators: %s: a path referenced from index.html. */
			$warnings[] = sprintf( __( 'index.html references %s, which is not in the zip.', 'banzaiembed' ), $ref );
		}

		return array(
			'scripts'     => array_values( array_unique( $scripts ) ),
			'styles'      => array_values( array_unique( $styles ) ),
			'script_type' => $modules ? 'module' : 'classic',
			'detected_by' => 'index-html',
			'warnings'    => array_values( array_unique( $warnings ) ),
			'base'        => $base,
		);
	}

	/**
	 * Strategy 2a: Vite's build manifest.
	 *
	 * @param string $dir Build directory.
	 * @return array|null
	 */
	private function from_vite_manifest( $dir ) {
		foreach ( array( '.vite/manifest.json', 'manifest.json' ) as $name ) {
			$manifest = json_decode( Filesystem::read( $dir . '/' . $name ), true );

			// A PWA's manifest.json is also called that; Vite's maps source
			// paths to chunk objects that each have a `file`.
			if ( ! is_array( $manifest ) || ! $manifest ) {
				continue;
			}

			$scripts = array();
			$styles  = array();

			foreach ( $manifest as $chunk ) {
				if ( ! is_array( $chunk ) || empty( $chunk['file'] ) || empty( $chunk['isEntry'] ) ) {
					continue;
				}

				if ( preg_match( '/\.(m?js|cjs)$/i', $chunk['file'] ) ) {
					$scripts[] = $chunk['file'];
				}

				$styles = array_merge( $styles, $this->vite_css( $manifest, $chunk, array() ) );
			}

			$scripts = array_values( array_filter( array_unique( $scripts ), array( $this, 'is_relative_file' ) ) );

			foreach ( $scripts as $script ) {
				if ( ! is_file( $dir . '/' . $script ) ) {
					$scripts = array();
					break;
				}
			}

			if ( $scripts ) {
				return array(
					'scripts'     => $scripts,
					'styles'      => array_values( array_filter( array_unique( $styles ), array( $this, 'is_relative_file' ) ) ),
					'script_type' => 'module',
					'detected_by' => 'vite-manifest',
					'warnings'    => array(),
				);
			}
		}

		return null;
	}

	/**
	 * CSS a Vite chunk needs, including what its static imports need.
	 *
	 * @param array    $manifest Whole manifest.
	 * @param array    $chunk    One chunk.
	 * @param string[] $seen     Chunk keys already visited.
	 * @return string[]
	 */
	private function vite_css( array $manifest, array $chunk, array $seen ) {
		$css = array();

		foreach ( isset( $chunk['imports'] ) ? (array) $chunk['imports'] : array() as $key ) {
			if ( isset( $manifest[ $key ] ) && is_array( $manifest[ $key ] ) && ! in_array( $key, $seen, true ) ) {
				$seen[] = $key;
				$css    = array_merge( $css, $this->vite_css( $manifest, $manifest[ $key ], $seen ) );
			}
		}

		return array_merge( $css, isset( $chunk['css'] ) ? (array) $chunk['css'] : array() );
	}

	/**
	 * Strategy 2b: asset-manifest.json from Create React App or webpack.
	 *
	 * @param string $dir Build directory.
	 * @return array|null
	 */
	private function from_asset_manifest( $dir ) {
		$manifest = json_decode( Filesystem::read( $dir . '/asset-manifest.json' ), true );

		if ( ! is_array( $manifest ) || empty( $manifest['entrypoints'] ) || ! is_array( $manifest['entrypoints'] ) ) {
			return null;
		}

		$scripts = array();
		$styles  = array();

		foreach ( $manifest['entrypoints'] as $entry ) {
			$entry = ltrim( (string) $entry, '/' );

			if ( ! $this->is_relative_file( $entry ) || ! is_file( $dir . '/' . $entry ) ) {
				continue;
			}

			if ( preg_match( '/\.(m?js|cjs)$/i', $entry ) ) {
				$scripts[] = $entry;
			} elseif ( preg_match( '/\.css$/i', $entry ) ) {
				$styles[] = $entry;
			}
		}

		if ( ! $scripts ) {
			return null;
		}

		return array(
			'scripts'     => $scripts,
			'styles'      => $styles,
			'script_type' => 'classic',
			'detected_by' => 'asset-manifest',
			'warnings'    => array(),
		);
	}

	/**
	 * Strategy 3: guess from filenames.
	 *
	 * @param string $dir Build directory.
	 * @return array|null
	 */
	private function from_patterns( $dir ) {
		$groups = array(
			'runtime' => '/^runtime([.~-]|$)/',
			'vendor'  => '/^(chunk-vendors|vendors?)([.~-]|$)/',
			'main'    => '/^(index|main|app|bundle)([.~-]|$)/',
		);
		$best   = array();
		$css    = array();
		$all    = array();

		foreach ( Filesystem::list_files( $dir ) as $file ) {
			if ( 0 === strpos( $file, '.vite/' ) ) {
				continue;
			}

			$base = strtolower( basename( $file ) );

			if ( preg_match( '/\.css$/', $base ) ) {
				$all[] = $file;

				if ( preg_match( '/^(index|main|app|style|styles|chunk-vendors)([.~-]|$)/', $base ) ) {
					$css[] = $file;
				}

				continue;
			}

			if ( ! preg_match( '/\.(m?js|cjs)$/', $base ) ) {
				continue;
			}

			foreach ( $groups as $group => $pattern ) {
				if ( preg_match( $pattern, $base ) ) {
					$size = (int) filesize( $dir . '/' . $file );

					// A Vite build can hold several index-*.js: lazy route
					// chunks get named after their index.vue. The entry,
					// which bundles the framework, is the largest.
					if ( ! isset( $best[ $group ] ) || $size > $best[ $group ]['size'] ) {
						$best[ $group ] = array(
							'file' => $file,
							'size' => $size,
						);
					}

					break;
				}
			}
		}

		if ( ! isset( $best['main'] ) ) {
			return null;
		}

		$scripts = array();

		foreach ( array_keys( $groups ) as $group ) {
			if ( isset( $best[ $group ] ) ) {
				$scripts[] = $best[ $group ]['file'];
			}
		}

		if ( ! $css && 1 === count( $all ) ) {
			$css = $all;
		}

		sort( $css );

		return array(
			'scripts'     => $scripts,
			'styles'      => $css,
			'script_type' => $this->looks_like_module( Filesystem::read( $dir . '/' . $best['main']['file'] ) ) ? 'module' : 'classic',
			'detected_by' => 'pattern',
			'warnings'    => array( __( 'There was no index.html or manifest, so the entry files were guessed from their names. Check them below.', 'banzaiembed' ) ),
		);
	}

	/**
	 * Whether source uses top-level import/export, so must load as a module.
	 *
	 * @param string $source JavaScript.
	 * @return bool
	 */
	private function looks_like_module( $source ) {
		return (bool) preg_match( '/(^|[;\n}])\s*(import\s*[{*"\'\w]|export\s*[{*\w])/', $source );
	}

	/**
	 * The ID of the first element with one in <body> — the mount point the
	 * app's own code targets.
	 *
	 * @param string $html index.html contents.
	 * @return string
	 */
	private function mount_id( $html ) {
		if ( ! preg_match( '/<body\b[^>]*>(.*)/is', $html, $body ) ) {
			return '';
		}

		preg_match_all( '/<(?:div|main|section)\b([^>]*)>/i', $body[1], $tags );

		foreach ( $tags[1] as $raw ) {
			$attrs = $this->attributes( $raw );

			if ( ! empty( $attrs['id'] ) && preg_match( '/^[A-Za-z][\w-]*$/', $attrs['id'] ) ) {
				return $attrs['id'];
			}
		}

		return '';
	}

	/**
	 * Parse the attributes out of the inside of a tag.
	 *
	 * @param string $raw Everything between the tag name and `>`.
	 * @return array<string,string> Lower-cased names; valueless ones map to ''.
	 */
	private function attributes( $raw ) {
		$out = array();

		preg_match_all( '/([^\s=\/>"\']+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>"\']+)))?/', $raw, $matches, PREG_SET_ORDER );

		foreach ( $matches as $m ) {
			$value = '';

			foreach ( array( 2, 3, 4 ) as $i ) {
				if ( isset( $m[ $i ] ) && '' !== $m[ $i ] ) {
					$value = $m[ $i ];
					break;
				}
			}

			$out[ strtolower( $m[1] ) ] = html_entity_decode( $value, ENT_QUOTES );
		}

		return $out;
	}

	/**
	 * Turn a reference from index.html into a stored entry path: a path
	 * relative to the build directory, or an absolute URL for external files.
	 *
	 * Root-absolute references (`/assets/x.js` — Vite's default `base: '/'`,
	 * CRA without `homepage`, or a base like `/old/site/path/`) are resolved
	 * by finding the file in the build, and $base is set to the prefix that
	 * was in front of it: the base the build was compiled for. Path_Rewriter
	 * uses it to fix the references inside the build's own JS and CSS.
	 *
	 * @param string $dir  Build directory.
	 * @param string $ref  src/href value.
	 * @param string $base Set to the build's base path when the reference
	 *                     was root-absolute, e.g. '/' or '/my-app/'.
	 * @return string|null Null when it cannot be found in the build.
	 */
	private function resolve( $dir, $ref, &$base ) {
		$ref = trim( $ref );

		if ( preg_match( '#^(https?:)?//#i', $ref ) ) {
			return 0 === strpos( $ref, '//' ) ? 'https:' . $ref : $ref;
		}

		$ref = preg_replace( '/[?#].*$/', '', $ref );

		if ( '' === $ref || preg_match( '/^[a-z][a-z0-9+.-]*:/i', $ref ) ) {
			return null;
		}

		if ( '/' === $ref[0] ) {
			// Try the full path, then with each leading segment dropped, which
			// also covers a non-root base such as `/my-app/`.
			$segments = array_values( array_filter( explode( '/', $ref ), 'strlen' ) );
			$dropped  = array();

			while ( $segments ) {
				$candidate = $this->normalise( implode( '/', $segments ) );

				if ( null !== $candidate && is_file( $dir . '/' . $candidate ) ) {
					$base = $dropped ? '/' . implode( '/', $dropped ) . '/' : '/';

					return $candidate;
				}

				$dropped[] = array_shift( $segments );
			}

			return null;
		}

		$path = $this->normalise( rawurldecode( $ref ) );

		return ( null !== $path && is_file( $dir . '/' . $path ) ) ? $path : null;
	}

	/**
	 * Collapse ./ and ../ in a relative path; null if it climbs out.
	 *
	 * @param string $path Relative path.
	 * @return string|null
	 */
	private function normalise( $path ) {
		$out = array();

		foreach ( explode( '/', $path ) as $segment ) {
			if ( '' === $segment || '.' === $segment ) {
				continue;
			}

			if ( '..' === $segment ) {
				if ( ! $out ) {
					return null;
				}

				array_pop( $out );
				continue;
			}

			$out[] = $segment;
		}

		return $out ? implode( '/', $out ) : null;
	}

	/**
	 * Whether a manifest path is a plain relative path inside the build.
	 *
	 * @param string $path Path.
	 * @return bool
	 */
	public function is_relative_file( $path ) {
		return is_string( $path ) && '' !== $path && null !== $this->normalise( $path ) && false === strpos( $path, '..' ) && ! preg_match( '#^([a-z]+:|/)#i', $path );
	}
}
