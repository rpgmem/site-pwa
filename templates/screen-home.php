<?php
/**
 * The home screen.
 *
 * @package SitePwa
 *
 * @var array{first_name: string} $site_pwa Values built by Frame::render().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h1 class="site-pwa-title">
	<?php
	/* translators: %s: the signed-in user's first name */
	echo esc_html( sprintf( __( 'Hello, %s', 'site-pwa' ), $site_pwa['first_name'] ) );
	?>
</h1>
<section class="site-pwa-card" aria-labelledby="site-pwa-screens-heading">
	<h2 id="site-pwa-screens-heading"><?php esc_html_e( 'Your screens', 'site-pwa' ); ?></h2>
	<p class="site-pwa-muted"><?php esc_html_e( 'Nothing here yet. The screens other plugins add to the app appear here.', 'site-pwa' ); ?></p>
</section>
