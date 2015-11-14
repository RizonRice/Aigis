<?php

class Help extends PlugIRC_Core{

const HELP_DIR = "help";
private $commandList = array();
private $commandPlugins = array();
private $aliases = array();
private $helpPages = array();

public function __construct(AigisIRC $AigisIRC){
	parent::__construct($AigisIRC);

	$this->triggers = array(
	"help" => "helpPage",
	"man"  => "helpPage",
	"h"    => "helpPage"
	);
	$this->setCommandList();
}

public function helpPage(MessIRC $MessIRC){
	$this->PlugIRC->requirePermission($MessIRC, "help");
	$args = $MessIRC->getArguments();
	if(!isset($args[0])){
		$plugins = array();
		foreach($this->commandPlugins as $command => $plugin){
			if(!isset($plugins[$plugin]))
				$plugins[$plugin] = array();
			$plugins[$plugin][] = $command;
		}
		$reply = FontIRC::bold("Commands:")." ";
		foreach($plugins as $plugin => $commands){
			if($this->PlugIRC->pluginLoaded($plugin))
				$reply .= "$plugin: ".FontIRC::italic(implode(" ", $commands)). " ";
		}
		$this->ConnIRC->notice($MessIRC->getNick(), $reply);
	}else{
		if(!isset($this->helpPages[$args[0]])){
			if(isset($this->aliases[$args[0]]))
				$args[0] = $this->aliases[$args[0]];
			else return;
		}
		$reply = FontIRC::arr($this->helpPages[$args[0]]);
		if($prefixes = $this->PlugIRC->getPrefixes($this->commandPlugins[$args[0]])){
			$prefixes = "(".implode(")(",$prefixes).")";
			$reply = str_replace("%TRIG%", $prefixes, $reply);
			$this->ConnIRC->notice($MessIRC->getNick(), $reply);
		}
	}
}

private function setCommandList(){
	$helpDir = scandir(self::HELP_DIR, 1);
	foreach($helpDir as $file){
		if(preg_match("/command.(.*).txt/", $file, $cmdName)){
			$command = $cmdName[1];
			$this->commandList[] = $command;
			$fileLines = file(self::HELP_DIR . "/$file");
			$this->commandPlugins[$command] = rtrim(array_shift($fileLines));
			$aliases = explode(" ", rtrim(array_shift($fileLines)));
			foreach($aliases as $alias){
				$this->aliases[$alias] = $command;
			}
			$this->helpPages[$command] = implode($fileLines);
		}
	}
}

public function getFullCommand($command){
	if(isset($this->aliases[$command]))
		$full = $this->aliases[$command];
	elseif(isset($this->helpPages[$command]))
		$full = $command;
	else
		$full = null;

	return $full;
}

}
