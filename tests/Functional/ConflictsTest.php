<?php

declare(strict_types=1);

namespace SitePwa\Tests\Functional;

use SitePwa\Admin\SettingsPage;
use SitePwa\App\Conflicts;
use SitePwa\App\Routes;
use SitePwa\App\ServiceWorker;
use SitePwa\Settings;
use WP_UnitTestCase;

/**
 * Another plugin that registers a root service worker keeps the app off
 * until the replacement is confirmed, and a push service can share the
 * root by loading its script into the app's worker.
 *
 * @covers \SitePwa\App\Conflicts
 * @covers \SitePwa\App\ServiceWorker
 * @covers \SitePwa\Settings
 * @covers \SitePwa\Admin\SettingsPage
 */
final class ConflictsTest extends WP_UnitTestCase {

	protected function tearDown(): void {
		delete_option( Settings::OPTION );
		update_option( 'active_plugins', array() );
		remove_all_filters( 'site_pwa_sw_imports' );
		remove_all_filters( 'site_pwa_known_sw_plugins' );
		parent::tearDown();
	}

	private function withOneSignal(): void {
		update_option( 'active_plugins', array( 'onesignal-free-web-push-notifications/onesignal.php', 'hello.php' ) );
	}

	public function test_no_known_plugin_active_means_no_conflict(): void {
		update_option( 'active_plugins', array( 'hello.php' ) );
		self::assertSame( array(), Conflicts::active() );
	}

	public function test_an_active_known_plugin_is_named(): void {
		$this->withOneSignal();
		self::assertSame( array( 'onesignal-free-web-push-notifications/onesignal.php' => 'OneSignal' ), Conflicts::active() );
	}

	public function test_the_known_list_is_filterable(): void {
		update_option( 'active_plugins', array( 'my-push/my-push.php' ) );
		add_filter( 'site_pwa_known_sw_plugins', static fn( array $known ): array => $known + array( 'my-push/my-push.php' => 'My Push' ) );
		self::assertSame( array( 'my-push/my-push.php' => 'My Push' ), Conflicts::active() );
	}

	public function test_a_conflict_keeps_the_app_off_until_the_replacement_is_confirmed(): void {
		$this->withOneSignal();
		update_option( Settings::OPTION, array( 'enabled' => true ) );

		self::assertFalse( Settings::enabled() );
		self::assertNull( Routes::response( 'manifest' ) );
		self::assertStringContainsString( 'registration.unregister', Routes::response( 'sw' )['body'], 'Off by conflict serves the worker that removes itself.' );

		update_option( Settings::OPTION, array( 'enabled' => true, 'replace_confirmed' => true ) );
		self::assertTrue( Settings::enabled() );
		self::assertNotNull( Routes::response( 'manifest' ) );
	}

	public function test_confirming_alone_does_not_turn_the_app_on(): void {
		update_option( Settings::OPTION, array( 'replace_confirmed' => true ) );
		self::assertFalse( Settings::enabled() );
	}

	public function test_only_same_site_imports_reach_the_worker(): void {
		add_filter(
			'site_pwa_sw_imports',
			static fn(): array => array(
				home_url( '/wp-content/plugins/push/sw.js' ),
				'https://evil.example.com/sw.js',
				'javascript:alert(1)',
				home_url( '/wp-content/plugins/push/sw.js' ),
			)
		);

		self::assertSame( array( home_url( '/wp-content/plugins/push/sw.js' ) ), Conflicts::imports() );
	}

	public function test_the_worker_loads_the_imports_before_its_own_code(): void {
		update_option( Settings::OPTION, array( 'enabled' => true ) );
		add_filter( 'site_pwa_sw_imports', static fn(): array => array( home_url( '/push-sw.js' ) ) );

		$script = ServiceWorker::script();

		self::assertStringStartsWith( 'importScripts("' . home_url( '/push-sw.js' ) . '");' . "\n" . 'self.SITE_PWA_CONFIG', $script );
	}

	public function test_without_imports_the_worker_starts_with_its_config(): void {
		self::assertStringStartsWith( 'self.SITE_PWA_CONFIG', ServiceWorker::script() );
	}

	public function test_the_screen_warns_and_says_the_app_stays_off(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->withOneSignal();
		update_option( Settings::OPTION, array( 'enabled' => true ) );

		$html = $this->render();

		self::assertStringContainsString( 'OneSignal', $html );
		self::assertStringContainsString( 'notice-warning', $html );
		self::assertStringContainsString( 'The app stays off until you confirm the replacement', $html );
		self::assertStringNotContainsString( 'The app is on at', $html );
	}

	public function test_the_screen_says_when_the_replacement_was_confirmed(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->withOneSignal();
		update_option( Settings::OPTION, array( 'enabled' => true, 'replace_confirmed' => true ) );

		$html = $this->render();

		self::assertStringContainsString( 'Replacement confirmed', $html );
		self::assertStringContainsString( 'The app is on at', $html );
	}

	public function test_the_browser_check_names_the_apps_own_worker(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		self::assertStringContainsString( 'data-own="' . esc_url( Routes::url( 'sw' ) ) . '"', $this->render() );
	}

	public function test_the_browser_check_loads_on_this_screen_only(): void {
		SettingsPage::enqueue( 'options-general.php' );
		self::assertFalse( wp_script_is( 'site-pwa-admin-conflicts', 'enqueued' ) );

		SettingsPage::enqueue( 'settings_page_site-pwa' );
		self::assertTrue( wp_script_is( 'site-pwa-admin-conflicts', 'enqueued' ) );
	}

	private function render(): string {
		ob_start();
		SettingsPage::render();
		return (string) ob_get_clean();
	}
}
