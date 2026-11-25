# Registering screens with the app

Another plugin adds screens to the app through one action. It needs no check that Site PWA is installed: without it, the action never fires and nothing runs.

```php
add_action( 'site_pwa_register_screens', static function ( $screens, int $api ): void {
	$screens->add( 'presence', array(
		'label'      => __( 'Presence', 'my-plugin' ),
		'icon'       => 'check',
		'place'      => 'nav',
		'capability' => 'read',
		'position'   => 1,
		'render'     => array( My\Presence::class, 'render' ),
	) );

	$screens->add( 'calendar', array(
		'label' => __( 'Calendar', 'my-plugin' ),
		'icon'  => 'calendar',
		'url'   => '/calendar/',
	) );
}, 10, 2 );
```

`$api` is the contract's version, `1` today. It is raised only by a change that would break a consumer written for the previous one, so a consumer can refuse a version it does not know.

## Arguments

| Key | Required | Default | What it does |
| --- | --- | --- | --- |
| `label` | yes | | The name in the navigation, on the home screen and as the screen's heading. Pass it already translated. Markup is stripped. |
| `render` | one of the two | | A callable that prints or returns the screen's HTML. The app draws it at `/app/s/<id>/`, inside its frame and under the label as `<h1>`. |
| `url` | one of the two | | A page on this site, absolute or starting with `/`. The screen links there instead of having a page in the app. Another host is refused. |
| `icon` | no | `grid` | One of the app's icons: `home`, `user`, `edit`, `logout`, `share`, `link`, `open`, `more`, `grid`, `calendar`, `check`, `document`, `chevron`, `add`, `close`, `download`, `news`, `qr`, `bell`, `back`, `award`. An unknown name draws `grid`. |
| `place` | no | `more` | `nav` puts the screen in the bottom navigation; `more` puts it in the More list. |
| `slot` | no | | `center` raises a `nav` screen in the middle of the bar, as the app's main action. Refused on a `more` screen. |
| `section` | no | `services` | More's group: `services` for what a person uses, `operator` for what they do for others. Anything else is refused. |
| `subtitle` | no | | A line under the name in More. Markup is stripped. |
| `badge` | no | | A short status beside the name in More: a string, or a callable that returns one when More is drawn (`"2 active · 1 offline"`). Markup is stripped; a callable that throws or returns anything else shows nothing. |
| `capability` | no | `read` | Who sees the screen. The app checks it everywhere the screen appears: navigation, home, More and its own URL. |
| `position` | no | `10` | Order, lowest first; screens with the same position sort by label. |

The id is part of the URL: lowercase letters, digits and hyphens. `home`, `account`, `more`, `news`, `post`, `offline`, `manifest`, `sw` and `s` belong to the app.

`add()` returns `true`, or a `WP_Error` naming what is wrong. It also calls `_doing_it_wrong()`, so the mistake shows under `WP_DEBUG`. A refused screen is simply absent; the rest still register.

## Where screens go

The bottom navigation holds at most five items:
- Home;
- up to three `nav` screens, by position;
- More.

A `nav` screen that asks for the `center` slot takes one of the three and sits raised in the middle of them, with Home and one screen on its left and one screen and More on its right. One screen holds the slot: the first by position. A second one stays an ordinary `nav` screen.

More opens on the person: their initials, their name, a line from the `site_pwa_profile_line` filter and a link to their details. Then the `more` screens and the `nav` screens that did not fit, in two groups by `section` (an empty group is not drawn), with News at the end of Services; the site's pages; the preferences; and Sign out.

```php
add_filter( 'site_pwa_profile_line', static fn( string $line, WP_User $user ): string => 'RF ***.**1-0 · Teacher', 10, 2 );
```

The line is plain text; markup is stripped. The dark theme preference is kept on that device only, in the browser's storage, and turns the frame and the screens inside it dark before the first paint by putting `data-site-pwa-scheme="dark"` and FFC's `ffc-dark-mode` class on `<html>`. Off, the app follows the site's choice again.

## Feeding the home screen

The home screen shows what a person should see first, and it learns it from the plugins that know it, through one filter:

