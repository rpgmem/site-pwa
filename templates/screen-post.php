<?php
/**
 * A post or page drawn inside the app.
 *
 * The main query already holds the post (Content::request()), so the loop
 * runs as on the site and shortcodes see the post they belong to. Every link
 * that leaves the app carries the permalink, never the app URL.
 *
 * @package SitePwa
 *
 * @var array{permalink: string, post_title: string} $site_pwa Values built by Frame::render().
 */

use SitePwa\App\Icons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

while ( have_posts() ) :
	the_post();
	?>
	<article class="site-pwa-article">
		<h1 class="site-pwa-title"><?php echo esc_html( wp_strip_all_tags( get_the_title() ) ); ?></h1>
		<div class="site-pwa-content">
			<?php the_content(); ?>
		</div>
	</article>
	<?php
endwhile;
?>
<div class="site-pwa-share site-pwa-actions" data-url="<?php echo esc_url( $site_pwa['permalink'] ); ?>" data-title="<?php echo esc_attr( $site_pwa['post_title'] ); ?>" data-copied="<?php esc_attr_e( 'Link copied.', 'site-pwa' ); ?>" data-copy-failed="<?php esc_attr_e( 'Copy this link:', 'site-pwa' ); ?>">
	<button type="button" class="site-pwa-button" data-share hidden>
		<?php echo Icons::svg( 'share' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS, escaped there. ?>
		<span><?php esc_html_e( 'Share', 'site-pwa' ); ?></span>
	</button>
	<button type="button" class="site-pwa-button site-pwa-button-neutral" data-copy>
		<?php echo Icons::svg( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS, escaped there. ?>
		<span><?php esc_html_e( 'Copy link', 'site-pwa' ); ?></span>
	</button>
	<a class="site-pwa-button site-pwa-button-neutral" href="<?php echo esc_url( $site_pwa['permalink'] ); ?>">
		<?php echo Icons::svg( 'open' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS, escaped there. ?>
		<span><?php esc_html_e( 'Open on the site', 'site-pwa' ); ?></span>
	</a>
	<p class="site-pwa-muted site-pwa-share-status" role="status" data-status></p>
</div>
