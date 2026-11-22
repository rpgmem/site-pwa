<?php
/**
 * A post the app cannot show: it does not exist, is not published, or the
 * signed-in user may not read it. One answer for all three, so the screen
 * does not tell which.
 *
 * @package SitePwa
 *
 * @var array{home: string} $site_pwa Values built by Frame::render().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h1 class="site-pwa-title"><?php esc_html_e( 'This page is not available', 'site-pwa' ); ?></h1>
<section class="site-pwa-card">
	<p><?php esc_html_e( 'It may have been removed, or you may not have access to it.', 'site-pwa' ); ?></p>
	<p><a class="site-pwa-button" href="<?php echo esc_url( $site_pwa['home'] ); ?>"><?php esc_html_e( 'Back to the home screen', 'site-pwa' ); ?></a></p>
</section>
