<?php

class Quotes extends PlugIRC_Core{

	const PLUGIN_NAME = "QuotesDB";
	const PLUGIN_DESC = "IRC quotes database.";
	const PLUGIN_VERSION = '2.00';

	public function __construct(AigisIRC $AigisIRC){
		parent::__construct($AigisIRC);
		$this->PDO = new PDO('sqlite:plugins/etc/Quotes.sqlite');

		if(!file_exists('/usr/lib/sqlite3/pcre.so'))
			throw new Exception('SQLite3 PCRE extension not installed. Try installing sqlite3-pcre.');
		$this->PDO->exec('.load /usr/lib/sqlite3/pcre.so');

		$this->PDO->exec("CREATE TABLE IF NOT EXISTS quotes('id' INTEGER PRIMARY KEY NOT NULL, 'quote' TEXT, 'quoter' TEXT, TIME INTEGER);");

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
			$id = rand(1, $count-1);
			$quote = $this->getQuote($id, $quoter, $time);

			$reply = "Quote $id: $quote\x0F :: Added by $quoter on $time";
			$this->ConnIRC->msg($MessIRC->getReplyTarget(), $reply);
		}

	}

	public function getQuoteCount(){
		if($sth = $this->PDO->query("SELECT COUNT(*) FROM quotes;"))
			return $sth->fetchColumn();
		else throw new Exception('Error parsing database.');
	}

	public function getQuote($id, &$quoter, &$time){
		if($sth = $this->PDO->prepare("SELECT quote,quoter,time FROM quotes WHERE id=?;")){
			$sth->execute(array($id));
			$quote = $sth->fetch(PDO::FETCH_ASSOC);

			$quoter = $quote['quoter'];
			$time   = $quote['time'];
			return $quote['quote'];
		}else throw new Exception("Quotes::getQuote(): Error fetching quote for $id.");
	}

	public function addQuote($quote, $quoter){
		$user = $this->UserIRC->getUser($quoter);
		$quoter = $user->getUsername();
		if($sth = $this->AigisDB->prepare("INSERT INTO quotes (quoter, quote, time) VALUES(:quoter,:quote,:time);")){
			$sth->execute(array(
				':quoter' => $quoter,
				':quote'  => $quote,
				':time'   => time()
			));
			return $this->PDO->lastInsertId();
		}else throw new Exception("Quotes::addQuote(): Error adding quote \"$quote\".");
}

	public function searchQuotes($searchTerm){
		if($sth = $this->PDO->prepare("SELECT id FROM quotes WHERE quote REGEXP ?;")){
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
