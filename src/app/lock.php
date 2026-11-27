<?php
/**
 * The app lock: the phone's own screen lock in front of the app.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\App;

use SitePwa\Plugin;
use SitePwa\Settings;

/**
 * Locks the app with the phone's screen lock (fingerprint, face or the
 * phone's PIN), through a platform credential (WebAuthn) the phone keeps.
 *
 * The lock lives on the device: nothing is stored on the server, and
 * unlocking works without a connection. The browser decides how the phone
 * asks; the app cannot ask for a fingerprint only, nor create a PIN of its
 * own. It locks when the app opens and when it comes back after
 * {@see GRACE} seconds away, unless the app itself sent the person out
 * (a PDF, a share sheet, a file picker).
 *
 * A consumer makes it mandatory for an account through the
 * `site_pwa_lock_required` filter; everyone else is invited to turn it on.
 */
final class Lock {

	/**
	 * Script handle.
	 */
	public const HANDLE = 'site-pwa-lock';

	/**
	 * Seconds the app may stay out of sight before it locks again.
	 */
	public const GRACE = 10;

	/**
	 * Days a dismissed invitation to turn the lock on stays hidden.
	 */
	public const NUDGE_DAYS = 7;

	/**
	 * Query argument the app's own sign-in adds to the address it returns
	 * to, so the first screen after signing in offers the lock (#76). The
	 * script takes it off the address once read.
	 */
	public const WELCOME = 'site_pwa_welcome';

	/**
	 * Whether the lock is mandatory for this account.
	 *
	 * @param \WP_User $user The signed-in user.
	 */
	public static function required( \WP_User $user ): bool {
		if ( $user->ID <= 0 ) {
			return false;
		}
		/**
		 * Whether the app lock is mandatory for an account: the app shows
		 * nothing until it is turned on.
		 *
		 * @param bool     $required False by default.
		 * @param \WP_User $user     The signed-in user.
		 */
		return true === apply_filters( 'site_pwa_lock_required', false, $user );
	}

	/**
	 * Whether this screen is the first one after signing in on the app's
	 * own sign-in, which offers the lock before anything else (#76).
	 */
	public static function welcome(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a display hint with no effect on data; it only decides whether the lock is offered.
		return isset( $_GET[ self::WELCOME ] ) && '1' === sanitize_text_field( wp_unslash( (string) $_GET[ self::WELCOME ] ) );
	}

	/**
	 * The key this account's lock is stored under on the device: the same
	 * phone may serve more than one account, and each keeps its own.
	 *
	 * @param \WP_User $user The signed-in user.
	 */
	public static function key( \WP_User $user ): string {
		return substr( wp_hash( 'site-pwa-lock|' . $user->ID ), 0, 16 );
	}

	/**
	 * What the script needs, and the head check reads before the first paint.
	 *
	 * @param \WP_User $user The signed-in user.
	 * @return array{key: string, required: bool, welcome: bool, grace: int, nudgeDays: int, name: string, logout: string, strings: array<string, string>}
	 */
	public static function config( \WP_User $user ): array {
		return array(
			'key'       => self::key( $user ),
			'required'  => self::required( $user ),
			'welcome'   => self::welcome(),
			'grace'     => self::GRACE,
			'nudgeDays' => self::NUDGE_DAYS,
			'name'      => Settings::get()['name'],
			'logout'    => wp_logout_url( Routes::url( 'home' ) ),
			'strings'   => array(
				'unlocking'  => __( 'Confirm with your phone\'s screen lock…', 'site-pwa' ),
				'failed'     => __( 'Not unlocked. Try again, or sign out and in again.', 'site-pwa' ),
				'turningOn'  => __( 'Confirm with your phone\'s screen lock to turn the lock on…', 'site-pwa' ),
				'turnedOn'   => __( 'The app now locks with your phone\'s screen lock.', 'site-pwa' ),
				'notTurned'  => __( 'The lock was not turned on. Try again.', 'site-pwa' ),
				'noLock'     => __( 'This phone has no screen lock. That is highly unsafe: anyone holding the phone opens the app and everything in it. Turn the screen lock on in the phone\'s settings, then come back.', 'site-pwa' ),
				'turnedOff'  => __( 'The app no longer locks on this phone.', 'site-pwa' ),
				'requiredOn' => __( 'Required for your account: it cannot be turned off.', 'site-pwa' ),
			),
		);
	}

	/**
	 * Mark the document locked before the first paint, so nothing the app
	 * shows is seen before the phone is unlocked. The script then decides
	 * what the lock screen asks for.
	 *
	 * @param \WP_User $user The signed-in user.
	 */
	public static function headScript( \WP_User $user ): string {
		$config = self::config( $user );
		$data   = wp_json_encode(
			array(
				'key'      => $config['key'],
				'required' => $config['required'],
				'welcome'  => $config['welcome'],
				'grace'    => $config['grace'],
			)
		);
		return 'try{var c=' . $data . ',d=document.documentElement,s=null,o=null;'
			. "try{s=JSON.parse(localStorage.getItem('site-pwa-lock:'+c.key)||'null');}catch(e){}"
			. "try{o=parseInt(sessionStorage.getItem('site-pwa-lock-seen:'+c.key)||'0',10);}catch(e){}"
			. 'if((s&&s.id)||c.required){if(!o||Date.now()-o>c.grace*1000){d.classList.add(\'site-pwa-locked\');}}'
			. 'else if(c.welcome){d.classList.add(\'site-pwa-locked\');}'
			. '}catch(e){}';
	}

	/**
	 * Enqueue the lock's script with its configuration.
	 *
	 * @param \WP_User $user The signed-in user.
	 */
	public static function enqueue( \WP_User $user ): void {
		$js = Plugin::asset( 'assets/js/lock.js' );
		wp_enqueue_script(
			self::HANDLE,
			Plugin::url() . $js,
			array(),
			Plugin::assetVersion( $js ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script( self::HANDLE, 'sitePwaLock', self::config( $user ) );
	}
}
