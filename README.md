# Site PWA

Turns a WordPress site into an installable app (PWA): a home-screen icon, an app shell for signed-in users, and a contract through which other plugins — Free Form Certificate first — register their screens.

**Status:** foundation only. The plugin installs and updates itself from GitHub Releases; the app arrives in the releases tracked under the [first milestone](https://github.com/rpgmem/site-pwa/milestones).

## Install

Download `site-pwa-X.Y.Z.zip` from the [latest release](https://github.com/rpgmem/site-pwa/releases/latest) and upload it under Plugins → Add New → Upload Plugin. Later versions arrive on Dashboard → Updates, each package checked against its SHA-256.

## Requirements

PHP 8.3 and WordPress 6.8 or later.

## Development

```bash
composer install && npm ci
vendor/bin/phpunit --testsuite unit
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpcs
npm run lint && npm test
```

The conventions — branches, releases, quality gates, translations — are in [`CLAUDE.md`](CLAUDE.md).

**License** GPLv3 or later. See [`LICENSE`](LICENSE).
