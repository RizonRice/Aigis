<?php

class PlugIRC_Core{

const PLUGIN_NAME = "Untitled.";
const PLUGIN_VERSION = '1.0';
const PLUGIN_DESC = "No description set.";

// Added for Help plugin.

protected $requireConfig = false;

protected $AigisIRC;
protected $ConnIRC;
protected $PlugIRC;
protected $UserIRC = null;

protected $triggers = array();
protected $prefixes = array();

protected $configFile = array();

public function __construct(AigisIRC $AigisIRC){
	$this->AigisIRC = $AigisIRC;
	$this->ConnIRC  = $AigisIRC->getAigisVar("ConnIRC");
	$this->PlugIRC  = $AigisIRC->getAigisVar("PlugIRC");
	$this->UserIRC  = $AigisIRC->getAigisVar("UserIRC");
	$this->prefixes = $this->PlugIRC->getDefaultPrefixes();
	$this->loadConfig();
}

public function triggerParse(MessIRC $MessIRC){
	if(!$MessIRC->parseCommand($this->prefixes)) return;

	$triggers = $this->triggers;
	$command = strtolower($MessIRC->command());
	if($help = $this->PlugIRC->getPlugin("Help")){
		$full = $help->getFullCommand($command);
		if($this->PlugIRC->getPermission($MessIRC, "command.$full") !== 2)
			return;
	}
	if(isset($triggers[$command]) and method_exists($this, $triggers[$command])){
		try{
			call_user_func(array($this, $triggers[$command]), $MessIRC);
		}catch(Exception $e){
			$this->exceptionParse($e, $MessIRC);
		}
	}
}

public function exceptionParse(Exception $e, MessIRC $MessIRC){
	$reply = $e->getMessage();

	if(preg_match('/^([^:]+)::([^:]+): (.+)/', $reply, $match))
		$reply = $match[3];

	if($reply == "")
		return;

	if(get_class($e) == "NoticeException")
		$this->ConnIRC->notice($MessIRC->getNick(), $reply);
	else{
		if($MessIRC->inChannel())
			$reply = $MessIRC->getNick() . ": " . $reply;
		$this->ConnIRC->msg($MessIRC->getReplyTarget(), $reply);
	}
}

protected function loadConfig(){
	// ~/.config file
	$confile = PLUGIRC_HOMECFG.'/'.get_class($this).'.ini';
	if(file_exists($confile))
		$this->configFile = parse_ini_file($confile, true);

	// Hardcoded config file.
	elseif(file_exists(PLUGIRC_CONFIG.'/'.get_class($this).'.ini'))
		$this->configFile = parse_ini_file(PLUGIRC_HOMECFG.'/'.get_class($this).'.ini', true);

	// Throw exception if config file required.
	elseif($this->requireConfig == TRUE)
		throw new Exception("Config file is needed!");
}

public function getPrefixes(){
	return $this->prefixes;
}

public function consoleSend($message, $type = 'info'){
	consoleSend(get_class($this) . ": " . $message, "PlugIRC", $type);
}

public function privmsg(MessIRC $MessIRC){
	$this->triggerParse($MessIRC);
}

public function raw(MessIRC $MessIRC) {}

public function ctcp(MessIRC $MessIRC) {}
public function notice(MessIRC $MessIRC) {}

public function error(MessIRC $MessIRC) {}
public function ping(MessIRC $MessIRC) {}

public function nick(MessIRC $MessIRC) {}
public function quit(MessIRC $MessIRC) {}
public function join(MessIRC $MessIRC) {}
public function part(MessIRC $MessIRC) {}

}
