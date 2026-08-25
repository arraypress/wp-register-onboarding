<?php
/**
 * Wizard
 *
 * @package     ArrayPress\RegisterOnboarding
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       2.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterOnboarding;

use ArrayPress\FieldKit\Assets;
use ArrayPress\FieldKit\Context\ArrayContext;
use ArrayPress\FieldKit\Context\OptionContext;
use ArrayPress\FieldKit\Contracts\Context;
use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\FieldSet;
use ArrayPress\RegisterOnboarding\Support\Storage;
use ArrayPress\RegisterOnboarding\Utils\Runtime;
use WP_Error;

/**
 * One setup wizard: its steps, where it stores answers, and how it advances.
 *
 * The rendering is Screen's. What is here is everything that has to be right
 * whether or not anybody looks at the page — which step is current, whether
 * this user may submit it, what a submission sanitizes to, and when the
 * wizard is finished.
 */
final class Wizard {

	/**
	 * The wizard's identifier.
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * Its configuration.
	 *
	 * @var array<string, mixed>
	 */
	private array $config;

	/**
	 * Errors from a submission that did not validate, keyed by field.
	 *
	 * @var array<string, string>
	 */
	private array $errors = [];

	/**
	 * The submission being re-rendered, when one failed to validate.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $submitted = null;

	/**
	 * The screen hook this wizard's page was registered under.
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Construct.
	 *
	 * @param string               $id     The wizard's identifier.
	 * @param array<string, mixed> $config Its configuration.
	 */
	public function __construct( string $id, array $config ) {
		$this->id     = $id;
		$this->config = self::normalize( $id, $config );
	}

	/**
	 * Fill in everything the configuration did not say.
	 *
	 * @param string               $id     The wizard's identifier.
	 * @param array<string, mixed> $config Its configuration.
	 *
	 * @return array<string, mixed>
	 */
	private static function normalize( string $id, array $config ): array {
		$config = array_merge(
			[
				// Where it lives. index.php rather than nothing: a page with
				// no parent used to be registered against null, which core
				// hands to plugin_basename() and PHP 8.1 deprecates. Hidden
				// is the same page with its menu item taken back out, which
				// is how core's own hidden screens do it.
				'parent_slug'        => 'index.php',
				'menu_slug'          => '',
				'hidden'             => true,
				'capability'         => 'manage_options',

				// What it is called.
				'page_title'         => '',
				'menu_title'         => '',
				'header_title'       => '',
				'logo'               => '',

				// When it runs, and what happens at the end.
				'redirect'           => false,
				'completed_option'   => '',
				'completed_redirect' => '',

				// Where answers go. One option holding every field, a pair
				// of callbacks, or — saying nothing — an option per field.
				'option'             => '',
				'get_callback'       => null,
				'update_callback'    => null,

				'steps'              => [],
				'body_class'         => '',
				'notices'            => false,
				'accent'             => '',
				'labels'             => [],
			],
			$config
		);

		$config['menu_slug'] = sanitize_key( '' === (string) $config['menu_slug'] ? $id : (string) $config['menu_slug'] );

		if ( '' === (string) $config['page_title'] ) {
			$config['page_title'] = __( 'Setup', 'arraypress' );
		}

		foreach ( [ 'menu_title', 'header_title' ] as $key ) {
			if ( '' === (string) $config[ $key ] ) {
				$config[ $key ] = $config['page_title'];
			}
		}

		if ( '' === (string) $config['completed_option'] ) {
			$config['completed_option'] = str_replace( '-', '_', sanitize_key( $id ) ) . '_completed';
		}

		$config['labels'] = array_merge(
			[
				'next'     => __( 'Continue', 'arraypress' ),
				'previous' => __( 'Back', 'arraypress' ),
				'skip'     => __( 'Skip this step', 'arraypress' ),
				'finish'   => __( 'Finish setup', 'arraypress' ),
				'exit'     => __( 'Exit setup', 'arraypress' ),
			],
			(array) $config['labels']
		);

		// Hex or nothing. It lands in a style attribute, and esc_attr() stops
		// a value closing the attribute but not one appending a second
		// declaration after a semicolon.
		$config['accent'] = (string) ( sanitize_hex_color( (string) $config['accent'] ) ?? '' );

		$config['steps'] = self::normalize_steps( (array) $config['steps'] );

		return $config;
	}

