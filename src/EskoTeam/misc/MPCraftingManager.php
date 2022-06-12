<?php

declare(strict_types=1);

namespace EskoTeam\misc;

use EskoTeam\network\ProtocolInfo;
use EskoTeam\network\translator\Translator;
use pocketmine\inventory\CraftingManager;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\CraftingDataPacket;
use pocketmine\Server;
use pocketmine\timings\Timings;

class MPCraftingManager extends CraftingManager
{

	const PROTOCOL = [
		ProtocolInfo::BEDROCK_1_18_30,
		ProtocolInfo::BEDROCK_1_18_10,
		ProtocolInfo::BEDROCK_1_18_0,
		ProtocolInfo::BEDROCK_1_17_40,
		ProtocolInfo::BEDROCK_1_17_30,
		ProtocolInfo::BEDROCK_1_17_10,
		ProtocolInfo::BEDROCK_1_17_0,
		ProtocolInfo::BEDROCK_1_16_220
	];
	/** @var BatchPacket[] */
	protected $multiVersionCraftingDataCache = [];

	public function buildCraftingDataCache(): void
	{
		Timings::$craftingDataCacheRebuildTimer->startTiming();
		$c = Server::getInstance()->getCraftingManager();
		foreach (self::PROTOCOL as $protocol) {
			$pk = new CraftingDataPacket();
			$pk->cleanRecipes = true;

			foreach ($c->shapelessRecipes as $list) {
				foreach ($list as $recipe) {
					$pk->addShapelessRecipe($recipe);
				}
			}
			foreach ($c->shapedRecipes as $list) {
				foreach ($list as $recipe) {
					$pk->addShapedRecipe($recipe);
				}
			}

			foreach ($c->furnaceRecipes as $recipe) {
				$pk->addFurnaceRecipe($recipe);
			}

			$pk = Translator::fromServer($pk, $protocol);

			$batch = new BatchPacket();
			$batch->addPacket($pk);
			$batch->setCompressionLevel(Server::getInstance()->networkCompressionLevel);
			$batch->encode();

			$this->multiVersionCraftingDataCache[$protocol] = $batch;
		}
		Timings::$craftingDataCacheRebuildTimer->stopTiming();
	}

	public function getCraftingDataPacketA(int $protocol): BatchPacket
	{
		return $this->multiVersionCraftingDataCache[$protocol];
	}
}
