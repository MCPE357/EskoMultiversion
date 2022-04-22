<?php

declare(strict_types=1);

namespace EskoTeam\network\translator;

use EskoTeam\network\ProtocolInfo;
use pocketmine\network\mcpe\protocol\NpcRequestPacket;

class NpcRequestPacketTranslator
{

	public static function deserialize(NpcRequestPacket $packet, int $protocol)
	{
		$packet->entityRuntimeId = $packet->getEntityRuntimeId();
		$packet->requestType = $packet->getByte();
		$packet->commandString = $packet->getString();
		$packet->actionType = $packet->getByte();
		if ($protocol >= ProtocolInfo::BEDROCK_1_17_10) {
			$packet->sceneName = $packet->getString();
		}
	}
}