<?php
// For use with AigisPermissions.php
class AigisPermissions_DB{

private $PDO;
private $UserIRC;
private $network = "";

public function __construct(UserIRC $UserIRC, $network){

	$sqlite = "plugins/etc/AigisPermissions.sqlite";
	$this->PDO = new PDO("sqlite:$sqlite");
	$this->PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$this->network = $network;
	// Create the tables if they don't exist.
	$this->PDO->exec("CREATE TABLE IF NOT EXISTS 'permissions_channel'('channel' text    NOT NULL,   'network'  text, 'permission' text NOT NULL, 'value' tinyint(1) NOT NULL);");
	$this->PDO->exec("CREATE TABLE IF NOT EXISTS 'permissions_nick'   ('nick'    text    NOT NULL,   'network'  text, 'permission' text NOT NULL, 'value' tinyint(1) NOT NULL);");
	$this->PDO->exec("CREATE TABLE IF NOT EXISTS 'permissions_user'   ('userid'  INTEGER NOT NULL,   'network'  text, 'permission' text NOT NULL, 'value' tinyint(1) NOT NULL);");
	$this->PDO->exec("CREATE TABLE IF NOT EXISTS 'users'              ('userid'  INTEGER PRIMARY KEY NOT NULL,        'username' text NOT NULL,   'passwd' text NOT NULL);");

	$this->UserIRC = $UserIRC;
}

// *** SYNCING WITH DATABASE FUNCTIONS ***

// Sets all the user's permissions in the database.
public function fetchUserPermissions(UserIRC_User $user){
	$id = $user->getUserID();
	if($sth = $this->PDO->prepare("SELECT permission,value from permissions_user WHERE userid = :uid AND (network IS NULL OR network = :network)")){
		$sth->execute(array(
			':uid'     => $id,
			':network' => $this->network
		));
		consoleSend("Starting permission fetch for $id...");
		while($perms = $sth->fetch(PDO::FETCH_ASSOC)){
			$value = (bool) $perms['value'];
			$node  = AigisPermissions::getFormattedNode($perms['permission']);
			consoleSend("$id: $node => $value");
			$user->setNode($node, $value);
		}

	}else throw new Exception("Error parsing user database.");
}

// Returns all permissions in the nick/hostmask database.
public function fetchNickPermissions(){
	if($sth = $this->PDO->prepare("SELECT nick,permission,value from permissions_nick WHERE network IS NULL OR network = :network;")){
		$sth->execute(array(
			':network' => $this->network
		));
		return $sth->fetchAll();
	}else throw new Exception("Error parsing nick database.");
}

// Sets all the channel's permissions in the database.
public function fetchChanPermissions($channelName){
	$channel = $this->UserIRC->getChannel($channelName);
	if($sth = $this->PDO->prepare("SELECT permission,value from permissions_channel WHERE channel = :channel AND (network IS NULL OR network = :network)")){
		$sth->execute(array(
			':channel' => $channelName,
			':network' => $this->network
		));
		while($permRow = $sth->fetch(PDO::FETCH_ASSOC)){
			$value = (bool) $permRow['value'];
			$node  = AigisPermissions::getFormattedNode($permRow['permission']);
			$channel->setNode($node, $value);
		}
	}else throw new Exception("Error parsing channel database.");
}

public function setUserPermission($uid, $permission, $value){
	// Check if the permission has a row in the database for this user.
	if($exists = $this->PDO->prepare("SELECT COUNT(*) FROM permissions_user WHERE userid=:uid AND permission=:perm AND network = :network;")){
		$exists->execute(array(
			':uid'     => $uid,
			':perm'    => $permission,
			':network' => $this->network
		));
		if($exists->fetchColumn() === 0)
			$query = "INSERT INTO permissions_user VALUES(:uid,:net,:perm,:value);";
		else
			$query = "UPDATE permissions_user SET value=:value WHERE userid=:uid AND permission=:perm AND network=:net;";
	}else throw new Exception("Error parsing database.");

	if($sth = $this->PDO->prepare($query)){
		$sth->execute(array(
			':uid'   => $uid,
			':net'   => $this->network,
			':perm'  => $permission,
			':value' => $value
		));
	}else throw new Exception("Error updating database.");
}

public function setNickPermission($nick, $permission, $value){
	// Check if the permission has a row in the database for this user.
	if($exists = $this->PDO->prepare("SELECT COUNT(*) FROM permissions_nick WHERE nick=:nick AND permission=:perm AND network=:net;")){
		$exists->execute(array(
			':nick' => $nick,
			':perm' => $permission,
			':net'  => $network
		));
		if($exists->fetchColumn() === 0)
			$query = "INSERT INTO permissions_nick VALUES(:nick,:net,:perm,:value);";
		else
			$query = "UPDATE permissions_nick SET value=:value WHERE nick=:nick AND permission=:perm AND network=:net;";
	}else throw new Exception("Error parsing database.");

	if($sth = $this->PDO->prepare($query)){
		$stmt->execute(array(
			':nick'  => $nick,
			':net'   => $network,
			':perm'  => $permission,
			':value' => $value
		));
	}else throw new Exception("Error updating database.");
}

public function setChanPermission($chan, $permission, $value){
	// Check if the permission has a row in the database for this user.
	if($exists = $this->PDO->prepare("SELECT COUNT(*) FROM permissions_channel WHERE channel=:chan AND permission=:perm AND network=:net;")){
		$exists->execute(array(
			':chan' => $chan,
			':perm' => $permission,
			':net'  => $network
		));
		if($exists->fetchColumn() === 0)
			$query = "INSERT INTO permissions_channel VALUES(:chan,:net,:perm,:value);";
		else
			$query = "UPDATE permissions_channel SET value=:value WHERE channel=:chan AND permission=:perm AND network=:net;";
	}else throw new Exception("Error parsing database.");

	if($sth = $this->PDO->prepare($query)){
		$sth->execute(array(
			':chan'  => $chan,
			':net'   => $network,
			':perm'  => $permission,
			':value' => $value
		));
	}else throw new Exception("Error updating database.");
}

// *** LOGIN-RELATED FUNCTIONS ***

// Checks if a username is taken.
public function isTaken($username){
	if($sth = $this->PDO->prepare("SELECT * FROM users WHERE username=?;")){
		$sth->execute(array($username));
		return ($sth->rowCount() != 0);
	}else
		throw new Exception("Error searching database.");
}

// Creates a user.
public function registerUser($username, $password){
	if($sth = $this->PDO->prepare("INSERT INTO users (username, passwd) VALUES(?,?);")){
		$sth->execute(array($username, md5($password)));
		return $this->PDO->lastInsertId();;
	}else
		throw new Exception("Error adding user to database.");
}


// Checks password and returns UID of given user or false if login is invalid.
public function verifyPassword($username, $password){
	if($sth = $this->PDO->prepare("SELECT userid FROM users WHERE username=? AND passwd=?;")){
		$sth->execute(array($username, md5($password)));

		$uids = $sth->fetchAll(PDO::FETCH_NUM);
		if(count($uids) != 1)
			return false;
		return (int) $uids[0][0];
	}else
		throw new Exception("Error searching database.");
}

}
