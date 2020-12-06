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
?>
<!DOCTYPE HTML>
<html>

<head>
	<title>Legacy LTrax Edit Quota</title>
</head>

<body>
	<?php
	if (!$dev) {
		echo "ERROR: Access denied!";
		exit();
	}

	// ----------- M A I N ---------------
	$mac = @$_GET['s']; 					// exactly 16 Zeichen. api_key and mac identify device
	echo "<p><b><big>Legacy LTrax Edit Quota </big></b><br></p>";
	echo "<p><a href=\"index.php\">Legacy LTrax Home</a><br>";
	echo "<a href=\"device_lx.php?s=$mac&show=a\">Device View $mac (All)</a><br></p>";

	$dpath = S_DATA . "/$mac/quota_days.dat";
	$quota = @file($dpath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

	echo "<p>Edit Quota: (Device $mac): (Set both to '' to use Remove Quota)</p>";
	echo "<form name=\"uname_editform\"  action=\"./set_quota.php?s=$mac\" method=\"post\">";
	echo "<label for=\"d\">Days (&ge;1): </label> <input id=\"d\" name=\"udays\" value=\"" . @$quota[0] . "\"><br>";
	echo "<label for=\"l\">Lines (&ge;10): </label> <input id=\"l\" name=\"ulines\" value=\"" . @$quota[1] . "\"><br><br>";
	?>
	<input type="Submit" name="UP">
	</form>
</body>

</html>