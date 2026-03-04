# WordPress Onboarding Wizard

A declarative system for registering WordPress admin onboarding wizards. Guide users through multi-step plugin setup
flows with built-in field rendering, validation, auto-saving, Select2 for searchable dropdowns, field dependencies,
conditional steps, sync/import integration, confetti celebrations, and progress tracking.

## Installation

```bash
composer require arraypress/wp-register-onboarding
```

Ships with `arraypress/wp-currencies` and `arraypress/wp-countries` for built-in option presets.

## Quick Start

```php
register_onboarding( 'my-plugin-setup', [
    'header_title' => 'My Plugin Setup',
    'menu_slug'    => 'my-plugin-setup',
    'logo'         => plugin_dir_url( __FILE__ ) . 'assets/logo.png',

    'steps' => [
        'welcome' => [
            'title'       => 'Welcome',
            'type'        => 'welcome',
            'icon'        => 'dashicons-admin-home',
            'description' => 'Let\'s get your plugin set up.',
        ],
        'settings' => [
            'title'  => 'Settings',
            'type'   => 'fields',
            'icon'   => 'dashicons-admin-settings',
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
            'title'    => 'All Done!',
            'type'     => 'complete',
            'icon'     => 'dashicons-flag',
            'confetti' => true,
        ],
    ],
] );
```

## Configuration Reference

