<?php

declare(strict_types=1);

namespace EskoTeam\network\translator;

use EskoTeam\network\ProtocolInfo;
use pocketmine\network\mcpe\protocol\RemoveVolumeEntityPacket;

class RemoveVolumeEntityPacketTranslator
{

	public static function serialize(RemoveVolumeEntityPacket $packet, int $protocol)
	{
		$packet->putUnsignedVarInt($packet->getEntityNetId());
		if ($protocol >= ProtocolInfo::BEDROCK_1_18_30) {
			$packet->putVarInt($packet->getDimension());
		}
	}
}
