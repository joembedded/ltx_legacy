<?php
	error_reporting(E_ALL);
	include("api_key.inc.php");

	// _extern_check_dapikey.php  - JoEmbedded.de
	
	// ---FRAGMENT/SAMPLE MODIFY and use on external !---
	// ---FRAGMENT/SAMPLE MODIFY and use on external !---
	
	// Check api_key for a new device from a e.g. a MAC-KEY-List. Return 'CHECK OK' (or 'ERROR', ..);
	// Only located in this dir for DEMO because S_API_KEY is used to check access
	 
	// Stand alone test: http://localhost/ltx/sw/conf/_extern_check_dapikey.php?k=LegacyLTX&d=xSintXtl&s=0011223344556677 )

	// --- MAIN ---
	header('Content-Type: text/plain');
	$dapi_key = @$_GET['d'];				// apiKey from device
	$sapi_key = @$_GET['k'];				// To check access
	$mac = @$_GET['s']; 				// 16 Zeichen. api_key and mac identify device

	if (!isset($mac) || strlen($mac) != 16) {
		die("MAC Len");
	}
	if (!isset($sapi_key) || strcmp($sapi_key, S_API_KEY)) {
		die("NO ACCESS (API)");
	}
	
	// Check against Default D_API_KEY ---FRAGMENT/SAMPLE MODIFY---
	if(!isset($dapi_key) || strcmp($dapi_key, D_API_KEY)) {
		die("INVALID Key");
	}
	// Check against Default D_API_KEY ---FRAGMENT/SAMPLE MODIFY---

	echo "CHECK OK"; // Key from Device is OK
?>