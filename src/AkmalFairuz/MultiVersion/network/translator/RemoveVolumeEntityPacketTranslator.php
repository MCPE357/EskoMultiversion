<?php

declare(strict_types=1);

namespace AkmalFairuz\MultiVersion\network\translator;

use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\RemoveVolumeEntityPacket;

class RemoveVolumeEntityPacketTranslator{

	public static function serialize(RemoveVolumeEntityPacket $packet, int $protocol){
		$packet->putUnsignedVarInt($packet->getEntityNetId());
		if($protocol >= ProtocolInfo::CURRENT_PROTOCOL){
			$packet->putVarInt($packet->getDimension());
		}
	}
}