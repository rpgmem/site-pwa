<?php

declare(strict_types=1);

namespace SitePwa\Tests\Functional;

use SitePwa\App\Frame;
use SitePwa\App\Home;
use SitePwa\App\Routes;
use SitePwa\Settings;
use WP_UnitTestCase;

/**
 * The home screen's contract (#23): what a consumer hands through
 * `site_pwa_home`, what the app keeps of it, and how it draws it.
 *
 * @covers \SitePwa\App\Home
 */
final class HomeTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wp_styles']  = null;
		$GLOBALS['wp_scripts'] = null;
		update_option( Settings::OPTION, array( 'enabled' => true, 'name' => 'Escola Viva' ) );
		wp_set_current_user( self::factory()->user->create( array( 'display_name' => 'Maria Souza' ) ) );
	}

	protected function tearDown(): void {
		remove_all_filters( Home::FILTER );
		set_query_var( Routes::QUERY_VAR, '' );
		delete_option( Settings::OPTION );
		parent::tearDown();
	}

	private function feed( array $home ): void {
		add_filter( Home::FILTER, static fn( array $defaults ): array => array_merge( $defaults, $home ) );
	}

	private function home(): string {
		set_query_var( Routes::QUERY_VAR, 'home' );
		return Frame::render( 'home' );
	}

	public function test_the_filter_receives_empty_defaults_the_user_and_the_version(): void {
		$seen = array();
		add_filter(
			Home::FILTER,
			static function ( $home, $user, $api ) use ( &$seen ) {
				$seen = array( $home, $user->ID, $api );
				return $home;
			},
			10,
			3
		);

		$items = Home::items( wp_get_current_user() );

		self::assertSame( array( 'banners' => array(), 'next' => null, 'pending' => array() ), $seen[0] );
		self::assertSame( get_current_user_id(), $seen[1] );
		self::assertSame( Home::API_VERSION, $seen[2] );
		self::assertSame( array( 'banners' => array(), 'next' => null, 'pending' => array() ), $items );
	}

	public function test_nothing_handed_draws_no_banner_no_card_and_no_pending(): void {
		$html = $this->home();

		self::assertStringNotContainsString( 'data-banner=', $html );
		self::assertStringNotContainsString( 'site-pwa-next', $html );
		self::assertStringNotContainsString( 'site-pwa-pending-heading', $html );
		self::assertStringContainsString( 'No news yet.', $html );
	}

	public function test_what_is_handed_is_checked_before_it_is_drawn(): void {
		$this->feed(
			array(
				'banners' => array(
					array( 'id' => 'ffc:attendance:42', 'text' => 'Attendance confirmed at %s (18/11)', 'emphasis' => '<b>Encontro</b>' ),
					array( 'id' => 'Bad Id!', 'text' => 'kept, with a clean id', 'tone' => 'purple' ),
					array( 'id' => '', 'text' => 'no id' ),
					array( 'id' => 'x', 'text' => 'no place for %s or %s', 'emphasis' => 'it' ),
					'not an array',
				),
				'next'    => array(
					'title'   => '<i>Reunião pedagógica</i>',
					'meta'    => 'Auditório · 25/11 · 14:00',
					'actions' => array(
						array( 'label' => 'Presence QR', 'url' => '/app/s/presence/', 'icon' => 'qr', 'primary' => true ),
						array( 'label' => 'Elsewhere', 'url' => 'https://elsewhere.example/' ),
						array( 'label' => 'Details', 'url' => '/app/s/calendar/', 'icon' => 'nope' ),
						array( 'label' => 'Third', 'url' => '/x/' ),
					),
				),
				'pending' => array(
					array( 'label' => 'Post-event form', 'meta' => 'Until 28/11', 'url' => '/forms/1/', 'icon' => 'document' ),
					array( 'label' => 'Reregistration', 'meta' => 'Deadline 30/11', 'url' => '/rereg/', 'icon' => 'edit', 'tone' => 'warning' ),
					array( 'label' => 'No link' ),
					array( 'label' => 'Off site', 'url' => 'javascript:alert(1)' ),
				),
			)
		);

		$items = Home::items( wp_get_current_user() );

		self::assertSame( array( 'ffc:attendance:42', 'badid', 'x' ), array_column( $items['banners'], 'id' ) );
		self::assertSame( 'Encontro', $items['banners'][0]['emphasis'] );
		self::assertSame( 'success', $items['banners'][0]['tone'], 'a banner is a confirmation unless it says otherwise' );
		self::assertSame( 'info', $items['banners'][1]['tone'], 'an unknown tone falls back' );
		self::assertSame( '', $items['banners'][2]['emphasis'], 'a sentence without exactly one place drops the emphasis' );
		self::assertSame( 'Reunião pedagógica', $items['next']['title'] );
		self::assertSame( array( 'Presence QR', 'Details' ), array_column( $items['next']['actions'], 'label' ), 'off-site links are dropped and two actions kept' );
		self::assertSame( '', $items['next']['actions'][1]['icon'] );
		self::assertSame( array( 'Post-event form', 'Reregistration' ), array_column( $items['pending'], 'label' ) );
		self::assertSame( array( 'info', 'warning' ), array_column( $items['pending'], 'tone' ) );

		$html = $this->home();
		self::assertStringContainsString( 'Attendance confirmed at <strong>Encontro</strong> (18/11)', $html );
		self::assertStringContainsString( 'data-banner="ffc:attendance:42"', $html );
		self::assertStringContainsString( '<p class="site-pwa-next-title">Reunião pedagógica</p>', $html );
		self::assertMatchesRegularExpression( '#<a class="site-pwa-button" href="[^"]*/app/s/presence/">#', $html, 'the primary action is the accent button' );
		self::assertMatchesRegularExpression( '#<a class="site-pwa-button site-pwa-button-neutral" href="[^"]*/app/s/calendar/">#', $html );
		self::assertStringContainsString( '<strong>Post-event form</strong>', $html );
		self::assertStringContainsString( 'site-pwa-pending-icon site-pwa-tone-warning', $html );
		self::assertStringContainsString( 'Notifications, pending items: 2', $html );
		self::assertStringContainsString( 'class="site-pwa-badge"', $html );
		self::assertStringNotContainsString( 'elsewhere.example', $html );
		self::assertStringNotContainsString( 'javascript:', $html );
	}

	public function test_a_card_without_a_title_and_a_filter_that_returns_nonsense_draw_nothing(): void {
		$this->feed( array( 'next' => array( 'meta' => 'no title' ) ) );
		self::assertNull( Home::items( wp_get_current_user() )['next'] );

		remove_all_filters( Home::FILTER );
		add_filter( Home::FILTER, '__return_false' );
		self::assertSame( array( 'banners' => array(), 'next' => null, 'pending' => array() ), Home::items( wp_get_current_user() ) );
	}

	public function test_an_item_can_name_a_screen_the_user_may_open_instead_of_a_url(): void {
		add_action(
			\SitePwa\App\Screens::ACTION,
			static function ( $registry ): void {
				$registry->add( 'presence', array( 'label' => 'Presence', 'render' => static fn(): string => '' ) );
				$registry->add( 'staff', array( 'label' => 'Staff', 'capability' => 'manage_options', 'render' => static fn(): string => '' ) );
			}
		);
		\SitePwa\App\Screens::reset();
		$this->feed(
			array(
				'next'    => array( 'title' => 'Meeting', 'actions' => array( array( 'label' => 'QR', 'screen' => 'presence' ), array( 'label' => 'Staff only', 'screen' => 'staff' ) ) ),
				'pending' => array( array( 'label' => 'Unknown', 'screen' => 'nope' ), array( 'label' => 'Presence', 'screen' => 'presence' ) ),
			)
		);

		$items = Home::items( wp_get_current_user() );
		\SitePwa\App\Screens::reset();

		self::assertSame( array( 'QR' ), array_column( $items['next']['actions'], 'label' ), 'a screen the user cannot open is dropped' );
		self::assertStringEndsWith( '/s/presence/', $items['next']['actions'][0]['url'] );
		self::assertSame( array( 'Presence' ), array_column( $items['pending'], 'label' ) );
	}

	public function test_the_lists_are_capped(): void {
		$banners = array();
		$pending = array();
		for ( $i = 0; $i < 12; $i++ ) {
			$banners[] = array( 'id' => 'b' . $i, 'text' => 'Banner ' . $i );
			$pending[] = array( 'label' => 'Item ' . $i, 'url' => '/item/' . $i . '/' );
		}
		$this->feed( array( 'banners' => $banners, 'pending' => $pending ) );

		$items = Home::items( wp_get_current_user() );
		self::assertCount( Home::MAX_BANNERS, $items['banners'] );
		self::assertCount( Home::MAX_PENDING, $items['pending'] );
	}
}
