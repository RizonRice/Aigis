<?php

// AigisIRC
// GitHub: https://github.com/Joaquin-V/AigisIRC

// Require all of the modules.
require_once "ConnIRC.php";
require_once "UserIRC.php";
require_once "MessIRCManager.php";
require_once "FontIRC.php";
require_once "PlugIRC.php";

class AigisIRCException extends Exception {}

class AigisIRC{

// Version constants.
const AIGISIRC_VERSION = '1.00';
const AIGISIRC_VERNAME = '1.0 Pallas Athena';
const AIGISIRC_GITHUB = "https://github.com/Joaquin-V/AigisIRC";
// IRC modules.
private $ConnIRC		= null;
private $PlugIRC		= null;
private $MessIRCManager		= null;
private $UserIRC		= null;
private $LangIRC		= null;
// Bot information.
private $botNick		= "";
private $altNick		= "";
private $nsPass			= null;
// Misc. information.
private $autoJoin		= array();
private $ajList			= array();
const AUTOJOIN_DELAY		= 7;
private $startTime		= 0;
private $lastMsg		= 0;
private $lastConn		= null;
private $lastRegg		= null;

public function __construct($botNick, $networkName, $altNick = "LunarBot"){
	// Pass variables to the private object vars.
	$this->botNick = $botNick;
	$this->altNick = $altNick;
	$this->network = $networkName;
	// Start UserIRC and MessIRCManager
	$this->MessIRCManager	= new MessIRCManager($this, $botNick);
	$this->UserIRC		= new UserIRC($this, $botNick);
	$this->PlugIRC		= new PlugIRC($this);

	$this->startTime = time();
}

public function setConnInfo($host, $port = 6667){
	$this->ConnIRC = new ConnIRC($this, $this->network, $this->botNick, $host, $port);
	return $this->ConnIRC;
}

public function autoJoin($ajList = null){
	if(is_null($ajList)){
		foreach($this->autoJoin as $chan){ $this->ConnIRC->join($chan); }
		$this->autoJoin = array();
		return;
	}elseif(is_array($ajList)){
		foreach($ajList as $chan){
			$this->autoJoin[] = $chan;
		}
	}elseif(is_string($ajList))
		$this->autoJoin[] = $ajList;
}

public function nsIdentify(){
	if(isset($this->nsPass))
		$this->ConnIRC->send("PRIVMSG NickServ :IDENTIFY " . $this->nsPass);
}

// Loop cycle.
public function loopCycle(){
	$lastPing = time();
	if(!$this->ConnIRC->connected())
		$this->ConnIRC->connect();
	if($sockread = $this->ConnIRC->read()){
		$this->lastMsg = time();
		$MessIRC = $this->MessIRCManager->getMessage($sockread);

//		consoleSend($sockread, "ConnIRC", "info"); /* Uncomment to see the world through bot eyes. */

		// Tell ConnIRC to parse RAW input.
		if($MessIRC->getType() == "raw")
			$this->ConnIRC->parseRaw($MessIRC);

		// Ping-pong
		if( $MessIRC->getType() == "ping")
			$this->ConnIRC->send("PONG " . $MessIRC->getMessage());

		$type = $MessIRC->getType();

		if(method_exists($this->UserIRC, $type))
			$this->UserIRC->$type($MessIRC);

		try{
			$this->PlugIRC->pluginSendAll($type, $MessIRC);
		}catch(Exception $e){
			if(get_class($e) == "NoticeException")
				return;
			else
				consoleSend($e->getMessage(), "PlugIRC", "warning");
		}
	}

	// Auto-join
	if(!is_null($this->lastRegg) && time() - $this->lastRegg >= self::AUTOJOIN_DELAY && count($this->autoJoin) !== 0){
		$this->autoJoin();
	}

	// Ping timeouts.
	$time = time();
	if($time - $this->lastMsg >= ConnIRC::ACTIVITY_TIMEOUT && $time - $this->lastConn >= ConnIRC::RECONNECT_DELAY){
		consoleSend("Disconnected.","ConnIRC","warning");
		$this->ConnIRC->connect();
		$channels = $this->UserIRC->getSelf()->getChans();
		$this->autoJoin($channels);
	}
}

// Returns a variable in the object.
public function getAigisVar($varname){
	if(isset($this->$varname))
		return $this->$varname;
	return null;
}

// Sets an object's variable.
public function setAigisVar($varname, $value){
	$this->$varname = $value;
}

}
