<?php
/**
 * The app's bottom navigation.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\App;

/**
 * The items of the bottom navigation, in order: Home, up to
 * Screens::NAV_SLOTS registered `nav` screens, and More. More lists the
 * `more` screens, the `nav` screens that did not fit, and the account, so
 * the bar holds at most five items whatever is registered.
 *
 * @phpstan-type Item array{id: string, label: string, url: string, icon: string, current: bool}
 */
final class Navigation {

	/**
	 * The items, the current one marked.
	 *
	 * @param string $current The current item's key (see key()).
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
		);
		foreach ( Screens::split()['nav'] as $screen ) {
			$items[] = array(
				'id'    => 'screen:' . $screen['id'],
				'label' => $screen['label'],
				'url'   => Screens::link( $screen ),
				'icon'  => $screen['icon'],
			);
		}
		$items[] = array(
			'id'    => 'more',
			'label' => __( 'More', 'site-pwa' ),
			'url'   => Routes::url( 'more' ),
			'icon'  => 'more',
		);

		return array_map(
			static fn( array $item ): array => $item + array( 'current' => $item['id'] === $current ),
			$items
		);
	}

	/**
	 * The navigation key a route marks as current: a screen in the bar marks
	 * itself, and everything reached through More marks More.
	 *
	 * @param string $route  The route being drawn.
	 * @param string $screen The screen id, for the `screen` route.
	 */
	public static function key( string $route, string $screen = '' ): string {
		if ( 'home' === $route ) {
			return 'home';
		}
		if ( 'screen' === $route ) {
			$in_bar = in_array( $screen, array_column( Screens::split()['nav'], 'id' ), true );
			return $in_bar ? 'screen:' . $screen : 'more';
		}
		return in_array( $route, array( 'more', 'account' ), true ) ? 'more' : '';
	}
}
