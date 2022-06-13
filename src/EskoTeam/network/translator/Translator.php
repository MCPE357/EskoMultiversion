<?php

declare(strict_types=1);

namespace EskoTeam\network\translator;

use EskoTeam\misc\MPItemTypeDictionary;
use EskoTeam\misc\MPRuntimeBlockMapping;
use EskoTeam\network\ChunkConverter;
use EskoTeam\network\ProtocolInfo;
use pocketmine\entity\Entity;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\AddItemActorPacket;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\AnimateEntityPacket;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\CraftingDataPacket;
use pocketmine\network\mcpe\protocol\CreativeContentPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\GameRulesChangedPacket;
use pocketmine\network\mcpe\protocol\InventoryContentPacket;
use pocketmine\network\mcpe\protocol\InventorySlotPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\MobArmorEquipmentPacket;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\NpcRequestPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\PlayerSkinPacket;
use pocketmine\network\mcpe\protocol\RemoveVolumeEntityPacket;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\SetTitlePacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\Player;

class Translator
{

	public static function fromClient(DataPacket $packet, int $protocol, Player $player = null): DataPacket
	{
		$pid = $packet::NETWORK_ID;
		switch ($pid) {
			case LoginPacket::NETWORK_ID:
				/** @var LoginPacket $packet */
				if ($protocol < ProtocolInfo::BEDROCK_1_17_30) {
					$packet->clientData["SkinGeometryDataEngineVersion"] = "MP";
				}
				return $packet;
			case PlayerSkinPacket::NETWORK_ID:
				/** @var PlayerSkinPacket $packet */
				if ($protocol < ProtocolInfo::BEDROCK_1_17_30) {
					self::decodeHeader($packet);
					PlayerSkinPacketTranslator::deserialize($packet, $protocol);
				}
				return $packet;
			case InventoryTransactionPacket::NETWORK_ID:
				/** @var InventoryTransactionPacket $packet */
				self::decodeHeader($packet);
				InventoryTransactionPacketTranslator::deserialize($packet, $protocol);
				return $packet;
			case LevelSoundEventPacket::NETWORK_ID:
				/** @var LevelSoundEventPacket $packet */
				$packet->decode();
				switch ($packet->sound) {
					case LevelSoundEventPacket::SOUND_PLACE:
					case LevelSoundEventPacket::SOUND_BREAK_BLOCK:
						$block = MPRuntimeBlockMapping::fromStaticRuntimeId($packet->extraData, $protocol);
						$packet->extraData = RuntimeBlockMapping::toStaticRuntimeId($block[0], $block[1]);
						return $packet;
				}
				return $packet;
			case NpcRequestPacket::NETWORK_ID:
				/** @var NpcRequestPacket $packet */
				self::decodeHeader($packet);
				NpcRequestPacketTranslator::deserialize($packet, $protocol);
				return $packet;
			case MobEquipmentPacket::NETWORK_ID:
				/** @var MobEquipmentPacket $packet */
				self::decodeHeader($packet);
				MobEquipmentPacketTranslator::deserialize($packet, $protocol);
				return $packet;
			case MobArmorEquipmentPacket::NETWORK_ID:
				/** @var MobArmorEquipmentPacket $packet */
				self::decodeHeader($packet);
				MobArmorEquipmentPacketTranslator::deserialize($packet, $protocol);
				return $packet;
			case PlayerActionPacket::NETWORK_ID:
				/** @var PlayerActionPacket */
				self::decodeHeader($packet);
				PlayerActionPacketTranslator::deserialize($packet, $protocol);
				self::encodeHeader($packet);
				PlayerActionPacketTranslator::serialize($packet, $protocol);
				break;
		}
		return $packet;
	}

	public static function decodeHeader(DataPacket $packet)
	{
		$packet->isEncoded = false;
		$packet->offset = 0;
		$header = $packet->getUnsignedVarInt();
		$pid = $header & $packet::PID_MASK;
		if ($pid !== $packet::NETWORK_ID) {
			throw new \UnexpectedValueException("Expected " . $packet::NETWORK_ID . " for packet ID, got $pid");
		}
		$packet->senderSubId = ($header >> 10) & 0x03;
		$packet->recipientSubId = ($header >> 12) & 0x03;
	}

