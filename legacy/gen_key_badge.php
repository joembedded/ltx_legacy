<?php

// generate Key Badge
// V1.0 2011(C) JoEmbedded 

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
$dbg = 0;	// Currently not used
$mac = @$_GET['s']; // 16 Chars, Uppercase
$now = time(); // Sec ab 1.1.1970, GMT

if (!isset($mac) || strlen($mac) != 16) {
	exit_error("MAC Len");
}

// Never generate Files for unexisten Loggers...
if (!file_exists(S_DATA . "/$mac") || check_dirs()) exit_error("Error (Directory/MAC not found)");
?>
<!DOCTYPE html>
<html>

<head>
	<style>
		table {
			border-radius: 10px;
			padding: 1px 5px 1px 5px;
			border: 1px solid black;
		}

		td {
			padding: 1px;
		}

		hr {
			border: 1px dotted gray;
		}
	</style>
	<script>
	</script>
	<title><?php echo "Key: MAC:$mac"; ?></title>
</head>

<body>
	<?php

	$xlog = "(Generating Badge)";

	$dpath = S_DATA . "/$mac/cmd/";
	@include("$dpath/def_factory_key.php"); // If available: Use it
	if (defined("FW_FACTORY_KEY")) {
		$sec_key = pack("H*", FW_FACTORY_KEY);	// If there is a key, use it
		echo "Factory Key already stored";
		$xlog .= "(Factory Key already stored)";
	} else {
		echo "No Factory Key found, generating...\n";
		$xlog .= "(Get Factory Key...)";
		$sec_key = get_factory_key();
	}
	//echo "FW-Key: '".$fw_key."'<br>";
	echo "<br>Printed: " . date('d.m.Y H:i:s');

	if (strlen($sec_key) != 16) {
		exit_error("Illegal Key len:" . strlen($sec_key) . "(must be 16)");
	}

	$of = fopen($dpath . 'def_factory_key.php', 'w');	// Cache Factory Key
	fwrite($of, "<?php\n define(\"FW_FACTORY_KEY\",\"" . implode("",unpack("H*", $sec_key)) . "\");\n?>\n");
	fclose($of);

	$fw_key = strtoupper(implode("",unpack("H*", $sec_key)));
	$script = $_SERVER['PHP_SELF'];	// /xxx.php
	$server = $_SERVER['HTTP_HOST'];
	$lp = strpos($script, "legacy/gen_key");
	$sroot = substr($script, 0, $lp - 1);
	$sec = "";
	//if(HTTPS_SERVER!=null) $sec="https://"; // requires inc/config.inc.php
	//else $sec="http://";

	// Generate Ticket /last 6 Digitas
	$plain = substr($mac . "      ", 10, 6); // exactly (last) 6 chars
	$crc = ((~crc32($plain)) & 0xFFFF);
	$xc = $crc & 255;
	$res = "";
	for ($i = 0; $i < 6; $i++) $res .= chr(ord($plain[$i]) ^ (($i * 15 + $xc) & 255));
	$res .= chr(($crc >> 8) & 255) . chr($xc);
	$ticket = strtoupper(implode("",unpack("H*", $res)));	// String 2 Hex-String, reverse: pack("H*", $hex);

	$anz = 1;	// Number of Badges
	echo "<br><br><hr><br>";

	$ownertoken = substr($fw_key, 0, 16);
	$qrtxt = "MAC:$mac OT:$ownertoken";
	// Pin: get a 6-digit PIN 100100-999899 out of fw_key
	$pin=(hexdec(substr($fw_key, 0, 8))  % 899800)+100100;
	// Anm. in JS - Pin Berechnung: ((parseInt(ownertoken.substring(0,8),16))% 899800)+100100

	$qrlink = "../sw/php_qr/ltx_qr.php?text=" . urlencode($qrtxt) . "&px=3&fx=1";
	for ($i = 0; $i < $anz; $i++) {
		echo "<table>";
		echo "<tr style='font-size:120%; font-weight:bold;'><td>Device MAC:</td><td>$mac</td><td rowspan='4' style='margin:0; padding:0;' ><img src='$qrlink'></td></tr>";
		echo "<tr style='font-size:100%; font-weight:bold;'><td>Device Owner Token:</td><td>$ownertoken</td></tr>";
		echo "<tr style='font-size:100%; font-weight:bold;'><td>Device PIN:</td><td>$pin</td></tr>";
		echo "<tr><td>Server Login$sec:</td><td>" . $server . $sroot . "</td></tr>";
		echo "<tr><td>Server Ticket:</td><td>$ticket</td><td valign='top'  style='font-size:60%;'> MAC/OwnerToken</td></tr>";
		echo "</table><br>";
	}

	add_logfile(); // Regular exit
	?>


	<hr>
	<br>
	<button onclick="myFunction()">Print</button>
	<script>
		function myFunction() {
			window.print();
		}
	</script>


</body>

</html>