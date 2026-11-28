<?php

declare(strict_types=1);

namespace SitePwa\Tests\Functional;

use SitePwa\App\Routes;
use SitePwa\App\Screens;
use SitePwa\Settings;
use WP_Error;
use WP_UnitTestCase;

/**
 * A screen a person must complete before using the app (#80).
 *
 * @covers \SitePwa\App\Screens
 * @covers \SitePwa\App\Routes
 * @covers \SitePwa\App\Frame
 */
final class RequiredScreensTest extends WP_UnitTestCase {

	/**
	 * Whether the signed-in person still has to complete the details screen.
	 *
	 * @var bool
	 */
	private bool $missing = true;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wp_styles']  = null;
		$GLOBALS['wp_scripts'] = null;
		Screens::reset();
		update_option( Settings::OPTION, array( 'enabled' => true, 'name' => 'Escola Viva' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
	}

	protected function tearDown(): void {
		remove_all_actions( Screens::ACTION );
		Screens::reset();
		set_query_var( Routes::QUERY_VAR, '' );
		set_query_var( Routes::SCREEN_VAR, '' );
		unset( $_GET[ Screens::NEXT ] );
		delete_option( Settings::OPTION );
		parent::tearDown();
	}

	/**
	 * Register a required details screen and an ordinary one.
	 *
	 * @param array<string, mixed> $details Extra arguments of the details screen.
	 * @return array<string, true|WP_Error>
	 */
	private function register( array $details = array() ): array {
		$results = array();
		add_action(
			Screens::ACTION,
			function ( Screens $registry ) use ( $details, &$results ): void {
				$results['details'] = $registry->add(
					'details',
					$details + array(
						'label'    => 'Complete your details',
						'render'   => static fn(): string => '<form class="details"></form>',
						'required' => fn( \WP_User $user ): bool => $this->missing && $user->ID > 0,
						'position' => 50,
					)
				);
				$results['presence'] = $registry->add(
					'presence',
					array(
						'label'  => 'Presence',
						'place'  => 'nav',
						'render' => static fn(): string => '<p class="presence">Code</p>',
					)
				);
			}
		);
		Screens::all();
		return $results;
	}

	private function open( string $route, string $id = '' ): array {
		set_query_var( Routes::QUERY_VAR, $route );
		set_query_var( Routes::SCREEN_VAR, $id );
		return Routes::response( $route );
	}

	public function test_every_screen_leads_to_the_required_one_carrying_where_the_person_was_going(): void {
		$this->register();
		$details = home_url( '/app/s/details/' );

		foreach ( array( 'home' => Routes::url( 'home' ), 'more' => Routes::url( 'more' ), 'news' => Routes::url( 'news' ) ) as $route => $url ) {
			$response = $this->open( $route );
			self::assertSame( 302, $response['status'], $route );
			self::assertSame( add_query_arg( Screens::NEXT, rawurlencode( $url ), $details ), $response['headers']['Location'], $route );
		}
		$screen = $this->open( 'screen', 'presence' );
		self::assertSame( add_query_arg( Screens::NEXT, rawurlencode( home_url( '/app/s/presence/' ) ), $details ), $screen['headers']['Location'] );
	}

	public function test_the_required_screen_is_drawn_without_the_navigation_and_with_a_way_out(): void {
		$this->register();

		$response = $this->open( 'screen', 'details' );

		self::assertSame( 200, $response['status'] );
		self::assertStringContainsString( '<form class="details"></form>', $response['body'] );
		self::assertStringContainsString( 'site-pwa-required-note', $response['body'] );
		self::assertStringContainsString( 'site-pwa-required-signout', $response['body'] );
		self::assertStringNotContainsString( 'class="site-pwa-nav"', $response['body'], 'nowhere else to go' );
	}

	public function test_once_complete_it_sends_the_person_on_and_the_app_opens_again(): void {
		$this->register();
		$this->missing = false;

		$_GET[ Screens::NEXT ] = home_url( '/app/s/presence/' );
		$response              = $this->open( 'screen', 'details' );
		self::assertSame( 302, $response['status'] );
		self::assertSame( home_url( '/app/s/presence/' ), $response['headers']['Location'] );

		$_GET[ Screens::NEXT ] = 'https://elsewhere.example/';
		self::assertSame( Routes::url( 'home' ), $this->open( 'screen', 'details' )['headers']['Location'], 'never off the app' );

		unset( $_GET[ Screens::NEXT ] );
		$plain = $this->open( 'screen', 'details' );
		self::assertSame( 200, $plain['status'], 'opened at its address, it is an ordinary screen' );
		self::assertStringContainsString( 'class="site-pwa-nav"', $plain['body'] );
		self::assertStringNotContainsString( '/app/s/details/', $plain['body'], 'a step, never listed (#82)' );
		self::assertSame( array( 'presence' ), array_column( Screens::visible(), 'id' ) );
		self::assertStringNotContainsString( 'site-pwa-required-note', $plain['body'] );
		self::assertSame( 200, $this->open( 'home' )['status'] );
	}

	public function test_a_screen_the_person_may_not_open_or_a_broken_check_never_locks_the_app(): void {
		$this->register( array( 'capability' => 'manage_options' ) );
		self::assertSame( 200, $this->open( 'home' )['status'], 'not theirs to complete' );

		Screens::reset();
		remove_all_actions( Screens::ACTION );
		$this->register(
			array(
				'required' => static function (): bool {
					throw new \RuntimeException( 'broken' );
				},
			)
		);
		self::assertSame( 200, $this->open( 'home' )['status'], 'a check that throws leaves the app open' );

		Screens::reset();
		remove_all_actions( Screens::ACTION );
		$this->register( array( 'required' => static fn() => 1 ) );
		self::assertSame( 200, $this->open( 'home' )['status'], 'only a real true counts' );
	}

	public function test_the_first_required_screen_by_position_comes_first(): void {
		add_action(
			Screens::ACTION,
			static function ( Screens $registry ): void {
				foreach ( array( 'second' => 20, 'first' => 5 ) as $id => $position ) {
					$registry->add(
						$id,
						array(
							'label'    => ucfirst( $id ),
							'render'   => '__return_empty_string',
							'required' => '__return_true',
							'position' => $position,
						)
					);
				}
			}
		);

		self::assertSame( 'first', Screens::pending( wp_get_current_user() )['id'] ?? null );
		self::assertNull( Screens::pending( new \WP_User( 0 ) ), 'nobody signed in' );
	}

	public function test_only_a_callable_on_a_screen_the_app_draws_can_be_required(): void {
		$this->setExpectedIncorrectUsage( 'SitePwa\App\Screens::add' );
		$results = array();
		add_action(
			Screens::ACTION,
			static function ( Screens $registry ) use ( &$results ): void {
				$results['text'] = $registry->add(
					'text',
					array(
						'label'    => 'Text',
						'render'   => '__return_empty_string',
						'required' => 'not a function',
					)
				);
				$results['link'] = $registry->add(
					'link',
					array(
						'label'    => 'Link',
						'url'      => '/somewhere/',
						'required' => '__return_true',
					)
				);
			}
		);
		Screens::all();

		self::assertInstanceOf( WP_Error::class, $results['text'] );
		self::assertInstanceOf( WP_Error::class, $results['link'] );
		self::assertSame( array(), Screens::all() );
	}
}
