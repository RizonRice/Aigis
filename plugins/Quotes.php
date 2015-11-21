<?php

class Quotes extends PlugIRC_Core{

	const PLUGIN_NAME = "QuotesDB";
	const PLUGIN_DESC = "IRC quotes database.";
	const PLUGIN_VERSION = '2.00';

	protected $flags = array(
		'add'    => array('-a', '--add'),
		'last'   => array('-l', '--last'),
		'search' => array('-s', '--search')
		);

	public function __construct(AigisIRC $AigisIRC){
		parent::__construct($AigisIRC);
		$dbFile = AIGIS_USR.'/Quotes.sqlite';
		$this->PDO = new PDO('sqlite:'.$dbFile);
		$this->PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		$this->PDO->sqliteCreateFunction('REGEXP', function($regex, $string){
			return (bool) preg_match("/$regex/i", $string);
		}, 2);
		$this->PDO->exec("CREATE TABLE IF NOT EXISTS quotes('id' INTEGER PRIMARY KEY NOT NULL, 'quote' TEXT, 'quoter' TEXT, 'time' INTEGER);");

		$this->triggers = array(
			"aquote" => "aquote",
			"aq"     => "aquote",

			"rquote" => "randomQuote",
			"rq"     => "randomQuote"
		);

	}

	public function aquote(MessIRC $MessIRC){
		$argv = $MessIRC->getArguments();

		// Random quote.
		if(!isset($argv[0])){
			$count = $this->getQuoteCount();
			$id = rand(1, $count);

			$this->ConnIRC->msg($MessIRC->getReplyTarget(), $this->getReply($id));
		}

		// Flags.
		elseif(strpos($argv[0], "-") === 0){
			// Add a quote.
			if(in_array($argv[0], $this->flags['add'])){
				array_shift($argv);
				$quote = implode(' ', $argv);
				$nick  = $MessIRC->getNick();
				$id    = $this->addQuote($quote, $nick);
				$this->ConnIRC->msg($MessIRC->getReplyTarget(),
					"Added. ID: $id");
			}
			// Get last quote.
			if(in_array($argv[0], $this->flags['last'])){
				$last = $this->getQuoteCount();
				$this->ConnIRC->msg($MessIRC->getReplyTarget(),
					$this->getReply($last));
			}
			// Search for a quote.
			if(in_array($argv[0], $this->flags['search'])){
				array_shift($argv);
				$query  = implode(' ', $argv);
				$quotes = $this->searchQuotes($query);
				$count  = count($quotes);

				// No quotes.
				if($count == 0)
					throw new Exception('No quotes found.');
				// One quote.
				elseif($count == 1){
					$this->ConnIRC->msg($MessIRC->getReplyTarget(),
						$this->getReply($count[0]));
				}
				// Many quotes.
				else{
					$reply = 'Quotes matching '.FontIRC::italic($query).
						': '.implode(' ', $quotes);

					if(count($query) > 120)
						$this->ConnIRC->notice($MessIRC->getNick(), $reply);
					else
						$this->ConnIRC->msg($MessIRC->getReplyTarget(), $reply);
				}
			}
		}

		// Quote ID.
		elseif(ctype_digit($argv[0])){

			
		}

		// Default to searching.
		else{

		}
	}

	public function getReply($id){
		$quote = $this->getQuote($id, $quoter, $time);
		return FontIRC::bold("Quote $id").': '.
			FontIRC::terminate($quote).' :: Added by '.
			FontIRC::italic($quoter).' on '.FontIRC::italic($time);
	}

	public function getQuoteCount(){
		if($sth = $this->PDO->query("SELECT COUNT(*) FROM quotes WHERE id != 0;"))
			return $sth->fetchColumn();
		else throw new Exception('Error parsing database.');
	}

	public function getQuote($id, &$quoter, &$time){
		if($sth = $this->PDO->prepare("SELECT quote,quoter,time FROM quotes WHERE id=?;")){
			$sth->execute(array($id));
			if(($quote = $sth->fetch(PDO::FETCH_ASSOC)) === false)
				throw new Exception("Quote $id not found.");

			$quoter = $quote['quoter'];
			$time   = $quote['time'];
			return $quote['quote'];
		}else throw new Exception("Quotes::getQuote(): Error fetching quote for $id.");
	}

	public function addQuote($quote, $quoter){
		$user = $this->UserIRC->getUser($quoter);
		$quoter = $user->getUsername();
		if($sth = $this->PDO->prepare("INSERT INTO quotes VALUES(NULL,:quote,:quoter,DATETIME(:time));")){
			$sth->execute(array(
				':quoter' => $quoter,
				':quote'  => $quote,
				':time'   => time()
			));
			return $this->PDO->lastInsertId();
		}else throw new Exception("Quotes::addQuote(): Error adding quote \"$quote\".");
}

	public function searchQuotes($searchTerm){
		if($sth = $this->PDO->prepare("SELECT id FROM quotes WHERE REGEXP(?, quote);")){
			$sth->execute(array($searchTerm));
			$ids = array();
			$result = $sth->fetchAll();

			foreach($result as $id){
				$ids[] = $id[0];
			}
			return $ids;
		}else throw new Exception("Quotes::searchQuotes(): Error searching for \"$searchTerm\".");
	}

	// Random quote
	public function randomQuote(MessIRC $MessIRC){
		$args = $MessIRC->getArguments();

		if(isset($args[0]))
			$qof = $args[0];
		else $qof = $MessIRC->getReplyTarget();

		// If a channel is specified.
		if(strstr($qof, "#") !== false){
			$chan = $this->UserIRC->getChannel($qof);
			$maxQuotes = $chan->getMessageCount() - 1;
			$quoteNum = rand(1, $maxQuotes);
			$msg = $chan->getMessage($quoteNum);
		}else{
			$chan = $MessIRC->getReplyTarget();
			$user = $this->UserIRC->getUser($qof);
			$maxQuotes = $user->getMessageCount($chan) - 1;
			if(is_null($maxQuotes))
				throw new Exception("There are no messages under that nick.");
			$quoteNum = rand(1, $maxQuotes);
			$msg = $user->getMessage($chan, $quoteNum);
			if(is_null($msg))
				throw new Exception();
		}

		if($msg->isAction()) $string = "* ".$msg->getNick()." ".$msg->getMessage();
		else                 $string = "<".$msg->getNick()."> ".$msg->getMessage();
		$this->ConnIRC->msg($MessIRC->getReplyTarget(), $string);
	}


}
