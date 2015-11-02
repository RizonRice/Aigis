<?php

class curl{

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

	static public function getJson($url) {
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
