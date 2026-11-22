# CLAUDE.md

Project conventions for Claude (Anthropic CLI / agent sessions) working on this repository. Sessions inherit nothing across cold starts, so the durable rules live here. The workflow mirrors `rpgmem/ffcertificate`, `rpgmem/institutional-email`, `rpgmem/email-signature-generator` and `rpgmem/total-mail-queue`. Where this file is silent, Free Form Certificate's is the reference.

## What this repository ships

**Site PWA** turns a WordPress site into an installable app (PWA): a web app manifest and a home-screen icon, an app shell for signed-in users, and a **screen registration contract** through which other plugins add their screens. Free Form Certificate is the first consumer, and its attendance terminal stays in Free Form Certificate (rpgmem/ffcertificate#2738 is the umbrella issue). Every GitHub release attaches one asset, the plugin zip (`site-pwa-X.Y.Z.zip`).

The plugin is independent of Free Form Certificate: it never requires it, and a consumer registers its screens through the contract rather than this plugin reading theirs.

## Language

Every versioned artifact is in **English**:
- code, comments and docblocks;
- i18n source strings, whose translations live in `languages/` (pt_BR first);
- `CHANGELOG.md` and commit messages;
- issue and PR titles and bodies.

The conversation with the maintainer is in Brazilian Portuguese, and that does not decide the language of anything committed. A PR body is composed in the flow of the conversation, so the discipline belongs at the moment of writing it.

## Branches and pull requests

- **`main`** is production. It changes only through the release PR (`develop → main`) and through `hotfix/*` PRs.
- **`develop`** is the integration branch and the default base for every feature PR.
- Feature branches are `claude/<short-kebab-description>`, cut from `develop`. Always go through a PR, so the commit goes through CI.
- **Opening a PR:**
  - Open it as **draft**, flip it to ready, then enable **auto-merge (SQUASH)**.
  - When a PR completes a pre-agreed unit, flip it straight to ready.
  - When its scope emerged mid-session, confirm with the maintainer first.
- When `develop` moves under a PR, the PR is `behind`. Update the branch; don't wait it out.
- **Closing issues:** `Closes #NNN` does not auto-close an issue when the PR merges into `develop`, because the default branch is `main`. Close the issue by hand when the work lands, and tick its checkboxes or map each one to the PR that shipped it.
- **Refs:** an agent's credential can create and update refs but **cannot delete a ref** (HTTP 403). Deleting a branch that survives the merge is the maintainer's job (see "Issues, pull requests and the merge queue").
- Dependabot targets `develop`. A security update opens against `main`: retarget it to `develop`.
- `dependabot-automerge.yml` arms squash auto-merge on Dependabot's patch and minor updates, the same as Free Form Certificate; the required checks still gate the merge. Majors stay manual, because a major can change behaviour the checks do not see.

### Rulesets (set by the maintainer in Settings → Rules → Rulesets)

An agent cannot read or change these; the table is the intended configuration, so check the UI before relying on it.

| Ruleset | Rules | Bypass |
| --- | --- | --- |
| `develop-locked` | Restrict deletions | none |
| `develop-ci-gates` | Require a pull request (0 approvals) · Require status checks · branches up to date | Repository admin |
| `main-locked` | Restrict deletions · Block force pushes | none |
| `main-ci-gates` | Require a pull request (0 approvals) · Require status checks · branches up to date | Repository admin |

**Required status checks:** every job in `tests.yml`, `lint.yml` and `assets.yml`, and CodeQL's `Analyze` jobs, by job name.

A required check must run on every PR to that branch: no `paths:` filter, and no job-level `if:` that can skip it. Otherwise every PR waits forever on a check that never reports.

Force push stays allowed on `develop`, because the post-release sync is a reset.

## Issues, pull requests and the merge queue

The same standard as Free Form Certificate (its `CLAUDE.md` → "Issues, pull requests and the merge queue"). The layout lives in the templates — `.github/ISSUE_TEMPLATE/` (bug, feature, task) and `.github/PULL_REQUEST_TEMPLATE.md` (plus `PULL_REQUEST_TEMPLATE/release.md`, reached with `?template=release.md`); the rules around them are here.

- **An issue before the PR.** Every PR names the issue it closes. Exempt, and only these: a trivial docs or chore change, a Dependabot PR, the `release: X.Y.Z` bump and the CHANGELOG SHA backfill. An agent opens the issue itself, in English, from the task template.
- **Titles.** Issue: `Area: the problem in one sentence`. PR: `type(scope): summary`, `type` one of `feat`, `fix`, `docs`, `test`, `refactor`, `chore` — it becomes the squash subject on `develop`. Release titles keep their own shape.
- **Labels: one type, one area.** Type is `bug`, `enhancement`, `task`, `documentation` or `security`; area is an `area:` label (`area:infra` for CI, tooling and releases). `duplicate`, `invalid` and `wontfix` say why something closed unmerged, and `dependencies` and the ecosystem labels belong to Dependabot, whose config applies them. An agent cannot create a label, only apply one that exists.
- **A PR carries the labels of the issue it closes.** The agent's tool opens a PR with no labels and cannot set them at creation, so the agent applies them as the next step, before marking it ready. Every issue and every PR carries its type and its area; a PR that only names its issue in the body is unlabelled until someone labels it, which is how the alignment PRs of 2026-10-12 went out bare in three repositories.
- **One merge at a time.** Only one PR is ready with auto-merge armed; the others wait as drafts. After a merge the next PR takes `develop` by a merge, never a rebase; `CHANGELOG.md` conflicts keep both bullets, translation catalogues are recompiled rather than hand-edited, the touched tests run again, and only then is it flipped to ready and armed.
- **After each merge** the agent closes the issue by hand, ticking or naming each acceptance criterion.
- **Branches.** Settings → General → "Automatically delete head branches" is on, so GitHub deletes a merged PR's branch. Whatever survives, the agent lists at the end of the batch for the maintainer to delete; local worktrees the agent removes itself.

## Versioning and CHANGELOG

- **The version** lives in three places, all updated in the same commit:
  - the `Version:` header in `site-pwa.php`;
  - `SITE_PWA_VERSION`, defined just below it in the same file. `Plugin::VERSION` reads it, and the unit bootstrap reads it from the file's text;
  - `Stable tag` in `readme.txt`.
- **The floors** (PHP 8.3, WordPress 6.8) live in the plugin header, `readme.txt`, `Plugin::REQUIRES_PHP` / `REQUIRES_WP` and `phpcs.xml.dist`. `composer.json` declares the PHP floor twice: in `require.php`, and in `config.platform.php`, which makes Composer resolve dev dependencies as if it ran on the floor, so none can quietly require a newer PHP.
- **`PluginIdentityTest`** fails when any of those disagree.
- **Feature PRs never bump the version.**
  - Their CHANGELOG entry goes under `## [Unreleased]`.
  - Entries are grouped by `Added` / `Changed` / `Fixed` / `Removed` / `Security` / `Deprecated`, and no other heading.
  - Append the bullet at the end of its heading.
- **A bullet** is:
  - a bold lead sentence;
  - the PR or issue reference;
  - then one or two short sentences, aiming for 300 characters.

  A released heading reads `` ## [X.Y.Z] (YYYY-MM-DD) — `<short-sha>` ``, the sha being the release commit on `main`.

## Releases

1. **On `develop`,** through a dedicated `release: X.Y.Z` PR:
   - bump the three version sites;
   - rename `[Unreleased]` to `## [X.Y.Z] (YYYY-MM-DD)`;
   - add a fresh `[Unreleased]` above it;
   - read `git diff origin/main...origin/develop -- readme.txt languages/readme-pt_BR.txt`: a change on one side only is a translation left behind (see "Translations").
2. **Open the `develop → main` PR.** Keep it draft until the maintainer confirms the production release.
3. **After it lands on `main`,** tag that commit (`vX.Y.Z`) and push the tag. `.github/workflows/release.yml` then:
   - checks the tag against the header version;
   - builds the zip through `bin/build-release-zip.sh` and `.distignore`;
   - publishes a GitHub Release whose notes are that CHANGELOG section, marked as the latest. The plugin's updater reads `releases/latest`, and GitHub records the zip's SHA-256 digest, which the updater checks.

   The Lint workflow runs the same build against the newest tag on every PR, so a broken `.distignore` or CHANGELOG section fails there first.

   **Pushing the tag publishes a production release, so an agent hands that command to the maintainer and does not run it.** Verify `HEAD` equals `origin/main` before tagging.
4. **Sync `develop` with `main`:**
   - after a squash merge, reset it (`git reset --hard origin/main`, then a force-with-lease push);
   - after a merge commit, fast-forward it.
5. **Backfill the sha** through a normal PR to `develop`: append `` — `<short-sha>` `` to the new heading. This must come after step 4, which would discard it.

The zip ships no Composer or npm dependency. The autoloader is inline in the main plugin file. Anything new at the repository root that should not ship goes into `.distignore`.

## Quality gates

These run in CI on every PR and on pushes to `main` and `develop`:
- PHPUnit `unit` suite on PHP 8.3 and 8.4, and the functional suite against a real WordPress and MySQL (`phpunit.functional.xml.dist`). The functional suite runs on the latest WordPress and on **the declared floor**: the `floor` leg reads `Requires at least` from the plugin header, resolves it to that branch's newest patch release and swaps the WordPress test library to the same branch, so moving the floor moves the test. A floor nothing runs is a claim, not a requirement.
- PHPStan level 8, and PHPCS with WPCS and PHPCompatibilityWP.
- ESLint over `assets/js` and Stylelint over `assets/css`.
- **Verify minified assets are up to date**: CI runs `npm run build` and fails when an `assets/**/*.min.*` file changes or appears. Run `npm run build` after editing any file under `assets/css` or `assets/js`, and commit the `.min` and `.map` beside it.
- Vitest over `tests/js`. No script ships yet, so the run passes with no test; the first script under `assets/js` arrives with its tests, and the JS floor then counts it.
- `composer validate --strict` and `composer audit --locked`.
- The release build against the newest tag.
- Coverage floors. The PHP floor is `COVERAGE_FLOOR_LINES` in `tests.yml`, checked against the merged line coverage of the unit and functional suites (`bin/coverage-merge.php`). The JS floor is `thresholds.lines` in `vitest.config.mjs`, which counts every file in `assets/js`. The rule is the same in the four plugins (Free Form Certificate, Total Mail Queue, Email Signature Generator, Institutional Email) and here: **a floor is the highest of 90%, the measured figure minus 5 points, and its current value.** So it is never below 90%, never more than 5 points behind what is measured, and never lowered — the one exception is an honest re-measure after deleting covered product code, recorded beside the floor. Raise it in the PR that delivers the gain, and only once the measured figure is above it.

Two guards read the code rather than test it:
- `ReadmeUpgradeNoticeTest`: the Upgrade Notice holds one entry, for the Stable tag, at most 300 characters, pointing at `CHANGELOG.md`, and the updater's own parser returns it.
- `SuppressionTest`: every `phpcs:ignore` or `phpcs:disable` names a sniff and gives a reason after `--`; a `@phpstan-ignore` names an identifier with a reason, and the `-line` forms are refused.

A browser script under test runs through `runScript()` in `tests/js/helpers.js`, never `new Function()`, which V8 cannot attribute coverage to.

There is no deploy to the testes site yet: the plugin has nothing to see there until the app shell lands, and the deploy workflow arrives with it.

Locally:

```bash
composer install && npm ci
vendor/bin/phpunit --testsuite unit
# The functional suite needs MySQL. A cloud session's container ships MariaDB stopped;
# the environment's setup script starts it with the CI credentials (root / root).
service mariadb start
bash bin/install-wp-tests.sh wp_site_pwa_tests root root 127.0.0.1 false latest
vendor/bin/phpunit -c phpunit.functional.xml.dist
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpcs
npm run lint
npm run test:coverage
npm run build   # after editing assets/css or assets/js
```

## Updates

`Update\GithubUpdater` (ported from Institutional Email) feeds WordPress's own update check from `releases/latest`:

- **The asset.** It picks the `site-pwa-*.zip` asset.
- **The checksum.** It verifies the asset's SHA-256 digest before WordPress installs it.
- **"View details".** The tabs come from the offered release's `readme.txt` (or `languages/readme-pt_BR.txt` for a pt_BR administrator), with the banner and icon under `assets/img/brand/`. `GithubUpdater::SCREENSHOTS` is empty until a screen ships; a screenshot joins `docs/screenshots/`, that list and a caption in both readmes together (`PluginDetailsAssetsTest`).
- **The release's own metadata.** `Requires at least`, `Tested up to`, `Requires PHP` and the Upgrade Notice come from the offered release's own `readme.txt`.
- **Caching.** It caches for 12 hours in the `site_pwa_github_update` site transient, with ETag requests. `uninstall.php` deletes it.
- **Registration.** It is registered outside `is_admin()`, because WordPress also scans from cron.
- **The `releases/latest` consequence.** A release published by hand for an old tag must never be marked latest, which is why `release.yml` sets `make_latest: false` for `workflow_dispatch`.

## Translations

`languages/` holds the `.pot`, and per locale the `.po`, `.mo` and `.l10n.php`. After changing a translatable string, run `bin/i18n.sh` (needs GNU gettext): it re-extracts the `.pot`, merges it into each `.po` and recompiles. Translate the new entries in the `.po`, then run it again.

- **Never edit a compiled catalogue by hand**, and never compile one with a tool outside the repository. `msgfmt` writes the `.mo` and `bin/po-to-l10n.php` the `.l10n.php`; a fix that reaches one and not the other depends on which file WordPress loaded. Before #72 the committed `.l10n.php` had come from a script outside the repository, in a format the committed compiler did not write.
- **`bin/po-catalogue.php` is the one `.po` reader.** The compiler and the tests both use it, so they cannot disagree about what an entry is. It handles the shapes this catalogue does not carry yet (a context, a plural, a fuzzy entry); `PoCatalogueTest` exercises them on a synthetic file.
- **The `.l10n.php` is in `wp i18n make-php`'s shape, byte for byte** — the same keys in the same order Loco and WP-CLI write, so a catalogue compiled here, saved from Loco or made by WP-CLI is the same file. WP-CLI reads the text domain from the `.po`'s `X-Domain` header, which is why the header is there; keep it. The reader sits inside PHPStan's and PHPCS's paths like the plugin's own code, which is what found it typed wrong when it first went in (#74).
- **`TranslationCatalogueAgreementTest`** fails when the three files of a locale disagree, in either direction, or when the `.l10n.php` is not byte for byte what the compiler writes. Its self-check recounts the `.po`'s entries by a different route, so a reader that read nothing fails instead of passing.
- **`languages/readme-pt_BR.txt` is the readme "View details" shows a pt_BR administrator.** A PR that changes `readme.txt` updates it in the same PR. `PluginDetailsAssetsTest` holds the two to the same structure (tab sections, the count of other sections, the screenshot captions) and cannot see the content: a changed sentence in English passes CI with the translation still saying the old thing (#71).

## Code

- Namespace `SitePwa`; no global functions. A class maps to a kebab-case file under `src/`, and `AutoloaderTest` fails when one does not.
- **CSS lives in `assets/css` and JS in `assets/js`, never inline**, each minified by `npm run build` and enqueued as the `.min` build unless `SCRIPT_DEBUG` is on.
- Escape on output; check a nonce and a capability on every request entry point; sanitize every setting on save.
- **Every option the plugin writes is deleted in `uninstall.php`**, in the same change that adds it; `UninstallTest` fails on an option it was not told about.
- **No personal data in a cache.** The service worker caches the offline page and the app's stylesheet, and nothing else: an app page goes network-first and is never stored, because it may carry a user's data or a nonce. The offline page is built with no user data and no nonce for that reason, and `AppRoutesTest` pins it. This is a decision of the umbrella issue, not an optimisation to revisit.

## The app's routes and the service worker

- **Routes.** Every app page lives under one prefix, `/app/` unless `site_pwa_app_prefix` says otherwise; the worker script is `/site-pwa-sw.js` at the site root, because a worker reaches only the paths at and below its own file. `App\Routes` owns the rewrite rules. Bump `Routes::REVISION` when they change: an install updated from GitHub runs no activation hook, and the revision is what flushes them.
- **What the worker touches** is decided by `route()` in `assets/js/sw.js`, a pure function the Vitest suite drives: a navigation under the app path is answered network-first with the offline page as fallback; a precached file comes from the cache; everything else, posts, `wp-admin`, `wp-login`, the REST API and `admin-ajax` included, is not answered at all. A new kind of request the worker should handle starts with a case there.
- **Off means cleaned up, not abandoned.** While the app is off the worker URL keeps answering, with `assets/js/sw-retire.js`, which deletes the app's caches and unregisters itself. A 404 there would leave every installed copy running the last worker it saw.
- **One worker per root.** A known plugin that registers its own root worker (`App\Conflicts::KNOWN`, filterable) keeps the app off until the replacement is confirmed; the settings screen also asks the browser what it holds. A push service shares the root through `site_pwa_sw_imports`, kept only for same-site URLs and loaded with `importScripts()` before the app's code. The three outcomes are documented in both readmes.
- **The login URL is always `wp_login_url()`**; `LoginUrlTest` refuses `wp-login.php` in the PHP and the templates, because a site that moves its login page filters that function.
- **App pages are their own document** (`templates/`), printed without the theme and without `wp_head()` until the app frame replaces the placeholder home; their CSS and JS are linked directly, with a PHPCS ignore saying so.
