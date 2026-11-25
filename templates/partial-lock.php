<?php
/**
 * The lock screen (#38): drawn hidden on every app screen, shown by the
 * head check before the first paint and by `assets/js/lock.js`. Its mode
 * decides what it asks for: unlock, turn the lock on (required), or the
 * warning for a phone without a screen lock.
 *
 * @package SitePwa
 *
 * @var array{icon: string, lock: array{logout: string}} $site_pwa Values built by Frame::render().
 */

use SitePwa\App\Icons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="site-pwa-lock" data-site-pwa-lock="overlay" data-mode="unlock" role="dialog" aria-modal="true" aria-labelledby="site-pwa-lock-title" hidden>
	<div class="site-pwa-lock-card">
		<img src="<?php echo esc_url( $site_pwa['icon'] ); ?>" alt="" width="56" height="56">
		<span class="site-pwa-lock-icon" aria-hidden="true"><?php echo Icons::svg( 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?></span>
		<h1 id="site-pwa-lock-title">
			<span class="site-pwa-lock-for-unlock"><?php esc_html_e( 'The app is locked', 'site-pwa' ); ?></span>
			<span class="site-pwa-lock-for-setup"><?php esc_html_e( 'Lock the app with your phone\'s screen lock', 'site-pwa' ); ?></span>
			<span class="site-pwa-lock-for-nolock"><?php esc_html_e( 'This phone has no screen lock', 'site-pwa' ); ?></span>
		</h1>
		<p class="site-pwa-lock-for-unlock"><?php esc_html_e( 'Unlock with your fingerprint, face or the phone\'s PIN.', 'site-pwa' ); ?></p>
		<p class="site-pwa-lock-for-setup"><?php esc_html_e( 'Required for your account. The phone asks for its own screen lock once, then every time the app opens or comes back after a few seconds away. No new password.', 'site-pwa' ); ?></p>
		<p class="site-pwa-lock-status" role="status" aria-live="polite" data-site-pwa-lock="status"></p>
		<button type="button" class="site-pwa-button site-pwa-lock-for-unlock" data-site-pwa-lock="unlock"><?php esc_html_e( 'Unlock', 'site-pwa' ); ?></button>
		<button type="button" class="site-pwa-button site-pwa-lock-for-setup" data-site-pwa-lock="setup"><?php esc_html_e( 'Turn the lock on', 'site-pwa' ); ?></button>
		<button type="button" class="site-pwa-button site-pwa-lock-for-nolock" data-site-pwa-lock="setup"><?php esc_html_e( 'Try again', 'site-pwa' ); ?></button>
		<a class="site-pwa-lock-signout" href="<?php echo esc_url( $site_pwa['lock']['logout'] ); ?>" data-site-pwa-lock="signout"><?php esc_html_e( 'Sign out and in again', 'site-pwa' ); ?></a>
	</div>
</div>
