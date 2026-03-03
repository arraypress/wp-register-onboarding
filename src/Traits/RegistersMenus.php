<?php
/**
 * Registers Menus Trait
 *
 * Handles WordPress admin menu page registration for onboarding wizards.
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
	 * @return void
	 * @since 1.0.0
	 */
	public static function register_menus(): void {
		foreach ( self::$wizards as $id => $config ) {
			self::register_menu( $id, $config );
		}
	}

	/**
	 * Register a single admin menu page
	 *
	 * When parent_slug is empty, the wizard is registered as a hidden
	 * page that doesn't appear in the admin sidebar.
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

		add_submenu_page(
			$config['parent_slug'] ?: null,
			$config['page_title'],
			$config['menu_title'],
			$config['capability'],
			$config['menu_slug'],
			$render_callback
		);
	}

}
