<?php

declare(strict_types=1);

namespace SitePwa\Tests\Functional;

use SitePwa\Admin\SettingsPage;
use SitePwa\App\AppIcon;
use SitePwa\App\Manifest;
use SitePwa\Plugin;
use SitePwa\Settings;
use WP_UnitTestCase;

/**
 * The app's icon (#26): the site icon, the plugin's, or a custom one from
 * the Media Library, checked on save and made in the sizes the manifest
 * declares.
 *
 * @covers \SitePwa\App\AppIcon
 * @covers \SitePwa\App\Manifest
 * @covers \SitePwa\Admin\SettingsPage::save
 */
final class AppIconTest extends WP_UnitTestCase {

	/** @var list<string> */
	private array $files = array();

	protected function tearDown(): void {
		delete_option( Settings::OPTION );
		delete_option( 'site_icon' );
		foreach ( $this->files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
		$GLOBALS['wp_settings_errors'] = array();
		parent::tearDown();
	}

	/**
	 * An image in the Media Library.
	 *
	 * @param int    $width  Width.
	 * @param int    $height Height.
	 * @param string $type   png or jpg.
	 */
	private function image( int $width, int $height, string $type = 'png' ): int {
		$image = imagecreatetruecolor( $width, $height );
		imagefill( $image, 0, 0, (int) imagecolorallocate( $image, 200, 30, 90 ) );
		$file = get_temp_dir() . 'site-pwa-icon-' . wp_generate_password( 6, false ) . '.' . $type;
		'png' === $type ? imagepng( $image, $file ) : imagejpeg( $image, $file );
		$this->files[] = $file;
		return (int) self::factory()->attachment->create_upload_object( $file );
	}

	/**
	 * @param array<string, mixed> $values Settings beside the name.
	 */
	private function settings( array $values ): void {
		update_option( Settings::OPTION, $values + array( 'name' => 'Escola Viva' ) );
	}

	public function test_the_default_is_the_site_icon_and_falls_back_to_the_plugins(): void {
		self::assertSame( AppIcon::SITE, Settings::defaults()['icon_source'] );
		self::assertSame( AppIcon::plugin(), Manifest::icons(), 'no site icon: the plugin\'s, maskable included' );

		update_option( 'site_icon', self::factory()->attachment->create_upload_object( Plugin::dir() . 'assets/img/brand/icon-512.png' ) );
		self::assertSame( (string) get_site_icon_url( 192 ), Manifest::icons()[0]['src'] );

		$this->settings( array( 'icon_source' => AppIcon::DEFAULT ) );
		self::assertSame( AppIcon::plugin(), Manifest::icons(), 'the plugin\'s icon even when the site has one' );
	}

	public function test_a_custom_icon_is_offered_in_both_sizes_and_drives_every_place_that_shows_the_icon(): void {
		$id = $this->image( 1024, 1024 );
		$this->settings( SettingsPage::save( array( 'name' => 'Escola Viva', 'icon_source' => AppIcon::CUSTOM, 'icon_id' => (string) $id ) ) );

		self::assertSame( AppIcon::CUSTOM, Settings::get()['icon_source'] );
		$icons = Manifest::icons();
		self::assertSame( array( '192x192', '512x512' ), array_column( $icons, 'sizes' ) );
		self::assertSame( array( 'any', 'any' ), array_column( $icons, 'purpose' ), 'never offered as maskable' );
		foreach ( $icons as $icon ) {
			$size = (int) explode( 'x', $icon['sizes'] )[0];
			$path = str_replace( wp_get_upload_dir()['baseurl'], wp_get_upload_dir()['basedir'], $icon['src'] );
			self::assertFileExists( $path );
			self::assertSame( array( $size, $size ), array_slice( (array) getimagesize( $path ), 0, 2 ), 'the file is the size it claims' );
		}
		self::assertSame( $icons[0]['src'], Manifest::touchIcon(), 'apple-touch-icon and the login logo follow' );
		self::assertArrayHasKey( AppIcon::sizeName( 192 ), wp_get_attachment_metadata( $id )['sizes'], 'recorded, so WordPress deletes it with the image' );

		// Saving again does not make the sizes twice.
		$before = wp_get_attachment_metadata( $id );
		SettingsPage::save( array( 'icon_source' => AppIcon::CUSTOM, 'icon_id' => $id ) );
		self::assertSame( $before, wp_get_attachment_metadata( $id ) );
	}

	public function test_a_custom_icon_of_exactly_512_uses_the_original_for_that_size(): void {
		$id = $this->image( 512, 512 );
		$this->settings( SettingsPage::save( array( 'icon_source' => AppIcon::CUSTOM, 'icon_id' => $id ) ) );

		$icons = Manifest::icons();
		self::assertSame( array( '192x192', '512x512' ), array_column( $icons, 'sizes' ) );
		self::assertSame( wp_get_attachment_url( $id ), $icons[1]['src'] );
	}

	/**
	 * @return array<string, array{0: string, 1: int, 2: int, 3: string}>
	 */
	public function refused(): array {
		return array(
			'not square' => array( 'png', 600, 512, 'must be square' ),
			'too small'  => array( 'png', 256, 256, 'at least 512 px' ),
			'not PNG'    => array( 'jpg', 512, 512, 'must be a PNG' ),
		);
	}

	/**
	 * @dataProvider refused
	 */
	public function test_an_unusable_custom_icon_is_refused_with_a_message_and_the_icon_before_stays( string $type, int $width, int $height, string $message ): void {
		$this->settings( array( 'icon_source' => AppIcon::DEFAULT ) );
		$id = $this->image( $width, $height, $type );

		$saved = SettingsPage::save( array( 'name' => 'Escola Viva', 'icon_source' => AppIcon::CUSTOM, 'icon_id' => $id ) );

		self::assertSame( AppIcon::DEFAULT, $saved['icon_source'] );
		self::assertSame( 0, $saved['icon_id'] );
		$errors = get_settings_errors( Settings::OPTION );
		self::assertCount( 1, $errors );
		self::assertStringContainsString( $message, $errors[0]['message'] );
	}

	public function test_choosing_custom_without_an_image_is_refused(): void {
		$saved = SettingsPage::save( array( 'icon_source' => AppIcon::CUSTOM, 'icon_id' => '' ) );
		self::assertSame( AppIcon::SITE, $saved['icon_source'] );
		self::assertStringContainsString( 'Choose an image', get_settings_errors( Settings::OPTION )[0]['message'] );
	}

	public function test_a_custom_icon_deleted_later_falls_back_to_the_plugins(): void {
		$id = $this->image( 512, 512 );
		$this->settings( SettingsPage::save( array( 'icon_source' => AppIcon::CUSTOM, 'icon_id' => $id ) ) );
		wp_delete_attachment( $id, true );

		self::assertSame( AppIcon::plugin(), Manifest::icons() );
	}

	public function test_the_settings_keep_only_known_sources_and_whole_ids(): void {
		self::assertSame( AppIcon::SITE, Settings::sanitize( array( 'icon_source' => 'other' ) )['icon_source'] );
		self::assertSame( 0, Settings::sanitize( array( 'icon_id' => '-4' ) )['icon_id'] );
		self::assertSame( 12, Settings::sanitize( array( 'icon_id' => '12' ) )['icon_id'] );
		self::assertSame( 0, Settings::sanitize( array( 'icon_id' => 'x' ) )['icon_id'] );
	}
}
