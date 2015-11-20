<?php

class FontIRC{

// FontIRC
// Part of AigisIRC (https://github.com/Joaquin-V/AigisIRC)

	public static function bold($text){
		return "\x02$text\x02";
	}

	public static function italic($text){
		return "\x1D$text\x1D";
	}

	public static function underline($text){
		return "\x1F$text\x1F";
	}

	public static function terminate($text = ""){
		return "$text\x0F";
	}

	public static function colour($text, $fg = "", $bg = null){
		$cstring = "\x03$fg";
		//If a bg is defined, append the comma
		if(isset($bg))
			$cstring .= ",$bg";
		$formatted = $cstring . $text . "\x03";
		return $formatted;
	}

	public static function stripStyles($text){
		if(!is_string($text))
			return "";
		//Strip colours
		$text = preg_replace('/\x03\d{1,2}(,\d{1,2})?/', "", $text);

		//Strip everything else
		$text = str_replace(array(chr(2), chr(3), chr(15), chr(22), chr(29), chr(31)), "", $text);

		return $text;
	}

	public static function arr($array){
		$formatted = str_replace(
			array('[bold]', '[italic]', '[underline]',
			'[colour]', '[color]'),
			array("\x02", "\x1D", "\x1F",
				"\x03", "\x03"), 
			$array);

		return $formatted;
	}

	public static function HTML2IRC($HTML){
		$IRC = html_entity_decode($HTML);
		$IRC = preg_replace_callback("/(&#[0-9]+;)/",
			function($m){ return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"); },
			$IRC);

		// HTML tags for bold, italic, underline and underline.
		$IRC = preg_replace("/<b>(.*)<\/b>/", self::bold('$1'), $IRC);
		$IRC = preg_replace("/<i>(.*)<\/i>/", self::italic('$1'), $IRC);
		$IRC = preg_replace("/<u>(.*)<\/u>/", FontIRC::underline('$1'), $IRC);

		// Remove extra HTML.
		$IRC = preg_replace("/<[^>]*>/s", "", $IRC);

		return trim($IRC);
	}
}

?>
