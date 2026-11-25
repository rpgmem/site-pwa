<?php

declare(strict_types=1);

namespace SitePwa\Tests\Functional;

use SitePwa\Admin\SettingsPage;
use SitePwa\Plugin;
use SitePwa\Settings;
use WP_UnitTestCase;

/**
 * The settings: defaults, sanitizing and the screen that saves them.
 *
 * @covers \SitePwa\Settings
 * @covers \SitePwa\Admin\SettingsPage
 */
final class SettingsTest extends WP_UnitTestCase {

	protected function tearDown(): void {
		delete_option( Settings::OPTION );
		parent::tearDown();
	}

	public function test_the_app_is_off_by_default(): void {
		self::assertFalse( Settings::enabled() );
		self::assertSame( get_bloginfo( 'name' ), Settings::get()['name'] );
	}

	public function test_a_cleared_or_invalid_field_takes_its_default(): void {
		$clean = Settings::sanitize(
			array(
				'enabled'          => '1',
				'name'             => '  ',
				'short_name'       => '',
				'theme_color'      => 'red',
				'background_color' => '#ABCDEF',
			)
		);

		self::assertTrue( $clean['enabled'] );
		self::assertSame( get_bloginfo( 'name' ), $clean['name'] );
		self::assertSame( mb_substr( get_bloginfo( 'name' ), 0, Settings::SHORT_NAME_MAX ), $clean['short_name'] );
		self::assertSame( '#1d5e8c', $clean['theme_color'] );
		self::assertSame( '#abcdef', $clean['background_color'] );
	}

	public function test_markup_is_stripped_and_the_short_name_is_cut(): void {
		$clean = Settings::sanitize(
			array(
				'name'       => '<b>Escola</b> Municipal',
				'short_name' => 'Escola Municipal Viva',
			)
		);

		self::assertSame( 'Escola Municipal', $clean['name'] );
		self::assertSame( 'Escola Munic', $clean['short_name'] );
	}

	public function test_the_invite_sentences_are_plain_text_cut_to_length_and_default_when_empty(): void {
		$clean = Settings::sanitize(
			array(
				'banner_text'  => '<i>Mais</i> rápido ' . str_repeat( 'á', 60 ),
				'install_text' => array( 'not text' ),
			)
		);

		self::assertSame( Settings::BANNER_TEXT_MAX, mb_strlen( $clean['banner_text'] ) );
		self::assertStringStartsWith( 'Mais rápido á', $clean['banner_text'] );
		self::assertSame( '', $clean['install_text'] );

		update_option( Settings::OPTION, array( 'install_text' => str_repeat( 'x', 200 ) ) );
		self::assertSame( Settings::INSTALL_TEXT_MAX, mb_strlen( Settings::installText() ) );
		self::assertSame( 'Faster in the app.', Settings::bannerText() );
		delete_option( Settings::OPTION );
	}

	public function test_a_value_that_is_not_an_array_reads_as_the_defaults(): void {
		update_option( Settings::OPTION, 'garbage' );
		self::assertSame( Settings::defaults(), Settings::get() );
		self::assertSame( Settings::defaults(), Settings::sanitize( null ) );
	}

	public function test_the_setting_is_registered_with_its_sanitizer(): void {
		SettingsPage::registerSetting();
		$registered = get_registered_settings();

		self::assertSame( array( SettingsPage::class, 'save' ), $registered[ Settings::OPTION ]['sanitize_callback'] );
	}

	public function test_saving_through_options_php_runs_the_sanitizer(): void {
		SettingsPage::registerSetting();
		update_option( Settings::OPTION, array( 'enabled' => 'yes', 'theme_color' => 'javascript:alert(1)' ) );

		$stored = get_option( Settings::OPTION );
		self::assertTrue( $stored['enabled'] );
		self::assertSame( '#1d5e8c', $stored['theme_color'] );
	}

	public function test_the_screen_is_under_settings_for_administrators_only(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		SettingsPage::addMenu();
		global $submenu;

		self::assertContains( SettingsPage::SLUG, array_column( $submenu['options-general.php'], 2 ) );
		self::assertSame( 'manage_options', array_column( $submenu['options-general.php'], 1, 2 )[ SettingsPage::SLUG ] );
	}

	public function test_the_screen_prints_the_form_and_the_app_link_when_on(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option( Settings::OPTION, array( 'enabled' => true ) );

		ob_start();
		SettingsPage::render();
		$html = (string) ob_get_clean();

		self::assertStringContainsString( 'action="options.php"', $html );
		self::assertStringContainsString( 'name="site_pwa_settings[enabled]"', $html );
		self::assertStringContainsString( home_url( '/app/' ), $html );
	}

	public function test_the_screen_prints_nothing_without_the_capability(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		ob_start();
		SettingsPage::render();
		self::assertSame( '', ob_get_clean() );
	}

	public function test_deactivating_forgets_the_rules(): void {
		update_option( \SitePwa\App\Routes::REVISION_OPTION, 'x' );
		update_option( 'rewrite_rules', array( 'a' => 'b' ) );

		Plugin::deactivate();

		self::assertFalse( get_option( \SitePwa\App\Routes::REVISION_OPTION ) );
		self::assertNotSame( array( 'a' => 'b' ), get_option( 'rewrite_rules' ) );
	}
}
