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
"up"       => "uptime",
"fortune"  => "fortuneCookie",
"google"   => "googleIt",
"g"        => "googleIt",
"decide"   => "decide"
);
$this->PlugIRC->setDefaultPerms(array("misc.PLUGINS_LOAD" => false, "misc.PLUGINS_UNLOAD" => false));
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

public function showPlugins(MessIRC $MessIRC){
	$args = $MessIRC->getArguments();
	$loaded = $this->PlugIRC->getAllPlugins(true);
	$plugins = array();

	foreach(scandir("plugins") as $filename){
		if(strrchr($filename, ".") == ".php")
			$plugins[] = substr($filename, 0, strlen($filename) - 4);
	}

	if(count($args) == 0){
		$this->PlugIRC->requirePermission($MessIRC, "misc.PLUGINS.LIST");

		foreach($plugins as $key => $plugin){
			if(in_array($plugin, $loaded, true))
				continue;
			else
				$plugins[$key] = FontIRC::colour($plugin, 4);
		}

		$reply = "Plugins: ".implode(", ", $plugins);
		$this->ConnIRC->msg($MessIRC->getReplyTarget(), $reply);
		return;
	}
	switch($args[0]){
		case "load":
		$args = $MessIRC->requireArguments(2);
		$this->PlugIRC->requirePermission($MessIRC, "misc.PLUGINS.LOAD");

		$this->PlugIRC->loadPlugin($args[1]);
		$this->ConnIRC->msg($MessIRC->getReplyTarget(), "Plugin loaded.");
		break;

		case "unload":
		$args = $MessIRC->requireArguments(2);
		$this->PlugIRC->requirePermission($MessIRC, "misc.PLUGINS.LOAD");

		$this->PlugIRC->unloadPlugin($args[1]);
		$this->ConnIRC->msg($MessIRC->getReplyTarget(), "Plugin unloaded.");
		break;

		default:
		$args = $MessIRC->requireArguments(1);
		$this->PlugIRC->requirePermission($MessIRC, "misc.PLUGINS.INFO");
		if(!class_exists($args[0]) && !is_subclass_of($args[0], "PlugIRC_Core"))
			throw new Exception("That plugin does not exist.");
		$className = $args[0];

		$reply1 = $className::PLUGIN_NAME . " - " . $className::PLUGIN_DESC;
		$reply2 = "Status: ". (in_array($className, $loaded, true) ? "Loaded." : "Unloaded.") . " | Version: ".$className::PLUGIN_VERSION;
		$this->ConnIRC->msg($MessIRC->getReplyTarget(), $reply1."\n".$reply2);
	}
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

public function fortuneCookie(MessIRC $MessIRC){
	$this->PlugIRC->requirePermission($MessIRC, "misc.FORTUNE");
	exec("fortune -s", $fortune);
	$unlined = implode(" ", $fortune);
	$this->ConnIRC->msg($MessIRC->getReplyTarget(), $unlined);
}

public function googleIt(MessIRC $MessIRC){
	$this->PlugIRC->requirePermission($MessIRC, "misc.GOOGLE");

	$query = $MessIRC->requireArguments(1);
	$query = implode(" ", $query);
	$query = str_replace(array("+", " "), array("%2B", "+"), $query);
	$link  = "http://lmgtfy.com/?q=$query";

	$this->ConnIRC->msg($MessIRC->getReplyTarget(), $link);
}

public function decide(MessIRC $MessIRC){
	$this->PlugIRC->requirePermission($MessIRC, "misc.DECIDE");
	$query = $MessIRC->requireArguments(2);
	$decide = implode(" ", $query);

	if(in_array($query, "|")){
		$choices = explode(" | ", $decide);
	}elseif(in_array($query, "or")){
		$choices = explode(" or ", $decide);
	}elseif(in_array($query, ",")){
		$choices = explode(", ", $decide);
	}else
		$choices = array("Yes.", "No.");

	$decision = array_rand($choices);

	$this->ConnIRC->msg($MessIRC->getReplyTarget(), $decision);
}

}
