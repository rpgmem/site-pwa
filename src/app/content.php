<?php
/**
 * Posts and pages drawn inside the app.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\App;

use WP_Post;

/**
 * A site post or page opened inside the app, at `/app/?p=ID`.
 *
 * The app URL is only where the app draws the post. Everything that leaves the
 * app (the canonical link, Share, Copy link, "Open on the site") carries the
 * post's permalink, so a link someone shares opens on the normal site.
 */
final class Content {

	/**
	 * Post meta that marks a layout drawn by a page builder, key => value
	 * that turns it on (`null`: any non-empty value).
	 */
	private const BUILDER_META = array(
		'_elementor_edit_mode'  => 'builder',
		'_et_pb_use_builder'    => 'on',
		'_fl_builder_enabled'   => null,
		'_wpb_vc_js_status'     => 'true',
		'_bricks_editor_mode'   => 'bricks',
		'ct_builder_shortcodes' => null,
	);

	/**
	 * Wire the query that loads the post.
	 */
	public static function register(): void {
		// `request` runs before the main query: rewriting the vars here is
		// what makes the main query load the post, so `is_singular()` and the
		// global `$post` are right when plugins enqueue their assets.
		add_filter( 'request', array( self::class, 'request' ) );
	}

	/**
	 * Turn the app's home with `?p=ID` into the `post` route for that post.
	 *
	 * @param array<string, mixed> $vars The parsed query vars.
	 * @return array<string, mixed>
	 */
	public static function request( array $vars ): array {
		if ( 'home' !== ( $vars[ Routes::QUERY_VAR ] ?? '' ) || ! isset( $vars['p'] ) ) {
			return $vars;
		}
		$id = absint( $vars['p'] );
		if ( 0 === $id ) {
			return $vars;
		}
		$vars[ Routes::QUERY_VAR ] = 'post';
		$vars['p']                 = $id;
		// `p` alone queries only `post`; naming the type reaches pages and
		// other public types. An unknown id keeps `post` and finds nothing.
		$type              = get_post_type( $id );
		$vars['post_type'] = false === $type ? 'post' : $type;
		return $vars;
	}

	/**
	 * The app URL of a post.
	 *
	 * @param int $id Post id.
	 */
	public static function url( int $id ): string {
		return add_query_arg( 'p', $id, Routes::url( 'home' ) );
	}

	/**
	 * The post the main query loaded, when the current user may read it and
	 * its type is viewable on the site.
	 */
	public static function current(): ?WP_Post {
		global $wp_query;
		$post = $wp_query->post ?? null;
		if ( ! $post instanceof WP_Post || 1 !== (int) $wp_query->post_count ) {
			return null;
		}
		if ( ! is_post_type_viewable( $post->post_type ) ) {
			return null;
		}
		$readable = 'publish' === $post->post_status
			|| ( 'private' === $post->post_status && current_user_can( 'read_post', $post->ID ) );
		return $readable ? $post : null;
	}

	/**
	 * The post id the request asked for, or 0.
	 */
	public static function requested(): int {
		return absint( get_query_var( 'p' ) );
	}

	/**
	 * Whether a post can be drawn in the app. A layout built with a page
	 * builder, or a page template, depends on the theme and opens on the
	 * normal site instead.
	 *
	 * @param WP_Post $post The post.
	 */
	public static function opensInApp( WP_Post $post ): bool {
		$opens = '' === (string) get_page_template_slug( $post );
		foreach ( self::BUILDER_META as $key => $value ) {
			$stored = get_post_meta( $post->ID, $key, true );
			if ( null === $value ? ! empty( $stored ) : $value === $stored ) {
				$opens = false;
			}
		}
		/**
		 * Whether a post is drawn inside the app or opens on the site.
		 *
		 * @param bool    $opens Whether it opens in the app.
		 * @param WP_Post $post  The post.
		 */
		return (bool) apply_filters( 'site_pwa_opens_in_app', $opens, $post );
	}
}
