<?php
/**
 * A screen registered by another plugin: the app's heading, then the
 * consumer's markup.
 *
 * @package SitePwa
 *
 * @var array{screen: array{id: string, label: string}, screen_html: string} $site_pwa Values built by Frame::render().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h1 class="site-pwa-title"><?php echo esc_html( $site_pwa['screen']['label'] ); ?></h1>
<div class="site-pwa-screen site-pwa-screen-<?php echo esc_attr( $site_pwa['screen']['id'] ); ?>">
	<?php echo $site_pwa['screen_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the registering plugin's own markup, escaped by it, as a shortcode's is. ?>
</div>
