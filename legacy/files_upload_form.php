<?php
error_reporting(E_ALL);
include("../sw/conf/api_key.inc.php");
//include("../sw/lxu_loglib.php");
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
?>

<!DOCTYPE HTML>
<html>

<head>
	<title>Legacy LTrax Filesystem Upload</title>
</head>

<body>
	<?php

	// ----------- M A I N ---------------
	$mac = @$_GET['s']; 					// exactly 16 Zeichen. api_key and mac identify device
	echo "<p><b><big>Legacy LTrax Filesystem Upload </big></b><br></p>";
	echo "<p><a href=\"index.php\">LTrax Home</a><br>";
	echo "<a href=\"device_lx.php?s=$mac&show=a\">Device View $mac (All)</a><br></p>";

	echo "<p>Select File (Device $mac):</p>";
	echo "<form name=\"file_uploadform\" enctype=\"multipart/form-data\" action=\"./files_upload.php?s=$mac\" method=\"post\">";
	?>
	File:
	<input type="file" name="X"> <input type="Submit" name="UP">
	</form>
</body>

</html>