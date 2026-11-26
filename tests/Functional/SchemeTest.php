<?php

declare(strict_types=1);

namespace SitePwa\Tests\Functional;

use SitePwa\App\Frame;
use SitePwa\App\Pages;
use SitePwa\App\Routes;
use SitePwa\App\Scheme;
use SitePwa\App\ServiceWorker;
use SitePwa\Settings;
use WP_UnitTestCase;

/**
 * The app's light and dark theme: its own choice, or the one another plugin
 * offers through `site_pwa_scheme_sources`. The default is the source a
 * consumer registers as `ffc` (Free Form Certificate's Dark Mode, which that
 * plugin offers itself, #48), so the frame and the screens it draws agree.
 *
 * @covers \SitePwa\App\Scheme
 * @covers \SitePwa\Settings
 */
final class SchemeTest extends WP_UnitTestCase {

	/**
	 * What the consumer registered as `ffc` answers.
	 */
	private static ?string $answer = null;

	protected function setUp(): void {
		parent::setUp();
		self::$answer = null;
		add_filter( 'site_pwa_scheme_sources', array( self::class, 'consumer' ) );
	}

	/**
	 * A consumer offering its scheme under the default id, as Free Form Certificate does.
	 *
	 * @param array<string, mixed> $sources Sources by id.
	 * @return array<string, mixed>
	 */
	public static function consumer( array $sources ): array {
		return $sources + array(
			'ffc' => array(
				'label' => 'Free Form Certificate: Dark Mode',
				'read'  => static fn(): ?string => self::$answer,
			),
		);
	}

	protected function tearDown(): void {
		delete_option( Settings::OPTION );
		remove_all_filters( 'site_pwa_scheme_sources' );
		parent::tearDown();
	}

	private function settings( array $values ): void {
		update_option( Settings::OPTION, $values + array( 'enabled' => true ) );
	}

	public function test_the_default_follows_the_source_registered_as_ffc(): void {
		$this->settings( array() );
		self::assertSame( Scheme::FFC, Settings::get()['color_scheme'] );

		foreach ( array( 'light', 'dark', 'auto' ) as $scheme ) {
			self::$answer = $scheme;
			self::assertSame( $scheme, Scheme::current(), $scheme );
		}
	}

	public function test_no_plugin_is_read_by_name_so_without_a_consumer_the_default_follows_the_device(): void {
		remove_all_filters( 'site_pwa_scheme_sources' );
		$this->settings( array() );

		self::assertSame( array(), Scheme::sources() );
		self::assertSame( 'auto', Scheme::current() );
	}

	public function test_an_unknown_answer_follows_the_device(): void {
		$this->settings( array() );
		self::$answer = 'sometimes';

		self::assertSame( 'auto', Scheme::current() );
	}

	public function test_the_apps_own_choice_wins_over_the_source(): void {
		self::$answer = 'dark';
		foreach ( array( 'auto', 'light', 'dark' ) as $scheme ) {
			$this->settings( array( 'color_scheme' => $scheme ) );
			self::assertSame( $scheme, Scheme::current() );
		}
	}

	public function test_a_source_that_is_gone_follows_the_device(): void {
		$this->settings( array( 'color_scheme' => 'gone' ) );

		self::assertSame( 'auto', Scheme::current() );
	}

	public function test_another_plugin_can_be_a_source_and_cannot_take_an_own_id(): void {
		add_filter(
			'site_pwa_scheme_sources',
			static fn( array $sources ): array => $sources + array(
				'other' => array( 'label' => 'Other', 'read' => static fn(): string => 'dark' ),
				'light' => array( 'label' => 'Shadow', 'read' => static fn(): string => 'dark' ),
				'bad'   => array( 'label' => 'Bad' ),
			)
		);
		$this->settings( array( 'color_scheme' => 'other' ) );

		self::assertSame( array( 'ffc', 'other' ), array_keys( Scheme::sources() ) );
		self::assertSame( 'dark', Scheme::current() );
	}

	public function test_a_source_answering_nothing_usable_follows_the_device(): void {
		add_filter(
			'site_pwa_scheme_sources',
			static fn( array $sources ): array => $sources + array( 'other' => array( 'label' => 'Other', 'read' => static fn() => null ) )
		);
		$this->settings( array( 'color_scheme' => 'other' ) );

		self::assertSame( 'auto', Scheme::current() );
	}

