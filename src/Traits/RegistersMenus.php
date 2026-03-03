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

	/**
	 * Fix parent menu highlight for wizard pages
	 *
	 * When a wizard has a parent_slug, WordPress loses the menu
	 * highlight after step navigation changes the URL to admin.php.
	 * This ensures the correct parent menu stays highlighted.
	 *
	 * @param string $parent_file The parent file.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function fix_parent_menu_highlight( string $parent_file ): string {
		global $plugin_page;

		foreach ( self::$wizards as $config ) {
			if ( ! empty( $config['parent_slug'] ) && $plugin_page === $config['menu_slug'] ) {
				return $config['parent_slug'];
			}
		}

		return $parent_file;
	}

	/**
	 * Fix submenu highlight for wizard pages
	 *
	 * Ensures the correct submenu item stays highlighted when
	 * navigating between wizard steps.
	 *
	 * @param string|null $submenu_file The submenu file.
	 *
	 * @return string|null
	 * @since 1.0.0
	 */
	public static function fix_submenu_highlight( ?string $submenu_file ): ?string {
		global $plugin_page;

		foreach ( self::$wizards as $config ) {
			if ( ! empty( $config['parent_slug'] ) && $plugin_page === $config['menu_slug'] ) {
				return $config['menu_slug'];
			}
		}

		return $submenu_file;
	}

}