	/**
	 * Fill in every step's defaults.
	 *
	 * @param array<string, array<string, mixed>> $steps The steps.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function normalize_steps( array $steps ): array {
		$normalized = [];

		foreach ( $steps as $key => $step ) {
			$normalized[ (string) $key ] = array_merge(
				[
					'title'       => '',
					'description' => '',
					'type'        => 'fields',
					'icon'        => '',
					'show_if'     => null,
					'skippable'   => false,
					'skip_label'  => '',
					'confetti'    => false,
					'redirect'    => '',

					// fields
					'fields'      => [],

					// content
					'image'       => '',
					'content'     => '',
					'items'       => [],
					'links'       => [],

					// callback
					'render'      => null,

					// sync
					'sync_id'     => '',

					'validate'    => null,
					'save'        => null,
				],
				(array) $step
			);
		}

		return $normalized;
	}

	/**
	 * The wizard's identifier.
	 *
	 * @return string
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * One configuration value.
	 *
	 * @param string $key      The key.
	 * @param mixed  $fallback Returned when it is not set.
	 *
	 * @return mixed
	 */
	public function get( string $key, mixed $fallback = null ): mixed {
		return $this->config[ $key ] ?? $fallback;
	}

	/**
	 * The page slug.
	 *
	 * @return string
	 */
	public function slug(): string {
		return (string) $this->config['menu_slug'];
	}

	/**
	 * Whether this user may run the wizard.
	 *
	 * @return bool
	 */
	public function is_permitted(): bool {
		return current_user_can( (string) $this->config['capability'] );
	}

	/* ---------------------------------------------------------------------
	 * Steps
	 * ------------------------------------------------------------------ */

	/**
	 * The steps that apply, in order.
	 *
	 * A step with a `show_if` that says no is not hidden, it is absent: it is
	 * not counted in the progress, not reachable by its own URL, and a
	 * submission naming it is refused.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function steps(): array {
		return array_filter(
			$this->config['steps'],
			static fn( array $step ): bool => ! is_callable( $step['show_if'] ) || (bool) call_user_func( $step['show_if'] )
		);
	}

	/**
	 * The keys of the steps that apply.
	 *
	 * @return string[]
	 */
	public function keys(): array {
		return array_keys( $this->steps() );
	}

	/**
	 * One step, if it applies.
	 *
	 * @param string $key The step's key.
	 *
	 * @return array<string, mixed>|null
	 */
	public function step( string $key ): ?array {
		return $this->steps()[ $key ] ?? null;
	}

	/**
	 * The step the request is on.
	 *
	 * @return string
	 */
	public function current_key(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which step to show, not acting on it.
		$asked = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '';
		$keys  = $this->keys();

		return in_array( $asked, $keys, true ) ? $asked : (string) ( $keys[0] ?? '' );
	}

	/**
	 * The step before or after another.
	 *
	 * @param string $key       The step to move from.
	 * @param int    $direction 1 for the next, -1 for the one before.
	 *
	 * @return string The step's key, or empty at either end.
	 */
	public function adjacent( string $key, int $direction ): string {
		$keys  = $this->keys();
		$index = array_search( $key, $keys, true );

		if ( false === $index ) {
			return '';
		}

		return (string) ( $keys[ $index + $direction ] ?? '' );
	}

	/**
	 * Whether a step is the last that applies.
	 *
	 * @param string $key The step's key.
	 *
	 * @return bool
	 */
	public function is_last( string $key ): bool {
		return '' === $this->adjacent( $key, 1 );
	}

	/* ---------------------------------------------------------------------
	 * URLs
	 * ------------------------------------------------------------------ */

	/**
	 * The URL of a step.
	 *
	 * Built rather than taken from menu_page_url(), which escapes what it
	 * returns for display — turning every `&` into an entity, which is right
	 * in an href and wrong in a redirect. The rule for which file a page is
	 * reached through is core's own, from that function.
	 *
	 * @param string $step The step, or empty for the first.
	 *
	 * @return string
	 */
	public function url( string $step = '' ): string {
		global $_parent_pages;

		$slug   = $this->slug();
		$parent = (string) ( $_parent_pages[ $slug ] ?? $this->config['parent_slug'] );

		// A page whose parent is itself a plugin page is reached through
		// admin.php, because the parent has no file of its own.
		$base = ( '' === $parent || isset( $_parent_pages[ $parent ] ) ) ? 'admin.php' : $parent;

		$args = [ 'page' => $slug ];

		if ( '' !== $step ) {
			$args['step'] = $step;
		}

		return admin_url( add_query_arg( $args, $base ) );
	}

	/* ---------------------------------------------------------------------
	 * Completion
	 * ------------------------------------------------------------------ */

	/**
	 * Whether the wizard has been finished.
	 *
	 * @return bool
	 */
	public function is_completed(): bool {
		return (bool) get_option( (string) $this->config['completed_option'] );
	}

