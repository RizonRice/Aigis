<?php

class UserIRC_User{

const MAX_MESSAGE_STORAGE = 2500;

private $nick = "";
private $ident = "";
private $host = "";

private $msgs = array();

private $channels = array();
private $chanmodes = array();

private $loggedin = false;
private $userid = 0;
private $username = "";

private $nodes = array();

public function __construct($address){
if(preg_match("/(.*)!(.*)@(.*)/", $address, $matches)){
	$this->nick = $matches[1];
	$this->ident = $matches[2];
	$this->host = $matches[3];
}else
	$this->nick = $address;
}

public function addMessage(MessIRC $message){
	$channel = $message->getReplyTarget();
	if(!isset($this->msgs[$channel]))
		$this->msgs[$channel] = array();

	array_unshift($this->msgs[$channel], $message);
	$this->msgs[$channel] = array_slice($this->msgs[$channel], 0, self::MAX_MESSAGE_STORAGE);
	$this->updateHost($message);
}

public function getMessage($channel, $number){
	if(!isset($this->msgs[$channel][$number]))
		return null;

	return $this->msgs[$channel][$number];
}

public function getMessageCount($channel){
	if(isset($this->msgs[$channel]))
		return count($this->msgs[$channel]);
	else return null;
}

public function getNick(){
	return $this->nick;
}

public function getFullHost(){
	return $this->ident."@".$this->host;
}

public function setNick($nick){
	$this->nick = $nick;
}

public function setLogin($userid, $username){
	if(!is_numeric($userid) || !is_string($username))
		throw new Exception("Given values are not the correct type.");

	$this->loggedin = true;
	$this->userid   = (int) $userid;
	$this->username = $username;
}

public function logout(){
	$this->loggedin = false;
	$this->userid = 0;
	$this->username = "";
}

public function getNode($node, $ignoreWildcard = false){
	$nodes = $this->nodes;

	if(isset($nodes[$node]))
		return $nodes[$node];

	if($ignoreWildcard)
		return null;

	$nodeGroups = explode('.', $node);
	unset($nodeGroups[count($nodeGroups) - 1]);
	while(count($nodeGroups) != 0){
		$nodePointer = implode('.', $nodeGroups);
		if(isset($nodes["$nodePointer.*"]))
			return $nodes["$nodePointer.*"];
		unset($nodeGroups[count($nodeGroups) - 1]);
	}
	if(isset($nodes['*']))
		return $nodes['*'];

	return null;
}

public function setNode($node, $setting){
	$this->nodes[$node] = $setting;
}

public function getUserID(){
	return $this->userid;
}

public function destroyNodeTree(){
	$this->nodes = array();
}

public function getUsername(){
	if($this->username == "")
		return $this->nick;
	return $this->username;
}

public function loginStatus(){
	return $this->loggedin;
}

public function addChan($channel){
	$this->channels[$channel] = $channel;
	$this->chanmodes[$channel] = array();
}

public function rmChan($channel){
	if(isset($this->channels[$channel])){
		unset($this->channels[$channel]);
		unset($this->chanmodes[$channel]);
	}
}

public function getChans(){
	return $this->channels;
}

public function mode($channel, $mode){
	if(preg_match_all('/([+-])([qaohv]+)/', $mode, $regex, PREG_PATTERN_ORDER)){
		$letterPointer = 0;
		for($modePointer = 0; isset($regex[0][$modePointer]); $modePointer++){
			$modeSymbol = $regex[1][$modePointer];
			$modeLetters= $regex[2][$modePointer];
			$letterArray= str_split($modeLetters);
			foreach($letterArray as $letter){
				if($modeSymbol == "+")
					$this->chanmodes[$channel][] = $letterArray;
				else{
					if(($chanmodeKey = array_search($letterArray, $this->chanmodes[$channel])) !== false)
						unset($this->chanmodes[$channel][$chanmodeKey]);
				}
				$letterPointer++;
			}
		}
	}
	return false;
}

public function getModes($channel){
	if(!isset($this->chanmodes[$channel]))
		return array();
	return $this->chanmodes[$channel];
}

public function updateHost(MessIRC $MessIRC){
	$this->nick  = $MessIRC->getNick();
	$this->ident = $MessIRC->getIdent();
	$this->host  = $MessIRC->getHostmask();
}

}
