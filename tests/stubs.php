<?php
/**
 * WordPress stubs specific to an admin page that redirects.
 *
 * @package ArrayPress\RegisterOnboarding
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

// The asset loaders work out a URL by asking where the file sits relative to
// wp-content. The library really is under one during development, so pointing
// at it gives them a true answer rather than a contrived one.
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', dirname( __DIR__, 3 ) );
}

/**
 * A redirect, so a test can see where a submission went.
 *
 * Production code redirects and exits, which a test cannot follow. Throwing
 * instead stops the request at the same point and carries the destination
 * out, and satisfies the `never` return type for the same reason `exit` does.
 */
final class OnboardingRedirect extends RuntimeException {

	/**
	 * Where it went.
	 *
	 * @var string
	 */
	public string $url;

	/**
	 * Construct.
	 *
	 * @param string $url Where it went.
	 */
	public function __construct( string $url ) {
		parent::__construct( 'Redirected to ' . $url );

		$this->url = $url;
	}
}

/**
 * A wp_die(), likewise.
 */
final class OnboardingDied extends RuntimeException {}

/**
 * Reset every stubbed global between tests.
 *
 * @return void
 */
function ob_reset_globals(): void {
	$GLOBALS['ob_options']       = [];
	$GLOBALS['ob_transients']    = [];
	$GLOBALS['ob_actions']       = [];
	$GLOBALS['ob_filters']       = [];
	$GLOBALS['ob_menu']          = [];
	$GLOBALS['ob_removed_menu']  = [];
	$GLOBALS['ob_styles']        = [];
	$GLOBALS['ob_scripts']       = [];
	$GLOBALS['ob_caps']          = [ 'manage_options' ];
	$GLOBALS['ob_nonce_ok']      = true;
	$GLOBALS['ob_doing_ajax']    = false;
	$GLOBALS['_parent_pages']    = [];

	$_GET  = [];
	$_POST = [];

	// The registry is static, and so is the flag that stops it attaching its
	// hooks twice — which is right in a request and wrong across tests,
	// where the second test would find no hooks attached at all.
	if ( class_exists( 'ArrayPress\\RegisterOnboarding\\Onboarding' ) ) {
		( new ReflectionProperty( 'ArrayPress\\RegisterOnboarding\\Onboarding', 'wizards' ) )->setValue( null, [] );
		( new ReflectionProperty( 'ArrayPress\\RegisterOnboarding\\Onboarding', 'hooked' ) )->setValue( null, false );
	}
}

