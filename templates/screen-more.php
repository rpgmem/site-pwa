<?php
/**
 * More: the news, the chosen pages, the screens that are not in the bottom
 * navigation, then the account.
 *
 * @package SitePwa
 *
 * @var array{more: list<array{id: string, label: string, icon: string}>, account_url: string, news_url: string, pages: list<array{id: int, label: string, url: string, external: bool}>} $site_pwa Values built by Frame::render().
 */

use SitePwa\App\Screens;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_pwa_rows = array(
	array(
		'label' => __( 'News', 'site-pwa' ),
		'icon'  => 'news',
		'url'   => $site_pwa['news_url'],
	),
);
foreach ( $site_pwa['pages'] as $site_pwa_page ) {
	$site_pwa_rows[] = array(
		'label' => $site_pwa_page['label'],
		'icon'  => 'document',
		'url'   => $site_pwa_page['url'],
		'end'   => $site_pwa_page['external'] ? 'open' : 'chevron',
	);
}
foreach ( $site_pwa['more'] as $site_pwa_screen ) {
	$site_pwa_rows[] = array(
		'label' => $site_pwa_screen['label'],
		'icon'  => $site_pwa_screen['icon'],
		'url'   => Screens::link( $site_pwa_screen ),
	);
}
$site_pwa_rows[] = array(
	'label' => __( 'Account', 'site-pwa' ),
	'icon'  => 'user',
	'url'   => $site_pwa['account_url'],
);
?>
<h1 class="site-pwa-title"><?php esc_html_e( 'More', 'site-pwa' ); ?></h1>
<nav class="site-pwa-card site-pwa-card-list" aria-label="<?php esc_attr_e( 'More', 'site-pwa' ); ?>">
	<?php require __DIR__ . '/partial-screen-list.php'; ?>
</nav>
