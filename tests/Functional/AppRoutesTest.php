<?php

declare(strict_types=1);

namespace SitePwa\Tests\Functional;

use SitePwa\App\Manifest;
use SitePwa\App\Pages;
use SitePwa\App\Routes;
use SitePwa\App\ServiceWorker;
use SitePwa\Plugin;
use SitePwa\Settings;
use WP_UnitTestCase;

/**
 * The app's routes inside a real WordPress: what each one answers with the
 * app on and off, and the rules that reach them.
 *
 * @covers \SitePwa\App\Routes
 * @covers \SitePwa\App\Manifest
 * @covers \SitePwa\App\ServiceWorker
 * @covers \SitePwa\App\Pages
 */
final class AppRoutesTest extends WP_UnitTestCase {

	protected function tearDown(): void {
		delete_option( Settings::OPTION );
		delete_option( 'site_icon' );
		remove_all_filters( 'site_pwa_app_prefix' );
		parent::tearDown();
	}

	private function turnOn(): void {
		update_option( Settings::OPTION, array( 'enabled' => true, 'name' => 'Escola Viva', 'short_name' => 'Viva' ) );
	}

	public function test_the_routes_answer_nothing_while_the_app_is_off(): void {
		foreach ( Routes::ROUTES as $route ) {
			self::assertNull( Routes::response( $route ), "{$route} answered with the app off." );
		}
	}

	public function test_the_worker_answers_off_too_with_one_that_removes_itself(): void {
		$response = Routes::response( 'sw' );

		self::assertNotNull( $response );
		self::assertStringContainsString( 'registration.unregister', $response['body'] );
		self::assertStringNotContainsString( 'SITE_PWA_CONFIG', $response['body'] );
	}

	public function test_the_worker_is_served_as_javascript_never_cached_and_allowed_at_the_root(): void {
		$this->turnOn();
		$response = Routes::response( 'sw' );

		self::assertSame( 'application/javascript; charset=utf-8', $response['headers']['Content-Type'] );
		self::assertSame( 'no-cache', $response['headers']['Cache-Control'] );
		self::assertSame( '/', $response['headers']['Service-Worker-Allowed'] );
		self::assertStringStartsWith( 'self.SITE_PWA_CONFIG = {', $response['body'] );
	}

	public function test_the_worker_config_names_the_app_path_and_precaches_only_files_without_user_data(): void {
		$this->turnOn();
		$config = ServiceWorker::config();

		self::assertSame( '/app/', $config['appPath'] );
		self::assertSame( array( Routes::url( 'offline' ), Pages::stylesheet() ), $config['precache'] );
		self::assertStringStartsWith( ServiceWorker::CACHE_PREFIX, $config['cache'] );
	}

	public function test_the_manifest_carries_the_settings_and_starts_inside_the_app(): void {
		$this->turnOn();
		$response = Routes::response( 'manifest' );
		$manifest = json_decode( $response['body'], true );

		self::assertSame( 'application/manifest+json; charset=utf-8', $response['headers']['Content-Type'] );
		self::assertSame( 'no-cache', $response['headers']['Cache-Control'], 'a changed colour or icon must reach an installed app (#28)' );
		self::assertSame( 'Escola Viva', $manifest['name'] );
		self::assertSame( 'Viva', $manifest['short_name'] );
		self::assertSame( home_url( '/app/' ), $manifest['start_url'] );
		self::assertSame( home_url( '/app/' ), $manifest['scope'] );
		self::assertSame( 'standalone', $manifest['display'] );
		self::assertSame( array( array( 'platform' => 'webapp', 'url' => Routes::url( 'manifest' ) ) ), $manifest['related_applications'], 'names itself, so the install page can tell it is installed (#34)' );
		self::assertArrayNotHasKey( 'prefer_related_applications', $manifest );
	}

	public function test_without_a_site_icon_the_plugin_icons_ship_including_a_maskable_one(): void {
		$icons = Manifest::icons();

		self::assertSame( array( 'any', 'any', 'maskable' ), array_column( $icons, 'purpose' ) );
		foreach ( $icons as $icon ) {
			$file = Plugin::dir() . substr( $icon['src'], strlen( Plugin::url() ) );
			self::assertFileExists( $file );
			$size = getimagesize( $file );
			self::assertSame( $icon['sizes'], $size[0] . 'x' . $size[1] );
		}
	}

