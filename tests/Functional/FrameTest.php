<?php

declare(strict_types=1);

namespace SitePwa\Tests\Functional;

use SitePwa\App\Frame;
use SitePwa\App\Icons;
use SitePwa\App\Login;
use SitePwa\App\Navigation;
use SitePwa\App\Routes;
use SitePwa\Settings;
use WP_UnitTestCase;

/**
 * The app's frame: its document, its navigation, the theme kept out, and
 * the login dressed as the app.
 *
 * @covers \SitePwa\App\Frame
 * @covers \SitePwa\App\Navigation
 * @covers \SitePwa\App\Icons
 * @covers \SitePwa\App\Login
 */
final class FrameTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Fresh registries: an earlier test's printed or queued assets would
		// otherwise hide what this one enqueues.
		$GLOBALS['wp_styles']  = null;
		$GLOBALS['wp_scripts'] = null;
		update_option( Settings::OPTION, array( 'enabled' => true, 'name' => 'Escola Viva' ) );
		wp_set_current_user( self::factory()->user->create( array( 'first_name' => 'Maria', 'display_name' => 'Maria Souza', 'user_email' => 'maria@example.test' ) ) );
	}

	protected function tearDown(): void {
		set_query_var( Routes::QUERY_VAR, '' );
		delete_option( Settings::OPTION );
		unset( $_REQUEST['redirect_to'] );
		parent::tearDown();
	}

	private function screen( string $route ): string {
		set_query_var( Routes::QUERY_VAR, $route );
		return Frame::render( $route );
	}

	public function test_the_frame_has_landmarks_a_skip_link_and_the_current_item_marked(): void {
		$html = $this->screen( 'account' );

		self::assertMatchesRegularExpression( '/<a class="site-pwa-skip" href="#site-pwa-main">/', $html );
		self::assertStringContainsString( '<header class="site-pwa-header">', $html );
		self::assertStringContainsString( '<main id="site-pwa-main" class="site-pwa-main" tabindex="-1">', $html );
		self::assertStringContainsString( '<nav class="site-pwa-nav" aria-label="App">', $html );
		self::assertSame( 1, substr_count( $html, 'aria-current="page"' ) );
		self::assertMatchesRegularExpression( '#href="' . preg_quote( Routes::url( 'more' ), '#' ) . '" aria-current="page"#', $html );
	}

	public function test_the_skip_link_comes_before_the_header_and_the_navigation_after_the_content(): void {
		$html = $this->screen( 'home' );
		$skip = strpos( $html, 'site-pwa-skip' );
		$head = strpos( $html, '<header' );
		$main = strpos( $html, '<main' );
		$nav  = strpos( $html, '<nav' );

		self::assertTrue( $skip < $head && $head < $main && $main < $nav, 'Focus order: skip link, header, content, navigation.' );
	}

	public function test_the_home_screen_opens_on_the_accent_band_with_a_greeting_and_the_name(): void {
		$html = $this->screen( 'home' );

		self::assertStringContainsString( '<header class="site-pwa-header site-pwa-header-home">', $html );
		self::assertMatchesRegularExpression( '#<p class="site-pwa-hello">\s*<span>Good (morning|afternoon|evening),</span>\s*<strong>Maria Souza</strong>#', $html );
		self::assertStringContainsString( '<a class="site-pwa-bell" href="#site-pwa-pending-heading">', $html, 'the bell leads to Pending even with nothing pending' );
		self::assertStringContainsString( 'Notifications: nothing pending', $html );
		self::assertStringNotContainsString( 'site-pwa-badge', $html, 'no badge without anything pending' );
		// Attribute order differs between WordPress releases (src first on 6.8).
		self::assertMatchesRegularExpression( '#<script(?=[^>]*\sid="site-pwa-home-js")(?=[^>]*\ssrc="[^"]*assets/js/home(\.min)?\.js)[^>]*>#', $html, 'the banner script loads on home' );
	}

	public function test_the_greeting_follows_the_hour(): void {
		self::assertSame( 'Good morning,', \SitePwa\App\Home::greeting( 5 ) );
		self::assertSame( 'Good morning,', \SitePwa\App\Home::greeting( 11 ) );
		self::assertSame( 'Good afternoon,', \SitePwa\App\Home::greeting( 12 ) );
		self::assertSame( 'Good afternoon,', \SitePwa\App\Home::greeting( 17 ) );
		self::assertSame( 'Good evening,', \SitePwa\App\Home::greeting( 18 ) );
		self::assertSame( 'Good evening,', \SitePwa\App\Home::greeting( 2 ) );
	}

	public function test_an_inner_screen_has_a_back_arrow_only_where_it_was_reached_from_another(): void {
		$_SERVER['HTTP_REFERER'] = Routes::url( 'more' );
		$account = $this->screen( 'account' );
		self::assertStringContainsString( '<a class="site-pwa-back" href="' . esc_url( Routes::url( 'more' ) ) . '">', $account );

		$more = $this->screen( 'more' );
		self::assertStringNotContainsString( 'site-pwa-back', $more, 'More is in the bar' );
		self::assertStringNotContainsString( '<header', $more );
		self::assertSame( '', Frame::back( 'more' ) );
		self::assertSame( '', Frame::back( 'home' ) );
		self::assertSame( '', Frame::back( 'screen' ) );
		unset( $_SERVER['HTTP_REFERER'] );
	}

	public function test_the_back_arrow_returns_to_the_app_screen_the_person_came_from(): void {
		$from = add_query_arg( 'topic', 'eventos', \SitePwa\App\News::url() );
		$_SERVER['HTTP_REFERER'] = $from;
		self::assertSame( $from, Frame::back( 'post' ), 'News with its category' );

		$_SERVER['HTTP_REFERER'] = home_url( '/app/s/agenda/' );
		self::assertSame( home_url( '/app/s/agenda/' ), Frame::back( 'post' ), 'a screen another plugin registered' );
		unset( $_SERVER['HTTP_REFERER'] );
	}

	public function test_without_an_app_screen_behind_it_the_back_arrow_leads_home(): void {
		$home = Routes::url( 'home' );
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		foreach ( array(
			'none'          => null,
			'another site'  => 'https://other.test/app/',
			'a site page'   => (string) get_permalink( $page ),
			'the login'     => wp_login_url(),
			'not a URL'     => 'http://',
		) as $case => $referer ) {
			if ( null === $referer ) {
				unset( $_SERVER['HTTP_REFERER'] );
			} else {
				$_SERVER['HTTP_REFERER'] = $referer;
			}
			self::assertSame( $home, Frame::back( 'post' ), $case );
			self::assertSame( $home, Frame::back( 'account' ), $case );
		}

		// The page itself (a reload keeps its own address as the referrer).
		$this->go_to( add_query_arg( 'p', 7, Routes::url( 'home' ) ) );
		$_SERVER['HTTP_REFERER'] = add_query_arg( 'p', 7, Routes::url( 'home' ) );
		self::assertSame( $home, Frame::back( 'post' ), 'itself' );
		unset( $_SERVER['HTTP_REFERER'] );
	}

	/**
	 * Preferences is a screen of its own, reached from More: it has a back
	 * arrow, its own title, More lit in the bar, and its own address.
	 */
	public function test_preferences_is_a_screen_reached_from_more(): void {
		self::assertSame( home_url( '/app/preferences/' ), Routes::url( 'preferences' ) );
		self::assertSame( 'more', \SitePwa\App\Navigation::key( 'preferences' ) );
		self::assertNotSame( '', Frame::back( 'preferences' ) );
		self::assertContains( 'preferences', \SitePwa\App\Screens::RESERVED, 'no plugin screen can take its id' );

		$html = $this->screen( 'preferences' );
		self::assertStringContainsString( '<h1 class="site-pwa-title">Preferences</h1>', $html );
		self::assertStringContainsString( '<title>Preferences', $html );
		self::assertStringContainsString( 'data-site-pwa-theme', $html );
	}

	/**
	 * Another plugin can add a section to Preferences; one without a label,
	 * a callable or a slug id, or that draws nothing, is dropped.
	 */
	public function test_plugins_add_sections_to_preferences(): void {
		add_filter(
			'site_pwa_preference_sections',
			static function ( array $sections ): array {
				$sections['messages']  = array(
					'label'  => 'Messages',
					'render' => static function (): void {
						echo '<p class="probe">E-mail toggles</p>';
					},
				);
				$sections['returned']  = array(
					'label'  => 'Returned',
					'render' => static fn(): string => '<p>returned</p>',
				);
				$sections['no-label']  = array( 'render' => static fn(): string => 'x' );
				$sections['no-render'] = array( 'label' => 'Nothing' );
				$sections['empty']     = array(
					'label'  => 'Empty',
					'render' => static fn(): string => '  ',
				);
				$sections['Bad Id']    = array(
					'label'  => 'Bad',
					'render' => static fn(): string => 'x',
				);
				return $sections;
			}
		);

		$user     = wp_get_current_user();
		$sections = Frame::preferenceSections( $user );
		self::assertSame( array( 'messages', 'returned' ), array_column( $sections, 'id' ) );

		$html = $this->screen( 'preferences' );
		self::assertStringContainsString( '<h2 id="site-pwa-pref-messages">Messages</h2>', $html );
		self::assertStringContainsString( '<p class="probe">E-mail toggles</p>', $html );
		self::assertLessThan( strpos( $html, 'probe' ), strpos( $html, 'data-site-pwa-theme' ), 'the app\'s own preferences come first' );
		self::assertStringNotContainsString( 'probe', $this->screen( 'more' ), 'only Preferences draws them' );

		remove_all_filters( 'site_pwa_preference_sections' );
		add_filter( 'site_pwa_preference_sections', '__return_false' );
		self::assertSame( array(), Frame::preferenceSections( $user ) );
		remove_all_filters( 'site_pwa_preference_sections' );
	}

	public function test_the_account_screen_shows_the_users_own_details_and_the_way_out(): void {
		$html = $this->screen( 'account' );

		self::assertStringContainsString( 'Maria Souza', $html );
		self::assertStringContainsString( 'maria@example.test', $html );
		self::assertStringContainsString( esc_url( get_edit_profile_url( get_current_user_id() ) ), $html );
		self::assertStringContainsString( 'action=logout', $html );
	}

	public function test_the_frame_prints_wp_head_with_the_manifest_and_wp_footer_with_the_worker_registration(): void {
		$html = $this->screen( 'home' );

		self::assertStringContainsString( '<link rel="manifest" href="' . esc_url( Routes::url( 'manifest' ) ) . '">', $html );
		self::assertStringContainsString( '<meta name="theme-color" content="#1d5e8c" media="(prefers-color-scheme: light)">', $html );
		self::assertStringContainsString( "noindex", $html );
		self::assertMatchesRegularExpression( '#<script data-sw="' . preg_quote( esc_url( Routes::url( 'sw' ) ), '#' ) . '" data-scope="/"[^>]*register#', $html );
		self::assertStringContainsString( 'site-pwa-app-css', $html );
	}

	public function test_the_frame_prints_its_own_title_once_whatever_the_theme_declares(): void {
		remove_theme_support( 'title-tag' );
		$html = $this->screen( 'account' );
		self::assertSame( 1, substr_count( $html, '<title>' ) );
		self::assertStringContainsString( '<title>Account – Escola Viva</title>', $html );

		// Set directly: add_theme_support() refuses `title-tag` after wp_loaded.
		$GLOBALS['_wp_theme_features']['title-tag'] = true;
		self::assertSame( 1, substr_count( $this->screen( 'account' ), '<title>' ) );
		unset( $GLOBALS['_wp_theme_features']['title-tag'] );
	}

	public function test_the_themes_assets_are_taken_out_and_other_plugins_stay(): void {
		set_query_var( Routes::QUERY_VAR, 'home' );
		wp_enqueue_style( 'theme-style', get_stylesheet_directory_uri() . '/style.css', array(), '1' );
		wp_enqueue_script( 'theme-script', get_template_directory_uri() . '/app.js', array(), '1', true );
		wp_enqueue_style( 'other-plugin', plugins_url( 'other/x.css' ), array(), '1' );
		wp_enqueue_style( 'global-styles', false, array(), '1' );

		Frame::isolate();

		self::assertFalse( wp_style_is( 'theme-style', 'enqueued' ) );
		self::assertFalse( wp_script_is( 'theme-script', 'enqueued' ) );
		self::assertFalse( wp_style_is( 'global-styles', 'enqueued' ) );
		self::assertTrue( wp_style_is( 'other-plugin', 'enqueued' ) );
	}

	public function test_outside_the_app_nothing_is_touched(): void {
		wp_enqueue_style( 'theme-style', get_stylesheet_directory_uri() . '/style.css', array(), '1' );

		Frame::isolate();
		Frame::enqueue();
		ob_start();
		Frame::head();

		self::assertSame( '', ob_get_clean() );
		self::assertTrue( wp_style_is( 'theme-style', 'enqueued' ) );
		self::assertFalse( wp_style_is( Frame::HANDLE, 'enqueued' ) );
		self::assertTrue( Frame::adminBar( true ) );
		self::assertSame( 'Title', Frame::title( 'Title' ) );
		self::assertSame( array(), Frame::robots( array() ) );
		self::assertSame( '<script src="x">', Frame::scriptTag( '<script src="x">', 'other' ) );
	}

	public function test_inside_the_app_there_is_no_admin_bar_and_the_title_names_the_screen(): void {
		set_query_var( Routes::QUERY_VAR, 'account' );

		self::assertFalse( Frame::adminBar( true ) );
		self::assertSame( 'Account – Escola Viva', Frame::title( '' ) );
	}

	public function test_without_screens_the_navigation_is_home_then_more_with_one_current(): void {
		$items = Navigation::items( 'home' );

		self::assertSame( array( 'home', 'more' ), array_column( $items, 'id' ) );
		self::assertSame( array( true, false ), array_column( $items, 'current' ) );
		self::assertSame( 'more', Navigation::key( 'account' ) );
		self::assertSame( '', Navigation::key( 'post' ) );
	}

	public function test_icons_are_a_closed_set_hidden_from_assistive_technology(): void {
		self::assertStringContainsString( 'aria-hidden="true"', Icons::svg( 'home' ) );
		self::assertSame( '', Icons::svg( '<script>' ) );
	}

	public function test_the_login_styles_load_only_while_the_app_is_on(): void {
		Login::enqueue();
		self::assertTrue( wp_style_is( Login::HANDLE, 'enqueued' ) );
		self::assertStringContainsString( '--site-pwa-logo:url(', implode( '', wp_styles()->get_data( Login::HANDLE, 'after' ) ) );

		wp_dequeue_style( Login::HANDLE );
		update_option( Settings::OPTION, array( 'enabled' => false ) );
		Login::enqueue();
		self::assertFalse( wp_style_is( Login::HANDLE, 'enqueued' ) );
	}

	public function test_the_login_logo_points_back_to_the_app_only_when_reached_from_it(): void {
		self::assertSame( 'https://wordpress.org/', Login::headerUrl( 'https://wordpress.org/' ) );
		self::assertSame( 'Powered by WordPress', Login::headerText( 'Powered by WordPress' ) );

		$_REQUEST['redirect_to'] = Routes::url( 'account' );

		self::assertSame( Routes::url( 'home' ), Login::headerUrl( 'https://wordpress.org/' ) );
		self::assertSame( 'Escola Viva', Login::headerText( 'Powered by WordPress' ) );
	}

	public function test_every_login_rule_applies_only_inside_the_installed_app(): void {
		$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/login.css' );
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );

		self::assertSame( 1, preg_match( '/^\s*@media \(display-mode: standalone\) \{(.*)\}\s*$/s', $css ), 'login.css holds one standalone media block and nothing outside it.' );
	}

	public function test_the_login_from_the_app_names_the_app_moves_the_lost_link_and_says_what_remember_does(): void {
		update_option( 'blogdescription', 'Learn & grow' );
		self::assertSame( 'core', Login::message( 'core' ), 'a login not from the app is left alone' );
		ob_start();
		Login::lostPasswordLink();
		self::assertSame( '', ob_get_clean() );

		$_REQUEST['redirect_to'] = Routes::url( 'home' );

		$message = Login::message( '<p class="message">core</p>' );
		self::assertStringStartsWith( '<div class="site-pwa-login-heading" hidden><p class="site-pwa-login-name">Escola Viva</p><p class="site-pwa-login-tagline">Learn &amp; grow</p></div>', $message );
		self::assertStringEndsWith( '<p class="message">core</p>', $message, 'what another plugin prints there is kept' );

		update_option( 'blogdescription', '' );
		self::assertStringNotContainsString( 'site-pwa-login-tagline', Login::message( '' ) );

		ob_start();
		Login::lostPasswordLink();
		$lost = (string) ob_get_clean();
		self::assertStringContainsString( '<p class="site-pwa-login-lost" hidden><a href="' . esc_url( wp_lostpassword_url( Routes::url( 'home' ) ) ) . '">Forgot your password?</a></p>', $lost );

		self::assertFalse( has_filter( 'gettext', array( Login::class, 'rememberLabel' ) ), 'outside the login screen nothing is relabelled' );
		Login::relabel();
		self::assertSame( 10, has_filter( 'gettext', array( Login::class, 'rememberLabel' ) ) );
		self::assertSame( 'Keep me signed in on this device', Login::rememberLabel( 'Remember Me', 'Remember Me', 'default' ) );
		self::assertSame( 'Remember Me', Login::rememberLabel( 'Remember Me', 'Remember Me', 'other-plugin' ) );
		self::assertSame( 'Log In', Login::rememberLabel( 'Log In', 'Log In', 'default' ) );

		unset( $_REQUEST['redirect_to'] );
		self::assertSame( 'Remember Me', Login::rememberLabel( 'Remember Me', 'Remember Me', 'default' ) );
	}

	public function test_the_dark_theme_this_device_chose_is_applied_in_the_head_before_the_first_paint(): void {
		$html = $this->screen( 'home' );
		$head = substr( $html, 0, (int) strpos( $html, '</head>' ) );
		self::assertStringContainsString( Frame::darkScript(), $head );
		self::assertStringContainsString( "'" . Frame::DARK_KEY . "'", Frame::darkScript(), 'the head script reads the key More writes' );
		self::assertStringContainsString( "'" . Frame::DARK_KEY . "'", (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/more.js' ) );
		self::assertFalse( wp_script_is( Frame::MORE_HANDLE, 'enqueued' ), 'only Preferences loads the choice' );

		$more = $this->screen( 'preferences' );
		self::assertTrue( wp_script_is( Frame::MORE_HANDLE, 'enqueued' ) );
		self::assertStringContainsString( '<fieldset class="site-pwa-theme" data-site-pwa-theme data-site-pwa-theme-default="' . \SitePwa\App\Scheme::current() . '">', $more );
		foreach ( array( 'auto', 'dark', 'light' ) as $choice ) {
			self::assertStringContainsString( '<input type="radio" name="site-pwa-theme" value="' . $choice . '">', $more );
		}
		self::assertStringContainsString( "'" . Frame::SCHEME_KEY . "'", Frame::darkScript(), 'the head script reads the key More writes' );
		self::assertStringContainsString( "'" . Frame::SCHEME_KEY . "'", (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/more.js' ) );
		self::assertStringContainsString( "'" . Frame::DARK_BAR . "'", Frame::darkScript(), 'the head script turns the phone bars dark too' );
	}

	public function test_the_dark_root_classes_come_from_the_filter_and_only_plain_class_names_pass(): void {
		self::assertSame( array(), Frame::darkRootClasses(), 'no plugin is named by default' );
		self::assertStringContainsString( '[].forEach', Frame::darkScript() );

		add_filter(
			'site_pwa_dark_root_classes',
			static fn( array $classes ): array => array_merge( $classes, array( 'ffc-dark-mode', 'ffc-dark-mode', 'x"};alert(1);//', 42, '' ) )
		);
		self::assertSame( array( 'ffc-dark-mode' ), Frame::darkRootClasses() );
		self::assertStringContainsString( '["ffc-dark-mode"].forEach', Frame::darkScript() );

		remove_all_filters( 'site_pwa_dark_root_classes' );
		add_filter( 'site_pwa_dark_root_classes', '__return_false' );
		self::assertSame( array(), Frame::darkRootClasses() );
		remove_all_filters( 'site_pwa_dark_root_classes' );
	}

	public function test_more_opens_with_the_person_their_initials_and_a_line_other_plugins_supply(): void {
		$more = $this->screen( 'more' );
		self::assertStringContainsString( '<span class="site-pwa-avatar" aria-hidden="true">MS</span>', $more );
		self::assertStringContainsString( '<strong>Maria Souza</strong>', $more );
		self::assertStringNotContainsString( 'site-pwa-profile-text"><strong>Maria Souza</strong>' . "\n\t\t\t\t<span>", $more );

		add_filter( 'site_pwa_profile_line', static fn( string $line, \WP_User $user ): string => '<i>RF</i> ***.**1-0 · ' . $user->first_name, 10, 2 );
		self::assertStringContainsString( '<span>RF ***.**1-0 · Maria</span>', $this->screen( 'more' ) );

		self::assertSame( 'MS', Frame::initials( 'maria da silva souza' ) );
		self::assertSame( 'Á', Frame::initials( '  ágata ' ) );
		self::assertSame( 'ÉO', Frame::initials( 'élio otávio' ) );
		self::assertSame( '', Frame::initials( '   ' ) );

		$login = self::factory()->user->create( array( 'user_login' => 'jlima', 'display_name' => '' ) );
		self::assertSame( 'jlima', Frame::profile( new \WP_User( $login ) )['name'], 'a user with no display name shows their login' );
	}
}
