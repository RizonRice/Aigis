<?php

require_once "MessIRC.php";


class MessIRCManager{

private $AigisIRC;
private $SelfNick;
private $lastMessage;

public function __construct(AigisIRC $AigisIRC, $SelfNick){
	$this->AigisIRC = $AigisIRC;
	$this->SelfNick = $SelfNick;
}

public function getMessage($data){
	$MessIRC = new MessIRC($data, $this->SelfNick);
	$this->lastMessage = $MessIRC;
	return $MessIRC;
}

public function lastMessage(){
	return $this->lastMessage;
}

}
