<?php

declare(strict_types=1);

namespace EskoTeam\misc;

use pocketmine\Player;

class Session
{
	public function __construct(
		public Player $player,
		public int    $protocol
	)
	{
	}
}