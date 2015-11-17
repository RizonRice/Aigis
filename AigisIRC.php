<?php

// AigisIRC
// GitHub: https://github.com/Joaquin-V/AigisIRC

// Create some constants.

// Aigis configuration directory in home.
define('AIGIS_HOME', getenv('HOME').'/.config/aigis');
if(!file_exists(AIGIS_HOME))
	mkdir(AIGIS_HOME, 0755, true);
if(!is_dir(AIGIS_HOME)){
	unlink(AIGIS_HOME);
	mkdir(AIGIS_HOME, 0755, true);
}

// usr directory for random files like AigisURL/TextDB databases.
define('AIGIS_USR', AIGIS_HOME.'/usr');
if(!file_exists(AIGIS_USR))
	mkdir(AIGIS_USR, 0755, true);

// Home directory for plugins.
define('AIGIS_HOMEPLG', AIGIS_HOME.'/plugins');
if(!file_exists(AIGIS_HOMEPLG))
	mkdir(AIGIS_HOMEPLG, 0755, true);

// plugins/config for plugin configuration files.
define('PLUGIRC_CONFIG',  'plugins/config');
define('PLUGIRC_HOMECFG', AIGIS_HOMEPLG.'/config');
if(!file_exists(PLUGIRC_HOMECFG))
	mkdir(PLUGIRC_HOMECFG, 0755, true);



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
const AIGISIRC_GITHUB = "https://github.com/MakotoYuki/AigisIRC/";
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

		consoleSend($sockread, "ConnIRC", "info"); /* Uncomment to see the world through bot eyes. */

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

	// Ping timeouts.
	$time = time();
	if($time - $this->lastMsg >= ConnIRC::ACTIVITY_TIMEOUT && $time - $this->lastConn >= ConnIRC::RECONNECT_DELAY){
		consoleSend("Disconnected for ping timeout.","ConnIRC","warning");
		$this->ConnIRC->connect();
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

public static function getConfig($network){
	if(!file_exists(AIGIS_HOME."/aigis.conf")){

		if(file_exists("config/aigis.conf"))
			$configF = parse_ini_file("config/aigis.conf", true);
		else throw new Exception("Config file not found.");

	}else $configF = parse_ini_file(AIGIS_HOME."/aigis.conf", true);
	if(!isset($configF[$network]))
		throw new Exception("Unknown network: $network");
	$config = array_merge($configF['Global'], $configF[$network]);
	$config['network'] = $network;
	return $config;
}

}
