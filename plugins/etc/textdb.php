<?php

class textdatabase{

	protected $maxLength = 350;
	protected $file = "";

	public function __construct($fileDir = "urldb.json", $maxLength = 350){
		// Ghetto file and directory creator.
		if(!file_exists($fileDir)){
			if(preg_match("/(.*\/).+/", $fileDir, $match)){
				$dir = $match[1];
				if(!is_dir($dir))
					mkdir($dir, 0755, true);
			}
			file_put_contents($fileDir, "{}");
		}
		$this->file = $fileDir;
		$this->maxLength = $maxLength;
	}

	public function getText($name){
		$db = $this->getDatabase();
		if(!isset($db[$name]))
			return null;
		return $db[$name];
	}

	public function setText($name, $text){
		$db = $this->getDatabase();

		if(strlen($text) > $this->maxLength)
			throw new Exception("Exceeded character limit.");

		$db[$name] = $text;
		$this->updateDatabase($db);
		return count($db[$name]);
	}

	public function deleteText($name){
		$db = $this->getDatabase();

		if(isset($db[$name])){
			unset($db[$name]);
			$this->updateDatabase($db);
			return true;
		}
		else return false;
	}

	public function getDatabase(){
		if(!file_exists($this->file))
			throw new Exception("Database file was deleted.");
		$json = file_get_contents($this->file);
		return json_decode($json, true);
	}

	private function updateDatabase($db){
		$json = json_encode($db);
		file_put_contents($this->file, $json);
	}

}
