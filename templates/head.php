<?php
/**
 * The head every app page shares.
 *
 * @package SitePwa
 *
 * @var array<string, string> $site_pwa Values built by Pages::render().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="<?php echo esc_attr( $site_pwa['theme_color'] ); ?>">
<meta name="robots" content="noindex">
<link rel="manifest" href="<?php echo esc_url( $site_pwa['manifest'] ); ?>">
<link rel="apple-touch-icon" href="<?php echo esc_url( $site_pwa['touch_icon'] ); ?>">
<link rel="stylesheet" href="<?php echo esc_url( $site_pwa['stylesheet'] ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- the app page is its own document, printed without wp_head(). ?>">
<style><?php echo esc_html( $site_pwa['identity'] ); ?></style>
<script src="<?php echo esc_url( $site_pwa['register'] ); ?>" data-sw="<?php echo esc_url( $site_pwa['sw'] ); ?>" data-scope="<?php echo esc_attr( $site_pwa['scope'] ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- the app page is its own document, printed without wp_head(). ?>" defer></script>
