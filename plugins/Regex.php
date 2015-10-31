<?php

class Regex extends PlugIRC_Core{

const REPLY_MAX_EXTEND = 15;
private $prefixesToAvoid = array('s/', 'c/', '!', '~');

public function __construct(AigisIRC $AigisIRC){
	parent::__construct($AigisIRC);
	$this->PlugIRC->setDefaultPerms(array("regex.NO_BRAKES" => false), true);
}

public function privmsg(MessIRC $MessIRC){
	if(!$MessIRC->inChannel())
		return;
	if(preg_match('/^s\/(.*)\/(.*)\/(.*)$/', $MessIRC->getMessage(), $regex)){
		if($this->PlugIRC->getPermission($MessIRC, "regex") != 2)
			return;

		$search  = $regex[1];
		$replace = $regex[2];
		$flags   = str_replace('g', '', $regex[3], $gCount);
		$limit   = ($gCount == 0) ? 1 : -1;
		if(!$MessIRC->inChannel()) return;
		$channel = $this->UserIRC->getChannel($MessIRC->getReplyTarget());

		$msgid = 1;
		while($msg = $channel->getMessage($msgid)){
			if($msg->parseCommand(array_merge($this->prefixes, $this->prefixesToAvoid))){
				$msgid++;
				continue;
			}
			if(($replaced = @preg_replace("/$search/$flags", $replace, $msg->getMessage(), $limit)) !== $msg->getMessage() && $replaced !== ""){
				if(strlen($replaced) > (strlen($msg->getMessage()) + self::REPLY_MAX_EXTEND) && $this->PlugIRC->getPermission($MessIRC, "regex.NO_BRAKES") != 2)
					$this->ConnIRC->msg($MessIRC->getReplyTarget(), "<".$msg->getNick()."> [Something uncontrollably long.]");
				else{
					if($msg->isAction())
						$this->ConnIRC->msg($MessIRC->getReplyTarget(), "* ".$msg->getNick()." ".$replaced);
					else
						$this->ConnIRC->msg($MessIRC->getReplyTarget(), "<".$msg->getNick()."> ".$replaced);
				}
				return;
			}else $msgid++;		
		}
	}

}

}
