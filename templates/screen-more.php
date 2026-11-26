<?php
/**
 * More: the person, then what they use, what they do as an operator, the
 * site's pages, the preferences and the way out.
 *
 * @package SitePwa
 *
 * @var array{more: list<array{id: string, label: string, icon: string, section: string, subtitle: string, badge: callable|string, render: callable|null, url: string}>, profile: array{initials: string, name: string, line: string}, account_url: string, logout_url: string, news_url: string, pages: list<array{id: int, label: string, url: string, external: bool}>} $site_pwa Values built by Frame::render().
 */

use SitePwa\App\Icons;
use SitePwa\App\Scheme;
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

	<?php if ( array() !== $site_pwa['pages'] ) : ?>
		<section class="site-pwa-section" aria-labelledby="site-pwa-more-pages">
			<div class="site-pwa-section-head"><h2 id="site-pwa-more-pages"><?php esc_html_e( 'Site pages', 'site-pwa' ); ?></h2></div>
			<ul class="site-pwa-panel site-pwa-pending site-pwa-more-list">
				<?php foreach ( $site_pwa['pages'] as $site_pwa_page ) : ?>
					<li>
						<a href="<?php echo esc_url( $site_pwa_page['url'] ); ?>">
							<span class="site-pwa-pending-text"><strong><?php echo esc_html( $site_pwa_page['label'] ); ?></strong></span>
							<?php echo Icons::svg( $site_pwa_page['external'] ? 'open' : 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<section class="site-pwa-section" aria-labelledby="site-pwa-more-preferences">
		<div class="site-pwa-section-head"><h2 id="site-pwa-more-preferences"><?php esc_html_e( 'Preferences', 'site-pwa' ); ?></h2></div>
		<div class="site-pwa-panel site-pwa-preferences">
			<fieldset class="site-pwa-theme" data-site-pwa-theme data-site-pwa-theme-default="<?php echo esc_attr( Scheme::current() ); ?>">
				<legend>
					<strong><?php esc_html_e( 'Appearance', 'site-pwa' ); ?></strong>
					<span><?php esc_html_e( 'On this device only. Automatic follows the phone.', 'site-pwa' ); ?></span>
				</legend>
				<div class="site-pwa-segmented">
					<?php
					foreach (
						array(
							'auto'  => __( 'Automatic', 'site-pwa' ),
							'dark'  => __( 'Dark', 'site-pwa' ),
							'light' => __( 'Light', 'site-pwa' ),
						) as $site_pwa_value => $site_pwa_label
					) :
						?>
						<label><input type="radio" name="site-pwa-theme" value="<?php echo esc_attr( $site_pwa_value ); ?>"><span><?php echo esc_html( $site_pwa_label ); ?></span></label>
					<?php endforeach; ?>
				</div>
			</fieldset>
			<label class="site-pwa-switch">
				<span>
					<strong><?php esc_html_e( 'Lock with the phone\'s screen lock', 'site-pwa' ); ?></strong>
					<span><?php esc_html_e( 'Fingerprint, face or the phone\'s PIN when the app opens, and when it comes back after a few seconds away.', 'site-pwa' ); ?></span>
					<span data-site-pwa-lock="required-note" hidden><?php esc_html_e( 'Required for your account: it cannot be turned off.', 'site-pwa' ); ?></span>
				</span>
				<input type="checkbox" role="switch" data-site-pwa-lock-switch>
			</label>
			<p class="site-pwa-lock-status" role="status" aria-live="polite" data-site-pwa-lock="status"></p>
		</div>
	</section>

	<a class="site-pwa-panel site-pwa-signout" href="<?php echo esc_url( $site_pwa['logout_url'] ); ?>">
		<?php echo Icons::svg( 'logout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed drawing from Icons::PATHS. ?>
		<span><?php esc_html_e( 'Sign out', 'site-pwa' ); ?></span>
	</a>
</div>
