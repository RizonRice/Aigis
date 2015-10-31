<?php

class Admin extends PlugIRC_Core{

const DEFAULT_PART_MSG = "Memento Mori";
const MESSAGES_FILE = "plugins/etc/quitMessages.txt";

public function __construct(AigisIRC $AigisIRC){
parent::__construct($AigisIRC);

$this->triggers = array(
"join"   => "joinChan",
"part"   => "partChan",
"say"    => "sayComm",
"irc"    => "ircRaw",
"restart"=> "restart"
);

$this->PlugIRC->setDefaultPerms(array(
"admin.CHANNEL_MANAGEMENT" => false,
"admin.PRIVMSG"            => false,
"admin.RAW_COMMAND"        => false,
"admin.RESTART"            => false,
"admin.INVITE"             => false ));
}

public function joinChan(MessIRC $MessIRC){
	$this->PlugIRC->requirePermission($MessIRC, "admin.CHANNEL_MANAGEMENT");

	$this->ConnIRC->send("JOIN ".$MessIRC->requireArguments(1)[0]);
}

public function partChan(MessIRC $MessIRC){
	$this->PlugIRC->requirePermission($MessIRC, "admin.CHANNEL_MANAGEMENT");
	$args = $MessIRC->requireArguments(1);

	$channel = array_shift($args);
	if(isset($args[0])) $reason = implode(" ", $args);
	else                $reason = self::DEFAULT_PART_MSG;

	$this->ConnIRC->part($channel, $reason);
}

public function sayComm(MessIRC $MessIRC){
	$this->PlugIRC->requirePermission($MessIRC, "admin.PRIVMSG");
	$args = $MessIRC->requireArguments(2);

	$target = array_shift($args);
	$message = implode(" ", $args);
	$this->ConnIRC->msg($target, $message);
}

public function ircRaw(MessIRC $MessIRC){
	$this->PlugIRC->requirePermission($MessIRC, "admin.RAW_COMMAND");
	$args = $MessIRC->requireArguments(1);

	$this->ConnIRC->send(implode(" ", $args));
}

public function restart(MessIRC $MessIRC){
	$this->PlugIRC->requirePermission($MessIRC, "admin.RESTART");
	$args = $MessIRC->getArguments();
	global $argv;

	$messages = file(self::MESSAGES_FILE);
	$key = array_rand($messages);
	$this->ConnIRC->send("QUIT :{$messages[$key]}");
	$this->ConnIRC->disconnect();
	echo "\033[2J\033[1;1H";
	consoleSend("Bot restarted by ".$MessIRC->getNick().".", "SYS", "warning");
	if(isset($args[0]) AND $args[0] == "pull"){
		consoleSend("Pulling latest version from GitHub...", "SYS", "warning");
		exec("git pull");
	}
	$executable = array_shift($argv);
	pcntl_exec("$executable", $argv);
}

public function invite(MessIRC $MessIRC){
	if($this->PlugIRC->getPermission($MessIRC, "admin.INVITE") == 2){
		$this->ConnIRC->join($MessIRC->getMessage());
		return;
	}

	$notify  = "lunarmage";
	$channel = $MessIRC->getMessage();
	$this->ConnIRC->msg($botChannel, "Received an invite to $channel by ".$MessIRC->getNick());
}

}
