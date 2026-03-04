<?php
/**
 * Onboarding Wizard Registration Manager
 *
 * Central manager class for registering and rendering WordPress admin
 * onboarding wizards. Provides a configuration-driven approach to creating
 * multi-step setup flows with support for:
 *
 * - Automatic menu page registration (hidden or visible)
 * - Step-based navigation with progress indicator
 * - Built-in step types: welcome, fields, checklist, complete, callback, sync
 * - Field rendering with automatic saving via wp_options or custom callbacks
 * - Per-field and per-step validation with error display
 * - Select2 for searchable selects (countries, currencies, etc.)
 * - Option presets via arraypress/wp-currencies and arraypress/wp-countries
 * - Field dependencies for conditional visibility and attribute swapping
 * - Multiple dependency rules per field (show + swap simultaneously)
 * - Conditional step visibility via show_if callbacks
 * - Skippable steps with optional skip labels
 * - Inline sync step type via arraypress/wp-inline-sync
 * - Activation redirect on first run
 * - Color theming via CSS custom properties
 * - Confetti celebration on completion
 *
 * @package     ArrayPress\RegisterOnboarding
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterOnboarding;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Class Manager
 *
 * Static manager class for onboarding wizard registration and rendering.
 *
 * @since 1.0.0
 */
class Manager {

	use Traits\RegistersMenus;
	use Traits\EnqueuesAssets;
	use Traits\ProcessesSteps;
	use Traits\RendersPage;
	use Traits\RendersSteps;
	use Traits\RendersFields;
	use Traits\ManagesValues;
	use Traits\ResolvesOptions;

	/* =========================================================================
	 * PROPERTIES
	 * ========================================================================= */

	/**
	 * Registered wizards storage
	 *
	 * @since 1.0.0
	 * @var array<string, array>
	 */
	private static array $wizards = [];

	/**
	 * Asset enqueue flag
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private static bool $assets_enqueued = false;

	/**
	 * Initialization flag
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * Current step errors
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static array $errors = [];

	/**
	 * Current wizard ID (set during rendering/submission)
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private static string $current_wizard = '';

	/* =========================================================================
	 * REGISTRATION
	 * ========================================================================= */

	/**
	 * Register an onboarding wizard
	 *
	 * @param string $id     Unique wizard identifier.
	 * @param array  $config Wizard configuration array.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function register( string $id, array $config ): void {
		self::init();

		$defaults = [
			// Menu registration
			'page_title'         => '',
			'menu_title'         => '',
			'menu_slug'          => '',
			'parent_slug'        => '',
			'capability'         => 'manage_options',

			// Header
			'logo'               => '',
			'header_title'       => '',

			// Behavior
			'redirect'           => false,
			'completed_option'   => '',
			'completed_redirect' => '',

			// Custom value callbacks
			'get_callback'       => null,
			'update_callback'    => null,

			// Steps
			'steps'              => [],

			// Display
			'body_class'         => '',

			// Colors
			'colors'             => [],

			// Labels
			'labels'             => [],
		];

		$config = wp_parse_args( $config, $defaults );

		// Ensure menu_slug
		if ( empty( $config['menu_slug'] ) ) {
			$config['menu_slug'] = sanitize_key( $id );
		}

		// Parse labels
		$config['labels'] = wp_parse_args( $config['labels'], [
			'next'     => __( 'Continue', 'arraypress' ),
			'previous' => __( 'Back', 'arraypress' ),
			'skip'     => __( 'Skip this step', 'arraypress' ),
			'finish'   => __( 'Finish Setup', 'arraypress' ),
			'exit'     => __( 'Exit Setup', 'arraypress' ),
		] );

		// Auto-generate titles
		if ( empty( $config['page_title'] ) ) {
			$config['page_title'] = __( 'Setup Wizard', 'arraypress' );
		}
		if ( empty( $config['menu_title'] ) ) {
			$config['menu_title'] = $config['page_title'];
		}
		if ( empty( $config['header_title'] ) ) {
			$config['header_title'] = $config['page_title'];
		}

		// Auto-generate completed option key (normalize hyphens to underscores)
		if ( empty( $config['completed_option'] ) ) {
			$config['completed_option'] = str_replace( '-', '_', sanitize_key( $id ) ) . '_completed';
		}

		// Normalize steps
		$config['steps'] = self::normalize_steps( $config['steps'] );

		self::$wizards[ $id ] = $config;
	}

	/* =========================================================================
	 * INITIALIZATION
	 * ========================================================================= */

