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
 * Screens::NAV_SLOTS registered `nav` screens, and More. A screen that asked
 * for the `center` slot sits in the middle of them, raised, and takes one of
 * those slots. More lists the `more` screens, the `nav` screens that did not
 * fit, and the account, so the bar holds at most five items whatever is
 * registered.
 *
 * @phpstan-type Item array{id: string, label: string, url: string, icon: string, raised: bool, current: bool}
 */
final class Navigation {

	/**
	 * The items, the current one marked.
	 *
	 * @param string $current The current item's key (see key()).
	 * @return list<Item>
	 */
	public static function items( string $current ): array {
		$split   = Screens::split();
		$screens = array_map( array( self::class, 'screen' ), $split['nav'] );
		if ( null !== $split['center'] ) {
			// The middle of the five, or as close as fewer screens allow.
			array_splice( $screens, (int) ceil( count( $screens ) / 2 ), 0, array( self::screen( $split['center'], true ) ) );
		}
		$items = array_merge(
			array(
				array(
					'id'     => 'home',
					'label'  => __( 'Home', 'site-pwa' ),
					'url'    => Routes::url( 'home' ),
					'icon'   => 'home',
					'raised' => false,
				),
			),
			$screens,
			array(
				array(
					'id'     => 'more',
					'label'  => __( 'More', 'site-pwa' ),
					'url'    => Routes::url( 'more' ),
					'icon'   => 'more',
					'raised' => false,
				),
			)
		);

		return array_map(
			static fn( array $item ): array => $item + array( 'current' => $item['id'] === $current ),
			$items
		);
	}

	/**
	 * A screen as a bar item.
	 *
	 * @param array{id: string, label: string, icon: string, render: callable|null, url: string} $screen The screen.
	 * @param bool                                                                               $raised Whether it holds the centre slot.
	 * @return array{id: string, label: string, url: string, icon: string, raised: bool}
	 */
	private static function screen( array $screen, bool $raised = false ): array {
		return array(
			'id'     => 'screen:' . $screen['id'],
			'label'  => $screen['label'],
			'url'    => Screens::link( $screen ),
			'icon'   => $screen['icon'],
			'raised' => $raised,
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
			$split  = Screens::split();
			$bar    = array_column( $split['nav'], 'id' );
			$bar[]  = null === $split['center'] ? '' : $split['center']['id'];
			$in_bar = in_array( $screen, $bar, true );
			return $in_bar ? 'screen:' . $screen : 'more';
		}
		return in_array( $route, array( 'more', 'account', 'news' ), true ) ? 'more' : '';
	}
}
