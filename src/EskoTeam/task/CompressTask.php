<?php

declare(strict_types=1);

namespace EskoTeam\task;

use EskoTeam\MultiEsko;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use function chr;
use function zlib_encode;

class CompressTask extends AsyncTask
{

	/** @var string */
	private $payload;

	/** @var bool */
	private $fail = false;

	/** @var int */
	private $level;

	public function __construct(BatchPacket $packet, callable $callback)
	{
		$packet->reset();
		$this->payload = $packet->payload;
		$this->storeLocal([$packet, $callback]);
		$this->level = Server::getInstance()->networkCompressionLevel;
	}

	public function onRun()
	{
		try {
			$this->setResult(zlib_encode($this->payload, ZLIB_ENCODING_RAW, $this->level));
		} catch (\Exception $e) {
			$this->fail = true;
		}
	}

	public function onCompletion(Server $server)
	{
		if ($this->fail) {
			MultiEsko::getInstance()->getLogger()->error("Failed to compress batch packet");
			return;
		}
		[$packet, $callback] = $this->fetchLocal();
		/** @var BatchPacket $packet */
		$packet->isEncoded = true;
		$packet->buffer .= chr($packet->pid());
		$packet->buffer .= $this->getResult();
		$callback($packet);
	}
}