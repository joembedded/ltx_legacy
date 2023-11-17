<?php
// Convert HTML to File and upload
// V1.1 (C) JoEmbedded 

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


//---------------- MAIN ---------------
header('Content-Type: text/plain');

$dbg = 0;	// Currently not used
$mac = @$_POST['mac']; // 16 Chars, Uppercase
$freal_name = @$_POST['fname'];
$lines = @$_POST['lines'];

$now = time(); // Sec ab 1.1.1970, GMT

if ($dbg) print_r($_POST);

if (!isset($mac) || strlen($mac) != 16) {
	exit_error("MAC Len");
}

if (strlen($freal_name) > 21 || !strlen($freal_name)) exit_error("File Name Error");

if (!@file_exists(S_DATA . "/$mac")) exit_error("Error (Directory/MAC not found)");
//if(check_dirs()) exit_error("Error (Directory/MAC not found)");


if (intval($lines) < 1) exit_error("No Data $lines");

$ext = substr($freal_name, strpos($freal_name, '.'));
if (!strcasecmp($ext, ".php")) exit_error("Not allowed Format"); // Filter illegal formats!!!

echo "----- MAC:$mac: Upload:'$freal_name' Lines:$lines -------\n";
$xlog = "(Edited and Upload File:'$freal_name')";

$binfile = "";
for ($i = 0; $i < $lines; $i++) {
	$val = $_POST["z$i"];
	$binfile .=  $val . "\n";
}
//echo $binfile; // Show Output

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
echo "OK, File prepared for Upload ('put') (Back with Browser '<-')\n";
add_logfile(); // Regular exit
