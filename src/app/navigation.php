<?php
/**
 * The app's bottom navigation.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\App;

/**
 * The items of the bottom navigation, in order.
 *
 * The built-in items are the home screen and the account. The screen
 * registration contract (rpgmem/site-pwa#4) adds other plugins' screens
 * between the two; until then this list is the only source.
 *
 * @phpstan-type Item array{id: string, label: string, url: string, icon: string, current: bool}
 */
final class Navigation {

	/**
	 * The items, the current one marked.
	 *
	 * @param string $current The route being drawn.
	 * @return list<Item>
	 */
	public static function items( string $current ): array {
		$items = array(
			array(
				'id'    => 'home',
				'label' => __( 'Home', 'site-pwa' ),
				'url'   => Routes::url( 'home' ),
				'icon'  => 'home',
			),
			array(
				'id'    => 'account',
				'label' => __( 'Account', 'site-pwa' ),
				'url'   => Routes::url( 'account' ),
				'icon'  => 'user',
			),
		);

		return array_map(
			static fn( array $item ): array => $item + array( 'current' => $item['id'] === $current ),
			$items
		);
	}

	/**
	 * The label of a route's item, for the document title.
	 *
	 * @param string $route A screen route.
	 */
	public static function label( string $route ): string {
		foreach ( self::items( $route ) as $item ) {
			if ( $item['current'] ) {
				return $item['label'];
			}
		}
		return '';
	}
}
