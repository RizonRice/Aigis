<?php

require_once "UserIRC_User.php";
require_once "UserIRC_Channel.php";

class UserIRC{

// UserIRC
// Part of AigisIRC (https://github.com/Joaquin-V/AigisIRC)

private $AigisIRC = null;

private $users = array();
private $self = null;
private $channels = array();
private $PMchan = null;

public function __construct(AigisIRC $AigisIRC, $nickSelf){
$this->AigisIRC = $AigisIRC;
// Start instance of self user.
$this->self = new UserIRC_User($nickSelf . "!AigisIRC@lunaramge.com.ar");
}

// UserIRC::getUser($address) - Gets the user object of an address (or makes one if non-existant.)
// @param $address string User's address.
// @return User object.
// @throws Exception if $address doesn't match the regex.
public function getUser($address){
	//Seperate the nick, ident and hostmask.
	if(preg_match("/(.*)!(.*)@(.*)/", $address, $matches)){
		$nick = $matches[1];
		$ident = $matches[2];
		$host = $matches[3];
	}else $nick = $address;
	//Check if the nick is the same as the bot's and if so, return the self user.
	if($nick == $this->nickSelf())
		return $this->self;
	//If not, check if there is a user object for that nick.
	elseif(isset($this->users[$nick]))
		return $this->users[$nick];
	//If there isn't, create a user object.
	$this->users[$nick] = new UserIRC_User($address);
	return $this->users[$nick];
}

// UserIRC::getChannel($channel) - Gets a channel object of a specified channel.
// @param string $channel Channel name.
public function getChannel($channel){
	if(!isset($this->channels[$channel]))
		$this->channels[$channel] = new UserIRC_Channel($channel);
	return $this->channels[$channel];
}

public function getSelf(){
	return $this->self;
}

// Parse server messages.
public function raw(MessIRC $MessIRC){
	$raw = $MessIRC->getRaw();
	switch($raw){
		case 353:
			$params = $MessIRC->getParams();
			array_shift($params);
			$channel = array_shift($params);

			foreach($params as $address){
				if(preg_match('/([:~&@%+]*)([^!]+)(?:!([^@]+)@(.+))?/', $address, $match)){
					@list(, $modes, $nick, $ident, $host) = $match;

					$modes = str_split($modes);
					$modeLetters = array('~' => "q", '&' => "a", '@' => "o", '%' => "h", '+' => "v");
					$modecmd = "+";
					foreach($modes as $modechr){
						if(isset($modeLetters[$modechr]))
							$modecmd .= $modeLetters[$modechr];
					}

					$user = $this->getUser("$nick!$ident@$host");
					$chan = $this->getChannel($channel);

					$user->addChan($channel);
					$chan->join($nick);

					if($modecmd != "+")
						$user->mode($channel, $modecmd);
				}
			}
		break;
	}
}

public function privmsg(MessIRC $MessIRC){
	if($MessIRC->getNick() == $this->nickSelf())
		consoleSend("[Force-spoken] ".$MessIRC->getReplyTarget()." -> ".$MessIRC->getMessage(), "ConnIRC", "send");

	if($MessIRC->inChannel()){
		$chan = $this->getChannel($MessIRC->getReplyTarget());
		$chan->addMessage($MessIRC);
	}
	$user = $this->getUser($MessIRC->getNick());
	$user->addMessage($MessIRC);
}

public function join(MessIRC $MessIRC){
	if($MessIRC->getNick() == $this->nickSelf())
		consoleSend("Joined ".$MessIRC->getReplyTarget(), "ConnIRC", "info");

	$user = $this->getUser($MessIRC->getNick());
	$chan = $this->getChannel($MessIRC->getReplyTarget());

	$user->addChan($MessIRC->getReplyTarget());
	$user->updateHost($MessIRC);
	$chan->join($MessIRC->getNick());
}

public function nick(MessIRC $MessIRC){
	$nick = $MessIRC->getNick();
	$user = $this->getUser($nick);
	$newNick = $MessIRC->getMessage();

	$user->setNick($newNick);
	$this->users[$newNick] = $user;
	unset($this->users[$nick]);
}

public function part(MessIRC $MessIRC){
	if($MessIRC->getNick() == $this->nickSelf())
		consoleSend("Parted ".$MessIRC->getReplyTarget()." (".$MessIRC->getMessage().")", "ConnIRC", "info");

	$user = $this->getUser($MessIRC->getNick());
	$chan = $this->getChannel($MessIRC->getReplyTarget());

	$user->rmChan($MessIRC->getReplyTarget());
	$chan->part($MessIRC->getNick());
}

public function kick(MessIRC $MessIRC){
	$nick = $MessIRC->getNick();
	$reason = explode(" ", MessIRC::strSince($MessIRC->getArrayMsg(), 3));
	$kicked = array_shift($reason);
	$reason = MessIRC::stripCol(implode(" ",$reason));
	if($kicked == $this->nickSelf()){
		// Uncomment for automatic rejoin on kick.
		// consoleSend("Kicked from ".$MessIRC->getReplyTarget()." by ".$MessIRC->getNick().". Reason: ".$reason, "ConnIRC", "warning");
		// $this->AigisIRC->getAigisVar("ConnIRC")->join($MessIRC->getReplyTarget());
	}
	$user = $this->getUser($kicked);
	$chan = $this->getChannel($MessIRC->getReplyTarget());
	$user->rmChan($MessIRC->getReplyTarget());
	$chan->part($kicked);
}

public function quit(MessIRC $MessIRC){
	$nick = $MessIRC->getNick();
	$user = $this->getUser($nick);
	$chans = $user->getChans();
	foreach($chans as $chan){
		$channel = $this->getChannel($chan);
		$channel->part($nick);
	}

	if(isset($this->users[$nick]))
		unset($this->users[$nick]);
}

public function mode(MessIRC $MessIRC){
	$modeArray = $MessIRC->getArrayMsg();
	if(!isset($modeArray[4]))
		return; //Channel modes that don't change admin settings. They're not that important.
	elseif(strpos($modeArray[2], '#') === false)
		return; //User modes. Not really useful for a bot (much less one that will most likely not be an IRCOp.)
	else{ //Channel modes that affect user admin settings (+vhoaq.)
		if(preg_match_all('/(\+|\-)([ahoqv]+)/', $modeArray[3], $regex)){
			$channel = $modeArray[2];
			// Pointer to shift through nicks in the mode command.
			$letterPointer = 0;
			// Loop through both modes being added (+) and modes being revoked (-).
			for($modePointer = 0; isset($regex[0][$modePointer]); $modePointer++){
				$modeSymbol = $regex[1][$modePointer];
				$modeLetters= $regex[2][$modePointer];
				// Split the letters into an array and individually add modes to the user objects. This lets us add modes to lines that manage multiple users in one go.
				$letterArray= str_split($modeLetters);
				foreach($letterArray as $letter){
					$modeCommand = $modeSymbol.$letter;
					$nick = $modeArray[$letterPointer+4];
					$user = $this->getUser($nick);

					$user->mode($channel, $modeCommand);
					$letterPointer++;
				}
			}
		}
	}
}

public function nickSelf(){
	$nick = $this->AigisIRC->getAigisVar("botNick");
	$this->self->setNick($nick);
	return $nick;
}

}
