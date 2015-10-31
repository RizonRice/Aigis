<?php

class LangIRC{

	const LANGUAGE_DIR = "lang";
	const FALLBACK_LANGUAGE = "en_GB";

	public function __construct(AigisIRC $AigisIRC){
		$this->AigisIRC = $AigisIRC;
	}

	public function openFiles(){
		
	}

	private function load(){
		if(!self::langExists($this->language)){
			if(self::langExists(self::FALLBACK_LANGUAGE)){
				$this->language = self::FALLBACK_LANGUAGE;
				return $this->load();
			}else throw new Exception("Specified and fallback languages don't exist.");
		}

		$confFile = parse_ini_file(self::LANGUAGE_DIR."/$language/lang.conf", true);

		$this->nativeName  = $confFile['general']['nativeName'];
		$this->englishName = $confFile['general']['englishName'];
		$this->author      = $confFile['general']['author'];
		$this->plugins     = $confFile['plugirc']['supported'];
	}

	public static function langExists($language){
		return file_exists(self::LANGUAGE_DIR."/$language/lang.conf");
	}



}
