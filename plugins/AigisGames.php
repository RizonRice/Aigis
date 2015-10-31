<?php

class AigisGames extends PlugIRC_Core{

const PLUGIN_NAME = "Fun & Games";
protected $requireConfig = true;

public function __construct(AigisIRC $AigisIRC){
parent::__construct($AigisIRC);

$this->triggers = array(
"dice" => "dice",
"roll" => "dice",
"coin" => "coinFlip",
"flip" => "coinFlip"
);
}

public function dice(MessIRC $MessIRC){
	$this->PlugIRC->requirePermission($MessIRC, "games.DICE");
	$args = $MessIRC->requireArguments(1);

	if(isset($this->configFile['preset'][$args[0]]))
		$args[0] = $this->configFile['preset'][$args[0]];
	if(preg_match('/([0-9]+)d([0-9]+)/', $args[0], $dice)){
		$amount = $dice[1];
		$sides  = $dice[2];
	}else throw new Exception("Bad syntax: ".$args[0]);

	if($amount > 100)
		throw new Exception("Woah there, that's a lot of dice.");
	if($sides > getrandmax())
		throw new Exception("What kind of dice has that many sides?");
	$dicePointer = 0;
	$diceTotal = 0;
	$rolls = array();
	while($dicePointer < $amount){
		$roll = rand(1, $sides);
		$rolls[] = $roll;
		$diceTotal = $diceTotal + $roll;
		$dicePointer++;
	}
	$reply = "[{$args[0]}] ".implode(" ", $rolls);
	if($amount > 1) $reply .= " [Total: $diceTotal]";
	$this->ConnIRC->msg($MessIRC->getReplyTarget(), $reply);
}

public function coinFlip(MessIRC $MessIRC){
	$this->PlugIRC->requirePermission($MessIRC, "games.COIN");
	$args = $MessIRC->getArguments();
	if(isset($args[0]) && is_numeric($args[0]) && $args[0] <= 1000000) $coins = $args[0];
	else $coins = 1;

	$coinPointer = 1;
	$heads = 0;
	$tails = 0;
	while($coinPointer <= $coins){
		$flip = rand(0,1);
		if($flip == 0) $heads++;
		else           $tails++;
		$coinPointer++;
	}
	$reply = "Heads: $heads | Tails: $tails";
	$this->ConnIRC->msg($MessIRC->getReplyTarget(), $reply);
}

}
