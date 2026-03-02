# WordPress Onboarding Wizard

A declarative system for registering WordPress admin onboarding wizards. Guide users through multi-step plugin setup
flows with built-in field rendering, validation, auto-saving, and progress tracking.

## Installation

```bash
composer require arraypress/wp-register-onboarding
```

## Quick Start

```php
register_onboarding( 'my-plugin-setup', [
    'page_title' => __( 'Setup Wizard', 'myplugin' ),
    'menu_slug'  => 'my-plugin-setup',
    'logo'       => plugin_dir_url( __FILE__ ) . 'assets/logo.png',

    'steps' => [
        'welcome' => [
            'title'       => __( 'Welcome', 'myplugin' ),
            'type'        => 'welcome',
            'description' => __( 'Let\'s get your plugin set up.', 'myplugin' ),
        ],
        'settings' => [
            'title'  => __( 'Settings', 'myplugin' ),
            'type'   => 'fields',
            'fields' => [
                'currency' => [
                    'type'    => 'select',
                    'label'   => __( 'Currency', 'myplugin' ),
                    'options' => 'currencies',
                    'default' => 'USD',
                    'option'  => 'myplugin_currency',
                ],
            ],
        ],
        'done' => [
            'title'       => __( 'All Done!', 'myplugin' ),
            'type'        => 'complete',
            'description' => __( 'Your plugin is ready.', 'myplugin' ),
        ],
    ],
] );
```

That's it — the hidden admin page, step rendering, progress bar, navigation, validation, and auto-saving to `wp_options`
are handled automatically.

## Configuration Reference

```php
register_onboarding( 'wizard_id', [
    // Menu Registration
    'page_title'  => 'Setup Wizard',         // Page title tag text
    'menu_title'  => 'Setup',                // Menu item text
    'menu_slug'   => 'my-setup',             // Admin page slug
    'parent_slug' => '',                     // Parent menu (empty = hidden page)
    'capability'  => 'manage_options',       // Capability required

    // Header
    'logo'         => '',                    // URL to logo image
    'header_title' => '',                    // Fallback title (if no logo)

    // Behavior
    'redirect'          => false,            // Auto-redirect on activation
    'completed_option'  => '',               // wp_options key for completion (auto-generated)

    // Steps
    'steps' => [],                           // Step definitions (see below)

    // Display
    'body_class' => '',                      // Additional CSS body class

    // Colors (override any CSS custom property)
    'colors' => [],                          // key => value pairs (see Colors section)

    // Labels
    'labels' => [
        'next'     => 'Continue',
        'previous' => 'Back',
        'skip'     => 'Skip this step',
        'finish'   => 'Finish Setup',
        'exit'     => 'Exit Setup',
    ],
] );
```

## Step Types

### Welcome

An introductory screen with an optional image and feature highlights.

```php
'welcome' => [
    'title'       => __( 'Welcome to MyPlugin', 'myplugin' ),
    'type'        => 'welcome',
    'description' => __( 'We\'ll have you set up in under 2 minutes.', 'myplugin' ),
    'image'       => plugin_dir_url( __FILE__ ) . 'assets/welcome.svg',
    'features'    => [
        [
            'icon'        => 'dashicons-cart',
            'title'       => __( 'Accept Payments', 'myplugin' ),
            'description' => __( 'Start selling in minutes with Stripe.', 'myplugin' ),
        ],
        [
            'icon'        => 'dashicons-chart-area',
            'title'       => __( 'Track Revenue', 'myplugin' ),
            'description' => __( 'Real-time analytics and reports.', 'myplugin' ),
        ],
    ],
],
```

### Fields

A form step with built-in field types that auto-save to `wp_options`.

