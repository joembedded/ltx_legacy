<?PHP
// -------------------------------------------------------------------
// edit.php - Edit datafile (and write back)
// 19.09.2023 joembedded.de

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

$p100_beschr = array(
	"*@100_System",
	"*DEVICE_TYP",
	"*MAX_CHANNELS",
	"*HK_FLAGS",
	"*NewCookie [Parameter 10-digit Timestamp.32]",
	"Device_Name[BLE:$11/total:$41]",
	"Period_sec[10..86400]",
	"Period_Offset_sec[0..(Period_sec-1)]",
	"Period_Alarm_sec[0..Period_sec]",
	"Period_Internet_sec[0..604799]",
	"Period_Internet_Alarm_sec[0..Period_Internet_sec]",
	"UTC_Offset_sec[-43200..43200]",
	"Flags (B0:Rec B1:Ring) (0: RecOff)",
	"HK_flags (B0:Bat B1:Temp B2.Hum B3.Perc)",
	"HK_reload[0..255]",
	"Net_Mode (0:Off 1:OnOff 2:On_5min 3:Online)",
	"ErrorPolicy (O:None 1:RetriesForAlarms, 2:RetriesForAll)",
	"MinTemp_oC[-40..10]",
	"Config0_U31 (B0:OffPer.Inet:On/Off B1,2:BLE:On/Mo/Li/MoLi B3:EnDS B4:CE:Off/On B5:Live:Off/On)",
	"Configuration_Command[$79]",
);

$pkan_beschr = array(
	"*@ChanNo",
	"Action[0..65535] (B0:Meas B1:Cache B2:Alarms)",
	"Physkan_no[0..65535]",
	"Kan_caps_str[$8]",
	"Src_index[0..255]",
	"Unit[$8]",
	"Mem_format[0..255]",
	"DB_id[0..2e31]",
	"Offset[float]",
	"Factor[float]",
	"Alarm_hi[float]",
	"Alarm_lo[float]",
	"Messbits[0..65535]",
	"Xbytes[$32]"
);

$p200_beschr = array(	// sys_param.lxp
	"*@200_Sys_Param",
	"APN[$41]",
	"Server/VPN[$41]",
	"Script/Id[$41]",
	"API Key[$41]",
	"ConFlags[0..255] (B0:Verbose B1:RoamAllow B4:LOG_FILE (B5:LOG_UART) B7:Debug)",
	"SIM Pin[0..65535] (opt)",
	"APN User[$41]",
	"APN Password[$41]",
	"Max_creg[10..255]",
	"Port[1..65535]",
	"Server_timeout_0[1000..65535]",
	"Server_timeout_run[1000..65535]",
	"Modem Check Reload[60..3600]",
	"Bat. Capacity (mAh)[0..100000]",
	"Bat. Volts 0%[float]",
	"Bat. Volts 100%[float]",
	"Max Ringsize (Bytes)[1000..2e31]",
	"mAmsec/Measure[0..1e9]",
	"Mobile Protocol[0..255] B0:0/1:HTTP/HTTPS B1:PRESET B2,3:TCP/UDPSetup"
);


// ---------------------------- M A I N --------------
// header('Content-Type: text/plain'); Output as HTML
$mac = $_GET["s"];
$fname = $_GET["f"];
$rfile = S_DATA . "/$mac/$fname";

$beschr = array();

$ext = substr($fname, strpos($fname, '.'));
if (!strcasecmp($ext, ".php")) {
	echo "<ERROR: Not allowed Format>\n"; // Filter illegal formats (Never display contents of PHP)
} else if (strpos("$mac/$fname", "..") || !file_exists($rfile)) { // prevent upper dirs
	echo "<ERROR: File not found: '$fname' (maybe too old?)>\n";
} else {
	$sfname = substr($fname, strpos($fname, '/') + 1); // WIthout Dir

	$data = file($rfile, FILE_IGNORE_NEW_LINES);
	$action = "edit2file.php";
	echo "<!DOCTYPE HTML><html><head><meta charset='utf-8' /><title>Edit $fname</title></head><body>\n";
	echo "<b>*** ONLY FOR DEVELOPMENT ***</b><br>";
	echo "Edit '$sfname':";
	echo "<br><form name=\"form\" action=\"$action\" method=\"post\">\n";
	$cnt = count($data);
	$rel = 0;
	for ($i = 0; $i < $cnt; $i++) {
		$var = $data[$i]; // 'undefined' JS-String
		if (@$var[0] == '@') {
			$pval = substr($var, 1);
			if ($pval == 100) {
				$beschr = $p100_beschr;
				$beschr[0] = "*=== System ===";
			} else if ($pval == 200) {
				$beschr = $p200_beschr;
				$beschr[0] = "*=== Sys_Param ===";
			} else {
				$beschr = $pkan_beschr;
				$beschr[0] = "*=== Channel #$pval ===";
			}
			$bidx = 0;
			echo "<hr>";
			$rel = 0;
		} else $rel++;
		if (@$pval == 100 && $rel == 4) $var = time();
		echo "[$i (+$rel)] &nbsp;<input type=\"text\" name=\"z$i\" value=\"$var\"";
		if (isset($beschr[$rel])) {
			if (@$beschr[$rel][0] == '*') echo " readonly"; // disabled: NOT incl. with form!
			echo '>';
			echo " '", @$beschr[$rel] . "'";
		} else echo '>';
		echo "<br>\n";
	}
	echo "<input type=\"hidden\" name=\"fname\" value=\"$sfname\" >\n";
	echo "<input type=\"hidden\" name=\"mac\" value=\"$mac\" >\n";
	echo "<input type=\"hidden\" name=\"lines\" value=\"$cnt\" >\n";
	echo "<br>\n";
	echo "<button type=\"submit\">OK and WriteBack</button></form></body></html>\n";
}
