<?php
/**
 * Storage
 *
 * @package     ArrayPress\RegisterOnboarding
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       2.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterOnboarding\Support;

use ArrayPress\FieldKit\Contracts\Context;
use ArrayPress\FieldKit\Field;

/**
 * Where a wizard's answers go.
 *
 * A setup wizard is the one screen in a plugin that writes settings it does
 * not own the storage for. It runs before anything else and it fills in
 * whatever the plugin already reads — which is rarely one place.
 *
 * So there are three arrangements, and this is the one for two of them. A
 * field goes to its own option, named by the field's key or by an `option` of
 * its own; or, given a callback pair, to whatever the plugin already uses.
 * The third — every field in one option — is the kit's own OptionContext,
 * because that is exactly what it does.
 */
final class Storage implements Context {

	/**
	 * Reads a value.
	 *
	 * @var callable|null
	 */
	private $reader;

	/**
	 * Writes a value.
	 *
	 * @var callable|null
	 */
	private $writer;

	/**
	 * Construct.
	 *
	 * @param callable|null $reader Reads a value, given a key and a default.
	 * @param callable|null $writer Writes a value, given a key and a value.
	 */
	public function __construct( ?callable $reader = null, ?callable $writer = null ) {
		$this->reader = $reader;
		$this->writer = $writer;
	}

	/**
	 * Read a field's stored value.
	 *
	 * @param int|string $object_id Ignored. A wizard has one set of answers.
	 * @param Field      $field     The field.
	 *
	 * @return mixed
	 */
	public function read( int|string $object_id, Field $field ): mixed {
		$key = self::key( $field );

		if ( null !== $this->reader ) {
			return ( $this->reader )( $key, null );
		}

		// null rather than false, so the kit can tell "never saved" from
		// "saved as empty" and apply the configured default to the first
		// and not the second.
		return get_option( $key, null );
	}

	/**
	 * Write a field's sanitized value.
	 *
	 * @param int|string $object_id Ignored.
	 * @param Field      $field     The field.
	 * @param mixed      $value     Sanitized, unslashed value.
	 *
	 * @return void
	 */
	public function write( int|string $object_id, Field $field, mixed $value ): void {
		$key = self::key( $field );

		if ( null !== $this->writer ) {
			( $this->writer )( $key, $value );

			return;
		}

		update_option( $key, $value );
	}

	/**
	 * Drop a field's value.
	 *
	 * A field whose conditions are not met is deleted rather than stored, so
	 * an answer to a question that stopped being asked does not survive.
	 *
	 * @param int|string $object_id Ignored.
	 * @param Field      $field     The field.
	 *
	 * @return void
	 */
	public function delete( int|string $object_id, Field $field ): void {
		$key = self::key( $field );

		if ( null !== $this->writer ) {
			( $this->writer )( $key, null );

			return;
		}

		delete_option( $key );
	}

	/**
	 * Where one field is stored.
	 *
	 * Its own `option` if it names one, and its key otherwise — so a wizard
	 * whose field keys already match the plugin's option names says nothing,
	 * and one whose keys are short says it once per field.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	private static function key( Field $field ): string {
		$option = (string) $field->get( 'option', '' );

		return '' === $option ? $field->key() : $option;
	}
}
