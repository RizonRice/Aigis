<?php

class Woof extends PlugIRC_Core{

	const REPLY_FILE = "plugins/etc/woof.txt";
	private $woofFile = array();

	public function __construct(AigisIRC $AigisIRC){
		parent::__construct($AigisIRC);

		$this->triggers = array("woof" => "woof");

		if(!file_exists(self::REPLY_FILE))
			throw new Exception(self::REPLY_FILE." doesn't exist!");
		$this->woofFile = file(self::REPLY_FILE);
	}

	public function woof(MessIRC $MessIRC){
		$this->PlugIRC->requirePermission($MessIRC, "woof");
		$key = array_rand($this->woofFile);
		throw new Exception($this->woofFile[$key]);
	}

}
