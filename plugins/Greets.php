<?php

class Greets extends PlugIRC_Core{

const PLUGIN_NAME = "Greets";
const PLUGIN_DESC = "Greet manager for AigisIRC.";

const DEFAULT_FORMAT = "[_NICK_] _GREET_";
private $greets = array();
private $format = array();

private $AigisDB = null;

public function __construct(AigisIRC $AigisIRC){
	$this->requireConfig = true;
	parent::__construct($AigisIRC);
	$this->AigisDB = $this->PlugIRC->requirePlugin("AigisDB");

	$this->updateGreets();
	
	$this->triggers = array(
	"greet" => "greetManager",
	"gr"    => "greetManager"
	);
	$this->PlugIRC->setDefaultPerms(array("greets.FSET" => false));
}

public function join(MessIRC $MessIRC){
	$nick = $MessIRC->getNick();
	if(!isset($this->greets[$nick]))
		return;

	try{
		$this->PlugIRC->requirePermission($MessIRC, "greets");
	}catch(Exception $e){
		return;
	}

	$greet = $this->getGreet($nick);
	$this->ConnIRC->msg($MessIRC->getReplyTarget(), $this->formatGreet($nick));
}

public function greetManager(MessIRC $MessIRC){
	$this->PlugIRC->requirePermission($MessIRC, "greets");
	$args = $MessIRC->requireArguments(1);
	$nick = $MessIRC->getNick();
	switch($args[0]){
		case "set":
			$args = $MessIRC->requireArguments(2);
			$greet = MessIRC::strSince($args, 1);
			if($this->setGreet($nick, $greet))
				$this->ConnIRC->msg($MessIRC->getReplyTarget(), "Your greet is now \"$greet\"");
			else
				$this->ConnIRC->msg($MessIRC->getReplyTarget(), "An error occured.");
		break;

		case "remove":
		case "rm":
		case "delete":
		case "del":
			$this->removeGreet($nick);
			$this->ConnIRC->msg($MessIRC->getReplyTarget(), "Greet removed.");
		break;

		case "display":
		case "show":
			if(isset($args[1])) $nick = $args[1];
			if($greet = $this->getGreet($nick))
				$this->ConnIRC->msg($MessIRC->getReplyTarget(), $this->formatGreet($nick));
			else throw new Exception("$nick doesn't have a greet set.");
		break;

		case "fset":
			$this->PlugIRC->requirePermission($MessIRC, "greets.FSET");
			$args = $MessIRC->requireArguments(3);
			$nick = $args[1];
			$greet = MessIRC::strSince($args, 2);
			if($this->setGreet($nick, $greet))
				$this->ConnIRC->msg($MessIRC->getReplyTarget(), "[$nick] $greet");
			else
				$this->ConnIRC->msg($MessIRC->getReplyTarget(), "An error occured.");
		break;

		case "fdel":
			$this->PlugIRC->requirePermission($MessIRC, "greets.FSET");
			$args = $MessIRC->requireArguments(2);
			$nick = $args[1];
			$this->removeGreet($nick);
			$this->ConnIRC->msg($MessIRC->getReplyTarget(), "Greet removed.");
		break;

		default:
			throw new Exception("Unknown argument: ".$args[0]);
		break;
	}
}

public function getGreet($nick){
	if(isset($this->greets[$nick]))
		return $this->greets[$nick];
	return null;
}

public function setGreet($nick, $greet){
	$network = $this->ConnIRC->getNetwork();
	$query = isset($this->greets[$nick]) ? "UPDATE greets SET greet=? WHERE nick=? AND network =?;" : "INSERT INTO greets (greet,nick,network) VALUES(?,?,?);";

	if($stmt = $this->AigisDB->prepare($query)){
		$stmt->bind_param("sss", $greet, $nick, $network);
		$stmt->execute();
		$stmt->close();
		$this->greets[$nick] = $greet;
		return true;
	}else
		return false;
}

public function removeGreet($nick){
	if(isset($this->greets[$nick]))
		unset($this->greets[$nick]);
	if($stmt = $this->AigisDB->prepare("DELETE FROM greets WHERE nick=?;")){
		$stmt->bind_param("s", $nick);
		$stmt->execute();
		$stmt->close();
		return true;
	}else return false;
}

public function updateGreets(){
	$network = $this->ConnIRC->getNetwork();
	if($query = $this->AigisDB->query("SELECT * FROM greets WHERE network='$network';")){
		$greets = $query->fetch_all(MYSQLI_ASSOC);
		foreach($greets as $greet){
			$nick = $greet['nick'];
			$this->greets[$nick] = $greet['greet'];
			$this->format[$nick] = $greet['format'];
		}
	}else
		throw new Exception("Error fetching greets from database!");
}

public function formatGreet($nick){
	$formatName = $this->format[$nick];
	$format = self::DEFAULT_FORMAT;
	if(isset($this->configFile['format'][$formatName]))
		$format = $this->configFile['format'][$formatName];
	$return = str_replace(array("_NICK_","_GREET_"), array($nick, $this->greets[$nick]), $format);
	return $return;
}

}
