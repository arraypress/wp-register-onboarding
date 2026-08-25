<?php
/**
 * Screen
 *
 * @package     ArrayPress\RegisterOnboarding
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       2.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterOnboarding;

/**
 * One wizard step, drawn.
 *
 * Everything here is markup and the decisions that only matter to markup. The
 * two that are worth stating:
 *
 * Back and Skip are links, not buttons. Neither saves anything, so neither
 * needs to post, and making them submit buttons is what forced the wizard to
 * carry a hidden direction input, a click handler to set it, and a keydown
 * handler to stop Enter submitting the first button in the form — which was
 * Back. One submit button in the form and Enter does the obvious thing with
 * no script at all.
 *
 * And the progress indicator is an ordered list with `aria-current`, rather
 * than a progress bar. A progress bar is a single value; this is a set of
 * named places, one of which you are at.
 */
final class Screen {

	/**
	 * The wizard being drawn.
	 *
	 * @var Wizard
	 */
	private Wizard $wizard;

	/**
	 * Construct.
	 *
	 * @param Wizard $wizard The wizard.
	 */
	public function __construct( Wizard $wizard ) {
		$this->wizard = $wizard;
	}

	/**
	 * Draw the current step.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! $this->wizard->is_permitted() ) {
			wp_die( esc_html__( 'You are not allowed to run this setup.', 'arraypress' ), '', [ 'response' => 403 ] );
		}

		$key  = $this->wizard->current_key();
		$step = $this->wizard->step( $key );

		if ( null === $step ) {
			return;
		}

		$accent = (string) $this->wizard->get( 'accent', '' );

		printf(
			'<div class="onboarding"%s>',
			'' === $accent ? '' : sprintf( ' style="--onboarding-accent:%s"', esc_attr( $accent ) )
		);

		echo '<div class="onboarding__inner">';

		$this->header();
		$this->progress( $key );

		echo '<div class="onboarding__card">';

		$this->step_header( $step );

		// On the form rather than localized into a global. Strauss renames
		// classes and namespaces, not string literals, so two plugins each
		// bundling a prefixed copy would agree on the name of the global and
		// disagree about who set it. An attribute on the element the script
		// already found belongs to that element.
		printf(
			'<form method="post" class="onboarding__form" action="%s" data-sync="%s" data-celebrate="%s">',
			esc_url( $this->wizard->url( $key ) ),
			'sync' === (string) $step['type'] ? '1' : '0',
			empty( $step['confetti'] ) ? '0' : '1'
		);

		wp_nonce_field( 'onboarding_' . $this->wizard->id() . '_' . $key );

		printf(
			'<input type="hidden" name="%s" value="%s"><input type="hidden" name="onboarding_step" value="%s">',
			esc_attr( Onboarding::INPUT ),
			esc_attr( $this->wizard->id() ),
			esc_attr( $key )
		);

		$this->errors();

		echo '<div class="onboarding__content">';
		$this->content( $step );
		echo '</div>';

		$this->actions( $key, $step );

		echo '</form></div>';

		$this->exit_link();

		echo '</div></div>';
	}

	/**
	 * The logo, or the title where there is no logo.
	 *
	 * @return void
	 */
	private function header(): void {
		$logo = (string) $this->wizard->get( 'logo', '' );

		echo '<div class="onboarding__header">';

		if ( '' !== $logo ) {
			// The heading is still there, for anyone not looking at the logo.
			printf(
				'<h1 class="screen-reader-text">%s</h1><img class="onboarding__logo" src="%s" alt="">',
				esc_html( (string) $this->wizard->get( 'header_title' ) ),
				esc_url( $logo )
			);
		} else {
			printf(
				'<h1 class="onboarding__title">%s</h1>',
				esc_html( (string) $this->wizard->get( 'header_title' ) )
			);
		}

		echo '</div>';
	}

