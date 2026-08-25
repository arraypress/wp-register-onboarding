<?php
/**
 * Wizard tests.
 *
 * @package ArrayPress\RegisterOnboarding
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterOnboarding\Tests;

use ArrayPress\RegisterOnboarding\Onboarding;
use ArrayPress\RegisterOnboarding\Screen;
use ArrayPress\RegisterOnboarding\Wizard;
use OnboardingDied;
use OnboardingRedirect;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * A setup wizard is a form that runs before anything is set up, which is the
 * awkward part: it writes settings it does not own, on behalf of a user who
 * has not seen the plugin yet, across several requests with a redirect
 * between each.
 *
 * So what is tested here is the crossing points — which step is current, what
 * a submission sanitizes to, where an answer is stored, and when the thing is
 * finished — rather than the fields, which are the kit's and are tested there.
 */
final class WizardTest extends TestCase {

	/**
	 * Reset the stubbed globals and the registry.
	 */
	protected function setUp(): void {
		ob_reset_globals();

		foreach ( array_keys( Onboarding::all() ) as $id ) {
			Onboarding::unregister( (string) $id );
		}
	}

	/**
	 * Register a wizard.
	 *
	 * @param array<string, mixed> $config Overrides.
	 *
	 * @return Wizard
	 */
	private function wizard( array $config = [] ): Wizard {
		$wizard = Onboarding::register(
			'myplugin',
			array_merge(
				[
					'steps' => [
						'welcome' => [
							'type'    => 'content',
							'title'   => 'Welcome',
							'content' => '<p>Hello.</p>',
						],
						'store'   => [
							'title'  => 'Your store',
							'fields' => [
								'store_name' => [
									'type'  => 'text',
									'label' => 'Store name',
								],
							],
						],
						'done'    => [
							'type'  => 'content',
							'title' => 'All done',
						],
					],
				],
				$config
			)
		);

		$this->assertInstanceOf( Wizard::class, $wizard );

		return $wizard;
	}

