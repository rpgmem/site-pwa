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
 * @phpstan-type Item array{id: int, title: string, excerpt: string, date: string, category: string, image: string, url: string}
 * @phpstan-type Category array{id: int, name: string, slug: string}
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
	 * @param int $page     Page number, from 1.
	 * @param int $count    Posts per page.
	 * @param int $category Only this category, or 0 for every news category.
	 * @return array{items: list<Item>, pages: int}
	 */
	public static function page( int $page, int $count = self::PER_PAGE, int $category = 0 ): array {
		$args       = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, $count ),
			'paged'               => max( 1, $page ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
		);
		$categories = Settings::get()['news_categories'];
		if ( $category > 0 ) {
			$args['cat'] = $category;
		} elseif ( array() !== $categories ) {
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
				'id'       => $post->ID,
				'title'    => wp_strip_all_tags( get_the_title( $post ) ),
				// get_the_excerpt() runs wp_trim_excerpt(), which strips
				// shortcodes and blocks before trimming the text.
				'excerpt'  => wp_strip_all_tags( get_the_excerpt( $post ) ),
				'date'     => (string) get_the_date( '', $post ),
				'category' => self::categoryName( $post->ID ),
				'image'    => false === $image ? '' : (string) $image,
				'url'      => Content::url( $post->ID ),
			);
		}

		return array(
			'items' => $items,
			'pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * The categories the news screen offers as chips: the ones chosen in
	 * the settings, or every category with a published post when none was
	 * chosen. Fewer than two leave nothing to choose between, so none.
	 *
	 * @return list<Category>
	 */
	public static function categories(): array {
		$chosen = Settings::get()['news_categories'];
		$terms  = get_categories(
			array(
				'hide_empty' => true,
				'include'    => $chosen,
				'orderby'    => 'name',
			)
		);
		$list   = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term ) {
				$list[] = array(
					'id'   => (int) $term->term_id,
					'name' => wp_strip_all_tags( $term->name ),
					'slug' => (string) $term->slug,
				);
			}
		}
		return count( $list ) > 1 ? $list : array();
	}

	/**
	 * The category the request asked for, among the offered ones, or null.
	 *
	 * @return Category|null
	 */
	public static function requestedCategory(): ?array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a category slug for a read-only list.
		$slug = isset( $_GET['topic'] ) ? sanitize_title( wp_unslash( (string) $_GET['topic'] ) ) : '';
		if ( '' === $slug ) {
			return null;
		}
		foreach ( self::categories() as $category ) {
			if ( $category['slug'] === $slug ) {
				return $category;
			}
		}
		return null;
	}

	/**
	 * The name of a post's first category, for the line under its title.
	 *
	 * @param int $post_id Post.
	 */
	private static function categoryName( int $post_id ): string {
		$terms = get_the_category( $post_id );
		return isset( $terms[0] ) ? wp_strip_all_tags( $terms[0]->name ) : '';
	}

	/**
	 * The URL of a page of the news screen, in a category or all of them.
	 *
	 * @param int    $page     Page number, from 1.
	 * @param string $category A category slug, or '' for all.
	 */
	public static function url( int $page = 1, string $category = '' ): string {
		$url = Routes::url( 'news' );
		if ( '' !== $category ) {
			$url = add_query_arg( 'topic', $category, $url );
		}
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
