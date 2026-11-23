<?php
/**
 * Stand-in for Free Form Certificate's SettingsReader: the one read Site PWA
 * makes, with the values a test sets.
 */

declare(strict_types=1);

namespace FreeFormCertificate\Settings;

final class SettingsReader {

	/** @var array<string, mixed> */
	public static array $stored = array();

	/**
	 * @param mixed $fallback Default.
	 * @return mixed
	 */
	public static function get( string $key, $fallback = null ) {
		return self::$stored[ $key ] ?? $fallback;
	}
}
