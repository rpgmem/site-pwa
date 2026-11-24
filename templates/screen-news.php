<?php
/**
 * The site's news, ten to a page: category chips, the newest as a featured
 * card, then the rest as text cards.
 *
 * @package SitePwa
 *
 * @var array{news: array{items: list<array{id: int, title: string, excerpt: string, date: string, category: string, image: string, url: string}>, pages: int}, news_page: int, news_categories: list<array{id: int, name: string, slug: string}>, news_category: array{id: int, name: string, slug: string}|null} $site_pwa Values built by Frame::render().
 */

use SitePwa\App\News;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_pwa_page  = $site_pwa['news_page'];
$site_pwa_pages = $site_pwa['news']['pages'];
$site_pwa_slug  = null === $site_pwa['news_category'] ? '' : $site_pwa['news_category']['slug'];
$site_pwa_items = $site_pwa['news']['items'];
?>
<h1 class="site-pwa-title"><?php esc_html_e( 'News', 'site-pwa' ); ?></h1>
<?php if ( array() !== $site_pwa['news_categories'] ) : ?>
	<nav class="site-pwa-chips" aria-label="<?php esc_attr_e( 'News categories', 'site-pwa' ); ?>">
		<a href="<?php echo esc_url( News::url() ); ?>"<?php echo '' === $site_pwa_slug ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'All', 'site-pwa' ); ?></a>
		<?php foreach ( $site_pwa['news_categories'] as $site_pwa_category ) : ?>
			<a href="<?php echo esc_url( News::url( 1, $site_pwa_category['slug'] ) ); ?>"<?php echo $site_pwa_category['slug'] === $site_pwa_slug ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $site_pwa_category['name'] ); ?></a>
		<?php endforeach; ?>
	</nav>
<?php endif; ?>
<?php if ( array() === $site_pwa_items ) : ?>
	<p class="site-pwa-panel site-pwa-panel-note site-pwa-muted"><?php esc_html_e( 'No news yet.', 'site-pwa' ); ?></p>
<?php else : ?>
	<div class="site-pwa-stories">
		<?php foreach ( $site_pwa_items as $site_pwa_index => $site_pwa_item ) : ?>
			<?php
			$site_pwa_featured = 0 === $site_pwa_index && 1 === $site_pwa_page;
			$site_pwa_meta     = implode( ' · ', array_filter( array( $site_pwa_item['date'], $site_pwa_item['category'] ), static fn( string $part ): bool => '' !== $part ) );
			?>
			<a class="site-pwa-panel site-pwa-story<?php echo $site_pwa_featured ? ' site-pwa-story-featured' : ''; ?>" href="<?php echo esc_url( $site_pwa_item['url'] ); ?>">
				<?php if ( $site_pwa_featured && '' !== $site_pwa_item['image'] ) : ?>
					<img src="<?php echo esc_url( $site_pwa_item['image'] ); ?>" alt="" loading="lazy">
				<?php endif; ?>
				<span class="site-pwa-story-text">
					<span class="site-pwa-story-meta"><?php echo esc_html( $site_pwa_meta ); ?></span>
					<strong><?php echo esc_html( $site_pwa_item['title'] ); ?></strong>
					<?php if ( $site_pwa_featured && '' !== $site_pwa_item['excerpt'] ) : ?>
						<span class="site-pwa-news-excerpt"><?php echo esc_html( $site_pwa_item['excerpt'] ); ?></span>
					<?php endif; ?>
				</span>
			</a>
		<?php endforeach; ?>
	</div>
	<?php if ( $site_pwa_pages > 1 ) : ?>
		<nav class="site-pwa-pager" aria-label="<?php esc_attr_e( 'News pages', 'site-pwa' ); ?>">
			<?php if ( $site_pwa_page > 1 ) : ?>
				<a class="site-pwa-button site-pwa-button-neutral" rel="prev" href="<?php echo esc_url( News::url( $site_pwa_page - 1, $site_pwa_slug ) ); ?>"><?php esc_html_e( 'Newer news', 'site-pwa' ); ?></a>
			<?php endif; ?>
			<span class="site-pwa-muted">
				<?php
				/* translators: 1: the current page number, 2: the number of pages */
				echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'site-pwa' ), $site_pwa_page, $site_pwa_pages ) );
				?>
			</span>
			<?php if ( $site_pwa_page < $site_pwa_pages ) : ?>
				<a class="site-pwa-button site-pwa-button-neutral" rel="next" href="<?php echo esc_url( News::url( $site_pwa_page + 1, $site_pwa_slug ) ); ?>"><?php esc_html_e( 'Older news', 'site-pwa' ); ?></a>
			<?php endif; ?>
		</nav>
	<?php endif; ?>
<?php endif; ?>
