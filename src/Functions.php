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