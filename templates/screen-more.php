<?php
/**
 * More: the person, then what they use (Preferences right after the
 * screens other plugins add, then News), what they do as an operator, and
 * the way out. The site's pages are on Home, so they are not repeated here.
 *
 * @package SitePwa
 *
 * @var array{more: list<array{id: string, label: string, icon: string, section: string, subtitle: string, badge: callable|string, render: callable|null, url: string}>, profile: array{initials: string, name: string, line: string}, account_url: string, preferences_url: string, logout_url: string, news_url: string} $site_pwa Values built by Frame::render().
 */

use SitePwa\App\Icons;
use SitePwa\App\Screens;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_pwa_services = array();
$site_pwa_operator = array();
foreach ( $site_pwa['more'] as $site_pwa_screen ) {
	$site_pwa_row = array(
		'label'    => $site_pwa_screen['label'],
		'icon'     => $site_pwa_screen['icon'],
		'url'      => Screens::link( $site_pwa_screen ),
		'subtitle' => $site_pwa_screen['subtitle'],
		'badge'    => Screens::badge( $site_pwa_screen ),
	);
	if ( 'operator' === $site_pwa_screen['section'] ) {
		$site_pwa_operator[] = $site_pwa_row;
	} else {
		$site_pwa_services[] = $site_pwa_row;
	}
}
$site_pwa_services[] = array(
	'label'    => __( 'Preferences', 'site-pwa' ),
	'icon'     => 'sliders',
	'url'      => $site_pwa['preferences_url'],
	'subtitle' => __( 'Appearance and screen lock', 'site-pwa' ),
	'badge'    => '',
);
$site_pwa_services[] = array(
	'label'    => __( 'News', 'site-pwa' ),
	'icon'     => 'news',
	'url'      => $site_pwa['news_url'],
	'subtitle' => '',
	'badge'    => '',
);

$site_pwa_groups = array(
	'services' => array( __( 'Services', 'site-pwa' ), $site_pwa_services ),
	'operator' => array( __( 'Operator', 'site-pwa' ), $site_pwa_operator ),
);
?>
<h1 class="site-pwa-title"><?php esc_html_e( 'More', 'site-pwa' ); ?></h1>
<div class="site-pwa-more">
	<section class="site-pwa-panel site-pwa-profile" aria-label="<?php esc_attr_e( 'Your account', 'site-pwa' ); ?>">
		<span class="site-pwa-avatar" aria-hidden="true"><?php echo esc_html( $site_pwa['profile']['initials'] ); ?></span>
		<span class="site-pwa-profile-text">
			<strong><?php echo esc_html( $site_pwa['profile']['name'] ); ?></strong>
			<?php if ( '' !== $site_pwa['profile']['line'] ) : ?>
				<span><?php echo esc_html( $site_pwa['profile']['line'] ); ?></span>
			<?php endif; ?>
		</span>
		<a class="site-pwa-profile-link" href="<?php echo esc_url( $site_pwa['account_url'] ); ?>"><?php esc_html_e( 'My details', 'site-pwa' ); ?></a>
	</section>

	<?php foreach ( $site_pwa_groups as $site_pwa_key => $site_pwa_group ) : ?>
		<?php if ( array() !== $site_pwa_group[1] ) : ?>
			<section class="site-pwa-section" aria-labelledby="site-pwa-more-<?php echo esc_attr( $site_pwa_key ); ?>">
				<div class="site-pwa-section-head"><h2 id="site-pwa-more-<?php echo esc_attr( $site_pwa_key ); ?>"><?php echo esc_html( $site_pwa_group[0] ); ?></h2></div>
				<ul class="site-pwa-panel site-pwa-pending site-pwa-more-list">
					<?php foreach ( $site_pwa_group[1] as $site_pwa_row ) : ?>
						<li>
							<a href="<?php echo esc_url( $site_pwa_row['url'] ); ?>">
								<span class="site-pwa-pending-icon site-pwa-tone-info"><?php echo Icons::svg( $site_pwa_row['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?></span>
								<span class="site-pwa-pending-text">
									<strong><?php echo esc_html( $site_pwa_row['label'] ); ?></strong>
									<?php if ( '' !== $site_pwa_row['subtitle'] ) : ?>
										<span><?php echo esc_html( $site_pwa_row['subtitle'] ); ?></span>
									<?php endif; ?>
								</span>
								<?php if ( '' !== $site_pwa_row['badge'] ) : ?>
									<span class="site-pwa-badge-text"><?php echo esc_html( $site_pwa_row['badge'] ); ?></span>
								<?php endif; ?>
								<?php echo Icons::svg( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>
	<?php endforeach; ?>

	<a class="site-pwa-panel site-pwa-signout" href="<?php echo esc_url( $site_pwa['logout_url'] ); ?>">
		<?php echo Icons::svg( 'logout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
		<span><?php esc_html_e( 'Sign out', 'site-pwa' ); ?></span>
	</a>
</div>
