<?php

require_once "plugins/etc/curl.php";
class WebStuff extends PlugIRC_Core{

	const PLUGIN_NAME = "WebStuff";
	const PLUGIN_DESC = "Fun web-based commands.";

	const LASTFM_USERS = "plugins/etc/lastfm.json";

	// General array for API keys.
	protected $apikeys = array();

	// Last.FM
	public $lfmUsers = array();
	public $lfmFlags = array(
		'set'  => array('-s', '--set', '-a', '--add'),
		'user' => array('-u', '--user')
		);


	public function __construct(AigisIRC $AigisIRC){
		parent::__construct($AigisIRC);

		$this->apikeys = $this->configFile['keys'];

		$network = $this->ConnIRC->getNetwork();
		$db      = json_decode(file_get_contents(self::LASTFM_USERS), true);
		if(isset($db[$network]))
			$this->lfmUsers = $db[$network];

		$this->triggers = array(
			// Last.FM scrobble.
			"nowplaying" => "LastFM",
			"np"         => "LastFM",
			// Google search.
			"google" => "Google",
			"g"      => "Google"
		);
	}


	public function LastFM(MessIRC $MessIRC){
		$this->PlugIRC->getPermission($MessIRC, "web.LASTFM");
		if(!isset($this->apikeys['lastfm']))
			throw new Exception("No Last.FM API key was found.");

		$argv = $MessIRC->getArguments();
		// User asking for their own track.
		if(!isset($argv[0]))
			$username = $this->getLFMUser($MessIRC->getNick());
		// User sent a flag.
		elseif(strpos($argv[0], "-") === 0){
			// Set Last.FM user.
			if(in_array($argv[0], $this->lfmFlags['set']))
				return $this->setLFMUser($MessIRC);
			// Get specific Last.FM user.
			elseif(in_array($argv[0], $this->lfmFlags['user']))
				$username = $MessIRC->requireArguments(2)[1];
			else
				throw new Exception('Usage: '.$MessIRC->command().
					' [IRC nick] [--user <Last.FM user>] [--set <Last.FM user>]');
		}
		// User is asking for someone else's username.
		else
			$username = $this->getLFMUser($argv[0]);

		// Start scrobbling process.
		$query_args = array(
			'method'  => 'user.getRecentTracks',
			'api_key' => $this->apikeys['lastfm'],
			'format'  => 'json',
			'limit'   => 1,
			'user'    => $username
		);
		$query = "http://ws.audioscrobbler.com/2.0/?".http_build_query($query_args);
		$json  = curl::getJson($query);

		if(isset($json['error']))
			throw new Exception($json['message']);

		$track = $json['recenttracks']['track'][0];
		if(!isset($track['@attr']['nowplaying']))
			return $this->ConnIRC->msg($MessIRC->getReplyTarget(),
				$MessIRC->getNick()." isn't playing anything right now.");

		$artist = $track['artist']['#text'];
		$name   = $track['name'];
		$this->ConnIRC->msg($MessIRC->getReplyTarget(),
			"[".$MessIRC->getNick()."] ".
			FontIRC::italic("$artist - $name"));
	}

	public function setLFMUser(MessIRC $MessIRC){
		$file    = file_get_contents(self::LASTFM_USERS);
		$decode  = json_decode($file, true);
		$network = $this->ConnIRC->getNetwork();
		$nick    = strtolower($MessIRC->getNick());
		$user    = $MessIRC->requireArguments(2)[1];

		if(!isset($decode[$network]))
			$decode[$network] = array();

		$decode[$network][$nick] = $user;

		$json = json_encode($decode);
		$this->lfmUsers = $decode[$network];
		file_put_contents(self::LASTFM_USERS, $json);
		throw new Exception("Your Last.FM username has been set.");
	}

	public function getLFMUser($nick){
		$lnick = strtolower($nick);

		if(isset($this->lfmUsers[$lnick]))
			return $this->lfmUsers[$lnick];
		else return $nick;
	}

	public function Google(MessIRC $MessIRC){
		$this->PlugIRC->getPermission($MessIRC, "web.GOOGLE");
		$search = implode(" ", $MessIRC->requireArguments(1));

		$req =
		"http://ajax.googleapis.com/ajax/services/search/web?v=1.0&rsz=1&q=".urlencode($search);
		$json = curl::getJson($req);

		if(!isset($json['responseData']['results'][0]))
			throw new Exception("No results found for \"$search\"");

		$result = $json['responseData']['results'][0];
		$URL    = $result['url'];
		$cont =
		str_replace("\n", "", self::IRC2HTML($result['content']));
		$cont = preg_replace_callback("/(&#[0-9]+;)/",
			function($m){
				return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
			}, $cont);

		$this->ConnIRC->msg($MessIRC->getReplyTarget(),
			FontIRC::italic($URL)." - $cont");
	}

	public static function IRC2HTML($HTML){
		// Unicode/HTML entities.
		$HTML = html_entity_decode($HTML);
		$HTML = preg_replace_callback("/(&#[0-9]+;)/",
			function($m){ return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"); },
			$HTML);

		// Bold, italic, underline, etc.
		$HTML = preg_replace("/<b>(.*)<\/b>/", FontIRC::bold('$1'), $HTML);
		$HTML = preg_replace("/<i>(.*)<\/i>/", FontIRC::italic('$1'), $HTML);
		$HTML = preg_replace("/<u>(.*)<\/u>/", FontIRC::underline('$1'), $HTML);

		// Remove extra HTML.
		$HTML = preg_replace("/<[^>]*>/s", "", $HTML);

		// Trim
		$HTML = trim($HTML);

		return $HTML;
	}
}
