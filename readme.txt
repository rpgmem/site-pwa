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

This first release is the plugin's foundation: it installs, updates itself from GitHub Releases and stores nothing yet. The app itself arrives in the releases that follow.

== Installation ==

1. Download `site-pwa-X.Y.Z.zip` from the latest GitHub release.
2. In WordPress, go to Plugins → Add New → Upload Plugin, choose the zip and activate it.

== Privacy ==

The plugin stores no personal data. It keeps one cache of the latest GitHub release, in a site transient deleted with the plugin.

== Changelog ==

The full history of every release is in [CHANGELOG.md](https://github.com/rpgmem/site-pwa/blob/main/CHANGELOG.md).

== Upgrade Notice ==

= 0.1.0 =
The plugin's foundation: it installs and updates itself from GitHub Releases, with each package checked against its SHA-256. The app arrives in later releases. Details in CHANGELOG.md.
