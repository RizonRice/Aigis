<?php

class LinkInfo extends PlugIRC_Core{

const PLUGIN_NAME = "Link Information";
const PLUGIN_DESC = "Gives information on posted links.";

/* 
* For some reason (as of July 23rd, 2015) SoundCloud doesn't
* have a problem if the ID is "YOUR_CLIENT_ID", so don't
* worry about getting an API key.
*/

protected $requireConfig = true;
private $YOUTUBE_API_KEY = "";
private $SOUNDCLOUD_CLIENT_ID = "YOUR_CLIENT_ID"; 
private $prefixesToAvoid = array('s/', '!', '~');
private $IP = "";

public function __construct(AigisIRC $AigisIRC){
	parent::__construct($AigisIRC);
	$this->YOUTUBE_API_KEY = $this->configFile['apikeys']['youtube'];
	$this->SOUNDCLOUD_CLIENT_ID = $this->configFile['apikeys']['soundcloud'];
}

public function connect($time){
	// Get IP address so that it's censored when links contain it.
	$ipCheckCurl = self::getBody("http://checkip.dyndns.org/");
	if(preg_match(
        "/Current IP Address: ([0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3})/",
        $ipCheckCurl,
        $ipRegex
    )){
		$this->IP = $ipRegex[1];
		$this->consoleSend("IP resolved to $this->IP");
	}
}

public function privmsg(MessIRC $MessIRC){
	if($this->PlugIRC->getPermission($MessIRC, "link.PARSE_ANY") !== 2)
		return;

	if($MessIRC->parseCommand(array_merge($this->prefixes, $this->prefixesToAvoid)))
		return;

	if(preg_match_all('/(https?:\/\/[^\/]{0,1}\S*)/', $MessIRC->getMessage(), $regex)){
		foreach($regex[0] as $key => $link){
			$reply[] = str_replace($this->IP, "[nice try]", $this->URLReply($link, $MessIRC)); // Blocks own IP.
		}
		$this->ConnIRC->msg($MessIRC->getReplyTarget(), $reply);
	}
}

public function URLReply($link, MessIRC $MessIRC){
	$AigisPermissions = $this->PlugIRC->getPlugin("AigisPermissions");
	$headers = self::getHeaders($link);
	if(!preg_match("/\sContent-Type: ?([^\s;]+)/i", $headers, $regex))
		throw new Exception("LinkInfo::URLReply: Content-Type not found for ".$link);
	$contentType = $regex[1];

	if($contentType = "text/html"){
		if(!preg_match("/https?:\/\/([^\/]+)(?:\/(.*))?/i", FontIRC::stripStyles($link), $regex))
			throw new Exception("Malformed URL: ".$link);

		@list(,$domain, $page) = $regex;
		$site = $domain;
		if(substr_count($site, ".") > 1)
			$site = substr($site, 1 + strrpos($site, ".", strrpos($site, ".") - strlen($site) - 1));
		switch($site){
			case "youtube.com":
				$this->PlugIRC->requirePermission($MessIRC, "link.PARSE_YOUTUBE");
				if(preg_match("/^watch\?.*?v=([a-z0-9_-]+)/i", $page, $video))
					return "YouTube: ".$this->YouTube($video[1]);
			break;

			case "youtu.be":
				$this->PlugIRC->requirePermission($MessIRC, "link.PARSE_YOUTUBE");
				if(preg_match("/^([a-z0-9_-]+)/i", $page, $video))
					return "YouTube: ".$this->YouTube($video[1]);
			break;

			case "soundcloud.com":
				$this->PlugIRC->requirePermission($MessIRC, "link.PARSE_SOUNDCLOUD");
					return "SoundCloud: ".$this->SoundCloud($link);
				return;
			break;

			case "reddit.com":
				$this->PlugIRC->requirePermission($MessIRC, "link.PARSE_REDDIT");
				return "Reddit: ".$this->Reddit($link);
			break;

			default:
				$this->PlugIRC->requirePermission($MessIRC, "link.PARSE_DEFAULT");
				$urlTitle = self::URLTitle($link);
				if($urlTitle == "") return;
				return "URL: $urlTitle";
			break;
		}
	}
}


static public function URLTitle($url){
	$page = self::getAll($url);
	if(preg_match("/<title>([^<]+)<\/title>/mi", $page, $title)){
		$title = str_replace("\n", " ", $title[1]);
		return trim(html_entity_decode($title));
	}
}


/****************************

  Website-specific parsers. 

****************************/

// YouTube

public function YouTube($video, $returnRaw = false){
	$json = self::getBody("https://www.googleapis.com/youtube/v3/videos?part=contentDetails,snippet,statistics&id=$video&key=".$this->YOUTUBE_API_KEY);
	if($json == "Invalid id")
		return null;
	$data = json_decode($json, true);
	$info = $data['items'][0];
	if($returnRaw) return $info;
	$title = str_replace("''", "\"", $info['snippet']['title']);
	$duration = self::iso8601tohuman($info['contentDetails']['duration'], true);
	$uploader = $info['snippet']['channelTitle'];
	$date = date("F j, Y", strtotime($info['snippet']['publishedAt']));
	$likes = $info['statistics']['likeCount'];
	$dislikes = $info['statistics']['dislikeCount'];

	$reply = "$title [$duration]";
	return $reply;
}

public function Reddit($url) {
	// We don't support anything but comment threads just yet.
	if(!strstr($url, "/comments/")) {
		return null;
	}

	$urlparts = explode("/", str_replace("https://www.reddit.com/", "", $url));
	$id = $urlparts[3];

	$url = "https://www.reddit.com/by_id/t3_" . $id . ".json";
	$data = self::getJsonBody($url);

	if(isset($data['error'])){
		return " No such thread.";
	}

	$postInfo = $data["data"]["children"][0]["data"];
	// print_r($postInfo);
	$response = "";

	// Mark NSFW if applicable
	if($postInfo["over_18"]) {
		$response .= "[NSFW] ";
	}

	$response .= "/u/" . $postInfo["author"] . " posted ";

	// Truncate the title at around 100 characters
	$title = $postInfo["title"];
	if(strlen($title) > 99) {
		$title = substr($title, 0, 96) . "...";
	}
	$response .= "\"".$title."\"";

	// If it's a self-post, there's no actual link karma to report.
	$score = $postInfo["score"];
	if(!$postInfo["is_self"]) {
		$response .= " for " . $score . " link karma.";
	}

	return $response;
}

// SoundCloud

public function SoundCloud($url){
	$json = self::getJsonBody("http://api.soundcloud.com/resolve?url=".$url."&client_id=".$this->SOUNDCLOUD_CLIENT_ID);
	if(!isset($json['kind']))
		throw new Exception("LinkInfo::SoundCloud: No kind found in \"$url\".");
	switch($json['kind']){
		case "track":
			$track = $json["title"];
			$artist = $json["user"]["username"];
			$genre = $json["genre"];
			$favs = $json["favoritings_count"];
			return "Track: $track by $artist";
		break;

		case "user":
			$username = $json["username"];
			$location = $json["country"];
			if($json["city"] != "") $location = $json["city"].", ".$json["country"];
			$name = $json["full_name"];
			if($name == "") $name = "N/A";
			$site = $json["website"];
			if($site == "") $site = "N/A";
			$trackc = $json["track_count"];
			$followers = $json["followers_count"];
			return "User: $name [$username]";
		break;

		case "playlist":
			$title = $json["title"];
			$trackc = $json["track_count"];
			$username = $json["user"]["username"];
			return "Set: $title [$trackc tracks]";
		break;

		default:
			throw new Exception("LinkInfo::SoundCloud: Unknown link kind: ".$json['kind']);
		break;
	}
}

// 4chan

static public function fourchan($url){
	// Coming soon :O
	// :o
}


// Time convertion


static public function iso8601tohuman($iso8601, $returnString = false){
	$result = array("yr" => 0, "mon" => 0, "day" => 0, "hr" => 0, "min" => 0, "sec" => 0);
	$string = "";
	if(preg_match('/P(\d+Y)?(\d+M)?(\d+D)?T(\d+H)?(\d+M)?(\d+S)?/', $iso8601, $regex)){
		foreach($regex as $key => $value){
			switch($key){
				case 1: $result['yr']     = intval($value); if($value != 0) $string .= $result['yr']."yr ";   break;
				case 2: $result['mon']    = intval($value); if($value != 0) $string .= $result['mon']."mon "; break;
				case 3: $result['day']    = intval($value); if($value != 0) $string .= $result['day']."day "; break;
				case 4: $result['hr']     = intval($value); if($value != 0) $string .= $result['hr']."hr ";   break;
				case 5: $result['min']    = intval($value); if($value != 0) $string .= $result['min']."min "; break;
				case 6: $result['sec']    = intval($value); if($value != 0) $string .= $result['sec']."sec";  break;
			}
		}
	}
	if($returnString) return rtrim($string);
	else return $result;
	
}


// Functions to simplify the cURL code.

static public function getCurl($url){
	if (!extension_loaded("cURL"))
		throw new Exception("cURL module is required.");

	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($curl, CURLOPT_MAXREDIRS, 5);
	curl_setopt($curl, CURLOPT_TIMEOUT, 10);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	return $curl;
}

static public function getHeaders($url){
	$curl = self::getCurl($url);
	curl_setopt($curl, CURLOPT_HEADER, 1);
	curl_setopt($curl, CURLOPT_NOBODY, 1);
	$result = curl_exec($curl);
	curl_close($curl);
	return $result;
}

static public function getBody($url){
	$curl = self::getCurl($url);
	$result = curl_exec($curl);
	curl_close($curl);
	return $result;
}

static public function getJsonBody($url) {
	return json_decode(self::getBody($url), true);
}

static public function getAll($url){
	$curl = self::getCurl($url);
	curl_setopt($curl, CURLOPT_HEADER, 1);
	$result = curl_exec($curl);
	curl_close($curl);
	return $result;
}

}