	public function test_a_filter_that_breaks_the_list_leaves_no_source(): void {
		add_filter( 'site_pwa_scheme_sources', '__return_false' );

		self::assertSame( array(), Scheme::sources() );
	}

	public function test_an_empty_choice_is_stored_as_ffc_and_a_value_is_kept_as_a_key(): void {
		self::assertSame( 'ffc', Settings::sanitize( array( 'color_scheme' => '' ) )['color_scheme'] );
		self::assertSame( 'dark', Settings::sanitize( array( 'color_scheme' => 'Dark' ) )['color_scheme'] );
		self::assertSame( 'ffc', Settings::sanitize( array( 'color_scheme' => array( 'x' ) ) )['color_scheme'] );
	}

	public function test_the_frame_and_the_offline_page_carry_the_scheme_class(): void {
		$this->settings( array( 'color_scheme' => 'dark' ) );
		$GLOBALS['wp_scripts'] = null;
		$GLOBALS['wp_styles']  = null;
		wp_set_current_user( self::factory()->user->create() );
		set_query_var( Routes::QUERY_VAR, 'home' );

		$html = Frame::render( 'home' );
		set_query_var( Routes::QUERY_VAR, '' );

		self::assertStringContainsString( '<body class="site-pwa-app site-pwa-scheme-dark">', $html );
		self::assertStringContainsString( '<body class="site-pwa site-pwa-scheme-dark">', Pages::render() );
	}

	public function test_changing_the_scheme_renews_the_workers_cache(): void {
		$this->settings( array( 'color_scheme' => 'light' ) );
		$before = ServiceWorker::config()['cache'];

		$this->settings( array( 'color_scheme' => 'dark' ) );

		self::assertNotSame( $before, ServiceWorker::config()['cache'] );
	}

	/**
	 * Android paints the installed app's navigation bar from the manifest,
	 * which the page's theme-color meta never reaches: a dark site hands it
	 * the dark ground, and every other scheme keeps the settings' colours.
	 */
	public function test_the_manifest_hands_a_dark_site_the_dark_ground(): void {
		$this->settings( array( 'color_scheme' => 'dark', 'background_color' => '#ffffff' ) );
		$manifest = \SitePwa\App\Manifest::data();
		self::assertSame( Frame::DARK_BAR, $manifest['theme_color'] );
		self::assertSame( Frame::DARK_BAR, $manifest['background_color'] );

		foreach ( array( 'light', 'auto' ) as $scheme ) {
			$this->settings( array( 'color_scheme' => $scheme, 'background_color' => '#ffffff' ) );
			$manifest = \SitePwa\App\Manifest::data();
			self::assertSame( '#1d5e8c', $manifest['theme_color'], $scheme );
			self::assertSame( '#ffffff', $manifest['background_color'], $scheme );
		}
	}

	/**
	 * The phone's status and navigation bars follow the theme: the accent in
	 * the light one, the dark ground in the dark one, and both by the
	 * device's setting when the theme follows the device.
	 */
	public function test_the_phone_bars_follow_the_theme(): void {
		$this->settings( array( 'color_scheme' => 'light' ) );
		self::assertSame( '<meta name="theme-color" content="#1d5e8c">' . "\n", \SitePwa\App\Frame::themeColorTags() );

		$this->settings( array( 'color_scheme' => 'dark' ) );
		self::assertSame( '<meta name="theme-color" content="' . \SitePwa\App\Frame::DARK_BAR . '">' . "\n", \SitePwa\App\Frame::themeColorTags() );

		$this->settings( array( 'color_scheme' => 'auto' ) );
		$tags = \SitePwa\App\Frame::themeColorTags();
		self::assertStringContainsString( '<meta name="theme-color" content="#1d5e8c" media="(prefers-color-scheme: light)">', $tags );
		self::assertStringContainsString( '<meta name="theme-color" content="' . \SitePwa\App\Frame::DARK_BAR . '" media="(prefers-color-scheme: dark)">', $tags );

		$css  = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/app.css' );
		$dark = substr( $css, (int) strpos( $css, '.site-pwa-scheme-dark,' ) );
		self::assertStringContainsString( '--site-pwa-bg: ' . \SitePwa\App\Frame::DARK_BAR . ';', substr( $dark, 0, 200 ), 'the bars take the dark ground the stylesheet paints' );
	}
}
