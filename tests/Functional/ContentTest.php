<?php

declare(strict_types=1);

namespace SitePwa\Tests\Functional;

use SitePwa\App\Content;
use SitePwa\App\Frame;
use SitePwa\App\Routes;
use SitePwa\Settings;
use WP_UnitTestCase;

/**
 * Posts and pages drawn inside the app at `/app/?p=ID`: who may read them,
 * which open on the site instead, and that every link leaving the app is the
 * permalink.
 *
 * @covers \SitePwa\App\Content
 * @covers \SitePwa\App\Routes
 * @covers \SitePwa\App\Frame
 */
final class ContentTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wp_styles']  = null;
		$GLOBALS['wp_scripts'] = null;
		update_option( Settings::OPTION, array( 'enabled' => true, 'name' => 'Escola Viva' ) );
		add_shortcode( 'site_pwa_probe', static fn(): string => '<span class="probe">' . (int) get_the_ID() . '</span>' );
	}

	protected function tearDown(): void {
		remove_shortcode( 'site_pwa_probe' );
		remove_all_filters( 'site_pwa_opens_in_app' );
		delete_option( Settings::OPTION );
		parent::tearDown();
	}

	private function signIn( string $role = 'subscriber' ): int {
		$id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $id );
		return $id;
	}

	/**
	 * Load `/app/?p=ID` the way WordPress parses it: the route var from the
	 * rewrite rule and `p` from the query string.
	 *
	 * @return array{status: int, headers: array<string, string>, body: string}
	 */
	private function open( int $id ): array {
		$this->go_to( home_url( '/?' . Routes::QUERY_VAR . '=home&p=' . $id ) );
		$response = Routes::response( Routes::current() );
		self::assertNotNull( $response );
		return $response;
	}

	private function post( array $args = array() ): int {
		return self::factory()->post->create(
			$args + array(
				'post_title'   => 'Reunião de pais',
				'post_content' => 'Quinta às 19h. [site_pwa_probe]',
			)
		);
	}

	public function test_the_app_url_of_a_post_is_the_home_with_its_id(): void {
		self::assertSame( home_url( '/app/?p=42' ), Content::url( 42 ) );
	}

	public function test_the_request_turns_the_home_with_an_id_into_the_post_route_of_its_type(): void {
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$vars = Content::request( array( Routes::QUERY_VAR => 'home', 'p' => (string) $page ) );

		self::assertSame( 'post', $vars[ Routes::QUERY_VAR ] );
		self::assertSame( $page, $vars['p'] );
		self::assertSame( 'page', $vars['post_type'] );
	}

	public function test_the_request_leaves_other_queries_alone(): void {
		$site = array( 'p' => '5' );
		self::assertSame( $site, Content::request( $site ) );

		$home = array( Routes::QUERY_VAR => 'home' );
		self::assertSame( $home, Content::request( $home ) );

		$zero = array( Routes::QUERY_VAR => 'home', 'p' => 'abc' );
		self::assertSame( $zero, Content::request( $zero ) );

		$unknown = Content::request( array( Routes::QUERY_VAR => 'home', 'p' => '999999' ) );
		self::assertSame( 'post', $unknown['post_type'] );
	}

	public function test_a_signed_out_visitor_goes_to_the_login_and_comes_back_to_the_post(): void {
		$id       = $this->post();
		$response = $this->open( $id );

		self::assertSame( 302, $response['status'] );
		self::assertSame( wp_login_url( Content::url( $id ) ), $response['headers']['Location'] );
		self::assertSame( 'private, no-store', $response['headers']['Cache-Control'] );
	}

	public function test_a_post_is_drawn_in_the_frame_with_its_shortcodes_run_in_the_loop(): void {
		$this->signIn();
		$id       = $this->post();
		$response = $this->open( $id );

		self::assertSame( 200, $response['status'] );
		self::assertSame( 'private, no-store', $response['headers']['Cache-Control'] );
		self::assertStringContainsString( '<main id="site-pwa-main"', $response['body'] );
		self::assertStringContainsString( '<h1 class="site-pwa-title">Reunião de pais</h1>', $response['body'] );
		self::assertStringContainsString( 'Quinta às 19h.', $response['body'] );
		self::assertStringContainsString( '<span class="probe">' . $id . '</span>', $response['body'] );
		self::assertStringNotContainsString( 'aria-current="page"', $response['body'] );
	}

	public function test_every_link_that_leaves_the_app_is_the_permalink(): void {
		$this->signIn();
		$id        = $this->post();
		$body      = $this->open( $id )['body'];
		$permalink = get_permalink( $id );

		self::assertSame( 1, substr_count( $body, '<link rel="canonical"' ) );
		self::assertStringContainsString( '<link rel="canonical" href="' . $permalink . '"', $body );
		self::assertStringContainsString( 'data-url="' . esc_url( $permalink ) . '"', $body );
		self::assertStringContainsString( '<a class="site-pwa-button site-pwa-button-neutral" href="' . esc_url( $permalink ) . '">', $body );
		self::assertStringNotContainsString( 'data-url="' . esc_url( Content::url( $id ) ) . '"', $body );
		self::assertMatchesRegularExpression( '/<button type="button" class="site-pwa-button" data-share hidden>/', $body );
	}

	public function test_the_post_screen_loads_the_share_script_and_names_the_post_in_the_title(): void {
		$this->signIn();
		$this->open( $this->post() );

		self::assertTrue( Frame::active() );
		Frame::enqueue();
		self::assertTrue( wp_script_is( Frame::SHARE_HANDLE, 'enqueued' ) );
		self::assertSame( 'Reunião de pais – Escola Viva', Frame::title( 'x' ) );
		self::assertFalse( Frame::adminBar( true ) );
	}

	public function test_the_share_script_stays_off_the_other_screens(): void {
		$this->signIn();
		set_query_var( Routes::QUERY_VAR, 'home' );
		Frame::enqueue();

		self::assertFalse( wp_script_is( Frame::SHARE_HANDLE, 'enqueued' ) );
		set_query_var( Routes::QUERY_VAR, '' );
	}

	public function test_a_page_opens_by_its_id_too(): void {
		$this->signIn();
		$page = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Calendário' ) );

		$response = $this->open( $page );

		self::assertSame( 200, $response['status'] );
		self::assertStringContainsString( 'Calendário', $response['body'] );
	}

	public function test_what_the_user_may_not_read_is_one_not_available_screen(): void {
		$this->signIn();
		register_post_type( 'site_pwa_hidden', array( 'public' => false ) );

		$cases = array(
			'draft'      => $this->post( array( 'post_status' => 'draft' ) ),
			'private'    => $this->post( array( 'post_status' => 'private', 'post_author' => self::factory()->user->create( array( 'role' => 'editor' ) ) ) ),
			'not viewable' => $this->post( array( 'post_type' => 'site_pwa_hidden' ) ),
			'missing'    => 999999,
		);
		foreach ( $cases as $label => $id ) {
			$response = $this->open( $id );
			self::assertSame( 404, $response['status'], $label );
			self::assertStringContainsString( 'This page is not available', $response['body'], $label );
			self::assertStringNotContainsString( 'Reunião de pais', $response['body'], $label );
			self::assertSame( 'Not available – Escola Viva', Frame::title( 'x' ), $label );
		}
		unregister_post_type( 'site_pwa_hidden' );
	}

	public function test_a_private_post_opens_for_whoever_may_read_it(): void {
		$this->signIn( 'administrator' );

		self::assertSame( 200, $this->open( $this->post( array( 'post_status' => 'private' ) ) )['status'] );
	}

	public function test_a_password_protected_post_asks_for_the_password_first(): void {
		$this->signIn();
		$body = $this->open( $this->post( array( 'post_password' => 'fake-secret' ) ) )['body'];

		self::assertStringContainsString( 'post-password-form', $body );
		self::assertStringNotContainsString( 'Quinta às 19h.', $body );
	}

	public function test_a_layout_that_depends_on_the_theme_opens_on_the_site(): void {
		$this->signIn();
		$cases = array(
			'elementor' => array( '_elementor_edit_mode', 'builder' ),
			'divi'      => array( '_et_pb_use_builder', 'on' ),
			'beaver'    => array( '_fl_builder_enabled', '1' ),
			'wpbakery'  => array( '_wpb_vc_js_status', 'true' ),
			'bricks'    => array( '_bricks_editor_mode', 'bricks' ),
			'oxygen'    => array( 'ct_builder_shortcodes', '[ct_section]' ),
			'template'  => array( '_wp_page_template', 'full-width.php' ),
		);
		foreach ( $cases as $label => list( $key, $value ) ) {
			$id = $this->post( array( 'post_type' => 'page' ) );
			update_post_meta( $id, $key, $value );

			$response = $this->open( $id );

			self::assertSame( 302, $response['status'], $label );
			self::assertSame( get_permalink( $id ), $response['headers']['Location'], $label );
		}
	}

	public function test_a_builder_meta_switched_off_still_opens_in_the_app(): void {
		$this->signIn();
		$id = $this->post();
		update_post_meta( $id, '_elementor_edit_mode', '' );
		update_post_meta( $id, '_et_pb_use_builder', 'off' );

		self::assertSame( 200, $this->open( $id )['status'] );
	}

	public function test_a_filter_decides_either_way(): void {
		$this->signIn();
		$id = $this->post();
		add_filter( 'site_pwa_opens_in_app', '__return_false' );

		self::assertSame( 302, $this->open( $id )['status'] );

		remove_all_filters( 'site_pwa_opens_in_app' );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		add_filter( 'site_pwa_opens_in_app', '__return_true' );

		self::assertSame( 200, $this->open( $id )['status'] );
	}

	public function test_the_post_route_answers_nothing_while_the_app_is_off(): void {
		$this->signIn();
		$id = $this->post();
		delete_option( Settings::OPTION );
		$this->go_to( home_url( '/?' . Routes::QUERY_VAR . '=home&p=' . $id ) );

		self::assertNull( Routes::response( Routes::current() ) );
	}

	public function test_the_request_filter_is_wired(): void {
		self::assertSame( 10, has_filter( 'request', array( Content::class, 'request' ) ) );
	}
}
