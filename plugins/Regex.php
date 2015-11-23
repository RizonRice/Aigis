<?php

class Regex extends PlugIRC_Core{

	const PLUGIN_NAME = "Regex";
	const PLUGIN_DESC = "Regular expressions in IRC.";

	const REPLY_MAX_EXTEND = 15;
	private $prefixesToAvoid = array('s/', '!', '~');

	public function __construct(AigisIRC $AigisIRC){
		parent::__construct($AigisIRC);
		$this->PlugIRC->setDefaultPerms(array("regex.NO_BRAKES" => false), true);
	}

	public function privmsg(MessIRC $MessIRC){
		if(!$MessIRC->inChannel())
			return;
		if($this->PlugIRC->getPermission($MessIRC, 'regex') != 2)
			return;
		$method = $MessIRC->getMessage();
		if(strpos($method, 's/') === 0){
			$chan = $MessIRC->getReplyTarget();

			// Separate by semicolon like in GNU sed.
			$regexes = explode(';', $method);

			if(preg_match('/^s\/(.+)\/(.*)\/(.*)$/', $regexes[0], $m))
				$origReg = '/'.$m[1].'/'.$m[3];
			else return;

			if(($msg = $this->getLastMessage($chan, $origReg)) === false)
				return;

			$new = $msg->getMessage();
			foreach($regexes as $regex){
				if(strpos($regex, 's/') !== 0)
					return;
				$regex = strstr($regex, 's/');

				// Replace escaped / to something else.
				$regex = str_replace('\/', "\x01", $regex);
				if(preg_match('/^s\/(.+)\/(.*)\/(.*)$/', $regex, $m)){
					$method  = $m[1];
					$replace = $m[2];
					$flags   = $m[3];
					$full    = "/$method/$flags";

					if(!$this->validRegex($full))
						continue;

					$new = $this->replace($new, $replace, $full);
					$new = str_replace("\x01", '/', $new);
				}
			}

			// Check reply size.
			$maxSize = count($msg->getMessage()) + self::REPLY_MAX_EXTEND;
			//if($maxSize > strlen($new))
			//	throw new Exception("P-please don't.");

			// Determine reply type.
			$nick = $msg->getNick();
			if($msg->isAction())
				$reply = "* $nick $new";
			else
				$reply = "<$nick> $new";

			$this->ConnIRC->msg($chan, $reply);
		}
	}

	public function validRegex($regex){
		if(@preg_match($regex, "test") === false)
			return false;
		else return true;
	}

	public function replace($string, $replace, $regex){
		if(preg_match('/^\/(.+)\/(.*)$/', $regex, $m)){
			$method = $m[1];
			$flags  = $m[2];
			str_replace('g', '', $flags, $global);

			if($global)
				$global = -1;
			else
				$global = 1;

			return @preg_replace($regex, $replace, $string, $global);
		}
		return $string;
	}

	public function getLastMessage($chan, $method){
		$obj = $this->UserIRC->getChannel($chan);
		$prefixes = array_merge($this->prefixes, $this->prefixesToAvoid);
		$i = 0;
		while($msg = $obj->getMessage($i)){
			if($msg->parseCommand($prefixes) or !@preg_match($method, $msg->getMessage())){
				$i++;
				continue;
			}

			return $msg;
		}
		return false;
	}

}
