<?php
/**
 * The app's frame: header, the screen and the bottom navigation.
 *
 * @package SitePwa
 *
 * @var array{name: string, icon: string, home: string, route: string, greeting: string, user_name: string, pending: int, back: string, navigation: list<array{id: string, label: string, url: string, icon: string, raised: bool, current: bool}>, content: string, lock: array{logout: string}} $site_pwa Values built by Frame::render().
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
<?php echo \SitePwa\App\Frame::template( 'partial-lock', $site_pwa ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a template, built escaped. ?>
<a class="site-pwa-skip" href="#site-pwa-main"><?php esc_html_e( 'Skip to content', 'site-pwa' ); ?></a>
<?php if ( 'home' === $site_pwa['route'] ) : ?>
<header class="site-pwa-header site-pwa-header-home">
	<a class="site-pwa-logo" href="<?php echo esc_url( $site_pwa['home'] ); ?>">
		<img src="<?php echo esc_url( $site_pwa['icon'] ); ?>" alt="<?php echo esc_attr( $site_pwa['name'] ); ?>" width="40" height="40">
	</a>
	<p class="site-pwa-hello">
		<span><?php echo esc_html( $site_pwa['greeting'] ); ?></span>
		<strong><?php echo esc_html( $site_pwa['user_name'] ); ?></strong>
	</p>
	<a class="site-pwa-bell" href="#site-pwa-pending-heading">
		<?php echo Icons::svg( 'bell' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
		<span class="site-pwa-visually-hidden">
			<?php
			echo esc_html(
				0 === $site_pwa['pending']
					? __( 'Notifications: nothing pending', 'site-pwa' )
					/* translators: %d: number of pending items */
					: sprintf( __( 'Notifications, pending items: %d', 'site-pwa' ), $site_pwa['pending'] )
			);
			?>
		</span>
	<?php if ( $site_pwa['pending'] > 0 ) : ?>
		<span class="site-pwa-badge" aria-hidden="true"></span>
	<?php endif; ?>
	</a>
</header>
<?php elseif ( '' !== $site_pwa['back'] ) : ?>
<header class="site-pwa-header">
	<a class="site-pwa-back" href="<?php echo esc_url( $site_pwa['back'] ); ?>">
		<?php echo Icons::svg( 'back' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
		<span><?php esc_html_e( 'Back', 'site-pwa' ); ?></span>
	</a>
</header>
<?php endif; ?>
<main id="site-pwa-main" class="site-pwa-main" tabindex="-1">
	<?php echo $site_pwa['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a screen template, built escaped. ?>
</main>
<nav class="site-pwa-nav" aria-label="<?php esc_attr_e( 'App', 'site-pwa' ); ?>">
	<ul>
		<?php foreach ( $site_pwa['navigation'] as $site_pwa_item ) : ?>
			<li<?php echo $site_pwa_item['raised'] ? ' class="site-pwa-nav-raised"' : ''; ?>>
				<a href="<?php echo esc_url( $site_pwa_item['url'] ); ?>"<?php echo $site_pwa_item['current'] ? ' aria-current="page"' : ''; ?>>
					<?php if ( $site_pwa_item['raised'] ) : ?>
						<span class="site-pwa-nav-lift"><?php echo Icons::svg( $site_pwa_item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS, escaped there. ?></span>
					<?php else : ?>
						<?php echo Icons::svg( $site_pwa_item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS, escaped there. ?>
					<?php endif; ?>
					<span><?php echo esc_html( $site_pwa_item['label'] ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
<?php wp_footer(); ?>
</body>
</html>
