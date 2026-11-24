<?php
/**
 * The "Install" banner on the site's own pages, leading to the install page.
 * Hidden until assets/js/install.js finds a phone outside the installed app.
 *
 * @package SitePwa
 *
 * @var array{name: string, text: string, url: string, icon: string} $site_pwa Values built by Invite::banner().
 */

use SitePwa\App\Icons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<aside class="site-pwa-site-banner" data-key="site-pwa-banner" aria-label="<?php esc_attr_e( 'App', 'site-pwa' ); ?>" hidden>
	<img src="<?php echo esc_url( $site_pwa['icon'] ); ?>" alt="" width="40" height="40">
	<p>
		<strong><?php echo esc_html( $site_pwa['name'] ); ?></strong>
		<span><?php echo esc_html( $site_pwa['text'] ); ?></span>
	</p>
	<a class="site-pwa-site-banner-open" href="<?php echo esc_url( $site_pwa['url'] ); ?>"><?php esc_html_e( 'Install', 'site-pwa' ); ?></a>
	<button type="button" class="site-pwa-site-banner-close" data-dismiss aria-label="<?php esc_attr_e( 'Not now', 'site-pwa' ); ?>">
		<?php echo Icons::svg( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
	</button>
</aside>
