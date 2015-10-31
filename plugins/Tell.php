<?php

class Tell extends PlugIRC_Core{

const PLUGIN_NAME = "Tell";
const PLUGIN_DESC = "Stores messages and sends them when the recipient joins a channel or says something.";
const PLUGIN_VERSION = "1.0";

private $messages = array();

public function __construct(AigisIRC $AigisIRC){
parent::__construct($AigisIRC);

$this->triggers = array(
"tell" => "addMessage"
);
}

public function privmsg(MessIRC $MessIRC){
	parent::privmsg($MessIRC);

	$nick = strtolower($MessIRC->getNick());
	if(isset($this->messages[$nick]))
		$this->sendMessage($MessIRC, $nick);
}

public function join(MessIRC $MessIRC){
	$nick = strtolower($MessIRC->getNick());
	if(isset($this->messages[$nick]))
		$this->sendMessage($MessIRC, $nick);
}

public function addMessage(MessIRC $MessIRC){
	$this->PlugIRC->requirePermission($MessIRC, "tell.SEND");
	$args = $MessIRC->requireArguments(2);
	$sendTo = strtolower($args[0]);
	$msg = MessIRC::strSince($args, 1);
	$sender = $MessIRC->getNick();
	$this->messages[$sendTo][] = array(
		"sender" => $sender,
		"message"=> $msg
	);
	$this->ConnIRC->msg($MessIRC->getReplyTarget(), "Message stored.");
}

public function sendMessage(MessIRC $MessIRC, $nick){
	foreach($this->messages[$nick] as $message){
		$sender = $message["sender"];
		$msg = $message["message"];
		$reply = "Message from $sender: $msg";
		$this->ConnIRC->msg($nick, $reply);
	}
	$this->messages[$nick] = array();
}

}