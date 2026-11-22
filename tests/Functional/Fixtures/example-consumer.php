<?php
/**
 * A fake plugin that registers screens the way a real consumer would,
 * mirroring the example in docs/screens.md.
 */

declare(strict_types=1);

namespace SitePwa\Tests\Functional\Fixtures;

final class ExampleConsumer {

	public static function register(): void {
		add_action( 'site_pwa_register_screens', array( self::class, 'screens' ), 10, 2 );
	}

	/**
	 * @param \SitePwa\App\Screens $screens The registry.
	 * @param int                  $api     The contract's version.
	 */
	public static function screens( $screens, int $api ): void {
		if ( $api < 1 ) {
			return;
		}
		$screens->add(
			'presence',
			array(
				'label'    => 'Presence',
				'icon'     => 'check',
				'place'    => 'nav',
				'position' => 1,
				'render'   => array( self::class, 'presence' ),
			)
		);
		$screens->add(
			'reports',
			array(
				'label'      => 'Reports',
				'icon'       => 'document',
				'capability' => 'edit_others_posts',
				'render'     => static fn(): string => '<p class="report">Only editors see this.</p>',
			)
		);
		$screens->add(
			'calendar',
			array(
				'label' => 'Calendar',
				'icon'  => 'calendar',
				'url'   => '/calendar/',
			)
		);
	}

	public static function presence(): void {
		echo '<p class="presence">Show this code at the entrance.</p>';
	}
}
