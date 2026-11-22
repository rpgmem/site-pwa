<?php
/**
 * The app's home page: a placeholder until the app frame replaces it.
 *
 * @package SitePwa
 *
 * @var array<string, string> $site_pwa Values built by Pages::render().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!doctype html>
<html lang="<?php echo esc_attr( $site_pwa['lang'] ); ?>">
<head>
<?php require __DIR__ . '/head.php'; ?>
<title><?php echo esc_html( $site_pwa['name'] ); ?></title>
</head>
<body class="site-pwa">
<main class="site-pwa-card">
	<h1><?php echo esc_html( $site_pwa['name'] ); ?></h1>
	<?php if ( '' !== $site_pwa['user_name'] ) : ?>
		<p>
			<?php
			/* translators: %s: the signed-in user's display name */
			echo esc_html( sprintf( __( 'Signed in as %s.', 'site-pwa' ), $site_pwa['user_name'] ) );
			?>
		</p>
		<p><a class="site-pwa-button" href="<?php echo esc_url( $site_pwa['logout_url'] ); ?>"><?php esc_html_e( 'Sign out', 'site-pwa' ); ?></a></p>
	<?php else : ?>
		<p><?php esc_html_e( 'Sign in to use the app.', 'site-pwa' ); ?></p>
		<p><a class="site-pwa-button" href="<?php echo esc_url( $site_pwa['login_url'] ); ?>"><?php esc_html_e( 'Sign in', 'site-pwa' ); ?></a></p>
	<?php endif; ?>
</main>
</body>
</html>
