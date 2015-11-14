<?php

class Remind extends PlugIRC_Core{

	const REMINDERS_LIMIT = 5;
	const MAXTIME = 1440;
	const MINTIME = 1;

	protected $reminders = array();
	protected $remc = array();

	public function __construct(AigisIRC $AigisIRC){
		parent::__construct($AigisIRC);

		$this->triggers = array(
			"remind" => "Command"
		);
	}

	public function Command(MessIRC $MessIRC){
		$this->PlugIRC->requirePermission($MessIRC, "remind");
		$args = $MessIRC->requireArguments(2);

		$mins = array_shift($args);

		if(!ctype_digit($mins))
			throw new Exception("Provide a number for time.");
		if($mins < self::MINTIME OR $mins > self::MAXTIME)
			throw new Exception("Time provided not in allowed range (".self::MINTIME."-".self::MAXTIME.")");

		$duration = $mins * 60;

		$text = implode(" ", $args);

		$this->addReminder(
			$MessIRC->getReplyTarget(), $MessIRC->getNick(),
			$duration, $text);

		$this->ConnIRC->msg($MessIRC->getReplyTarget(), "Reminder set for $mins minutes: $text");
	}

	public function checkReminders(){
		$done = array();
		$time = time();

		foreach($this->reminders as $key => $remind){
			$elapsed = $time - $remind['start'];
			if($elapsed >= $remind['duration']){
				$done[] = $remind;
				unset($this->reminders[$key]);
				$this->remc[$remind['nick']]--;
			}
		}
		return $done;
	}

	public function addReminder($chan, $nick, $duration, $text){
		if(!isset($this->remc[$nick]))
			$this->remc[$nick] = 0;

		if($this->remc[$nick] >= self::REMINDERS_LIMIT)
			throw new Exception("Error: Reminder limit exceeded for $nick.");

		$this->reminders[] = array(
			'start'    => time(),
			'duration' => $duration,

			'nick'    => $nick,
			'channel' => $chan,
			'text'    => $text
		);
		$this->remc[$nick]++;
	}

	private function sendReminders(){
		$reminders = $this->checkReminders();
		foreach($reminders as $reminder){
			$chan = $reminder['channel'];
			$text = $reminder['text'];
			$nick = $reminder['nick'];

			$this->ConnIRC->msg($chan, "$nick: $text");
		}
	}

	// Check reminders on ANYTHING sent to the plugin.

	public function privmsg(MessIRC $MessIRC){
		$this->triggerParse($MessIRC);
		$this->sendReminders();
	}

	public function raw(MessIRC $MessIRC)    {$this->sendReminders();}

	public function ctcp(MessIRC $MessIRC)   {$this->sendReminders();}
	public function notice(MessIRC $MessIRC) {$this->sendReminders();}

	public function error(MessIRC $MessIRC)  {$this->sendReminders();}
	public function ping(MessIRC $MessIRC)   {$this->sendReminders();}

	public function nick(MessIRC $MessIRC)   {$this->sendReminders();}
	public function quit(MessIRC $MessIRC)   {$this->sendReminders();}
	public function join(MessIRC $MessIRC)   {$this->sendReminders();}
	public function part(MessIRC $MessIRC)   {$this->sendReminders();}
}
