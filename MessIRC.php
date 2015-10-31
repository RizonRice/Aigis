<?php

class MessIRC{

// MessIRC
// Part of AigisIRC (https://github.com/Joaquin-V/AigisIRC)

protected $type = "";
protected $fullString = "";
protected $nickSelf = "";
protected $dataArray = array();

protected $raw = 0;

protected $nick = "";
protected $ident = "";
protected $host = "";

protected $inChannel = false;
protected $message = "";
protected $messageArray = array();
protected $isAction = "";
protected $isCTCP = false;
protected $CTCP = "";

protected $command = "";
protected $commandArgs = array();

protected $replyTarget = "";

// MessIRC::__construct($data, $nickSelf)
// @param string $data     String of data from an IRC server.
// @param string $nickSelf Bot's nick.
public function __construct($data, $nickSelf){
// Check if $data is a string.
if(!is_string($data))
	throw new AigisIRCException("MessIRC: Passed data is not a string.");

$this->nickSelf = $nickSelf;
$this->fullString = $data;

$dataArray = explode(" ", $data);
$this->fullArray = $dataArray;

// PING and ERROR use different syntax from everything else.
if($dataArray[0] == "PING" or $dataArray[0] == "ERROR"){
	$this->type = strtolower($dataArray[0]);
	$this->setParameters(self::stripCol(self::strSince($dataArray, 1)));
}
// If it's not PING or ERROR, continue.
else{
	// Parse source and target.
	$this->parseSource($dataArray[0]);
	$this->parseTarget($dataArray[2]);
	$this->setParameters(self::stripCol(self::strSince($dataArray, 3)));
	// Special parsing in case the type of message needs it.
	switch($dataArray[1]){
		// PRIVMSG: Could be a message, action or CTCP.
		case "PRIVMSG":
			$this->type = "privmsg";
			if(preg_match('/:\x01(\S+) ?(.*)\x01$/', $data, $match)){
				// If it's an ACTION or other CTCP.
				if($match[1] == "ACTION"){
					$this->isAction = true;
					$this->message = $match[2];
					$this->messageArray = explode(" ", $match[2]);
				}else{
					$this->type = "ctcp";
					$this->ctcp = $match[1];
				}
			}
		break;
		// NOTICE: Could be a CTCP response or a regular notice.
		case "NOTICE":
			if(preg_match('/:\x01(\S+) ?(.*)\x01$/', $data, $match)){
				$this->type = "ctcpResponse";
				$this->ctcp = $match[1];
			}else
				$this->type = "notice";
		break;
		// NICK and QUIT: Since these don't have a target, the parameters are moved one place down.
		case "NICK":
		case "QUIT":
			$this->type = strtolower($dataArray[1]);
			$this->setParameters(self::stripCol(self::strSince($dataArray, 2)));
		break;
		default:
			// Raw: These are numeric values that don't have an alphanumeric name.
			if(is_numeric($dataArray[1])){
				$this->type = "raw";
				$this->raw = intval($dataArray[1]);
			}else
				$this->type = strtolower($dataArray[1]);
		break;
	}
}

}

public function parseSource($source){
	if(preg_match('/^:([^!]+)!([^@]+)@((?:[^.]+\.?)*)/', $source, $match)){
		$this->nick  = $match[1];
		$this->ident = $match[2];
		$this->host  = $match[3];
	}else
		$this->nick = $source;
}

public function parseTarget($target){
	$this->target = self::stripCol($target);
	if(substr($this->target, 0, 1) =='#'){
		$this->inChannel = true;
		$this->replyTarget = $this->target;
	}else
		$this->replyTarget = $this->nick;
}

public function parseCommand($prefixes = array("!")){
	if(!is_array($prefixes)) throw new AigisIRCException("MessIRC: Prefixes aren't in an array.");
	foreach($prefixes as $prefix){
		if(strlen($this->message) > strlen($prefix) && substr($this->message, 0, strlen($prefix)) == $prefix){
			$this->isCommand = true;
			$parameters = explode(" ", substr($this->message, strlen($prefix)));
			$this->command = array_shift($parameters);
			$this->commandArgs = $parameters;
			return true;
		}
	}
	//PMs should be treated as commands, prefix or no prefix
	if(!$this->inChannel){
		$this->isCommand = true;
		$parameters = $this->messageArray;
		$this->command = array_shift($parameters);
		$this->commandArgs = $parameters;
		return true;
	}
	return false;
}

protected function setParameters($message){
	if(!is_string($message))
		return false;
	$this->message = $message;
	$this->messageArray = array_filter(explode(" ", $message), "strlen");
	return true;
}

public function wasMentioned(){
	return (strpos($this->message, $this->nickSelf) !== false);
}

public static function stripCol($text){
	if(substr($text, 0, 1) == ':')
		return substr($text, 1);
	return $text;
}

public static function strSince($array, $since){
	if(is_string($array))
		$array = explode(" ", $array);
	if(!isset($array[$since]))
		return "";
	return implode(" ", array_slice($array, $since));
}

public function getType(){
	return $this->type;
}

public function getString(){
	return $this->fullString;
}

public function getArguments(){
	return $this->commandArgs;
}

public function getRaw(){
	return $this->raw;
}

public function getNick(){
	return $this->nick;
}

public function getIdent(){
	return $this->ident;
}

public function getHostmask(){
	return $this->host;
}

public function inChannel(){
	return $this->inChannel;
}

public function getMessage(){
	return $this->message;
}

public function isAction(){
	return $this->isAction;
}

public function getCTCP(){
	if($this->isCTCP)
		return $this->CTCP;
	return false;
}

public function getReplyTarget(){
	return $this->replyTarget;
}

public function command(){
	return $this->command;
}

public function commandArgs(){
	return $this->commandArgs;
}

public function getParams(){
	return $this->messageArray;
}

public function getFullMsg(){
	return $this->fullString;
}

public function getArrayMsg(){
	return $this->fullArray;
}

public function requireArguments($amount){
	if(count($this->commandArgs) < $amount)
		throw new Exception("Insufficient arguments.");
	return $this->commandArgs;
}

}
