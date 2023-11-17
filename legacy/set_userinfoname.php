<?php
// Set user_info name for a Logger

// V1.0 2018(C) JoEmbedded 
// todo: --- authenticate user

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

$uiname = @$_POST['uiname'];

if (!isset($mac) || strlen($mac) != 16) {
	exit_error("MAC Len");
}

if (!@file_exists(S_DATA . "/$mac")) exit_error("Error (Directory/MAC not found)");
//if(check_dirs()) exit_error("Error (Directory/MAC not found)");

$dpath = S_DATA . "/$mac/user_info.dat";
if (strlen($uiname) < 1) {
	echo "Name removed\n";
	$xlog = "(Name removed)";
	@unlink($dpath);
} else {
	echo "Legacy Name set to: '$uiname'\n";
	$xlog = "(Set Legacy Name to: '$uiname')";
	$of = fopen($dpath, 'wb');
	fwrite($of, $uiname);
	fclose($of);
}

echo "OK (Back with Browser '<-')\n";
add_logfile(); // Regular exit
