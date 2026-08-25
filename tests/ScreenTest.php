<?php
/**
 * Screen tests.
 *
 * @package ArrayPress\RegisterOnboarding
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterOnboarding\Tests;

use ArrayPress\RegisterOnboarding\Onboarding;
use ArrayPress\RegisterOnboarding\Screen;
use ArrayPress\RegisterOnboarding\Wizard;
use OnboardingDied;
use PHPUnit\Framework\TestCase;

/**
 * The page around the fields.
 *
 * Most of what is asserted here is structure rather than appearance, because
 * the structure is what carries meaning to anyone not looking at it: an
 * ordered list of named places with one marked current, a single submit
 * button so that Enter does the obvious thing, and an alert region for what
 * came back wrong.
 */
final class ScreenTest extends TestCase {

	/**
	 * Reset the stubbed globals and the registry.
	 */
	protected function setUp(): void {
		ob_reset_globals();
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
						'welcome' => [ 'type' => 'content', 'title' => 'Welcome', 'icon' => 'admin-home' ],
						'store'   => [
							'title'  => 'Your store',
							'fields' => [ 'store_name' => [ 'type' => 'text', 'label' => 'Store name' ] ],
						],
						'done'    => [ 'type' => 'content', 'title' => 'All done' ],
					],
				],
				$config
			)
		);

		$this->assertInstanceOf( Wizard::class, $wizard );

		return $wizard;
	}

	/**
	 * Render the current step.
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
	 * The progress is a list of places, one of which you are at.
	 *
	 * Not a progress bar: a progress bar is one value between two bounds and
	 * has no children to name. This has three named steps and you are on the
	 * second of them.
	 */
	public function test_the_progress_is_a_list_with_a_current_step(): void {
		$wizard = $this->wizard();

		$_GET['step'] = 'store';
		$html         = $this->render( $wizard );

		$this->assertStringContainsString( '<nav class="onboarding__progress"', $html );
		$this->assertSame( 3, substr_count( $html, '<li class="onboarding__step' ) );
		$this->assertSame( 1, substr_count( $html, 'aria-current="step"' ) );
		$this->assertStringContainsString( 'onboarding__step--done', $html );
		$this->assertStringContainsString( 'onboarding__step--upcoming', $html );
	}

	/**
	 * A wizard of one step has nothing to show progress through.
	 */
	public function test_a_single_step_has_no_progress(): void {
		$wizard = $this->wizard( [ 'steps' => [ 'only' => [ 'type' => 'content', 'title' => 'Only' ] ] ] );

		$this->assertStringNotContainsString( 'onboarding__progress', $this->render( $wizard ) );
	}

	/**
	 * Back and Skip are links, and Continue is the only button.
	 *
	 * Which is the whole reason: with three submit buttons in a form, Enter
	 * submits the first one in the document — Back — so the wizard carried a
	 * hidden direction input, a click handler to set it and a keydown handler
	 * to stop Enter doing that. Neither Back nor Skip saves anything, so
	 * neither needs to post.
	 */
	public function test_only_continue_is_a_button(): void {
		$wizard = $this->wizard(
			[
				'steps' => [
					'welcome' => [ 'type' => 'content', 'title' => 'Welcome' ],
					'store'   => [ 'type' => 'content', 'title' => 'Store', 'skippable' => true ],
					'done'    => [ 'type' => 'content', 'title' => 'Done' ],
				],
			]
		);

		$_GET['step'] = 'store';
		$html         = $this->render( $wizard );

		$this->assertSame( 1, substr_count( $html, '<button' ) );
		$this->assertStringContainsString( 'class="onboarding__back"', $html );
		$this->assertStringContainsString( 'class="onboarding__skip"', $html );
		$this->assertStringNotContainsString( 'onboarding_direction', $html );
	}

	/**
	 * The first step has nothing to go back to, and the last nothing to skip.
	 */
	public function test_the_ends_of_the_wizard_lose_their_links(): void {
		$wizard = $this->wizard();

		$this->assertStringNotContainsString( 'onboarding__back', $this->render( $wizard ) );

		$_GET['step'] = 'done';
		$html         = $this->render( $wizard );

		$this->assertStringNotContainsString( 'onboarding__skip', $html );
		$this->assertStringContainsString( 'Finish setup', $html );
	}

	/**
	 * The form posts to the step it is on.
	 *
	 * So a submission that comes back with errors comes back on the right
	 * step rather than the first one.
	 */
	public function test_the_form_posts_to_its_own_step(): void {
		$wizard = $this->wizard();

		$_GET['step'] = 'store';

		$this->assertStringContainsString( 'step=store', $this->render( $wizard ) );
	}

	/**
	 * An accent has to be a colour.
	 *
	 * It lands in a style attribute. esc_attr() stops a value closing the
	 * attribute, and does nothing at all about one appending a second
	 * declaration after a semicolon.
	 */
	public function test_an_accent_has_to_be_a_colour(): void {
		$wizard = $this->wizard( [ 'accent' => '#8a2be2' ] );

		$this->assertStringContainsString( '--onboarding-accent:#8a2be2', $this->render( $wizard ) );

		Onboarding::unregister( 'myplugin' );

		$wizard = $this->wizard( [ 'accent' => 'red;background:url(https://example.test/x)' ] );

		$this->assertStringNotContainsString( 'background:url', $this->render( $wizard ) );
		$this->assertStringNotContainsString( '--onboarding-accent', $this->render( $wizard ) );
	}

	/**
	 * A step's icon works with or without its prefix.
	 *
	 * The prefix used to be stripped with ltrim(), which takes a set of
	 * characters rather than a prefix: `dashicons-chart-bar` came out as
	 * `rt-bar`, because every leading letter that appears in the word
	 * "dashicons" was eaten.
	 */
	public function test_an_icon_keeps_its_name(): void {
		$wizard = $this->wizard(
			[
				'steps' => [
					'one' => [ 'type' => 'content', 'title' => 'One', 'icon' => 'chart-bar' ],
					'two' => [ 'type' => 'content', 'title' => 'Two', 'icon' => 'dashicons-store' ],
				],
			]
		);

		$html = $this->render( $wizard );

		$this->assertStringContainsString( 'dashicons-chart-bar', $html );
		$this->assertStringContainsString( 'dashicons-store', $html );
	}

	/**
	 * Errors are announced, not left to be found.
	 */
	public function test_errors_are_an_alert(): void {
		$wizard = $this->wizard(
			[
				'steps' => [
					'store' => [
						'fields' => [
							'store_name' => [ 'type' => 'text', 'validate' => static fn(): bool => false ],
						],
					],
					'done'  => [ 'type' => 'content', 'title' => 'Done' ],
				],
			]
		);

		$_POST = [
			Onboarding::INPUT     => 'myplugin',
			'onboarding_step'     => 'store',
			$wizard->input_name() => [ 'store_name' => 'Acme' ],
		];

		$wizard->submit();

		$html = $this->render( $wizard );

		$this->assertStringContainsString( 'role="alert"', $html );
		$this->assertStringContainsString( 'notice notice-error', $html );
	}

	/**
	 * A logo replaces the title without removing it.
	 *
	 * The page still needs a heading for anyone not looking at the picture.
	 */
	public function test_a_logo_keeps_the_heading(): void {
		$wizard = $this->wizard(
			[
				'logo'         => 'https://example.test/logo.svg',
				'header_title' => 'My Plugin',
			]
		);

		$html = $this->render( $wizard );

		$this->assertStringContainsString( 'onboarding__logo', $html );
		$this->assertStringContainsString( '<h1 class="screen-reader-text">My Plugin</h1>', $html );
	}

	/**
	 * Content is markup the caller wrote; titles are text.
	 */
	public function test_content_is_filtered_and_titles_are_escaped(): void {
		$wizard = $this->wizard(
			[
				'steps' => [
					'one' => [
						'type'    => 'content',
						'title'   => '<script>alert(1)</script>Welcome',
						'content' => '<p>Read the <a href="https://example.test">docs</a>.<script>alert(1)</script></p>',
					],
					'two' => [ 'type' => 'content', 'title' => 'Two' ],
				],
			]
		);

		$html = $this->render( $wizard );

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( '<a href="https://example.test">docs</a>', $html );
	}

	/**
	 * A link that opens elsewhere says so.
	 */
	public function test_an_external_link_says_that_it_is_external(): void {
		$wizard = $this->wizard(
			[
				'steps' => [
					'one' => [
						'type'  => 'content',
						'title' => 'Done',
						'links' => [
							[ 'label' => 'Read the docs', 'url' => 'https://example.test/docs', 'external' => true ],
							[ 'label' => 'Add a product', 'url' => 'https://example.test/wp-admin/post-new.php' ],
						],
					],
					'two' => [ 'type' => 'content', 'title' => 'Two' ],
				],
			]
		);

		$html = $this->render( $wizard );

		$this->assertStringContainsString( 'rel="noopener noreferrer"', $html );
		$this->assertStringContainsString( 'opens in a new tab', $html );
		$this->assertSame( 1, substr_count( $html, 'target="_blank"' ) );
	}

	/**
	 * A user who may not run the wizard does not get the page.
	 */
	public function test_the_page_is_refused_without_the_capability(): void {
		$wizard = $this->wizard();

		$GLOBALS['ob_caps'] = [ 'read' ];

		$this->expectException( OnboardingDied::class );

		$this->render( $wizard );
	}

	/**
	 * A step type nobody knows is handed to whoever does.
	 */
	public function test_an_unknown_step_type_is_handed_on(): void {
		$wizard = $this->wizard(
			[
				'steps' => [
					'one' => [ 'type' => 'plugin_special', 'title' => 'Special' ],
					'two' => [ 'type' => 'content', 'title' => 'Two' ],
				],
			]
		);

		$this->render( $wizard );

		$this->assertContains( 'arraypress_onboarding_render_plugin_special', $GLOBALS['ob_actions']['fired'] );
	}

	/**
	 * A callback step draws itself.
	 */
	public function test_a_callback_step_draws_itself(): void {
		$wizard = $this->wizard(
			[
				'steps' => [
					'one' => [
						'type'   => 'callback',
						'title'  => 'Special',
						'render' => static function (): void {
							echo '<p class="mine">Drawn by the plugin.</p>';
						},
					],
					'two' => [ 'type' => 'content', 'title' => 'Two' ],
				],
			]
		);

		$this->assertStringContainsString( 'Drawn by the plugin.', $this->render( $wizard ) );
	}

	/**
	 * The wizard's page is marked, so a plugin can style around it.
	 */
	public function test_the_body_is_marked(): void {
		$this->wizard( [ 'body_class' => 'myplugin-setup' ] );

		$_GET['page'] = 'myplugin';

		$classes = Onboarding::body_class( 'wp-admin' );

		$this->assertStringContainsString( 'onboarding-wizard', $classes );
		$this->assertStringContainsString( 'onboarding-wizard-myplugin', $classes );
		$this->assertStringContainsString( 'myplugin-setup', $classes );
	}

	/**
	 * And every other page is left alone.
	 */
	public function test_another_page_is_left_alone(): void {
		$this->wizard();

		$_GET['page'] = 'something-else';

		$this->assertSame( 'wp-admin', Onboarding::body_class( 'wp-admin' ) );
	}

	/**
	 * Somebody else's notices are cleared off a takeover page.
	 *
	 * Core prints every registered notice above whatever the page callback
	 * returns, so an unrelated plugin's "Your licence expires in 14 days"
	 * lands on top of the first screen of a plugin that has just been
	 * installed.
	 */
	public function test_other_plugins_notices_are_cleared(): void {
		$this->wizard();

		add_action( 'admin_notices', 'some_other_plugin' );
		$_GET['page'] = 'myplugin';

		Onboarding::quieten();

		$this->assertArrayNotHasKey( 'admin_notices', $GLOBALS['ob_actions'] );
	}

	/**
	 * A wizard that would rather keep them keeps them.
	 */
	public function test_notices_can_be_kept(): void {
		$this->wizard( [ 'notices' => true ] );

		add_action( 'admin_notices', 'some_other_plugin' );
		$_GET['page'] = 'myplugin';

		Onboarding::quieten();

		$this->assertArrayHasKey( 'admin_notices', $GLOBALS['ob_actions'] );
	}

	/**
	 * And every other admin page keeps them.
	 */
	public function test_notices_survive_everywhere_else(): void {
		$this->wizard();

		add_action( 'admin_notices', 'some_other_plugin' );
		$_GET['page'] = 'something-else';

		Onboarding::quieten();

		$this->assertArrayHasKey( 'admin_notices', $GLOBALS['ob_actions'] );
	}

	/**
	 * The kit is loaded for a step that has fields, and so are ours.
	 */
	public function test_assets_load_for_the_step_being_shown(): void {
		$wizard = $this->wizard();

		$_GET['step'] = 'store';
		$wizard->enqueue();

		$this->assertArrayHasKey( 'onboarding', $GLOBALS['ob_styles'] );
		$this->assertArrayHasKey( 'onboarding', $GLOBALS['ob_scripts'] );
		$this->assertArrayHasKey( 'field-kit', $GLOBALS['ob_styles'] );
	}

	/**
	 * A step with nothing to fill in does not load the field kit.
	 */
	public function test_a_content_step_does_not_load_the_field_kit(): void {
		$wizard = $this->wizard();

		$wizard->enqueue();

		$this->assertArrayNotHasKey( 'field-kit', $GLOBALS['ob_styles'] );
		$this->assertArrayHasKey( 'onboarding', $GLOBALS['ob_styles'] );
	}
}
