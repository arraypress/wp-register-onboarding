<?php
/**
 * Renders Page Trait
 *
 * Handles the main wizard page layout including header, progress bar,
 * step wrapper, navigation buttons, and exit link.
 *
 * @package     ArrayPress\RegisterOnboarding
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterOnboarding\Traits;

trait RendersPage {

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

						<?php
						// Run before_render callback if provided
						if ( ! empty( $current_step['before_render'] ) && is_callable( $current_step['before_render'] ) ) {
							call_user_func( $current_step['before_render'], $current_step );
						}
						?>

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
							<?php elseif ( ! empty( $step['icon'] ) ) : ?>
								<span class="dashicons <?php echo esc_attr( $step['icon'] ); ?>"></span>
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
	 * Render navigation buttons (back, skip, next/finish)
	 *
	 * For sync steps, the Continue button starts disabled and is
	 * enabled by JavaScript when the sync completes.
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
		$is_sync   = $step['type'] === 'sync';

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
					        class="button button-primary onboarding-btn onboarding-btn--next"
						<?php if ( $is_sync ) : ?> disabled<?php endif; ?>>
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

}