```php
'settings' => [
    'title'       => __( 'Store Settings', 'myplugin' ),
    'type'        => 'fields',
    'description' => __( 'Configure the basics.', 'myplugin' ),
    'fields'      => [
        'currency' => [
            'type'    => 'select',
            'label'   => __( 'Currency', 'myplugin' ),
            'options' => 'currencies',         // Built-in preset
            'default' => 'USD',
            'option'  => 'myplugin_currency',  // wp_options key
        ],
        'email' => [
            'type'        => 'email',
            'label'       => __( 'Notification Email', 'myplugin' ),
            'default'     => '{admin_email}',  // Resolves automatically
            'option'      => 'myplugin_email',
            'placeholder' => __( 'you@example.com', 'myplugin' ),
            'validate'    => 'is_email',
        ],
        'test_mode' => [
            'type'        => 'toggle',
            'label'       => __( 'Test Mode', 'myplugin' ),
            'description' => __( 'Enable test mode for development.', 'myplugin' ),
            'default'     => true,
            'option'      => 'myplugin_test_mode',
        ],
    ],
],
```

**Supported field types:** `text`, `email`, `url`, `number`, `textarea`, `select`, `radio`, `toggle`

### Checklist

A list of toggleable features that auto-save to individual `wp_options` keys.

```php
'features' => [
    'title'       => __( 'Enable Features', 'myplugin' ),
    'type'        => 'checklist',
    'description' => __( 'Choose which features to enable.', 'myplugin' ),
    'items'       => [
        [
            'label'       => __( 'Email Receipts', 'myplugin' ),
            'description' => __( 'Send customers a receipt after purchase.', 'myplugin' ),
            'option'      => 'myplugin_email_receipts',
            'default'     => true,
        ],
        [
            'label'       => __( 'PDF Invoices', 'myplugin' ),
            'description' => __( 'Attach invoices to order emails.', 'myplugin' ),
            'option'      => 'myplugin_pdf_invoices',
            'default'     => false,
        ],
    ],
],
```

### Complete

A success screen with next-step links.

```php
'done' => [
    'title'       => __( 'You\'re All Set!', 'myplugin' ),
    'type'        => 'complete',
    'description' => __( 'Your store is ready to accept payments.', 'myplugin' ),
    'links'       => [
        [
            'label' => __( 'Create Your First Product', 'myplugin' ),
            'url'   => admin_url( 'post-new.php?post_type=product' ),
        ],
        [
            'label'    => __( 'View Documentation', 'myplugin' ),
            'url'      => 'https://docs.myplugin.com/',
            'external' => true,
        ],
    ],
    'redirect' => admin_url( 'edit.php?post_type=product' ), // Optional redirect after finish
],
```

### Callback

A fully custom step where you control the markup.

```php
'stripe' => [
    'title'       => __( 'Connect Stripe', 'myplugin' ),
    'type'        => 'callback',
    'description' => __( 'Link your Stripe account.', 'myplugin' ),
    'render'      => 'myplugin_render_stripe_step',
    'validate'    => 'myplugin_validate_stripe_step',
    'save'        => 'myplugin_save_stripe_step',
    'skippable'   => true,
    'skip_label'  => __( 'I\'ll do this later', 'myplugin' ),
],
```

The library handles the step card, header, navigation, and form. Your `render` callback outputs the content area markup.
Your `validate` callback receives `$_POST` data and returns `true` or a `WP_Error`. Your `save` callback handles
persisting the data however you need.

## Conditional Steps

Show or hide steps based on runtime conditions:

```php
'payments' => [
    'title'   => __( 'Payment Setup', 'myplugin' ),
    'type'    => 'fields',
    'show_if' => function() {
        return ! get_option( 'myplugin_stripe_key' );
    },
    'fields'  => [ ... ],
],
```

When `show_if` returns `false`, the step is excluded from the progress bar and navigation.

## Skippable Steps

```php
'optional' => [
    'title'      => __( 'Optional Config', 'myplugin' ),
    'type'       => 'fields',
    'skippable'  => true,
    'skip_label' => __( 'I\'ll configure this later', 'myplugin' ),
    'fields'     => [ ... ],
],
```

