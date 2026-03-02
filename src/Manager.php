<?php
/**
 * Onboarding Wizard Registration Manager
 *
 * Central manager class for registering and rendering WordPress admin
 * onboarding wizards. Provides a configuration-driven approach to creating
 * multi-step setup flows with support for:
 * - Automatic menu page registration (hidden from nav)
 * - Step-based navigation with progress indicator
 * - Built-in step types: welcome, fields, checklist, complete, callback
 * - Field rendering with automatic wp_options saving
 * - Per-field and per-step validation with error display
 * - Option presets for common data (countries, currencies, timezones)
 * - Conditional step visibility via show_if callbacks
 * - Skippable steps with optional skip labels
 * - Activation redirect on first run
 * - Color theming via CSS custom properties
 * - AJAX step saving with nonce verification
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
			'page_title'  => '',
			'menu_title'  => '',
			'menu_slug'   => '',
			'parent_slug' => '',
			'capability'  => 'manage_options',

			// Header
			'logo'         => '',
			'header_title' => '',

			// Behavior
			'redirect'        => false,
			'completed_option' => '',

			// Steps
			'steps' => [],

			// Display
			'body_class' => '',

			// Colors
			'colors' => [],

			// Labels
			'labels' => [],
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

		// Auto-generate completed option key
		if ( empty( $config['completed_option'] ) ) {
			$config['completed_option'] = sanitize_key( $id ) . '_completed';
		}

		// Normalize steps
		$config['steps'] = self::normalize_steps( $config['steps'] );

		self::$wizards[ $id ] = $config;
	}

	/* =========================================================================
	 * INITIALIZATION
	 * ========================================================================= */

	/**
	 * Initialize the manager
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
	}

	/* =========================================================================
	 * MENU REGISTRATION
	 * ========================================================================= */

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
	 * Wizards are registered as hidden submenu pages under the parent slug
	 * so they don't clutter the admin menu.
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

		if ( ! empty( $config['parent_slug'] ) ) {
			add_submenu_page(
				$config['parent_slug'],
				$config['page_title'],
				$config['menu_title'],
				$config['capability'],
				$config['menu_slug'],
				$render_callback
			);
		} else {
			// Register as a hidden page (null parent)
			add_submenu_page(
				null,
				$config['page_title'],
				$config['menu_title'],
				$config['capability'],
				$config['menu_slug'],
				$render_callback
			);
		}
	}

	/* =========================================================================
	 * ASSETS
	 * ========================================================================= */

	/**
	 * Enqueue assets
	 *
	 * @param string $hook Current admin page hook.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function enqueue_assets( string $hook ): void {
		$page = $_GET['page'] ?? '';

		if ( empty( $page ) ) {
			return;
		}

		foreach ( self::$wizards as $id => $config ) {
			if ( ( $config['menu_slug'] ?? '' ) === $page ) {
				self::do_enqueue_assets( $config );
				break;
			}
		}
	}

	/**
	 * Actually enqueue the assets
	 *
	 * @param array $config Wizard configuration.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function do_enqueue_assets( array $config ): void {
		if ( self::$assets_enqueued ) {
			return;
		}
		self::$assets_enqueued = true;

		wp_enqueue_composer_style(
			'onboarding-wizard-styles',
			__FILE__,
			'css/onboarding-wizard.css'
		);

		wp_enqueue_composer_script(
			'onboarding-wizard-scripts',
			__FILE__,
			'js/onboarding-wizard.js',
			[],
			true
		);
	}

	/* =========================================================================
	 * ACTIVATION REDIRECT
	 * ========================================================================= */

	/**
	 * Handle activation redirect
	 *
	 * When 'redirect' is enabled, the wizard sets a transient on plugin
	 * activation that triggers a one-time redirect to the setup page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function maybe_redirect(): void {
		foreach ( self::$wizards as $id => $config ) {
			if ( empty( $config['redirect'] ) ) {
				continue;
			}

			$transient_key = sanitize_key( $id ) . '_redirect';

			if ( ! get_transient( $transient_key ) ) {
				continue;
			}

			delete_transient( $transient_key );

			// Don't redirect on bulk activations or AJAX
			if ( wp_doing_ajax() || isset( $_GET['activate-multi'] ) ) {
				continue;
			}

			// Don't redirect if already completed
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
	 * Call this from your plugin's activation hook to trigger the redirect.
	 *
	 * @param string $id Wizard identifier.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function set_redirect( string $id ): void {
		$transient_key = sanitize_key( $id ) . '_redirect';
		set_transient( $transient_key, 1, 30 );
	}

	/* =========================================================================
	 * STEP SUBMISSION
	 * ========================================================================= */

	/**
	 * Process step form submissions
	 *
	 * Handles validation and saving when a step form is submitted.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function process_step_submission(): void {
		if ( empty( $_POST['onboarding_wizard_id'] ) || empty( $_POST['onboarding_step'] ) ) {
			return;
		}

		$wizard_id = sanitize_key( $_POST['onboarding_wizard_id'] );
		$step_key  = sanitize_key( $_POST['onboarding_step'] );
		$direction = sanitize_key( $_POST['onboarding_direction'] ?? 'next' );

		if ( ! isset( self::$wizards[ $wizard_id ] ) ) {
			return;
		}

		$config = self::$wizards[ $wizard_id ];

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'onboarding_step_' . $wizard_id . '_' . $step_key ) ) {
			wp_die( __( 'Security check failed.', 'arraypress' ) );
		}

		// Check capability
		if ( ! current_user_can( $config['capability'] ) ) {
			wp_die( __( 'You do not have permission to access this page.', 'arraypress' ) );
		}

		$step = $config['steps'][ $step_key ] ?? null;

		if ( ! $step ) {
			return;
		}

		// Going back — skip validation and saving
		if ( $direction === 'previous' ) {
			$prev_key = self::get_adjacent_step( $config, $step_key, 'previous' );
			if ( $prev_key ) {
				wp_safe_redirect( self::get_step_url( $config, $prev_key ) );
				exit;
			}
		}

		// Skipping — no validation, just advance
		if ( $direction === 'skip' ) {
			$next_key = self::get_adjacent_step( $config, $step_key, 'next' );
			if ( $next_key ) {
				wp_safe_redirect( self::get_step_url( $config, $next_key ) );
				exit;
			}
		}

		// Validate and save the step
		$errors = self::validate_step( $step, $_POST );

		if ( ! empty( $errors ) ) {
			// Store errors for display — they'll be picked up on re-render
			self::$errors = $errors;

			return;
		}

		// Save the step data
		self::save_step( $step, $_POST );

		// Mark as completed if this is the last step
		$visible_keys = self::get_visible_step_keys( $config );
		$last_key     = end( $visible_keys );

		if ( $step_key === $last_key ) {
			update_option( $config['completed_option'], time() );

			/**
			 * Fires when an onboarding wizard is completed
			 *
			 * @param string $wizard_id Wizard identifier.
			 * @param array  $config    Wizard configuration.
			 *
			 * @since 1.0.0
			 */
			do_action( 'arraypress_onboarding_completed', $wizard_id, $config );
			do_action( "arraypress_onboarding_completed_{$wizard_id}", $config );

			// If the complete step has a redirect URL, use it
			if ( ! empty( $step['redirect'] ) ) {
				wp_safe_redirect( $step['redirect'] );
				exit;
			}
		}

		// Advance to next step
		$next_key = self::get_adjacent_step( $config, $step_key, 'next' );

		if ( $next_key ) {
			wp_safe_redirect( self::get_step_url( $config, $next_key ) );
			exit;
		}

		// No next step — redirect to admin
		wp_safe_redirect( admin_url() );
		exit;
	}

	/* =========================================================================
	 * VALIDATION
	 * ========================================================================= */

	/**
	 * Validate a step's submitted data
	 *
	 * @param array $step Step configuration.
	 * @param array $data Submitted POST data.
	 *
	 * @return array Array of field_key => error message pairs.
	 * @since 1.0.0
	 */
	private static function validate_step( array $step, array $data ): array {
		$errors = [];

		// Step-level validation callback (for callback type steps)
		if ( ! empty( $step['validate'] ) && is_callable( $step['validate'] ) ) {
			$result = call_user_func( $step['validate'], $data );

			if ( is_wp_error( $result ) ) {
				$errors['_step'] = $result->get_error_message();
			} elseif ( $result === false ) {
				$errors['_step'] = __( 'Validation failed.', 'arraypress' );
			}
		}

		// Field-level validation
		if ( in_array( $step['type'], [ 'fields', 'checklist' ], true ) ) {
			$fields = $step['type'] === 'fields' ? ( $step['fields'] ?? [] ) : [];

			foreach ( $fields as $key => $field ) {
				if ( empty( $field['validate'] ) ) {
					continue;
				}

				$value = $data[ $key ] ?? '';

				if ( is_callable( $field['validate'] ) ) {
					$result = call_user_func( $field['validate'], $value );

					if ( is_wp_error( $result ) ) {
						$errors[ $key ] = $result->get_error_message();
					} elseif ( $result === false ) {
						$errors[ $key ] = sprintf(
						/* translators: %s: Field label */
							__( '%s is invalid.', 'arraypress' ),
							$field['label'] ?? $key
						);
					}
				}
			}
		}

		return $errors;
	}

	/* =========================================================================
	 * SAVING
	 * ========================================================================= */

	/**
	 * Save a step's data
	 *
	 * @param array $step Step configuration.
	 * @param array $data Submitted POST data.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function save_step( array $step, array $data ): void {
		// Custom save callback
		if ( ! empty( $step['save'] ) && is_callable( $step['save'] ) ) {
			call_user_func( $step['save'], $data );

			return;
		}

		// Auto-save fields type
		if ( $step['type'] === 'fields' && ! empty( $step['fields'] ) ) {
			foreach ( $step['fields'] as $key => $field ) {
				if ( empty( $field['option'] ) ) {
					continue;
				}

				$value = $data[ $key ] ?? $field['default'] ?? '';
				$value = self::sanitize_field_value( $field, $value );

				update_option( $field['option'], $value );
			}
		}

		// Auto-save checklist type
		if ( $step['type'] === 'checklist' && ! empty( $step['items'] ) ) {
			foreach ( $step['items'] as $index => $item ) {
				if ( empty( $item['option'] ) ) {
					continue;
				}

				// Checkboxes: present in POST = checked, absent = unchecked
				$value = isset( $data['checklist'][ $index ] ) ? true : false;

				update_option( $item['option'], $value );
			}
		}
	}

	/**
	 * Sanitize a field value based on its type
	 *
	 * @param array  $field Field configuration.
	 * @param mixed  $value Raw value.
	 *
	 * @return mixed Sanitized value.
	 * @since 1.0.0
	 */
	private static function sanitize_field_value( array $field, $value ) {
		// Custom sanitize callback
		if ( ! empty( $field['sanitize'] ) && is_callable( $field['sanitize'] ) ) {
			return call_user_func( $field['sanitize'], $value );
		}

		switch ( $field['type'] ?? 'text' ) {
			case 'email':
				return sanitize_email( $value );

			case 'url':
				return esc_url_raw( $value );

			case 'number':
				return intval( $value );

			case 'textarea':
				return sanitize_textarea_field( $value );

			case 'toggle':
				return (bool) $value;

			case 'select':
			case 'radio':
				// Validate against known options
				$options = self::resolve_options( $field['options'] ?? [] );
				if ( isset( $options[ $value ] ) ) {
					return sanitize_text_field( $value );
				}

				return $field['default'] ?? '';

			case 'text':
			default:
				return sanitize_text_field( $value );
		}
	}

	/* =========================================================================
	 * BODY CLASSES
	 * ========================================================================= */

	/**
	 * Add body classes
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
	 * PAGE RENDERING
	 * ========================================================================= */

	/**
	 * Render the wizard page
	 *
	 * @param string $id Wizard identifier.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function render_page( string $id ): void {
		if ( ! isset( self::$wizards[ $id ] ) ) {
			return;
		}

		$config       = self::$wizards[ $id ];
		$visible_keys = self::get_visible_step_keys( $config );

		if ( empty( $visible_keys ) ) {
			return;
		}

		// Determine current step
		$current_key = sanitize_key( $_GET['step'] ?? '' );

		if ( empty( $current_key ) || ! in_array( $current_key, $visible_keys, true ) ) {
			$current_key = $visible_keys[0];
		}

		$current_step  = $config['steps'][ $current_key ];
		$current_index = array_search( $current_key, $visible_keys, true );
		$total_steps   = count( $visible_keys );
		$is_first      = $current_index === 0;
		$is_last       = $current_index === $total_steps - 1;

		// Build inline color overrides
		$style_attr = self::build_color_style( $config['colors'] );

		/**
		 * Fires before the wizard renders
		 *
		 * @param string $id     Wizard identifier.
		 * @param array  $config Wizard configuration.
		 *
		 * @since 1.0.0
		 */
		do_action( 'arraypress_before_render_onboarding', $id, $config );
		do_action( "arraypress_before_render_onboarding_{$id}", $config );

		?>
		<div class="onboarding-wrap"<?php echo $style_attr; ?>>
			<div class="onboarding-container">

				<?php self::render_header( $config ); ?>
				<?php self::render_progress( $config, $visible_keys, $current_index ); ?>

				<div class="onboarding-step-wrap">
					<?php self::render_step_header( $current_step, $current_index, $total_steps ); ?>

					<form method="post" class="onboarding-form" novalidate>
						<?php wp_nonce_field( 'onboarding_step_' . $id . '_' . $current_key ); ?>
						<input type="hidden" name="onboarding_wizard_id" value="<?php echo esc_attr( $id ); ?>">
						<input type="hidden" name="onboarding_step" value="<?php echo esc_attr( $current_key ); ?>">
						<input type="hidden" name="onboarding_direction" value="next">

						<?php self::render_step_errors(); ?>

						<div class="onboarding-step-content">
							<?php self::render_step_content( $current_step, $config ); ?>
						</div>

						<?php self::render_navigation( $config, $current_step, $is_first, $is_last ); ?>
					</form>
				</div>

				<?php self::render_exit_link( $config ); ?>

			</div>
		</div>
		<?php

		/**
		 * Fires after the wizard renders
		 *
		 * @param string $id     Wizard identifier.
		 * @param array  $config Wizard configuration.
		 *
		 * @since 1.0.0
		 */
		do_action( 'arraypress_after_render_onboarding', $id, $config );
		do_action( "arraypress_after_render_onboarding_{$id}", $config );
	}

	/* =========================================================================
	 * COMPONENT RENDERING
	 * ========================================================================= */

	/**
	 * Render the wizard header
	 *
	 * @param array $config Wizard configuration.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function render_header( array $config ): void {
		$logo_url     = $config['logo'] ?? '';
		$header_title = $config['header_title'];

		?>
		<div class="onboarding-header">
			<?php if ( $logo_url ) : ?>
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="onboarding-header__logo">
			<?php endif; ?>
			<?php if ( ! empty( $header_title ) && empty( $logo_url ) ) : ?>
				<h1 class="onboarding-header__title"><?php echo esc_html( $header_title ); ?></h1>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the progress bar
	 *
	 * @param array $config        Wizard configuration.
	 * @param array $visible_keys  Array of visible step keys.
	 * @param int   $current_index Current step index.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function render_progress( array $config, array $visible_keys, int $current_index ): void {
		$total = count( $visible_keys );

		if ( $total <= 1 ) {
			return;
		}

		?>
		<div class="onboarding-progress" role="progressbar"
		     aria-valuenow="<?php echo esc_attr( $current_index + 1 ); ?>"
		     aria-valuemin="1"
		     aria-valuemax="<?php echo esc_attr( $total ); ?>">
			<div class="onboarding-progress__steps">
				<?php foreach ( $visible_keys as $index => $key ) : ?>
					<?php
					$step  = $config['steps'][ $key ];
					$state = 'upcoming';

					if ( $index < $current_index ) {
						$state = 'completed';
					} elseif ( $index === $current_index ) {
						$state = 'current';
					}
					?>
					<div class="onboarding-progress__step onboarding-progress__step--<?php echo esc_attr( $state ); ?>">
						<div class="onboarding-progress__dot">
							<?php if ( $state === 'completed' ) : ?>
								<span class="dashicons dashicons-yes-alt"></span>
							<?php else : ?>
								<span class="onboarding-progress__number"><?php echo esc_html( $index + 1 ); ?></span>
							<?php endif; ?>
						</div>
						<span class="onboarding-progress__label"><?php echo esc_html( $step['title'] ); ?></span>
					</div>
					<?php if ( $index < $total - 1 ) : ?>
						<div class="onboarding-progress__connector onboarding-progress__connector--<?php echo $index < $current_index ? 'completed' : 'upcoming'; ?>"></div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the step header (title + description)
	 *
	 * @param array $step          Current step configuration.
	 * @param int   $current_index Current step index.
	 * @param int   $total_steps   Total visible step count.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function render_step_header( array $step, int $current_index, int $total_steps ): void {
		?>
		<div class="onboarding-step-header">
			<h2 class="onboarding-step-header__title"><?php echo esc_html( $step['title'] ); ?></h2>
			<?php if ( ! empty( $step['description'] ) ) : ?>
				<p class="onboarding-step-header__description"><?php echo esc_html( $step['description'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render validation errors
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function render_step_errors(): void {
		if ( empty( self::$errors ) ) {
			return;
		}

		?>
		<div class="onboarding-errors">
			<?php foreach ( self::$errors as $key => $message ) : ?>
				<div class="onboarding-error" data-field="<?php echo esc_attr( $key ); ?>">
					<span class="dashicons dashicons-warning"></span>
					<?php echo esc_html( $message ); ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render step content based on type
	 *
	 * @param array $step   Current step configuration.
	 * @param array $config Wizard configuration.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function render_step_content( array $step, array $config ): void {
		switch ( $step['type'] ) {
			case 'welcome':
				self::render_welcome_step( $step );
				break;

			case 'fields':
				self::render_fields_step( $step );
				break;

			case 'checklist':
				self::render_checklist_step( $step );
				break;

			case 'complete':
				self::render_complete_step( $step );
				break;

			case 'callback':
				self::render_callback_step( $step );
				break;

			default:
				/**
				 * Fires for custom step types
				 *
				 * @param array $step   Step configuration.
				 * @param array $config Wizard configuration.
				 *
				 * @since 1.0.0
				 */
				do_action( 'arraypress_onboarding_render_step_' . $step['type'], $step, $config );
				break;
		}
	}

	/**
	 * Render navigation buttons
	 *
	 * @param array $config Wizard configuration.
	 * @param array $step   Current step configuration.
	 * @param bool  $is_first Whether this is the first step.
	 * @param bool  $is_last  Whether this is the last step.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function render_navigation( array $config, array $step, bool $is_first, bool $is_last ): void {
		$labels   = $config['labels'];
		$can_skip = ! empty( $step['skippable'] );

		// Welcome and complete steps don't show back button
		$show_back = ! $is_first && ! in_array( $step['type'], [ 'welcome' ], true );

		?>
		<div class="onboarding-navigation">
			<div class="onboarding-navigation__left">
				<?php if ( $show_back ) : ?>
					<button type="submit" name="onboarding_direction" value="previous"
					        class="button onboarding-btn onboarding-btn--back">
						<span class="dashicons dashicons-arrow-left-alt2"></span>
						<?php echo esc_html( $labels['previous'] ); ?>
					</button>
				<?php endif; ?>
			</div>

			<div class="onboarding-navigation__right">
				<?php if ( $can_skip && ! $is_last ) : ?>
					<button type="submit" name="onboarding_direction" value="skip"
					        class="button onboarding-btn onboarding-btn--skip">
						<?php echo esc_html( $step['skip_label'] ?? $labels['skip'] ); ?>
					</button>
				<?php endif; ?>

				<?php if ( $step['type'] !== 'complete' ) : ?>
					<button type="submit" name="onboarding_direction" value="next"
					        class="button button-primary onboarding-btn onboarding-btn--next">
						<?php echo esc_html( $is_last ? $labels['finish'] : $labels['next'] ); ?>
						<?php if ( ! $is_last ) : ?>
							<span class="dashicons dashicons-arrow-right-alt2"></span>
						<?php endif; ?>
					</button>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the exit link below the wizard
	 *
	 * @param array $config Wizard configuration.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function render_exit_link( array $config ): void {
		?>
		<div class="onboarding-exit">
			<a href="<?php echo esc_url( admin_url() ); ?>" class="onboarding-exit__link">
				<?php echo esc_html( $config['labels']['exit'] ); ?>
			</a>
		</div>
		<?php
	}

	/* =========================================================================
	 * STEP TYPE RENDERERS
	 * ========================================================================= */

	/**
	 * Render a welcome step
	 *
	 * @param array $step Step configuration.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function render_welcome_step( array $step ): void {
		?>
		<div class="onboarding-welcome">
			<?php if ( ! empty( $step['image'] ) ) : ?>
				<div class="onboarding-welcome__image">
					<img src="<?php echo esc_url( $step['image'] ); ?>" alt="">
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $step['features'] ) ) : ?>
				<div class="onboarding-welcome__features">
					<?php foreach ( $step['features'] as $feature ) : ?>
						<div class="onboarding-welcome__feature">
							<?php if ( ! empty( $feature['icon'] ) ) : ?>
								<span class="dashicons <?php echo esc_attr( $feature['icon'] ); ?>"></span>
							<?php else : ?>
								<span class="dashicons dashicons-yes"></span>
							<?php endif; ?>
							<div class="onboarding-welcome__feature-text">
								<?php if ( ! empty( $feature['title'] ) ) : ?>
									<strong><?php echo esc_html( $feature['title'] ); ?></strong>
								<?php endif; ?>
								<?php if ( ! empty( $feature['description'] ) ) : ?>
									<span><?php echo esc_html( $feature['description'] ); ?></span>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a fields step
	 *
	 * @param array $step Step configuration.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function render_fields_step( array $step ): void {
		if ( empty( $step['fields'] ) ) {
			return;
		}

		?>
		<div class="onboarding-fields">
			<?php foreach ( $step['fields'] as $key => $field ) : ?>
				<?php self::render_field( $key, $field ); ?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render a single field
	 *
	 * @param string $key   Field key.
	 * @param array  $field Field configuration.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function render_field( string $key, array $field ): void {
		$type      = $field['type'] ?? 'text';
		$label     = $field['label'] ?? '';
		$help      = $field['help'] ?? '';
		$option    = $field['option'] ?? '';
		$default   = $field['default'] ?? '';
		$has_error = isset( self::$errors[ $key ] );

		// Get current value from POST (re-display) or from saved option
		$value = $_POST[ $key ] ?? '';

		if ( empty( $value ) && ! empty( $option ) ) {
			$value = get_option( $option, $default );
		}

		if ( empty( $value ) ) {
			$value = $default;
		}

		// Handle {admin_email} placeholder
		if ( $value === '{admin_email}' ) {
			$value = get_option( 'admin_email' );
		}

		$field_classes = [ 'onboarding-field' ];
		if ( $has_error ) {
			$field_classes[] = 'onboarding-field--error';
		}

		?>
		<div class="<?php echo esc_attr( implode( ' ', $field_classes ) ); ?>">
			<?php if ( ! empty( $label ) ) : ?>
				<label for="field-<?php echo esc_attr( $key ); ?>" class="onboarding-field__label">
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endif; ?>

			<div class="onboarding-field__input">
				<?php
				switch ( $type ) {
					case 'select':
						self::render_select_field( $key, $field, $value );
						break;

					case 'radio':
						self::render_radio_field( $key, $field, $value );
						break;

					case 'toggle':
						self::render_toggle_field( $key, $field, $value );
						break;

					case 'textarea':
						self::render_textarea_field( $key, $field, $value );
						break;

					case 'email':
					case 'url':
					case 'number':
					case 'text':
					default:
						self::render_text_field( $key, $field, $value, $type );
						break;
				}
				?>
			</div>

			<?php if ( ! empty( $help ) ) : ?>
				<p class="onboarding-field__help"><?php echo esc_html( $help ); ?></p>
			<?php endif; ?>

			<?php if ( $has_error ) : ?>
				<p class="onboarding-field__error"><?php echo esc_html( self::$errors[ $key ] ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a text input field
	 *
	 * @param string $key   Field key.
	 * @param array  $field Field configuration.
	 * @param string $value Current value.
	 * @param string $type  Input type (text, email, url, number).
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function render_text_field( string $key, array $field, string $value, string $type = 'text' ): void {
		$placeholder = $field['placeholder'] ?? '';

		printf(
			'<input type="%s" id="field-%s" name="%s" value="%s" placeholder="%s" class="onboarding-input">',
			esc_attr( $type ),
			esc_attr( $key ),
			esc_attr( $key ),
			esc_attr( $value ),
			esc_attr( $placeholder )
		);
	}

	/**
	 * Render a select field
	 *
	 * @param string $key   Field key.
	 * @param array  $field Field configuration.
	 * @param string $value Current value.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function render_select_field( string $key, array $field, string $value ): void {
		$options     = self::resolve_options( $field['options'] ?? [] );
		$placeholder = $field['placeholder'] ?? '';

		?>
		<select id="field-<?php echo esc_attr( $key ); ?>"
		        name="<?php echo esc_attr( $key ); ?>"
		        class="onboarding-select">
			<?php if ( ! empty( $placeholder ) ) : ?>
				<option value=""><?php echo esc_html( $placeholder ); ?></option>
			<?php endif; ?>
			<?php foreach ( $options as $opt_value => $opt_label ) : ?>
				<option value="<?php echo esc_attr( $opt_value ); ?>"
					<?php selected( $value, $opt_value ); ?>>
					<?php echo esc_html( $opt_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render a radio field
	 *
	 * @param string $key   Field key.
	 * @param array  $field Field configuration.
	 * @param string $value Current value.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function render_radio_field( string $key, array $field, string $value ): void {
		$options = self::resolve_options( $field['options'] ?? [] );

		?>
		<div class="onboarding-radio-group">
			<?php foreach ( $options as $opt_value => $opt_label ) : ?>
				<label class="onboarding-radio">
					<input type="radio"
					       name="<?php echo esc_attr( $key ); ?>"
					       value="<?php echo esc_attr( $opt_value ); ?>"
						<?php checked( $value, $opt_value ); ?>>
					<span class="onboarding-radio__label"><?php echo esc_html( $opt_label ); ?></span>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render a toggle field
	 *
	 * @param string $key   Field key.
	 * @param array  $field Field configuration.
	 * @param mixed  $value Current value.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function render_toggle_field( string $key, array $field, $value ): void {
		$checked     = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
		$description = $field['description'] ?? '';

		?>
		<label class="onboarding-toggle">
			<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="0">
			<input type="checkbox"
			       name="<?php echo esc_attr( $key ); ?>"
			       value="1"
				<?php checked( $checked ); ?>
				   class="onboarding-toggle__input">
			<span class="onboarding-toggle__slider"></span>
			<?php if ( ! empty( $description ) ) : ?>
				<span class="onboarding-toggle__text"><?php echo esc_html( $description ); ?></span>
			<?php endif; ?>
		</label>
		<?php
	}

	/**
	 * Render a textarea field
	 *
	 * @param string $key   Field key.
	 * @param array  $field Field configuration.
	 * @param string $value Current value.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function render_textarea_field( string $key, array $field, string $value ): void {
		$placeholder = $field['placeholder'] ?? '';
		$rows        = $field['rows'] ?? 4;

		printf(
			'<textarea id="field-%s" name="%s" placeholder="%s" rows="%d" class="onboarding-textarea">%s</textarea>',
			esc_attr( $key ),
			esc_attr( $key ),
			esc_attr( $placeholder ),
			absint( $rows ),
			esc_textarea( $value )
		);
	}

	/**
	 * Render a checklist step
	 *
	 * @param array $step Step configuration.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function render_checklist_step( array $step ): void {
		if ( empty( $step['items'] ) ) {
			return;
		}

		?>
		<div class="onboarding-checklist">
			<?php foreach ( $step['items'] as $index => $item ) : ?>
				<?php
				$option  = $item['option'] ?? '';
				$default = $item['default'] ?? false;
				$checked = false;

				// Check POST first, then saved option, then default
				if ( isset( $_POST['checklist'][ $index ] ) ) {
					$checked = true;
				} elseif ( ! empty( $option ) && get_option( $option ) !== false ) {
					$checked = filter_var( get_option( $option ), FILTER_VALIDATE_BOOLEAN );
				} else {
					$checked = (bool) $default;
				}
				?>
				<label class="onboarding-checklist__item">
					<input type="checkbox"
					       name="checklist[<?php echo esc_attr( $index ); ?>]"
					       value="1"
						<?php checked( $checked ); ?>
						   class="onboarding-checklist__input">
					<span class="onboarding-checklist__toggle"></span>
					<div class="onboarding-checklist__text">
						<span class="onboarding-checklist__label"><?php echo esc_html( $item['label'] ); ?></span>
						<?php if ( ! empty( $item['description'] ) ) : ?>
							<span class="onboarding-checklist__description"><?php echo esc_html( $item['description'] ); ?></span>
						<?php endif; ?>
					</div>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render a complete step
	 *
	 * @param array $step Step configuration.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function render_complete_step( array $step ): void {
		?>
		<div class="onboarding-complete">
			<div class="onboarding-complete__icon">
				<span class="dashicons dashicons-yes-alt"></span>
			</div>

			<?php if ( ! empty( $step['links'] ) ) : ?>
				<div class="onboarding-complete__links">
					<?php foreach ( $step['links'] as $link ) : ?>
						<a href="<?php echo esc_url( $link['url'] ); ?>"
						   class="button <?php echo empty( $link['external'] ) ? 'button-primary' : ''; ?> onboarding-complete__link"
							<?php if ( ! empty( $link['external'] ) ) : ?>
								target="_blank" rel="noopener noreferrer"
							<?php endif; ?>>
							<?php echo esc_html( $link['label'] ); ?>
							<?php if ( ! empty( $link['external'] ) ) : ?>
								<span class="dashicons dashicons-external"></span>
							<?php endif; ?>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a callback step
	 *
	 * @param array $step Step configuration.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function render_callback_step( array $step ): void {
		if ( ! empty( $step['render'] ) && is_callable( $step['render'] ) ) {
			call_user_func( $step['render'], $step );
		}
	}

	/* =========================================================================
	 * STEP NAVIGATION
	 * ========================================================================= */

	/**
	 * Get visible step keys (filtered by show_if)
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
	 * Get the adjacent step key (next or previous)
	 *
	 * @param array  $config    Wizard configuration.
	 * @param string $step_key  Current step key.
	 * @param string $direction 'next' or 'previous'.
	 *
	 * @return string|null Next/previous step key or null.
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
	 * OPTION PRESETS
	 * ========================================================================= */

	/**
	 * Resolve options — handles presets and raw arrays
	 *
	 * @param string|array $options Options definition (preset string or array).
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
				return self::get_currency_options();

			case 'countries':
				return self::get_country_options();

			case 'us_states':
				return self::get_us_state_options();

			case 'timezones':
				return self::get_timezone_options();

			default:
				/**
				 * Filter to register custom option presets
				 *
				 * @param array  $options Empty array.
				 * @param string $preset  Preset name.
				 *
				 * @return array key => label pairs.
				 * @since 1.0.0
				 */
				return apply_filters( 'arraypress_onboarding_preset_' . $options, [] );
		}
	}

	/**
	 * Get currency options
	 *
	 * @return array
	 * @since 1.0.0
	 */
	private static function get_currency_options(): array {
		return [
			'USD' => __( 'US Dollar ($)', 'arraypress' ),
			'EUR' => __( 'Euro (€)', 'arraypress' ),
			'GBP' => __( 'British Pound (£)', 'arraypress' ),
			'CAD' => __( 'Canadian Dollar (CA$)', 'arraypress' ),
			'AUD' => __( 'Australian Dollar (A$)', 'arraypress' ),
			'JPY' => __( 'Japanese Yen (¥)', 'arraypress' ),
			'CHF' => __( 'Swiss Franc (CHF)', 'arraypress' ),
			'CNY' => __( 'Chinese Yuan (¥)', 'arraypress' ),
			'SEK' => __( 'Swedish Krona (kr)', 'arraypress' ),
			'NZD' => __( 'New Zealand Dollar (NZ$)', 'arraypress' ),
			'MXN' => __( 'Mexican Peso (MX$)', 'arraypress' ),
			'SGD' => __( 'Singapore Dollar (S$)', 'arraypress' ),
			'HKD' => __( 'Hong Kong Dollar (HK$)', 'arraypress' ),
			'NOK' => __( 'Norwegian Krone (kr)', 'arraypress' ),
			'KRW' => __( 'South Korean Won (₩)', 'arraypress' ),
			'TRY' => __( 'Turkish Lira (₺)', 'arraypress' ),
			'INR' => __( 'Indian Rupee (₹)', 'arraypress' ),
			'BRL' => __( 'Brazilian Real (R$)', 'arraypress' ),
			'ZAR' => __( 'South African Rand (R)', 'arraypress' ),
			'THB' => __( 'Thai Baht (฿)', 'arraypress' ),
			'PLN' => __( 'Polish Zloty (zł)', 'arraypress' ),
			'DKK' => __( 'Danish Krone (kr)', 'arraypress' ),
			'TWD' => __( 'New Taiwan Dollar (NT$)', 'arraypress' ),
			'CZK' => __( 'Czech Koruna (Kč)', 'arraypress' ),
			'ILS' => __( 'Israeli Shekel (₪)', 'arraypress' ),
			'PHP' => __( 'Philippine Peso (₱)', 'arraypress' ),
			'AED' => __( 'UAE Dirham (AED)', 'arraypress' ),
			'CLP' => __( 'Chilean Peso (CLP)', 'arraypress' ),
			'SAR' => __( 'Saudi Riyal (SAR)', 'arraypress' ),
			'MYR' => __( 'Malaysian Ringgit (RM)', 'arraypress' ),
		];
	}

	/**
	 * Get country options
	 *
	 * @return array
	 * @since 1.0.0
	 */
	private static function get_country_options(): array {
		return [
			'US' => __( 'United States', 'arraypress' ),
			'GB' => __( 'United Kingdom', 'arraypress' ),
			'CA' => __( 'Canada', 'arraypress' ),
			'AU' => __( 'Australia', 'arraypress' ),
			'DE' => __( 'Germany', 'arraypress' ),
			'FR' => __( 'France', 'arraypress' ),
			'IT' => __( 'Italy', 'arraypress' ),
			'ES' => __( 'Spain', 'arraypress' ),
			'NL' => __( 'Netherlands', 'arraypress' ),
			'BE' => __( 'Belgium', 'arraypress' ),
			'AT' => __( 'Austria', 'arraypress' ),
			'CH' => __( 'Switzerland', 'arraypress' ),
			'SE' => __( 'Sweden', 'arraypress' ),
			'NO' => __( 'Norway', 'arraypress' ),
			'DK' => __( 'Denmark', 'arraypress' ),
			'FI' => __( 'Finland', 'arraypress' ),
			'IE' => __( 'Ireland', 'arraypress' ),
			'PT' => __( 'Portugal', 'arraypress' ),
			'PL' => __( 'Poland', 'arraypress' ),
			'CZ' => __( 'Czech Republic', 'arraypress' ),
			'JP' => __( 'Japan', 'arraypress' ),
			'KR' => __( 'South Korea', 'arraypress' ),
			'CN' => __( 'China', 'arraypress' ),
			'IN' => __( 'India', 'arraypress' ),
			'BR' => __( 'Brazil', 'arraypress' ),
			'MX' => __( 'Mexico', 'arraypress' ),
			'AR' => __( 'Argentina', 'arraypress' ),
			'CL' => __( 'Chile', 'arraypress' ),
			'CO' => __( 'Colombia', 'arraypress' ),
			'ZA' => __( 'South Africa', 'arraypress' ),
			'NG' => __( 'Nigeria', 'arraypress' ),
			'EG' => __( 'Egypt', 'arraypress' ),
			'KE' => __( 'Kenya', 'arraypress' ),
			'IL' => __( 'Israel', 'arraypress' ),
			'AE' => __( 'United Arab Emirates', 'arraypress' ),
			'SA' => __( 'Saudi Arabia', 'arraypress' ),
			'TR' => __( 'Turkey', 'arraypress' ),
			'TH' => __( 'Thailand', 'arraypress' ),
			'SG' => __( 'Singapore', 'arraypress' ),
			'MY' => __( 'Malaysia', 'arraypress' ),
			'PH' => __( 'Philippines', 'arraypress' ),
			'ID' => __( 'Indonesia', 'arraypress' ),
			'VN' => __( 'Vietnam', 'arraypress' ),
			'NZ' => __( 'New Zealand', 'arraypress' ),
			'RU' => __( 'Russia', 'arraypress' ),
			'UA' => __( 'Ukraine', 'arraypress' ),
			'RO' => __( 'Romania', 'arraypress' ),
			'HU' => __( 'Hungary', 'arraypress' ),
			'GR' => __( 'Greece', 'arraypress' ),
			'HK' => __( 'Hong Kong', 'arraypress' ),
			'TW' => __( 'Taiwan', 'arraypress' ),
		];
	}

	/**
	 * Get US state options
	 *
	 * @return array
	 * @since 1.0.0
	 */
	private static function get_us_state_options(): array {
		return [
			'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
			'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
			'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
			'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
			'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
			'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
			'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
			'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
			'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
			'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
			'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
			'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
			'WI' => 'Wisconsin', 'WY' => 'Wyoming', 'DC' => 'District of Columbia',
		];
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
				$label             = str_replace( [ '/', '_' ], [ ' — ', ' ' ], $parts[1] );
				$timezones[ $zone ] = $parts[0] . ' — ' . $label;
			}
		}

		return $timezones;
	}

	/* =========================================================================
	 * UTILITY METHODS
	 * ========================================================================= */

	/**
	 * Normalize step definitions
	 *
	 * Ensures every step has all required keys with sensible defaults.
	 *
	 * @param array $steps Raw step definitions.
	 *
	 * @return array Normalized step definitions.
	 * @since 1.0.0
	 */
	private static function normalize_steps( array $steps ): array {
		$defaults = [
			'title'       => '',
			'description' => '',
			'type'        => 'fields',
			'show_if'     => null,
			'skippable'   => false,
			'skip_label'  => '',
			'fields'      => [],
			'items'       => [],
			'features'    => [],
			'links'       => [],
			'image'       => '',
			'redirect'    => '',
			'render'      => null,
			'validate'    => null,
			'save'        => null,
		];

		foreach ( $steps as $key => &$step ) {
			$step = wp_parse_args( $step, $defaults );
		}
		unset( $step );

		return $steps;
	}

	/**
	 * Build inline CSS style attribute from color overrides
	 *
	 * Maps config color keys to CSS custom properties. Only non-empty
	 * values are included, so anything not set falls back to the
	 * :root defaults in the stylesheet.
	 *
	 * @param array $colors Color overrides from config.
	 *
	 * @return string The style attribute string (with leading space) or empty.
	 * @since 1.0.0
	 */
	private static function build_color_style( array $colors ): string {
		if ( empty( $colors ) ) {
			return '';
		}

		$map = [
			'accent'       => '--ob-accent',
			'accent_hover' => '--ob-accent-hover',
			'accent_light' => '--ob-accent-light',
			'success'      => '--ob-success',
			'error'        => '--ob-error',
			'text_primary' => '--ob-text-primary',
			'text_secondary' => '--ob-text-secondary',
			'text_muted'   => '--ob-text-muted',
			'border'       => '--ob-border',
			'border_light' => '--ob-border-light',
			'bg_white'     => '--ob-bg-white',
			'bg_subtle'    => '--ob-bg-subtle',
			'bg_page'      => '--ob-bg-page',
			'radius'       => '--ob-radius',
			'radius_lg'    => '--ob-radius-lg',
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
	 * @return bool True if removed.
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