	/**
	 * Initialize the manager (runs once)
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;

		add_action( 'admin_menu', [ __CLASS__, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_filter( 'admin_body_class', [ __CLASS__, 'add_body_class' ] );
		add_action( 'admin_init', [ __CLASS__, 'process_step_submission' ] );
		add_action( 'admin_init', [ __CLASS__, 'maybe_redirect' ] );

		// Fix menu highlight when navigating between steps
		add_filter( 'parent_file', [ __CLASS__, 'fix_parent_menu_highlight' ] );
		add_filter( 'submenu_file', [ __CLASS__, 'fix_submenu_highlight' ] );
	}

	/* =========================================================================
	 * BODY CLASSES
	 * ========================================================================= */

	/**
	 * Add body classes for wizard pages
	 *
	 * @param string $classes Space-separated body classes.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function add_body_class( string $classes ): string {
		$page = $_GET['page'] ?? '';

		if ( empty( $page ) ) {
			return $classes;
		}

		foreach ( self::$wizards as $id => $config ) {
			if ( $config['menu_slug'] === $page ) {
				$classes .= ' onboarding-wizard';
				$classes .= ' onboarding-wizard-' . sanitize_html_class( $id );

				if ( ! empty( $config['body_class'] ) ) {
					$classes .= ' ' . sanitize_html_class( $config['body_class'] );
				}
				break;
			}
		}

		return $classes;
	}

	/* =========================================================================
	 * ACTIVATION REDIRECT
	 * ========================================================================= */

	/**
	 * Handle activation redirect
	 *
	 * Checks for a redirect transient set during plugin activation.
	 * Redirects to the wizard page if the wizard hasn't been completed.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function maybe_redirect(): void {
		foreach ( self::$wizards as $id => $config ) {
			if ( empty( $config['redirect'] ) ) {
				continue;
			}

			$transient_key = str_replace( '-', '_', sanitize_key( $id ) ) . '_redirect';

			if ( ! get_transient( $transient_key ) ) {
				continue;
			}

			delete_transient( $transient_key );

			if ( wp_doing_ajax() || isset( $_GET['activate-multi'] ) ) {
				continue;
			}

			if ( get_option( $config['completed_option'] ) ) {
				continue;
			}

			wp_safe_redirect( admin_url( 'admin.php?page=' . $config['menu_slug'] ) );
			exit;
		}
	}

	/**
	 * Set the redirect transient
	 *
	 * Call this from your plugin's activation hook to trigger the
	 * redirect on first admin page load. Use the register_onboarding_redirect()
	 * helper function for a cleaner API.
	 *
	 * @param string $id Wizard identifier.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function set_redirect( string $id ): void {
		set_transient( str_replace( '-', '_', sanitize_key( $id ) ) . '_redirect', 1, 30 );
	}

	/* =========================================================================
	 * STEP NAVIGATION
	 * ========================================================================= */

	/**
	 * Get visible step keys (respects show_if callbacks)
	 *
	 * @param array $config Wizard configuration.
	 *
	 * @return array Ordered array of visible step keys.
	 * @since 1.0.0
	 */
	private static function get_visible_step_keys( array $config ): array {
		$keys = [];

		foreach ( $config['steps'] as $key => $step ) {
			if ( ! empty( $step['show_if'] ) && is_callable( $step['show_if'] ) ) {
				if ( ! call_user_func( $step['show_if'] ) ) {
					continue;
				}
			}

			$keys[] = $key;
		}

		return $keys;
	}

	/**
	 * Get the next or previous step key
	 *
	 * @param array  $config    Wizard configuration.
	 * @param string $step_key  Current step key.
	 * @param string $direction 'next' or 'previous'.
	 *
	 * @return string|null
	 * @since 1.0.0
	 */
	private static function get_adjacent_step( array $config, string $step_key, string $direction ): ?string {
		$visible_keys = self::get_visible_step_keys( $config );
		$index        = array_search( $step_key, $visible_keys, true );

		if ( $index === false ) {
			return null;
		}

		if ( $direction === 'next' && isset( $visible_keys[ $index + 1 ] ) ) {
			return $visible_keys[ $index + 1 ];
		}

		if ( $direction === 'previous' && isset( $visible_keys[ $index - 1 ] ) ) {
			return $visible_keys[ $index - 1 ];
		}

		return null;
	}

