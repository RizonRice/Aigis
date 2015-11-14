<?php

require_once "plugins/etc/urldb.php";
class AigisURL extends PlugIRC_Core{

	const PLUGIN_NAME = "AigisURL";
	const PLUGIN_DESC = "URL storing database.";

	const DATABASE_DIR = "plugins/etc/aigisurl/";
	// urldb configuration.
	const MAX_URLS = 5;
	const VALIDATE_URLS = true;
	protected $urldb;
	protected $permission;

	protected $flags = array(
		'add'     => array('-a', '--add'),
		'delete'  => array('-d', '--delete'),
		'replace' => array('-r', '--replace') );

	protected $messages = array(
		'none'    => '%s doesn\'t have any %s saved.',
		'add'     => '%s successfully added.',
		'delete'  => '%s removed.',
		'replace' => '%s replaced.',
		'usage'   => 'Usage: %s [--add urls...] [--delete ids...] [--replace id url]'
	);

	protected $words = array(
		'singular' => 'URL',
		'plural'   => 'URLs',
		'neutral'  => 'URL(s)'
	);

	protected $triggers = array(
		'url' => 'Command'
	);

	public function __construct(AigisIRC $AigisIRC){
		parent::__construct($AigisIRC);
		$dbFile = self::DATABASE_DIR.
			$this->ConnIRC->getNetwork().".{$this->words['plural']}.json";
		$this->urldb = new urldb($dbFile, static::MAX_URLS, static::VALIDATE_URLS);
		$this->permission = "aigisurl.".$this->words['singular'];
	}

	public function Command(MessIRC $MessIRC){
		$this->PlugIRC->requirePermission($MessIRC, $this->permission);
		$argv = $MessIRC->getArguments();
		$nick = $MessIRC->getNick();

		// No arguments returns user's URLs.
		if(!isset($argv[0]))
			$this->ConnIRC->msg($MessIRC->getReplyTarget(),
				$this->getReply($nick));

		// User sent a flag.
		elseif(strpos($argv[0], "-") === 0){
			$flag = array_shift($argv);
			$flaglist = $this->flags;
			// Add a URL.
			if(in_array($flag, $flaglist['add'])){
				// Check all of them first.
				foreach($argv as $URL){
					if(!urldb::checkURL($URL) and static::VALIDATE_URLS)
						throw new Exception("Invalid URL: $URL");
				}
				// Now we add them.
				foreach($argv as $URL){
					$this->urldb->addURL($nick, $URL);
				}
				$this->ConnIRC->msg($MessIRC->getReplyTarget(),
					sprintf($this->messages['add'],
						ucfirst($this->words['plural'])));
			}

			// Remove a URL.
			elseif(in_array($flag, $flaglist['delete'])){
				// Wildcard to remove all URLs.
				if($argv[0] == '*'){
					while($this->urldb->deleteURL($nick, 0)){ }
					$this->ConnIRC->msg($MessIRC->getReplyTarget(),
						"All of your {$this->words['plural']} have been deleted.");
					return;
				}
				// Check if the IDs given are associated to a URL
				// (or are even numbers.)
				$URLs = $this->urldb->getURLs($nick);
				$nums = array();
				foreach($argv as $number){
					if(!ctype_digit($number))
						throw new Exception("All values must be numeric or one * wildcard.");
					// Subtract 1 from IDs passed so IRC
					// replies start at 1 and not 0.
					$number--;
					if(!isset($URLs[$number]))
						throw new Exception("Unknown value: ".++$number);
					$nums[] = $number;
				}
				$this->urldb->deleteURL($nick, $nums);

				$this->ConnIRC->msg($MessIRC->getReplyTarget(),
					sprintf($this->messages['delete'],
						ucfirst($this->words['plural'])));
			}

			// Replace a URL.
			elseif(in_array($flag, $flaglist['replace'])){
				$argv = $MessIRC->requireArguments(3);
				array_shift($argv);
				// Check if the given ID exists.
				$URLs = $this->urldb->getURLs($nick);
				$ID = $argv[0];
				$realID = $ID-1;
				$URL = $argv[1];
				if(!isset($URLs[$realID]))
					throw new Exception("Unknown value: $ID");
				if(!urldb::checkURL($URL) and static::VALIDATE_URLS)
					throw new Exception("Invalid URL: $URL");

				$this->urldb->replaceURL($nick, $realID, $URL);
				$this->ConnIRC->msg($MessIRC->getReplyTarget(),
					sprintf($this->messages['replace'],
						ucfirst($this->words['singular'])));
			}

			// Unknown flag.
			else
				throw new Exception(sprintf($this->messages['usage'],
					$MessIRC->command()));
		}

		// User asked for another user's URLs or a specific URL of theirs.
		else{
			// Asked for a specific URL of someone else.
			if(isset($argv[1])){
				if(!ctype_digit($argv[1]))
					throw new Exception("Please provide a number.");
				$this->ConnIRC->msg($MessIRC->getReplyTarget(),
					$this->getReply($argv[0], $argv[1]));
			}elseif(ctype_digit($argv[0])){
				$this->ConnIRC->msg($MessIRC->getReplyTarget(),
					$this->getReply(
						$MessIRC->getNick(),
						$argv[0]));
			}else{
			$this->ConnIRC->msg($MessIRC->getReplyTarget(),
				$this->getReply($argv[0]));
			}
		}

	}

	public function getReply($nick, $ID = null){
		$URLs = $this->urldb->getURLs($nick);
		// Empty array means no URLs.
		if(count($URLs) === 0)
			return sprintf($this->messages['none'], $nick, $this->words['plural']);

		$reply = "";
		// Do reply for a specific ID.
		if(isset($ID)){
			$realID = $ID-1;
			if(!isset($URLs[$realID]))
				return "No {$this->words['plural']} with ID $ID found.";
			else
				return "[$ID] {$URLs[$realID]} [$nick]";
		}

		foreach($URLs as $num => $url){
			$num++;
			$reply .= "[$num] $url ";
		}
		$reply .= "[$nick]";
		return $reply;
	}
}
