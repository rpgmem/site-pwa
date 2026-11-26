<?php

declare(strict_types=1);

namespace SitePwa\Tests\Functional;

use SitePwa\App\AppPages;
use SitePwa\App\Content;
use SitePwa\App\Frame;
use SitePwa\App\Icons;
use SitePwa\App\Navigation;
use SitePwa\App\News;
use SitePwa\App\Routes;
use SitePwa\Settings;
use WP_UnitTestCase;

/**
 * The site's news and the chosen pages inside the app: the home screen's
 * cards, the news screen and its pages, More, and the two settings behind
 * them.
 *
 * @covers \SitePwa\App\News
 * @covers \SitePwa\App\AppPages
 * @covers \SitePwa\App\Frame
 * @covers \SitePwa\App\Routes
 * @covers \SitePwa\Settings
 */
final class NewsTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wp_styles']  = null;
		$GLOBALS['wp_scripts'] = null;
		$this->settings( array() );
	}

	protected function tearDown(): void {
		unset( $_GET['pg'], $_GET['topic'] );
		set_query_var( Routes::QUERY_VAR, '' );
		delete_option( Settings::OPTION );
		parent::tearDown();
	}

	/**
	 * @param array<string, mixed> $extra Settings beside the defaults.
	 */
	private function settings( array $extra ): void {
		update_option( Settings::OPTION, $extra + array( 'enabled' => true, 'name' => 'Escola Viva' ) );
	}

	private function signIn(): void {
		wp_set_current_user( self::factory()->user->create() );
	}

	/**
	 * @param array<string, mixed> $args Post arguments.
	 */
	private function post( array $args = array() ): int {
		static $day = 0;
		++$day;
		return self::factory()->post->create(
			$args + array(
				'post_status' => 'publish',
				'post_date'   => sprintf( '2026-01-%02d 10:00:00', $day ),
			)
		);
	}

	public function test_the_excerpt_strips_shortcodes_and_markup(): void {
		$this->post(
			array(
				'post_title'   => 'Meeting',
				'post_content' => '[gallery ids="1,2"] <strong>The meeting</strong> is on Friday.',
				'post_excerpt' => '',
			)
		);

		$item = News::page( 1 )['items'][0];

		self::assertSame( 'Meeting', $item['title'] );
		self::assertSame( 'The meeting is on Friday.', $item['excerpt'] );
		self::assertSame( Content::url( $item['id'] ), $item['url'] );
		self::assertSame( '', $item['image'] );
	}

	public function test_only_published_posts_newest_first_and_from_the_chosen_categories(): void {
		$events = self::factory()->category->create( array( 'name' => 'Events' ) );
		$this->post( array( 'post_title' => 'Old' ) );
		$this->post( array( 'post_title' => 'Event', 'post_category' => array( $events ) ) );
		$this->post( array( 'post_title' => 'New' ) );
		$this->post( array( 'post_title' => 'Draft', 'post_status' => 'draft' ) );

		self::assertSame( array( 'New', 'Event', 'Old' ), array_column( News::page( 1 )['items'], 'title' ) );

		$this->settings( array( 'news_categories' => array( $events ) ) );
		self::assertSame( array( 'Event' ), array_column( News::page( 1 )['items'], 'title' ) );
	}

	public function test_the_news_screen_pages_ten_at_a_time(): void {
		for ( $i = 1; $i <= 12; $i++ ) {
			$this->post( array( 'post_title' => 'Post ' . $i ) );
		}
		$this->signIn();
		set_query_var( Routes::QUERY_VAR, 'news' );

		$first = Frame::render( 'news' );
		self::assertSame( 10, substr_count( $first, 'class="site-pwa-panel site-pwa-story' ) );
		self::assertSame( 1, substr_count( $first, 'site-pwa-story-featured' ), 'the newest is the featured card' );
		self::assertStringContainsString( 'Page 1 of 2', $first );
		self::assertStringContainsString( 'rel="next" href="' . esc_url( News::url( 2 ) ) . '"', $first );
		self::assertStringNotContainsString( 'rel="prev"', $first );

		$_GET['pg'] = '2';
		$second     = Frame::render( 'news' );
		self::assertSame( 2, substr_count( $second, 'class="site-pwa-panel site-pwa-story' ) );
		self::assertStringNotContainsString( 'site-pwa-story-featured', $second, 'only page 1 features a story' );
		self::assertStringContainsString( 'Post 1', $second );
		self::assertStringContainsString( 'rel="prev" href="' . esc_url( Routes::url( 'news' ) ) . '"', $second );
		self::assertStringNotContainsString( 'rel="next"', $second );
		self::assertSame( Routes::url( 'news' ) . '?pg=2', News::url( 2 ) );
	}

	public function test_the_news_screen_is_a_signed_in_screen_under_more(): void {
		global $wp_rewrite;
		Routes::addRules();
		self::assertSame( 'index.php?site_pwa_route=news', $wp_rewrite->extra_rules_top['^app/news/?$'] );
		self::assertContains( 'news', Routes::SCREENS );
		self::assertSame( 'more', Navigation::key( 'news' ) );

		$response = Routes::response( 'news' );
		self::assertNotNull( $response );
		self::assertSame( 302, $response['status'] );

		$this->signIn();
		set_query_var( Routes::QUERY_VAR, 'news' );
		self::assertSame( 'News – Escola Viva', Frame::title( 'x' ) );
	}

	public function test_empty_states_without_news_or_pages(): void {
		$this->signIn();
		set_query_var( Routes::QUERY_VAR, 'home' );

		$home = Frame::render( 'home' );
		self::assertStringContainsString( 'No news yet.', $home );
		self::assertStringNotContainsString( 'See all news', $home );
		self::assertStringContainsString( 'No pages yet.', $home );

		set_query_var( Routes::QUERY_VAR, 'news' );
		$news = Frame::render( 'news' );
		self::assertStringContainsString( 'No news yet.', $news );
		self::assertStringNotContainsString( 'site-pwa-pager', $news );
	}

	public function test_home_shows_the_three_latest_and_the_chosen_pages(): void {
		for ( $i = 1; $i <= 4; $i++ ) {
			$this->post( array( 'post_title' => 'Post ' . $i ) );
		}
		$inside  = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Rules', 'menu_order' => 2 ) );
		$outside = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Shop', 'menu_order' => 1 ) );
		$draft   = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Hidden', 'post_status' => 'draft' ) );
		$this->settings( array( 'app_pages' => array( $inside => false, $outside => true, $draft => false ) ) );
		$this->signIn();
		set_query_var( Routes::QUERY_VAR, 'home' );

		$home = Frame::render( 'home' );

		self::assertStringContainsString( 'Post 4', $home );
		self::assertStringContainsString( 'Post 2', $home );
		self::assertStringNotContainsString( 'Post 1<', $home );
		self::assertStringContainsString( 'href="' . esc_url( News::url() ) . '">See all</a>', $home );
		self::assertSame( array( 'Shop', 'Rules' ), array_column( AppPages::items(), 'label' ) );
		self::assertStringContainsString( 'href="' . esc_url( Content::url( $inside ) ) . '"', $home );
		self::assertStringContainsString( 'href="' . esc_url( (string) get_permalink( $outside ) ) . '"', $home );
		self::assertStringNotContainsString( 'Hidden', $home );
	}

	public function test_more_lists_the_account_preferences_news_and_sign_out_but_not_the_pages(): void {
		$outside = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Shop' ) );
		$this->settings( array( 'app_pages' => array( $outside => true ) ) );
		$this->signIn();
		set_query_var( Routes::QUERY_VAR, 'more' );

		$more = Frame::render( 'more' );

		$account     = strpos( $more, esc_url( Routes::url( 'account' ) ) );
		$preferences = strpos( $more, esc_url( Routes::url( 'preferences' ) ) );
		$news        = strpos( $more, esc_url( Routes::url( 'news' ) ) );
		$out         = strpos( $more, 'site-pwa-signout' );
		self::assertNotFalse( $account );
		self::assertLessThan( $preferences, $account, 'the profile card comes first' );
		self::assertLessThan( $news, $preferences, 'Preferences sits after the screens, before News' );
		self::assertLessThan( $out, $news, 'sign out comes last' );
		self::assertStringContainsString( Icons::svg( 'sliders' ), $more );
		self::assertStringNotContainsString( esc_url( (string) get_permalink( $outside ) ), $more, 'the site pages are on Home, not here' );

		set_query_var( Routes::QUERY_VAR, 'home' );
		self::assertStringContainsString( esc_url( (string) get_permalink( $outside ) ), Frame::render( 'home' ) );
		set_query_var( Routes::QUERY_VAR, 'more' );
	}

	public function test_the_settings_keep_positive_ids_and_the_two_page_forms(): void {
		self::assertSame( array(), Settings::defaults()['news_categories'] );
		self::assertSame( array(), Settings::defaults()['app_pages'] );
		self::assertSame( array( 3, 7 ), Settings::sanitize( array( 'news_categories' => array( '3', 'x', '-2', '7', '3' ) ) )['news_categories'] );
		self::assertSame( array(), Settings::sanitize( array( 'news_categories' => 'all' ) )['news_categories'] );

		$posted = array(
			'4' => array( 'show' => '1' ),
			'5' => array( 'show' => '1', 'site' => '1' ),
			'6' => array( 'site' => '1' ),
			'x' => array( 'show' => '1' ),
			'-8' => array( 'show' => '1' ),
		);
		self::assertSame( array( 4 => false, 5 => true ), Settings::sanitize( array( 'app_pages' => $posted ) )['app_pages'] );
		self::assertSame( array( 4 => false, 5 => true ), Settings::sanitize( array( 'app_pages' => array( 4 => false, 5 => true ) ) )['app_pages'] );
	}

	public function test_the_news_screen_offers_its_categories_as_chips_and_filters_by_the_chosen_one(): void {
		$events = self::factory()->category->create( array( 'name' => 'Events', 'slug' => 'events' ) );
		$notice = self::factory()->category->create( array( 'name' => 'Notices', 'slug' => 'notices' ) );
		self::factory()->category->create( array( 'name' => 'Empty', 'slug' => 'empty' ) );
		$fair = $this->post( array( 'post_title' => 'Fair', 'post_category' => array( $events ) ) );
		$this->post( array( 'post_title' => 'Closed', 'post_category' => array( $notice ) ) );
		$this->signIn();
		set_query_var( Routes::QUERY_VAR, 'news' );

		self::assertSame( array( 'events', 'notices' ), array_column( News::categories(), 'slug' ), 'only categories with a post, by name' );
		$all = Frame::render( 'news' );
		self::assertStringContainsString( '<nav class="site-pwa-chips" aria-label="News categories">', $all );
		self::assertStringContainsString( 'href="' . esc_url( News::url() ) . '" aria-current="page">All</a>', $all );
		self::assertStringContainsString( '<span class="site-pwa-story-meta">' . get_the_date( '', $fair ) . ' · Events</span>', $all );

		$_GET['topic'] = 'events';
		self::assertSame( $events, News::requestedCategory()['id'] ?? null );
		$one = Frame::render( 'news' );
		self::assertStringContainsString( 'Fair', $one );
		self::assertStringNotContainsString( 'Closed', $one );
		self::assertStringContainsString( 'href="' . esc_url( News::url( 1, 'events' ) ) . '" aria-current="page">Events</a>', $one );
		self::assertSame( Routes::url( 'news' ) . '?topic=events&pg=2', News::url( 2, 'events' ), 'the pager keeps the category' );

		$_GET['topic'] = 'empty';
		self::assertNull( News::requestedCategory(), 'a category not offered is ignored' );
		unset( $_GET['topic'] );

		$this->settings( array( 'news_categories' => array( $events ) ) );
		self::assertSame( array(), News::categories(), 'one category leaves nothing to choose between' );
		self::assertStringNotContainsString( 'site-pwa-chips', Frame::render( 'news' ) );
	}
}
