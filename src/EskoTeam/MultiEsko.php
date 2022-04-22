<?php

declare(strict_types=1);

namespace EskoTeam;

use EskoTeam\misc\MPCraftingManager;
use EskoTeam\misc\MPRuntimeBlockMapping;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\SingletonTrait;

class MultiEsko extends PluginBase
{
	use SingletonTrait;

	/** @var MPCraftingManager */
	public $craftingManager;

	/** @var bool */
	public $canJoin = false;

	public function onEnable()
	{
		$this->getLogger()->alert("Loading...");
		self::setInstance($this);

		foreach ($this->getResources() as $fwp => $fn) {
			$this->saveResource($fwp, true);
		}

		MPRuntimeBlockMapping::init();

		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function (): void {
			$this->craftingManager = new MPCraftingManager();
			$this->canJoin = true;
		}), 1);

		$this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
		$this->getLogger()->alert("Started Successfully!");
	}
}
