<?php
/**
 * Processes Steps Trait
 *
 * Handles form submission processing, validation, saving,
 * and field sanitization for onboarding wizard steps.
 *
 * @package     ArrayPress\RegisterOnboarding
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterOnboarding\Traits;

trait ProcessesSteps {

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

		// Sync steps skip validation/saving — just advance
		if ( $step['type'] === 'sync' ) {
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

			// Step-level redirect takes priority, then wizard-level completed_redirect
			if ( ! empty( $step['redirect'] ) ) {
				wp_safe_redirect( $step['redirect'] );
				exit;
			}

			if ( ! empty( $config['completed_redirect'] ) ) {
				wp_safe_redirect( $config['completed_redirect'] );
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

}
