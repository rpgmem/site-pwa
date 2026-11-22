<?php
/**
 * The home screen.
 *
 * @package SitePwa
 *
 * @var array{first_name: string, screens: list<array{id: string, label: string, icon: string}>} $site_pwa Values built by Frame::render().
 */

use SitePwa\App\Screens;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h1 class="site-pwa-title">
	<?php
	/* translators: %s: the signed-in user's first name */
	echo esc_html( sprintf( __( 'Hello, %s', 'site-pwa' ), $site_pwa['first_name'] ) );
	?>
</h1>
<section class="site-pwa-card" aria-labelledby="site-pwa-screens-heading">
	<h2 id="site-pwa-screens-heading"><?php esc_html_e( 'Your screens', 'site-pwa' ); ?></h2>
	<?php if ( array() === $site_pwa['screens'] ) : ?>
		<p class="site-pwa-muted"><?php esc_html_e( 'Nothing here yet. The screens other plugins add to the app appear here.', 'site-pwa' ); ?></p>
	<?php else : ?>
		<?php
		$site_pwa_rows = array();
		foreach ( $site_pwa['screens'] as $site_pwa_screen ) {
			$site_pwa_rows[] = array(
				'label' => $site_pwa_screen['label'],
				'icon'  => $site_pwa_screen['icon'],
				'url'   => Screens::link( $site_pwa_screen ),
			);
		}
		require __DIR__ . '/partial-screen-list.php';
		?>
	<?php endif; ?>
</section>
