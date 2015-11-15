<?php

require_once 'plugins/TextDB.php';
class Greets extends TextDB{

	protected $words = array(
		'singular' => 'greeting'
	);

	protected $triggers = array(
		'greet' => 'Command',
		'intro' => 'Command',
		'gr'    => 'Command'
	);

	public function join(MessIRC $MessIRC){
		$this->PlugIRC->requirePermission($MessIRC, 'command.greet');
		$nick = $MessIRC->getNick();

		if($greet = $this->textdatabase->getText($nick))
			$this->ConnIRC->msg(
				$MessIRC->getReplyTarget(),
				"[$nick] $greet");
	}

}
