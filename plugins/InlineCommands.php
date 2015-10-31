<?php

// Make a class that extends MessIRC that returns true on every command.
class InlineMessIRC extends MessIRC{
	public function parseCommand($prefixes = null){
		$this->isCommand = true;
		$parameters = $this->messageArray;
		$this->command = array_shift($parameters);
		$this->commandArgs = $parameters;
		return true;
	}
}

class InlineCommands extends PlugIRC_Core{

	const INLINE_REGEX = '<\$ *(.*)\$>';

	public function privmsg(MessIRC $MessIRC){
		// Prevent multiple loops.
		if(get_class($MessIRC) == "InlineMessIRC") return;

		if(preg_match("/".self::INLINE_REGEX."/", $MessIRC->getMessage(), $regex)){
			$this->PlugIRC->requirePermission($MessIRC, "inline");

			$hostmask = 
			$MessIRC->getNick()."!".$MessIRC->getIdent()."@".$MessIRC->getHostmask();

			$msg = ":$hostmask PRIVMSG ".$MessIRC->getReplyTarget()." :".$regex[1];

			$MessIRCManager = $this->AigisIRC->getAigisVar("MessIRCManager");
			$newMess = new InlineMessIRC($msg, $MessIRCManager);

			$this->PlugIRC->pluginSendAll($MessIRC->getType(), $newMess);
		}
	}

}

?>
