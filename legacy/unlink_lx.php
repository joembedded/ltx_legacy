<?php
// Unlink File
// Fragment for a small cmd-File to remove 1-2 files
// V1.0 2018(C) JoEmbedded 
// todo: --- authenticate use

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
$fnames = @$_GET['f']; // 16 Chars, Uppercase
$fnames2 = @$_GET['f2']; // 16 Chars, Uppercase

if (!isset($mac) || strlen($mac) != 16) {
	exit_error("MAC Len");
}

if (!@file_exists(S_DATA . "/$mac")) exit_error("Error (Directory/MAC not found)");

$xlog = "";

echo "Remove File '$fnames' for Device '$mac'\n";
$fnamel = S_DATA . "/$mac/$fnames";
if (!@file_exists($fnamel)) {
	$xlog .= "(File '$mac/$fnames' not found, can't remove)";
} else {
	if (unlink($fnamel) == false) {
		exit_error("unlink('$mac/$fnames)' failed"); // Fatal
	}
	$xlog .= "(File removed:'$mac/$fnames')";
	echo "File removed '$mac/$fnames'\n";
}
if (!empty($fnames2)) {
	echo "Remove File '$fnames' for Device '$mac'\n";
	$fnamel = S_DATA . "/$mac/$fnames2";
	if (!@file_exists($fnamel)) {
		$xlog .= ("File '$mac/$fnames2' not found, can't remove");
	} else {
		if (unlink($fnamel) == false) {
			exit_error("'unlink(...$mac/$fnames)' failed"); // Fatal
		}
		$xlog .= "(File removed:'$mac/$fnames2')";
		echo "File removed '$mac/$fnames2'\n";
	}
}

echo "OK (Back with Browser '<-')\n";
add_logfile(); // Regular exit
