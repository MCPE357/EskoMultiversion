<?php

declare(strict_types=1);

namespace EskoTeam\network\translator;

use EskoTeam\network\ProtocolInfo;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;

class PlayerActionPacketTranslator
{

	public static function deserialize(PlayerActionPacket $packet, int $protocol)
	{
		$packet->entityRuntimeId = $packet->getEntityRuntimeId();
		$packet->action = $packet->getVarInt();
		$packet->getBlockPosition($packet->x, $packet->y, $packet->z);
		if ($protocol > ProtocolInfo::BEDROCK_1_18_30) {
			$packet->getBlockPosition($packet->resultX, $packet->resultY, $packet->resultZ);
		}
		$packet->face = $packet->getVarInt();
	}

	public static function serialize(PlayerActionPacket $packet, int $protocol)
	{
		$packet->putEntityRuntimeId($packet->entityRuntimeId);
		$packet->putVarInt($packet->action);
		$packet->putBlockPosition($packet->x, $packet->y, $packet->z);
		if ($protocol > ProtocolInfo::BEDROCK_1_18_30) {
			$packet->putBlockPosition($packet->resultX, $packet->resultY, $packet->resultZ);
		}
		$packet->putVarInt($packet->face);
	}
}