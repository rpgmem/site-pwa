=== Site PWA ===
Contributors: rpgmem
Tags: pwa, app, progressive web app, home screen
Requires at least: 6.8
Tested up to: 7.1.3
Stable tag: 0.1.0
Requires PHP: 8.3
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Turns the site into an installable app: a home-screen icon, an app shell for signed-in users and screens other plugins register.

== Description ==

Site PWA makes a WordPress site installable on a phone or a computer, as an app with its own icon and window. Signed-in users open it on an app shell, and other plugins add their screens to it through a registration contract.

Settings → Site PWA turns the app on; it is off until then. The app is for signed-in users: it opens on its own frame, independent of the theme, with a home screen and the account, and the WordPress login takes the app's look inside the installed app. A post or page opens inside the app at `/app/?p=ID`; Share and Copy link hand out its normal address on the site, and a page built with a page builder or a page template opens on the site instead. The app lives under `/app/` (filterable with `site_pwa_app_prefix`), with the site icon from Settings → General, or the plugin's own, on the home screen. Without a connection, the app shows an offline page. The app's screens arrive in the releases that follow.

== Installation ==

1. Download `site-pwa-X.Y.Z.zip` from the latest GitHub release.
2. In WordPress, go to Plugins → Add New → Upload Plugin, choose the zip and activate it.

== Screenshots ==

1. The app's home screen, with the bottom navigation.
2. The account screen: who is signed in, edit profile, sign out.
3. The WordPress login inside the installed app.
4. The account screen in the dark theme.
5. A post opened inside the app, with Copy link and Open on the site.

== Other service workers ==

A browser keeps one service worker per site root, so the app's and another plugin's (OneSignal, SuperPWA, PWA for WP, the PWA plugin) would replace each other on every visit. Three outcomes:

* **No other worker:** turning the app on is enough.
* **Another plugin registers one:** Settings → Site PWA names it and the app stays off. Deactivate that plugin, or confirm the replacement, which stops that plugin's worker. The screen also lists the workers your own browser already holds for the site.
* **A push service that must keep working:** it can share the root instead. Its script, on this site, is loaded first by the app's worker when it is returned by the `site_pwa_sw_imports` filter; a push service can also load the app's worker into its own with `importScripts( '/site-pwa-sw.js' )`.

== Privacy ==

The plugin stores no personal data. It keeps its settings, the revision of its URL rules and one cache of the latest GitHub release, all deleted with the plugin. In the browser, the app's service worker caches only the offline page and the app's stylesheet: never a page, so nothing a signed-in user sees stays on the device.

== Changelog ==

The full history of every release is in [CHANGELOG.md](https://github.com/rpgmem/site-pwa/blob/main/CHANGELOG.md).

== Upgrade Notice ==

= 0.1.0 =
The plugin's foundation: it installs and updates itself from GitHub Releases, with each package checked against its SHA-256. The app arrives in later releases. Details in CHANGELOG.md.
