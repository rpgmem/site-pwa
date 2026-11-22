# Changelog

All notable changes to **Site PWA** are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- **The plugin's foundation** (#1). Namespace `SitePwa\` with an inline autoloader, PHP 8.3 and WordPress 6.8 floors, translations in `languages/` (pt_BR), and `uninstall.php` deleting everything the plugin stores — today only the update cache.
- **Updates from GitHub Releases** (#1). `Update\GithubUpdater` offers `releases/latest` on Dashboard → Updates, checks the package's SHA-256 before WordPress installs it, and fills "View details" from the offered release's readme, in Portuguese for a pt_BR administrator.
- **CI and releases the same as the other plugins** (#1). PHPUnit (unit on PHP 8.3/8.4, functional on the latest WordPress and on the floor), coverage floor, PHPStan, PHPCS, ESLint, Stylelint, Vitest, minified-asset check, Composer audit, CodeQL; a `v*` tag publishes the zip and its notes.
- **The app is installable** (#2). Settings → Site PWA turns it on (off by default) and sets its name, short name and colours. It lives under `/app/` with a manifest, the site icon or the plugin's, and an offline page; a service worker at the site root acts only on the app's pages and never caches a page.
- **Turning the app off cleans browsers up** (#2). While it is off, the worker URL serves a worker that deletes the app's caches and unregisters itself, so a phone that installed the app is cleaned on its next visit.
- **Another plugin's service worker keeps the app off** (#2). OneSignal, SuperPWA, PWA for WP or the PWA plugin, while active, keep the app off until the replacement is confirmed; the settings screen names them and lists the workers the browser already holds. A push service can share the root through `site_pwa_sw_imports` instead.