	/**
	 * Render a wizard's current step and return the markup.
	 *
	 * @param Wizard $wizard The wizard.
	 *
	 * @return string
	 */
	private function render( Wizard $wizard ): string {
		ob_start();

		try {
			( new Screen( $wizard ) )->render();
		} finally {
			// Cleaned here and returned below: returning from a finally
			// block discards whatever exception was in flight, so a page
			// that refused to render would look like one that rendered
			// nothing.
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	/**
	 * Post a step.
	 *
	 * @param Wizard               $wizard The wizard.
	 * @param string               $step   The step's key.
	 * @param array<string, mixed> $values The field values.
	 *
	 * @return string|null Where it redirected, or null when it did not.
	 */
	private function submit( Wizard $wizard, string $step, array $values = [] ): ?string {
		$_POST = [
			Onboarding::INPUT           => $wizard->id(),
			'onboarding_step'           => $step,
			$wizard->input_name()       => $values,
		];

		try {
			$wizard->submit();
		} catch ( OnboardingRedirect $redirect ) {
			return $redirect->url;
		}

		return null;
	}

	/* ---------------------------------------------------------------------
	 * Registration
	 * ------------------------------------------------------------------ */

	/**
	 * A wizard with nothing to ask is not a wizard.
	 */
	public function test_a_wizard_without_steps_is_refused(): void {
		$this->assertNull( Onboarding::register( 'empty', [] ) );
		$this->assertNull( Onboarding::register( '', [ 'steps' => [ 'a' => [] ] ] ) );
	}

	/**
	 * Registering attaches the hooks the page needs.
	 */
	public function test_registering_attaches_the_shared_hooks(): void {
		$this->wizard();

		foreach ( [ 'admin_menu', 'admin_head', 'admin_enqueue_scripts', 'admin_init' ] as $hook ) {
			$this->assertArrayHasKey( $hook, $GLOBALS['ob_actions'], sprintf( '%s is not hooked.', $hook ) );
		}

		$this->assertArrayHasKey( 'admin_body_class', $GLOBALS['ob_filters'] );
	}

	/**
	 * They are attached once, however many wizards there are.
	 */
	public function test_the_hooks_are_attached_once(): void {
		$this->wizard();
		Onboarding::register( 'second', [ 'steps' => [ 'a' => [ 'type' => 'content' ] ] ] );

		$this->assertCount( 1, $GLOBALS['ob_actions']['admin_menu'] );
	}

	/* ---------------------------------------------------------------------
	 * Steps
	 * ------------------------------------------------------------------ */

	/**
	 * A step whose condition says no is absent, not hidden.
	 *
	 * Not counted in the progress, not reachable by its own URL, and a
	 * submission naming it is refused — because a step you can still post to
	 * is a step that is still there.
	 */
	public function test_a_step_that_does_not_apply_is_absent(): void {
		$wizard = $this->wizard(
			[
				'steps' => [
					'first'  => [ 'type' => 'content', 'title' => 'First' ],
					'maybe'  => [ 'type' => 'content', 'title' => 'Maybe', 'show_if' => static fn(): bool => false ],
					'last'   => [ 'type' => 'content', 'title' => 'Last' ],
				],
			]
		);

		$this->assertSame( [ 'first', 'last' ], $wizard->keys() );
		$this->assertNull( $wizard->step( 'maybe' ) );
		$this->assertSame( 'last', $wizard->adjacent( 'first', 1 ) );
		$this->assertTrue( $wizard->is_last( 'last' ) );
	}

	/**
	 * The request's step is used when it is one of the wizard's.
	 */
	public function test_the_current_step_comes_from_the_request(): void {
		$wizard = $this->wizard();

		$this->assertSame( 'welcome', $wizard->current_key(), 'The first step is not the default.' );

		$_GET['step'] = 'store';
		$this->assertSame( 'store', $wizard->current_key() );

		$_GET['step'] = 'not_a_step';
		$this->assertSame( 'welcome', $wizard->current_key(), 'An unknown step was accepted.' );
	}

	/* ---------------------------------------------------------------------
	 * URLs
	 * ------------------------------------------------------------------ */

	/**
	 * A step's URL goes through the file its page was registered under.
	 *
	 * Not admin.php regardless, which is what made the menu highlight wrong
	 * on every step after the first and needed two filters to put back.
	 */
	public function test_a_step_url_uses_the_parent_file(): void {
		$wizard = $this->wizard( [ 'parent_slug' => 'index.php' ] );

		Onboarding::register_menus();

		$this->assertSame(
			'https://example.test/wp-admin/index.php?page=myplugin&step=store',
			$wizard->url( 'store' )
		);
	}

	/**
	 * A page under another plugin page is reached through admin.php.
	 *
	 * Core's rule, because a top-level plugin menu has no file of its own.
	 */
	public function test_a_page_under_a_plugin_page_uses_admin_php(): void {
		$GLOBALS['_parent_pages']['myplugin-home'] = 'admin.php';

		$wizard = $this->wizard( [ 'parent_slug' => 'myplugin-home' ] );

		Onboarding::register_menus();

		$this->assertSame(
			'https://example.test/wp-admin/admin.php?page=myplugin',
			$wizard->url()
		);
	}

	/* ---------------------------------------------------------------------
	 * The menu
	 * ------------------------------------------------------------------ */

	/**
	 * The page is registered, and then taken back off the menu.
	 *
	 * Registered against index.php rather than null: core hands the parent to
	 * plugin_basename(), and PHP 8.1 deprecates null there. Hiding is how
	 * core's own hidden screens do it.
	 */
	public function test_the_page_is_registered_and_hidden(): void {
		$this->wizard();

		Onboarding::register_menus();
		Onboarding::hide_menus();

		$this->assertArrayHasKey( 'myplugin', $GLOBALS['ob_menu'] );
		$this->assertSame( 'index.php', $GLOBALS['ob_menu']['myplugin']['parent_slug'] );
		$this->assertContains( 'myplugin', $GLOBALS['ob_removed_menu'] );
	}

	/**
	 * A wizard that wants to be findable stays on the menu.
	 */
	public function test_a_visible_wizard_stays_on_the_menu(): void {
		$this->wizard( [ 'hidden' => false ] );

		Onboarding::register_menus();
		Onboarding::hide_menus();

		$this->assertSame( [], $GLOBALS['ob_removed_menu'] );
	}

	/* ---------------------------------------------------------------------
	 * Completion
	 * ------------------------------------------------------------------ */

	/**
	 * The last step can be finished.
	 *
	 * It could not before. A step of type `complete` was given no forward
	 * button at all, and completion was recorded by submitting the last step
	 * — so a wizard that ended, as the documented example did, on a "You are
	 * all set" screen was never marked as finished. is_onboarding_completed()
	 * returned false for the rest of the install's life.
	 */
	public function test_the_last_step_can_be_finished(): void {
		$wizard = $this->wizard();

		$_GET['step'] = 'done';

		$html = $this->render( $wizard );

		$this->assertStringContainsString( 'Finish setup', $html );
		$this->assertSame( 1, substr_count( $html, 'type="submit"' ) );
	}

	/**
	 * Finishing records it, and says so.
	 */
	public function test_finishing_records_it(): void {
		$wizard = $this->wizard();

		$this->assertFalse( $wizard->is_completed() );

		$this->submit( $wizard, 'done' );

		$this->assertTrue( $wizard->is_completed() );
		$this->assertContains( 'arraypress_onboarding_completed', $GLOBALS['ob_actions']['fired'] );
	}

	/**
	 * Finishing goes where the wizard said, and the dashboard otherwise.
	 */
	public function test_finishing_redirects(): void {
		$wizard = $this->wizard( [ 'completed_redirect' => 'https://example.test/wp-admin/admin.php?page=myplugin' ] );

		$this->assertSame(
			'https://example.test/wp-admin/admin.php?page=myplugin',
			$this->submit( $wizard, 'done' )
		);
	}

	/**
	 * A finished wizard can be run again.
	 */
	public function test_a_finished_wizard_can_be_reset(): void {
		$wizard = $this->wizard();

		$this->submit( $wizard, 'done' );
		$this->assertTrue( $wizard->is_completed() );

		$this->assertTrue( $wizard->reset() );
		$this->assertFalse( $wizard->is_completed() );
	}

	/**
	 * Any other step goes to the next one.
	 */
	public function test_a_step_advances_to_the_next(): void {
		$wizard = $this->wizard();

		$this->assertStringContainsString( 'step=store', (string) $this->submit( $wizard, 'welcome' ) );
		$this->assertFalse( $wizard->is_completed() );
	}

	/* ---------------------------------------------------------------------
	 * Submission
	 * ------------------------------------------------------------------ */

	/**
	 * A submission without a valid nonce goes nowhere.
	 */
	public function test_a_submission_needs_its_nonce(): void {
		$wizard = $this->wizard();

		$GLOBALS['ob_nonce_ok'] = false;

		$this->expectException( OnboardingDied::class );

		$this->submit( $wizard, 'store', [ 'store_name' => 'Acme' ] );
	}

	/**
	 * And a user allowed to make it.
	 *
	 * The capability is checked on the submission and not only on the page:
	 * the page being unreachable is not the same as the endpoint being
	 * closed.
	 */
	public function test_a_submission_needs_the_capability(): void {
		$wizard = $this->wizard();

		$GLOBALS['ob_caps'] = [ 'read' ];

		$this->expectException( OnboardingDied::class );

		$this->submit( $wizard, 'store', [ 'store_name' => 'Acme' ] );
	}

	/**
	 * A step that stopped applying while the form was open is not saved.
	 */
	public function test_a_step_that_no_longer_applies_is_not_saved(): void {
		$wizard = $this->wizard(
			[
				'steps' => [
					'first' => [ 'type' => 'content', 'title' => 'First' ],
					'gone'  => [
						'title'   => 'Gone',
						'show_if' => static fn(): bool => false,
						'fields'  => [ 'store_name' => [ 'type' => 'text' ] ],
					],
				],
			]
		);

		$this->submit( $wizard, 'gone', [ 'store_name' => 'Acme' ] );

		$this->assertArrayNotHasKey( 'store_name', $GLOBALS['ob_options'] );
	}

	/**
	 * A value is sanitized by its own field type.
	 */
	public function test_a_value_is_sanitized_by_its_type(): void {
		$wizard = $this->wizard(
			[
				'steps' => [
					'store' => [
						'fields' => [
							'contact' => [ 'type' => 'email' ],
							'seats'   => [ 'type' => 'number', 'max' => 10 ],
						],
					],
				],
			]
		);

		$this->submit(
			$wizard,
			'store',
			[
				'contact' => '  someone@example.test  ',
				'seats'   => '9999',
			]
		);

		$this->assertSame( 'someone@example.test', $GLOBALS['ob_options']['contact'] );
		$this->assertSame( 10, $GLOBALS['ob_options']['seats'] );

		// An address that is not one is not stored as an empty string, which
		// would read as an answer. It is deleted.
		$this->submit( $wizard, 'store', [ 'contact' => 'not an address', 'seats' => '3' ] );

		$this->assertArrayNotHasKey( 'contact', $GLOBALS['ob_options'] );
	}

	/**
	 * A toggle turned off is stored as off.
	 *
	 * It could not be. The old renderer read a value, treated anything empty
	 * as absent, and fell back to the configured default — so a toggle
	 * defaulting to on could be switched off, saved, and would come back on.
	 * Both halves are asserted, because a wizard that stores nothing at all
	 * would satisfy the second on its own.
	 */
	public function test_a_toggle_can_be_turned_off(): void {
		$wizard = $this->wizard(
			[
				'steps' => [
					'store' => [
						'fields' => [
							'emails' => [ 'type' => 'toggle', 'default' => 1 ],
						],
					],
				],
			]
		);

		$this->submit( $wizard, 'store', [ 'emails' => '1' ] );
		$this->assertSame( 1, $GLOBALS['ob_options']['emails'] );

		// Unticked: absent from the submission entirely.
		$this->submit( $wizard, 'store', [] );
		$this->assertSame( 0, $GLOBALS['ob_options']['emails'] );
	}

	/**
	 * A step's own save callback replaces the storing, not the sanitizing.
	 */
	public function test_a_save_callback_receives_sanitized_values(): void {
		$seen = null;

		$wizard = $this->wizard(
			[
				'steps' => [
					'store' => [
						'fields' => [ 'store_name' => [ 'type' => 'text' ] ],
						'save'   => function ( array $values ) use ( &$seen ): void {
							$seen = $values;
						},
					],
				],
			]
		);

		$this->submit( $wizard, 'store', [ 'store_name' => 'Acme <script>alert(1)</script>' ] );

		$this->assertSame( [ 'store_name' => 'Acme' ], $seen );
		$this->assertArrayNotHasKey( 'store_name', $GLOBALS['ob_options'], 'It stored the value as well.' );
	}

	/* ---------------------------------------------------------------------
	 * Validation
	 * ------------------------------------------------------------------ */

	/**
	 * A field that does not validate stops the step.
	 */
	public function test_a_field_that_does_not_validate_stops_the_step(): void {
		$wizard = $this->wizard(
			[
				'steps' => [
					'store' => [
						'fields' => [
							'store_name' => [
								'type'     => 'text',
								'label'    => 'Store name',
								'validate' => static fn( $value ) => '' === (string) $value
									? new WP_Error( 'required', 'A store name is required.' )
									: true,
							],
						],
					],
				],
			]
		);

		$this->assertNull( $this->submit( $wizard, 'store', [ 'store_name' => '' ] ), 'It advanced anyway.' );
		$this->assertSame( [ 'store_name' => 'A store name is required.' ], $wizard->errors() );
		$this->assertArrayNotHasKey( 'store_name', $GLOBALS['ob_options'] );
	}

	/**
	 * Returning false is the short way of saying the same thing.
	 */
	public function test_returning_false_is_a_message_about_the_field(): void {
		$wizard = $this->wizard(
			[
				'steps' => [
					'store' => [
						'fields' => [
							'store_name' => [
								'type'     => 'text',
								'label'    => 'Store name',
								'validate' => static fn(): bool => false,
							],
						],
					],
				],
			]
		);

		$this->submit( $wizard, 'store', [ 'store_name' => 'Acme' ] );

		$this->assertStringContainsString( 'Store name', $wizard->errors()['store_name'] );
	}

	/**
	 * A step can also check the answers together.
	 */
	public function test_a_step_can_validate_its_answers_together(): void {
		$wizard = $this->wizard(
			[
				'steps' => [
					'store' => [
						'fields'   => [
							'low'  => [ 'type' => 'number' ],
							'high' => [ 'type' => 'number' ],
						],
						'validate' => static fn( array $values ) => $values['low'] <= $values['high']
							? true
							: new WP_Error( 'range', 'The low value must not exceed the high one.' ),
					],
				],
			]
		);

		$this->submit( $wizard, 'store', [ 'low' => '9', 'high' => '2' ] );

		$this->assertArrayHasKey( '_step', $wizard->errors() );
	}

	/**
	 * A step that came back with errors shows what was submitted.
	 *
	 * Not what is stored. The old renderer read POST, treated anything empty
	 * as absent, and fell back to the stored value — so a field the user had
	 * deliberately cleared came back filled in, and a field they had
	 * corrected came back showing the old value they were correcting.
	 */
	public function test_a_failed_step_re_renders_the_submission(): void {
		$GLOBALS['ob_options']['store_name'] = 'The stored name';

		$wizard = $this->wizard(
			[
				'steps' => [
					'store' => [
						'fields' => [
							'store_name' => [ 'type' => 'text', 'label' => 'Store name' ],
							'contact'    => [
								'type'     => 'email',
								'validate' => static fn(): bool => false,
							],
						],
					],
				],
			]
		);

		$this->submit( $wizard, 'store', [ 'store_name' => 'The typed name', 'contact' => 'x@example.test' ] );

		$_GET['step'] = 'store';
		$html         = $this->render( $wizard );

		$this->assertStringContainsString( 'The typed name', $html );
		$this->assertStringNotContainsString( 'The stored name', $html );
	}

	/* ---------------------------------------------------------------------
	 * Storage
	 * ------------------------------------------------------------------ */

	/**
	 * A field goes to the option its key names.
	 */
	public function test_a_field_goes_to_the_option_its_key_names(): void {
		$wizard = $this->wizard();

		$this->submit( $wizard, 'store', [ 'store_name' => 'Acme' ] );

		$this->assertSame( 'Acme', $GLOBALS['ob_options']['store_name'] );
	}

	/**
	 * Unless it names one of its own.
	 *
	 * Which is what a wizard filling in an existing plugin's settings needs:
	 * the field key is short and readable, the option is whatever the plugin
	 * already reads.
	 */
	public function test_a_field_can_name_its_own_option(): void {
		$wizard = $this->wizard(
			[
				'steps' => [
					'store' => [
						'fields' => [
							'name' => [ 'type' => 'text', 'option' => 'myplugin_store_name' ],
						],
					],
				],
			]
		);

		$this->submit( $wizard, 'store', [ 'name' => 'Acme' ] );

		$this->assertSame( 'Acme', $GLOBALS['ob_options']['myplugin_store_name'] );
		$this->assertArrayNotHasKey( 'name', $GLOBALS['ob_options'] );
	}

	/**
	 * A wizard can put every answer in one option instead.
	 */
	public function test_a_wizard_can_store_everything_in_one_option(): void {
		$wizard = $this->wizard( [ 'option' => 'myplugin_settings' ] );

		$this->submit( $wizard, 'store', [ 'store_name' => 'Acme' ] );

		$this->assertSame( [ 'store_name' => 'Acme' ], $GLOBALS['ob_options']['myplugin_settings'] );
	}

	/**
	 * Or hand every read and write to the plugin.
	 */
	public function test_a_wizard_can_hand_storage_to_the_plugin(): void {
		$written = [];

		$wizard = $this->wizard(
			[
				'get_callback'    => static fn( string $key, $default_value ) => 'Already set',
				'update_callback' => function ( string $key, $value ) use ( &$written ): void {
					$written[ $key ] = $value;
				},
			]
		);

		$_GET['step'] = 'store';
		$this->assertStringContainsString( 'Already set', $this->render( $wizard ) );

		$this->submit( $wizard, 'store', [ 'store_name' => 'Acme' ] );

		$this->assertSame( [ 'store_name' => 'Acme' ], $written );
		$this->assertSame( [], $GLOBALS['ob_options'] );
	}

	/* ---------------------------------------------------------------------
	 * The activation redirect
	 * ------------------------------------------------------------------ */

	/**
	 * A wizard that asked to be shown is shown, once.
	 */
	public function test_the_activation_redirect_happens_once(): void {
		$this->wizard( [ 'redirect' => true ] );

		Onboarding::redirect_after_activation( 'myplugin' );

		try {
			Onboarding::route();
			$this->fail( 'It did not redirect.' );
		} catch ( OnboardingRedirect $redirect ) {
			$this->assertStringContainsString( 'page=myplugin', $redirect->url );
		}

		// The second admin request does not.
		Onboarding::route();

		$this->assertTrue( true );
	}

	/**
	 * A wizard already finished is not offered again.
	 */
	public function test_a_finished_wizard_is_not_redirected_to(): void {
		$wizard = $this->wizard( [ 'redirect' => true ] );

		$wizard->complete();
		Onboarding::redirect_after_activation( 'myplugin' );

		Onboarding::route();

		$this->assertTrue( true );
	}

	/**
	 * Activating several plugins at once is left alone.
	 *
	 * That screen lists what happened to each of them, and one plugin taking
	 * it over loses the rest of it.
	 */
	public function test_a_bulk_activation_is_not_interrupted(): void {
		$this->wizard( [ 'redirect' => true ] );

		Onboarding::redirect_after_activation( 'myplugin' );
		$_GET['activate-multi'] = '1';

		Onboarding::route();

		$this->assertTrue( true );
	}

	/**
	 * A wizard that did not ask is not redirected to.
	 */
	public function test_a_wizard_that_did_not_ask_is_not_redirected_to(): void {
		$this->wizard( [ 'redirect' => false ] );

		Onboarding::redirect_after_activation( 'myplugin' );

		Onboarding::route();

		$this->assertTrue( true );
	}
}
