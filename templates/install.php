<?php
/**
 * The public install page, `/app/install/` (#34).
 *
 * The same document for everyone: no login, no user data, no nonce. Every
 * state is in the markup and hidden; assets/js/install.js shows the one that
 * fits this browser. Without JavaScript only the <noscript> hint shows.
 *
 * @package SitePwa
 *
 * @var array<string, string> $site_pwa Values built by Pages::render().
 */

use SitePwa\App\Icons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_pwa_copy = static function () use ( $site_pwa ): void {
	?>
	<p class="site-pwa-install-address">
		<input type="text" readonly value="<?php echo esc_url( $site_pwa['install_url'] ); ?>" aria-label="<?php esc_attr_e( 'Address of this page', 'site-pwa' ); ?>" data-address>
	</p>
	<p>
		<button type="button" class="site-pwa-button site-pwa-install-wide" data-copy>
			<?php echo Icons::svg( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
			<span><?php esc_html_e( 'Copy address', 'site-pwa' ); ?></span>
		</button>
	</p>
	<?php
};
?>
<!doctype html>
<html lang="<?php echo esc_attr( $site_pwa['lang'] ); ?>">
<head>
<?php require __DIR__ . '/head.php'; ?>
<title>
<?php
/* translators: %s: the app's name */
echo esc_html( sprintf( __( 'Install %s', 'site-pwa' ), $site_pwa['name'] ) );
?>
</title>
</head>
<body class="site-pwa-install-doc <?php echo esc_attr( \SitePwa\App\Scheme::bodyClass() ); ?>">
<main class="site-pwa-install-page" data-install-page data-home="<?php echo esc_url( $site_pwa['home'] ); ?>" data-copied="<?php esc_attr_e( 'Address copied.', 'site-pwa' ); ?>" data-copy-failed="<?php esc_attr_e( 'Select the address and copy it.', 'site-pwa' ); ?>">
	<header class="site-pwa-install-hero">
		<img src="<?php echo esc_url( $site_pwa['touch_icon'] ); ?>" alt="" width="96" height="96">
		<h1><?php echo esc_html( $site_pwa['name'] ); ?></h1>
		<p><?php echo esc_html( $site_pwa['pitch'] ); ?></p>
	</header>

	<div class="site-pwa-install-body">
		<section class="site-pwa-card" data-state="wait" hidden>
			<p class="site-pwa-muted"><?php esc_html_e( 'Checking this browser…', 'site-pwa' ); ?></p>
		</section>

		<section class="site-pwa-card" data-state="prompt" hidden>
			<p>
				<button type="button" class="site-pwa-button site-pwa-install-wide" data-install>
					<?php echo Icons::svg( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
					<span><?php esc_html_e( 'Install the app', 'site-pwa' ); ?></span>
				</button>
			</p>
			<p class="site-pwa-muted"><?php esc_html_e( 'The icon goes to your home screen. You sign in inside the app, once.', 'site-pwa' ); ?></p>
		</section>

		<section class="site-pwa-card" data-state="ios-safari" hidden>
			<h2><?php esc_html_e( 'Add it to your home screen in three taps', 'site-pwa' ); ?></h2>
			<ol class="site-pwa-steps">
				<li>
					<?php echo Icons::svg( 'share' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
					<span><?php esc_html_e( 'Tap Share in Safari’s toolbar, at the bottom.', 'site-pwa' ); ?></span>
				</li>
				<li>
					<?php echo Icons::svg( 'add' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
					<span><?php esc_html_e( 'Choose Add to Home Screen.', 'site-pwa' ); ?></span>
				</li>
				<li>
					<?php echo Icons::svg( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
					<span><?php esc_html_e( 'Tap Add.', 'site-pwa' ); ?></span>
				</li>
			</ol>
			<p class="site-pwa-muted"><?php esc_html_e( 'On iPhone no browser installs with a button; these steps are the official way.', 'site-pwa' ); ?></p>
		</section>

		<section class="site-pwa-card" data-state="ios-other" hidden>
			<h2><?php esc_html_e( 'Add it to your home screen in three taps', 'site-pwa' ); ?></h2>
			<ol class="site-pwa-steps">
				<li>
					<?php echo Icons::svg( 'share' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
					<span><?php esc_html_e( 'Tap Share, at the top, beside the address.', 'site-pwa' ); ?></span>
				</li>
				<li>
					<?php echo Icons::svg( 'add' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
					<span><?php esc_html_e( 'Choose Add to Home Screen. If it is not there, scroll the list or tap More.', 'site-pwa' ); ?></span>
				</li>
				<li>
					<?php echo Icons::svg( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
					<span><?php esc_html_e( 'Tap Add.', 'site-pwa' ); ?></span>
				</li>
			</ol>
			<p class="site-pwa-muted"><?php esc_html_e( 'On iPad and iPhone no browser installs with a button; these steps are the official way.', 'site-pwa' ); ?></p>
		</section>

		<section class="site-pwa-card" data-state="ios-old" hidden>
			<p><?php esc_html_e( 'On this version of iOS only Safari adds apps to the home screen. Open this address in Safari to install.', 'site-pwa' ); ?></p>
			<?php $site_pwa_copy(); ?>
		</section>

		<section class="site-pwa-card" data-state="installed" hidden>
			<p class="site-pwa-install-done">
				<?php echo Icons::svg( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
				<span><?php esc_html_e( 'Already installed on this device', 'site-pwa' ); ?></span>
			</p>
			<p>
				<a class="site-pwa-button site-pwa-install-wide" href="<?php echo esc_url( $site_pwa['home'] ); ?>">
					<?php echo Icons::svg( 'open' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
					<span><?php esc_html_e( 'Open the app', 'site-pwa' ); ?></span>
				</a>
			</p>
			<p class="site-pwa-muted">
				<?php
				/* translators: %s: the app's short name, as under its icon */
				echo esc_html( sprintf( __( 'It did not open? Look for the %s icon on your home screen.', 'site-pwa' ), $site_pwa['short_name'] ) );
				?>
			</p>
		</section>

		<section class="site-pwa-card" data-state="unsupported" hidden>
			<p><?php esc_html_e( 'This browser offers no install button. Open this address in Chrome (Android) or Safari (iPhone), or look for Install app or Add to Home screen in the browser’s menu.', 'site-pwa' ); ?></p>
			<?php $site_pwa_copy(); ?>
		</section>
		<noscript>
			<section class="site-pwa-card">
				<p><?php esc_html_e( 'Look for Install app or Add to Home screen in the browser’s menu.', 'site-pwa' ); ?></p>
			</section>
		</noscript>

		<p class="site-pwa-visually-hidden" role="status" data-copy-status></p>
		<p class="site-pwa-install-continue"><a href="<?php echo esc_url( $site_pwa['home'] ); ?>"><?php esc_html_e( 'Continue in the browser', 'site-pwa' ); ?></a></p>
	</div>
</main>
<script src="<?php echo esc_url( $site_pwa['install_js'] ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- the install page is its own document, printed without wp_head(). ?>" defer></script>
</body>
</html>
