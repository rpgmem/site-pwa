<?php
/**
 * Stand-in for Free Form Certificate's PublicDesign: the four members
 * Site PWA reads, with the values a test sets.
 */

declare(strict_types=1);

namespace FreeFormCertificate\Core;

final class PublicDesign {

	/** @var array{enabled: bool, accent: string, radius: int, card_radius: int} */
	public static array $stored = array(
		'enabled'     => false,
		'accent'      => '#2271b1',
		'radius'      => 4,
		'card_radius' => 8,
	);

	public bool $enabled;
	public string $accent;
	public int $radius;
	public int $card_radius;

	public static function from_settings(): self {
		$design              = new self();
		$design->enabled     = self::$stored['enabled'];
		$design->accent      = self::$stored['accent'];
		$design->radius      = self::$stored['radius'];
		$design->card_radius = self::$stored['card_radius'];
		return $design;
	}
}
