<?php

declare(strict_types=1);

namespace EskoTeam\misc;

use EskoTeam\MultiEsko;
use EskoTeam\network\ProtocolInfo;
use pocketmine\network\mcpe\convert\ItemTypeDictionary;
use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\SingletonTrait;
use function array_key_exists;
use function file_get_contents;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function json_decode;

class MPItemTypeDictionary
{
	use SingletonTrait;

	/**
	 * @var ItemTypeEntry[][]
	 */
	private $itemTypes;
	/**
	 * @var string[][]
	 */
	private $intToStringIdMap = [];
	/**
	 * @var int[][]
	 */
	private $stringToIntMap = [];

	/**
	 * @param ItemTypeEntry[][] $itemTypes
	 */
	public function __construct(array $itemTypes)
	{
		$this->itemTypes = $itemTypes;
		foreach ($this->itemTypes as $protocol => $types) {
			foreach ($types as $type) {
				$this->stringToIntMap[$protocol][$type->getStringId()] = $type->getNumericId();
				$this->intToStringIdMap[$protocol][$type->getNumericId()] = $type->getStringId();
			}
		}
	}

	private static function make(): self
	{
		$itemTypes = [];
		foreach (ProtocolInfo::PROTOCOL as $protocol => $file) {
			$data = file_get_contents(MultiEsko::getInstance()->getDataFolder() . 'vanilla/required_item_list' . $file . '.json');
			if ($data === false) throw new AssumptionFailedError("Missing required resource file");
			$table = json_decode($data, true);
			if (!is_array($table)) {
				throw new AssumptionFailedError("Invalid item list format");
			}

			$params = [];
			foreach ($table as $name => $entry) {
				if (!is_array($entry) || !is_string($name) || !isset($entry["component_based"], $entry["runtime_id"]) || !is_bool($entry["component_based"]) || !is_int($entry["runtime_id"])) {
					throw new AssumptionFailedError("Invalid item list format");
				}
				$params[] = new ItemTypeEntry($name, $entry["runtime_id"], $entry["component_based"]);
			}
			$itemTypes[$protocol] = $params;
		}
		return new self($itemTypes);
	}

	/**
	 * @param int $protocol
	 * @return ItemTypeEntry[]
	 * @phpstan-return list<ItemTypeEntry>
	 */
	public function getEntries(int $protocol): array
	{
		return $this->itemTypes[$protocol];
	}

	public function getAllEntries()
	{
		return $this->itemTypes;
	}

	public function fromStringId(string $stringId, int $protocol): int
	{
		if (!array_key_exists($stringId, $this->stringToIntMap[$protocol])) {
			return ItemTypeDictionary::getInstance()->fromStringId($stringId); // custom item check
		}
		return $this->stringToIntMap[$protocol][$stringId];
	}

	public function fromIntId(int $intId, int $protocol): string
	{
		if (!array_key_exists($intId, $this->intToStringIdMap[$protocol])) {
			return ItemTypeDictionary::getInstance()->fromIntId($intId); // custom item check
		}
		return $this->intToStringIdMap[$protocol][$intId];
	}
}