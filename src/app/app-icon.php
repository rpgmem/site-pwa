<?php
/**
 * The app's icon: the site icon, the plugin's, or one chosen in the Media Library.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\App;

use SitePwa\Plugin;
use SitePwa\Settings;

/**
 * Which icon the app installs with, and its sizes (#26).
 *
 * Three sources, chosen on Settings → Site PWA:
 * - `site`: the site icon (Settings → General), or the plugin's own when
 *   the site has none;
 * - `default`: the plugin's own, which also ships a maskable version;
 * - `custom`: a square PNG of at least 512 px from the Media Library.
 *
 * Neither the site icon nor a custom one is offered as maskable: nothing
 * guarantees its drawing stays inside the safe zone a launcher may crop to.
 *
 * @phpstan-type IconEntry array{src: string, sizes: string, type: string, purpose: string}
 */
final class AppIcon {

	public const SITE    = 'site';
	public const DEFAULT = 'default';
	public const CUSTOM  = 'custom';

	/**
	 * The sources, in the order the settings screen offers them.
	 */
	public const SOURCES = array( self::SITE, self::DEFAULT, self::CUSTOM );

	/**
	 * The smallest side a custom icon may have: the largest size the
	 * manifest declares.
	 */
	public const MIN_SIZE = 512;

	/**
	 * The sizes the manifest declares, which a browser asks for by name.
	 */
	public const SIZES = array( 192, 512 );

	/**
	 * The icons the manifest lists, for the source in use.
	 *
	 * @return list<IconEntry>
	 */
	public static function icons(): array {
		$settings = Settings::get();
		$source   = $settings['icon_source'];

		if ( self::CUSTOM === $source && null === self::problem( $settings['icon_id'] ) ) {
			return self::custom( $settings['icon_id'] );
		}
		if ( self::SITE === $source && has_site_icon() ) {
			return self::site();
		}
		return self::plugin();
	}

	/**
	 * Why an attachment cannot be the app's icon, or null when it can.
	 *
	 * @param int $id Attachment.
	 */
	public static function problem( int $id ): ?string {
		if ( $id <= 0 || ! wp_attachment_is_image( $id ) ) {
			return __( 'Choose an image from the Media Library for the custom icon.', 'site-pwa' );
		}
		if ( 'image/png' !== get_post_mime_type( $id ) ) {
			return __( 'The custom icon must be a PNG image.', 'site-pwa' );
		}
		$meta   = wp_get_attachment_metadata( $id );
		$width  = is_array( $meta ) && isset( $meta['width'] ) ? (int) $meta['width'] : 0;
		$height = is_array( $meta ) && isset( $meta['height'] ) ? (int) $meta['height'] : 0;
		if ( $width !== $height ) {
			/* translators: 1: width in px, 2: height in px */
			return sprintf( __( 'The custom icon must be square; this image is %1$d × %2$d px.', 'site-pwa' ), $width, $height );
		}
		if ( $width < self::MIN_SIZE ) {
			/* translators: 1: the image's side in px, 2: the smallest side allowed in px */
			return sprintf( __( 'The custom icon must be at least %2$d px wide; this image is %1$d px.', 'site-pwa' ), $width, self::MIN_SIZE );
		}
		return null;
	}

	/**
	 * Make the sizes the manifest declares for a custom icon, once, and
	 * record them in the attachment's metadata, so WordPress deletes them
	 * with the image.
	 *
	 * @param int $id Attachment that passed problem().
	 */
	public static function prepare( int $id ): void {
		$meta = wp_get_attachment_metadata( $id );
		$file = get_attached_file( $id );
		if ( ! is_array( $meta ) || ! is_string( $file ) || '' === $file ) {
			return;
		}
		if ( ! function_exists( 'image_make_intermediate_size' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$sizes   = isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ? $meta['sizes'] : array();
		$changed = false;
		foreach ( self::SIZES as $size ) {
			$name = self::sizeName( $size );
			if ( isset( $sizes[ $name ] ) || (int) ( $meta['width'] ?? 0 ) === $size ) {
				continue;
			}
			$made = image_make_intermediate_size( $file, $size, $size, true );
			if ( is_array( $made ) ) {
				$sizes[ $name ] = $made;
				$changed        = true;
			}
		}
		if ( $changed ) {
			$meta['sizes'] = $sizes;
			wp_update_attachment_metadata( $id, $meta );
		}
	}

	/**
	 * The image size name a manifest size is kept under.
	 *
	 * @param int $size Side in px.
	 */
	public static function sizeName( int $size ): string {
		return 'site-pwa-' . $size;
	}

	/**
	 * A custom icon at each declared size: the size made for it, or the
	 * original when it is that size or the size could not be made.
	 *
	 * @param int $id Attachment.
	 * @return list<IconEntry>
	 */
	private static function custom( int $id ): array {
		$meta     = wp_get_attachment_metadata( $id );
		$original = (string) wp_get_attachment_url( $id );
		$side     = is_array( $meta ) ? (int) ( $meta['width'] ?? 0 ) : 0;
		$icons    = array();
		foreach ( self::SIZES as $size ) {
			$made    = wp_get_attachment_image_src( $id, self::sizeName( $size ) );
			$exact   = is_array( $made ) && (int) $made[1] === $size;
			$icons[] = array(
				'src'     => $exact ? (string) $made[0] : $original,
				'sizes'   => $exact ? $size . 'x' . $size : $side . 'x' . $side,
				'type'    => 'image/png',
				'purpose' => 'any',
			);
		}
		return $icons;
	}

	/**
	 * The site icon at each declared size.
	 *
	 * @return list<IconEntry>
	 */
	private static function site(): array {
		$icons = array();
		foreach ( self::SIZES as $size ) {
			$url     = (string) get_site_icon_url( $size );
			$type    = wp_check_filetype( (string) wp_parse_url( $url, PHP_URL_PATH ) );
			$icons[] = array(
				'src'     => $url,
				'sizes'   => $size . 'x' . $size,
				'type'    => is_string( $type['type'] ) ? $type['type'] : 'image/png',
				'purpose' => 'any',
			);
		}
		return $icons;
	}

	/**
	 * The plugin's own icon, with its maskable version.
	 *
	 * @return list<IconEntry>
	 */
	public static function plugin(): array {
		$base = Plugin::url() . 'assets/img/brand/';
		return array(
			array(
				'src'     => $base . 'icon-192.png',
				'sizes'   => '192x192',
				'type'    => 'image/png',
				'purpose' => 'any',
			),
			array(
				'src'     => $base . 'icon-512.png',
				'sizes'   => '512x512',
				'type'    => 'image/png',
				'purpose' => 'any',
			),
			array(
				'src'     => $base . 'icon-maskable-512.png',
				'sizes'   => '512x512',
				'type'    => 'image/png',
				'purpose' => 'maskable',
			),
		);
	}
}