A "Skip" link appears next to the Continue button. Skipping bypasses validation and saving.

## Field Validation

Per-field validation callbacks receive the submitted value and return `true` or a `WP_Error`:

```php
'fields' => [
    'api_key' => [
        'type'     => 'text',
        'label'    => __( 'API Key', 'myplugin' ),
        'option'   => 'myplugin_api_key',
        'validate' => function( $value ) {
            if ( strlen( $value ) < 10 ) {
                return new WP_Error( 'invalid_key', 'API key must be at least 10 characters.' );
            }
            return true;
        },
    ],
],
```

You can also use built-in PHP functions: `'validate' => 'is_email'`

## Option Presets

String shorthand for common select options:

| Preset       | Description                              |
|--------------|------------------------------------------|
| `currencies` | 30 major world currencies with symbols   |
| `countries`  | 50 countries by Stripe availability      |
| `us_states`  | All 50 US states + DC                    |
| `timezones`  | All PHP timezone identifiers             |

```php
'currency' => [
    'type'    => 'select',
    'options' => 'currencies',  // Instead of a raw array
],
```

Custom presets via filter:

```php
add_filter( 'arraypress_onboarding_preset_my_options', function() {
    return [ 'a' => 'Option A', 'b' => 'Option B' ];
} );
```

## Activation Redirect

Auto-redirect users to the wizard on plugin activation:

```php
// In register_onboarding config:
'redirect' => true,

// In your plugin's activation hook:
register_activation_hook( __FILE__, function() {
    \ArrayPress\RegisterOnboarding\Manager::set_redirect( 'my-plugin-setup' );
} );
```

The redirect fires once, skips bulk activations, and won't redirect if the wizard has already been completed.

## Completion Tracking

The wizard auto-saves a completion timestamp to `wp_options` when the last step is submitted.

```php
// Check if completed
\ArrayPress\RegisterOnboarding\Manager::is_completed( 'my-plugin-setup' );

// Reset to allow re-running
\ArrayPress\RegisterOnboarding\Manager::reset( 'my-plugin-setup' );
```

## Colors

Override CSS custom properties via the `colors` config:

```php
'colors' => [
    'accent'       => '#6366f1',
    'accent_hover' => '#4f46e5',
    'accent_light' => '#eef2ff',
],
```

Available keys: `accent`, `accent_hover`, `accent_light`, `success`, `error`, `text_primary`, `text_secondary`,
`text_muted`, `border`, `border_light`, `bg_white`, `bg_subtle`, `bg_page`, `radius`, `radius_lg`

## Hooks

### Actions

```php
// Before/after wizard renders
add_action( 'arraypress_before_render_onboarding', fn( $id, $config ) => null, 10, 2 );
add_action( 'arraypress_before_render_onboarding_{wizard_id}', fn( $config ) => null );
add_action( 'arraypress_after_render_onboarding', fn( $id, $config ) => null, 10, 2 );
add_action( 'arraypress_after_render_onboarding_{wizard_id}', fn( $config ) => null );

// When wizard is completed
add_action( 'arraypress_onboarding_completed', fn( $id, $config ) => null, 10, 2 );
add_action( 'arraypress_onboarding_completed_{wizard_id}', fn( $config ) => null );

// Custom step type rendering
add_action( 'arraypress_onboarding_render_step_{type}', fn( $step, $config ) => null, 10, 2 );
```

### Filters

```php
// Custom option presets
add_filter( 'arraypress_onboarding_preset_{name}', fn() => [] );
```

## Body Classes

- `onboarding-wizard` — added to all wizard pages
- `onboarding-wizard-{id}` — wizard-specific class
- Custom class from the `body_class` config option

## Requirements

- PHP 7.4+
- WordPress 5.0+
- arraypress/wp-composer-assets

## License

GPL-2.0-or-later

## Credits

Created by [David Sherlock](https://davidsherlock.com) at [ArrayPress](https://arraypress.com).
