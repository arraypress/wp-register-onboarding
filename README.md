# WordPress Onboarding Wizard

A declarative system for registering WordPress admin onboarding wizards. Guide users through multi-step plugin setup
flows with built-in field rendering, validation, auto-saving, Select2 for searchable dropdowns, field dependencies,
and progress tracking.

## Installation

```bash
composer require arraypress/wp-register-onboarding
```

Ships with `arraypress/wp-currencies` and `arraypress/wp-countries` for built-in option presets.

## Quick Start

```php
register_onboarding( 'my-plugin-setup', [
    'page_title' => 'Setup Wizard',
    'menu_slug'  => 'my-plugin-setup',
    'logo'       => plugin_dir_url( __FILE__ ) . 'assets/logo.png',

    'steps' => [
        'welcome' => [
            'title'       => 'Welcome',
            'type'        => 'welcome',
            'description' => 'Let\'s get your plugin set up.',
        ],
        'settings' => [
            'title'  => 'Settings',
            'type'   => 'fields',
            'fields' => [
                'currency' => [
                    'type'    => 'select',
                    'label'   => 'Currency',
                    'options' => 'currencies',
                    'default' => 'USD',
                    'option'  => 'myplugin_currency',
                ],
            ],
        ],
        'done' => [
            'title' => 'All Done!',
            'type'  => 'complete',
        ],
    ],
] );
```

## Configuration Reference

```php
register_onboarding( 'wizard_id', [
    // Menu Registration
    'page_title'  => 'Setup Wizard',
    'menu_slug'   => 'my-setup',
    'parent_slug' => '',
    'capability'  => 'manage_options',

    // Header
    'logo'         => '',
    'header_title' => '',

    // Behavior
    'redirect'         => false,
    'completed_option' => '',

    // Storage (optional — defaults to get_option/update_option)
    'storage' => [
        'get'    => function( $key, $default ) { ... },
        'update' => function( $key, $value ) { ... },
    ],

    // Steps
    'steps' => [],

    // Display
    'body_class' => '',
    'colors'     => [],

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

## Storage

By default, each field's `option` key maps to its own `wp_options` row via `get_option` / `update_option`.

To use a custom storage backend (like `arraypress/wp-register-setting-fields`), provide `get` and `update` callbacks:

```php
'storage' => [
    'get'    => function( string $key, $default = null ) {
        return get_setting_field_value( 'my_settings', $key, $default );
    },
    'update' => function( string $key, $value ) {
        return update_setting_field_value( 'my_settings', $key, $value );
    },
],
```

When storage callbacks are defined, the field's `option` key becomes a field key within that system rather than a
standalone `wp_options` key.

## Step Types

### Welcome

```php
'welcome' => [
    'title'       => 'Welcome to MyPlugin',
    'type'        => 'welcome',
    'description' => 'We\'ll have you set up in under 2 minutes.',
    'image'       => plugin_dir_url( __FILE__ ) . 'assets/welcome.svg',
    'features'    => [
        [ 'icon' => 'dashicons-cart', 'title' => 'Accept Payments', 'description' => '...' ],
    ],
],
```

### Fields

```php
'settings' => [
    'title'  => 'Store Settings',
    'type'   => 'fields',
    'fields' => [
        'currency' => [
            'type'    => 'select',
            'label'   => 'Currency',
            'options' => 'currencies',
            'default' => 'USD',
            'option'  => 'myplugin_currency',
        ],
        'email' => [
            'type'        => 'email',
            'label'       => 'Email',
            'default'     => '{admin_email}',
            'option'      => 'myplugin_email',
            'validate'    => 'is_email',
        ],
    ],
],
```

**Supported field types:** `text`, `email`, `url`, `number`, `textarea`, `select`, `radio`, `toggle`

Select fields with more than 10 options automatically get Select2 search. Override with `'searchable' => true/false`.

### Checklist

```php
'features' => [
    'title' => 'Enable Features',
    'type'  => 'checklist',
    'items' => [
        [ 'label' => 'Email Receipts', 'description' => '...', 'option' => 'myplugin_receipts', 'default' => true ],
    ],
],
```

### Complete

```php
'done' => [
    'title' => 'You\'re All Set!',
    'type'  => 'complete',
    'links' => [
        [ 'label' => 'Create Product', 'url' => admin_url( '...' ) ],
        [ 'label' => 'View Docs', 'url' => 'https://...', 'external' => true ],
    ],
],
```

### Callback

```php
'custom' => [
    'title'    => 'Connect Stripe',
    'type'     => 'callback',
    'render'   => 'my_render_callback',
    'validate' => 'my_validate_callback',
    'save'     => 'my_save_callback',
],
```

## Field Dependencies

Fields can be shown/hidden or have their attributes swapped based on another field's value:

```php
'fields' => [
    'test_mode' => [
        'type'    => 'toggle',
        'label'   => 'Test Mode',
        'default' => true,
        'option'  => 'myplugin_test_mode',
    ],
    'stripe_key' => [
        'type'        => 'text',
        'label'       => 'Stripe Key',
        'placeholder' => 'pk_live_...',
        'option'      => 'myplugin_stripe_key',
        'depends'     => [
            'field'    => 'test_mode',
            'operator' => '==',
            'value'    => '1',
            'action'   => 'swap',
            'attrs'    => [
                'placeholder' => 'pk_test_...',
                'help'        => 'Using test mode — no real charges.',
            ],
            'attrs_alt' => [
                'placeholder' => 'pk_live_...',
                'help'        => 'Live mode — real charges will apply.',
            ],
        ],
    ],
],
```

### Dependency Options

| Key          | Description                                                   |
|--------------|---------------------------------------------------------------|
| `field`      | Source field key to watch                                     |
| `operator`   | `==`, `!=`, `in`, `not_in` (default: `==`)                   |
| `value`      | Value to compare (string, or array for `in`/`not_in`)        |
| `action`     | `show` = toggle visibility, `swap` = swap attributes only    |
| `attrs`      | Attributes to apply when condition is **met**                 |
| `attrs_alt`  | Attributes to apply when condition is **not met**             |

Swappable attributes: `placeholder`, `help`, `label`

## Option Presets

String shorthand for common select options:

| Preset       | Source                           | Count |
|--------------|----------------------------------|-------|
| `currencies` | `arraypress/wp-currencies`       | 136   |
| `countries`  | `arraypress/wp-countries`        | 249   |
| `timezones`  | PHP `timezone_identifiers_list`  | ~400  |

Custom presets via filter:

```php
add_filter( 'arraypress_onboarding_preset_my_options', function() {
    return [ 'a' => 'Option A', 'b' => 'Option B' ];
} );
```

## Select2 for Long Lists

Selects with more than 10 options automatically get Select2 for search and filtering.
Control it per field with the `searchable` flag:

```php
'currency' => [
    'type'       => 'select',
    'options'    => 'currencies',
    'searchable' => true,   // Force Select2 on
],
'small_list' => [
    'type'       => 'select',
    'options'    => [ 'a' => 'A', 'b' => 'B' ],
    'searchable' => false,  // Force Select2 off
],
```

## Custom Storage

By default, each field's `option` key maps to an individual `wp_options` row. To store everything in a
single serialized array (e.g. via `arraypress/wp-register-setting-fields`), provide `storage` callbacks:

```php
register_onboarding( 'my-plugin-setup', [
    'storage' => [
        'get'    => fn( $key, $default ) => get_setting_field_value( 'my_plugin', $key, $default ),
        'update' => fn( $key, $value )   => update_setting_field_value( 'my_plugin', $key, $value ),
    ],
    'steps' => [ ... ],
] );
```

When `storage` callbacks are set, the field `option` key becomes a key within your storage system rather
than a standalone `wp_options` key. The library calls `get` when pre-populating fields and `update` on save.

## Field Dependencies

Show/hide fields or swap attributes based on another field's value:

```php
'stripe_key' => [
    'type'        => 'text',
    'label'       => 'Stripe Key',
    'placeholder' => 'pk_test_...',
    'help'        => 'Test mode — no real charges.',
    'depends'     => [
        'field'    => 'test_mode',       // Source field key
        'operator' => '==',              // ==, !=, in, not_in
        'value'    => '1',               // Value to match
        'action'   => 'show',            // 'show' or 'swap'
        'attrs'    => [                  // Applied when condition met
            'placeholder' => 'pk_test_...',
            'help'        => 'Test mode — no real charges.',
        ],
        'attrs_alt' => [                 // Applied when condition NOT met
            'placeholder' => 'pk_live_...',
            'help'        => 'Live mode — real charges.',
        ],
    ],
],
```

The `action` controls behavior: `show` hides the field when the condition is false, `swap` keeps it visible
but swaps attributes. Swappable attributes: `placeholder`, `help`, `label`.

## Activation Redirect

```php
'redirect' => true,

