<?php
/**
 * The account screen: who is signed in, and the way out.
 *
 * @package SitePwa
 *
 * @var array{user: WP_User, profile_url: string, logout_url: string} $site_pwa Values built by Frame::render().
 */

use SitePwa\App\Icons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h1 class="site-pwa-title"><?php esc_html_e( 'Account', 'site-pwa' ); ?></h1>
<section class="site-pwa-card" aria-labelledby="site-pwa-account-heading">
	<h2 id="site-pwa-account-heading" class="site-pwa-visually-hidden"><?php esc_html_e( 'Signed in as', 'site-pwa' ); ?></h2>
	<dl class="site-pwa-facts">
		<dt><?php esc_html_e( 'Name', 'site-pwa' ); ?></dt>
		<dd><?php echo esc_html( $site_pwa['user']->display_name ); ?></dd>
		<dt><?php esc_html_e( 'Email', 'site-pwa' ); ?></dt>
		<dd><?php echo esc_html( $site_pwa['user']->user_email ); ?></dd>
	</dl>
	<p class="site-pwa-actions">
		<a class="site-pwa-button site-pwa-button-neutral" href="<?php echo esc_url( $site_pwa['profile_url'] ); ?>"><?php echo Icons::svg( 'edit' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?><?php esc_html_e( 'Edit profile', 'site-pwa' ); ?></a>
		<a class="site-pwa-button" href="<?php echo esc_url( $site_pwa['logout_url'] ); ?>"><?php echo Icons::svg( 'logout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?><?php esc_html_e( 'Sign out', 'site-pwa' ); ?></a>
	</p>
</section>
