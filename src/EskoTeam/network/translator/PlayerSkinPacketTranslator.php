<?php

declare(strict_types=1);

namespace EskoTeam\network\translator;

use EskoTeam\network\Serializer;
use pocketmine\network\mcpe\protocol\PlayerSkinPacket;

class PlayerSkinPacketTranslator
{

	public static function serialize(PlayerSkinPacket $packet, int $protocol)
	{
		$packet->putUUID($packet->uuid);
		Serializer::putSkin($packet->skin, $packet, $protocol);
		$packet->putString($packet->newSkinName);
		$packet->putString($packet->oldSkinName);
		($packet->buffer .= ($packet->skin->isVerified() ? "\x01" : "\x00"));
	}

	public static function deserialize(PlayerSkinPacket $packet, int $protocol)
	{
		$packet->uuid = $packet->getUUID();
		$packet->skin = Serializer::getSkin($packet, $protocol);
		$packet->newSkinName = $packet->getString();
		$packet->oldSkinName = $packet->getString();
		$packet->skin->setVerified((($packet->get(1) !== "\x00")));
	}
}