```php
register_onboarding( 'wizard-id', [
    // Menu Registration
    'page_title'  => 'Setup Wizard',     // Browser tab title (auto-set from header_title)
    'menu_title'  => 'Setup Wizard',     // Sidebar label when parent_slug is set (auto-set from page_title)
    'menu_slug'   => 'my-setup',         // URL slug (auto-set from wizard ID)
    'parent_slug' => '',                 // Empty = hidden page, or parent menu slug
    'capability'  => 'manage_options',

    // Header
    'logo'         => '',                // URL to logo image (displayed instead of title)
    'header_title' => '',                // Text header (used when no logo)

    // Behavior
    'redirect'           => true,        // Auto-redirect to wizard on plugin activation
    'completed_redirect' => '',          // URL to redirect to after wizard completion

    // Value Callbacks (optional — defaults to get_option/update_option)
    'get_callback'    => function( string $key, $default ) { ... },
    'update_callback' => function( string $key, $value ) { ... },

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

### Auto-Generated Defaults

Several configuration keys are auto-generated when not provided:

| Key                | Generated From                    | Example                     |
|--------------------|-----------------------------------|-----------------------------|
| `page_title`       | Falls back to "Setup Wizard"      | `Setup Wizard`              |
| `menu_title`       | Falls back to `page_title`        | `Setup Wizard`              |
| `header_title`     | Falls back to `page_title`        | `Setup Wizard`              |
| `menu_slug`        | Sanitized wizard ID               | `my-plugin-setup`           |
| `completed_option` | Wizard ID with hyphens normalized | `my_plugin_setup_completed` |

The `completed_option` key is stored in `wp_options` as a timestamp when the wizard finishes. You rarely
need to set this manually.

## Step Types

### Welcome

An introductory step with an optional image and feature highlights.

```php
'welcome' => [
    'title'       => 'Welcome to MyPlugin',
    'type'        => 'welcome',
    'icon'        => 'dashicons-admin-home',
    'description' => 'We\'ll have you set up in under 2 minutes.',
    'image'       => plugin_dir_url( __FILE__ ) . 'assets/welcome.svg',
    'features'    => [
        [
            'icon'        => 'dashicons-cart',
            'title'       => 'Accept Payments',
            'description' => 'Connect Stripe and start selling.',
        ],
        [
            'icon'        => 'dashicons-chart-area',
            'title'       => 'Track Revenue',
            'description' => 'Real-time analytics and reports.',
        ],
    ],
],
```

### Info

An educational or informational step with optional image, HTML content, and structured sections. No form
fields — purely read-only content. Use it for "here's what we'll set up" overviews, feature explanations,
or prerequisite instructions between functional steps.

```php
'how_it_works' => [
    'title'       => 'How It Works',
    'type'        => 'info',
    'icon'        => 'dashicons-info',
    'description' => 'Here\'s what we\'ll set up in the next few steps.',
    'image'       => plugin_dir_url( __FILE__ ) . 'assets/diagram.svg',
    'content'     => '<p>MyPlugin connects to your payment provider to manage products and process orders.</p>',
    'sections'    => [
        [
            'icon'        => 'dashicons-admin-settings',
            'title'       => 'Configure Settings',
            'description' => 'Set your currency, connect Stripe, and choose your checkout page.',
        ],
        [
            'icon'        => 'dashicons-download',
            'title'       => 'Import Products',
            'description' => 'Pull existing products or create your first one.',
        ],
        [
            'icon'        => 'dashicons-yes',
            'title'       => 'Enable Features',
            'description' => 'Turn on receipts, invoices, and customer accounts.',
        ],
    ],
],
```

The `content` key accepts HTML (sanitized via `wp_kses_post`). The `sections` array renders structured
blocks with an icon, title, and description — similar to the welcome step's features but designed for
explanatory content rather than feature highlights.

### Fields

A step with form fields that are automatically rendered, validated, and saved.

```php
'settings' => [
    'title'  => 'Store Settings',
    'type'   => 'fields',
    'fields' => [
        'store_name' => [
            'type'        => 'text',
            'label'       => 'Store Name',
            'placeholder' => 'My Awesome Store',
            'option'      => 'store_name',
            'help'        => 'This appears in emails and receipts.',
            'validate'    => function( $value ) {
                if ( empty( trim( $value ) ) ) {
                    return new WP_Error( 'required', 'Store name is required.' );
                }
                return true;
            },
        ],
        'currency' => [
            'type'       => 'select',
            'label'      => 'Currency',
            'options'    => 'currencies',
            'default'    => 'USD',
            'option'     => 'currency',
            'searchable' => true,
        ],
        'email' => [
            'type'     => 'email',
            'label'    => 'Notification Email',
            'default'  => '{admin_email}',
            'option'   => 'notification_email',
            'validate' => 'is_email',
        ],
    ],
],
```

**Supported field types:** `text`, `email`, `url`, `number`, `textarea`, `select`, `radio`, `toggle`

The `{admin_email}` placeholder is automatically replaced with the site's admin email address.

#### Field Options

| Key           | Type            | Description                                                            |
|---------------|-----------------|------------------------------------------------------------------------|
| `type`        | `string`        | Field type (see supported types above)                                 |
| `label`       | `string`        | Label displayed above the field                                        |
| `placeholder` | `string`        | Placeholder text for text-based inputs                                 |
| `help`        | `string`        | Help text displayed below the field                                    |
| `default`     | `mixed`         | Default value                                                          |
| `option`      | `string`        | Storage key (wp_options key or custom callback key)                    |
| `options`     | `array\|string` | Options for select/radio (array or preset string)                      |
| `searchable`  | `bool`          | Force Select2 on/off (auto-enabled for 10+ options)                    |
| `validate`    | `callable`      | Validation callback — return `true` or `WP_Error`                      |
| `sanitize`    | `callable`      | Custom sanitization callback (auto-sanitized by type)                  |
| `depends`     | `array`         | Field dependency rules (see [Field Dependencies](#field-dependencies)) |
| `rows`        | `int`           | Row count for `textarea` fields (default: 4)                           |
| `description` | `string`        | Description text for `toggle` fields                                   |

### Checklist

A step with toggle items for enabling/disabling features.

```php
'features' => [
    'title'       => 'Enable Features',
    'type'        => 'checklist',
    'description' => 'Choose which features to enable.',
    'items'       => [
        [
            'label'       => 'Email Receipts',
            'description' => 'Automatically send receipts after each purchase.',
            'option'      => 'email_receipts',
            'default'     => true,
        ],
        [
            'label'       => 'PDF Invoices',
            'description' => 'Generate and attach PDF invoices to order emails.',
            'option'      => 'pdf_invoices',
            'default'     => false,
        ],
    ],
],
```

### Sync

A step that integrates with `arraypress/wp-inline-sync` for batch import/sync operations with a progress bar.
The sync must be registered externally via `register_sync()` on the `init` hook — the onboarding library
only renders the UI wrapper and references the sync by its ID.

**Step 1: Register the sync early (on `init`)**

```php
add_action( 'init', function () {
    register_sync( 'my_import_products', [
        'hook_suffix'        => 'settings_page_my-setup',
        'container'          => '.onboarding-sync-container',
        'reload_on_complete' => false,
        'data_callback'      => 'my_fetch_products',
        'process_callback'   => 'my_process_product',
        'name_callback'      => fn( $item ) => $item->name ?? $item->id,
    ] );
} );
```

**Step 2: Reference the sync in the wizard step**

```php
'import' => [
    'title'       => 'Import Products',
    'type'        => 'sync',
    'icon'        => 'dashicons-download',
    'description' => 'Pull your existing products from Stripe.',
    'skippable'   => true,
    'skip_label'  => 'Skip Import',
    'sync_id'     => 'my_import_products',
],
```

**Why external registration?** The inline-sync library registers REST API routes during `rest_api_init`
(inside the `init` hook). If the onboarding library tried to register syncs internally during `admin_menu`
or `admin_init`, it would be too late — the REST routes would never register. External registration gives
the consuming plugin full control over timing.

**Hook suffix computation:** WordPress generates hook suffixes based on the parent menu:

| Parent                  | Hook Suffix Formula                  | Example                  |
|-------------------------|--------------------------------------|--------------------------|
| Hidden (no parent_slug) | `admin_page_{menu_slug}`             | `admin_page_my-setup`    |
| `options-general.php`   | `settings_page_{menu_slug}`          | `settings_page_my-setup` |
| `tools.php`             | `tools_page_{menu_slug}`             | `tools_page_my-setup`    |
| Custom top-level        | `{parent_basename}_page_{menu_slug}` | `toplevel_page_my-setup` |

**Sync step behavior:**

- The Continue button starts disabled and is enabled when the sync completes
- The sync trigger button hides during processing and on successful completion
- If the sync has failures, the trigger button reappears for retry
- If the sync is cancelled, the trigger button reappears
- Skippable sync steps allow users to bypass the import entirely

**Callback format:** See the `arraypress/wp-inline-sync` README for full callback documentation. The
`data_callback` must return `items`, `has_more`, `cursor`, and `total`. The `process_callback` must
return `'created'`, `'updated'`, `'skipped'`, or `WP_Error`.

### Complete

A success step with action links and an optional confetti celebration.

```php
'done' => [
    'title'       => 'You\'re All Set!',
    'type'        => 'complete',
    'icon'        => 'dashicons-flag',
    'description' => 'Your plugin is configured and ready to go.',
    'confetti'    => true,
    'links'       => [
        [ 'label' => 'Create Product', 'url' => admin_url( 'post-new.php?post_type=product' ) ],
        [ 'label' => 'View Dashboard', 'url' => admin_url() ],
        [ 'label' => 'Read the Docs', 'url' => 'https://docs.example.com', 'external' => true ],
    ],
],
```

Setting `confetti` to `true` triggers a lightweight canvas-based confetti burst when the step renders. No
external dependencies — the animation creates a temporary overlay, fires particles with gravity and drag,
fades out after ~3 seconds, and cleans itself up.

### Callback

A fully custom step where you provide your own render, validate, and save functions.

```php
'branding' => [
    'title'     => 'Store Branding',
    'type'      => 'callback',
    'skippable' => true,
    'render'    => 'my_render_branding',
    'validate'  => 'my_validate_branding',
    'save'      => 'my_save_branding',
],
```

All three callbacks receive the step configuration array. The `validate` callback should return `true`
or a `WP_Error`. The `save` callback receives the submitted `$_POST` data.

## Step Options

These options are available on all step types:

| Key             | Type       | Description                                                                         |
|-----------------|------------|-------------------------------------------------------------------------------------|
| `title`         | `string`   | Step title displayed in the header and progress bar                                 |
| `description`   | `string`   | Description text below the title                                                    |
| `type`          | `string`   | Step type: `welcome`, `info`, `fields`, `checklist`, `sync`, `complete`, `callback` |
| `icon`          | `string`   | Dashicon class for the progress bar (e.g. `dashicons-cart`)                         |
| `show_if`       | `callable` | PHP callback — return `false` to skip this step entirely                            |
| `before_render` | `callable` | PHP callback — runs before the step content renders                                 |
| `skippable`     | `bool`     | Show a skip button (default: `false`)                                               |
| `skip_label`    | `string`   | Custom skip button text                                                             |
| `confetti`      | `bool`     | Trigger confetti animation on this step (default: `false`)                          |
| `validate`      | `callable` | Step-level validation callback                                                      |
| `save`          | `callable` | Step-level custom save callback                                                     |

## Step Icons

Each step can display a dashicon in the progress bar instead of its step number. When a step is completed,
the icon is replaced with a green checkmark regardless of whether an icon was set.

```php
'steps' => [
    'welcome'  => [ 'title' => 'Welcome',   'icon' => 'dashicons-admin-home',     ... ],
    'settings' => [ 'title' => 'Settings',  'icon' => 'dashicons-admin-settings', ... ],
    'import'   => [ 'title' => 'Import',    'icon' => 'dashicons-download',       ... ],
    'features' => [ 'title' => 'Features',  'icon' => 'dashicons-yes',            ... ],
    'done'     => [ 'title' => 'Complete',  'icon' => 'dashicons-flag',           ... ],
],
```

Icons are optional — steps without an `icon` key show their step number as before. You can mix icons
and numbers across steps.

## Before Render Callback

The `before_render` callback runs just before a step's content is rendered. Use it for side effects
like checking API connectivity, preloading data, or displaying notices.

```php
'api_setup' => [
    'title'         => 'API Connection',
    'type'          => 'fields',
    'before_render' => function( $step ) {
        // Check if the API key from a previous step is valid
        $key = get_option( 'my_stripe_key' );
        if ( $key && ! validate_stripe_key( $key ) ) {
            echo '<div class="notice notice-warning"><p>Your Stripe key appears invalid.</p></div>';
        }
    },
    'fields' => [ ... ],
],
```

The callback receives the step configuration array. It runs after errors are displayed but before the
step content, so any output appears between the error block and the step body.

### Notices

The library provides styled notice classes for use in `before_render` callbacks. These render correctly
within the wizard context (unlike WordPress's `.notice` classes which assume the standard admin layout).

```php
'before_render' => function( $step ) {
    echo '<div class="onboarding-notice onboarding-notice--info"><p>This is an info notice.</p></div>';
    echo '<div class="onboarding-notice onboarding-notice--success"><p>This is a success notice.</p></div>';
    echo '<div class="onboarding-notice onboarding-notice--warning"><p>This is a warning notice.</p></div>';
    echo '<div class="onboarding-notice onboarding-notice--error"><p>This is an error notice.</p></div>';
},
```

Available variants: `--info` (accent blue), `--success` (green), `--warning` (amber), `--error` (red).
Without a variant, the notice uses the default accent color.

## Completion Redirect

To auto-redirect after the wizard finishes instead of showing a complete step with links:

```php
register_onboarding( 'my-plugin-setup', [
    'completed_redirect' => admin_url( 'edit.php?post_type=product' ),
    'steps' => [
        // ... your steps ...
        'done' => [
            'title' => 'Finishing Up',
            'type'  => 'complete',
        ],
    ],
] );
```

When the user clicks "Finish Setup" on the last step before the complete step, the wizard saves
completion status and redirects to the configured URL. If both `completed_redirect` and a step-level
`redirect` are set, the step-level redirect takes priority.

## Conditional Steps

Use `show_if` to conditionally show or hide entire steps based on server-side logic. The callback runs
on each page load — the step is completely removed from the wizard (not just hidden) when it returns `false`.

```php
'payments' => [
    'title'   => 'Payment Gateway',
    'type'    => 'fields',
    'show_if' => function() {
        // Only show if Stripe isn't already configured
        $options = get_option( 'my_settings', [] );
        return empty( $options['stripe_key'] );
    },
    'fields' => [ ... ],
],
```

This is different from field dependencies which control visibility *within* a step using client-side
JavaScript. `show_if` is server-side and controls whether the step exists at all.

## Field Dependencies

Fields can be shown/hidden or have their attributes swapped based on another field's value on the same step.
Dependencies are evaluated in the browser as the user interacts with the form.

### Simple Dependency (Single Rule)

Show or hide a field based on another field's value:

```php
'fields' => [
    'gateway' => [
        'type'    => 'radio',
        'label'   => 'Payment Gateway',
        'options' => [
            'stripe' => 'Stripe',
            'paypal' => 'PayPal',
            'both'   => 'Both',
        ],
        'default' => 'stripe',
        'option'  => 'gateway',
    ],
    'paypal_client_id' => [
        'type'    => 'text',
        'label'   => 'PayPal Client ID',
        'option'  => 'paypal_client_id',
        'depends' => [
            'field'    => 'gateway',
            'operator' => 'in',
            'value'    => [ 'paypal', 'both' ],
            'action'   => 'show',
        ],
    ],
],
```

### Attribute Swapping

Keep a field visible but swap its placeholder, help text, or label based on another field:

```php
'stripe_key' => [
    'type'        => 'text',
    'label'       => 'Stripe Publishable Key',
    'placeholder' => 'pk_test_...',
    'help'        => 'Test mode — no real charges.',
    'option'      => 'stripe_key',
    'depends'     => [
        'field'     => 'test_mode',
        'operator'  => '==',
        'value'     => '1',
        'action'    => 'swap',
        'attrs'     => [
            'placeholder' => 'pk_test_...',
            'help'        => 'Test mode — no real charges.',
        ],
        'attrs_alt' => [
            'placeholder' => 'pk_live_...',
            'help'        => 'Live mode — real charges will apply.',
        ],
    ],
],
```

When the condition is met, `attrs` values are applied. When it's not met, `attrs_alt` values are applied.

### Multiple Rules Per Field

A field can have multiple dependency rules. Pass an array of rule objects:

```php
'stripe_key' => [
    'type'        => 'text',
    'label'       => 'Stripe Key',
    'placeholder' => 'pk_test_...',
    'help'        => 'Test mode — no real charges.',
    'option'      => 'stripe_key',
    'depends'     => [
        // Rule 1: Only visible when Stripe is selected
        [
            'field'    => 'gateway',
            'operator' => 'in',
            'value'    => [ 'stripe', 'both' ],
            'action'   => 'show',
        ],
        // Rule 2: Swap placeholders based on test mode
        [
            'field'     => 'test_mode',
            'operator'  => '==',
            'value'     => '1',
            'action'    => 'swap',
            'attrs'     => [
                'placeholder' => 'pk_test_...',
                'help'        => 'Test mode — no real charges.',
            ],
            'attrs_alt' => [
                'placeholder' => 'pk_live_...',
                'help'        => 'Live mode — real charges will apply.',
            ],
        ],
    ],
],
```

Both single-object and array-of-objects syntax are supported. The library auto-detects which format
you're using.

For visibility, if *any* `show` rule fails, the field is hidden. Attribute `swap` rules are applied
independently regardless of other rules.

### Dependency Reference

| Key         | Type            | Description                                               |
|-------------|-----------------|-----------------------------------------------------------|
| `field`     | `string`        | Source field key to watch                                 |
| `operator`  | `string`        | `==`, `!=`, `in`, `not_in` (default: `==`)                |
| `value`     | `string\|array` | Value to compare (string, or array for `in`/`not_in`)     |
| `action`    | `string`        | `show` = toggle visibility, `swap` = swap attributes only |
| `attrs`     | `array`         | Attributes applied when condition is **met**              |
| `attrs_alt` | `array`         | Attributes applied when condition is **not met**          |

**Swappable attributes:** `placeholder`, `help`, `label`

## Value Callbacks

By default, each field's `option` key maps to its own `wp_options` row via `get_option` / `update_option`.

To use a custom storage backend (like a single serialized array or `arraypress/wp-register-setting-fields`),
provide `get_callback` and `update_callback`:

```php
register_onboarding( 'my-plugin-setup', [
    'get_callback'    => fn( $key, $default ) => get_setting_field_value( 'my_plugin', $key, $default ),
    'update_callback' => fn( $key, $value )   => update_setting_field_value( 'my_plugin', $key, $value ),

    'steps' => [ ... ],
] );
```

Or store everything in a single option array:

```php
register_onboarding( 'my-plugin-setup', [
    'get_callback' => function( string $key, $default = '' ) {
        $options = get_option( 'my_plugin_settings', [] );
        return $options[ $key ] ?? $default;
    },
    'update_callback' => function( string $key, $value ) {
        $options         = get_option( 'my_plugin_settings', [] );
        $options[ $key ] = $value;
        update_option( 'my_plugin_settings', $options );
    },

    'steps' => [ ... ],
] );
```

When callbacks are provided, the field's `option` key becomes a key within your storage system rather
than a standalone `wp_options` key. The library calls `get_callback` when pre-populating fields and
`update_callback` on save.

## Option Presets

String shorthand for common select options:

| Preset                | Source                          | Count  |
|-----------------------|---------------------------------|--------|
| `currencies`          | `arraypress/wp-currencies`      | 136    |
| `countries`           | `arraypress/wp-countries`       | 249    |
| `timezones`           | PHP `timezone_identifiers_list` | ~400   |
| `pages`               | Published WordPress pages       | varies |
| `languages`           | `wp_get_available_translations` | ~200   |
| `users`               | All WordPress users             | varies |
| `users:{role}`        | Users filtered by role          | varies |
| `taxonomy:{taxonomy}` | Terms from any taxonomy         | varies |

**Parameterized presets** use a colon separator to pass arguments:

```php
// All users
'options' => 'users',

