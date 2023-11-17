<?php
/* Upload a new Firmware-File to a device and store it encrypted
* as '_firmware.sec'. If no key-File is found, the Factory-Key is downloaded from JesFs-Home
* Factory-key ist stored as php definition to prevent external access
* Firmeware-File either per POST or viaURL_parameter (if already somewhere in the local Filesystem)
* New: Only .SEC-Files since FW 1.0
*
* V1.3 - 27.06.2022(C) JoEmbedded 
*/

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

/*** V1.0: Only .SEC-Files allowed 
define("HDR0_MAGIC", "\x4F\x9C\x9B\xE7"); // in E79B9C4F LE-Format - PHP hat Probleme mit U32-Zahlen, daher STRINGS

function get_factory_key()
{
	global $mac, $xlog, $token;

	$api_key = KEY_API;
	$getfsec_url = KEY_SERVER_URL;

	$ch = curl_init("$getfsec_url?k=$api_key&s=$mac&t=$token");
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
*/ // V1.0


//---------------- MAIN ---------------
header('Content-Type: text/plain');

$dbg = 0;
$mac = @$_GET['s']; // 16 Chars, Uppercase
$now = time(); // Sec ab 1.1.1970, GMT

$lfname = @$_GET['lfname'];	// Opionally File from File System (with Spaces) (wg. BULK)
if (!empty($lfname)) {
	$fname = S_DATA . "/stemp/$lfname";        // File is name on Disk (in stemp)
	$freal_name = @$_GET['lreal']; // Optionally REAL name
	if (empty($freal_name)) $freal_name = $fname;
	$fsize = filesize($fname);
} else {
	$fname = @$_FILES['X']['tmp_name'];        // File is (temp) name on Disk (Temp.-Name)
	$freal_name = @$_FILES['X']['name'];        // Real Name
	$fsize = @$_FILES['X']['size'];
}

$token = @$_REQUEST['token'];
if ($dbg) echo "*** DEBUG ***\nToken: '$token'\n";

if (!isset($mac) || strlen($mac) != 16) {
	exit_error("MAC Len");
}
// Never generate Updates for unexisting Loggers...
if (!file_exists(S_DATA . "/$mac") || check_dirs()) exit_error("Error (Directory/MAC not found)");
if (empty($fname)) exit_error("No Data (Need Firmware File)");

echo "Check File '$freal_name'...\n";
if ($fsize < 10000 || $fsize > 1000000) {	// firmware <10k impossible, >1M ?!
	exit_error("No Firmware File '$freal_name' Size:$fsize Bytes");
}
$xlog = "(Firmware:'$freal_name' Size:$fsize)";

if ($dbg) echo "Loaded File:'$freal_name' Size:$fsize\n";

$fw_bin = file_get_contents($fname);

$dpath = S_DATA . "/$mac/cmd/";

if(substr($freal_name,-4) === '.sec' ){
	if((strlen($fw_bin)%16)){
		exit_error("File is either no firmware file or corrupt! (SIZE)");
	}
	$fw_sec = $fw_bin;
}
/*** V1.0: Only .SEC-Files allowed 
else{

	$magic = substr($fw_bin, 0, 4);
	if ($dbg) {
		show_str("Found Magic:", $magic);
		show_str("Soll Magic:", HDR0_MAGIC);
	}
	if ($magic != HDR0_MAGIC) { // String Compare!
		exit_error("File is either no firmware file or corrupt! (HDR0)");
	}

	$hdrlen = u32l_str(substr($fw_bin, 4, 4));
	$binlen = u32l_str(substr($fw_bin, 8, 4));
	if ($dbg) echo "Hdrlen:$hdrlen, Binlen:$binlen\n";

	if (($hdrlen + $binlen) != $fsize) {
		exit_error("File is either no firmware file or corrupt! (Filesize)");
	}

	// CRC-check
	$sollcrc = substr($fw_bin, 16, 4);

	$istcrc = str_u32l(~crc32(substr($fw_bin, $hdrlen, $binlen)));
	if ($dbg) {
		show_str("SollCRC32:", $sollcrc);
		show_str("istCRC32:", $istcrc);
	}

	if ($sollcrc != $istcrc) { // String Compare!
		exit_error("File is either no firmware file or corrupt! (CRC32)");
	}

	echo "Firmware dated '$cookie_str' (UTC)\n";
	echo "Loaded Firmware '$freal_name': Size $fsize Bytes\n";
	echo "for MAC: $mac\n";

	$sec_key = "";	// Assume empty.. Later maybe user's key..

	@include("$dpath/def_factory_key.php"); // If available: Use it
	if (defined("FW_FACTORY_KEY")) {
		$sec_key = pack("H*", FW_FACTORY_KEY);	// If there is a key, use it

	}

	if (strlen($sec_key) != 16) { // Condition for AES128-Key
		echo "No Factory Key file found, generating...\n";
		$xlog .= "(Get Factory Key...)";
		$sec_key = get_factory_key();

		$of = fopen($dpath . 'def_factory_key.php', 'w');	// Cache Factory Key
		fwrite($of, "<?php\n define(\"FW_FACTORY_KEY\",\"" . implode(unpack("H*", $sec_key)) . "\");\n?>\n");
		fclose($of);
	} else {
		$xlog .= "(Factory Key already stored)";
		echo "Factory Key already stored\n";
	}

	if (strlen($sec_key) != 16) {
		exit_error("Illegal Key len:" . strlen($sec_key) . "(must be 16)");
	}

	echo "Using Key: '" . implode(unpack("H*", $sec_key)) . "'\n";

	// Padd
	while (strlen($fw_bin) % 16) $fw_bin .= "\0";

	// Encrypting firmware
	$iv = "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0";
	$fw_sec = openssl_encrypt($fw_bin, "aes-128-cbc", $sec_key,  OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);

	//--- todo: Lock files before modifying.
	@unlink($dpath . '_firmware.sec.umeta');	// Kill Meta-infos

	echo "Writing encrypted firmware '_firmware.sec'\n";
}
*/  // V1.0

$of = fopen($dpath . '_firmware.sec', 'wb');
fwrite($of, $fw_sec);
fclose($of);

$of = fopen($dpath . '_firmware.sec.umeta', 'w');	// Keep some (u)meta data
fwrite($of, "fname_original\t$freal_name\n");		// Only for info
fwrite($of, "sent\t0\n");					// Count Number of transmits
fclose($of);
// Add to protocol

echo "OK (Back with Browser '<-')\n";
add_logfile(); // Regular exit
