<?php
/* lxu_wug_v1.php - Script fuer Wunderground-Get-Daten
* 18.11.2024  - (C)JoEmbedded.de
*
* ------------------------------------------------------
* Die Daten werden via GET geschickt. Z.B. fuer Ecowitt-Stationen, z.B.
* localhost/ltx/sw/lxu_wug_v1.php?ID=0011223344556677&PASSWORD=XXXXX&&tempf=61.70
*
* Setup fuer EcoWitt in EcoWitt-APP:
* - Wetterdienst auswaehlen
* - Protokoll Wunderground
* - Server/IP(http:) Hostname, z.B. 'server.abc' oder IP
* - Pfad: inkl. '/'und'?', z.B.: '/ltx/sw/lxu_wug_v1.php?'
* - ID: eine (beliebige) 16-stellige HEX-Zahl (z.B '0123456789ABCDEF')
* - Key: 'D_API_KEY' (aus Datei './conf/api_key.inc.php', 
*         kann auch dynamisch pro Geraet sein, siehe './conf/check_dapikey.inc.php'
* - Port: i.d.R: 80 
* ------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set("display_errors", true);
ignore_user_abort(true);
date_default_timezone_set('UTC'); // fuer strtotime
header('Content-Type: text/plain');

include("conf/api_key.inc.php");
include("lxu_loglib.php");

// Mapper fuer die eingehenden Daten - Keine Kommentare und keine END-Kommas im JSON!
$inmap = json_decode('{ 
	"tempf": {
		"six": 0,
		"unit": "°C_Outdoor",
		"offset": 32.0,
		"multi": 0.555555555556,
		"digits": 3,
		"rem": "Outdoor Temp (raw: in °F)"
	},
	"humidity": {
		"six": 1,
		"unit": "%rH_Outdoor",
		"offset": 0.0,
		"multi": 1.0,
		"digits": 2,
		"rem": "Outdoor Humidity in %"
	},
	"dewptf": {
		"six": 2,
		"unit": "°C_DewOutdoor",
		"offset": 32.0,
		"multi": 0.555555555556,
		"digits": 3,
		"rem": "Dewpoint (raw: in °F)"
	},
	"indoortempf": {
		"six": 3,
		"unit": "°C_Indoor",
		"offset": 32.0,
		"multi": 0.555555555556,
		"digits": 3,
		"rem": "Indoor Temp (raw: in °F)"
	},
	"indoorhumidity": {
		"six": 4,
		"unit": "%rH_Indoor",
		"offset": 0.0,
		"multi": 1.0,
		"digits": 2,
		"rem": "Outdoor Humidity in %"
	},
	"baromin": {
		"six": 5,
		"unit": "mBar_Baro",
		"offset": 0.0,
		"multi": 33.8637526,
		"digits": 1,
		"rem": "Baro (raw in inch)"
	},
	"solarradiation": {
		"six": 6,
		"unit": "W/m²_Solar",
		"offset": 0.0,
		"multi": 1.0,
		"digits": 3,
		"rem": "Solar Radiation"
	},
	"UV": {
		"six": 7,
		"unit": "UV",
		"offset": 0.0,
		"multi": 1.0,
		"digits": 1,
		"rem": "UV-INdex"
	},
	"winddir": {
		"six": 8,
		"unit": "°Dir_Wi",
		"offset": 0.0,
		"multi": 1.0,
		"digits": 0,
		"rem": "0-360°"
	},
	"windspeedmph": {
		"six": 9,
		"unit": "m/sec_Wi",
		"offset": 0.0,
		"multi": 0.44704,
		"digits": 1,
		"rem": "WindSpeed"
	},
	"windgustmph": {
		"six": 10,
		"unit": "m/sec_WiMax",
		"offset": 0.0,
		"multi": 0.44704,
		"digits": 1,
		"rem": "Boe"
	},
	"rainin": {
		"six": 11,
		"unit": "mm/hr_Rain",
		"offset": 0.0,
		"multi": 25.4,
		"digits": 2,
		"rem": "Rain per last hr (raw: inch/hr)"
	},
	"dailyrainin": {
		"six": 12,
		"unit": "mm/d_Rain",
		"offset": 0.0,
		"multi": 25.4,
		"digits": 2,
		"rem": "Daily rain (raw: inch/d)"
	},
	"soiltempf": {
		"six": 13,
		"unit": "°C_Soil",
		"offset": 32.0,
		"multi": 0.555555555556,
		"digits": 3,
		"rem": "Soil Temp (raw: in °F)"
	},
	"soilmoisture": {
		"six": 14,
		"unit": "%Vol_Soil",
		"offset": 0.0,
		"multi": 1.0,
		"digits": 0,
		"rem": "Soil Moisture (Vol%)"
	}
}');

//------------ Funktionen ----------------

// ---------------- Trigger: External async Script lxu_trigger.php  -------------------------
function call_trigger($mac, $reason)
{
	global $dbg;
	$self = $_SERVER['PHP_SELF'];
	$port = $_SERVER['SERVER_PORT'];
	$isHttps =  (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')  || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
	if ($isHttps) $server = "https://";
	else $server = "http://";
	$server .= $_SERVER['SERVER_NAME'];
	$rpos = strrpos($self, '/'); // Same Level
	$tscript = substr($self, 0, $rpos) . "/lxu_trigger.php";
	$arg = "k=" . S_API_KEY . "&r=$reason&s=$mac";    // Parameter: API-KEY, reason and MAC
	$clog = "(trigger(MAC:$mac: Reason:$reason))";
	$ch = curl_init("$server:$port$tscript?$arg");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
	curl_setopt($ch, CURLOPT_TIMEOUT, 1);
	$result = curl_exec($ch);
	if ($dbg) {
		$rlen = strlen(@$result);
		if ($rlen > 80) $sres = substr(@$result, 0, 80) . '...';
		else $sres = $result;
		$clog .= "(Curl:trigger: [$rlen]'$sres')";
	}
	if (curl_errno($ch)) {
		$clog = '(ERROR: Curl:' . curl_error($ch) . ')';
	}
	curl_close($ch);
	return $clog;
}

// Read/Write INI-File - Gibt leeres array zurueck wenn nicht existent. 
// Noch altes System mit KeyValue
function read_ini($fname)
{
	$devi = array();
	if (file_exists($fname)) {
		$lines = file("$fname", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		foreach ($lines as $line) {
			$tmp = explode("\t", $line);
			$devi[$tmp[0]] = $tmp[1];
		}
	}
	return $devi;
}
function write_ini($fname, &$devi)
{
	$of = fopen($fname, 'w');
	foreach ($devi as $key => $val) {
		fwrite($of, "$key\t$val\n");
	}
	fclose($of);
}

// Write content Data into LEGACY Directory
function add_data($cont)
{
	global $xlog, $dpath;
	if (@filesize("$dpath/files/data.edt") > 1000000) {    // 2*1MB per Device Maximum
		@unlink("$dpath/files/data.edt.bak");
		@rename("$dpath/files/data.edt", "$dpath/files/data.edt.bak");
		$xlog .= " ('data.edt' -> '/data.edt.bak')";
	}
	@file_put_contents("$dpath/files/data.edt", $cont, FILE_APPEND);
}

// Default-iparam generieren - mit 1 Channel
function gen_default_iparam($mac, &$kvalues)
{
	global $now, $xlog, $inmap;
	$typ = 950;
	$prefix = "WUG";	// Wunderground
	ksort($kvalues);

	$iparam = array("@100", $typ, 48 /*channels*/, '0' /*HK_FLAGS*/, $now,   $prefix . substr($mac, -11), '60' /* Period Unknown*/, '0', '0', '0', '0', '0', '1', '0', '1', '1', '0', '-40', '0', '');
	$i = 0;
	foreach ($kvalues as $key => $val) {
		$tp = $inmap->{$val[1]};
		$iparam = array_merge($iparam, array(
			"@$i",
			'1',
			'0' /*Physkan*/,
			'' /*Propsm (unused)*/,
			$key /* Srcidx */,
			$tp->unit,
			$tp->digits,
			'0',
			'0.0',
			'1.0',
			'0.0',
			'0.0',
			'0',
			"(Raw: " . $val[1] . ")" /*Xbytes*/
		));
		$i++;
	}
	// Ohne sys_param fragt trigger nach sys_param.. Ignorieren
	file_put_contents(S_DATA . "/$mac/files/iparam.lxp", implode("\n", $iparam) . "\n");
	$xlog .= ("'iparam.lxp' generated");
	return $iparam;
}