// Only administrators
'options' => 'users:administrator',

// Only editors
'options' => 'users:editor',

// Categories
'options' => 'taxonomy:category',

// Tags
'options' => 'taxonomy:post_tag',

// Custom taxonomy
'options' => 'taxonomy:product_cat',
```

Custom presets via filter:

```php
add_filter( 'arraypress_onboarding_preset_my_options', function() {
    return [ 'a' => 'Option A', 'b' => 'Option B' ];
} );
```

Then use in a field:

```php
'my_field' => [
    'type'    => 'select',
    'label'   => 'My Field',
    'options' => 'my_options',
],
```

## Select2

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

## Activation Redirect

Redirect the user to the wizard immediately after plugin activation:

```php
register_onboarding( 'my-plugin-setup', [
    'redirect' => true,
    'steps'    => [ ... ],
] );
```

Then in your plugin's main file:

```php
register_activation_hook( __FILE__, function() {
    register_onboarding_redirect( 'my-plugin-setup' );
} );
```

The redirect only fires once (via a transient), only for single-plugin activation (not bulk), and
only when the wizard hasn't already been completed.

## Completion Tracking

The wizard automatically stores a timestamp in `wp_options` when the user reaches the last step.
The option key is auto-generated from the wizard ID (e.g. `my_plugin_setup_completed`).

```php
// Check if the wizard has been completed
\ArrayPress\RegisterOnboarding\Manager::is_completed( 'my-plugin-setup' );

