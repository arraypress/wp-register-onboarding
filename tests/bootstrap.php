<?php
/**
 * PHPUnit bootstrap.
 *
 * @package ArrayPress\RegisterOnboarding
 */

declare( strict_types=1 );

require_once __DIR__ . '/stubs.php';

/*
 * The kit's stubs cover the field layer. Ours are required first so that
 * where the two overlap this file's version wins, since every stub is
 * guarded by function_exists().
 */
require_once dirname( __DIR__ ) . '/vendor/arraypress/wp-field-kit/tests/stubs.php';

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