	/**
	 * Record that it has been finished.
	 *
	 * @return void
	 */
	public function complete(): void {
		update_option( (string) $this->config['completed_option'], time() );

		/**
		 * Fires when a wizard is finished.
		 *
		 * @param string $id     The wizard's identifier.
		 * @param Wizard $wizard The wizard.
		 *
		 * @since 2.0.0
		 */
		do_action( 'arraypress_onboarding_completed', $this->id, $this );
		do_action( "arraypress_onboarding_completed_{$this->id}", $this );
	}

	/**
	 * Let it run again.
	 *
	 * @return bool
	 */
	public function reset(): bool {
		return delete_option( (string) $this->config['completed_option'] );
	}

	/* ---------------------------------------------------------------------
	 * Fields
	 * ------------------------------------------------------------------ */

	/**
	 * Where this wizard's answers are stored.
	 *
	 * @return Context
	 */
	public function context(): Context {
		if ( '' !== (string) $this->config['option'] ) {
			return new OptionContext( (string) $this->config['option'] );
		}

		return new Storage(
			is_callable( $this->config['get_callback'] ) ? $this->config['get_callback'] : null,
			is_callable( $this->config['update_callback'] ) ? $this->config['update_callback'] : null
		);
	}

	/**
	 * The field set for a step.
	 *
	 * @param array<string, mixed> $step    The step.
	 * @param Context|null         $context Where values come from and go.
	 *
	 * @return FieldSet|null Null when the step has no fields.
	 */
	public function fields( array $step, ?Context $context = null ): ?FieldSet {
		$configs = (array) $step['fields'];

		if ( [] === $configs ) {
			return null;
		}

		return new FieldSet( $configs, $context ?? $this->context(), $this->input_name() );
	}

	/**
	 * The field set for the step being rendered.
	 *
	 * Seeded with the submission when one failed to validate, so a field the
	 * user cleared stays cleared rather than reverting to what is stored —
	 * which used to make an emptied field look saved and a corrected one look
	 * ignored.
	 *
	 * @param array<string, mixed> $step The step.
	 *
	 * @return FieldSet|null
	 */
	public function fields_to_render( array $step ): ?FieldSet {
		return $this->fields( $step, null === $this->submitted ? null : new ArrayContext( $this->submitted ) );
	}

	/**
	 * The name a step's fields are submitted under.
	 *
	 * @return string
	 */
	public function input_name(): string {
		return 'onboarding_' . str_replace( '-', '_', sanitize_key( $this->id ) );
	}

	/**
	 * Errors from a submission that did not validate.
	 *
	 * @return array<string, string>
	 */
	public function errors(): array {
		return $this->errors;
	}

	/* ---------------------------------------------------------------------
	 * Submission
	 * ------------------------------------------------------------------ */

	/**
	 * Handle a submission of one of this wizard's steps.
	 *
	 * @return void
	 */
	public function submit(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified two lines down, once the step is known.
		$key = isset( $_POST['onboarding_step'] ) ? sanitize_key( wp_unslash( $_POST['onboarding_step'] ) ) : '';

		check_admin_referer( 'onboarding_' . $this->id . '_' . $key );

		if ( ! $this->is_permitted() ) {
			wp_die(
				esc_html__( 'You are not allowed to run this setup.', 'arraypress' ),
				'',
				[ 'response' => 403 ]
			);
		}

		$step = $this->step( $key );

		// A step that no longer applies — its show_if changed while the form
		// was open — is not saved. Back to the beginning rather than an error
		// page: the answers that got here are still stored.
		if ( null === $step ) {
			$this->go( $this->url() );
		}

		$values = $this->sanitized( $step );
		$errors = $this->validate( $step, $values );

		if ( [] !== $errors ) {
			$this->errors    = $errors;
			$this->submitted = $values;

			return;
		}

		$this->save( $step, $values );

		if ( $this->is_last( $key ) ) {
			$this->complete();

			$this->go(
				(string) ( $step['redirect'] ?: $this->config['completed_redirect'] ?: admin_url() )
			);
		}

		$this->go( $this->url( $this->adjacent( $key, 1 ) ) );
	}

	/**
	 * What a submission sanitizes to, without storing any of it.
	 *
	 * Two passes over a handful of fields, because the alternative is
	 * validating raw input — which means every validator has to do the
	 * sanitizing again to know what it is looking at, and then disagree with
	 * the kit about it.
	 *
	 * @param array<string, mixed> $step The step.
	 *
	 * @return array<string, mixed>
	 */
	private function sanitized( array $step ): array {
		$set = $this->fields( $step, new ArrayContext() );

		if ( null === $set ) {
			return [];
		}

		// Still slashed: the field set unslashes once, at its own boundary.
		return $set->save( $this->raw_input() );
	}

