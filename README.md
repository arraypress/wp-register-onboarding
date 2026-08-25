# Register Onboarding

A setup wizard for a WordPress plugin: steps, navigation, validation and
storage, with the fields drawn by [wp-field-kit](https://github.com/arraypress/wp-field-kit).

## Install

```bash
composer require arraypress/wp-register-onboarding
```

Requires PHP 8.3.

## Use

```php
add_action( 'init', function () {
	register_onboarding( 'myplugin-setup', [
		'parent_slug' => 'options-general.php',
		'logo'        => plugins_url( 'logo.svg', __FILE__ ),
		'redirect'    => true,
		'option'      => 'myplugin_settings',
		'steps'       => [
			'welcome' => [
				'type'    => 'content',
				'title'   => __( 'Welcome', 'my-plugin' ),
				'content' => '<p>' . esc_html__( 'Two questions and you are done.', 'my-plugin' ) . '</p>',
			],
			'store'   => [
				'title'  => __( 'Your store', 'my-plugin' ),
				'fields' => [
					'store_name' => [
						'type'     => 'text',
						'label'    => __( 'Store name', 'my-plugin' ),
						'validate' => fn( $value ) => '' === trim( (string) $value )
							? new WP_Error( 'required', __( 'A store name is required.', 'my-plugin' ) )
							: true,
					],
					'currency'   => [
						'type'    => 'select',
						'label'   => __( 'Currency', 'my-plugin' ),
						'options' => [ 'gbp' => 'GBP', 'eur' => 'EUR', 'usd' => 'USD' ],
					],
				],
			],
			'done'    => [
				'type'     => 'content',
				'title'    => __( 'All set', 'my-plugin' ),
				'confetti' => true,
				'links'    => [
					[ 'label' => __( 'Add a product', 'my-plugin' ), 'url' => admin_url( 'post-new.php' ) ],
				],
			],
		],
	] );
} );
```

And, from the activation hook, to have it open once:

```php
register_activation_hook( __FILE__, function () {
	onboarding_redirect_after_activation( 'myplugin-setup' );
} );
```

Elsewhere:

```php
if ( ! is_onboarding_completed( 'myplugin-setup' ) ) {
	// Nudge them towards get_onboarding_url( 'myplugin-setup' ).
}
```

## Step types

| Type       | What it is                                                          |
| ---------- | ------------------------------------------------------------------- |
| `fields`   | Field kit fields. The default.                                       |
| `content`  | Something to read: `image`, `content`, `items`, `links`.             |
| `callback` | Your own markup, from `render`.                                      |
| `sync`     | An import, via [wp-inline-sync](https://github.com/arraypress/wp-inline-sync). |

Anything else fires `arraypress_onboarding_render_{type}`, so a plugin can add
a kind of step this library has never heard of.

### Every step

| Option        | Type     | What it does                                                |
| ------------- | -------- | ----------------------------------------------------------- |
| `title`       | string   | The step's heading.                                          |
| `description` | string   | A line under it.                                             |
| `icon`        | string   | A dashicon, with or without the `dashicons-` prefix.         |
| `show_if`     | callable | Whether the step applies at all.                             |
| `skippable`   | bool     | Offer a skip link. Nothing is saved when it is used.         |
| `validate`    | callable | Checks the step's answers together. `true`, `false` or `WP_Error`. |
| `save`        | callable | Stores them yourself instead.                                |
| `confetti`    | bool     | Celebrate on arrival.                                        |
| `redirect`    | string   | Where finishing on this step goes.                           |

A `show_if` that says no makes the step **absent**, not hidden: it is not
counted in the progress, not reachable by its own URL, and a submission naming
it is refused.

### The wizard

| Option               | Type     | What it does                                              |
| -------------------- | -------- | ---------------------------------------------------------- |
| `parent_slug`        | string   | Which admin file the page hangs off. `index.php`.          |
| `menu_slug`          | string   | The page slug. The wizard's id by default.                 |
| `hidden`             | bool     | Take the menu item back off. `true`.                       |
| `capability`         | string   | Who may run it. `manage_options`.                          |
| `page_title`         | string   | The browser title.                                         |
| `header_title`       | string   | The heading above the card.                                |
| `logo`               | string   | An image to use instead of the heading.                    |
| `accent`             | string   | A hex colour. The admin colour scheme by default.          |
| `notices`            | bool     | Keep other plugins' admin notices. `false`.                |
| `redirect`           | bool     | Open once after activation, with the helper above.         |
| `completed_option`   | string   | Where completion is recorded.                              |
| `completed_redirect` | string   | Where finishing goes.                                      |
| `option`             | string   | One option holding every answer.                           |
| `get_callback`       | callable | Read an answer yourself.                                   |
| `update_callback`    | callable | Write one yourself.                                        |
| `labels`             | array    | `next`, `previous`, `skip`, `finish`, `exit`.              |

## Where the answers go

Three arrangements, in the order you are likely to want them:

**One option.** `'option' => 'myplugin_settings'` puts every answer in one
array, keyed by field key — which is what a plugin with a settings array
already reads.

**An option each.** Say nothing and each field goes to an option named by its
key, or by an `option` of its own:

```php
'store_name' => [ 'type' => 'text', 'option' => 'myplugin_store_name' ],
```

**Yours.** `get_callback` and `update_callback` are handed a key and a value
and can do whatever the plugin already does.

## Validation

A field validator gets the sanitized value and every other answer; a step
validator gets them all. Return `true`, `false`, or a `WP_Error` whose message
is shown.

Sanitizing happens first, by field type, so a validator sees what would be
stored rather than what was typed. Nothing is stored until the whole step
passes, and a step that came back with errors re-renders **what was
submitted** — not what is stored, which is what made a deliberately cleared
field come back filled in.

## What it gets right

**The last step can be finished.** It could not before: a step of type
`complete` was given no forward button, and completion was recorded by
submitting the last step. A wizard that ended, as the documented example did,
on an "All set" screen was therefore never marked as finished, and
`is_onboarding_completed()` returned false for the rest of the install's life.

**A toggle can be turned off.** The old renderer read the submitted value,
treated anything empty as absent, and fell back to the configured default — so
a toggle defaulting to on could be switched off, saved, and would come back on.

**Back and Skip are links.** Neither saves anything, so neither needs to post.
Making them submit buttons is what forced the wizard to carry a hidden
direction input, a click handler to set it, and a keydown handler to stop
Enter submitting the first button in the form — which was Back. One submit
button and Enter does the obvious thing with no script at all.

**Step URLs go through the page's own parent.** They were `admin.php?page=…`
regardless, which is why the menu highlight was wrong on every step after the
first and needed two filters to put back.

**It follows the admin colour scheme.** A wizard is usually the first screen of
a plugin somebody has just installed; being the one screen that ignores their
colour scheme is a poor first impression.

## Upgrading from 1.x

**The fields are the kit's.** Which means about fifty types instead of eight,
conditional visibility, and sanitizing by type — and that `select2` is gone,
along with the 265-line renderer and the bespoke dependency map.

Field configuration is the kit's, so `depends` becomes `show_when` (both are
read), and `searchable` becomes `'type' => 'enhanced_select'`.

**`checklist` steps are gone.** A checklist is a fields step whose fields are
toggles, and it had its own renderer, its own save path and its own numerically
indexed input names.

**`welcome`, `info` and `complete` are one `content` type.** They were three
renderers for image, prose, a list and some links.

**Option presets are gone.** `'options' => 'currencies'` resolved five fixed
lists and three that queried the database — `pages` loaded every published page
into a `<select>`. The kit takes a callable, so a fixed list is
`'options' => fn() => My\Currencies::all()`, and pages, users, terms and posts
are field types of their own that search rather than enumerate.

**`register_onboarding_redirect()` is `onboarding_redirect_after_activation()`,**
and `get_onboarding_wizard()` returns a `Wizard` rather than an array.

## Testing

```bash
composer test          # phpunit
composer lint          # phpcs, defect sniffs
composer format:check  # phpcs, formatting
composer docs:verify   # the field configuration in this file, against the kit
```