	/**
	 * Get the URL for a specific step
	 *
	 * @param array  $config   Wizard configuration.
	 * @param string $step_key Step key.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private static function get_step_url( array $config, string $step_key ): string {
		return add_query_arg( [
			'page' => $config['menu_slug'],
			'step' => $step_key,
		], admin_url( 'admin.php' ) );
	}

	/* =========================================================================
	 * UTILITY METHODS
	 * ========================================================================= */

	/**
	 * Normalize step definitions with defaults
	 *
	 * @param array $steps Raw step definitions.
	 *
	 * @return array Normalized step definitions.
	 * @since 1.0.0
	 */
	private static function normalize_steps( array $steps ): array {
		$defaults = [
			'title'         => '',
			'description'   => '',
			'type'          => 'fields',
			'icon'          => '',
			'show_if'       => null,
			'before_render' => null,
			'skippable'     => false,
			'skip_label'    => '',
			'confetti'      => false,
			'sync_id'       => '',
			'fields'        => [],
			'items'         => [],
			'features'      => [],
			'links'         => [],
			'image'         => '',
			'redirect'      => '',
			'render'        => null,
			'validate'      => null,
			'save'          => null,
		];

		foreach ( $steps as $key => &$step ) {
			$step         = wp_parse_args( $step, $defaults );
			$step['_key'] = $key;
		}
		unset( $step );

		return $steps;
	}

	/**
	 * Build inline CSS style attribute from color overrides
	 *
	 * @param array $colors Color overrides from config.
	 *
	 * @return string The style attribute string or empty.
	 * @since 1.0.0
	 */
	private static function build_color_style( array $colors ): string {
		if ( empty( $colors ) ) {
			return '';
		}

		$map = [
			'accent'         => '--ob-accent',
			'accent_hover'   => '--ob-accent-hover',
			'accent_light'   => '--ob-accent-light',
			'success'        => '--ob-success',
			'error'          => '--ob-error',
			'text_primary'   => '--ob-text-primary',
			'text_secondary' => '--ob-text-secondary',
			'text_muted'     => '--ob-text-muted',
			'border'         => '--ob-border',
			'border_light'   => '--ob-border-light',
			'bg_white'       => '--ob-bg-white',
			'bg_subtle'      => '--ob-bg-subtle',
			'bg_page'        => '--ob-bg-page',
			'radius'         => '--ob-radius',
			'radius_lg'      => '--ob-radius-lg',
		];

		$declarations = [];

		foreach ( $colors as $key => $value ) {
			if ( empty( $value ) || ! isset( $map[ $key ] ) ) {
				continue;
			}
			$declarations[] = esc_attr( $map[ $key ] ) . ':' . esc_attr( $value );
		}

		if ( empty( $declarations ) ) {
			return '';
		}

		return ' style="' . implode( ';', $declarations ) . '"';
	}

	/* =========================================================================
	 * WIZARD MANAGEMENT
	 * ========================================================================= */

	/**
	 * Get a registered wizard configuration
	 *
	 * @param string $id Wizard identifier.
	 *
	 * @return array|null
	 * @since 1.0.0
	 */
	public static function get_wizard( string $id ): ?array {
		return self::$wizards[ $id ] ?? null;
	}

	/**
	 * Check if a wizard is registered
	 *
	 * @param string $id Wizard identifier.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function has_wizard( string $id ): bool {
		return isset( self::$wizards[ $id ] );
	}

	/**
	 * Check if a wizard has been completed
	 *
	 * @param string $id Wizard identifier.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function is_completed( string $id ): bool {
		if ( ! isset( self::$wizards[ $id ] ) ) {
			return false;
		}

		return (bool) get_option( self::$wizards[ $id ]['completed_option'] );
	}

	/**
	 * Reset a wizard's completion status
	 *
	 * @param string $id Wizard identifier.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function reset( string $id ): bool {
		if ( ! isset( self::$wizards[ $id ] ) ) {
			return false;
		}

		return delete_option( self::$wizards[ $id ]['completed_option'] );
	}

	/**
	 * Unregister a wizard
	 *
	 * @param string $id Wizard identifier.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function unregister( string $id ): bool {
		if ( isset( self::$wizards[ $id ] ) ) {
			unset( self::$wizards[ $id ] );

			return true;
		}

		return false;
	}

	/**
	 * Get all registered wizards
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_all_wizards(): array {
		return self::$wizards;
	}

}
