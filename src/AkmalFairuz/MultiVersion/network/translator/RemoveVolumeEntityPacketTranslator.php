<?php

declare(strict_types=1);

namespace AkmalFairuz\MultiVersion\network\translator;

use AkmalFairuz\MultiVersion\network\ProtocolConstants;
use pocketmine\network\mcpe\protocol\RemoveVolumeEntityPacket;

class RemoveVolumeEntityPacketTranslator{

	public static function serialize(RemoveVolumeEntityPacket $packet, int $protocol){
		$packet->putUnsignedVarInt($packet->getEntityNetId());
		if($protocol > ProtocolConstants::BEDROCK_1_18_10){
			$packet->putVarInt($packet->getDimension());
		}
	}
}