// Reset the wizard (allows it to run again)
\ArrayPress\RegisterOnboarding\Manager::reset( 'my-plugin-setup' );
```

## Menu Highlight

When the wizard is registered under a parent menu (e.g. `'parent_slug' => 'options-general.php'`),
the library automatically fixes WordPress sidebar highlighting as users navigate between steps. Without
this fix, the sidebar highlight would break because step navigation changes the URL from the original
menu page URL. This is handled internally — no configuration needed.

## Colors

Override the wizard's color scheme via CSS custom properties:

```php
'colors' => [
    'accent'       => '#6366f1',
    'accent_hover' => '#4f46e5',
    'accent_light' => '#eef2ff',
],
```

**Available color keys:**

| Key              | CSS Variable          | Description             |
|------------------|-----------------------|-------------------------|
| `accent`         | `--ob-accent`         | Primary accent color    |
| `accent_hover`   | `--ob-accent-hover`   | Accent hover state      |
| `accent_light`   | `--ob-accent-light`   | Light accent background |
| `success`        | `--ob-success`        | Success state color     |
| `error`          | `--ob-error`          | Error state color       |
| `text_primary`   | `--ob-text-primary`   | Primary text color      |
| `text_secondary` | `--ob-text-secondary` | Secondary text color    |
| `text_muted`     | `--ob-text-muted`     | Muted/disabled text     |
| `border`         | `--ob-border`         | Default border color    |
| `border_light`   | `--ob-border-light`   | Light border color      |
| `bg_white`       | `--ob-bg-white`       | Card background         |
| `bg_subtle`      | `--ob-bg-subtle`      | Subtle background       |
| `bg_page`        | `--ob-bg-page`        | Page background         |
| `radius`         | `--ob-radius`         | Default border radius   |
| `radius_lg`      | `--ob-radius-lg`      | Large border radius     |

## Hooks

### Actions

```php
// Before/after wizard page renders
do_action( 'arraypress_before_render_onboarding', $wizard_id, $config );
do_action( 'arraypress_before_render_onboarding_{wizard_id}', $config );
do_action( 'arraypress_after_render_onboarding', $wizard_id, $config );
do_action( 'arraypress_after_render_onboarding_{wizard_id}', $config );

