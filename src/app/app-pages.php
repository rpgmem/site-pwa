<?php
/**
 * The site's pages the administrator put in the app.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\App;

use SitePwa\Settings;

/**
 * The pages chosen under Settings → Site PWA, in the order of their menu
 * order and title. A page marked "open on the site" links to its permalink
 * and carries the external-link icon; the others open inside the app.
 *
 * @phpstan-type Item array{id: int, label: string, url: string, external: bool}
 */
final class AppPages {

	/**
	 * The chosen pages that are still published, in order.
	 *
	 * @return list<Item>
	 */
	public static function items(): array {
		$chosen = Settings::get()['app_pages'];
		if ( array() === $chosen ) {
			return array();
		}
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post__in'       => array_keys( $chosen ),
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'posts_per_page' => -1,
			)
		);

		$items = array();
		foreach ( $pages as $page ) {
			$external = $chosen[ $page->ID ] ?? false;
			$items[]  = array(
				'id'       => $page->ID,
				'label'    => wp_strip_all_tags( get_the_title( $page ) ),
				'url'      => $external ? (string) get_permalink( $page ) : Content::url( $page->ID ),
				'external' => $external,
			);
		}
		return $items;
	}
}
