<?php

class Quotes extends PlugIRC_Core{

const PLUGIN_NAME = "QuotesDB";
const PLUGIN_DESC = "IRC quotes database.";
const PLUGIN_VERSION = '2.00';

private $AigisDB = null;

public function __construct(AigisIRC $AigisIRC){
parent::__construct($AigisIRC);
$this->AigisDB = $this->PlugIRC->requirePlugin("AigisDB");

$this->triggers = array(
"aquote" => "aquote",
"aq"     => "aquote",

"rquote" => "randomQuote",
"rq"     => "randomQuote"
);

}

public function aquote(MessIRC $MessIRC){
	$this->PlugIRC->requirePermission($MessIRC, "quotes");
	$args = $MessIRC->getArguments();
	if(count($args) == 0 || $args[0] == "random"){
		$args[0] = rand(0, $this->AigisDB->query("SELECT COUNT(*) FROM quotes;")->fetch_row()[0]-1);
	}
	switch($args[0]){
		case "search":
		$args = $MessIRC->requireArguments(2);
		$sTerm = MessIRC::strSince($MessIRC->getMessage(), 2);
		$result = $this->searchQuotes($sTerm);
		if(count($result) == 0)
			throw new Exception("No quotes contain \"$sTerm\".");
		elseif(count($result) == 1){
			$quote = $this->getQuote((int)$result[0], $quoter, $quoteTime);
			$reply = "Quote " . FontIRC::bold($result[0]) . ": " . FontIRC::terminate($quote) . " | Added by $quoter on $quoteTime";
			$this->ConnIRC->msg($MessIRC->getReplyTarget(), $reply);
		}else{
		$reply = FontIRC::bold(count($result))." quotes containing \"$sTerm\": ".implode(" ", $result);
		$this->ConnIRC->msg($MessIRC->getReplyTarget(), $reply);
		}
		break;

		case "add":
			$args = $MessIRC->requireArguments(2);
			$quote = MessIRC::strSince($MessIRC->getMessage(), 2);
			$insertID = $this->addQuote($quote, $MessIRC->getNick());
			$this->ConnIRC->msg($MessIRC->getReplyTarget(), "Added. Quote ID: $insertID");
		break;

		default:
		if(is_numeric($args[0])){
			if($quote = $this->getQuote((int) $args[0], $quoter, $quoteTime))
				$reply = "Quote {$args[0]}: $quote\x0F :: Added by $quoter on $quoteTime";
			else throw new Exception("Quote not found.");
			$this->ConnIRC->msg($MessIRC->getReplyTarget(), $reply);
		}else throw new Exception("Usage: ".$MessIRC->command()." id | add quote | search term");
		break;
	}
}

public function getQuote($id, &$quoter, &$quoteTime){
	if($stmt = $this->AigisDB->prepare("SELECT quote,quoter,time FROM quotes WHERE id=?;")){
		$stmt->bind_param("i", $id);
		$stmt->execute();
		$result = $stmt->get_result();
		$quote = $result->fetch_assoc();
		if($result->num_rows == 0)
			return null;
		$quoter = $quote['quoter'];
		$quoteTime = str_replace(" 00:00:00", "", $quote['time']);
		return $quote['quote'];
	}else throw new Exception("Quotes::getQuote(): Error fetching quote for $id.");
}

public function addQuote($quote, $quoter){
	$user = $this->UserIRC->getUser($quoter);
	$quoter = $user->getUsername();
	if($stmt = $this->AigisDB->prepare("INSERT INTO quotes VALUES(NULL,?,?,NOW());")){
		$stmt->bind_param("ss", $quoter, $quote);
		$stmt->execute();
		return $this->AigisDB->insertID();
	}else throw new Exception("Quotes::addQuote(): Error adding quote \"$quote\".");
}

public function searchQuotes($searchTerm){
	if($stmt = $this->AigisDB->prepare("SELECT id FROM quotes WHERE quote REGEXP ?;")){
		$stmt->bind_param("s", $searchTerm);
		$stmt->execute();
		$ids = array();
		$result = $stmt->get_result()->fetch_all();
		foreach($result as $id){
			$ids[] = $id[0];
		}
		return $ids;
	}else throw new Exception("Quotes::searchQuotes(): Error searching for \"$searchterm\".");
}

// Random quote
public function randomQuote(MessIRC $MessIRC){
	$args = $MessIRC->getArguments();

	if(isset($args[0]))
		$qof = $args[0];
	else $qof = $MessIRC->getNick();

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
