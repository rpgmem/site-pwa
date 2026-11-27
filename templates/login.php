<?php
/**
 * The app's own sign-in screen, `/app/login/` (#74).
 *
 * Inside the app's scope, so signing in never opens WordPress's login under
 * the browser's bar. It posts to itself; AppLogin::response() signs the
 * person in with wp_signon() and returns to the app.
 *
 * @package SitePwa
 *
 * @var array<string, mixed> $site_pwa Values built by Pages::render() and AppLogin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!doctype html>
<html lang="<?php echo esc_attr( (string) $site_pwa['lang'] ); ?>">
<head>
<?php require __DIR__ . '/head.php'; ?>
<title>
<?php
/* translators: %s: the app's name */
echo esc_html( sprintf( __( 'Sign in to %s', 'site-pwa' ), (string) $site_pwa['name'] ) );
?>
</title>
</head>
<body class="site-pwa-install-doc <?php echo esc_attr( \SitePwa\App\Scheme::bodyClass() ); ?>">
<main class="site-pwa-install-page site-pwa-login-page">
	<header class="site-pwa-install-hero">
		<img src="<?php echo esc_url( (string) $site_pwa['touch_icon'] ); ?>" alt="" width="96" height="96">
		<h1><?php echo esc_html( (string) $site_pwa['name'] ); ?></h1>
		<?php if ( '' !== $site_pwa['tagline'] ) : ?>
			<p><?php echo esc_html( (string) $site_pwa['tagline'] ); ?></p>
		<?php endif; ?>
	</header>

	<div class="site-pwa-install-body">
		<form class="site-pwa-card site-pwa-login-form" method="post" action="<?php echo esc_url( (string) $site_pwa['action'] ); ?>">
			<?php if ( '' !== $site_pwa['error'] ) : ?>
				<p class="site-pwa-login-error" role="alert"><?php echo esc_html( (string) $site_pwa['error'] ); ?></p>
			<?php endif; ?>
			<p class="site-pwa-login-field">
				<label for="site-pwa-login-user"><?php esc_html_e( 'Username or email', 'site-pwa' ); ?></label>
				<input type="text" id="site-pwa-login-user" name="log" value="<?php echo esc_attr( (string) $site_pwa['login'] ); ?>" autocomplete="username" autocapitalize="none" spellcheck="false" required>
			</p>
			<p class="site-pwa-login-field">
				<label for="site-pwa-login-pass"><?php esc_html_e( 'Password', 'site-pwa' ); ?></label>
				<input type="password" id="site-pwa-login-pass" name="pwd" autocomplete="current-password" required>
			</p>
			<p class="site-pwa-login-remember">
				<input type="checkbox" id="site-pwa-login-remember" name="rememberme" value="forever"<?php checked( (bool) $site_pwa['remember'] ); ?>>
				<label for="site-pwa-login-remember"><?php esc_html_e( 'Keep me signed in on this device', 'site-pwa' ); ?></label>
			</p>
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( (string) $site_pwa['back'] ); ?>">
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( (string) $site_pwa['nonce'] ); ?>">
			<p>
				<button type="submit" class="site-pwa-button site-pwa-install-wide"><?php esc_html_e( 'Sign in', 'site-pwa' ); ?></button>
			</p>
			<p class="site-pwa-login-lost">
				<a href="<?php echo esc_url( (string) $site_pwa['lost'] ); ?>"><?php esc_html_e( 'Forgot your password?', 'site-pwa' ); ?></a>
			</p>
		</form>
		<p class="site-pwa-muted site-pwa-login-site">
			<a href="<?php echo esc_url( (string) $site_pwa['site_login'] ); ?>"><?php esc_html_e( 'Sign in on the site instead', 'site-pwa' ); ?></a>
		</p>
	</div>
</main>
</body>
</html>
