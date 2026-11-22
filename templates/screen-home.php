<?php
/**
 * The home screen.
 *
 * @package SitePwa
 *
 * @var array{first_name: string, screens: list<array{id: string, label: string, icon: string}>, news: array{items: list<array{id: int, title: string, excerpt: string, date: string, image: string, url: string}>, pages: int}, news_url: string, pages: list<array{id: int, label: string, url: string, external: bool}>} $site_pwa Values built by Frame::render().
 */

use SitePwa\App\Icons;
use SitePwa\App\Screens;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h1 class="site-pwa-title">
	<?php
	/* translators: %s: the signed-in user's first name */
	echo esc_html( sprintf( __( 'Hello, %s', 'site-pwa' ), $site_pwa['first_name'] ) );
	?>
</h1>
<section class="site-pwa-card" aria-labelledby="site-pwa-screens-heading">
	<h2 id="site-pwa-screens-heading"><?php esc_html_e( 'Your screens', 'site-pwa' ); ?></h2>
	<?php if ( array() === $site_pwa['screens'] ) : ?>
		<p class="site-pwa-muted"><?php esc_html_e( 'Nothing here yet. The screens other plugins add to the app appear here.', 'site-pwa' ); ?></p>
	<?php else : ?>
		<?php
		$site_pwa_rows = array();
		foreach ( $site_pwa['screens'] as $site_pwa_screen ) {
			$site_pwa_rows[] = array(
				'label' => $site_pwa_screen['label'],
				'icon'  => $site_pwa_screen['icon'],
				'url'   => Screens::link( $site_pwa_screen ),
			);
		}
		require __DIR__ . '/partial-screen-list.php';
		?>
	<?php endif; ?>
</section>
<section class="site-pwa-card" aria-labelledby="site-pwa-news-heading">
	<h2 id="site-pwa-news-heading"><?php esc_html_e( 'Latest news', 'site-pwa' ); ?></h2>
	<?php if ( array() === $site_pwa['news']['items'] ) : ?>
		<p class="site-pwa-muted"><?php esc_html_e( 'No news yet.', 'site-pwa' ); ?></p>
	<?php else : ?>
		<?php
		$site_pwa_news = $site_pwa['news']['items'];
		require __DIR__ . '/partial-news-list.php';
		?>
		<p class="site-pwa-more-link"><a href="<?php echo esc_url( $site_pwa['news_url'] ); ?>"><?php esc_html_e( 'See all news', 'site-pwa' ); ?></a></p>
	<?php endif; ?>
</section>
<section class="site-pwa-card" aria-labelledby="site-pwa-pages-heading">
	<h2 id="site-pwa-pages-heading"><?php esc_html_e( 'Pages', 'site-pwa' ); ?></h2>
	<?php if ( array() === $site_pwa['pages'] ) : ?>
		<p class="site-pwa-muted"><?php esc_html_e( 'No pages yet. The pages chosen in the app\'s settings appear here.', 'site-pwa' ); ?></p>
	<?php else : ?>
		<?php
		$site_pwa_rows = array();
		foreach ( $site_pwa['pages'] as $site_pwa_page ) {
			$site_pwa_rows[] = array(
				'label' => $site_pwa_page['label'],
				'icon'  => 'document',
				'url'   => $site_pwa_page['url'],
				'end'   => $site_pwa_page['external'] ? 'open' : 'chevron',
			);
		}
		require __DIR__ . '/partial-screen-list.php';
		?>
	<?php endif; ?>
</section>
<section class="site-pwa-card site-pwa-install" data-key="site-pwa-install" aria-labelledby="site-pwa-install-heading" hidden>
	<button type="button" class="site-pwa-install-close" data-dismiss aria-label="<?php esc_attr_e( 'Not now', 'site-pwa' ); ?>">
		<?php echo Icons::svg( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
	</button>
	<h2 id="site-pwa-install-heading"><?php esc_html_e( 'Install the app', 'site-pwa' ); ?></h2>
	<div data-install-prompt hidden>
		<p><?php esc_html_e( 'Open it from your home screen, full screen, like any other app.', 'site-pwa' ); ?></p>
		<p class="site-pwa-actions">
			<button type="button" class="site-pwa-button" data-install>
				<?php echo Icons::svg( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
				<span><?php esc_html_e( 'Install app', 'site-pwa' ); ?></span>
			</button>
		</p>
	</div>
	<div data-install-ios hidden>
		<p><?php esc_html_e( 'Add it to your home screen to open it like any other app:', 'site-pwa' ); ?></p>
		<ol class="site-pwa-steps">
			<li>
				<?php echo Icons::svg( 'share' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
				<span><?php esc_html_e( 'Tap Share in Safari’s toolbar.', 'site-pwa' ); ?></span>
			</li>
			<li>
				<?php echo Icons::svg( 'add' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
				<span><?php esc_html_e( 'Choose Add to Home Screen.', 'site-pwa' ); ?></span>
			</li>
		</ol>
	</div>
</section>
