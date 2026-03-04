<?php
/**
 * Onboarding Wizard Helper Functions
 *
 * Global helper functions for registering onboarding setup wizards.
 * These functions provide a convenient procedural API for the Manager class.
 *
 * @package     ArrayPress\RegisterOnboarding
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

use ArrayPress\RegisterOnboarding\Manager;

if ( ! function_exists( 'register_onboarding' ) ) {
	/**
	 * Register an onboarding wizard
	 *
	 * Registers a new admin page that guides users through a multi-step
	 * setup wizard. The admin menu page is automatically created and
	 * steps are rendered with navigation, progress tracking, validation,
	 * and auto-saving to wp_options.
	 *
	 * @param string $id     Unique wizard identifier. Used in hooks and internally.
	 * @param array  $config Wizard configuration array. See Manager class for options.
	 *
	 * @return void
	 * @since 1.0.0
	 *
	 * @example
	 * register_onboarding( 'my-plugin-setup', [
	 *     'page_title'  => 'Setup Wizard',
	 *     'menu_slug'   => 'my-plugin-setup',
	 *     'logo'        => plugin_dir_url( __FILE__ ) . 'logo.png',
	 *     'steps'       => [
	 *         'welcome' => [
	 *             'title' => 'Welcome',
	 *             'type'  => 'welcome',
	 *             'description' => 'Let\'s get you set up.',
	 *         ],
	 *         'settings' => [
	 *             'title'  => 'Settings',
	 *             'type'   => 'fields',
	 *             'fields' => [ ... ],
	 *         ],
	 *         'done' => [
	 *             'title' => 'All Done!',
	 *             'type'  => 'complete',
	 *         ],
	 *     ],
	 * ] );
	 */
	function register_onboarding( string $id, array $config ): void {
		Manager::register( $id, $config );
	}
}

if ( ! function_exists( 'register_onboarding_redirect' ) ) {
	/**
	 * Set the activation redirect for an onboarding wizard
	 *
	 * Call this from your plugin's register_activation_hook callback
	 * to redirect the user to the wizard on first activation.
	 *
	 * @param string $id Wizard identifier matching the one used in register_onboarding().
	 *
	 * @return void
	 * @since 1.0.0
	 *
	 * @example
	 * register_activation_hook( __FILE__, function() {
	 *     register_onboarding_redirect( 'my-plugin-setup' );
	 * } );
	 */
	function register_onboarding_redirect( string $id ): void {
		Manager::set_redirect( $id );
	}
}

if ( ! function_exists( 'get_onboarding_wizard' ) ) {
	/**
	 * Get a registered wizard's configuration
	 *
	 * @param string $id Wizard identifier.
	 *
	 * @return array|null Wizard configuration or null if not registered.
	 * @since 1.0.0
	 */
	function get_onboarding_wizard( string $id ): ?array {
		return Manager::get_wizard( $id );
	}
}

if ( ! function_exists( 'is_onboarding_completed' ) ) {
	/**
	 * Check if an onboarding wizard has been completed
	 *
	 * @param string $id Wizard identifier.
	 *
	 * @return bool True if the wizard has been completed.
	 * @since 1.0.0
	 *
	 * @example
	 * if ( ! is_onboarding_completed( 'my-plugin-setup' ) ) {
	 *     add_action( 'admin_notices', 'show_setup_reminder' );
	 * }
	 */
	function is_onboarding_completed( string $id ): bool {
		return Manager::is_completed( $id );
	}
}

if ( ! function_exists( 'reset_onboarding' ) ) {
	/**
	 * Reset an onboarding wizard's completion status
	 *
	 * Allows the wizard to run again by removing its completion flag.
	 *
	 * @param string $id Wizard identifier.
	 *
	 * @return bool True if reset was successful.
	 * @since 1.0.0
	 */
	function reset_onboarding( string $id ): bool {
		return Manager::reset( $id );
	}
}

if ( ! function_exists( 'has_onboarding_wizard' ) ) {
	/**
	 * Check if an onboarding wizard is registered
	 *
	 * @param string $id Wizard identifier.
	 *
	 * @return bool True if the wizard is registered.
	 * @since 1.0.0
	 */
	function has_onboarding_wizard( string $id ): bool {
		return Manager::has_wizard( $id );
	}
}

if ( ! function_exists( 'unregister_onboarding' ) ) {
	/**
	 * Unregister an onboarding wizard
	 *
	 * @param string $id Wizard identifier.
	 *
	 * @return bool True if the wizard was unregistered.
	 * @since 1.0.0
	 */
	function unregister_onboarding( string $id ): bool {
		return Manager::unregister( $id );
	}
}
