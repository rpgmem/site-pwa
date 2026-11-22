<?php
/**
 * Post-deploy smoke for the testes host.
 *
 * Runs on the testes server over SSH right after the rsync, boots the real
 * WordPress and checks what only the real host can answer: did the deploy
 * land, and is the plugin running there. It is an alarm, not a merge gate:
 * `deploy-develop.yml` runs on push to `develop`, after the merge.
 *
 * Checks:
 *   1. The plugin is active. Files landing in a deactivated plugin look
 *      healthy on disk and do nothing.
 *   2. The live SITE_PWA_VERSION equals the commit's. A deploy that silently
 *      did not land leaves the host on an older version (Free Form
 *      Certificate's #628).
 *   3. The app's rewrite rules are in the stored rules, so the service worker
 *      and the app's URLs answer instead of a 404.
 * It also reports, without judging, whether the app is switched on: that is
 * a setting, not a defect.
 *
 * Usage: php testes-smoke.php <absolute-plugin-dir> <expected-version>
 * Exit codes: 0 = pass or skipped, 1 = a check failed.
 *
 * @package SitePwa
 */

declare(strict_types=1);

// The plugin's floor. A managed host's CLI binary can be older than the
// PHP the site runs on; that is not the plugin's defect, so it skips.
const SITE_PWA_SMOKE_MIN_PHP = '8.3';

$site_pwa_dir      = rtrim( (string) ( $argv[1] ?? '' ), '/' );
$site_pwa_expected = (string) ( $argv[2] ?? '' );

echo "Site PWA post-deploy smoke\n";

if ( '' === $site_pwa_dir || '' === $site_pwa_expected ) {
	echo "  [FAIL] usage: php testes-smoke.php <plugin-dir> <expected-version>\n";
	exit( 1 );
}

if ( version_compare( PHP_VERSION, SITE_PWA_SMOKE_MIN_PHP, '<' ) ) {
	printf( "  SKIPPED: this host's PHP CLI is %s, below the plugin's floor %s. Point the workflow at a newer binary to enable the smoke.\n", PHP_VERSION, SITE_PWA_SMOKE_MIN_PHP );
	exit( 0 );
}

$site_pwa_load = dirname( $site_pwa_dir, 3 ) . '/wp-load.php';
if ( ! is_file( $site_pwa_load ) ) {
	printf( "  [FAIL] wp-load.php not found at %s\n", $site_pwa_load );
	exit( 1 );
}

// wp_die() on an unreachable database prints its page and exits 0, which
// would read as a pass with no check run. Any end before the verdict fails.
$site_pwa_done = false;
register_shutdown_function(
	static function () use ( &$site_pwa_done ): void {
		if ( ! $site_pwa_done ) {
			echo "\n  [FAIL] WordPress ended before the smoke finished (database unreachable, or a fatal while booting).\n";
			exit( 1 );
		}
	}
);

$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
define( 'WP_USE_THEMES', false );
require $site_pwa_load;
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$site_pwa_ok = true;

/**
 * Print one result and remember a failure.
 *
 * @param bool   $ok     Whether it passed.
 * @param string $label  Check name.
 * @param string $detail What was seen.
 */
$site_pwa_check = static function ( bool $ok, string $label, string $detail ) use ( &$site_pwa_ok ): void {
	printf( "  [%s] %-28s %s\n", $ok ? 'PASS' : 'FAIL', $label, $detail );
	$site_pwa_ok = $site_pwa_ok && $ok;
};

$site_pwa_active = is_plugin_active( 'site-pwa/site-pwa.php' );
$site_pwa_check( $site_pwa_active, 'plugin active', $site_pwa_active ? 'site-pwa/site-pwa.php' : 'not active: activate it once in Plugins' );

$site_pwa_live = defined( 'SITE_PWA_VERSION' ) ? (string) constant( 'SITE_PWA_VERSION' ) : '(not loaded)';
$site_pwa_check( $site_pwa_live === $site_pwa_expected, 'live version', sprintf( 'host %s, commit %s', $site_pwa_live, $site_pwa_expected ) );

$site_pwa_rules = get_option( 'rewrite_rules' );
// Matched on the rule's target, not its pattern: the pattern is built with
// preg_quote(), whose escaping is an implementation detail.
$site_pwa_has = is_array( $site_pwa_rules ) && in_array( 'index.php?site_pwa_route=sw', $site_pwa_rules, true );
if ( $site_pwa_active ) {
	$site_pwa_check( $site_pwa_has, 'rewrite rules', $site_pwa_has ? 'worker route present' : 'worker route missing from rewrite_rules' );
} else {
	printf( "  [SKIP] %-28s %s\n", 'rewrite rules', 'the plugin is not active, so it adds none' );
}

$site_pwa_enabled = class_exists( '\SitePwa\Settings' ) && \SitePwa\Settings::enabled();
printf( "  [INFO] %-28s %s\n", 'app switched on', $site_pwa_enabled ? 'yes' : 'no (Settings → Site PWA)' );

$site_pwa_done = true;
echo $site_pwa_ok ? "SMOKE PASSED\n" : "SMOKE FAILED\n";
exit( $site_pwa_ok ? 0 : 1 );
