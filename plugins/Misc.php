<?php

class Misc extends PlugIRC_Core{

const PLUGIN_NAME = "Misc.";
const PLUGIN_DESC = "Miscellaneous functions.";

public function __construct(AigisIRC $AigisIRC){
parent::__construct($AigisIRC);


$this->triggers = array(
"bots"     => "bots",
"halp"     => "needHalp",
"uptime"   => "uptime",
"source"   => "source",
"decide"   => "decide"
);
$this->PlugIRC->setDefaultPerms(array(
	"misc.PLUGINS.LOAD" => false,
	"misc.PLUGINS.UNLOAD" => false));
}

public function bots(MessIRC $MessIRC){
	$this->PlugIRC->requirePermission($MessIRC, "misc.BOTS");
	$this->ConnIRC->msg($MessIRC->getReplyTarget(), "Reporting in! [PHP] Type \"?help\" for help.");
}

public function needHalp(MessIRC $MessIRC){
	$this->ConnIRC->msg($MessIRC->getReplyTarget(), "https://www.youtube.com/watch?v=U5fJi2fJIsA");
}

public static function secondsToReadable($time = 0){
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
	$this->ConnIRC->msg($MessIRC->getReplyTarget(), "Source: ".AigisIRC::AIGISIRC_GITHUB);
}

public function decide(MessIRC $MessIRC){
	$choice = implode(" ", $MessIRC->requireArguments(1));

	$arr = array();
	// Separated by pipes.
	if(preg_match('/\s\|\s/', $choice))
		$arr = explode('|', $choice);
	// Separated by commas.
	elseif(preg_match('/\S,\s/', $choice))
		$arr = explode(',', $choice);
	// Separated by "or."
	elseif(preg_match('/\sor\s/', $choice))
		$arr = explode(' or ', $choice);
	else
		$arr = array('Yes.', 'No.');

	$key = array_rand($arr);
	$decided = trim($arr[$key]);

	$this->ConnIRC->msg($MessIRC->getReplyTarget(), $MessIRC->getNick().": $decided");
}

}
