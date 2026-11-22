<?php
/**
 * A list of screens as rows with an icon, a label and a chevron.
 *
 * @package SitePwa
 *
 * @var list<array{label: string, icon: string, url: string}> $site_pwa_rows Rows to draw.
 */

use SitePwa\App\Icons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<ul class="site-pwa-list">
	<?php foreach ( $site_pwa_rows as $site_pwa_row ) : ?>
		<li>
			<a href="<?php echo esc_url( $site_pwa_row['url'] ); ?>">
				<?php echo Icons::svg( $site_pwa_row['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
				<span><?php echo esc_html( $site_pwa_row['label'] ); ?></span>
				<?php echo Icons::svg( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
			</a>
		</li>
	<?php endforeach; ?>
</ul>
