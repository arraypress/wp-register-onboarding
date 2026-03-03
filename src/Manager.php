<?php
/**
 * Onboarding Wizard Registration Manager
 *
 * Central manager class for registering and rendering WordPress admin
 * onboarding wizards. Provides a configuration-driven approach to creating
 * multi-step setup flows with support for:
 *
 * - Automatic menu page registration (hidden from nav)
 * - Step-based navigation with progress indicator
 * - Built-in step types: welcome, fields, checklist, complete, callback
 * - Field rendering with automatic saving via wp_options or custom callbacks
 * - Per-field and per-step validation with error display
 * - Select2 for searchable selects (countries, currencies, etc.)
 * - Option presets via arraypress/wp-currencies and arraypress/wp-countries
 * - Field dependencies for conditional visibility and attribute swapping
 * - Multiple dependency rules per field (show + swap simultaneously)
 * - Conditional step visibility via show_if callbacks
 * - Skippable steps with optional skip labels
 * - Activation redirect on first run
 * - Color theming via CSS custom properties
 *
 * @package     ArrayPress\RegisterOnboarding
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterOnboarding;

use ArrayPress\Currencies\Currency;
use ArrayPress\Countries\Countries;

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
                'page_title'       => '',
                'menu_title'       => '',
                'menu_slug'        => '',
                'parent_slug'      => '',
                'capability'       => 'manage_options',

            // Header
                'logo'             => '',
                'header_title'     => '',

            // Behavior
                'redirect'         => false,
                'completed_option' => '',

            // Custom value callbacks (flat — replaces default get_option/update_option)
                'get_callback'     => null,
                'update_callback'  => null,

            // Steps
                'steps'            => [],

            // Display
                'body_class'       => '',

            // Colors
                'colors'           => [],

            // Labels
                'labels'           => [],
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
     * Enqueue assets for the current wizard page
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
                self::do_enqueue_assets( $id, $config );
                break;
            }
        }
    }

    /**
     * Enqueue CSS, JS, Select2, and localize dependency data
     *
     * @param string $id     Wizard identifier.
     * @param array  $config Wizard configuration.
     *
     * @return void
     * @since 1.0.0
     */
    private static function do_enqueue_assets( string $id, array $config ): void {
        if ( self::$assets_enqueued ) {
            return;
        }
        self::$assets_enqueued = true;

        // Select2
        wp_enqueue_style(
                'select2',
                'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
                [],
                '4.1.0'
        );

        wp_enqueue_script(
                'select2',
                'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
                [ 'jquery' ],
                '4.1.0',
                true
        );

        // Wizard styles
        wp_enqueue_composer_style(
                'onboarding-wizard-styles',
                __FILE__,
                'css/onboarding-wizard.css'
        );

        // Wizard scripts
        wp_enqueue_composer_script(
                'onboarding-wizard-scripts',
                __FILE__,
                'js/onboarding-wizard.js',
                [ 'jquery', 'select2' ],
                true
        );

        // Build depends map for current step fields
        $depends_map = self::build_depends_map( $id, $config );

        // Check if current step has confetti enabled
        $visible_keys = self::get_visible_step_keys( $config );
        $current_key  = sanitize_key( $_GET['step'] ?? '' );

        if ( empty( $current_key ) || ! in_array( $current_key, $visible_keys, true ) ) {
            $current_key = $visible_keys[0] ?? '';
        }

        $current_step = $config['steps'][ $current_key ] ?? [];
        $confetti     = ! empty( $current_step['confetti'] );

        wp_localize_script( 'onboarding-wizard-scripts', 'onboardingWizard', [
                'depends'  => $depends_map,
                'confetti' => $confetti,
        ] );
    }

    /**
     * Build dependency map for the current step's fields
     *
     * Produces a JSON-serializable structure the JS uses to show/hide
     * fields and swap attributes based on other fields' values.
     *
     * Each field key maps to an array of dependency rule objects. A single
     * 'depends' object (with a 'field' key) is normalized to a one-element
     * array for consistency, so the JS always processes an array of rules.
     *
     * @param string $id     Wizard identifier.
     * @param array  $config Wizard configuration.
     *
     * @return array Map of field_key => array of dependency rule objects.
     * @since 1.0.0
     */
    private static function build_depends_map( string $id, array $config ): array {
        $current_key = sanitize_key( $_GET['step'] ?? '' );
        $visible     = self::get_visible_step_keys( $config );

        if ( empty( $current_key ) || ! in_array( $current_key, $visible, true ) ) {
            $current_key = $visible[0] ?? '';
        }

        if ( empty( $current_key ) || empty( $config['steps'][ $current_key ] ) ) {
            return [];
        }

        $step = $config['steps'][ $current_key ];

        if ( $step['type'] !== 'fields' || empty( $step['fields'] ) ) {
            return [];
        }

        $map = [];

        foreach ( $step['fields'] as $key => $field ) {
            if ( empty( $field['depends'] ) ) {
                continue;
            }

            $deps = $field['depends'];

            // Normalize: single depends object (has 'field' key) vs array of objects
            if ( isset( $deps['field'] ) ) {
                $deps = [ $deps ];
            }

            $entries = [];

            foreach ( $deps as $dep ) {
                $entry = [
                        'field'    => $dep['field'] ?? '',
                        'operator' => $dep['operator'] ?? '==',
                        'value'    => $dep['value'] ?? '',
                        'action'   => $dep['action'] ?? 'show',
                ];

                if ( ! empty( $dep['attrs'] ) ) {
                    $entry['attrs'] = $dep['attrs'];
                }

                if ( ! empty( $dep['attrs_alt'] ) ) {
                    $entry['attrs_alt'] = $dep['attrs_alt'];
                }

                $entries[] = $entry;
            }

            $map[ $key ] = $entries;
        }

        return $map;
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
     * VALUE CALLBACKS
     * ========================================================================= */

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

    /* =========================================================================
     * STEP SUBMISSION
     * ========================================================================= */

    /**
     * Process step form submissions
     *
     * Handles validation, saving, and navigation for the current step.
     * Guards against submissions for steps that aren't currently visible.
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

        $config               = self::$wizards[ $wizard_id ];
        self::$current_wizard = $wizard_id;

        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'onboarding_step_' . $wizard_id . '_' . $step_key ) ) {
            wp_die( __( 'Security check failed.', 'arraypress' ) );
        }

        if ( ! current_user_can( $config['capability'] ) ) {
            wp_die( __( 'You do not have permission to access this page.', 'arraypress' ) );
        }

        // Guard: only process steps that are currently visible
        $visible_keys = self::get_visible_step_keys( $config );
        if ( ! in_array( $step_key, $visible_keys, true ) ) {
            wp_safe_redirect( self::get_step_url( $config, $visible_keys[0] ?? '' ) );
            exit;
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

        // Validate
        $errors = self::validate_step( $step, $_POST );

        if ( ! empty( $errors ) ) {
            self::$errors = $errors;

            return;
        }

        // Save
        self::save_step( $config, $step, $_POST );

        // Mark as completed if this is the last visible step
        $last_key = end( $visible_keys );

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

        wp_safe_redirect( admin_url() );
        exit;
    }

    /* =========================================================================
     * VALIDATION
     * ========================================================================= */

    /**
     * Validate a step's submitted data
     *
     * Runs step-level validation callback first, then per-field validators.
     *
     * @param array $step Step configuration.
     * @param array $data Submitted POST data.
     *
     * @return array Array of field_key => error message pairs.
     * @since 1.0.0
     */
    private static function validate_step( array $step, array $data ): array {
        $errors = [];

        // Step-level validation callback
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
     * Uses the step's custom save callback if provided, otherwise
     * auto-saves fields and checklist items via the value callbacks.
     *
     * @param array $config Wizard configuration.
     * @param array $step   Step configuration.
     * @param array $data   Submitted POST data.
     *
     * @return void
     * @since 1.0.0
     */
    private static function save_step( array $config, array $step, array $data ): void {
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

                self::update_value( $config, $field['option'], $value );
            }
        }

        // Auto-save checklist type
        if ( $step['type'] === 'checklist' && ! empty( $step['items'] ) ) {
            foreach ( $step['items'] as $index => $item ) {
                if ( empty( $item['option'] ) ) {
                    continue;
                }

                $value = isset( $data['checklist'][ $index ] );

                self::update_value( $config, $item['option'], $value );
            }
        }
    }

    /**
     * Sanitize a field value based on its type
     *
     * @param array $field Field configuration.
     * @param mixed $value Raw value.
     *
     * @return mixed Sanitized value.
     * @since 1.0.0
     */
    private static function sanitize_field_value( array $field, $value ) {
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

        $config               = self::$wizards[ $id ];
        self::$current_wizard = $id;
        $visible_keys         = self::get_visible_step_keys( $config );

        if ( empty( $visible_keys ) ) {
            return;
        }

        $current_key = sanitize_key( $_GET['step'] ?? '' );

        if ( empty( $current_key ) || ! in_array( $current_key, $visible_keys, true ) ) {
            $current_key = $visible_keys[0];
        }

        $current_step  = $config['steps'][ $current_key ];
        $current_index = array_search( $current_key, $visible_keys, true );
        $total_steps   = count( $visible_keys );
        $is_first      = $current_index === 0;
        $is_last       = $current_index === $total_steps - 1;

        $style_attr = self::build_color_style( $config['colors'] );

        do_action( 'arraypress_before_render_onboarding', $id, $config );
        do_action( "arraypress_before_render_onboarding_{$id}", $config );

        ?>
        <div class="onboarding-wrap"<?php echo $style_attr; ?>>
            <div class="onboarding-container">

                <?php self::render_header( $config ); ?>
                <?php self::render_progress( $config, $visible_keys, $current_index ); ?>

                <div class="onboarding-step-wrap">
                    <?php self::render_step_header( $current_step ); ?>

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

        do_action( 'arraypress_after_render_onboarding', $id, $config );
        do_action( "arraypress_after_render_onboarding_{$id}", $config );
    }

    /* =========================================================================
     * COMPONENT RENDERING
     * ========================================================================= */

    /**
     * Render the wizard header (logo or title)
     *
     * @param array $config Wizard configuration.
     *
     * @return void
     * @since 1.0.0
     */
    private static function render_header( array $config ): void {
        ?>
        <div class="onboarding-header">
            <?php if ( ! empty( $config['logo'] ) ) : ?>
                <img src="<?php echo esc_url( $config['logo'] ); ?>" alt="" class="onboarding-header__logo">
            <?php elseif ( ! empty( $config['header_title'] ) ) : ?>
                <h1 class="onboarding-header__title"><?php echo esc_html( $config['header_title'] ); ?></h1>
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
     * @param array $step Current step configuration.
     *
     * @return void
     * @since 1.0.0
     */
    private static function render_step_header( array $step ): void {
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
                self::render_fields_step( $step, $config );
                break;

            case 'checklist':
                self::render_checklist_step( $step, $config );
                break;

            case 'complete':
                self::render_complete_step( $step );
                break;

            case 'callback':
                self::render_callback_step( $step );
                break;

            default:
                do_action( 'arraypress_onboarding_render_step_' . $step['type'], $step, $config );
                break;
        }
    }

    /**
     * Render navigation buttons (back, skip, next/finish)
     *
     * @param array $config   Wizard configuration.
     * @param array $step     Current step configuration.
     * @param bool  $is_first Whether this is the first step.
     * @param bool  $is_last  Whether this is the last step.
     *
     * @return void
     * @since 1.0.0
     */
    private static function render_navigation( array $config, array $step, bool $is_first, bool $is_last ): void {
        $labels    = $config['labels'];
        $can_skip  = ! empty( $step['skippable'] );
        $show_back = ! $is_first && $step['type'] !== 'welcome';

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
     * Render the exit link below the wizard card
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
     * @param array $step   Step configuration.
     * @param array $config Wizard configuration.
     *
     * @return void
     * @since 1.0.0
     */
    private static function render_fields_step( array $step, array $config ): void {
        if ( empty( $step['fields'] ) ) {
            return;
        }

        ?>
        <div class="onboarding-fields">
            <?php foreach ( $step['fields'] as $key => $field ) : ?>
                <?php self::render_field( $key, $field, $config ); ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Render a single field
     *
     * @param string $key    Field key.
     * @param array  $field  Field configuration.
     * @param array  $config Wizard configuration.
     *
     * @return void
     * @since 1.0.0
     */
    private static function render_field( string $key, array $field, array $config ): void {
        $type      = $field['type'] ?? 'text';
        $label     = $field['label'] ?? '';
        $help      = $field['help'] ?? '';
        $option    = $field['option'] ?? '';
        $default   = $field['default'] ?? '';
        $has_error = isset( self::$errors[ $key ] );

        // Get current value: POST > stored > default
        $value = $_POST[ $key ] ?? '';

        if ( empty( $value ) && ! empty( $option ) ) {
            $value = self::get_value( $config, $option, $default );
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

        // Data attributes for depends
        $data_attrs = '';
        if ( ! empty( $field['depends'] ) ) {
            $data_attrs = ' data-depends="' . esc_attr( $key ) . '"';
        }

        ?>
        <div class="<?php echo esc_attr( implode( ' ', $field_classes ) ); ?>"<?php echo $data_attrs; ?>>
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
                <p class="onboarding-field__help" data-default-help="<?php echo esc_attr( $help ); ?>">
                    <?php echo esc_html( $help ); ?>
                </p>
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
                '<input type="%s" id="field-%s" name="%s" value="%s" placeholder="%s" class="onboarding-input" data-default-placeholder="%s">',
                esc_attr( $type ),
                esc_attr( $key ),
                esc_attr( $key ),
                esc_attr( $value ),
                esc_attr( $placeholder ),
                esc_attr( $placeholder )
        );
    }

    /**
     * Render a select field (with optional Select2)
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
        $searchable  = $field['searchable'] ?? ( count( $options ) > 10 );

        $classes = 'onboarding-select';
        if ( $searchable ) {
            $classes .= ' onboarding-select--searchable';
        }

        ?>
        <select id="field-<?php echo esc_attr( $key ); ?>"
                name="<?php echo esc_attr( $key ); ?>"
                class="<?php echo esc_attr( $classes ); ?>"
                <?php if ( $searchable && ! empty( $placeholder ) ) : ?>
                    data-placeholder="<?php echo esc_attr( $placeholder ); ?>"
                <?php endif; ?>>
            <?php if ( ! empty( $placeholder ) ) : ?>
                <option value=""><?php echo esc_html( $placeholder ); ?></option>
            <?php endif; ?>
            <?php foreach ( $options as $opt_value => $opt_label ) : ?>
                <option value="<?php echo esc_attr( $opt_value ); ?>"
                        <?php selected( $value, (string) $opt_value ); ?>>
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
        <div class="onboarding-radio-group" id="field-<?php echo esc_attr( $key ); ?>">
            <?php foreach ( $options as $opt_value => $opt_label ) : ?>
                <label class="onboarding-radio">
                    <input type="radio"
                           name="<?php echo esc_attr( $key ); ?>"
                           value="<?php echo esc_attr( $opt_value ); ?>"
                            <?php checked( $value, (string) $opt_value ); ?>>
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
                   id="field-<?php echo esc_attr( $key ); ?>"
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
                '<textarea id="field-%s" name="%s" placeholder="%s" rows="%d" class="onboarding-textarea" data-default-placeholder="%s">%s</textarea>',
                esc_attr( $key ),
                esc_attr( $key ),
                esc_attr( $placeholder ),
                absint( $rows ),
                esc_attr( $placeholder ),
                esc_textarea( $value )
        );
    }

    /**
     * Render a checklist step
     *
     * @param array $step   Step configuration.
     * @param array $config Wizard configuration.
     *
     * @return void
     * @since 1.0.0
     */
    private static function render_checklist_step( array $step, array $config ): void {
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

                if ( isset( $_POST['checklist'][ $index ] ) ) {
                    $checked = true;
                } elseif ( ! empty( $option ) ) {
                    $stored = self::get_value( $config, $option, null );
                    if ( $stored !== null ) {
                        $checked = filter_var( $stored, FILTER_VALIDATE_BOOLEAN );
                    } else {
                        $checked = (bool) $default;
                    }
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
     * OPTION PRESETS
     * ========================================================================= */

    /**
     * Resolve options — handles presets and raw arrays
     *
     * Supports string presets ('currencies', 'countries', 'timezones')
     * and custom presets via the arraypress_onboarding_preset_{name} filter.
     *
     * @param string|array $options Options definition.
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
                return Currency::get_options();

            case 'countries':
                return Countries::all();

            case 'timezones':
                return self::get_timezone_options();

            default:
                /**
                 * Filter to register custom option presets
                 *
                 * @param array $options Empty array.
                 *
                 * @return array key => label pairs.
                 * @since 1.0.0
                 */
                return apply_filters( 'arraypress_onboarding_preset_' . $options, [] );
        }
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
                $label              = str_replace( [ '/', '_' ], [ ' — ', ' ' ], $parts[1] );
                $timezones[ $zone ] = $parts[0] . ' — ' . $label;
            }
        }

        return $timezones;
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
                'title'       => '',
                'description' => '',
                'type'        => 'fields',
                'show_if'     => null,
                'skippable'   => false,
                'skip_label'  => '',
                'confetti'    => false,
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