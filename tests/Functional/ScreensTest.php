<?php

declare(strict_types=1);

namespace SitePwa\Tests\Functional;

use SitePwa\App\Frame;
use SitePwa\App\Navigation;
use SitePwa\App\Routes;
use SitePwa\App\Screens;
use SitePwa\Settings;
use SitePwa\Tests\Functional\Fixtures\ExampleConsumer;
use WP_Error;
use WP_UnitTestCase;

require_once __DIR__ . '/Fixtures/example-consumer.php';

/**
 * The screen registration contract, through a fake consumer: what it may
 * register, where each screen goes, and that a screen never shows to someone
 * who cannot open it.
 *
 * @covers \SitePwa\App\Screens
 * @covers \SitePwa\App\Navigation
 * @covers \SitePwa\App\Routes
 * @covers \SitePwa\App\Frame
 */
final class ScreensTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wp_styles']  = null;
		$GLOBALS['wp_scripts'] = null;
		Screens::reset();
		update_option( Settings::OPTION, array( 'enabled' => true, 'name' => 'Escola Viva' ) );
	}

	protected function tearDown(): void {
		remove_all_actions( Screens::ACTION );
		Screens::reset();
		set_query_var( Routes::QUERY_VAR, '' );
		set_query_var( Routes::SCREEN_VAR, '' );
		delete_option( Settings::OPTION );
		parent::tearDown();
	}

	private function signIn( string $role = 'subscriber' ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	/**
	 * Register through the action, as a consumer does, and return what add() said.
	 *
	 * @param array<string, array<string, mixed>> $screens Id => args.
	 * @return array<string, true|WP_Error>
	 */
	private function register( array $screens ): array {
		$results = array();
		add_action(
			Screens::ACTION,
			static function ( Screens $registry ) use ( $screens, &$results ): void {
				foreach ( $screens as $id => $args ) {
					$results[ $id ] = $registry->add( $id, $args );
				}
			}
		);
		Screens::all();
		return $results;
	}

	private function open( string $id ): array {
		set_query_var( Routes::QUERY_VAR, 'screen' );
		set_query_var( Routes::SCREEN_VAR, $id );
		return Routes::response( 'screen' );
	}

	public function test_the_action_fires_once_with_the_contracts_version(): void {
		$calls = array();
		add_action(
			Screens::ACTION,
			static function ( $registry, int $api ) use ( &$calls ): void {
				$calls[] = array( get_class( $registry ), $api );
			},
			10,
			2
		);

		Screens::all();
		Screens::all();

		self::assertSame( array( array( Screens::class, 1 ) ), $calls );
	}

	public function test_a_consumer_needs_no_check_because_without_the_app_the_action_never_fires(): void {
		ExampleConsumer::register();

		self::assertSame( 10, has_action( Screens::ACTION, array( ExampleConsumer::class, 'screens' ) ) );
		self::assertSame( array( 'presence', 'reports', 'calendar' ), array_keys( Screens::all() ) );
	}

	public function test_the_example_consumers_screens_take_their_places_and_defaults(): void {
		ExampleConsumer::register();
		$all = Screens::all();

		self::assertSame( 'nav', $all['presence']['place'] );
		self::assertSame( 'read', $all['presence']['capability'] );
		self::assertSame( 'more', $all['calendar']['place'] );
		self::assertSame( home_url( '/calendar/' ), $all['calendar']['url'] );
		self::assertSame( home_url( '/calendar/' ), Screens::link( $all['calendar'] ) );
		self::assertSame( home_url( '/app/s/presence/' ), Screens::link( $all['presence'] ) );
		self::assertSame( 10, $all['calendar']['position'] );
	}

	public function test_invalid_registrations_are_refused_with_the_reason(): void {
		$this->setExpectedIncorrectUsage( 'SitePwa\App\Screens::add' );
		$render  = static fn(): string => '';
		$results = $this->register(
			array(
				'Bad Id'   => array( 'label' => 'X', 'render' => $render ),
				'account'  => array( 'label' => 'X', 'render' => $render ),
				'no-label' => array( 'render' => $render ),
				'place'    => array( 'label' => 'X', 'place' => 'top', 'render' => $render ),
				'both'     => array( 'label' => 'X', 'render' => $render, 'url' => '/x/' ),
				'neither'  => array( 'label' => 'X' ),
				'uncall'   => array( 'label' => 'X', 'render' => 'no_such_function_here' ),
				'external' => array( 'label' => 'X', 'url' => 'https://elsewhere.example/' ),
				'relative' => array( 'label' => 'X', 'url' => '//elsewhere.example/x' ),
				'scheme'   => array( 'label' => 'X', 'url' => 'javascript:alert(1)' ),
				'slot'     => array( 'label' => 'X', 'place' => 'nav', 'slot' => 'left', 'render' => $render ),
				'more-mid' => array( 'label' => 'X', 'slot' => 'center', 'render' => $render ),
				'section'  => array( 'label' => 'X', 'section' => 'admin', 'render' => $render ),
				'badge'    => array( 'label' => 'X', 'badge' => 42, 'render' => $render ),
				'ok'       => array( 'label' => 'X', 'render' => $render ),
			)
		);
		$codes = array_map( static fn( $r ) => is_wp_error( $r ) ? $r->get_error_code() : $r, $results );

		self::assertSame(
			array(
				'Bad Id'   => 'site_pwa_screen_id',
				'account'  => 'site_pwa_screen_reserved',
				'no-label' => 'site_pwa_screen_label',
				'place'    => 'site_pwa_screen_place',
				'both'     => 'site_pwa_screen_target',
				'neither'  => 'site_pwa_screen_target',
				'uncall'   => 'site_pwa_screen_render',
				'external' => 'site_pwa_screen_url',
				'relative' => 'site_pwa_screen_url',
				'scheme'   => 'site_pwa_screen_url',
				'slot'     => 'site_pwa_screen_slot',
				'more-mid' => 'site_pwa_screen_slot',
				'section'  => 'site_pwa_screen_section',
				'badge'    => 'site_pwa_screen_badge',
				'ok'       => true,
			),
			$codes
		);
		self::assertSame( array( 'ok' ), array_keys( Screens::all() ) );
	}

	public function test_a_second_registration_of_an_id_is_refused_and_the_first_kept(): void {
		$this->setExpectedIncorrectUsage( 'SitePwa\App\Screens::add' );
		$results = $this->register(
			array(
				'dup' => array( 'label' => 'First', 'url' => '/a/' ),
			)
		);
		Screens::reset();
		add_action(
			Screens::ACTION,
			static function ( Screens $registry ) use ( &$results ): void {
				$registry->add( 'dup', array( 'label' => 'First', 'url' => '/a/' ) );
				$results['again'] = $registry->add( 'dup', array( 'label' => 'Second', 'url' => '/b/' ) );
			}
		);

		self::assertSame( 'First', Screens::all()['dup']['label'] );
		self::assertSame( 'site_pwa_screen_duplicate', $results['again']->get_error_code() );
	}

	public function test_an_unknown_icon_falls_back_and_markup_leaves_the_label(): void {
		$this->register( array( 'x' => array( 'label' => '<b>Notas</b>', 'icon' => '<script>', 'url' => home_url( '/notas/' ) ) ) );

		self::assertSame( 'grid', Screens::all()['x']['icon'] );
		self::assertSame( 'Notas', Screens::all()['x']['label'] );
	}

	public function test_a_screen_shows_only_to_whoever_holds_its_capability(): void {
		ExampleConsumer::register();
		$this->signIn();

		self::assertSame( array( 'presence', 'calendar' ), array_column( Screens::visible(), 'id' ) );
		self::assertNull( Screens::find( 'reports' ) );
		$response = $this->open( 'reports' );
		self::assertSame( 404, $response['status'] );
		self::assertStringNotContainsString( 'Only editors', $response['body'] );
		self::assertStringNotContainsString( 'Reports', $response['body'] );

		$this->signIn( 'editor' );
		self::assertSame( array( 'presence', 'calendar', 'reports' ), array_column( Screens::visible(), 'id' ) );
		self::assertStringContainsString( '<p class="report">Only editors see this.</p>', $this->open( 'reports' )['body'] );
	}

	public function test_a_screen_is_drawn_in_the_frame_under_its_heading_with_its_tab_current(): void {
		ExampleConsumer::register();
		$this->signIn();

		$response = $this->open( 'presence' );

		self::assertSame( 200, $response['status'] );
		self::assertSame( 'private, no-store', $response['headers']['Cache-Control'] );
		self::assertStringContainsString( '<h1 class="site-pwa-title">Presence</h1>', $response['body'] );
		self::assertStringContainsString( '<p class="presence">Show this code at the entrance.</p>', $response['body'] );
		self::assertMatchesRegularExpression( '#href="' . preg_quote( home_url( '/app/s/presence/' ), '#' ) . '" aria-current="page"#', $response['body'] );
		self::assertSame( 'Presence – Escola Viva', Frame::title( 'x' ) );
	}

	public function test_a_screen_both_printing_and_returning_keeps_both_in_order(): void {
		$this->signIn();
		$this->register(
			array(
				'mixed' => array(
					'label'  => 'Mixed',
					'render' => static function (): string {
						echo '<i>printed</i>';
						return '<b>returned</b>';
					},
				),
			)
		);

		self::assertStringContainsString( '<i>printed</i><b>returned</b>', $this->open( 'mixed' )['body'] );
	}

	public function test_unknown_and_url_screens_have_no_page_in_the_app(): void {
		ExampleConsumer::register();
		$this->signIn();

		foreach ( array( 'nothing', 'calendar' ) as $id ) {
			$response = $this->open( $id );
			self::assertSame( 404, $response['status'], $id );
			self::assertStringContainsString( 'This page is not available', $response['body'], $id );
		}
		self::assertSame( 'Not available – Escola Viva', Frame::title( 'x' ) );
	}

	public function test_a_signed_out_visitor_goes_to_the_login_and_comes_back_to_the_screen(): void {
		ExampleConsumer::register();

		$response = $this->open( 'presence' );

		self::assertSame( 302, $response['status'] );
		self::assertSame( wp_login_url( home_url( '/app/s/presence/' ) ), $response['headers']['Location'] );
	}

	public function test_the_bar_holds_home_three_nav_screens_and_more_and_the_rest_join_more(): void {
		$this->signIn();
		$screens = array();
		foreach ( array( 'a', 'b', 'c', 'd' ) as $i => $id ) {
			$screens[ $id ] = array( 'label' => strtoupper( $id ), 'place' => 'nav', 'position' => $i, 'url' => '/' . $id . '/' );
		}
		$screens['e'] = array( 'label' => 'E', 'url' => '/e/' );
		$this->register( $screens );

		self::assertSame( array( 'home', 'screen:a', 'screen:b', 'screen:c', 'more' ), array_column( Navigation::items( 'home' ), 'id' ) );
		self::assertSame( array( 'd', 'e' ), array_column( Screens::split()['more'], 'id' ) );
		self::assertSame( 'screen:b', Navigation::key( 'screen', 'b' ) );
		self::assertSame( 'more', Navigation::key( 'screen', 'd' ) );
	}

	public function test_the_centre_slot_sits_raised_in_the_middle_and_takes_one_of_the_three(): void {
		$this->signIn();
		$this->register(
			array(
				'agenda'   => array( 'label' => 'Agenda', 'place' => 'nav', 'position' => 1, 'url' => '/agenda/' ),
				'certs'    => array( 'label' => 'Certificates', 'place' => 'nav', 'position' => 2, 'url' => '/certs/' ),
				'presence' => array( 'label' => 'Presence', 'place' => 'nav', 'slot' => 'center', 'position' => 3, 'icon' => 'qr', 'url' => '/presence/' ),
				'extra'    => array( 'label' => 'Extra', 'place' => 'nav', 'position' => 4, 'url' => '/extra/' ),
				'second'   => array( 'label' => 'Second', 'place' => 'nav', 'slot' => 'center', 'position' => 5, 'url' => '/second/' ),
			)
		);

		$items = Navigation::items( 'screen:presence' );
		self::assertSame( array( 'home', 'screen:agenda', 'screen:presence', 'screen:certs', 'more' ), array_column( $items, 'id' ) );
		self::assertSame( array( false, false, true, false, false ), array_column( $items, 'raised' ) );
		self::assertSame( array( 'extra', 'second' ), array_column( Screens::split()['more'], 'id' ), 'a second centre screen is an ordinary nav screen' );
		self::assertSame( 'screen:presence', Navigation::key( 'screen', 'presence' ) );

		set_query_var( Routes::QUERY_VAR, 'home' );
		$html = \SitePwa\App\Frame::render( 'home' );
		self::assertMatchesRegularExpression( '#<li class="site-pwa-nav-raised">\s*<a href="[^"]*/presence/">\s*<span class="site-pwa-nav-lift"><svg#', $html );
	}

	public function test_a_screen_in_the_bar_shows_its_badge_over_its_icon_and_keeps_it_in_more(): void {
		$this->signIn();
		$this->register(
			array(
				'certs'  => array( 'label' => 'Certificates', 'place' => 'nav', 'position' => 1, 'badge' => static fn(): string => '<b>2</b>', 'url' => '/certs/' ),
				'agenda' => array( 'label' => 'Agenda', 'place' => 'nav', 'position' => 2, 'badge' => static fn(): string => '', 'url' => '/agenda/' ),
				'broken' => array(
					'label'    => 'Broken',
					'place'    => 'nav',
					'position' => 3,
					'url'      => '/broken/',
					'badge'    => static function (): string {
						throw new \RuntimeException( 'down' );
					},
				),
			)
		);

		self::assertSame( array( '', '2', '', '', '' ), array_column( Navigation::items( 'home' ), 'badge' ) );

		set_query_var( Routes::QUERY_VAR, 'home' );
		$html = \SitePwa\App\Frame::render( 'home' );
		self::assertSame( 1, substr_count( $html, 'class="site-pwa-nav-badge"' ), 'an empty or failing badge draws nothing' );
		self::assertMatchesRegularExpression( '#<span>Certificates</span>\s*<span class="site-pwa-nav-badge" aria-hidden="true">2</span>\s*<span class="site-pwa-visually-hidden">2</span>#', $html );
	}

	public function test_a_lone_centre_screen_still_sits_between_home_and_more(): void {
		$this->signIn();
		$this->register( array( 'presence' => array( 'label' => 'Presence', 'place' => 'nav', 'slot' => 'center', 'url' => '/presence/' ) ) );
		self::assertSame( array( 'home', 'screen:presence', 'more' ), array_column( Navigation::items( 'home' ), 'id' ) );
	}

	public function test_more_lists_the_overflow_then_the_account_and_is_current(): void {
		ExampleConsumer::register();
		$this->signIn();
		set_query_var( Routes::QUERY_VAR, 'more' );

		$response = Routes::response( 'more' );
		$body     = $response['body'];

		self::assertSame( 200, $response['status'] );
		self::assertStringContainsString( '<div class="site-pwa-more">', $body );
		self::assertLessThan( strpos( $body, home_url( '/calendar/' ) ), strpos( $body, Routes::url( 'account' ) ), 'the profile card comes first' );
		self::assertLessThan( strpos( $body, 'site-pwa-signout' ), strpos( $body, home_url( '/calendar/' ) ) );
		self::assertMatchesRegularExpression( '#href="' . preg_quote( Routes::url( 'more' ), '#' ) . '" aria-current="page"#', $body );
		self::assertStringNotContainsString( 'Reports', $body );
		self::assertSame( 'More – Escola Viva', Frame::title( 'x' ) );
	}

	public function test_home_no_longer_lists_the_screens_the_navigation_already_shows(): void {
		ExampleConsumer::register();
		$this->signIn();
		set_query_var( Routes::QUERY_VAR, 'home' );

		$body = Frame::render( 'home' );

		self::assertStringNotContainsString( 'Your screens', $body );
		self::assertStringNotContainsString( 'Nothing here yet', $body );
		self::assertStringContainsString( '<span>Presence</span>', $body, 'still in the bar' );
	}

	public function test_the_screen_and_more_routes_have_rules_and_their_query_var_is_public(): void {
		global $wp_rewrite;
		Routes::addRules();

		self::assertSame( 'index.php?site_pwa_route=more', $wp_rewrite->extra_rules_top['^app/more/?$'] );
		self::assertSame( 'index.php?site_pwa_route=screen&site_pwa_screen=$matches[1]', $wp_rewrite->extra_rules_top['^app/s/([a-z0-9-]+)/?$'] );
		self::assertContains( Routes::SCREEN_VAR, Routes::queryVars( array() ) );
	}

	public function test_more_groups_screens_into_services_and_operator_with_their_subtitle_and_badge(): void {
		$this->signIn();
		$render = static fn(): string => 'x';
		$this->register(
			array(
				'certs'     => array( 'label' => 'Certificates', 'render' => $render, 'subtitle' => '<b>Issued</b> to you' ),
				'terminals' => array(
					'label'    => 'Terminals',
					'render'   => $render,
					'section'  => 'operator',
					'subtitle' => 'Check-in tablets',
					'badge'    => static fn(): string => '2 active · <em>1</em> offline',
				),
				'broken'    => array(
					'label'   => 'Broken',
					'render'  => $render,
					'section' => 'operator',
					'badge'   => static function (): string {
						throw new \RuntimeException( 'down' );
					},
				),
			)
		);
		$all = Screens::all();
		self::assertSame( 'services', $all['certs']['section'], 'the default section' );
		self::assertSame( 'Issued to you', $all['certs']['subtitle'] );
		self::assertSame( '2 active · 1 offline', Screens::badge( $all['terminals'] ) );
		self::assertSame( '', Screens::badge( $all['broken'] ), 'a badge that throws shows nothing' );
		self::assertSame( 'New', Screens::badge( array( 'badge' => ' New ' ) ) );
		self::assertSame( '', Screens::badge( array( 'badge' => static fn(): int => 3 ) ) );

		set_query_var( Routes::QUERY_VAR, 'more' );
		$more     = \SitePwa\App\Frame::render( 'more' );
		$services = strpos( $more, 'id="site-pwa-more-services"' );
		$operator = strpos( $more, 'id="site-pwa-more-operator"' );
		self::assertNotFalse( $services );
		self::assertNotFalse( $operator );
		self::assertLessThan( strpos( $more, '<strong>Certificates</strong>' ), $services );
		self::assertLessThan( $operator, strpos( $more, '<strong>Certificates</strong>' ) );
		self::assertLessThan( strpos( $more, '<strong>Terminals</strong>' ), $operator );
		self::assertStringContainsString( '<span class="site-pwa-badge-text">2 active · 1 offline</span>', $more );
		self::assertStringContainsString( '<span>Check-in tablets</span>', $more );
	}

	public function test_more_has_no_operator_section_when_no_screen_sits_there(): void {
		$this->signIn();
		$this->register( array( 'certs' => array( 'label' => 'Certificates', 'render' => static fn(): string => 'x' ) ) );
		set_query_var( Routes::QUERY_VAR, 'more' );
		self::assertStringNotContainsString( 'site-pwa-more-operator', \SitePwa\App\Frame::render( 'more' ) );
	}

	public function test_my_details_opens_the_screen_a_plugin_names_when_the_person_may_open_it(): void {
		$this->signIn();
		$this->register(
			array(
				'profile' => array( 'label' => 'Profile', 'render' => static fn(): string => '<p>Profile</p>' ),
				'locked'  => array( 'label' => 'Locked', 'render' => static fn(): string => '', 'capability' => 'manage_options' ),
			)
		);
		$user = wp_get_current_user();

		self::assertSame( Routes::url( 'account' ), \SitePwa\App\Frame::detailsUrl( $user ), 'nobody named a screen' );

		add_filter( 'site_pwa_details_screen', static fn(): string => 'profile' );
		self::assertSame( home_url( '/app/s/profile/' ), \SitePwa\App\Frame::detailsUrl( $user ) );
		remove_all_filters( 'site_pwa_details_screen' );

		add_filter( 'site_pwa_details_screen', static fn(): string => 'locked' );
		self::assertSame( Routes::url( 'account' ), \SitePwa\App\Frame::detailsUrl( $user ), 'a screen this person may not open' );
		remove_all_filters( 'site_pwa_details_screen' );

		add_filter( 'site_pwa_details_screen', static fn() => array( 'profile' ) );
		self::assertSame( Routes::url( 'account' ), \SitePwa\App\Frame::detailsUrl( $user ), 'not an id' );
		remove_all_filters( 'site_pwa_details_screen' );
	}
}
