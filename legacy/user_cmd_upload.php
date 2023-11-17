<?php
// User-command Upload

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

//---------------- Functions ---------------

function show_str($rem, $str)
{
	$size = strlen($str);
	echo "$rem [$size]:";
	for ($i = 0; $i < $size; $i++) {
		$bval = ord($str[$i]);
		if ($i) echo '-';
		echo dechex($bval);
	}
	echo "\n";
}

function u32l_str($uvs)
{ // le
	$ret = ord($uvs[0]) + (ord($uvs[1]) << 8) + (ord($uvs[2]) << 16) + (ord($uvs[3]) << 24);
	return $ret;
}
function str_u32l($uv32)
{ // le
	$ret = chr($uv32) . chr($uv32 >> 8) . chr($uv32 >> 16) . chr($uv32 >> 24);
	return $ret;
}


//---------------- MAIN ---------------
header('Content-Type: text/plain');

$dbg = 0;	// Currently not used
$mac = @$_GET['s']; // 16 Chars, Uppercase
$now = time(); // Sec ab 1.1.1970, GMT

$user_cmd = @$_POST['user_cmd']; // 16 Chars, Uppercase

if (!isset($mac) || strlen($mac) != 16) {
	exit_error("MAC Len");
}

if (!@file_exists(S_DATA . "/$mac")) exit_error("Error (Directory/MAC not found)");
//if(check_dirs()) exit_error("Error (Directory/MAC not found)");

if (empty($user_cmd)) exit_error("No Data (Need Command)");

$uc_size = strlen($user_cmd);
if ($uc_size < 1 || $uc_size > 245) {	// Limit to 1-245 chars
	exit_error("User Command Size:$uc_size Bytes?");
}

echo "User Command '$user_cmd' ($uc_size Bytes)\n";
$xlog = "(User Command '$user_cmd' ($uc_size Bytes))";

$dpath = S_DATA . "/$mac";

//--- todo: Lock files before modifying.
@unlink($dpath . "/cmd/usercmd.cmeta");	// Kill Put-Meta-infos

$of = fopen($dpath . "/cmd/usercmd.cmd", 'w');
fwrite($of, $user_cmd);
fclose($of);

// Add to protocol

echo "OK (Back with Browser '<-')\n";
add_logfile(); // Regular exit
