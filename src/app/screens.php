<?php
/**
 * Screens other plugins register with the app.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\App;

use WP_Error;

/**
 * The screen registration contract.
 *
 * A plugin registers its screens on an action, so it needs no check that Site
 * PWA is installed: without it the action never fires.
 *
 *     add_action( 'site_pwa_register_screens', static function ( $screens, int $api ): void {
 *         $screens->add( 'presence', array(
 *             'label'      => __( 'Presence', 'my-plugin' ),
 *             'icon'       => 'check',
 *             'place'      => 'nav',
 *             'capability' => 'read',
 *             'render'     => array( My\Presence::class, 'render' ),
 *         ) );
 *     }, 10, 2 );
 *
 * The app knows nothing about the plugins that use it. It decides where a
 * screen goes, draws it inside its frame at `/app/s/<id>/`, and shows it only
 * to whoever holds its capability: in the navigation, on the home screen and
 * at its URL alike.
 *
 * @phpstan-type Screen array{id: string, label: string, icon: string, place: string, slot: string, section: string, subtitle: string, badge: callable|string, capability: string, render: callable|null, url: string, position: int}
 */
final class Screens {

	/**
	 * The contract's version, passed as the action's second argument. Raised
	 * only when a change would break a consumer written for the previous one.
	 */
	public const API_VERSION = 1;

	/**
	 * The action consumers register on.
	 */
	public const ACTION = 'site_pwa_register_screens';

	/**
	 * Places a screen can take.
	 */
	public const PLACES = array( 'nav', 'more' );

	/**
	 * The slot a `nav` screen can ask for: `center` raises it in the middle of
	 * the bar, as the app's main action. One screen holds it; the first by
	 * position wins and any other stays an ordinary `nav` screen.
	 */
	public const SLOTS = array( '', 'center' );

	/**
	 * The More sections a screen can sit in: what a person uses, or what an
	 * operator does for others.
	 */
	public const SECTIONS = array( 'services', 'operator' );

	/**
	 * Screens in the bottom navigation, between Home and More. Further `nav`
	 * screens go to the More list.
	 */
	public const NAV_SLOTS = 3;

	/**
	 * Ids the app's own screens and routes use.
	 */
	public const RESERVED = array( 'home', 'account', 'more', 'news', 'post', 'offline', 'manifest', 'sw', 's' );

	/**
	 * The icon of a screen that names none, or one the app does not draw.
	 */
	public const DEFAULT_ICON = 'grid';

	/**
	 * Registered screens by id, or null before the action has fired.
	 *
	 * @var array<string, Screen>|null
	 */
	private static ?array $registered = null;

	/**
	 * Screens collected while the action runs.
	 *
	 * @var array<string, Screen>
	 */
	private array $collected = array();

	/**
	 * Every registered screen, by id. The action fires once, the first time
	 * the app asks.
	 *
	 * @return array<string, Screen>
	 */
	public static function all(): array {
		if ( null === self::$registered ) {
			$registry = new self();
			/**
			 * Register screens with the app.
			 *
			 * @param Screens $registry Call `add()` on it.
			 * @param int     $api      The contract's version.
			 */
			do_action( 'site_pwa_register_screens', $registry, self::API_VERSION );
			self::$registered = $registry->collected;
		}
		return self::$registered;
	}

	/**
	 * The screens the current user may open, ordered by position, then label.
	 *
	 * @param string|null $place `nav`, `more`, or null for both.
	 * @return list<Screen>
	 */
	public static function visible( ?string $place = null ): array {
		$screens = array_values(
			array_filter(
				self::all(),
				static fn( array $screen ): bool => ( null === $place || $place === $screen['place'] ) && current_user_can( $screen['capability'] )
			)
		);
		usort(
			$screens,
			static fn( array $a, array $b ): int => array( $a['position'], $a['label'] ) <=> array( $b['position'], $b['label'] )
		);
		return $screens;
	}

	/**
	 * A screen the current user may open, or null: unknown and forbidden are
	 * one answer, so the app never says which.
	 *
	 * @param string $id Screen id.
	 * @return Screen|null
	 */
	public static function find( string $id ): ?array {
		$screen = self::all()[ $id ] ?? null;
		return null !== $screen && current_user_can( $screen['capability'] ) ? $screen : null;
	}

