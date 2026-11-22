<?php
/**
 * Compile a .po catalogue into the PHP format WordPress 6.5+ prefers.
 *
 *   php bin/po-to-l10n.php languages/site-pwa-pt_BR.po
 *
 * Writes the .l10n.php next to the .po. `bin/i18n.sh` runs it beside
 * `msgfmt`, which writes the .mo; never edit either compiled file by hand.
 * The reading lives in `bin/po-catalogue.php`, which the catalogue tests use
 * too, so the compiler and the tests cannot disagree about an entry.
 *
 * @package SitePwa\Build
 */

declare(strict_types=1);

require_once __DIR__ . '/po-catalogue.php';

use SitePwa\Build\PoCatalogue;

$po = $argv[1] ?? '';
if ( '' === $po || ! is_readable( $po ) ) {
	fwrite( STDERR, "usage: php bin/po-to-l10n.php <file.po>\n" );
	exit( 1 );
}

$target = (string) preg_replace( '/\.po$/', '.l10n.php', $po );
file_put_contents( $target, PoCatalogue::l10n( $po, 'site-pwa' ) );
echo $target . "\n";