	public function test_the_site_icon_wins_and_is_never_offered_as_maskable(): void {
		$attachment = self::factory()->attachment->create_upload_object( Plugin::dir() . 'assets/img/brand/icon-512.png' );
		update_option( 'site_icon', $attachment );

		$icons = Manifest::icons();

		self::assertSame( array( '192x192', '512x512' ), array_column( $icons, 'sizes' ) );
		self::assertSame( array( 'any', 'any' ), array_column( $icons, 'purpose' ) );
		self::assertSame( 'image/png', $icons[0]['type'] );
		self::assertSame( get_site_icon_url( 192 ), Manifest::touchIcon() );
	}

	public function test_a_screen_sends_a_signed_out_visitor_to_the_login_url_and_back(): void {
		$this->turnOn();
		foreach ( array( 'home', 'account' ) as $route ) {
			$response = Routes::response( $route );

			self::assertSame( 302, $response['status'] );
			self::assertSame( \SitePwa\App\AppLogin::url( Routes::url( $route ) ), $response['headers']['Location'], 'the app signs in on its own screen (#74)' );
			self::assertSame( 'private, no-store', $response['headers']['Cache-Control'] );
		}

		add_filter( 'site_pwa_app_login', '__return_false' );
		self::assertSame( wp_login_url( Routes::url( 'home' ) ), Routes::response( 'home' )['headers']['Location'], 'switched off, WordPress\'s login' );
		remove_all_filters( 'site_pwa_app_login' );
	}

	public function test_a_screen_is_private_for_a_signed_in_user(): void {
		$this->turnOn();
		wp_set_current_user( self::factory()->user->create( array( 'display_name' => 'Maria Souza' ) ) );
		set_query_var( Routes::QUERY_VAR, 'home' );

		$response = Routes::response( 'home' );

		self::assertSame( 200, $response['status'] );
		self::assertSame( 'private, no-store', $response['headers']['Cache-Control'] );
		self::assertStringContainsString( 'Maria Souza', $response['body'] );
	}

	public function test_the_offline_page_carries_no_user_data_and_no_nonce(): void {
		$this->turnOn();
		wp_set_current_user( self::factory()->user->create( array( 'display_name' => 'Maria Souza' ) ) );

		$body = Routes::response( 'offline' )['body'];

		self::assertStringNotContainsString( 'Maria Souza', $body );
		self::assertStringNotContainsString( '_wpnonce', $body );
		self::assertStringContainsString( esc_url( Routes::url( 'home' ) ), $body );
	}

	public function test_the_install_page_is_public_and_in_the_apps_look(): void {
		update_option( Settings::OPTION, array( 'enabled' => true, 'name' => 'Escola Viva', 'short_name' => 'Viva', 'theme_color' => '#841d8c', 'install_text' => 'Tudo da escola no celular.' ) );
		wp_set_current_user( 0 );

		$response = Routes::response( 'install' );
		$body     = $response['body'];

		self::assertSame( 200, $response['status'], 'no login: the visitor sees the app before signing in (#34)' );
		self::assertSame( 'no-cache', $response['headers']['Cache-Control'] );
		self::assertStringContainsString( '<title>' . "\n" . 'Install Escola Viva</title>', $body );
		self::assertStringContainsString( '<h1>Escola Viva</h1>', $body );
		self::assertStringContainsString( '<p>Tudo da escola no celular.</p>', $body );
		self::assertStringContainsString( '<link rel="manifest" href="' . esc_url( Routes::url( 'manifest' ) ) . '">', $body );
		self::assertStringContainsString( '<meta name="theme-color" content="#841d8c" media="(prefers-color-scheme: light)">', $body );
		self::assertStringContainsString( 'data-install-page data-home="' . esc_url( Routes::url( 'home' ) ) . '"', $body );
		foreach ( array( 'wait', 'prompt', 'ios-safari', 'ios-other', 'ios-old', 'installed', 'unsupported' ) as $state ) {
			self::assertMatchesRegularExpression( '#data-state="' . $state . '" hidden>#', $body, "every state starts hidden: {$state}" );
		}
		self::assertStringContainsString( 'value="' . esc_url( Routes::url( 'install' ) ) . '"', $body );
		self::assertMatchesRegularExpression( '#<script src="[^"]*assets/js/install(\.min)?\.js\?ver=[^"]*" defer></script>#', $body );
		self::assertStringNotContainsString( '_wpnonce', $body );
	}

