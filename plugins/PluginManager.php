<?php

class PluginManager extends PlugIRC_Core{

	protected static $flags = array(
		'load'   => array('-l', '--load'),
		'unload' => array('-u', '--unload')
		);

	public function __construct(AigisIRC $AigisIRC){
		parent::__construct($AigisIRC);

		$this->triggers = array(
			"plugins" => "Command",
			"plg"     => "Command"
		);

		$this->PlugIRC->setDefaultPerms(array(
			"plugins.LOAD"   => false,
			"plugins.UNLOAD" => false));
	}

	public function Command(MessIRC $MessIRC){
		$argv = $MessIRC->getArguments();

		// No arguments: list all plugins.
		if(!isset($argv[0]))
			$this->listPlugins($MessIRC);

		// Flag detected.
		elseif(strpos($argv[0], "-") === 0){
			$flag = array_shift($argv);
			$flaglist = self::$flags;

			// Load plugin.
			if(in_array($flag, $flaglist['load']))
				$this->loadPlugin($MessIRC);

			// Unload plugin.
			elseif(in_array($flag, $flaglist['unload']))
				$this->unloadPlugin($MessIRC);

			// Unknown flag.
			else
				throw new Exception("Unknown option: $flag");
		}

		// Information on one plugin in particular.
		else{
			$this->getPluginInfo($MessIRC);
		}
	}

	public function listPlugins(MessIRC $MessIRC){
		$this->PlugIRC->requirePermission($MessIRC, "plugins.LIST");
		$plugins = self::getFullPlugins();
		$loaded = $this->PlugIRC->getAllPlugins(true);

		foreach($plugins as $key => $plg){
			if(in_array($plg, $loaded, true))
				continue;
			else
				$plugins[$key] = FontIRC::colour($plg, 4);
		}

		$reply = "Plugins: ".implode(", ", $plugins);
		$this->ConnIRC->notice($MessIRC->getNick(), $reply);
	}

	public function loadPlugin(MessIRC $MessIRC){
		$this->PlugIRC->requirePermission($MessIRC, "plugins.LOAD");
		$argv = $MessIRC->requireArguments(2);

		$res = $this->PlugIRC->loadPlugin($argv[1]);
		if($res === true)
			$this->ConnIRC->notice($MessIRC->getReplyTarget(), "Plugin successfully loaded.");
		elseif($res instanceof Exception){
			$msg = $res->getMessage();
			throw new Exception("Error: $msg");
		}
	}

	public function unloadPlugin(MessIRC $MessIRC){
		$this->PlugIRC->requirePermission($MessIRC, "plugins.UNLOAD");
		$argv = $MessIRC->requireArguments(2);
		$this->PlugIRC->unloadPlugin($argv[1]);
		$this->ConnIRC->notice($MessIRC->getReplyTarget(), "Plugin successfully unloaded.");
	}

	public function getPluginInfo(MessIRC $MessIRC){
		$this->PlugIRC->requirePermission($MessIRC, "plugins.INFO");
	}

	public static function getFullPlugins(){
		$plugins = array();
		foreach(scandir("plugins") as $filename){
			if(strrchr($filename, ".") == ".php")
				$plugins[] = substr($filename, 0, strlen($filename) - 4);
		}foreach(scandir(getenv('HOME')."/.config/aigis/plugins") as $filename){
			if(strrchr($filename, ".") == ".php")
				$plugins[] = substr($filename, 0, strlen($filename) - 4);
		}return $plugins;
	}
}
