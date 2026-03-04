<?php
/**
 * Renders Fields Trait
 *
 * Handles rendering of individual field types: text, email, url,
 * number, textarea, select, radio, and toggle.
 *
 * @package     ArrayPress\RegisterOnboarding
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterOnboarding\Traits;

trait RendersFields {

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

}
