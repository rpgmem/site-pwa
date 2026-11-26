<?php
/**
 * Preferences, reached from More: the appearance on this device and the
 * screen lock, kept on the device by more.js and lock.js, then the sections
 * other plugins add through `site_pwa_preference_sections`.
 *
 * @package SitePwa
 *
 * @var array{pref_sections: list<array{id: string, label: string, html: string}>} $site_pwa Values built by Frame::render().
 */

use SitePwa\App\Scheme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h1 class="site-pwa-title"><?php esc_html_e( 'Preferences', 'site-pwa' ); ?></h1>
<div class="site-pwa-more">
	<section class="site-pwa-section" aria-label="<?php esc_attr_e( 'Preferences', 'site-pwa' ); ?>">
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

	<?php foreach ( $site_pwa['pref_sections'] as $site_pwa_section ) : ?>
		<section class="site-pwa-section" aria-labelledby="site-pwa-pref-<?php echo esc_attr( $site_pwa_section['id'] ); ?>">
			<div class="site-pwa-section-head"><h2 id="site-pwa-pref-<?php echo esc_attr( $site_pwa_section['id'] ); ?>"><?php echo esc_html( $site_pwa_section['label'] ); ?></h2></div>
			<div class="site-pwa-panel site-pwa-preferences site-pwa-pref-section">
				<?php echo $site_pwa_section['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a registered plugin's own markup, as a screen's render. ?>
			</div>
		</section>
	<?php endforeach; ?>
</div>
