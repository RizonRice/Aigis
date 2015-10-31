<?php

class LogIRC extends PlugIRC_Core{

	const LOG_DIR = "logs";

	public function __construct(AigisIRC $AigisIRC){
		parent::__construct($AigisIRC);

		$this->triggers = array( "log" => "logCommand" );
	}

	public function logCommand(MessIRC $MessIRC){
		
	}

	public function privmsg(MessIRC $MessIRC){

	}
	public function join(MessIRC $MessIRC){

	}
	public function part(MessIRC $MessIRC){

	}
	public function quit(MessIRC $MessIRC){

	}
	public function raw(MessIRC $MessIRC){

	}
}
