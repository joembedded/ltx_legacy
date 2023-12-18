<?php
// Set initial D_API_KEY for this mac - ONLY included if $dapikey is not set/different for this MAC
// ** internal PART **
// 2 Versions possible A.)/B.)
// Info: global

if(!defined("DAPIKEY_SERVER")){
// A.) Simple: Use same D_API_KEY for all devices
	$xlog .= "(Use Default D_API_KEY)";
	$dapikey = D_API_KEY;
	$daksave = true;	// Save after creating Dirs
}else{
// B.) Individual: Via external API for predefined MAC/D_API_KEY pairs via CURL
	$qgetkey = DAPIKEY_SERVER."?k=".S_API_KEY."&d=$api_key&s=$mac";
	$ch = curl_init($qgetkey);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Info: Timeouts: 10 sec!
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
	$cres = curl_exec($ch);	
	$cinfo = curl_getinfo($ch);
	if (isset($cinfo['http_code']) && intval($cinfo['http_code'] != 200)) {
		exit_error("API Key Check Call"); // 
	}
	if (curl_errno($ch)) $xlog .= "(ERROR: check_dapikey:(" . curl_errno($ch) . "):'" . curl_error($ch) . "')";
	curl_close($ch);
	
	if(!isset($cres) || strcmp($cres,"CHECK OK")){
		exit_error("API Key Check Invalid");
	}
	$dapikey = $api_key;	// Key from Device was OK
	$daksave = true;	// Save after creating Dirs
}	
?>