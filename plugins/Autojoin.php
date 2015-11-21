<?php

class Autojoin extends PlugIRC_Core{

	const NS_ACCEPT_MSG = 'Password accepted - you are now recognized.';

	protected $channels = array();

	public function __construct(AigisIRC $AigisIRC){
		parent::__construct($AigisIRC);

		$network = $this->ConnIRC->getNetwork();
		$this->channels = AigisIRC::getConfig($network)['autoJoin'];
	}

	public function notice(MessIRC $MessIRC){
		// Join on NickServ authentication.
		if($MessIRC->getNick() == 'NickServ'){
			$msg = FontIRC::stripStyles($MessIRC->getMessage());
			if($msg == self::NS_ACCEPT_MSG)
				$this->autoJoin();
		}
	}

	public function raw(MessIRC $MessIRC){
		$raw = $MessIRC->getRaw();

		// Join on 001 raw.
		if($raw == 001)
			$this->autoJoin();
	}

	public function autoJoin(){
		foreach($this->channels as $chan){
			$this->ConnIRC->join($chan);
		}
	}
}
