<?php

declare(strict_types=1);

namespace EskoTeam\network\translator;

use EskoTeam\network\Serializer;
use pocketmine\network\mcpe\protocol\GameRulesChangedPacket;

class GameRulesChangedPacketTranslator
{

	public static function serialize(GameRulesChangedPacket $packet, int $protocol)
	{
		Serializer::putGameRules($packet, $packet->gameRules, $protocol);
	}
}