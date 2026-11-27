<?php
/**
 * A screen registered by another plugin: the app's heading, then the
 * consumer's markup.
 *
 * @package SitePwa
 *
 * @var array{screen: array{id: string, label: string}, screen_html: string, gated: bool, logout_url: string} $site_pwa Values built by Frame::render().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h1 class="site-pwa-title"><?php echo esc_html( $site_pwa['screen']['label'] ); ?></h1>
<?php if ( $site_pwa['gated'] ) : ?>
	<p class="site-pwa-required-note"><?php esc_html_e( 'Complete this step to go on using the app.', 'site-pwa' ); ?></p>
<?php endif; ?>
<div class="site-pwa-screen site-pwa-screen-<?php echo esc_attr( $site_pwa['screen']['id'] ); ?>">
	<?php echo $site_pwa['screen_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the registering plugin's own markup, escaped by it, as a shortcode's is. ?>
</div>
<?php if ( $site_pwa['gated'] ) : ?>
	<p class="site-pwa-required-signout"><a href="<?php echo esc_url( $site_pwa['logout_url'] ); ?>"><?php esc_html_e( 'Sign out', 'site-pwa' ); ?></a></p>
<?php endif; ?>
