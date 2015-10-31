<?php

class ConnIRC{

// ConnIRC
// Part of AigisIRC (https://github.com/Joaquin-V/AigisIRC)

const SOCKET_TIMEOUT = 100000;

const PING_FREQUENCY = 90;
const ACTIVITY_TIMEOUT = 150;
const RECONNECT_TIMEOUT = 7;
const RECONNECT_DELAY = 10;

private $AigisIRC	= null;

private $network	= "";
private $server		= "";
private $port		= "";
private $network001 = "";

private $hostmask	= "";
private $nick		= "";
private $ident		= "";
private $host		= "";

private $socket		= null;

public function __construct(AigisIRC $AigisIRC, $network, $nick, $server, $port = 6667){
	$this->AigisIRC = $AigisIRC;
	$this->network	= $network;
	$this->nick		= $nick;
	$this->server	= $server;
	$this->port		= $port;
}

public function __destruct(){
	$this->send("QUIT :It's important to keep someone in your thoughts, and to think that you are in theirs... And... to touch them... Just kidding.");
	fclose($this->socket);
}

public function connect(){
	// Close socket if it's open.
	if(@get_resource_type($this->socket) === "stream")
		fclose($this->socket);

	// Attempt to open the socket.
	consoleSend("Connecting to $this->server:$this->port...", "ConnIRC");
	$this->socket = @fsockopen($this->server, $this->port);

	// If the connection fails.
	if(!$this->socket){
		sleep(5);
		$this->connect();
	}
	// If the connection succeeds.
	else{
		$this->AigisIRC->setAigisVar("lastConn", time());

		// Send a PASS command.
		$this->send("PASS ".$this->AigisIRC->getAigisVar("nsPass"));
		// Set nick.
		$this->send("NICK $this->nick");
		// Send the USER command
		$this->send("USER AigisIRC localhost $this->server :AigisIRC");
	}
}

public function disconnect(){
	if($this->socket)
		fclose($this->socket);
}

public function connected(){
	if(@get_resource_type($this->socket) === "stream")
		return true;
	else return false;
}

// ConnIRC::read()
// @return string|null Last message from the IRC server or null if no new messages were received.
public function read(){
	$read = array($this->socket);
	$write = $except = null;
	if(($changed = stream_select($read, $write, $except, 0, self::SOCKET_TIMEOUT)) > 0){
		$data = trim(fgets($this->socket));
		return $data;
	}
	else
		return null;
}

public function parseRaw(MessIRC $MessIRC){
	switch($MessIRC->getRaw()){
		case 001:
		$this->AigisIRC->setAigisVar("lastRegg", time());
		// Successful connection.
		if(preg_match('/Welcome to the (\w*) [\QInternet Relay Chat\E|IRC]* Network (.*)/', $MessIRC->getMessage(), $match)){
			$this->network001 = $match[1];
			if(preg_match('/(.*)!(.*)@(.*)/', $match[2], $vhost)){
				$this->hostmask		= $vhost[0];
				$this->nick			= $vhost[1];
				$this->ident		= $vhost[2];
				$this->host			= $vhost[3];
			}else
				$this->nick = $match[2];
			$this->AigisIRC->setAigisVar("botNick", $this->nick);
			$this->AigisIRC->getAigisVar("UserIRC")->nickSelf();
		}
		consoleSend("Connected to $this->network001 as $this->nick.", "ConnIRC", "success");
		// Set +B (user mode for bots).
		$this->send("MODE " . $this->nick . " +B");
		// Identify with NickServ.
		$this->AigisIRC->nsIdentify();
		// Send successful connection to plugins.
		$this->AigisIRC->getAigisVar("PlugIRC")->pluginSendAll("connect", time());
		break;

		case 005:
		// Send PROTOCTL for UHNAMES and NAMESX support.
		$this->send("PROTOCTL UHNAMES");
		$this->send("PROTOCTL NAMESX");
		break;

		case 433:
		$altNick = $this->AigisIRC->getAigisVar("altNick");
		consoleSend("Nick is taken. Using alternative nick \"$altNick\".", "ConnIRC", "warning");
		$this->send("NICK " . $altNick);
		break;
	}
}

// ConnIRC::send($data)
// @param string $data String to send to the IRC server.
public function send($data){
	fputs($this->socket, $data."\n");
}

// ConnIRC::msg($target, $message, $ctcp)
// @param string $target  User of channel to send to.
// @param string $message Message to send.
// @param string $ctcp    If passed, CTCP command to send.
public function msg($target, $message, $ctcp = null){
	if(is_array($message)){
		foreach($message as $line)
			$this->msg($target, $line, $ctcp);
		return;
	}

	if(strlen(FontIRC::stripStyles($message)) === 0)
		return;

	$hostSelf = $this->AigisIRC->getAigisVar("UserIRC")->getSelf()->getFullHost();
	$maxlen = 512 - 1 - strlen($this->nick) - 1 - strlen($hostSelf) - 9 - strlen($target) - 2 - 2;

	if(isset($ctcp))
		$maxlen -= (3 + strlen($ctcp));

	if(strpos($message, "\n") !== false){
		$message = explode("\n", $message);
		$this->msg($target, $message, $ctcp);
		return;
	}
	$message = str_replace("\r", "", $message);

	$words = explode(" ", $message);
	$string = "";

	for($i = 0, $wordCount = count($words); $i < $wordCount; $i++){
		$string .= $words[$i] . " ";

		if((isset($words[$i+1]) && strlen($string . $words[$i+1]) > $maxlen) OR !isset($words[$i+1])){
			$stringToSend = substr($string, 0, -1);

			if(isset($ctcp))
				$stringToSend = "\x01$ctcp $stringToSend\x01";

			$this->send("PRIVMSG $target :$stringToSend");
			consoleSend(FontIRC::stripStyles("$target -> $string"), "ConnIRC", "send");
			//$this->AigisIRC->sendToModules("message_sent", "$target :$stringToSend");
			$string = "";
		}
	}
}

// ConnIRC::notice($target, $message, $ctcp)
// @param string $target  Where to send the message(s).
// @param string $message Message to send.
// @param string $ctcp    If passed, CTCP command to send.
public function notice($target, $message, $ctcp = null){
	if(is_array($message)){
		foreach($message as $line)
			$this->notice($target, $line, $ctcp);
		return;
	}

	if(strlen(FontIRC::stripStyles($message)) === 0)
		return;

	$maxlen = 512 - 1 - strlen($this->nick) - 1 - strlen($this->hostmask) - 8 - strlen($target) - 2 - 2;

	if(isset($ctcp))
		$maxlen -= (3 + strlen($ctcp));

	if(strpos($message, "\n") !== false){
		$message = explode("\n", $message);
		$this->notice($target, $message, $ctcp);
		return;
	}

	$words = explode(" ", $message);
	$string = "";

	for($i = 0, $wordCount = count($words); $i < $wordCount; $i++){
		$string .= $words[$i] . " ";

		if((isset($words[$i+1]) && strlen($string . $words[$i+1]) > $maxlen) OR !isset($words[$i+1])){
			$stringToSend = substr($string, 0, -1);

			if(isset($ctcp))
				$stringToSend = "\x01$ctcp $stringToSend\x01";

			$this->send("NOTICE $target :$stringToSend");
			consoleSend(FontIRC::stripStyles("[Notice] $target -> $string"), "ConnIRC", "send");
			//$this->AigisIRC->sendToModules("message_sent", "$target :$stringToSend");
			$string = "";
		}
	}
}


// ConnIRC::join($channel) - Joins a channel.
// @param string $channel Channel to join.
public function join($channel){
	$this->send("JOIN $channel");
}

// ConnIRC::part($channel) - Parts a channel.
// @param string $channel Channel to part.
// @param string $reason  Reason to part.
public function part($channel, $reason = "AigisIRC by LunarMage"){
	$this->send("PART $channel :$reason");
}

public function getNetwork(){
	return $this->network;
}

public function getNick(){
	return $this->nick;
}

}
