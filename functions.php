<?php

/* functions.php
 * GitHub: https://github.com/Joaquin-V/
 * A couple of functions I decided to make. consoleSend() intended for use with my IRC bot, Aigis.
 */

// Send formatted text to the console
//
// @param string $message Message to send.
// @param string $source  What part of the code is sending this message (ConnIRC, PlugIRC, etc.) A three-letter string can also be passed.
// @param string $type    Message type (information, warning, successful, send/receive) A two-letter string can also be passed.
// @param bool   $newline Send a new line at the end (useful for timers.)
// @return string The formatted message that was sent.

function consoleSend($message, $source = 'NUL', $type = 'info'){
        if($message == '' OR !is_string($message))
                return;
        $message = str_replace(array("\n","\\n"), "", $message);
        $sources = array("ConnIRC" => "IRC", "PlugIRC" => "PLG", "UserIRC" => "USR", "Error" => "ERR" , "Aigis" => "AIG");
        if(isset($sources[$source]))
                $src = strtoupper($sources[$source]);
        else{
                if(strlen(utf8_decode($source)) == 3)
                                $src = strtoupper($source);
                        else
                                $src = "???";
        }
        $types = array("info" => "--", "warning" => "\033[0;31m!!\033[0m", "success" => "\033[0;32m!!\033[0m", "send" => "->", "receive" => "<-");
        if(isset($types[$type]))
                $typechars = $types[$type];
        else{
                if(strlen(utf8_decode($type)) == 2)
                                $src = $type;
                        else
                                $src = "??";
        }
        $fullmsg = "[" . @date("d/m H:i:s") . "] $src $typechars $message\n";
        echo $fullmsg;
		return $fullmsg;
}

// Includes a directory of .php files.
// @param string $dir Directory of .php files.
function includeDirectory($dir){
	foreach (scandir($dir) as $entry) {
	//Ignore the . and .. dirs
		if($entry == "." or $entry == "..")
			continue;
		//Recursively run function for directories.
		elseif (is_dir(getcwd(). "/$dir/$entry"))
			includeDirectory("$dir/$entry");
		//Include .php files.
		elseif (strrchr($entry, ".") == ".php")
			include_once "$dir/$entry";
	}
}

// countBongs() - Gets the current time in Big Ben bongs.
// @return int Time in bongs.
function countBongs(){
	$londonDate = new DateTime(null, new DateTimeZone('Europe/London'));
	$bongs = $londonDate->format('h');
	return intval($bongs);
}