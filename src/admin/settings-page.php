<?php
/**
 * Settings → Site PWA.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\Admin;

use SitePwa\App\AppIcon;
use SitePwa\App\Conflicts;
use SitePwa\App\Identity;
use SitePwa\App\Scheme;
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

		// The custom icon is chosen in the Media Library (#26).
		wp_enqueue_media();
		$icon = Plugin::asset( 'assets/js/admin-icon.js' );
		wp_enqueue_script( 'site-pwa-admin-icon', Plugin::url() . $icon, array( 'media-editor' ), Plugin::assetVersion( $icon ), array( 'in_footer' => true ) );
		wp_localize_script(
			'site-pwa-admin-icon',
			'sitePwaIcon',
			array(
				'title'  => __( 'Choose the app icon', 'site-pwa' ),
				'button' => __( 'Use this image', 'site-pwa' ),
			)
		);
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
				'sanitize_callback' => array( self::class, 'save' ),
				'default'           => Settings::defaults(),
			)
		);
	}

	/**
	 * Clean what the form posted and check the custom icon (#26). An icon
	 * that cannot serve is refused with a message, and the icon saved
	 * before stays; one that can gets the sizes the manifest declares.
	 *
	 * Kept apart from Settings::sanitize(), which every read runs.
	 *
	 * @param mixed $input Posted value.
	 * @return array<string, mixed>
	 */
	public static function save( $input ): array {
		$clean = Settings::sanitize( $input );
		if ( AppIcon::CUSTOM !== $clean['icon_source'] ) {
			return $clean;
		}
		$problem = AppIcon::problem( $clean['icon_id'] );
		if ( null === $problem ) {
			AppIcon::prepare( $clean['icon_id'] );
			return $clean;
		}
		if ( function_exists( 'add_settings_error' ) ) {
			add_settings_error( Settings::OPTION, 'site_pwa_icon', $problem );
		}
		$before               = Settings::get();
		$clean['icon_source'] = $before['icon_source'];
		$clean['icon_id']     = $before['icon_id'];
		return $clean;
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
						<th scope="row"><?php esc_html_e( 'News', 'site-pwa' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Categories shown in the app', 'site-pwa' ); ?></legend>
								<?php
								$site_pwa_categories = get_categories( array( 'hide_empty' => false ) );
								foreach ( $site_pwa_categories as $site_pwa_category ) :
									?>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( $field( 'news_categories' ) ); ?>[]" value="<?php echo esc_attr( (string) $site_pwa_category->term_id ); ?>" <?php checked( in_array( $site_pwa_category->term_id, $settings['news_categories'], true ) ); ?>>
										<?php echo esc_html( $site_pwa_category->name ); ?>
									</label><br>
								<?php endforeach; ?>
							</fieldset>
							<p class="description"><?php esc_html_e( 'The app shows the posts of the ticked categories, newest first: the three latest on the home screen and all of them under More → News. With none ticked, it shows every post.', 'site-pwa' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Pages', 'site-pwa' ); ?></th>
						<td>
							<?php $site_pwa_pages = get_pages( array( 'post_status' => 'publish' ) ); ?>
							<?php $site_pwa_pages = false === $site_pwa_pages ? array() : $site_pwa_pages; ?>
							<?php if ( array() === $site_pwa_pages ) : ?>
								<p class="description"><?php esc_html_e( 'The site has no published pages.', 'site-pwa' ); ?></p>
							<?php else : ?>
								<table class="widefat striped site-pwa-pages">
									<thead>
										<tr>
											<th scope="col"><?php esc_html_e( 'In the app', 'site-pwa' ); ?></th>
											<th scope="col"><?php esc_html_e( 'Open on the site', 'site-pwa' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php
										foreach ( $site_pwa_pages as $site_pwa_page ) :
											$site_pwa_name  = $field( 'app_pages' ) . '[' . $site_pwa_page->ID . ']';
											$site_pwa_on    = array_key_exists( $site_pwa_page->ID, $settings['app_pages'] );
											$site_pwa_title = '' !== $site_pwa_page->post_title ? $site_pwa_page->post_title : __( '(no title)', 'site-pwa' );
											?>
											<tr>
												<td>
													<label>
														<input type="checkbox" name="<?php echo esc_attr( $site_pwa_name . '[show]' ); ?>" value="1" <?php checked( $site_pwa_on ); ?>>
														<?php echo esc_html( $site_pwa_title ); ?>
													</label>
												</td>
												<td>
													<label>
														<input type="checkbox" name="<?php echo esc_attr( $site_pwa_name . '[site]' ); ?>" value="1" <?php checked( $site_pwa_on && $settings['app_pages'][ $site_pwa_page->ID ] ); ?>>
														<span class="screen-reader-text">
															<?php
															/* translators: %s: a page title */
															echo esc_html( sprintf( __( 'Open %s on the site', 'site-pwa' ), $site_pwa_title ) );
															?>
														</span>
													</label>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'The ticked pages appear under More and on the home screen. A page opens inside the app, unless it is marked to open on the site, as a page built with a page builder should be.', 'site-pwa' ); ?></p>
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
								echo ' ' . esc_html__( 'A phone that already installed the app picks up a new colour, name or icon when its browser next refreshes the installed app, which can take a few days; reinstalling the app applies it at once.', 'site-pwa' );
								$site_pwa_text_shade = Identity::accentText( $site_pwa_look['accent'] );
								if ( $site_pwa_text_shade !== $site_pwa_look['accent'] ) {
									echo ' ' . esc_html(
										sprintf(
											/* translators: %s: a colour such as #8a6d00 */
											__( 'The accent is too light to read as text, so links and icons use the darker %s.', 'site-pwa' ),
											$site_pwa_text_shade
										)
									);
								}
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="site-pwa-color-scheme"><?php esc_html_e( 'Light or dark', 'site-pwa' ); ?></label></th>
						<td>
							<select id="site-pwa-color-scheme" name="<?php echo esc_attr( $field( 'color_scheme' ) ); ?>">
								<?php foreach ( Scheme::sources() as $site_pwa_id => $site_pwa_source ) : ?>
									<option value="<?php echo esc_attr( $site_pwa_id ); ?>" <?php selected( $settings['color_scheme'], $site_pwa_id ); ?>><?php echo esc_html( (string) $site_pwa_source['label'] ); ?></option>
								<?php endforeach; ?>
								<?php foreach ( Scheme::own() as $site_pwa_id => $site_pwa_label ) : ?>
									<option value="<?php echo esc_attr( $site_pwa_id ); ?>" <?php selected( $settings['color_scheme'], $site_pwa_id ); ?>><?php echo esc_html( $site_pwa_label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Whether the app draws light, dark, or follows the device. Following Free Form Certificate\'s Dark Mode keeps the app\'s header and menu in the same theme as the screens it draws inside them. While the chosen plugin is inactive, the app follows the device.', 'site-pwa' ); ?></p>
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
						<td>
							<?php
							$site_pwa_icon_labels = array(
								AppIcon::SITE    => __( 'The site icon', 'site-pwa' ),
								AppIcon::DEFAULT => __( 'The plugin\'s icon', 'site-pwa' ),
								AppIcon::CUSTOM  => __( 'A custom icon', 'site-pwa' ),
							);
							$site_pwa_icon_url    = $settings['icon_id'] > 0 ? (string) wp_get_attachment_image_url( $settings['icon_id'], 'thumbnail' ) : '';
							?>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Icon', 'site-pwa' ); ?></legend>
								<?php foreach ( $site_pwa_icon_labels as $site_pwa_id => $site_pwa_label ) : ?>
									<label>
										<input type="radio" name="<?php echo esc_attr( $field( 'icon_source' ) ); ?>" value="<?php echo esc_attr( $site_pwa_id ); ?>" <?php checked( $settings['icon_source'], $site_pwa_id ); ?>>
										<?php echo esc_html( $site_pwa_label ); ?>
									</label><br>
								<?php endforeach; ?>
							</fieldset>
							<div class="site-pwa-icon-picker" data-site-pwa-icon>
								<input type="hidden" name="<?php echo esc_attr( $field( 'icon_id' ) ); ?>" value="<?php echo esc_attr( (string) $settings['icon_id'] ); ?>" data-site-pwa-icon-id>
								<img src="<?php echo esc_url( $site_pwa_icon_url ); ?>" alt="" width="64" height="64" data-site-pwa-icon-preview<?php echo '' === $site_pwa_icon_url ? ' hidden' : ''; ?>>
								<button type="button" class="button" data-site-pwa-icon-choose><?php esc_html_e( 'Choose image', 'site-pwa' ); ?></button>
							</div>
							<p class="description"><?php esc_html_e( 'The site icon is the one set in Settings → General; without one, the app uses the plugin\'s icon. A custom icon is a square PNG of at least 512 px from the Media Library; the app makes the sizes it needs from it. Only the plugin\'s icon is shaped for launchers that crop icons into circles or squircles.', 'site-pwa' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