	/**
	 * A screen with a page inside the app, at `/app/s/<id>/`, that the
	 * current user may open; null otherwise. A URL screen opens at its own
	 * address, so it has no page here.
	 *
	 * @param string $id Screen id.
	 * @return Screen|null
	 */
	public static function page( string $id ): ?array {
		$screen = self::find( $id );
		return null !== $screen && null !== $screen['render'] ? $screen : null;
	}

	/**
	 * The `nav` screens that fit the bar, the one raised in its centre, and
	 * the rest, which join More. The centre takes one of the bar's slots, so
	 * the bar still holds Home, three screens and More.
	 *
	 * @return array{nav: list<Screen>, center: Screen|null, more: list<Screen>}
	 */
	public static function split(): array {
		$nav    = self::visible( 'nav' );
		$center = null;
		foreach ( $nav as $index => $screen ) {
			if ( 'center' === $screen['slot'] ) {
				$center = $screen;
				unset( $nav[ $index ] );
				break;
			}
		}
		$nav   = array_values( $nav );
		$slots = null === $center ? self::NAV_SLOTS : self::NAV_SLOTS - 1;
		return array(
			'nav'    => array_slice( $nav, 0, $slots ),
			'center' => $center,
			'more'   => array_merge( array_slice( $nav, $slots ), self::visible( 'more' ) ),
		);
	}

	/**
	 * Where a screen opens: its URL, or its page inside the app.
	 *
	 * @param array{id: string, render: callable|null, url: string} $screen The screen.
	 */
	public static function link( array $screen ): string {
		return null === $screen['render'] ? $screen['url'] : home_url( '/' . Routes::prefix() . '/s/' . $screen['id'] . '/' );
	}

	/**
	 * The short status a screen shows beside its name in More, as plain
	 * text: its string, or what its callable returns now. A callable that
	 * throws or returns anything else shows nothing.
	 *
	 * @param array{badge: callable|string} $screen The screen.
	 */
	public static function badge( array $screen ): string {
		$badge = $screen['badge'];
		if ( is_callable( $badge ) ) {
			try {
				$badge = call_user_func( $badge );
			} catch ( \Throwable $error ) {
				return '';
			}
		}
		return is_string( $badge ) ? trim( wp_strip_all_tags( $badge ) ) : '';
	}

	/**
	 * Forget what was registered, so the action fires again.
	 */
	public static function reset(): void {
		self::$registered = null;
	}

	/**
	 * Register a screen.
	 *
	 * @param string               $id   Lowercase letters, digits and hyphens; part of the URL.
	 * @param array<string, mixed> $args `label` (required), `icon`, `place` (`nav` or `more`,
	 *                                   default `more`), `slot` (`center` for a `nav` screen
	 *                                   raised in the middle of the bar), `section` (`services` or
	 *                                   `operator`, More's group), `subtitle`, `badge` (a string, or a
	 *                                   callable returning one, shown beside the name in More),
	 *                                   `capability` (default `read`), `position`
	 *                                   (default 10), and exactly one of `render` (a callable that
	 *                                   returns or prints the screen's HTML) or `url` (a page on
	 *                                   this site).
	 * @return true|WP_Error
	 */
	public function add( string $id, array $args ) {
		$screen = self::validate( $id, $args, $this->collected );
		if ( is_wp_error( $screen ) ) {
			_doing_it_wrong( __METHOD__, esc_html( $screen->get_error_message() ), '0.1.0' );
			return $screen;
		}
		$this->collected[ $id ] = $screen;
		return true;
	}

