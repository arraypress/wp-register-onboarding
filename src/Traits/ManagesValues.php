<?php
/**
 * Manages Values Trait
 *
 * Handles get/update value operations using custom callbacks
 * or falling back to wp_options.
 *
 * @package     ArrayPress\RegisterOnboarding
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterOnboarding\Traits;

trait ManagesValues {

	/**
	 * Get a stored value using custom callback or wp_options fallback
	 *
	 * When get_callback is provided, the field's 'option' key becomes
	 * a key within that system rather than a standalone wp_options key.
	 *
	 * @param array  $config  Wizard configuration.
	 * @param string $key     Option/field key.
	 * @param mixed  $default Default value.
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	private static function get_value( array $config, string $key, $default = '' ) {
		if ( ! empty( $config['get_callback'] ) && is_callable( $config['get_callback'] ) ) {
			return call_user_func( $config['get_callback'], $key, $default );
		}

		return get_option( $key, $default );
	}

	/**
	 * Update a stored value using custom callback or wp_options fallback
	 *
	 * When update_callback is provided, the field's 'option' key becomes
	 * a key within that system rather than a standalone wp_options key.
	 *
	 * @param array  $config Wizard configuration.
	 * @param string $key    Option/field key.
	 * @param mixed  $value  Value to store.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function update_value( array $config, string $key, $value ): void {
		if ( ! empty( $config['update_callback'] ) && is_callable( $config['update_callback'] ) ) {
			call_user_func( $config['update_callback'], $key, $value );

			return;
		}

		update_option( $key, $value );
	}

}
