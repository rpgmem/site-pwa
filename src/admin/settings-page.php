<?php
/**
 * Settings → Site PWA.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\Admin;

use SitePwa\App\Conflicts;
use SitePwa\App\Identity;
use SitePwa\App\Routes;
use SitePwa\Plugin;
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
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * On this screen only, the script that asks the browser which service
	 * workers it already has.
	 *
	 * @param string $hook The admin page hook.
	 */
	public static function enqueue( string $hook ): void {
		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}
		$file = Plugin::asset( 'assets/js/admin-conflicts.js' );
		wp_enqueue_script( 'site-pwa-admin-conflicts', Plugin::url() . $file, array(), Plugin::assetVersion( $file ), array( 'in_footer' => true ) );
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
		$settings  = Settings::get();
		$conflicts = Conflicts::active();
		$field     = static fn( string $key ): string => Settings::OPTION . '[' . $key . ']';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Site PWA', 'site-pwa' ); ?></h1>
			<?php if ( array() !== $conflicts ) : ?>
				<div class="notice <?php echo esc_attr( Settings::enabled() ? 'notice-info' : 'notice-warning' ); ?> inline">
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: plugin names, comma-separated */
								__( 'Also registering a service worker at the site root: %s. A browser keeps only one there, so the two would replace each other on every visit.', 'site-pwa' ),
								implode( ', ', $conflicts )
							)
						);
						?>
					</p>
					<p>
						<?php
						if ( $settings['enabled'] && ! $settings['replace_confirmed'] ) {
							esc_html_e( 'The app stays off until you confirm the replacement below, or that plugin is deactivated.', 'site-pwa' );
						} elseif ( $settings['replace_confirmed'] ) {
							esc_html_e( 'Replacement confirmed: the app\'s worker takes the root, and that plugin\'s worker stops working.', 'site-pwa' );
						}
						?>
					</p>
				</div>
			<?php endif; ?>
			<div id="site-pwa-sw-check" class="notice notice-warning inline" data-own="<?php echo esc_url( Routes::url( 'sw' ) ); ?>" hidden>
				<p><?php esc_html_e( 'This browser already has another service worker for this site:', 'site-pwa' ); ?></p>
				<ul class="site-pwa-sw-list"></ul>
				<p><?php esc_html_e( 'Turning the app on replaces it. Confirm the replacement below to continue.', 'site-pwa' ); ?></p>
			</div>
			<?php if ( Settings::enabled() ) : ?>
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
								<input type="checkbox" id="site-pwa-enabled" name="<?php echo esc_attr( $field( 'enabled' ) ); ?>" value="1" <?php checked( $settings['enabled'] ); ?>>
								<?php esc_html_e( 'Turn the installable app on', 'site-pwa' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'While it is off, the site serves no manifest, and a browser that installed the app removes its service worker on its next visit.', 'site-pwa' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Other service workers', 'site-pwa' ); ?></th>
						<td>
							<label>
								<input type="checkbox" id="site-pwa-replace" name="<?php echo esc_attr( $field( 'replace_confirmed' ) ); ?>" value="1" <?php checked( $settings['replace_confirmed'] ); ?>>
								<?php esc_html_e( 'Replace another plugin\'s service worker', 'site-pwa' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Only needed when another plugin registers a worker at the site root. A push service that wants to keep working can load its script through the site_pwa_sw_imports filter instead.', 'site-pwa' ); ?></p>
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
						<th scope="row"><?php esc_html_e( 'Invite to the app', 'site-pwa' ); ?></th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( $field( 'site_banner' ) ); ?>" value="0">
							<label>
								<input type="checkbox" id="site-pwa-site-banner" name="<?php echo esc_attr( $field( 'site_banner' ) ); ?>" value="1" <?php checked( $settings['site_banner'] ); ?>>
								<?php esc_html_e( 'Show a "Use the app" banner on the site\'s pages', 'site-pwa' ); ?>
							</label>
							<br>
							<input type="hidden" name="<?php echo esc_attr( $field( 'site_banner_guests' ) ); ?>" value="0">
							<label>
								<input type="checkbox" id="site-pwa-site-banner-guests" name="<?php echo esc_attr( $field( 'site_banner_guests' ) ); ?>" value="1" <?php checked( $settings['site_banner_guests'] ); ?>>
								<?php esc_html_e( 'Also show it to visitors who are not signed in', 'site-pwa' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Only on phones and tablets, and never inside the installed app. A visitor who is not signed in goes to the login first, then into the app. A visitor who closes the banner does not see it again on that device for 30 days. The app\'s home screen invites to install it the same way.', 'site-pwa' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="site-pwa-identity-source"><?php esc_html_e( 'Look', 'site-pwa' ); ?></label></th>
						<td>
							<select id="site-pwa-identity-source" name="<?php echo esc_attr( $field( 'identity_source' ) ); ?>">
								<option value="<?php echo esc_attr( Identity::OWN ); ?>" <?php selected( $settings['identity_source'], Identity::OWN ); ?>><?php esc_html_e( 'These settings', 'site-pwa' ); ?></option>
								<?php foreach ( Identity::sources() as $site_pwa_id => $site_pwa_source ) : ?>
									<option value="<?php echo esc_attr( $site_pwa_id ); ?>" <?php selected( $settings['identity_source'], $site_pwa_id ); ?>><?php echo esc_html( (string) $site_pwa_source['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Where the app takes its accent colour and its corners from. While the chosen design is switched off, or its plugin is inactive, the app uses the theme colour below.', 'site-pwa' ); ?></p>
							<?php $site_pwa_look = Identity::current(); ?>
							<p class="description">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: a colour such as #1d5e8c, 2: corner radius of cards in px, 3: corner radius of buttons in px */
										__( 'In use now: accent %1$s, cards %2$d px, buttons %3$d px.', 'site-pwa' ),
										$site_pwa_look['accent'],
										$site_pwa_look['radius'],
										$site_pwa_look['control_radius']
									)
								);
								?>
							</p>
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
