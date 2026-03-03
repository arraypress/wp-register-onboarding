<?php
/**
 * Enqueues Assets Trait
 *
 * Handles CSS, JS, Select2, and localized data for onboarding wizards.
 *
 * @package     ArrayPress\RegisterOnboarding
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterOnboarding\Traits;

trait EnqueuesAssets {

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

		// Resolve current step
		$visible_keys = self::get_visible_step_keys( $config );
		$current_key  = sanitize_key( $_GET['step'] ?? '' );

		if ( empty( $current_key ) || ! in_array( $current_key, $visible_keys, true ) ) {
			$current_key = $visible_keys[0] ?? '';
		}

		$current_step = $config['steps'][ $current_key ] ?? [];

		// Localize wizard data
		wp_localize_script( 'onboarding-wizard-scripts', 'onboardingWizard', [
			'depends'  => self::build_depends_map( $id, $config ),
			'confetti' => ! empty( $current_step['confetti'] ),
			'syncStep' => ( $current_step['type'] ?? '' ) === 'sync',
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

}
