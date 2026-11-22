<?php
/**
 * The site's news, ten to a page.
 *
 * @package SitePwa
 *
 * @var array{news: array{items: list<array{id: int, title: string, excerpt: string, date: string, image: string, url: string}>, pages: int}, news_page: int} $site_pwa Values built by Frame::render().
 */

use SitePwa\App\News;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_pwa_page  = $site_pwa['news_page'];
$site_pwa_pages = $site_pwa['news']['pages'];
?>
<h1 class="site-pwa-title"><?php esc_html_e( 'News', 'site-pwa' ); ?></h1>
<?php if ( array() === $site_pwa['news']['items'] ) : ?>
	<section class="site-pwa-card">
		<p class="site-pwa-muted"><?php esc_html_e( 'No news yet.', 'site-pwa' ); ?></p>
	</section>
<?php else : ?>
	<section class="site-pwa-card site-pwa-card-list">
		<?php
		$site_pwa_news = $site_pwa['news']['items'];
		require __DIR__ . '/partial-news-list.php';
		?>
	</section>
	<?php if ( $site_pwa_pages > 1 ) : ?>
		<nav class="site-pwa-pager" aria-label="<?php esc_attr_e( 'News pages', 'site-pwa' ); ?>">
			<?php if ( $site_pwa_page > 1 ) : ?>
				<a class="site-pwa-button site-pwa-button-neutral" rel="prev" href="<?php echo esc_url( News::url( $site_pwa_page - 1 ) ); ?>"><?php esc_html_e( 'Newer news', 'site-pwa' ); ?></a>
			<?php endif; ?>
			<span class="site-pwa-muted">
				<?php
				/* translators: 1: the current page number, 2: the number of pages */
				echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'site-pwa' ), $site_pwa_page, $site_pwa_pages ) );
				?>
			</span>
			<?php if ( $site_pwa_page < $site_pwa_pages ) : ?>
				<a class="site-pwa-button site-pwa-button-neutral" rel="next" href="<?php echo esc_url( News::url( $site_pwa_page + 1 ) ); ?>"><?php esc_html_e( 'Older news', 'site-pwa' ); ?></a>
			<?php endif; ?>
		</nav>
	<?php endif; ?>
<?php endif; ?>
