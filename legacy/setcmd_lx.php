<?php
// Setcmd File
// Fragment for a small cmd-File
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
$flen = intval(@$_GET['l']); // optional size (max 16)

if (!isset($mac) || strlen($mac) != 16) {
	exit_error("MAC Len");
}

if (!@file_exists(S_DATA . "/$mac")) exit_error("Error (Directory/MAC not found)");

if ($flen < 1) $flen = 1; // Zero-len not allowed 
else if ($flen > 16) {
	exit_error("CMD Len");
}
echo "Generate Cmd File '$fnames' ($flen Bytes) for Device '$mac' \n";
$fnamel = S_DATA . "/$mac/$fnames";

$of = fopen($fnamel, 'wb');
fwrite($of, substr("0123456789abcdef", 0, $flen));;
fclose($of);


$xlog = "(File generated:'$mac/$fnames' ($flen Bytes))";
echo "File generated '$mac/$fnames' ($flen Bytes)\n";
echo "OK (Back with Browser '<-')\n";
add_logfile(); // Regular exit