	/**
	 * Check a registration and fill its defaults.
	 *
	 * @param string                $id       Screen id.
	 * @param array<string, mixed>  $args     Its arguments.
	 * @param array<string, Screen> $existing Screens registered so far.
	 * @return Screen|WP_Error
	 */
	private static function validate( string $id, array $args, array $existing ) {
		if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $id ) ) {
			return new WP_Error( 'site_pwa_screen_id', sprintf( 'Screen id "%s" must be lowercase letters, digits and hyphens.', $id ) );
		}
		if ( in_array( $id, self::RESERVED, true ) ) {
			return new WP_Error( 'site_pwa_screen_reserved', sprintf( 'Screen id "%s" is reserved by the app.', $id ) );
		}
		if ( isset( $existing[ $id ] ) ) {
			return new WP_Error( 'site_pwa_screen_duplicate', sprintf( 'Screen "%s" is already registered.', $id ) );
		}

		$label = is_string( $args['label'] ?? null ) ? trim( wp_strip_all_tags( $args['label'] ) ) : '';
		if ( '' === $label ) {
			return new WP_Error( 'site_pwa_screen_label', sprintf( 'Screen "%s" needs a label.', $id ) );
		}

		$place = $args['place'] ?? 'more';
		if ( ! in_array( $place, self::PLACES, true ) ) {
			return new WP_Error( 'site_pwa_screen_place', sprintf( 'Screen "%s": place must be "nav" or "more".', $id ) );
		}

		$slot = $args['slot'] ?? '';
		if ( ! in_array( $slot, self::SLOTS, true ) ) {
			return new WP_Error( 'site_pwa_screen_slot', sprintf( 'Screen "%s": slot must be "center" or left out.', $id ) );
		}
		if ( 'center' === $slot && 'nav' !== $place ) {
			return new WP_Error( 'site_pwa_screen_slot', sprintf( 'Screen "%s": only a "nav" screen can take the center slot.', $id ) );
		}

		$section = $args['section'] ?? 'services';
		if ( ! in_array( $section, self::SECTIONS, true ) ) {
			return new WP_Error( 'site_pwa_screen_section', sprintf( 'Screen "%s": section must be "services" or "operator".', $id ) );
		}
		$badge = $args['badge'] ?? '';
		if ( ! is_string( $badge ) && ! is_callable( $badge ) ) {
			return new WP_Error( 'site_pwa_screen_badge', sprintf( 'Screen "%s": badge must be a string or a callable.', $id ) );
		}

		$render = $args['render'] ?? null;
		$url    = $args['url'] ?? null;
		if ( ( null === $render ) === ( null === $url ) ) {
			return new WP_Error( 'site_pwa_screen_target', sprintf( 'Screen "%s" needs exactly one of render or url.', $id ) );
		}
		if ( null !== $render && ! is_callable( $render ) ) {
			return new WP_Error( 'site_pwa_screen_render', sprintf( 'Screen "%s": render is not callable.', $id ) );
		}
		$link = '';
		if ( null !== $url ) {
			$link = self::sameSite( $url );
			if ( '' === $link ) {
				return new WP_Error( 'site_pwa_screen_url', sprintf( 'Screen "%s": url must be a page on this site.', $id ) );
			}
		}

		$icon = is_string( $args['icon'] ?? null ) && isset( Icons::PATHS[ $args['icon'] ] ) ? $args['icon'] : self::DEFAULT_ICON;
		$cap  = is_string( $args['capability'] ?? null ) && '' !== $args['capability'] ? $args['capability'] : 'read';

		return array(
			'id'         => $id,
			'label'      => $label,
			'icon'       => $icon,
			'place'      => $place,
			'slot'       => $slot,
			'section'    => $section,
			'subtitle'   => is_string( $args['subtitle'] ?? null ) ? trim( wp_strip_all_tags( $args['subtitle'] ) ) : '',
			'badge'      => $badge,
			'capability' => $cap,
			'render'     => null === $render ? null : $render,
			'url'        => $link,
			'position'   => is_numeric( $args['position'] ?? null ) ? (int) $args['position'] : 10,
		);
	}

	/**
	 * A URL on this site, made absolute, or '' for anything else. A path
	 * starting with `/` is taken as relative to the site's home.
	 *
	 * @param mixed $url Raw value.
	 */
	public static function sameSite( $url ): string {
		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}
		if ( '/' === $url[0] && ( ! isset( $url[1] ) || '/' !== $url[1] ) ) {
			$url = home_url( $url );
		}
		$url  = esc_url_raw( $url, array( 'http', 'https' ) );
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$home = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		return '' !== $url && is_string( $host ) && strtolower( $host ) === strtolower( (string) $home ) ? $url : '';
	}
}