// When the wizard is completed
do_action( 'arraypress_onboarding_completed', $wizard_id, $config );
do_action( 'arraypress_onboarding_completed_{wizard_id}', $config );

// Custom step type rendering
do_action( 'arraypress_onboarding_render_step_{type}', $step, $config );
```

### Filters

```php
// Register custom option presets
add_filter( 'arraypress_onboarding_preset_{name}', fn() => [ 'key' => 'Label' ] );
```

## Helper Functions

```php
// Register a wizard
register_onboarding( string $id, array $config ): void;

// Set activation redirect (call from register_activation_hook)
register_onboarding_redirect( string $id ): void;

// Get a wizard's configuration
get_onboarding_wizard( string $id ): ?array;

// Check if a wizard has been completed
is_onboarding_completed( string $id ): bool;

// Reset a wizard (allows it to run again)
reset_onboarding( string $id ): bool;

// Check if a wizard is registered
has_onboarding_wizard( string $id ): bool;

// Remove a wizard
unregister_onboarding( string $id ): bool;
```

## Manager Methods

```php
// Check if a wizard is registered
Manager::has_wizard( string $id ): bool;

// Get a wizard's configuration
Manager::get_wizard( string $id ): ?array;

// Check completion status
Manager::is_completed( string $id ): bool;

// Reset completion (allows wizard to run again)
Manager::reset( string $id ): bool;

// Remove a wizard
Manager::unregister( string $id ): bool;

// Get all registered wizards
Manager::get_all_wizards(): array;

// Set activation redirect transient
Manager::set_redirect( string $id ): void;
```

## Requirements

- PHP 8.1+
- WordPress 6.0+
- arraypress/wp-composer-assets
- arraypress/wp-currencies
- arraypress/wp-countries
- arraypress/wp-inline-sync (optional, for sync steps)

## License

GPL-2.0-or-later

## Credits

Created by [David Sherlock](https://davidsherlock.com) at [ArrayPress](https://arraypress.com).