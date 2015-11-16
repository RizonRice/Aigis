<?php

require_once("plugins/etc/Permissions_DB.php");

class PermissionsDeniedException extends Exception {}

class AigisPermissions extends PlugIRC_Core{

const PLUGIN_NAME = "Permissions";
const PLUGIN_DESC = "Permissions manager.";

private $database = null;
private $nickPerms = array();

public function __construct(AigisIRC $AigisIRC){
	parent::__construct($AigisIRC);

	$this->database = new AigisPermissions_DB($this->UserIRC, $this->ConnIRC->getNetwork());

	$nickPerms = $this->database->fetchNickPermissions();
	foreach($nickPerms as $permission){
		$nick = $permission['nick'];
		$perm = $permission['permission'];
		$val  = $permission['value'];
		if(!isset($this->nickPerms[$nick]))
			$this->nickPerms[$nick] = array();
		$this->nickPerms[$nick][$perm] = $val;
	}

	$this->triggers = array(
	"usermod" => "userMod",
	"umod"    => "userMod",
	"chanmod" => "channelMod",
	"cmod"    => "channelMod",

	"login"     => "login",
	"register"  => "register",
	"logout"    => "logout",

	"aenable"  => "enableCommand",
	"aen"      => "enableCommand",
	"adisable" => "disableCommand",
	"adis"     => "disableCommand"
);

$this->PlugIRC->setDefaultPerms(array("aigisperms.MODIFY_PERMISSIONS" => false, "permirc.TOGGLE_COMMANDS" => false));

}

// ** Permission modifying commands **

public function userMod(MessIRC $MessIRC){
	$this->PlugIRC->requirePermission($MessIRC, "aigisperms.MODIFY_PERMISSIONS");
	$args = $MessIRC->requireArguments(3);

	$nickToModify = $args[0];
	$permissionToChange = $args[1];
	$permissionValue = self::parseBoolString($args[2]);

	if(strpos($nickToModify,"mask:") !== false){
		$this->setNickPermission($nickToModify, $permissionToChange, $permissionValue);
		$this->ConnIRC->msg($MessIRC->getReplyTarget(), "$permissionToChange has been set to {$args[2]} for people with hostmask ".substr($nickToModify,5));
	}elseif(strpos($nickToModify,"uid:") !== false){
		$uid = substr($nickToModify,4);
		if(!is_numeric($uid))
			throw new Exception("User ID must be numeric.");
		$this->database->setUserPermission($uid, $permissionToChange, $permissionValue);
		$this->ConnIRC->msg($MessIRC->getReplyTarget(), "$permissionToChange has been set to {$args[2]} for User ID $uid.");
	}elseif(preg_match("/(.*)@(.*)/", $nickToModify, $match)){
		$nick = $match[1];
		$chan = $match[2];
		$this->setNickPermission($nickToModify, $permissionToChange, $permissionValue);
		$this->ConnIRC->msg($MessIRC->getReplyTarget(), "$permissionToChange has been set to {$args[2]} for $nick on $chan.");
	}else{
		$this->setUserPermission($nickToModify, $permissionToChange, $permissionValue);
		$this->ConnIRC->msg($MessIRC->getReplyTarget(), "$permissionToChange has been set to {$args[2]} for $nickToModify.");
	}
}

public function channelMod(MessIRC $MessIRC){
	$this->PlugIRC->requirePermission($MessIRC, "aigisperms.MODIFY_PERMISSIONS");

	$args = $MessIRC->requireArguments(3);
	$chanToModify = $args[0];
	$permToChange = $args[1];
	$permValue    = self::parseBoolString($args[2]);

	$this->setChanPermission($chanToModify, $permToChange, $permValue);
	$this->ConnIRC->msg($MessIRC->getReplyTarget(), "$permToChange has been set to {$args[2]} for $chanToModify.");
}

// ** Login/Logout commands **

public function login(MessIRC $MessIRC){
	if($MessIRC->inChannel())
		throw new Exception("What are you doing? Everyone on the channel can see your password! Send a PM.");
	$args = $MessIRC->requireArguments(2);
	$nick = $MessIRC->getNick();
	$user = $this->UserIRC->getUser($nick);
	$username = $args[0];
	$password = $args[1];

	if($user->loginStatus())
		throw new Exception("You're already logged in.");

	$uid = $this->database->verifyPassword($username, $password);
	if($uid === false)
		throw new Exception("Invalid username/password combination.");

	$user->setLogin($uid, $username);
	$this->database->fetchUserPermissions($user, $uid);

	$this->ConnIRC->msg($MessIRC->getReplyTarget(), "Logged in successfully.");
}

public function register(MessIRC $MessIRC){
	if($MessIRC->inChannel())
		throw new Exception("What are you doing? Everyone on the channel can see your password. Send a PM.");
	$args = $MessIRC->requireArguments(3);
	$nick = $MessIRC->getNick();
	$user = $this->UserIRC->getUser($nick);
	$username = $args[0];
	$password = $args[1];
	$confpass = $args[2];

	if($user->loginStatus())
		throw new Exception("You're already logged in.");

	if(strlen($username) > 30)
		throw new Exception("Username is too long.");

	if($this->database->isTaken($username))
		throw new Exception("Username is taken.");

	if($password !== $confpass)
		throw new Exception("Passwords do not match.");

	$id = $this->database->registerUser($username, $password);

	$user->setLogin($id, $username);

	$this->ConnIRC->msg($MessIRC->getReplyTarget(), "Registered successfully. Now logging you in...");
}

public function logout(MessIRC $MessIRC){
	$user = $this->UserIRC->getUser($MessIRC->getNick());

	if(!$user->loginStatus())
		throw new Exception("You're not logged in.");

	$user->logout();
	$user->destroyNodeTree();
	$this->ConnIRC->msg($MessIRC->getReplyTarget(), "Logged out successfully.");
}

