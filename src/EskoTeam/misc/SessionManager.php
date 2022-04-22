<?php

declare(strict_types=1);

namespace EskoTeam\misc;

use pocketmine\Player;

class SessionManager
{

	/** @var Session[] */
	private static $sessions = [];

	public static function remove(Player $player)
	{
		unset(self::$sessions[$player->getLoaderId()]);
	}

	public static function create(Player $player, int $protocol)
	{
		self::$sessions[$player->getLoaderId()] = new Session($player, $protocol);
	}

	public static function getProtocol(Player $player): ?int
	{
		if (($session = self::get($player)) !== null) {
			return $session->protocol;
		}
		return -1;
	}

	public static function get(Player $player): ?Session
	{
		return self::$sessions[$player->getLoaderId()] ?? null;
	}
}