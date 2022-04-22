<?php

declare(strict_types=1);

namespace EskoTeam\network\translator;

use EskoTeam\network\Serializer;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use function chr;
use function ord;

class MobEquipmentPacketTranslator
{

	public static function serialize(MobEquipmentPacket $packet, int $protocol)
	{
		$packet->putEntityRuntimeId($packet->entityRuntimeId);
		Serializer::putItem($packet, $protocol, $packet->item->getItemStack(), $packet->item->getStackId());
		($packet->buffer .= chr($packet->inventorySlot));
		($packet->buffer .= chr($packet->hotbarSlot));
		($packet->buffer .= chr($packet->windowId));
	}

	public static function deserialize(MobEquipmentPacket $packet, int $protocol)
	{
		$packet->entityRuntimeId = $packet->getEntityRuntimeId();
		$packet->item = Serializer::getItemStackWrapper($packet, $protocol);
		$packet->inventorySlot = (ord($packet->get(1)));
		$packet->hotbarSlot = (ord($packet->get(1)));
		$packet->windowId = (ord($packet->get(1)));
	}
}