	/**
	 * Where this step sits among the others.
	 *
	 * @param string $current The current step's key.
	 *
	 * @return void
	 */
	private function progress( string $current ): void {
		$steps = $this->wizard->steps();

		if ( count( $steps ) < 2 ) {
			return;
		}

		$keys  = array_keys( $steps );
		$index = (int) array_search( $current, $keys, true );

		printf(
			'<nav class="onboarding__progress" aria-label="%s"><ol>',
			esc_attr__( 'Setup steps', 'arraypress' )
		);

		foreach ( array_values( $steps ) as $position => $step ) {
			$state = 'upcoming';

			if ( $position < $index ) {
				$state = 'done';
			} elseif ( $position === $index ) {
				$state = 'current';
			}

			printf(
				'<li class="onboarding__step onboarding__step--%s"%s>',
				esc_attr( $state ),
				'current' === $state ? ' aria-current="step"' : ''
			);

			printf( '<span class="onboarding__marker">%s</span>', $this->marker( $step, $state, $position ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped as it is built.

			printf( '<span class="onboarding__step-title">%s</span>', esc_html( (string) $step['title'] ) );

			// Said once, for anyone who cannot see that the marker is ticked.
			if ( 'done' === $state ) {
				printf( '<span class="screen-reader-text">%s</span>', esc_html__( 'Completed', 'arraypress' ) );
			}

			echo '</li>';
		}

		echo '</ol></nav>';
	}

	/**
	 * What goes in a step's marker: a tick, its icon, or its number.
	 *
	 * @param array<string, mixed> $step     The step.
	 * @param string               $state    done, current or upcoming.
	 * @param int                  $position Its place in the list.
	 *
	 * @return string
	 */
	private function marker( array $step, string $state, int $position ): string {
		if ( 'done' === $state ) {
			return '<span class="dashicons dashicons-yes" aria-hidden="true"></span>';
		}

		if ( '' !== (string) $step['icon'] ) {
			return sprintf(
				'<span class="dashicons %s" aria-hidden="true"></span>',
				esc_attr( self::dashicon( (string) $step['icon'] ) )
			);
		}

		return esc_html( (string) number_format_i18n( $position + 1 ) );
	}

	/**
	 * A dashicon class, whether or not the caller wrote the prefix.
	 *
	 * Not ltrim(): that takes a set of characters rather than a prefix, so
	 * `dashicons-chart-bar` comes back as `rt-bar` — every leading letter
	 * that happens to appear in the word "dashicons" is eaten.
	 *
	 * @param string $icon The icon, with or without its prefix.
	 *
	 * @return string
	 */
	private static function dashicon( string $icon ): string {
		$icon = sanitize_html_class( $icon );

		return str_starts_with( $icon, 'dashicons-' ) ? $icon : 'dashicons-' . $icon;
	}

	/**
	 * The step's own title and description.
	 *
	 * @param array<string, mixed> $step The step.
	 *
	 * @return void
	 */
	private function step_header( array $step ): void {
		if ( '' === (string) $step['title'] && '' === (string) $step['description'] ) {
			return;
		}

		echo '<div class="onboarding__step-header">';

		if ( '' !== (string) $step['title'] ) {
			printf( '<h2>%s</h2>', esc_html( (string) $step['title'] ) );
		}

		if ( '' !== (string) $step['description'] ) {
			printf( '<p>%s</p>', esc_html( (string) $step['description'] ) );
		}

		echo '</div>';
	}

	/**
	 * Whatever the last submission had to say about itself.
	 *
	 * A notice with role="alert", so it is announced when the page comes back
	 * rather than being something you have to go looking for.
	 *
	 * @return void
	 */
	private function errors(): void {
		$errors = $this->wizard->errors();

		if ( [] === $errors ) {
			return;
		}

		echo '<div class="notice notice-error onboarding__errors" role="alert" tabindex="-1">';

		foreach ( $errors as $message ) {
			printf( '<p>%s</p>', esc_html( $message ) );
		}

		echo '</div>';
	}

	/**
	 * The step itself.
	 *
	 * @param array<string, mixed> $step The step.
	 *
	 * @return void
	 */
	private function content( array $step ): void {
		switch ( (string) $step['type'] ) {
			case 'fields':
				$this->fields( $step );
				break;

			case 'content':
				$this->prose( $step );
				break;

			case 'callback':
				if ( is_callable( $step['render'] ) ) {
					call_user_func( $step['render'], $step, $this->wizard );
				}
				break;

			case 'sync':
				$this->sync( $step );
				break;

			default:
				/**
				 * Draw a step type this library does not know.
				 *
				 * @param array  $step   The step.
				 * @param Wizard $wizard The wizard.
				 *
				 * @since 2.0.0
				 */
				do_action( 'arraypress_onboarding_render_' . (string) $step['type'], $step, $this->wizard );
				break;
		}
	}

	/**
	 * A step of fields, drawn by the kit.
	 *
	 * @param array<string, mixed> $step The step.
	 *
	 * @return void
	 */
	private function fields( array $step ): void {
		$set = $this->wizard->fields_to_render( $step );

		if ( null === $set ) {
			return;
		}

		echo '<div class="onboarding__fields field-kit">';
		echo $set->render( 0, $this->wizard->errors() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the kit escapes as it builds.
		echo '</div>';
	}

	/**
	 * A step that only has something to say.
	 *
	 * @param array<string, mixed> $step The step.
	 *
	 * @return void
	 */
	private function prose( array $step ): void {
		if ( '' !== (string) $step['image'] ) {
			printf( '<img class="onboarding__image" src="%s" alt="">', esc_url( (string) $step['image'] ) );
		}

		if ( '' !== (string) $step['content'] ) {
			printf( '<div class="onboarding__prose">%s</div>', wp_kses_post( (string) $step['content'] ) );
		}

		$this->items( (array) $step['items'] );
		$this->links( (array) $step['links'] );
	}

	/**
	 * A list of things, each with an icon and a line or two.
	 *
	 * @param array<int, array<string, string>> $items The items.
	 *
	 * @return void
	 */
	private function items( array $items ): void {
		if ( [] === $items ) {
			return;
		}

		echo '<ul class="onboarding__items">';

		foreach ( $items as $item ) {
			echo '<li class="onboarding__item">';

			printf(
				'<span class="dashicons %s" aria-hidden="true"></span>',
				esc_attr( self::dashicon( (string) ( $item['icon'] ?? 'yes' ) ) )
			);

			echo '<span class="onboarding__item-text">';

			if ( '' !== (string) ( $item['title'] ?? '' ) ) {
				printf( '<strong>%s</strong>', esc_html( (string) $item['title'] ) );
			}

			if ( '' !== (string) ( $item['description'] ?? '' ) ) {
				printf( '<span>%s</span>', esc_html( (string) $item['description'] ) );
			}

			echo '</span></li>';
		}

		echo '</ul>';
	}

	/**
	 * Somewhere to go next.
	 *
	 * @param array<int, array<string, mixed>> $links The links.
	 *
	 * @return void
	 */
	private function links( array $links ): void {
		if ( [] === $links ) {
			return;
		}

		echo '<div class="onboarding__links">';

		foreach ( $links as $link ) {
			$external = ! empty( $link['external'] );

			printf(
				'<a class="button %s" href="%s"%s>%s%s</a>',
				$external ? 'button-secondary' : 'button-primary',
				esc_url( (string) ( $link['url'] ?? '' ) ),
				$external ? ' target="_blank" rel="noopener noreferrer"' : '',
				esc_html( (string) ( $link['label'] ?? '' ) ),
				$external
					// Said, not just drawn: a link that opens elsewhere and
					// does not say so is the classic one.
					? sprintf(
						'<span class="dashicons dashicons-external" aria-hidden="true"></span><span class="screen-reader-text">%s</span>',
						esc_html__( '(opens in a new tab)', 'arraypress' )
					)
					: ''
			);
		}

		echo '</div>';
	}

	/**
	 * A step that runs an import.
	 *
	 * The work is wp-inline-sync's; what is here is the container it draws
	 * into and the button that starts it.
	 *
	 * @param array<string, mixed> $step The step.
	 *
	 * @return void
	 */
	private function sync( array $step ): void {
		if ( '' === (string) $step['sync_id'] || ! function_exists( 'render_sync_button' ) ) {
			return;
		}

		echo '<div class="onboarding__sync"><div class="inline-sync-container"></div>';

		render_sync_button( (string) $step['sync_id'] );

		echo '</div>';
	}

	/**
	 * Back, Skip and Continue.
	 *
	 * @param string               $key  The current step's key.
	 * @param array<string, mixed> $step The step.
	 *
	 * @return void
	 */
	private function actions( string $key, array $step ): void {
		$labels   = (array) $this->wizard->get( 'labels', [] );
		$previous = $this->wizard->adjacent( $key, -1 );
		$next     = $this->wizard->adjacent( $key, 1 );
		$is_last  = '' === $next;

		echo '<div class="onboarding__actions">';

		echo '<div class="onboarding__actions-start">';

		if ( '' !== $previous ) {
			printf(
				'<a class="onboarding__back" href="%s">%s</a>',
				esc_url( $this->wizard->url( $previous ) ),
				esc_html( (string) $labels['previous'] )
			);
		}

		echo '</div><div class="onboarding__actions-end">';

		if ( ! empty( $step['skippable'] ) && ! $is_last ) {
			printf(
				'<a class="onboarding__skip" href="%s">%s</a>',
				esc_url( $this->wizard->url( $next ) ),
				esc_html( (string) ( '' !== (string) $step['skip_label'] ? $step['skip_label'] : $labels['skip'] ) )
			);
		}

		printf(
			'<button type="submit" class="button button-primary button-hero onboarding__next">%s</button>',
			esc_html( (string) ( $is_last ? $labels['finish'] : $labels['next'] ) )
		);

		echo '</div></div>';
	}

	/**
	 * The way out.
	 *
	 * @return void
	 */
	private function exit_link(): void {
		printf(
			'<p class="onboarding__exit"><a href="%s">%s</a></p>',
			esc_url( admin_url() ),
			esc_html( (string) ( (array) $this->wizard->get( 'labels', [] ) )['exit'] )
		);
	}
}
