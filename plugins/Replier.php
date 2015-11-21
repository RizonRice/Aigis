<?php


class Replier extends PlugIRC_Core{

	const CORPUS_DIR    = "plugins/etc/markov";
	const MARKOV_EXEC   = "bin/markov.py";

	const THROTTLE_TIME = 180;

	protected $throttle = array();
	protected $lines    = array();
	protected $file     = ""; 
	protected $command  = '';

	public function __construct(AigisIRC $AigisIRC){
		parent::__construct($AigisIRC);
		array_push($this->prefixes, "!", "s/", "~", '.');
		// Separate the markove files per network.
		$corpusFile = self::CORPUS_DIR."/".$this->ConnIRC->getNetwork().".markov";

		if(!file_exists($corpusFile))
			throw new Exception("Corpus not found. Did you forget to generate it?");
		if(!file_exists(self::MARKOV_EXEC))
			throw new Exception("Markov generator not found.");

		$this->command = self::MARKOV_EXEC." $corpusFile";
		exec($this->command, $this->lines);
		$this->file = $corpusFile;
	}


	public function getReply(){
		$repkey = array_rand($this->lines);

		$reply = $this->lines[$repkey];
		unset($this->lines[$repkey]);

		if(count($this->lines) <= 0){
			$this->consoleSend('Generating markov...');
			$this->lines = array();
			exec($this->command, $this->lines);
		}

		return $reply;
	}

	public function privmsg(MessIRC $MessIRC){
		if($MessIRC->parseCommand($this->prefixes)) return;

		// Add line to log.
		file_put_contents($this->file, "\n".$MessIRC->getMessage(), FILE_APPEND);

		try{ $this->PlugIRC->requirePermission($MessIRC, "reply.MARKOV"); }
		catch(Exception $e){ return; }

		$chan = $MessIRC->getReplyTarget();
		if(!isset($this->throttle[$chan]))
			$this->throttle[$chan] = 0;
		if(time() - $this->throttle[$chan] < self::THROTTLE_TIME)
			return;

		$nick = $this->UserIRC->getSelf()->getNick();
		$str  = $MessIRC->getMessage();
		if(!preg_match("/\b$nick\b/", $str))
			return;

		$this->ConnIRC->msg($chan, $this->getReply());
		$this->throttle[$chan] = time();
	}
}
