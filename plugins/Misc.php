<?php

class Misc extends PlugIRC_Core{

const PLUGIN_NAME = "Misc.";
const PLUGIN_DESC = "Miscellaneous functions.";

public function __construct(AigisIRC $AigisIRC){
parent::__construct($AigisIRC);


$this->triggers = array(
"bots"     => "bots",
"seabears" => "seabearCircle",
"plugin"   => "showPlugins",
"halp"     => "needHalp",
"uptime"   => "uptime",
"source"   => "source",
"florida"  => "Florida"
);
$this->PlugIRC->setDefaultPerms(array(
	"misc.PLUGINS.LOAD" => false,
	"misc.PLUGINS.UNLOAD" => false));
}

public function bots(MessIRC $MessIRC){
	$this->PlugIRC->requirePermission($MessIRC, "misc.BOTS");
	$this->ConnIRC->msg($MessIRC->getReplyTarget(), "Reporting in! [PHP] Type \"?help\" for help.");
}

public function seabearCircle(MessIRC $MessIRC){
	if(!$MessIRC->inChannel())
		throw new Exception("PMs are already a circle, don't worry.");
	$chan = $this->UserIRC->getChannel($MessIRC->getReplyTarget());
	$nicks = $chan->nicklist();
	$chosenNick = $nicks[array_rand($nicks)];

	$this->ConnIRC->msg($MessIRC->getReplyTarget(), "gets near $chosenNick and draws a circle around both of them on the ground.", "ACTION");
}

public function needHalp(MessIRC $MessIRC){
	$this->ConnIRC->msg($MessIRC->getReplyTarget(), "https://www.youtube.com/watch?v=U5fJi2fJIsA");
}

public function secondsToReadable($time = 0){
	$weeks = floor($time/604800);
	$time  = $time - ($weeks * 604800);

	$days  = floor($time/86400);
	$time  = $time - ($days * 86400);

	$hours = floor($time/3600);
	$time  = $time - ($hours * 3600);

	$mins  = floor($time/60);
	$time  = $time - ($mins * 60);

	$secs  = $time;

	return "{$weeks}wk {$days}day {$hours}hr {$mins}min {$secs}sec";
}


public function uptime(MessIRC $MessIRC){
	$start = $this->AigisIRC->getAigisVar("startTime");
	$diff = time() - $start;
	$uptime = self::secondsToReadable($diff);
	$this->ConnIRC->msg($MessIRC->getReplyTarget(), "Current uptime: $uptime");
}

public function source(MessIRC $MessIRC){
	$this->ConnIRC->msg($MessIRC->getReplyTarget(), AigisIRC::AIGISIRC_GITHUB);
}

public function Florida(MessIRC $MessIRC){
	$this->PlugIRC->requirePermission($MessIRC, "misc.FLORIDAMAN");

	$reddit = curl::getJson("https://www.reddit.com/r/FloridaMan.json");
	if(!is_array($reddit) OR !isset($reddit['data']['children']))
		throw new Exception("Error parsing /r/floridaman.");
	$posts = $reddit['data']['children'];
	$id = array_rand($posts);
	if(!isset($posts[$id]['data']['title']))
		throw new Exception("Error parsing /r/floridaman.");
	$adventure = $posts[$id]['data']['title'];
	$this->ConnIRC->msg($MessIRC->getReplyTarget(), "/r/FloridaMan - $adventure");
}
}
