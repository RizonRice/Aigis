<?php
// For use with AigisPermissions.php
class AigisPermissions_DB{

private $mysqli;
private $UserIRC;

private $network;

private $dbHost = "";
private $dbUser = "";
private $dbPass = "";
private $dbName = "";

public function __construct($dbConf, UserIRC $UserIRC, $network){
	foreach($dbConf as $setting => $value){
		switch($setting){
			case "host": $this->dbHost = $value; break;
			case "user": $this->dbUser = $value; break;
			case "pass": $this->dbPass = $value; break;
			case "name": $this->dbName = $value; break;
		}
	}

	$this->mysqli = new mysqli($this->dbHost, $this->dbUser, $this->dbPass, $this->dbName);
	if($this->mysqli->connect_error)
		throw new Exception("Error connecting to database.");

	$this->network = $network;

	// Create the tables if they don't exist.
	$this->mysqli->query(
	"CREATE TABLE IF NOT EXISTS `users` ( `userid` int(11) NOT NULL AUTO_INCREMENT, `username` varchar(30) NOT NULL, `passwd` text NOT NULL, PRIMARY KEY (`userid`), UNIQUE KEY `username` (`username`) ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
	 CREATE TABLE IF NOT EXISTS `permissions_user` ( `userid` int(11) NOT NULL, `network` text COLLATE utf8_bin, `permission` text COLLATE utf8_bin NOT NULL, `value` tinyint(1) NOT NULL ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
	 CREATE TABLE IF NOT EXISTS `permissions_channel` ( `channel` text NOT NULL, `network` text NOT NULL, `permission` text NOT NULL, `value` tinyint(1) NOT NULL ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");

	$this->UserIRC = $UserIRC;
}

// *** SYNCING WITH DATABASE FUNCTIONS ***

// Sets all the user's permissions in the database.
public function fetchUserPermissions(UserIRC_User $user){
	$network = $this->network;
	$id = $user->getUserID();
	if($stmt = $this->mysqli->prepare("SELECT permission,value from permissions_user WHERE userid = ? AND (network IS NULL OR network = ?)")){
		$stmt->bind_param("ss", $id, $network);
		$stmt->execute();
		$result = $stmt->get_result();
		$user->destroyNodeTree();
		while($permRow = $result->fetch_array()){
			$value = (bool) $permRow['value'];
			$node  = "permirc.permission.".$permRow['permission'];
			$user->setNode($node, $value);
		}
	}else throw new Exception("Error parsing user database.");
}

// Returns all permissions in the nick/hostmask database.
public function fetchNickPermissions(){
	$network = $this->network;
	if($stmt = $this->mysqli->prepare("SELECT nick,permission,value from permissions_nick WHERE network IS NULL OR network =?;")){
		$stmt->bind_param("s",$network);
		$stmt->execute();
		$result = $stmt->get_result();
		return $result->fetch_all(MYSQLI_ASSOC);
	}else throw new Exception("Error parsing nick database.");
}

// Sets all the channel's permissions in the database.
public function fetchChanPermissions($channelName){
	$network = $this->network;
	$channel = $this->UserIRC->getChannel($channelName);
	if($stmt = $this->mysqli->prepare("SELECT permission,value from permissions_channel WHERE channel = ? AND (network IS NULL OR network = ?)")){
		$stmt->bind_param("ss", $channelName, $network);
		$stmt->execute();
		$result = $stmt->get_result();
		while($permRow = $result->fetch_array()){
			$value = (bool) $permRow['value'];
			$node  = "permirc.permission.".$permRow['permission'];
			$channel->setNode($node, $value);
		}
	}else throw new Exception("Error parsing channel database.");
}

public function setUserPermission($uid, $permission, $value){
	// Check if the permission has a row in the database for this user.
	if($existsQuery = $this->mysqli->prepare("SELECT COUNT(*) FROM permissions_user WHERE userid=? AND permission=?;")){
		$existsQuery->bind_param("is", $uid, $permission);
		$existsQuery->execute();
		$result = $existsQuery->get_result();
		if($result->fetch_array()[0] === 0)
			$query = "INSERT INTO permissions_user (value,userid,permission,network) VALUES(?,?,?,'$this->network');";
		else
			$query = "UPDATE permissions_user SET value=? WHERE userid=? AND permission=? AND network='$this->network';";
	}else throw new Exception("Error parsing database.");

	if($stmt = $this->mysqli->prepare($query)){
		$stmt->bind_param("iis", $value, $uid, $permission);
		$stmt->execute();
	}else throw new Exception("Error updating database.");
}

public function setNickPermission($nick, $permission, $value){
	// Check if the permission has a row in the database for this user.
	if($existsQuery = $this->mysqli->prepare("SELECT COUNT(*) FROM permissions_nick WHERE nick=? AND permission=?;")){
		$existsQuery->bind_param("ss", $nick, $permission);
		$existsQuery->execute();
		$result = $existsQuery->get_result();
		if($result->fetch_array()[0] === 0)
			$query = "INSERT INTO permissions_nick (value,nick,permission,network) VALUES(?,?,?,'$this->network');";
		else
			$query = "UPDATE permissions_nick SET value=? WHERE nick=? AND permission=? AND network='$this->network';";
	}else throw new Exception("Error parsing database.");

	if($stmt = $this->mysqli->prepare($query)){
		$stmt->bind_param("iss", $value, $nick, $permission);
		$stmt->execute();
	}else throw new Exception("Error updating database.");
}

public function setChanPermission($chan, $permission, $value){
	// Check if the permission has a row in the database for this user.
	if($existsQuery = $this->mysqli->prepare("SELECT COUNT(*) FROM permissions_channel WHERE network='$this->network' AND channel=? AND permission=?;")){
		$existsQuery->bind_param("ss", $chan, $permission);
		$existsQuery->execute();
		$result = $existsQuery->get_result();
		if($result->fetch_array()[0] === 0)
			$query = "INSERT INTO permissions_channel (value,channel,permission,network) VALUES(?,?,?,'$this->network');";
		else
			$query = "UPDATE permissions_channel SET value=? WHERE channel=? AND network='$this->network' AND permission=?;";
	}else throw new Exception("Error parsing database.");

	if($stmt = $this->mysqli->prepare($query)){
		$stmt->bind_param("iss", $value, $chan, $permission);
		$stmt->execute();
	}else throw new Exception("Error updating database.");
}

// *** LOGIN-RELATED FUNCTIONS ***

// Checks if a username is taken.
public function isTaken($username){
	if($stmt = $this->mysqli->prepare("SELECT * FROM users WHERE username = ?;")){
		$stmt->bind_param("s", $username);
		$stmt->execute();

		return ($stmt->get_result()->num_rows != 0);
	}else
		throw new Exception("Error searching database.");
}

// Creates a user.
public function registerUser($username, $password){
	if($stmt = $this->mysqli->prepare("INSERT INTO users (username, passwd) VALUES(?,MD5(?));")){
		$stmt->bind_param("ss", $username, $password);
		$stmt->execute();
		return $this->mysqli->insert_id;
	}else
		throw new Exception("Error adding user to database.");
}


// Checks password and returns UID of given user or false if login is invalid.
public function verifyPassword($username, $password){
	if($stmt = $this->mysqli->prepare("SELECT * FROM users WHERE username = ? AND passwd = MD5(?);")){
		$stmt->bind_param("ss", $username, $password);
		$stmt->execute();
		$result = $stmt->get_result();

		if($result->num_rows != 1)
			return false;
		return (int) $result->fetch_array()['userid'];
	}else
		throw new Exception("Error searching database.");
}

// Ping the database
public function ping(){
	if(!($this->mysqli instanceof mysqli))
		$this->mysqli = new mysqli($this->dbHost, $this->dbUser, $this->dbPass, $this->dbName);

	if($this->mysqli->query("SELECT 1"))
		return true;
	else
		$this->mysqli = new mysqli($this->dbHost, $this->dbUser, $this->dbPass, $this->dbName);
	return true;
}

}
