<?php
/**
 * The home screen.
 *
 * @package SitePwa
 *
 * @var array{home_items: array{banners: list<array{id: string, text: string, emphasis: string, tone: string}>, next: array{title: string, meta: string, actions: list<array{label: string, url: string, icon: string, primary: bool}>}|null, pending: list<array{label: string, meta: string, url: string, icon: string, tone: string}>}, news: array{items: list<array{id: int, title: string, excerpt: string, date: string, category: string, image: string, url: string}>, pages: int}, news_url: string, pages: list<array{id: int, label: string, url: string, external: bool}>} $site_pwa Values built by Frame::render().
 */

use SitePwa\App\Icons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_pwa_home = $site_pwa['home_items'];
?>
<h1 class="site-pwa-visually-hidden"><?php esc_html_e( 'Home', 'site-pwa' ); ?></h1>
<div class="site-pwa-home">
	<div class="site-pwa-banner site-pwa-tone-warning site-pwa-lock-nudge" data-site-pwa-lock-nudge data-mode="invite" hidden>
		<?php echo Icons::svg( 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
		<span>
			<span class="site-pwa-lock-for-invite"><?php esc_html_e( 'Lock the app with your phone\'s screen lock, so nobody else opens it on this phone.', 'site-pwa' ); ?></span>
			<span class="site-pwa-lock-for-nolock"><?php esc_html_e( 'This phone has no screen lock. That is highly unsafe: anyone holding the phone opens the app and everything in it. Turn the screen lock on in the phone\'s settings.', 'site-pwa' ); ?></span>
			<button type="button" class="site-pwa-link-button site-pwa-lock-for-invite" data-site-pwa-lock-on><?php esc_html_e( 'Turn it on', 'site-pwa' ); ?></button>
		</span>
		<button type="button" class="site-pwa-dismiss" data-dismiss aria-label="<?php esc_attr_e( 'Dismiss', 'site-pwa' ); ?>">
			<?php echo Icons::svg( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
		</button>
	</div>
	<?php foreach ( $site_pwa_home['banners'] as $site_pwa_banner ) : ?>
		<div class="site-pwa-banner site-pwa-tone-<?php echo esc_attr( $site_pwa_banner['tone'] ); ?>" role="status" data-banner="<?php echo esc_attr( $site_pwa_banner['id'] ); ?>">
			<?php echo Icons::svg( 'success' === $site_pwa_banner['tone'] ? 'check' : 'bell' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
			<span>
				<?php
				if ( '' === $site_pwa_banner['emphasis'] ) {
					echo esc_html( $site_pwa_banner['text'] );
				} else {
					list( $site_pwa_before, $site_pwa_after ) = explode( '%s', $site_pwa_banner['text'], 2 );
					echo esc_html( $site_pwa_before ) . '<strong>' . esc_html( $site_pwa_banner['emphasis'] ) . '</strong>' . esc_html( $site_pwa_after );
				}
				?>
			</span>
			<button type="button" class="site-pwa-dismiss" data-dismiss aria-label="<?php esc_attr_e( 'Dismiss', 'site-pwa' ); ?>">
				<?php echo Icons::svg( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
			</button>
		</div>
	<?php endforeach; ?>

	<?php if ( null !== $site_pwa_home['next'] ) : ?>
		<section class="site-pwa-panel site-pwa-next" aria-labelledby="site-pwa-next-heading">
			<h2 id="site-pwa-next-heading"><?php esc_html_e( 'Next commitment', 'site-pwa' ); ?></h2>
			<div>
				<p class="site-pwa-next-title"><?php echo esc_html( $site_pwa_home['next']['title'] ); ?></p>
				<?php if ( '' !== $site_pwa_home['next']['meta'] ) : ?>
					<p class="site-pwa-next-meta"><?php echo esc_html( $site_pwa_home['next']['meta'] ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( array() !== $site_pwa_home['next']['actions'] ) : ?>
				<p class="site-pwa-actions">
					<?php foreach ( $site_pwa_home['next']['actions'] as $site_pwa_action ) : ?>
						<a class="site-pwa-button<?php echo $site_pwa_action['primary'] ? '' : ' site-pwa-button-neutral'; ?>" href="<?php echo esc_url( $site_pwa_action['url'] ); ?>">
							<?php echo Icons::svg( $site_pwa_action['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS; '' draws nothing. ?>
							<span><?php echo esc_html( $site_pwa_action['label'] ); ?></span>
						</a>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php // Always drawn: the bell in the header leads here, with or without items. ?>
	<section class="site-pwa-section" aria-labelledby="site-pwa-pending-heading">
		<div class="site-pwa-section-head">
			<h2 id="site-pwa-pending-heading" tabindex="-1"><?php esc_html_e( 'Pending', 'site-pwa' ); ?></h2>
		</div>
		<?php if ( array() === $site_pwa_home['pending'] ) : ?>
			<div class="site-pwa-panel">
				<p class="site-pwa-muted site-pwa-panel-note"><?php esc_html_e( 'Nothing pending.', 'site-pwa' ); ?></p>
			</div>
		<?php else : ?>
		<ul class="site-pwa-panel site-pwa-pending">
			<?php foreach ( $site_pwa_home['pending'] as $site_pwa_item ) : ?>
				<li>
					<a href="<?php echo esc_url( $site_pwa_item['url'] ); ?>">
						<span class="site-pwa-pending-icon site-pwa-tone-<?php echo esc_attr( $site_pwa_item['tone'] ); ?>"><?php echo Icons::svg( $site_pwa_item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?></span>
						<span class="site-pwa-pending-text">
							<strong><?php echo esc_html( $site_pwa_item['label'] ); ?></strong>
							<?php if ( '' !== $site_pwa_item['meta'] ) : ?>
								<span><?php echo esc_html( $site_pwa_item['meta'] ); ?></span>
							<?php endif; ?>
						</span>
						<?php echo Icons::svg( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php endif; ?>
	</section>

	<section class="site-pwa-section" aria-labelledby="site-pwa-news-heading">
		<div class="site-pwa-section-head">
			<h2 id="site-pwa-news-heading"><?php esc_html_e( 'News', 'site-pwa' ); ?></h2>
			<?php if ( array() !== $site_pwa['news']['items'] ) : ?>
				<a href="<?php echo esc_url( $site_pwa['news_url'] ); ?>"><?php esc_html_e( 'See all', 'site-pwa' ); ?></a>
			<?php endif; ?>
		</div>
		<div class="site-pwa-panel">
			<?php if ( array() === $site_pwa['news']['items'] ) : ?>
				<p class="site-pwa-muted site-pwa-panel-note"><?php esc_html_e( 'No news yet.', 'site-pwa' ); ?></p>
			<?php else : ?>
				<?php
				$site_pwa_news = $site_pwa['news']['items'];
				require __DIR__ . '/partial-news-list.php';
				?>
			<?php endif; ?>
		</div>
	</section>
</div>
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
