<?php
/**
 * Renders Steps Trait
 *
 * Handles rendering of individual step types: welcome, fields,
 * checklist, complete, callback, and sync.
 *
 * @package     ArrayPress\RegisterOnboarding
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterOnboarding\Traits;

trait RendersSteps {

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

			case 'sync':
				self::render_sync_step( $step, $config );
				break;

			default:
				do_action( 'arraypress_onboarding_render_step_' . $step['type'], $step, $config );
				break;
		}
	}

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

	/**
	 * Render a sync step
	 *
	 * Registers a temporary inline sync operation targeting the wizard's
	 * own admin page, then renders the sync trigger button. The inline-sync
	 * library handles the progress bar, REST calls, and completion UI.
	 *
	 * Requires the arraypress/wp-inline-sync package.
	 *
	 * @param array $step   Step configuration.
	 * @param array $config Wizard configuration.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function render_sync_step( array $step, array $config ): void {
		$sync_config = $step['sync'] ?? [];

		if ( empty( $sync_config['data_callback'] ) || empty( $sync_config['process_callback'] ) ) {
			return;
		}

		// Build a unique sync ID from the wizard + step
		$sync_id = sanitize_key( self::$current_wizard . '_' . ( $step['_key'] ?? 'sync' ) );

		// Determine the hook suffix for this wizard page
		$hook_suffix = 'admin_page_' . $config['menu_slug'];

		// Register the sync via the inline-sync library
		if ( function_exists( 'register_sync' ) ) {
			register_sync( $sync_id, [
				'hook_suffix'        => $hook_suffix,
				'capability'         => $config['capability'],
				'title'              => $sync_config['title'] ?? $step['title'] ?? '',
				'button_label'       => $sync_config['button_label'] ?? __( 'Start Sync', 'arraypress' ),
				'button_class'       => 'button button-primary',
				'container'          => '.onboarding-sync-container',
				'reload_on_complete' => false,
				'data_callback'      => $sync_config['data_callback'],
				'process_callback'   => $sync_config['process_callback'],
				'name_callback'      => $sync_config['name_callback'] ?? null,
			] );
		}

		?>
		<div class="onboarding-sync">
			<div class="onboarding-sync-container"></div>
			<div class="onboarding-sync__actions">
				<?php
				if ( function_exists( 'render_sync_button' ) ) {
					render_sync_button( $sync_id );
				} else {
					?>
					<p class="onboarding-sync__missing">
						<?php esc_html_e( 'The inline sync library is not installed.', 'arraypress' ); ?>
					</p>
					<?php
				}
				?>
			</div>
		</div>
		<?php
	}

}
