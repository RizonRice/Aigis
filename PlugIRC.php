<?php

require_once "PlugIRC_Core.php";

// Include plugins.
includeDirectory("plugins");
includeDirectory(getenv("HOME")."/.config/aigis/plugins");

class NoticeException extends Exception {}
class PlugIRC{

// PlugIRC
// Part of AigisIRC (https://github.com/Joaquin-V/AigisIRC)

private $plugins = array();
private $defaultPrefixes = array();

private $defaultPerms = array();
private $permissionPlugin = "";

private $AigisIRC = null;
private $ConnIRC = null;

public function __construct(AigisIRC $AigisIRC, $permissionPlugin = "AigisPermissions"){
	$this->AigisIRC = $AigisIRC;
	//$this->loadPlugin($permissionPlugin);
	$this->permissionPlugin = $permissionPlugin;
}

public function loadPlugin($plugin){
	// Check if plugin file is loaded.
	if(!class_exists($plugin) || !is_subclass_of($plugin, "PlugIRC_Core")){
		consoleSend("Plugin \"$plugin\" doesn't exist.", "PlugIRC", "warning");
		return false;
	}
	try{
		$this->plugins[$plugin] = new $plugin($this->AigisIRC);
	}catch(Exception $e){
		unset($this->plugins[$plugin]);
		consoleSend("Error loading $plugin: ".$e->getMessage(), "PlugIRC");
		return $e;
	}
	consoleSend("Loaded $plugin.", "PlugIRC", "success");
	return true;
}

public function unloadPlugin($plugin){
	if(isset($this->plugins[$plugin]))
		unset($this->plugins[$plugin]);
}

public function pluginSend($plugin, $type, $data){
	if(method_exists($plugin, $type))
		$plugin->$type($data);
}

public function pluginSendAll($type, $data){
	foreach($this->plugins as $class => $plugin){
		$this->pluginSend($plugin, $type, $data);
	}
}

public function getPlugin($plugin){
	if(!isset($this->plugins[$plugin]))
		return null;
	return $this->plugins[$plugin];
}

public function getPrefixes($plugin){
	if(!isset($this->plugins[$plugin]))
		return null;
	return $this->plugins[$plugin]->getPrefixes();
}

public function getAllPlugins($list= false){
	if(!$list)
		return $this->plugins;
	$list = array();
	foreach($this->plugins as $name => $object){
		$list[] = $name;
	}
	return $list;
}

public function requirePlugin($plugin){
	if(!isset($this->plugins[$plugin]))
		throw new Exception("Required plugin: $plugin");
	return $this->plugins[$plugin];
}

public function pluginLoaded($plugin){
	return isset($this->plugins[$plugin]);
}

public function setPrefix($prefix){
	if(is_array($prefix)){
		foreach($prefix as $prfx)
			$this->setPrefix($prfx);
		return;
	}
	$this->defaultPrefixes[] = $prefix;
}

public function getDefaultPrefixes(){
	return $this->defaultPrefixes;
}

// Functions for easier permission management.

public function getPermissionPlugin(&$name = null){
	return $this->getPlugin($this->permissionPlugin);
}

public function getPermission(MessIRC $MessIRC, $permName){
	$perms = $this->getPermissionPlugin();
	if(!isset($this->defaultPerms[$permName]))
		$this->defaultPerms[$permName] = true;
	if(!isset($this->defaultPermsChan[$permName]))
		$this->defaultPermsChan[$permName] = true;

	$defaults = array("user" => $this->defaultPerms[$permName], "chan" => $this->defaultPermsChan[$permName]);
	if($perms == null)
		return $this->defaultPerms[$permName];
	return $perms->permissionParser($MessIRC, $permName, $defaults);
}

public function requirePermission(MessIRC $MessIRC, $permName){
	$permission = $this->getPermission($MessIRC, $permName);
	if($permission == -1)
		throw new Exception("PlugIRC::requirePermission: No permission plugin loaded.");
	elseif($permission == 0)
		throw new Exception("");
	elseif($permission == 1)
		throw new NoticeException("PlugIRC::requirePermission: Permission denied.");
}

public function setDefaultPerms($permissions, $chanPerms = false){
	if(!is_array($permissions))
		return false;
	foreach($permissions as $perm => $value){
		if(!is_bool($value))
			continue;
		if($chanPerms)
			$this->defaultPermsChan[$perm] = $value;
		else
			$this->defaultPerms[$perm] = $value;
	}return true;
}

}
