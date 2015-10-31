<?php

/* AigisIRC - TermIRC
 * Terminal output manager for AigisIRC.
 * GitHub: Joaquin-V
 */

class TermIRC{

	const LOG_FILE = "console.log";
	private $logFile;

	const TERM_TIMESTAMP = "d/m H:i:s";
	const FILE_TIMESTAMP = "d/m/Y H:i:s";

	static private $types = 
		array("info"      => "--",
			"warning" => "!!",
			"success" => "--", // Deprecated
			"send"    => "->",
			"receive" => "<-");
	static private $sources =
		array("ConnIRC"   => "IRC",
			"PlugIRC" => "PLG",
			"UserIRC" => "USR",
			"Error"   => "ERR",
			"Aigis"   => "AIG",
			"Verbose" => "VBS");

	private $AigisIRC;
	private $verbose = false;

	public function __construct(AigisIRC $AigisIRC){
		$this->logFile = fopen(self::LOG_FILE, "w");
		$this->AigisIRC = $AigisIRC;
	}

	public static function format($message, $source = 'NUL', $type = 'info'){
		if($message == '' OR !is_string($message))
			return false;

		$message = str_replace("\n", " ", $message);
		if(isset($this->sources[$source])) 
			$src = strtoupper(self::$sources[$source]);
		elseif(strlen(utf8_decode($source)) === 3)
			$src = strtoupper($source);
		else $src = "NUL";

		if(isset(self::$types[$type]))          $tp = $this->types[$type];
		elseif(strlen(utf8_decode($type)) == 2) $tp = $type;
		else                                    $tp = "??";

		$format = "$src $typechard $message\n";
		return $format;
	}

	public static function timeStamp($file = false){
		if($file)
			return "[".date(self::FILE_TIMESTAMP)."]";
		else
			return "[".date(self::TERM_TIMESTAMP)."]";
	}

	public function consoleSend($message, $source = 'NUL', $type = 'info'){
		$format = self::format($message, $source = 'NUL', $type = 'info');

		fwrite(STDOUT, 
	}

	public function verboseSend($message, $source = 'NUL', $type = 'info'){
		$format = self::format($message, $source = 'NUL', $type = 'info');
	}

	public static function stderrSend(){

	}
}
