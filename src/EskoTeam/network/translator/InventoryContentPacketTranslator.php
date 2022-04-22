<?php

declare(strict_types=1);

namespace EskoTeam\network\translator;

use EskoTeam\network\Serializer;
use pocketmine\network\mcpe\protocol\InventoryContentPacket;
use function count;

class InventoryContentPacketTranslator
{

	public static function serialize(InventoryContentPacket $packet, int $protocol)
	{
		$packet->putUnsignedVarInt($packet->windowId);
		$packet->putUnsignedVarInt(count($packet->items));
		foreach ($packet->items as $item) {
			Serializer::putItem($packet, $protocol, $item->getItemStack(), $item->getStackId());
		}
	}
}