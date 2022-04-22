<?php

declare(strict_types=1);

namespace EskoTeam\network;

use EskoTeam\network\translator\Translator;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\PlayerNetworkSessionAdapter;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\timings\Timings;
use function base64_encode;
use function bin2hex;
use function strlen;
use function substr;

class PlayerSessionAdapter extends PlayerNetworkSessionAdapter
{

	/** @var int */
	protected $protocol;
	/** @var Player */
	protected $player;

	public function __construct(Server $server, Player $player, int $protocol)
	{
		parent::__construct($server, $player);
		$this->player = $player;
		$this->protocol = $protocol;
	}

	public function handleDataPacket(DataPacket $packet)
	{
		if ($packet instanceof BatchPacket) {
			$packet->decode();
			foreach ($packet->getPackets() as $buf) {
				$pk = PacketPool::getPacket($buf);
				$pk->isEncoded = true;
				$ret = Translator::fromClient($pk, $this->protocol, $this->player);
				$this->hock($ret);
			}
		} else {
			$packet->isEncoded = true;
			$this->hock(Translator::fromClient($packet, $this->protocol, $this->player));
		}
	}

	private function hock(DataPacket $packet)
	{
		if (!$this->player->isConnected()) {
			return;
		}

		$timings = Timings::getReceiveDataPacketTimings($packet);
		$timings->startTiming();

		if ($packet->isEncoded) {
			$packet->decode();
		}

		if (!$packet->feof() and !$packet->mayHaveUnreadBytes()) {
			$remains = substr($packet->buffer, $packet->offset);
			Server::getInstance()->getLogger()->debug("Still " . strlen($remains) . " bytes unread in " . $packet->getName() . ": 0x" . bin2hex($remains));
		}

		$ev = new DataPacketReceiveEvent($this->player, $packet);
		$ev->call();
		if (!$ev->isCancelled() and !$packet->handle($this)) {
			Server::getInstance()->getLogger()->debug("Unhandled " . $packet->getName() . " received from " . $this->player->getName() . ": " . base64_encode($packet->buffer));
		}

		$timings->stopTiming();
	}
}