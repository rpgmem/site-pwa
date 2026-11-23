<?php
/**
 * The page the service worker shows without a connection.
 *
 * The worker keeps a copy of it, so it carries no user data and no nonce.
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
<title><?php esc_html_e( 'No connection', 'site-pwa' ); ?></title>
</head>
<body class="site-pwa <?php echo esc_attr( \SitePwa\App\Scheme::bodyClass() ); ?>">
<main class="site-pwa-card">
	<h1><?php esc_html_e( 'No connection', 'site-pwa' ); ?></h1>
	<p><?php esc_html_e( 'This page needs the internet. Check the connection and try again.', 'site-pwa' ); ?></p>
	<p><a class="site-pwa-button" href="<?php echo esc_url( $site_pwa['home'] ); ?>"><?php esc_html_e( 'Try again', 'site-pwa' ); ?></a></p>
</main>
</body>
</html>