	public static function fromServer(DataPacket $packet, int $protocol, Player $player = null, bool &$translated = true): ?DataPacket
	{
		$pid = $packet::NETWORK_ID;
		switch ($pid) {
			case ResourcePackStackPacket::NETWORK_ID:
				/** @var ResourcePackStackPacket $packet */
				$packet->baseGameVersion = "1.16.220";
				return $packet;
			case UpdateBlockPacket::NETWORK_ID:
				/** @var UpdateBlockPacket $packet */
				$block = RuntimeBlockMapping::fromStaticRuntimeId($packet->blockRuntimeId);
				$packet->blockRuntimeId = MPRuntimeBlockMapping::toStaticRuntimeId($block[0], $block[1], $protocol);
				return $packet;
			case LevelSoundEventPacket::NETWORK_ID:
				/** @var LevelSoundEventPacket $packet */
				switch ($packet->sound) {
					case LevelSoundEventPacket::SOUND_PLACE:
					case LevelSoundEventPacket::SOUND_BREAK_BLOCK:
						$block = RuntimeBlockMapping::fromStaticRuntimeId($packet->extraData);
						$packet->extraData = MPRuntimeBlockMapping::toStaticRuntimeId($block[0], $block[1], $protocol);
						return $packet;
				}
				return $packet;
			case AddActorPacket::NETWORK_ID:
				/** @var AddActorPacket $packet */
				switch ($packet->type) {
					case "minecraft:falling_block":
						if (isset($packet->metadata[Entity::DATA_VARIANT])) {
							$block = RuntimeBlockMapping::fromStaticRuntimeId($packet->metadata[Entity::DATA_VARIANT][1]);
							$packet->metadata[Entity::DATA_VARIANT] = [Entity::DATA_TYPE_INT, MPRuntimeBlockMapping::toStaticRuntimeId($block[0], $block[1], $protocol)];
						}
						return $packet;
				}
				return $packet;
			case LevelEventPacket::NETWORK_ID:
				/** @var LevelEventPacket $packet */
				switch ($packet->evid) {
					case LevelEventPacket::EVENT_PARTICLE_DESTROY:
						$block = RuntimeBlockMapping::fromStaticRuntimeId($packet->data);
						$packet->data = MPRuntimeBlockMapping::toStaticRuntimeId($block[0], $block[1], $protocol);
						return $packet;
					case LevelEventPacket::EVENT_PARTICLE_PUNCH_BLOCK:
						$position = $packet->position;
						$block = $player->getLevelNonNull()->getBlock($position);
						if ($block->getId() === 0) {
							return null;
						}
						$face = $packet->data & ~$block->getRuntimeId();
						$packet->data = MPRuntimeBlockMapping::toStaticRuntimeId($block->getId(), $block->getDamage(), $protocol) | $face;
						return $packet;
				}
				return $packet;
			case LevelChunkPacket::NETWORK_ID:
				/** @var LevelChunkPacket $packet */
				if ($protocol <= ProtocolInfo::BEDROCK_1_17_40) {
					if ($player->getLevel() !== null) {
						return ChunkConverter::serialize($player->getLevel(), $packet);
					}
					return null;
				}
				return $packet;
			case RemoveVolumeEntityPacket::NETWORK_ID:
				/** @var RemoveVolumeEntityPacket $packet */
				self::encodeHeader($packet);
				RemoveVolumeEntityPacketTranslator::serialize($packet, $protocol);
				break;
			case AnimateEntityPacket::NETWORK_ID:
				/** @var AnimateEntityPacket $packet */
				self::encodeHeader($packet);
				AnimateEntityPacketTranslator::serialize($packet, $protocol);
				return $packet;
			case CraftingDataPacket::NETWORK_ID:
				/** @var CraftingDataPacket $packet */
				self::encodeHeader($packet);
				CraftingDataPacketTranslator::serialize($packet, $protocol);
				return $packet;
			case PlayerListPacket::NETWORK_ID:
				/** @var PlayerListPacket $packet */
				self::encodeHeader($packet);
				PlayerListPacketTranslator::serialize($packet, $protocol);
				return $packet;
			case StartGamePacket::NETWORK_ID:
				/** @var StartGamePacket $packet */
				$packet->itemTable = MPItemTypeDictionary::getInstance()->getEntries($protocol);
				self::encodeHeader($packet);
				StartGamePacketTranslator::serialize($packet, $protocol);
				return $packet;
			case PlayerSkinPacket::NETWORK_ID:
				/** @var PlayerSkinPacket $packet */
				self::encodeHeader($packet);
				PlayerSkinPacketTranslator::serialize($packet, $protocol);
				return $packet;
			case AddItemActorPacket::NETWORK_ID:
				/** @var AddItemActorPacket $packet */
				self::encodeHeader($packet);
				AddItemActorPacketTranslator::serialize($packet, $protocol);
				return $packet;
			case InventoryContentPacket::NETWORK_ID:
				/** @var InventoryContentPacket $packet */
				self::encodeHeader($packet);
				InventoryContentPacketTranslator::serialize($packet, $protocol);
				return $packet;
			case MobEquipmentPacket::NETWORK_ID:
				/** @var MobEquipmentPacket $packet */
				self::encodeHeader($packet);
				MobEquipmentPacketTranslator::serialize($packet, $protocol);
				return $packet;
			case MobArmorEquipmentPacket::NETWORK_ID:
				/** @var MobArmorEquipmentPacket $packet */
				self::encodeHeader($packet);
				MobArmorEquipmentPacketTranslator::serialize($packet, $protocol);
				return $packet;
			case AddPlayerPacket::NETWORK_ID:
				/** @var AddPlayerPacket $packet */
				self::encodeHeader($packet);
				AddPlayerPacketTranslator::serialize($packet, $protocol);
				return $packet;
			case InventorySlotPacket::NETWORK_ID:
				/** @var InventorySlotPacket $packet */
				self::encodeHeader($packet);
				InventorySlotPacketTranslator::serialize($packet, $protocol);
				return $packet;
			case InventoryTransactionPacket::NETWORK_ID:
				/** @var InventoryTransactionPacket $packet */
				self::encodeHeader($packet);
				InventoryTransactionPacketTranslator::serialize($packet, $protocol);
				return $packet;
			case CreativeContentPacket::NETWORK_ID:
				/** @var CreativeContentPacket $packet */
				self::encodeHeader($packet);
				CreativeContentPacketTranslator::serialize($packet, $protocol);
				return $packet;
			case AvailableCommandsPacket::NETWORK_ID:
				/** @var AvailableCommandsPacket $packet */
				self::encodeHeader($packet);
				AvailableCommandsPacketTranslator::serialize($packet, $protocol);
				return $packet;
			case SetTitlePacket::NETWORK_ID:
				/** @var SetTitlePacket $packet */
				self::encodeHeader($packet);
				SetTitlePacketTranslator::serialize($packet, $protocol);
				return $packet;
			case ResourcePacksInfoPacket::NETWORK_ID:
				/** @var ResourcePacksInfoPacket $packet */
				self::encodeHeader($packet);
				ResourcePacksInfoPacketTranslator::serialize($packet, $protocol);
				return $packet;
			case GameRulesChangedPacket::NETWORK_ID:
				/** @var GameRulesChangedPacket $packet */
				self::encodeHeader($packet);
				GameRulesChangedPacketTranslator::serialize($packet, $protocol);
				return $packet;
		}
		$translated = false;
		return $packet;
	}

	public static function encodeHeader(DataPacket $packet)
	{
		$packet->reset();
		$packet->putUnsignedVarInt(
			$packet::NETWORK_ID |
			($packet->senderSubId << 10) |
			($packet->recipientSubId << 12)
		);
		$packet->isEncoded = true;
	}
}