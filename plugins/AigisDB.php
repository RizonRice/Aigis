<?php

class AigisDB extends PlugIRC_Core{

const PLUGIN_NAME = "AigisDB";
const PLUGIN_DESC = "Database manager for AigisIRC";

private $mysqli;

private $host;
private $user;
private $pass;

public function __construct(AigisIRC $AigisIRC){
	if(!extension_loaded("mysqlnd"))
		throw new Exception("PHP extension mysqlnd is not loaded!");
	$this->requireConfig = true;
	parent::__construct($AigisIRC);
	$this->loadConfig();
	$config = $this->configFile['sqlserver'];
	foreach($config as $setting => $value){
		switch($setting){
			case 'host': $this->host = $value; break;
			case 'user': $this->user = $value; break;
			case 'pass': $this->pass = $value; break;
		}
	}
	if(!@$this->connect())
		throw new Exception("Error connecting to database!");
}

public function connect(){
	if($this->mysqli = new mysqli($this->host, $this->user, $this->pass)){
		$this->mysqli->select_db("aigis");
		return true;
	}else return false;
}

public function query($query){
	return $this->mysqli->query($query);
}

public function prepare($query){
	return $this->mysqli->prepare($query);
}

public function insertID(){
	return $this->mysqli->insert_id;
}

// This function will run on IRC server ping or when called by external plugins.
public function ping($MessIRC = null){
if(!($this->mysqli instanceof mysqli))
	$this->connect;

if($this->mysqli->query("SELECT 1"))
	return true;
else
	$this->connect;
return true;
}

}
