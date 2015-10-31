<?php

class UserIRC_Channel{

const MAX_MESSAGE_STORAGE = 2500;

private $channel = "";
private $modes = "";

private $msgs = array();
private $nicklist = array();

private $nodes = array();

public function __construct($channel){
	$this->channel = $channel;
}

public function addMessage(MessIRC $message){
        array_unshift($this->msgs, $message);
        $this->msgs = array_slice($this->msgs, 0, self::MAX_MESSAGE_STORAGE);
}

public function getMessage($number = 0){
	return $this->msgs[$number];
}

public function getMessageCount(){
	return count($this->msgs);
}

public function getAllMessages(){
	return $this->msgs[$channel];
}

public function join($nick){
	$this->nicklist[] = $nick;
}

public function part($nick){
	$nickKey = array_search($nick, $this->nicklist);
	if($nickKey !== false)
		unset($this->nicklist[$nickKey]);
}

public function nicklist(){
	return $this->nicklist;
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

}
