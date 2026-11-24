<?php
/**
 * What the home screen shows, fed by other plugins.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\App;

use WP_User;

/**
 * The home screen's contract (#23).
 *
 * Other plugins hand it, through one filter, what the person should see
 * first:
 *
 *     add_filter( 'site_pwa_home', static function ( array $home, WP_User $user, int $api ): array {
 *         $home['pending'][] = array(
 *             'label' => __( 'Post-event form', 'my-plugin' ),
 *             'meta'  => __( 'Until 28/11', 'my-plugin' ),
 *             'url'   => '/forms/42/',
 *             'icon'  => 'document',
 *         );
 *         return $home;
 *     }, 10, 3 );
 *
 * The app knows nothing about the plugins that use it, so everything a
 * consumer hands it is checked here: text loses its markup, a link must be
 * on this site, an icon must be one the app draws, and what does not pass
 * is dropped rather than drawn half-way.
 *
 * @phpstan-type Banner array{id: string, text: string, emphasis: string, tone: string}
 * @phpstan-type Action array{label: string, url: string, icon: string, primary: bool}
 * @phpstan-type Next array{title: string, meta: string, actions: list<Action>}
 * @phpstan-type Pending array{label: string, meta: string, url: string, icon: string, tone: string}
 * @phpstan-type Items array{banners: list<Banner>, next: Next|null, pending: list<Pending>}
 */
final class Home {

	/**
	 * The filter consumers hook.
	 */
	public const FILTER = 'site_pwa_home';

	/**
	 * The contract's version, passed as the filter's third argument.
	 */
	public const API_VERSION = 1;

	/**
	 * Tones a banner or a pending item may take; anything else is `info`.
	 */
	public const TONES = array( 'info', 'success', 'warning' );

	/**
	 * The most of each the screen draws, so one consumer cannot bury the
	 * rest of the screen.
	 */
	public const MAX_BANNERS = 3;

	/**
	 * See MAX_BANNERS.
	 */
	public const MAX_PENDING = 8;

	/**
	 * Actions a next-commitment card holds.
	 */
	public const MAX_ACTIONS = 2;

	/**
	 * What the home screen shows the user, as the consumers handed it and
	 * once checked.
	 *
	 * @param WP_User $user The signed-in user.
	 * @return Items
	 */
	public static function items( WP_User $user ): array {
		/**
		 * Feed the app's home screen.
		 *
		 * @param array   $home `banners` (list), `next` (one card or null) and `pending` (list).
		 * @param WP_User $user The signed-in user.
		 * @param int     $api  The contract's version.
		 */
		$home = apply_filters(
			'site_pwa_home',
			array(
				'banners' => array(),
				'next'    => null,
				'pending' => array(),
			),
			$user,
			self::API_VERSION
		);
		$home = is_array( $home ) ? $home : array();

		$banners = array();
		foreach ( self::lists( $home['banners'] ?? null ) as $banner ) {
			$clean = self::banner( $banner );
			if ( null !== $clean ) {
				$banners[] = $clean;
			}
		}

		$pending = array();
		foreach ( self::lists( $home['pending'] ?? null ) as $item ) {
			$clean = self::pending( $item );
			if ( null !== $clean ) {
				$pending[] = $clean;
			}
		}

		return array(
			'banners' => array_slice( $banners, 0, self::MAX_BANNERS ),
			'next'    => is_array( $home['next'] ?? null ) ? self::next( $home['next'] ) : null,
			'pending' => array_slice( $pending, 0, self::MAX_PENDING ),
		);
	}

	/**
	 * The greeting above the name, by the site's hour.
	 *
	 * @param int|null $hour The hour, 0 to 23; the site's current one when null.
	 */
	public static function greeting( ?int $hour = null ): string {
		$hour = $hour ?? (int) wp_date( 'G' );
		if ( $hour >= 5 && $hour < 12 ) {
			return __( 'Good morning,', 'site-pwa' );
		}
		if ( $hour >= 12 && $hour < 18 ) {
			return __( 'Good afternoon,', 'site-pwa' );
		}
		return __( 'Good evening,', 'site-pwa' );
	}