//============ MAIN ===================
$dbg = 0; // Debug-Level if >0, see docu

try {
	if ($inmap === null) exit_error("JSON(inmap): " . json_last_error_msg()); // NoError if null

	$result = "success"; // GET-Result (EcoWitt ignoriert das)

	// Alternative Namen ID/s/'MAC16' PASSWORD/k/'L_KEY'
	$api_key = @$_GET['k'];                // max. 41 Chars KEY
	if (!isset($api_key)) $api_key = @$_GET['PASSWORD'];
	$mac = @$_GET['s'];                 // 16 Zeichen. api_key and mac identify device
	if (!isset($mac)) $mac = @$_GET['ID'];

	$now = time();                        // one timestamp for complete run
	$now_str = gmdate("Y-m-d H:i:s", $now); // Readable (UTC)
	$mtmain_t0 = microtime(true);         // for Benchmark 
	$dfn = gmdate("Ymd_His", $now);        // 'disk_filename_from_now' (sortable)

	$send_cmd = -1;                        // If set (0-255) send as Flags-cmd
	if (!isset($mac) || strlen($mac) != 16) {
		exit_error("MAC Len");
	}

	$dpath = S_DATA . "/$mac";                // Device Path global
	$xlog = "(lxu_wug_v1)";                 // Scriptname fuer Log

	if (@file_exists("$dpath/cmd/dbg.cmd"))  if (!$dbg) $dbg = 1;

	if (!isset($api_key)) exit_error("API Key"); // Required
	$dapikey = @file_get_contents("$dpath/dapikey.dat"); // false oder KEY
	if ($dapikey === false || strcmp($api_key, $dapikey)) { //
		include("conf/check_dapikey.inc.php"); // only on demand: check extern, opt. set daksave
		if ($dapikey === false || strcmp($api_key, $dapikey)) exit_error("API Key");
	}

	if (check_dirs()) exit_error("Error (Directory/MAC not found)");
	if (isset($daksave)) file_put_contents("$dpath/dapikey.dat", $dapikey); // Update Key

	// For Debug: record RawData 
	if ($dbg) file_put_contents("$dpath/dbg/indata.log", $now_str . ": " . $_SERVER['QUERY_STRING'] . "\n", FILE_APPEND);

	// Analyze and normalize uploaded data to channel-array (ksort)
	$kvalues = array();
	foreach ($_GET as $key => $val) {
		$prop = @$inmap->$key; // null if not found (as e.g. for KEY/PW..)
		if ($prop !== null) { // May be not sorted!
			$nval = round((floatval($val) - $prop->offset) * $prop->multi, $prop->digits);
			$kvalues[(int)$prop->six] = array($nval, $key);
		}
	}
	$kanz = count($kvalues);
	if ($kanz < 1) exit_error("NO USEABLE DATA");

	if ($dbg > 1) foreach ($kvalues as $key => $val) echo "K:$key V:" . $val[0] . " (" . $val[1] . ")\n";

	// New Parameters?
	if (file_exists("$dpath/put/iparam.lxp")) {
		@unlink("$dpath/files/iparam.lxp");
		rename("$dpath/put/iparam.lxp", "$dpath/files/iparam.lxp");
		@unlink("$dpath/cmd/iparam.lxp.pmeta");	// Kill Put-Meta-infos
		@unlink("$dpath/put/iparam.lxp");	// and mod.Par
	}
	// Param/Data-Files
	if (!file_exists("$dpath/files/iparam.lxp")) $iparam = gen_default_iparam($mac, $kvalues);
	else $iparam = file("$dpath/files/iparam.lxp", FILE_IGNORE_NEW_LINES);

	$dstr = @$_GET['date'];
	if (!isset($dstr) || $dstr === "now") $unix_ts = time();
	else $unix_ts =  strtotime($dstr);
	if ($unix_ts < 1000000000) exit_error("Illegal date");

	$maxlen = strlen($_SERVER['QUERY_STRING']); // Only GET Payload counts
	$devi = read_ini("$dpath/device_info.dat");

	$devi['now'] = $now;
	$devi['pasync'] = '1';    // May be asynchron
	// First of all: quota per UTC-day (in Bytes) only for info
	$day = floor($now / 86400); // intdiv >= PHP7
	if (@$devi['day'] != $day) { // new day, new quota
		@$devi['total_in'] += @$devi['quota_in'];    // Keep olds...
		@$devi['total_out'] += @$devi['quota_out']; // out: *todo* 
		$devi['day'] = $day;
		$devi['quota_in'] = $maxlen;
		$devi['quota_out'] = 0;
	} else {
		$devi['quota_in'] += $maxlen;
	}
	$ccnt = @$devi['trans'];
	if (!isset($ccnt)) $ccnt = 1;
	else $ccnt++;
	$devi['trans'] = $devi['conns'] = $ccnt;

	// Generate Data line (and units)
	$line = "!$unix_ts";
	$units = "!U";
	$reason=2; // Auto
	$mi = count($iparam);
	for ($i = 1; $i < $mi; $i++) {
		if (@$iparam[$i][0] == '@') {
			$action = intval($iparam[$i + 1]);
			if ($action & 1) { // Action Bit0=1
				$kn = intval(substr($iparam[$i], 1));
				$ksrc = $iparam[$i + 4];
				$kval = @$kvalues[(int)@$ksrc][0];
				if(isset($kval)){
					$kfval = floatval($kval);
					$kfval -= floatval($iparam[$i + 8]); // Scale Value
					$kfval *= floatval($iparam[$i + 9]);
					$r= (int)$iparam[$i + 6];
					if($r<0) $r=0; else if($r>9) $r=9; // Digits
					$kval=round($kfval,$r);
					// Check alarms Bit2=1
					if($action & 4){
						if($kval>=floatval($iparam[$i + 10])  // High-Alarm
						 || $kval<=floatval($iparam[$i + 11])){ // Low Alarm
							$kval ='*'.$kval; // Mark Alarm
							$reason |= 64;	// Global Alarmflag
						 }
					}
				}else $kval="ErrNoValue";
				$line .= " $kn:$kval";
				$ku = $iparam[$i + 5];
				$units .= " $kn:$ku";
			}
			$i += 13;
		}
	}
	$ldata = array($line."\n");
	$cookie = $iparam[4]; // cahnged Units: New Header
	if (@$devi['cookie'] !== $cookie) {
		$devi['cookie'] = $cookie;
		array_unshift($ldata, $units."\n");
		array_unshift($ldata, "<COOKIE $cookie>\n");
	}
	write_ini("$dpath/device_info.dat", $devi);

	$ftemp = gmdate("Ymd_His", $now) . '_wug.edt'; // No File Pos

	file_put_contents("$dpath/in_new/" . $ftemp, $ldata);
	add_data($ldata);

	$mtrun = round((microtime(true) - $mtmain_t0) * 1000, 4);
	$xlog .= "(Run:$mtrun msec)"; // Add old log Script Runtime
	add_logfile(); // Regular exit, entry in logfile should be first

	// Wunderground gibt tw. andere Antworten, z.B. success etc.. - Bei Bedarf abaendern
	echo $result . "\n*** Result: $xlog ***\n";
	$xlog .= call_trigger($mac, $reason);   

} catch (Exception $e) {
	$errm = $e->getMessage();
	exit_error($errm);
}
// ***
