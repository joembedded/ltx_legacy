<?php

include("../sw/conf/api_key.inc.php");
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

$ip = $_SERVER["REMOTE_ADDR"];  
$host = gethostbyaddr($ip);

echo "Remote IP: '$ip' ";  
echo "Hostname: '$host'";  
echo "<hr>";

phpinfo();