	/**
	 * Where an action or an item leads: a registered screen the user may
	 * open, named by `screen`, or a page of this site in `url`. An unknown or
	 * forbidden screen leads nowhere, so the item is dropped.
	 *
	 * @param array<mixed> $raw Raw action or item.
	 */
	private static function target( array $raw ): string {
		if ( is_string( $raw['screen'] ?? null ) ) {
			$screen = Screens::find( $raw['screen'] );
			return null === $screen ? '' : Screens::link( $screen );
		}
		return Screens::sameSite( $raw['url'] ?? null );
	}

	/**
	 * A list value as a list of arrays, whatever was handed.
	 *
	 * @param mixed $value Raw value.
	 * @return list<array<mixed>>
	 */
	private static function lists( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		return array_values( array_filter( $value, 'is_array' ) );
	}

	/**
	 * Plain text, without markup.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function text( $value ): string {
		return is_string( $value ) ? trim( wp_strip_all_tags( $value ) ) : '';
	}

	/**
	 * A tone the app draws, or `info`.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function tone( $value ): string {
		return is_string( $value ) && in_array( $value, self::TONES, true ) ? $value : 'info';
	}

	/**
	 * An icon the app draws, or the fallback.
	 *
	 * @param mixed  $value    Raw value.
	 * @param string $fallback The icon when the value names none the app has.
	 */
	private static function icon( $value, string $fallback ): string {
		return is_string( $value ) && isset( Icons::PATHS[ $value ] ) ? $value : $fallback;
	}

	/**
	 * A banner: an id the person can dismiss it by, its sentence and an
	 * optional emphasised part, which the sentence places with `%s`.
	 *
	 * @param array<mixed> $raw Raw banner.
	 * @return Banner|null
	 */
	private static function banner( array $raw ): ?array {
		$id   = is_string( $raw['id'] ?? null ) ? (string) preg_replace( '/[^a-z0-9:_-]/', '', strtolower( $raw['id'] ) ) : '';
		$text = self::text( $raw['text'] ?? null );
		if ( '' === $id || '' === $text ) {
			return null;
		}
		$emphasis = self::text( $raw['emphasis'] ?? null );
		if ( '' !== $emphasis && 1 !== substr_count( $text, '%s' ) ) {
			// A sentence without exactly one place for it cannot hold it.
			$emphasis = '';
		}
		return array(
			'id'       => $id,
			'text'     => $text,
			'emphasis' => $emphasis,
			'tone'     => self::tone( $raw['tone'] ?? 'success' ),
		);
	}

	/**
	 * The next commitment: what, where and when, and up to two actions.
	 *
	 * @param array<mixed> $raw Raw card.
	 * @return Next|null
	 */
	private static function next( array $raw ): ?array {
		$title = self::text( $raw['title'] ?? null );
		if ( '' === $title ) {
			return null;
		}
		$actions = array();
		foreach ( self::lists( $raw['actions'] ?? null ) as $action ) {
			$label = self::text( $action['label'] ?? null );
			$url   = self::target( $action );
			if ( '' === $label || '' === $url ) {
				continue;
			}
			$actions[] = array(
				'label'   => $label,
				'url'     => $url,
				'icon'    => self::icon( $action['icon'] ?? null, '' ),
				'primary' => ! empty( $action['primary'] ),
			);
		}
		return array(
			'title'   => $title,
			'meta'    => self::text( $raw['meta'] ?? null ),
			'actions' => array_slice( $actions, 0, self::MAX_ACTIONS ),
		);
	}

	/**
	 * A pending item: what it is, a line under it, and where it leads.
	 *
	 * @param array<mixed> $raw Raw item.
	 * @return Pending|null
	 */
	private static function pending( array $raw ): ?array {
		$label = self::text( $raw['label'] ?? null );
		$url   = self::target( $raw );
		if ( '' === $label || '' === $url ) {
			return null;
		}
		return array(
			'label' => $label,
			'meta'  => self::text( $raw['meta'] ?? null ),
			'url'   => $url,
			'icon'  => self::icon( $raw['icon'] ?? null, 'document' ),
			'tone'  => self::tone( $raw['tone'] ?? null ),
		);
	}
}