 // ** Permission getting/setting functions **

public function getUserPermission($user, $permission){
	$userObj = $this->UserIRC->getUser($user);
	$nodeName = self::getFormattedNode($permission);
	$nodeValue = $userObj->getNode($nodeName);

	if(is_null($nodeValue) && isset($this->nickPerms[$user][$permission]))
		return $this->getNickPermission($user, $permission);
	return $nodeValue;
}

public function setUserPermission($nick, $permission, $value){
	$userObj = $this->UserIRC->getUser($nick);
	if(!$userObj->loginStatus()){
		$this->setNickPermission($nick, $permission, $value);
		return;
	}

	$nodeName = self::getFormattedNode($permission);

	if(!is_bool($value))
		throw new Exception("Given value must be boolean.");

	$userObj->setNode($nodeName, $value);
	if($userObj->loginStatus()){
		$this->database->setUserPermission($userObj->getUserID(), $permission, $value);
	}else
		$this->database->setNickPermission($nick, $permission, $value);
	
}

public function getNickPermission($nick, $permission){
	if(isset($this->nickPerms[$nick][$permission]))
		return $this->nickPerms[$nick][$permission];
	return null;
}

public function setNickPermission($nick, $permission, $value){
	if(!isset($this->nickPerms[$nick]))
		$this->nickPerms[$nick] = array();
	$this->nickPerms[$nick][$permission] = $value;
	$this->database->setNickPermission($nick, $permission, $value);
	// If it's not a hostmask, add the node to the user object. This prevents people from avoiding permission changes by changing nick.
	if(strpos($nick, 'mask:') === false){
		$user = $this->UserIRC->getUser($nick);
		$user->setNode(self::getFormattedNode($permission), $value);
	}
}

public function getChanPermission($channel, $permission){
	$this->database->fetchChanPermissions($channel);
	$chanObj  = $this->UserIRC->getChannel($channel);
	$nodeName = self::getFormattedNode($permission);
	$nodeValue = $chanObj->getNode($nodeName);

	if(!is_bool($nodeValue))
		return self::parseBoolString($nodeValue);

	return $nodeValue;
}

public function setChanPermission($channel, $permission, $value){
	$chanObj  = $this->UserIRC->getChannel($channel);
	$nodeName = self::getFormattedNode($permission);

	if(!is_bool($value))
		throw new Exception("Given value must be boolean.");

	$this->database->setChanPermission($channel, $permission, $value);
	$chanObj->setNode($nodeName, $value);
}

public function permissionParser(MessIRC $MessIRC, $permission, $defaults){
	$nick = $MessIRC->getNick();
	$mask = $MessIRC->getHostmask();
	$chan = $MessIRC->getReplyTarget();

	$nickValue = $this->getUserPermission($nick, $permission);
	$maskValue = $this->getNickPermission("mask:$mask", $permission);
	if($MessIRC->inChannel())
		$chanValue = $this->getChanPermission($chan, $permission);
	else
		$chanValue = true;


	if(is_null($nickValue)) $nickValue = $defaults['user'];
	if(is_null($chanValue)) $chanValue = $defaults['chan'];

	if(!$chanValue)
		return 0;
	elseif(!$nickValue)
		return 1;
	else
		return 2;
}

// ** Enable/disable commands **
// These functions deny any plugin parse commands by trigger.

public function enableCommand(MessIRC $MessIRC){
	if(!$MessIRC->inChannel())
		throw new Exception("This command only works in channels.");
	$nickModes = $this->UserIRC->getUser($MessIRC->getNick())->getModes($MessIRC->getReplyTarget());
	if(!in_array("h", $nickModes) && !in_array("o", $nickModes))
		$this->PlugIRC->requirePermission($MessIRC, "permirc.TOGGLE_COMMANDS");

	$command = strtolower($MessIRC->requireArguments(1)[0]);

	$full = $this->PlugIRC->requirePlugin("Help")->getFullCommand($command);
	if($full === null)
		throw new Exception("Unknown command: $command");

	$this->setChanPermission($MessIRC->getReplyTarget(), "command.$full", true);
	$this->ConnIRC->msg($MessIRC->getReplyTarget(), "Enabled $full.");
}

public function disableCommand(MessIRC $MessIRC){
	if(!$MessIRC->inChannel())
		throw new Exception("This command only works in channels.");
	$nickModes = $this->UserIRC->getUser($MessIRC->getNick())->getModes($MessIRC->getReplyTarget());
	if(!in_array("h", $nickModes) && !in_array("o", $nickModes))
		$this->PlugIRC->requirePermission($MessIRC, "permirc.TOGGLE_COMMANDS");

	$command = strtolower($MessIRC->requireArguments(1)[0]);

	$full = $this->PlugIRC->requirePlugin("Help")->getFullCommand($command);
	if($full === null)
		throw new Exception("Unknown command: $command");

	$this->setChanPermission($MessIRC->getReplyTarget(), "command.$full", false);
	$this->ConnIRC->msg($MessIRC->getReplyTarget(), "Disabled $full.");
}

// ** Static functions **

// Return UserIRC node of permission.
public static function getFormattedNode($permission){
	return "permirc.permission.$permission";
}

// Parse allow/deny/true/false/whatever string and return a boolean value.
public static function parseBoolString($value){
	switch($value){
		case 'allow':
		case 'true':
		case 'yes':
		case 'on':
		case '1':
			return true;
		break;
		case 'deny':
		case 'false':
		case 'no':
		case 'off':
		case '0':
			return false;
		break;
		default:
			return null;
		break;
	}
}


}
