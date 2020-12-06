<?php
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
?>

<!DOCTYPE HTML>
<html>

<head>
	<title>Legacy LTrax Firmware Upload</title>
</head>

<body>
	<?php
	// Functions (as GenerateBadge)
	function get_factory_key()
	{
		global $mac, $xlog;

		$api_key = KEY_API_GL; // Needs no Token
		$getfsec_url = KEY_SERVER_URL;

		$ch = curl_init("$getfsec_url?k=$api_key&s=$mac");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($ch);
		if (curl_errno($ch)) $xlog .= "(ERROR: curl:'" . curl_error($ch) . "')";
		curl_close($ch);

		//echo "RES:$result\n";
		$obj = json_decode($result);
		if (intval($obj->result) < 0) {
			echo "ERROR calling '$getfsec_url': ", $obj->result;
			exit();
		}
		return pack("H*", $obj->fwkey);	// Make real string from Hex-String
	}



	// ----------- M A I N ---------------
	$mac = @$_GET['s']; 					// exactly 16 Zeichen. api_key and mac identify device
	echo "<p><b><big>Legacy LTrax Firmware Upload </big></b><br></p>";
	echo "<p><a href=\"index.php\">Legacy LTrax Home</a><br>";
	echo "<a href=\"device_lx.php?s=$mac&show=a\">Device View $mac (All)</a><br></p>";

	echo "<hr><b><h2>WARNING: Wrong Firmware can damage the Device!!!</h2></b><hr>";

	echo "<p>Select firmware (Device $mac):</p>";
	echo "<form name=\"fw_uploadform\" enctype=\"multipart/form-data\" action=\"./fw_upload.php?s=$mac\" method=\"post\">";

	$dpath =	$dpath = S_DATA . "/$mac/cmd/";
	$xlog = "";

	@include("$dpath/def_factory_key.php"); // If available: Use it
	if (defined("FW_FACTORY_KEY")) {
		echo "Factory Key found: " . FW_FACTORY_KEY . "<br><br>";
	} else {
		if (strlen(KEY_API_GL)) {
			echo "No Factory Key found, generating...\n";
			$xlog .= "(Get Factory Key...)";
			$sec_key = get_factory_key();
			if (strlen($sec_key) != 16) {
				exit_error("Illegal Key len:" . strlen($sec_key) . "(must be 16)");
			}
			$of = fopen($dpath . 'def_factory_key.php', 'w');	// Cache Factory Key
			fwrite($of, "<?php\n define(\"FW_FACTORY_KEY\",\"" . implode(unpack("H*", $sec_key)) . "\");\n?>\n");
			fclose($of);
			echo "Factory Key generated: " . implode(unpack("H*", $sec_key)) . "<br><br>";
		}

		echo "Owner Token or Key (only needed once for first Upload):";
		echo "<input type='text' name ='token'><br>";
	}
	if (strlen($xlog)) add_logfile(); // Regular exit
	?>

	Firmware File (*.bin):
	<input type="file" name="X"><br><br>
	<input type="Submit" name="UP"> <input type="reset" name="Reset">
	</form>
</body>

</html>