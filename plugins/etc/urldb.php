<?php

class urldb{

	protected $maxURLs = 5;
	protected $file = "";
	protected $validate = true;

	public function __construct($fileDir = "urldb.json", $maxURLs = 5, $validate = true){
		// Ghetto file and directory creator.
		if(!file_exists($fileDir)){
			if(preg_match("/(.*\/).+/", $fileDir, $match)){
				$dir = $match[1];
				if(!is_dir($dir)){
					mkdir($dir, "0770", true);
					consoleSend("Created directory $dir and made $fileDir");
				}
			}
			file_put_contents($fileDir, "{}");
		}
		$this->file = $fileDir;
		$this->maxURLs = $maxURLs;
		$this->validate = $validate;
	}

	public function getURLs($name){
		$db = $this->getDatabase();
		if(!isset($db[$name]))
			return array();
		return $db[$name];
	}

	public function addURL($name, $URL){
		$db = $this->getDatabase();
		if(!isset($db[$name]))
			$db[$name] = array();
		if(!self::checkURL($URL) and $this->validate)
			throw new Exception("Invalid URL: $URL");

		if(count($db[$name]) >= $this->maxURLs)
			throw new Exception("Entry limit has been reached.");

		$db[$name][] = $URL;
		$this->updateDatabase($db);
		return count($db[$name]);
	}

	public function deleteURL($name, $id){
		$db = $this->getDatabase();
		if(is_array($id) or isset($db[$name][$id])){
			// Support for passing an entire array of IDs.
			if(is_array($id)){
				foreach($id as $num){
					if(isset($db[$name][$num]))
						unset($db[$name][$num]);
				}
			}else 
				unset($db[$name][$id]);

			// Because of this line right here, I highly recommend
			// you just pass all IDs as an array in one go.
			$db[$name] = array_values($db[$name]);
			$this->updateDatabase($db);
			return true;
		}
		else return false;
	}

	public function replaceURL($name, $id, $new){
		$db = $this->getDatabase();
		if(!isset($db[$name][$id]))
			return false;

		$db[$name][$id] = $new;
		$this->updateDatabase($db);
		return true;
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

	static public function checkURL($URL){
		if(filter_var($URL, FILTER_VALIDATE_URL) === false){
			return false;
		}
		else return true;
	}
}
