<?php

declare(strict_types=1);

namespace EskoTeam\misc;

use EskoTeam\MultiEsko;
use EskoTeam\network\ProtocolInfo;
use pocketmine\block\BlockIds;
use pocketmine\nbt\NetworkLittleEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\R12ToCurrentBlockMapEntry;
use pocketmine\network\mcpe\NetworkBinaryStream;
use pocketmine\utils\AssumptionFailedError;
use RuntimeException;
use function file_get_contents;
use function json_decode;

class MPRuntimeBlockMapping
{

	/** @var int[][] */
	private static $legacyToRuntimeMap = [];
	/** @var int[][] */
	private static $runtimeToLegacyMap = [];
	/** @var CompoundTag[][]|null */
	private static $bedrockKnownStates = [];

	private function __construct()
	{
		//NOOP
	}

	public static function toStaticRuntimeId(int $id, int $meta = 0, int $protocol = ProtocolInfo::CURRENT_PROTOCOL): int
	{
		if ($protocol === ProtocolInfo::BEDROCK_1_18_0) {
			$protocol = ProtocolInfo::BEDROCK_1_17_40;
		}
		self::lazyInit();
		/*
		 * try id+meta first
		 * if not found, try id+0 (strip meta)
		 * if still not found, return update! block
		 */
		return self::$legacyToRuntimeMap[$protocol][($id << 4) | $meta] ?? self::$legacyToRuntimeMap[$protocol][$id << 4] ?? self::$legacyToRuntimeMap[$protocol][BlockIds::INFO_UPDATE << 4];
	}

	private static function lazyInit(): void
	{
		if (self::$bedrockKnownStates === null) {
			self::init();
		}
	}

	public static function init(): void
	{
		foreach (ProtocolInfo::PROTOCOL as $protocol => $fileName) {
			$canonicalBlockStatesFile = file_get_contents(MultiEsko::getInstance()->getDataFolder() . "vanilla/canonical_block_states" . $fileName . ".nbt");
			if ($canonicalBlockStatesFile === false) {
				throw new AssumptionFailedError("Missing required resource file");
			}
			$stream = new NetworkBinaryStream($canonicalBlockStatesFile);
			$list = [];
			while (!$stream->feof()) {
				$list[] = $stream->getNbtCompoundRoot();
			}
			self::$bedrockKnownStates[$protocol] = $list;

			self::setupLegacyMappings($protocol);
		}
	}

	private static function setupLegacyMappings(int $protocol): void
	{
		$legacyIdMap = json_decode(file_get_contents(MultiEsko::getInstance()->getDataFolder() . "vanilla/block_id_map.json"), true);
		$metaMap = [];

		/** @var R12ToCurrentBlockMapEntry[] $legacyStateMap */
		$legacyStateMap = [];
		$suffix = match ($protocol) {
			ProtocolInfo::BEDROCK_1_17_0, ProtocolInfo::BEDROCK_1_17_10 => ProtocolInfo::PROTOCOL[ProtocolInfo::BEDROCK_1_17_10],
			ProtocolInfo::BEDROCK_1_17_40, ProtocolInfo::BEDROCK_1_17_30 => ProtocolInfo::PROTOCOL[ProtocolInfo::BEDROCK_1_17_30],
			default => ProtocolInfo::PROTOCOL[$protocol]
		};
		foreach (MPRuntimeBlockMapping::$bedrockKnownStates[$protocol] as $runtimeId => $state) {
			$name = $state->getString("name");
			if (!isset($legacyIdMap[$name])) {
				continue;
			}

			$legacyId = $legacyIdMap[$name];
			if ($legacyId <= 469) {
				continue;
			} elseif (!isset($metaMap[$legacyId])) {
				$metaMap[$legacyId] = 0;
			}

			$meta = $metaMap[$legacyId]++;
			if ($meta > 15) {
				continue;
			}

			self::registerMapping($runtimeId, $legacyId, $meta, $protocol);
		}
		$path = MultiEsko::getInstance()->getDataFolder() . "vanilla/r12_to_current_block_map" . $suffix . ".bin";
		$legacyStateMapReader = new NetworkBinaryStream(file_get_contents($path));
		$nbtReader = new NetworkLittleEndianNBTStream();
		while (!$legacyStateMapReader->feof()) {
			$id = $legacyStateMapReader->getString();
			$meta = $legacyStateMapReader->getLShort();

			$offset = $legacyStateMapReader->getOffset();
			$state = $nbtReader->read($legacyStateMapReader->getBuffer(), false, $offset);
			$legacyStateMapReader->setOffset($offset);
			if (!($state instanceof CompoundTag)) {
				throw new RuntimeException("Blockstate should be a TAG_Compound");
			}
			$legacyStateMap[] = new R12ToCurrentBlockMapEntry($id, $meta, $state);
		}

		/**
		 * @var int[][] $idToStatesMap string id -> int[] list of candidate state indices
		 */
		$idToStatesMap = [];
		foreach (self::$bedrockKnownStates[$protocol] as $k => $state) {
			$idToStatesMap[$state->getString("name")][] = $k;
		}
		foreach ($legacyStateMap as $pair) {
			$id = $legacyIdMap[$pair->getId()] ?? null;
			if ($id === null) {
				throw new RuntimeException("No legacy ID matches " . $pair->getId());
			}
			$data = $pair->getMeta();
			if ($data > 15) {
				//we can't handle metadata with more than 4 bits
				continue;
			}
			$mappedState = $pair->getBlockState();

			//TODO HACK: idiotic NBT compare behaviour on 3.x compares keys which are stored by values
			$mappedState->setName("");
			$mappedName = $mappedState->getString("name");
			if (!isset($idToStatesMap[$mappedName])) {
				throw new RuntimeException("Mapped new state does not appear in network table");
			}
			foreach ($idToStatesMap[$mappedName] as $k) {
				$networkState = self::$bedrockKnownStates[$protocol][$k];
				if ($mappedState->equals($networkState)) {
					self::registerMapping($k, $id, $data, $protocol);
					continue 2;
				}
			}
			throw new RuntimeException("Mapped new state does not appear in network table");
		}
	}

	private static function registerMapping(int $staticRuntimeId, int $legacyId, int $legacyMeta, $protocol): void
	{
		self::$legacyToRuntimeMap[$protocol][($legacyId << 4) | $legacyMeta] = $staticRuntimeId;
		self::$runtimeToLegacyMap[$protocol][$staticRuntimeId] = ($legacyId << 4) | $legacyMeta;
	}

	/**
	 * @return int[] [id, meta]
	 */
	public static function fromStaticRuntimeId(int $runtimeId, int $protocol): array
	{
		if ($protocol === ProtocolInfo::BEDROCK_1_18_0) {
			$protocol = ProtocolInfo::BEDROCK_1_17_40;
		}
		self::lazyInit();
		$v = self::$runtimeToLegacyMap[$protocol][$runtimeId] ?? null;
		if ($v === null) {
			return [0, 0];
		}
		return [$v >> 4, $v & 0xf];
	}

	/**
	 * @return CompoundTag[]
	 */
	public static function getBedrockKnownStates(): array
	{
		self::lazyInit();
		return self::$bedrockKnownStates;
	}
}