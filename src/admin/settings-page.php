<?php
/**
 * Settings → Site PWA.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\Admin;

use SitePwa\App\Routes;
use SitePwa\Settings;

/**
 * The settings screen: turn the app on, name it and colour it.
 *
 * Saved through the Settings API (options.php), which checks the nonce and
 * the `manage_options` capability before `Settings::sanitize()` runs.
 */
final class SettingsPage {

	/**
	 * Menu slug and settings group.
	 */
	public const SLUG = 'site-pwa';

	/**
	 * Wire the menu and the setting.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'addMenu' ) );
		add_action( 'admin_init', array( self::class, 'registerSetting' ) );
	}

	/**
	 * Add Settings → Site PWA.
	 */
	public static function addMenu(): void {
		add_options_page(
			__( 'Site PWA', 'site-pwa' ),
			__( 'Site PWA', 'site-pwa' ),
			'manage_options',
			self::SLUG,
			array( self::class, 'render' )
		);
	}

	/**
	 * Register the option with its sanitizer.
	 */
	public static function registerSetting(): void {
		register_setting(
			self::SLUG,
			Settings::OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);
	}

	/**
	 * Print the screen.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = Settings::get();
		$field    = static fn( string $key ): string => Settings::OPTION . '[' . $key . ']';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Site PWA', 'site-pwa' ); ?></h1>
			<?php if ( $settings['enabled'] ) : ?>
				<div class="notice notice-success inline">
					<p>
						<?php esc_html_e( 'The app is on at', 'site-pwa' ); ?>
						<a href="<?php echo esc_url( Routes::url( 'home' ) ); ?>"><?php echo esc_html( Routes::url( 'home' ) ); ?></a>
					</p>
				</div>
			<?php endif; ?>
			<form method="post" action="options.php">
				<?php settings_fields( self::SLUG ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'App', 'site-pwa' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $field( 'enabled' ) ); ?>" value="1" <?php checked( $settings['enabled'] ); ?>>
								<?php esc_html_e( 'Turn the installable app on', 'site-pwa' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'While it is off, the site serves no manifest, and a browser that installed the app removes its service worker on its next visit.', 'site-pwa' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="site-pwa-name"><?php esc_html_e( 'Name', 'site-pwa' ); ?></label></th>
						<td><input type="text" class="regular-text" id="site-pwa-name" name="<?php echo esc_attr( $field( 'name' ) ); ?>" value="<?php echo esc_attr( $settings['name'] ); ?>" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="site-pwa-short-name"><?php esc_html_e( 'Short name', 'site-pwa' ); ?></label></th>
						<td>
							<input type="text" id="site-pwa-short-name" name="<?php echo esc_attr( $field( 'short_name' ) ); ?>" value="<?php echo esc_attr( $settings['short_name'] ); ?>" maxlength="<?php echo esc_attr( (string) Settings::SHORT_NAME_MAX ); ?>" required>
							<p class="description"><?php esc_html_e( 'Shown under the icon on a home screen.', 'site-pwa' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="site-pwa-theme-color"><?php esc_html_e( 'Theme colour', 'site-pwa' ); ?></label></th>
						<td><input type="color" id="site-pwa-theme-color" name="<?php echo esc_attr( $field( 'theme_color' ) ); ?>" value="<?php echo esc_attr( $settings['theme_color'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="site-pwa-background-color"><?php esc_html_e( 'Background colour', 'site-pwa' ); ?></label></th>
						<td>
							<input type="color" id="site-pwa-background-color" name="<?php echo esc_attr( $field( 'background_color' ) ); ?>" value="<?php echo esc_attr( $settings['background_color'] ); ?>">
							<p class="description"><?php esc_html_e( 'Shown while the app opens.', 'site-pwa' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Icon', 'site-pwa' ); ?></th>
						<td><p class="description"><?php esc_html_e( 'The app uses the site icon from Settings → General; without one, it uses the plugin\'s icon.', 'site-pwa' ); ?></p></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
