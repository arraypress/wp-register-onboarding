<?php
/**
 * Registers Menus Trait
 *
 * Handles WordPress admin menu page registration for onboarding wizards.
 * Captures the hook suffix returned by add_submenu_page() and stores
 * it on the wizard config for use by the sync step registration.
 *
 * @package     ArrayPress\RegisterOnboarding
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterOnboarding\Traits;

trait RegistersMenus {

	/**
	 * Register admin menu pages for all wizards
	 *
	 * After all menus are registered (giving us real hook suffixes),
	 * triggers sync step registration so the inline-sync library
	 * can hook admin_enqueue_scripts before it fires.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function register_menus(): void {
		foreach ( self::$wizards as $id => $config ) {
			self::register_menu( $id, $config );
		}

		// Now that we have real hook suffixes, register any sync steps
		self::register_sync_steps();
	}

	/**
	 * Register a single admin menu page
	 *
	 * Captures the hook suffix from add_submenu_page() and stores
	 * it on the wizard config for later use (sync step registration,
	 * asset enqueuing, etc).
	 *
	 * @param string $id     Wizard identifier.
	 * @param array  $config Wizard configuration.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function register_menu( string $id, array $config ): void {
		$render_callback = function () use ( $id ) {
			self::render_page( $id );
		};

		$hook_suffix = add_submenu_page(
			$config['parent_slug'] ?: null,
			$config['page_title'],
			$config['menu_title'],
			$config['capability'],
			$config['menu_slug'],
			$render_callback
		);

		// Store the real hook suffix on the wizard config
		if ( $hook_suffix ) {
			self::$wizards[ $id ]['hook_suffix'] = $hook_suffix;
		}
	}

}
