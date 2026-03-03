<?php
/**
 * Resolves Options Trait
 *
 * Handles resolution of option presets for select and radio fields.
 * Supports built-in presets (currencies, countries, timezones) and
 * custom presets via filter.
 *
 * @package     ArrayPress\RegisterOnboarding
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterOnboarding\Traits;

use ArrayPress\Currencies\Currency;
use ArrayPress\Countries\Countries;

trait ResolvesOptions {

	/**
	 * Resolve options — handles presets and raw arrays
	 *
	 * Supports string presets ('currencies', 'countries', 'timezones')
	 * and custom presets via the arraypress_onboarding_preset_{name} filter.
	 *
	 * @param string|array $options Options definition.
	 *
	 * @return array key => label pairs.
	 * @since 1.0.0
	 */
	private static function resolve_options( $options ): array {
		if ( is_array( $options ) ) {
			return $options;
		}

		if ( ! is_string( $options ) ) {
			return [];
		}

		switch ( $options ) {
			case 'currencies':
				return Currency::get_options();

			case 'countries':
				return Countries::all();

			case 'timezones':
				return self::get_timezone_options();

			default:
				/**
				 * Filter to register custom option presets
				 *
				 * @param array $options Empty array.
				 *
				 * @return array key => label pairs.
				 * @since 1.0.0
				 */
				return apply_filters( 'arraypress_onboarding_preset_' . $options, [] );
		}
	}

	/**
	 * Get timezone options
	 *
	 * @return array
	 * @since 1.0.0
	 */
	private static function get_timezone_options(): array {
		$timezones = [];
		$zones     = timezone_identifiers_list();

		foreach ( $zones as $zone ) {
			$parts = explode( '/', $zone, 2 );

			if ( count( $parts ) === 2 ) {
				$label              = str_replace( [ '/', '_' ], [ ' — ', ' ' ], $parts[1] );
				$timezones[ $zone ] = $parts[0] . ' — ' . $label;
			}
		}

		return $timezones;
	}

}