	/**
	 * Check a sanitized submission.
	 *
	 * @param array<string, mixed> $step   The step.
	 * @param array<string, mixed> $values The sanitized values.
	 *
	 * @return array<string, string> Messages keyed by field, `_step` for the step's own.
	 */
	private function validate( array $step, array $values ): array {
		$errors = [];

		foreach ( (array) $step['fields'] as $key => $config ) {
			$check = $config['validate'] ?? null;

			if ( ! is_callable( $check ) ) {
				continue;
			}

			$result = $check( $values[ $key ] ?? null, $values );

			if ( $result instanceof WP_Error ) {
				$errors[ (string) $key ] = $result->get_error_message();
			} elseif ( false === $result ) {
				/* translators: %s: the field's label. */
				$errors[ (string) $key ] = sprintf( __( '%s is not valid.', 'arraypress' ), $config['label'] ?? $key );
			}
		}

		if ( is_callable( $step['validate'] ) ) {
			$result = call_user_func( $step['validate'], $values );

			if ( $result instanceof WP_Error ) {
				$errors['_step'] = $result->get_error_message();
			} elseif ( false === $result ) {
				$errors['_step'] = __( 'Please check this step before continuing.', 'arraypress' );
			}
		}

		return $errors;
	}

	/**
	 * Store a step's answers.
	 *
	 * @param array<string, mixed> $step   The step.
	 * @param array<string, mixed> $values The sanitized values.
	 *
	 * @return void
	 */
	private function save( array $step, array $values ): void {
		if ( is_callable( $step['save'] ) ) {
			call_user_func( $step['save'], $values, $this );

			return;
		}

		$set = $this->fields( $step );

		if ( null === $set ) {
			return;
		}

		$set->save( $this->raw_input() );
	}

	/**
	 * The step's submission, exactly as it arrived.
	 *
	 * Neither unslashed nor sanitized here, and both on purpose: the field
	 * set unslashes once at its own boundary — doing it twice eats every
	 * backslash the user typed — and sanitizes per field, by type, which is
	 * the only place that knows a number from an email address.
	 *
	 * @return array<string, mixed>
	 */
	private function raw_input(): array {
		$name = $this->input_name();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- submit() verified the nonce; the field set unslashes and sanitizes, per field.
		return isset( $_POST[ $name ] ) ? (array) $_POST[ $name ] : [];
	}

	/**
	 * Go somewhere and stop.
	 *
	 * @param string $url Where to.
	 *
	 * @return never
	 */
	private function go( string $url ): never {
		wp_safe_redirect( $url );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Screen
	 * ------------------------------------------------------------------ */

	/**
	 * Register the wizard's admin page.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$this->hook = (string) add_submenu_page(
			(string) $this->config['parent_slug'],
			(string) $this->config['page_title'],
			(string) $this->config['menu_title'],
			(string) $this->config['capability'],
			$this->slug(),
			fn() => ( new Screen( $this ) )->render()
		);
	}

	/**
	 * Take the menu item back out, leaving the page reachable.
	 *
	 * @return void
	 */
	public function hide_menu(): void {
		if ( (bool) $this->config['hidden'] ) {
			remove_submenu_page( (string) $this->config['parent_slug'], $this->slug() );
		}
	}

	/**
	 * The screen hook the page was registered under.
	 *
	 * @return string
	 */
	public function hook(): string {
		return $this->hook;
	}

	/**
	 * Whether the request is on this wizard's page.
	 *
	 * @return bool
	 */
	public function is_current(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which page is being viewed.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		return $page === $this->slug();
	}

	/**
	 * Load what the current step needs.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		$step = $this->step( $this->current_key() );

		if ( null === $step ) {
			return;
		}

		$set = $this->fields_to_render( $step );

		if ( null !== $set ) {
			( new Assets() )->enqueue( $set->dependencies() );
		}

		arraypress_enqueue_composer_style( Runtime::handle(), __FILE__, 'css/onboarding.css' );
		arraypress_enqueue_composer_script( Runtime::handle(), __FILE__, 'js/onboarding.js', [], false, true );
	}

	/**
	 * Declare the configuration this library reads on top of the kit's.
	 *
	 * Without this the kit warns, under WP_DEBUG, that it does not know what
	 * `option` or `validate` are for — which is the point of the warning, and
	 * would be wrong here.
	 *
	 * @return void
	 */
	public static function declare_config_keys(): void {
		Field::allow_config_keys( [ 'option', 'validate' ] );
	}
}
