<?php
/**
 * News items as cards with an image, a title, the date and an excerpt.
 *
 * @package SitePwa
 *
 * @var list<array{id: int, title: string, excerpt: string, date: string, image: string, url: string}> $site_pwa_news Items to draw.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<ul class="site-pwa-news">
	<?php foreach ( $site_pwa_news as $site_pwa_item ) : ?>
		<li>
			<a href="<?php echo esc_url( $site_pwa_item['url'] ); ?>">
				<?php if ( '' !== $site_pwa_item['image'] ) : ?>
					<img src="<?php echo esc_url( $site_pwa_item['image'] ); ?>" alt="" loading="lazy">
				<?php endif; ?>
				<span class="site-pwa-news-text">
					<strong><?php echo esc_html( $site_pwa_item['title'] ); ?></strong>
					<span class="site-pwa-muted"><?php echo esc_html( $site_pwa_item['date'] ); ?></span>
					<?php if ( '' !== $site_pwa_item['excerpt'] ) : ?>
						<span class="site-pwa-news-excerpt"><?php echo esc_html( $site_pwa_item['excerpt'] ); ?></span>
					<?php endif; ?>
				</span>
			</a>
		</li>
	<?php endforeach; ?>
</ul>
