<?php
/**
 * Runtime Key Derivation
 *
 * @package     ArrayPress\RegisterOnboarding
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       2.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterOnboarding\Utils;

/**
 * Every runtime string this library registers, derived from its own namespace.
 *
 * Strauss rewrites class namespaces and leaves string literals alone. Two
 * plugins each bundling a prefixed copy of this library therefore get
 * distinct classes but would otherwise register identical script handles and
 * identical transient keys.
 *
 * That is not merely untidy. `wp_enqueue_style()` ignores a handle that is
 * already registered, so the plugin that enqueued second gets the other
 * plugin's stylesheet — from a directory its own build may not even contain.
 * And a shared transient means one plugin's activation sends the user to the
 * other plugin's wizard.
 *
 * The derivation exploits the one thing Strauss does rewrite: this file's
 * namespace. In a prefixed build `__NAMESPACE__` begins with the consumer's
 * prefix ("MyPlugin\ArrayPress\RegisterOnboarding\Utils"), unique per plugin
 * by construction, so every key comes out distinct with no configuration.
 */
final class Runtime {

	/**
	 * This library's own identifier, used when running unprefixed.
	 */
	private const LIBRARY = 'onboarding';

	/**
	 * The per-build prefix.
	 *
	 * "onboarding" for a plain Composer install — development, or a single
	 * consumer that does not use Strauss — and "{prefix}-onboarding" for a
	 * prefixed build.
	 *
	 * @return string
	 */
	public static function prefix(): string {
		$segments = explode( '\\', __NAMESPACE__ );
		$root     = $segments[0] ?? '';

		if ( '' === $root || 'ArrayPress' === $root ) {
			return self::LIBRARY;
		}

		return self::slug( $root ) . '-' . self::LIBRARY;
	}

	/**
	 * A script or style handle for this build.
	 *
	 * @param string $suffix Optional handle suffix.
	 *
	 * @return string
	 */
	public static function handle( string $suffix = '' ): string {
		return '' === $suffix ? self::prefix() : self::prefix() . '-' . $suffix;
	}

	/**
	 * An option or transient key for this build.
	 *
	 * @param string $suffix Optional key suffix.
	 *
	 * @return string
	 */
	public static function key( string $suffix = '' ): string {
		$base = str_replace( '-', '_', self::prefix() );

		return '' === $suffix ? $base : $base . '_' . $suffix;
	}

	/**
	 * Reduce a namespace segment to a lowercase slug.
	 *
	 * Not sanitize_title(): this runs from `__NAMESPACE__` at class-load
	 * time, which can precede WordPress being fully loaded.
	 *
	 * @param string $value Value to slug.
	 *
	 * @return string
	 */
	private static function slug( string $value ): string {
		$value = preg_replace( '/[^A-Za-z0-9]+/', '-', $value ) ?? '';

		return strtolower( trim( $value, '-' ) );
	}
}