register_activation_hook( __FILE__, function() {
    \ArrayPress\RegisterOnboarding\Manager::set_redirect( 'my-plugin-setup' );
} );
```

## Completion Tracking

```php
\ArrayPress\RegisterOnboarding\Manager::is_completed( 'my-plugin-setup' );
\ArrayPress\RegisterOnboarding\Manager::reset( 'my-plugin-setup' );
```

## Colors

```php
'colors' => [
    'accent'       => '#6366f1',
    'accent_hover' => '#4f46e5',
    'accent_light' => '#eef2ff',
],
```

Available: `accent`, `accent_hover`, `accent_light`, `success`, `error`, `text_primary`, `text_secondary`,
`text_muted`, `border`, `border_light`, `bg_white`, `bg_subtle`, `bg_page`, `radius`, `radius_lg`

## Hooks

```php
do_action( 'arraypress_before_render_onboarding', $id, $config );
do_action( 'arraypress_after_render_onboarding', $id, $config );
do_action( 'arraypress_onboarding_completed', $id, $config );
do_action( 'arraypress_onboarding_render_step_{type}', $step, $config );
add_filter( 'arraypress_onboarding_preset_{name}', fn() => [] );
```

## Requirements

- PHP 7.4+
- WordPress 5.0+
- arraypress/wp-composer-assets
- arraypress/wp-currencies
- arraypress/wp-countries

## License

GPL-2.0-or-later

## Credits

Created by [David Sherlock](https://davidsherlock.com) at [ArrayPress](https://arraypress.com).
