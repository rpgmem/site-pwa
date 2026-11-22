<?php
/**
 * The site's news inside the app.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\App;

use SitePwa\Settings;
use WP_Query;

/**
 * Lists the site's posts, newest first, from the categories the
 * administrator chose (every category when none is chosen). Built on the
 * server like the rest of the app; a post opens inside the app at
 * `/app/?p=ID` (see Content).
 *
 * @phpstan-type Item array{id: int, title: string, excerpt: string, date: string, image: string, url: string}
 */
final class News {

	/**
	 * Posts per page on the news screen.
	 */
	public const PER_PAGE = 10;

	/**
	 * Posts on the home screen's card.
	 */
	public const LATEST = 3;

	/**
	 * A page of news.
	 *
	 * @param int $page  Page number, from 1.
	 * @param int $count Posts per page.
	 * @return array{items: list<Item>, pages: int}
	 */
	public static function page( int $page, int $count = self::PER_PAGE ): array {
		$args       = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, $count ),
			'paged'               => max( 1, $page ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
		);
		$categories = Settings::get()['news_categories'];
		if ( array() !== $categories ) {
			$args['category__in'] = $categories;
		}
		$query = new WP_Query( $args );

		$items = array();
		foreach ( (array) $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$image   = get_the_post_thumbnail_url( $post, 'medium_large' );
			$items[] = array(
				'id'      => $post->ID,
				'title'   => wp_strip_all_tags( get_the_title( $post ) ),
				// get_the_excerpt() runs wp_trim_excerpt(), which strips
				// shortcodes and blocks before trimming the text.
				'excerpt' => wp_strip_all_tags( get_the_excerpt( $post ) ),
				'date'    => (string) get_the_date( '', $post ),
				'image'   => false === $image ? '' : (string) $image,
				'url'     => Content::url( $post->ID ),
			);
		}

		return array(
			'items' => $items,
			'pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * The URL of a page of the news screen.
	 *
	 * @param int $page Page number, from 1.
	 */
	public static function url( int $page = 1 ): string {
		$url = Routes::url( 'news' );
		return $page > 1 ? add_query_arg( 'pg', $page, $url ) : $url;
	}

	/**
	 * The page number the request asked for.
	 */
	public static function requested(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a page number for a read-only list.
		return isset( $_GET['pg'] ) ? max( 1, absint( wp_unslash( $_GET['pg'] ) ) ) : 1;
	}
}
