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
 * asks Freemius, failing closed when the SDK is not loaded. In development,
 * BZEM_SIMULATE_PRO forces it open (true) or closed (false).
 */
final class License {

	/**
	 * Whether a paid tier exists to show at all.
	 *
	 * Only true while there is a checkout to send people to — a "Pro" badge
	 * with nowhere to buy is an advert for a dead end. It does not gate
	 * is_valid().
	 */
	const PRO_AVAILABLE = true;

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
		// For exercising either path whatever the real licence says: true opens
		// the gate, false closes it. wp-config.php only.
		if ( defined( 'BZEM_SIMULATE_PRO' ) ) {
			return (bool) BZEM_SIMULATE_PRO;
		}

		// Fail closed rather than fatal when the SDK is not in the build.
		if ( ! function_exists( 'bzem_fs' ) ) {
			return false;
		}

		return (bool) \bzem_fs()->can_use_premium_code();
	}
}