	public function test_the_install_page_reads_the_same_for_a_signed_in_user(): void {
		$this->turnOn();
		wp_set_current_user( self::factory()->user->create( array( 'display_name' => 'Maria Souza' ) ) );

		$body = Routes::response( 'install' )['body'];

		self::assertStringNotContainsString( 'Maria Souza', $body, 'the page holds no personal data' );
		self::assertStringContainsString( 'Open it faster, straight from your home screen.', $body, 'an empty sentence reads the default' );
	}

	public function test_the_install_page_is_off_with_the_app(): void {
		self::assertNull( Routes::response( 'install' ) );
	}

	public function test_an_unknown_route_answers_nothing(): void {
		$this->turnOn();
		self::assertNull( Routes::response( 'wp-admin' ) );
	}

	public function test_the_account_route_has_its_rule(): void {
		Routes::addRules();
		global $wp_rewrite;
		self::assertSame( 'index.php?site_pwa_route=account', $wp_rewrite->extra_rules_top['^app/account/?$'] );
		self::assertSame( home_url( '/app/account/' ), Routes::url( 'account' ) );
	}

	public function test_the_rules_reach_each_route(): void {
		Routes::addRules();
		global $wp_rewrite;
		$rules = $wp_rewrite->extra_rules_top;

		self::assertSame( 'index.php?site_pwa_route=sw', $rules['^site\-pwa\-sw\.js$'] );
		self::assertSame( 'index.php?site_pwa_route=manifest', $rules['^app/manifest\.webmanifest$'] );
		self::assertSame( 'index.php?site_pwa_route=offline', $rules['^app/offline/?$'] );
		self::assertSame( 'index.php?site_pwa_route=install', $rules['^app/install/?$'] );
		self::assertSame( home_url( '/app/install/' ), Routes::url( 'install' ) );
		self::assertSame( 'index.php?site_pwa_route=home', $rules['^app/?$'] );
	}

	public function test_the_prefix_is_filterable_and_never_empty(): void {
		add_filter( 'site_pwa_app_prefix', static fn() => 'Meu App/' );
		self::assertSame( 'meuapp', Routes::prefix() );
		self::assertSame( home_url( '/meuapp/' ), Routes::url( 'home' ) );

		add_filter( 'site_pwa_app_prefix', static fn() => '//', 20 );
		self::assertSame( 'app', Routes::prefix() );
	}

	public function test_the_rules_flush_once_per_revision_and_prefix(): void {
		delete_option( Routes::REVISION_OPTION );
		Routes::maybeFlush();
		self::assertSame( Routes::REVISION . '|app', get_option( Routes::REVISION_OPTION ) );

		add_filter( 'site_pwa_app_prefix', static fn() => 'aplicativo' );
		Routes::maybeFlush();
		self::assertSame( Routes::REVISION . '|aplicativo', get_option( Routes::REVISION_OPTION ) );
	}

	public function test_the_query_var_is_public(): void {
		self::assertContains( Routes::QUERY_VAR, Routes::queryVars( array() ) );
	}

	public function test_a_request_that_is_not_the_apps_is_left_alone(): void {
		set_query_var( Routes::QUERY_VAR, '' );
		Routes::dispatch();
		self::assertFalse( is_404() );
	}

	public function test_an_app_page_while_the_app_is_off_is_a_404(): void {
		set_query_var( Routes::QUERY_VAR, 'home' );
		Routes::dispatch();
		self::assertTrue( is_404() );
	}

	public function test_the_handler_runs_before_canonical_redirects(): void {
		Routes::register();
		self::assertSame( 1, has_action( 'template_redirect', array( Routes::class, 'dispatch' ) ) );
		self::assertLessThan( has_action( 'template_redirect', 'redirect_canonical' ), 1 );
	}

	public function test_a_subdirectory_install_scopes_the_worker_to_its_path(): void {
		update_option( 'home', 'http://example.org/blog' );
		self::assertSame( '/blog/', Routes::scope() );
		self::assertSame( 'http://example.org/blog/site-pwa-sw.js', Routes::url( 'sw' ) );
		update_option( 'home', WP_TESTS_DOMAIN ? 'http://' . WP_TESTS_DOMAIN : '' );
	}
}
