<?php
/**
 * The app's frame: header, the screen and the bottom navigation.
 *
 * @package SitePwa
 *
 * @var array{name: string, icon: string, home: string, navigation: list<array{id: string, label: string, url: string, icon: string, current: bool}>, content: string} $site_pwa Values built by Frame::render().
 */

use SitePwa\App\Icons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php wp_head(); ?>
</head>
<body class="site-pwa-app <?php echo esc_attr( \SitePwa\App\Scheme::bodyClass() ); ?>">
<?php wp_body_open(); ?>
<a class="site-pwa-skip" href="#site-pwa-main"><?php esc_html_e( 'Skip to content', 'site-pwa' ); ?></a>
<header class="site-pwa-header">
	<a class="site-pwa-brand" href="<?php echo esc_url( $site_pwa['home'] ); ?>">
		<img src="<?php echo esc_url( $site_pwa['icon'] ); ?>" alt="" width="32" height="32">
		<span><?php echo esc_html( $site_pwa['name'] ); ?></span>
	</a>
</header>
<main id="site-pwa-main" class="site-pwa-main" tabindex="-1">
	<?php echo $site_pwa['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a screen template, built escaped. ?>
</main>
<nav class="site-pwa-nav" aria-label="<?php esc_attr_e( 'App', 'site-pwa' ); ?>">
	<ul>
		<?php foreach ( $site_pwa['navigation'] as $site_pwa_item ) : ?>
			<li>
				<a href="<?php echo esc_url( $site_pwa_item['url'] ); ?>"<?php echo $site_pwa_item['current'] ? ' aria-current="page"' : ''; ?>>
					<?php echo Icons::svg( $site_pwa_item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS, escaped there. ?>
					<span><?php echo esc_html( $site_pwa_item['label'] ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
<?php wp_footer(); ?>
</body>
</html>
