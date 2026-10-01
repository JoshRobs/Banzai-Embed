<?php
/**
 * License state.
 *
 * @package BanzaiEmbed
 */

namespace BanzaiEmbed;

defined( 'ABSPATH' ) || exit;

/**
 * Where the plugin's paid/free line is drawn.
 *
 * Same seam as BanzaiStyle's: one question the plugin asks, one place that
 * asks Freemius, failing closed when the SDK is not loaded. Until Freemius is
 * wired in (see the note in banzaiembed.php) it is always closed unless
 * BZEM_SIMULATE_PRO is defined.
 */
final class License {

	/**
	 * Whether a paid tier exists to show at all.
	 *
	 * False until there is a checkout to send people to — a "Pro" badge with
	 * nowhere to buy is an advert for a dead end. It does not gate is_valid().
	 */
	const PRO_AVAILABLE = false;

	/**
	 * Whether the paid tier is on show.
	 *
	 * @return bool
	 */
	public static function is_pro_available() {
		return self::PRO_AVAILABLE;
	}

	/**
	 * Whether pro features are unlocked.
	 *
	 * Call bzem_has_valid_license() rather than this directly.
	 *
	 * @return bool
	 */
	public static function is_valid() {
		// For exercising the unlocked path without a license. wp-config.php only.
		if ( defined( 'BZEM_SIMULATE_PRO' ) && BZEM_SIMULATE_PRO ) {
			return true;
		}

		// Fail closed rather than fatal when the SDK is not in the build.
		if ( ! function_exists( 'banzaiembed_fs' ) ) {
			return false;
		}

		return (bool) \banzaiembed_fs()->can_use_premium_code();
	}
}
