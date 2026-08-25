<?php
/**
 * Registration
 *
 * @package     ArrayPress\RegisterOnboarding
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       2.0.0
 */

declare( strict_types=1 );

use ArrayPress\RegisterOnboarding\Onboarding;
use ArrayPress\RegisterOnboarding\Wizard;

if ( ! function_exists( 'register_onboarding' ) ) {
	/**
	 * Register a setup wizard.
	 *
	 *     register_onboarding( 'myplugin-setup', [
	 *         'parent_slug' => 'options-general.php',
	 *         'logo'        => plugins_url( 'logo.svg', __FILE__ ),
	 *         'redirect'    => true,
	 *         'steps'       => [
	 *             'welcome' => [
	 *                 'type'    => 'content',
	 *                 'title'   => __( 'Welcome', 'my-plugin' ),
	 *                 'content' => '<p>' . esc_html__( 'Two questions and you are done.', 'my-plugin' ) . '</p>',
	 *             ],
	 *             'store'   => [
	 *                 'title'  => __( 'Your store', 'my-plugin' ),
	 *                 'fields' => [
	 *                     'myplugin_store_name' => [
	 *                         'type'     => 'text',
	 *                         'label'    => __( 'Store name', 'my-plugin' ),
	 *                         'required' => true,
	 *                     ],
	 *                 ],
	 *             ],
	 *         ],
	 *     ] );
	 *
	 * @param string               $id     The wizard's identifier.
	 * @param array<string, mixed> $config Its configuration.
	 *
	 * @return Wizard|null Null when the configuration has no steps.
	 */
	function register_onboarding( string $id, array $config ): ?Wizard {
		return Onboarding::register( $id, $config );
	}
}

if ( ! function_exists( 'onboarding_redirect_after_activation' ) ) {
	/**
	 * Send the user to a wizard once, after the plugin is activated.
	 *
	 * From the activation hook, where a redirect is not yet possible:
	 *
	 *     register_activation_hook( __FILE__, function () {
	 *         onboarding_redirect_after_activation( 'myplugin-setup' );
	 *     } );
	 *
	 * The wizard also has to be registered with `'redirect' => true`, so that
	 * turning the behaviour off does not mean finding every activation hook.
	 *
	 * @param string $id The wizard's identifier.
	 *
	 * @return void
	 */
	function onboarding_redirect_after_activation( string $id ): void {
		Onboarding::redirect_after_activation( $id );
	}
}

if ( ! function_exists( 'get_onboarding' ) ) {
	/**
	 * A registered wizard.
	 *
	 * @param string $id The wizard's identifier.
	 *
	 * @return Wizard|null
	 */
	function get_onboarding( string $id ): ?Wizard {
		return Onboarding::get( $id );
	}
}

if ( ! function_exists( 'get_onboarding_url' ) ) {
	/**
	 * Where a wizard is, so a plugin can link back to it.
	 *
	 * @param string $id   The wizard's identifier.
	 * @param string $step A step to open it on. The first by default.
	 *
	 * @return string Empty when no such wizard is registered.
	 */
	function get_onboarding_url( string $id, string $step = '' ): string {
		return Onboarding::get( $id )?->url( $step ) ?? '';
	}
}

if ( ! function_exists( 'is_onboarding_completed' ) ) {
	/**
	 * Whether a wizard has been finished.
	 *
	 *     if ( ! is_onboarding_completed( 'myplugin-setup' ) ) {
	 *         // Nudge them towards it.
	 *     }
	 *
	 * @param string $id The wizard's identifier.
	 *
	 * @return bool False when no such wizard is registered.
	 */
	function is_onboarding_completed( string $id ): bool {
		return (bool) Onboarding::get( $id )?->is_completed();
	}
}

if ( ! function_exists( 'reset_onboarding' ) ) {
	/**
	 * Let a wizard run again.
	 *
	 * @param string $id The wizard's identifier.
	 *
	 * @return bool
	 */
	function reset_onboarding( string $id ): bool {
		return (bool) Onboarding::get( $id )?->reset();
	}
}

if ( ! function_exists( 'unregister_onboarding' ) ) {
	/**
	 * Forget a wizard.
	 *
	 * @param string $id The wizard's identifier.
	 *
	 * @return bool
	 */
	function unregister_onboarding( string $id ): bool {
		return Onboarding::unregister( $id );
	}
}
