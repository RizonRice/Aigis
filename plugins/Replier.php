<?php


class Replier extends PlugIRC_Core{

	// Credit: https://github.com/TheReverend403/markovgen
	const CORPUS_FILE = "plugins/etc/markov.txt";
	const MARKOV_EXEC = "bin/markov.py";

	const THROTTLE_TIME = 180;

	protected $throttle = array();

	public function __construct(AigisIRC $AigisIRC){
		parent::__construct($AigisIRC);
		if(!file_exists(self::CORPUS_FILE))
			throw new Exception("Corpus not found. Did you forget to generate it?");
		if(!file_exists(self::MARKOV_EXEC))
			throw new Exception("Markov generator not found.");

		//exec(self::MARKOVGEN_EXEC.' reddit 4chan');
		exec(self::MARKOV_EXEC, $this->lines);
	}


	public function getReply(){
		$markov = $this->lines;
		$repkey = array_rand($markov);

		return $markov[$repkey];
	}

	public function privmsg(MessIRC $MessIRC){
		$this->PlugIRC->requirePermission($MessIRC, "reply.MARKOV");

		$chan = $MessIRC->getReplyTarget();
		if(!isset($this->throttle[$chan]))
			$this->throttle[$chan] = 0;
		if(time() - $this->throttle[$chan] < self::THROTTLE_TIME)
			return;

		$nick = strtolower($this->AigisIRC->getAigisVar("botNick"));
		$str  = strtolower($MessIRC->getMessage());
		if(substr_count($str, $nick) === 0)
			return;

		$this->ConnIRC->msg($chan, $this->getReply());
		$this->throttle[$chan] = time();
	}
}
