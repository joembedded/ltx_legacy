<?php
// Upload a new File to the device's filesystem

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

$fname = @$_FILES['X']['tmp_name'];        // File is name on Disk (Temp.-Name)
$freal_name = @$_FILES['X']['name'];        // Real Name
$fsize = @$_FILES['X']['size'];

if (!isset($mac) || strlen($mac) != 16) {
	exit_error("MAC Len");
}

if (strlen($freal_name) > 21 || !strlen($freal_name)) exit_error("File Name Error");

if (!@file_exists(S_DATA . "/$mac")) exit_error("Error (Directory/MAC not found)");
//if(check_dirs()) exit_error("Error (Directory/MAC not found)");

if (empty($fname)) exit_error("No Data (Need File)");

$ext = substr($freal_name, strpos($freal_name, '.'));
if (!strcasecmp($ext, ".php")) exit_error("Not allowed Format"); // Filter illegal formats!!!

echo "File '$freal_name' ($fsize Bytes)\n";
if ($fsize < 1 || $fsize > 15000000) {	// No empty files and <16MB
	exit_error("File '$freal_name' Size:$fsize Bytes?");
}
$xlog = "(Upload File:'$freal_name' Size:$fsize)";

$binfile = file_get_contents($fname);
$binlen = strlen($binfile);
if ($binlen != $fsize) {
	exit_error("File corrupt! (Filesize)");
}

$dpath = S_DATA . "/$mac";

//--- todo: Lock files before modifying.
@unlink($dpath . "/cmd/$freal_name.pmeta");	// Kill Put-Meta-infos

$of = fopen($dpath . "/put/$freal_name", 'wb');
fwrite($of, $binfile);
fclose($of);

$of = fopen($dpath . "/cmd/$freal_name.pmeta", 'w');	// Keep some fmeta data
fwrite($of, "sent\t0\n");					// Count Number of transmits
fclose($of);
// Add to protocol

echo "OK (Back with Browser '<-')\n";
add_logfile(); // Regular exit
