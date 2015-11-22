<?php

require_once "plugins/etc/textdb.php";
class TextDB extends PlugIRC_Core{

	const PLUGIN_NAME = "TextDB";
	const PLUGIN_DESC = "Text storing database.";

	const DATABASE_DIR = "plugins/etc/textdb/";
	// urldb configuration.
	const MAX_LENGTH = 350;
	protected $textdatabase;
	protected $permission;

	protected $flags = array(
		'set'     => array('-s', '--set', '-a', '--add'),
		'delete'  => array('-d', '--delete'),
	);

	protected $messages = array(
		'none'     => '%s doesn\'t have any %s saved.',
		'set'      => '%s has been set.',
		'delete'   => '%s removed.',
		'notfound' => 'No %s found.',
		'usage'    => 'Usage: %s [--set text] [--delete]'
	);

	protected $words = array(
		'singular' => 'text',
	);

	protected $triggers = array(
		'text' => 'Command'
	);

	public function __construct(AigisIRC $AigisIRC){
		parent::__construct($AigisIRC);
		$dbFile = self::DATABASE_DIR.
			$this->ConnIRC->getNetwork().".{$this->words['singular']}.json";
		$this->textdatabase = new textdatabase($dbFile, static::MAX_LENGTH);
		$this->permission = "textdb.".$this->words['singular'];
	}

	public function Command(MessIRC $MessIRC){
		$this->PlugIRC->requirePermission($MessIRC, $this->permission);
		$argv = $MessIRC->getArguments();
		$nick = $MessIRC->getNick();

		// No arguments returns user's text.
		if(!isset($argv[0]))
			$this->ConnIRC->msg($MessIRC->getReplyTarget(),
				$this->getReply($nick));

		// User sent a flag.
		elseif(strpos($argv[0], "-") === 0){
			$flag = array_shift($argv);
			$flaglist = $this->flags;
			// Set their text.
			if(in_array($flag, $flaglist['set'])){
				$text = implode(" ", $argv);
				$this->textdatabase->setText($nick, $text);

				$this->ConnIRC->msg($MessIRC->getReplyTarget(),
					sprintf($this->messages['set'],
						ucfirst($this->words['singular'])));
			}

			// Remove a URL.
			elseif(in_array($flag, $flaglist['delete'])){
				if($this->textdatabase->deleteText($nick))
					$this->ConnIRC->msg($MessIRC->getReplyTarget(),
						sprintf($this->messages['delete'],
							ucfirst($this->words['singular'])));

				else
					throw new Exception(
						sprintf($this->messages['notfound'],
							$this->words['singular']));
			}

			// Unknown flag.
			else
				throw new Exception(sprintf($this->messages['usage'],
					$MessIRC->command()));
		}

		// User asked for another user's text.
		else{
			$this->ConnIRC->msg($MessIRC->getReplyTarget(),
				$this->getReply($argv[0]));
		}

	}

	public function getReply($nick, $ID = null){
		$text = $this->textdatabase->getText($nick);
		// Null means no text.
		if($text == null)
			return sprintf($this->messages['none'], $nick, $this->words['singular']);

		return "$text [$nick]";
	}
}
