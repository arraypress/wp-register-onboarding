# Register Onboarding

A setup wizard for a plugin's first run, described as steps rather than built
as screens.

## What it does

The first five minutes after activation decide whether a plugin gets used. A
wizard helps — but building one means a multi-step form, progress, validation,
somewhere to keep the answers, a redirect on activation, and a way to know it
has been done.

This is that, from a list of steps. The fields are the field kit's, so the
controls match the rest of the admin, and answers land in the option you name.

## Features

* Describe a wizard as steps, with fields, and get a working screen
* Redirect to it once on activation, and never again
* Validate a step before letting somebody move past it
* Save answers into the option your settings screen already reads
* Include a step that only explains something, with no fields at all
* Ask whether setup was completed, to show a nudge until it is
* Reset it, for testing or for a fresh start

## Installation

```bash
composer require arraypress/wp-register-onboarding
```

## Quick start

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
					'currency' => [
						'type'    => 'select',
						'label'   => __( 'Currency', 'my-plugin' ),
						'options' => [ 'GBP' => 'GBP', 'USD' => 'USD' ],
					],
				],
			],
		],
	] );
} );
```

`'option' => 'myplugin_settings'` is the part worth noticing: answers go
straight into the option your settings page already uses, so a wizard is a
friendlier way into the same values rather than a second store of them.

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later

## License

GPL-2.0-or-later
