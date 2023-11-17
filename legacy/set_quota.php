<?php
// Set quota name for a Logger

// V1.1 21.1.2023(C) JoEmbedded 
error_reporting(E_ALL);

include("../sw/conf/api_key.inc.php");
include("../sw/lxu_loglib.php");
session_start();
if (isset($_REQUEST['k'])) {
	$api_key = $_REQUEST['k'];
	$_SESSION['key'] = L_KEY;
} else $api_key = @$_SESSION['key'];
if (!strcmp($api_key, L_KEY)) {
	$dev = 1;	// Dev-Funktionen anzeigen
} else {
	$dev = 0;	// Dev-Funktionen anzeigen
}
if (!$dev) {
	echo "ERROR: Access denied!";
	exit();
}


//---------------- MAIN ---------------
header('Content-Type: text/plain');

$dbg = 0;	// Currently not used
$mac = @$_GET['s']; // 16 Chars, Uppercase
$now = time(); // Sec ab 1.1.1970, GMT

$udays = @$_POST['udays'];
if(!isset($udays)) $udays="";
$ulines = @$_POST['ulines'];
if(!isset($ulines)) $ulines="";
$upush = @$_POST['upush'];
if(!isset($upush)) $upush="";

if (!isset($mac) || strlen($mac) != 16) {
	exit_error("MAC Len");
}

if (!@file_exists(S_DATA . "/$mac")) exit_error("Error (Directory/MAC not found)");
//if(check_dirs()) exit_error("Error (Directory/MAC not found)");

$dpath = S_DATA . "/$mac/quota_days.dat";
if (strlen($udays) < 1 && strlen($ulines) < 1 && strlen($upush)) {
	echo "Quota/Push removed\n";
	$xlog = "(Quota/Push removed)";
	@unlink($dpath);
} else {
	$qstr = intval($udays) . "\n" . intval($ulines) . "\n$upush\n";
	if (intval($udays) < 1 || intval($ulines) < 10) {
		echo "ERROR: Invalid Values";
		exit();
	}
	echo "Quota set to: " . intval($udays) . "/" . intval($ulines) . "\n";
	$xlog = "(Set Quota to: " . intval($udays) . "/" . intval($ulines) . ", Push: '$upush')";
	$of = fopen($dpath, 'wb');
	fwrite($of, $qstr);
	fclose($of);
}

echo "OK (Back with Browser '<-')\n";
add_logfile(); // Regular exit