```php
add_filter( 'site_pwa_home', static function ( array $home, WP_User $user, int $api ): array {
	$home['banners'][] = array(
		'id'       => 'my-plugin:attendance:42',
		'text'     => __( 'Attendance confirmed at %s (18/11)', 'my-plugin' ),
		'emphasis' => 'Coordinators meeting',
		'tone'     => 'success',
	);
	$home['next'] = array(
		'title'   => 'Pedagogical meeting',
		'meta'    => 'Paulo Freire Auditorium · Tue 25/11 · 14:00',
		'actions' => array(
			array( 'label' => __( 'Presence QR', 'my-plugin' ), 'screen' => 'presence', 'icon' => 'qr', 'primary' => true ),
			array( 'label' => __( 'Details', 'my-plugin' ), 'screen' => 'calendar' ),
		),
	);
	$home['pending'][] = array(
		'label' => __( 'Post-event form', 'my-plugin' ),
		'meta'  => __( 'Coordinators meeting · until 28/11', 'my-plugin' ),
		'url'   => '/forms/42/',
		'icon'  => 'document',
	);
	return $home;
}, 10, 3 );
```

`$api` is the contract's version, `1` today, as for screens. Append to the lists rather than replacing them, so other plugins' items stay. `next` holds one card: a later filter that sets it wins.

| Part | Keys | What the app does with it |
| --- | --- | --- |
| `banners` | `id`, `text`, `emphasis`, `tone` | A line on top that the person can dismiss. `text` places `emphasis` in bold with one `%s`; without exactly one, the emphasis is dropped. The `id` (letters, digits, `:`, `_`, `-`) is what the dismissal remembers, on that device only, so give each event its own. At most 3. |
| `next` | `title`, `meta`, `actions` (`label`, `screen` or `url`, `icon`, `primary`) | The "Next commitment" card. Up to two actions; the one marked `primary` is the accent button. |
| `pending` | `label`, `meta`, `screen` or `url`, `icon`, `tone` | A row of the "Pending" list, which also lights the badge on the bell. At most 8. |

`tone` is `info`, `success` or `warning`; anything else is `info`, and a banner without one is `success`. Text loses its markup, and an `icon` must be one of the app's. An action or a pending item leads either to a registered screen, named by `screen` (its id), or to a page in `url`, which must be on this site (absolute, or starting with `/`); a screen the user may not open counts as no link. An item that fails those is left out rather than drawn half-way. Nothing here is cached by the app or its service worker.

## Links that stay in the app

A link to a page of the site leaves the app: the page is outside the app's scope, so the phone opens it in a browser tab, inside the theme. Pass the URL through `site_pwa_app_url` and a page the app can draw comes back as its app address (`/app/?p=ID`). Anything else comes back unchanged: another site, an admin address, an archive, a post the user may not read, and a page with a page template or a page-builder layout, which opens on the site (`site_pwa_opens_in_app`). Without Site PWA the filter is not hooked and returns the URL as given, so no check is needed.

```php
$url = apply_filters( 'site_pwa_app_url', get_permalink( $page_id ) );
```

Inside a post or page the app draws, its links to other such pages are converted the same way, so following one keeps the app's frame.

## Requiring the app lock

The app locks with the phone's own screen lock (fingerprint, face or the phone's PIN) when it opens and when it comes back after ten seconds out of sight; it is optional, and More and Home invite the person to turn it on. A consumer makes it mandatory for an account with the `site_pwa_lock_required` filter: the app then shows nothing but the lock screen until it is turned on.

```php
add_filter( 'site_pwa_lock_required', static fn( bool $required, WP_User $user ): bool => $required || user_can( $user, 'my_operator_capability' ), 10, 2 );
```

Only a real `true` counts. The lock lives on the phone, through a platform credential (a passkey) the phone keeps for the site; nothing about it is stored on the server, and unlocking works offline. It protects against someone holding an unlocked phone, not against someone who changes the app's code on the phone.

## What the app does and does not do

- **It checks the capability.** A user without it sees the screen nowhere. Its URL answers with the same "not available" page as an unknown id, so the app never confirms the screen exists.
- **It requires a signed-in user,** as every app screen does. A signed-out visitor goes to the login and comes back to the screen.
- **It does not escape your markup.** What `render` produces is drawn as you wrote it, like a shortcode's output, so escaping is your job.
- **It never caches a screen.** Screens answer with `Cache-Control: private, no-store`, and the service worker does not store them.
- **It knows nothing about your plugin.** Everything it draws comes from the arguments above.