ob_reset_globals();

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default_value = false ) {
		return array_key_exists( $name, $GLOBALS['ob_options'] ) ? $GLOBALS['ob_options'][ $name ] : $default_value;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['ob_options'][ $name ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $name ) {
		if ( ! array_key_exists( $name, $GLOBALS['ob_options'] ) ) {
			return false;
		}

		unset( $GLOBALS['ob_options'][ $name ] );

		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		return $GLOBALS['ob_transients'][ $key ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $expiry = 0 ) {
		$GLOBALS['ob_transients'][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['ob_transients'][ $key ] );

		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['ob_actions'][ $hook ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['ob_filters'][ $hook ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$args ) {
		$GLOBALS['ob_actions']['fired'][] = $hook;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		return in_array( $capability, (array) $GLOBALS['ob_caps'], true );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $args, $url = '' ) {
		$args = is_array( $args ) ? $args : [ $args => $url ];

		$parts = explode( '?', (string) $url, 2 );
		$query = [];

		if ( isset( $parts[1] ) ) {
			parse_str( $parts[1], $query );
		}

		return $parts[0] . '?' . http_build_query( array_merge( $query, $args ) );
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $url, $status = 302 ) {
		throw new OnboardingRedirect( (string) $url );
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', $title = '', $args = [] ) {
		throw new OnboardingDied( is_string( $message ) ? $message : 'died' );
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
		if ( ! $GLOBALS['ob_nonce_ok'] ) {
			throw new OnboardingDied( 'nonce' );
		}

		$GLOBALS['ob_nonce_checked'] = $action;

		return 1;
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
		$field = sprintf( '<input type="hidden" name="%s" value="nonce">', $name );

		if ( $display ) {
			echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $field;
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null ) {
		if ( ! current_user_can( $capability ) ) {
			return false;
		}

		$GLOBALS['ob_menu'][ $menu_slug ]       = compact( 'parent_slug', 'page_title', 'menu_title', 'capability', 'callback' );
		$GLOBALS['_parent_pages'][ $menu_slug ] = $parent_slug;

		return 'toplevel_page_' . $menu_slug;
	}
}

if ( ! function_exists( 'remove_submenu_page' ) ) {
	function remove_submenu_page( $parent_slug, $menu_slug ) {
		$GLOBALS['ob_removed_menu'][] = $menu_slug;

		return false;
	}
}

if ( ! function_exists( 'sanitize_html_class' ) ) {
	function sanitize_html_class( $class, $fallback = '' ) {
		$class = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );

		return '' === $class ? $fallback : $class;
	}
}

if ( ! function_exists( 'wp_doing_ajax' ) ) {
	function wp_doing_ajax() {
		return (bool) $GLOBALS['ob_doing_ajax'];
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url, $protocols = null, $context = 'display' ) {
		return str_replace( '&', '&#038;', (string) $url );
	}
}

if ( ! function_exists( 'arraypress_enqueue_composer_style' ) ) {
	function arraypress_enqueue_composer_style( $handle, $calling_file, $file, $deps = [], $ver = false, $media = 'all' ) {
		$GLOBALS['ob_styles'][ $handle ] = $file;

		return true;
	}
}

if ( ! function_exists( 'arraypress_enqueue_composer_script' ) ) {
	function arraypress_enqueue_composer_script( $handle, $calling_file, $file, $deps = [], $ver = false, $args = true ) {
		$GLOBALS['ob_scripts'][ $handle ] = $file;

		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle, $src = '', $deps = [], $ver = false, $media = 'all' ) {
		$GLOBALS['ob_styles'][ $handle ] = $src;
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle, $src = '', $deps = [], $ver = false, $args = [] ) {
		$GLOBALS['ob_scripts'][ $handle ] = $src;
	}
}

if ( ! function_exists( 'wp_register_style' ) ) {
	function wp_register_style( $handle, $src = '', $deps = [], $ver = false, $media = 'all' ) {
		return true;
	}
}

if ( ! function_exists( 'wp_register_script' ) ) {
	function wp_register_script( $handle, $src = '', $deps = [], $ver = false, $args = [] ) {
		return true;
	}
}

if ( ! function_exists( 'wp_add_inline_script' ) ) {
	function wp_add_inline_script( $handle, $data, $position = 'after' ) {
		return true;
	}
}

if ( ! function_exists( 'wp_scripts' ) ) {
	/*
	 * The kit adds the handles a screen's fields need to its own script's
	 * dependencies, which means reading the registered script back. Nothing
	 * here asserts on the result, so a registry that knows nothing is enough
	 * — but it has to answer rather than not exist.
	 */
	function wp_scripts() {
		return new class() {

			/**
			 * A registered script, or false.
			 *
			 * @param string $handle The handle.
			 * @param string $status Which list to look in.
			 *
			 * @return false
			 */
			public function query( $handle, $status = 'registered' ) {
				return false;
			}
		};
	}
}

if ( ! function_exists( 'wp_style_is' ) ) {
	function wp_style_is( $handle, $status = 'enqueued' ) {
		return isset( $GLOBALS['ob_styles'][ $handle ] );
	}
}

if ( ! function_exists( 'wp_script_is' ) ) {
	function wp_script_is( $handle, $status = 'enqueued' ) {
		return isset( $GLOBALS['ob_scripts'][ $handle ] );
	}
}

/*
 * Enough of the path helpers for the asset loaders to work out a URL for a
 * file inside vendor/. They only ever concatenate and compare, so a stub that
 * agrees with itself is enough.
 */
if ( ! function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( $path ) {
		$path = str_replace( '\\', '/', (string) $path );

		return preg_replace( '|(?<=.)/+|', '/', $path ) ?? $path;
	}
}

if ( ! function_exists( 'site_url' ) ) {
	function site_url( $path = '', $scheme = null ) {
		return 'https://example.test/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'content_url' ) ) {
	function content_url( $path = '' ) {
		return 'https://example.test/wp-content/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'plugins_url' ) ) {
	function plugins_url( $path = '', $plugin = '' ) {
		return 'https://example.test/wp-content/plugins/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' ) . '/';
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * The parts of WP_Error a validator returns.
	 */
	class WP_Error {

		/**
		 * The message.
		 *
		 * @var string
		 */
		private string $message;

		/**
		 * Construct.
		 *
		 * @param string $code    Its code.
		 * @param string $message Its message.
		 */
		public function __construct( string $code = '', string $message = '' ) {
			$this->message = $message;
		}

		/**
		 * The message.
		 *
		 * @return string
		 */
		public function get_error_message(): string {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'remove_all_actions' ) ) {
	function remove_all_actions( $hook, $priority = false ) {
		unset( $GLOBALS['ob_actions'][ $hook ] );

		return true;
	}
}
