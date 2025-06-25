<?php
/* lxu_ltxlora_v1.php
* HTTP(s)-Uploader ltx-Payloads (https://github.com/joembedded/payload-decoder)  auf ltx-microcloud
* 20.06.2025 (C)JoEmbedded.de
* Bisher nur ChirpStack, aber TTN sehr aehnlich, siehe c_hook.php
*
* Docu (DE/EN): https://joembedded.de/x3/ltx_firmware/index.php?dir=./Open-SDI12-Blue-Sensors/xxxx
*
* Ist wohl schon vorgekommen, dass unvollstaendige Payloads geschcikt worden sind..
* Dann ggf. aufzeichnen?
*
* ------------------------------------------------------
* Beispiel:
* server.abc/ltx/sw/lxu_ltxlora_v1.php?KEY=xxxx - Daten als JSON im PHP-Stream
*

xxxxxxxxxxxxxxxxxxxxxxREMOVEcxxxxxxxxxxxxxxxx
DEBUG: 
	http://localhost/ltx/sw/lxu_ltxlora_v1.php?KEY=LX1310&x=1442  // Mit HK
	http://localhost/ltx/sw/lxu_ltxlora_v1.php?KEY=LX1310&x=1442h  // Mit HK, Fehler
	http://localhost/ltx/sw/lxu_ltxlora_v1.php?KEY=LX1310&x=1447  // Ohne HK
	
	https://joembedded.de/ltx/sw/lxu_ltxlora_v1.php?KEY=LX1310  // WRK

* ------------------------------------------------------*/


error_reporting(E_ALL);
ini_set("display_errors", true);
ignore_user_abort(true);
date_default_timezone_set('UTC'); // fuer strtotime
header("Content-Type: application/json");

include("conf/api_key.inc.php");
include("lxu_loglib.php");

//------------ Funktionen ----------------
function exit_json_error($errmsg)
{ // Gibt auch noch reg. exit_json_error()
	http_response_code(400);
	echo json_encode(['error' => $errmsg]);
	exit;
}

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
	while (!flock($of, LOCK_EX)) usleep(10000);  // Lock File - Is a MUST
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


//============ MAIN ===================
$dbg = 0; // Debug-Level if >0, see docu

try {

	$result = "success";
	$paylog = false;	// Wenn true: ggfs. aufzeichnen

	$api_key = @$_GET['KEY'] ?? '';;

	$now = time();                        // one timestamp for complete run
	$now_str = gmdate("Y-m-d H:i:s", $now); // Readable (UTC)
	$mtmain_t0 = microtime(true);         // for Benchmark 
	$dfn = gmdate("Ymd_His", $now);        // 'disk_filename_from_now' (sortable)

	// Hole den Body der POST-Anfrage
	$payload = file_get_contents("php://input");

	/*   **REMOVE** *
if($dbg>1){
	$payidx=$_GET['x'] ?? '???';
echo "PAYIDX: '$payidx'\n";
	$payload = file_get_contents("c://html/wrk/lora/$payidx.txt");
echo "Payload:\n$payload\n\n";
}
**/

	// Wandle den JSON-Payload in ein Array um
	$data = json_decode($payload, true);

	// devEui ist MAC - Muss Uppercase sein!
	if (isset($data['deviceInfo']['devEui'])) $mac = strtoupper($data['deviceInfo']['devEui']);
	else exit_json_error('Invalid Payload');
	if (!isset($mac) || strlen($mac) != 16) exit_json_error("DevEui/MAC Len");

	$dpath = S_DATA . "/$mac";                // Device Path global
	$xlog = "(lxu_ltxlora_v1)";                 // Scriptname fuer Log

	if (@file_exists("$dpath/cmd/dbg.cmd"))  if (!$dbg) $dbg = 1;

	if (!isset($api_key)) exit_json_error("API Key"); // Required
	$dapikey = @file_get_contents("$dpath/dapikey.dat"); // false oder KEY
	if ($dapikey === false || strcmp($api_key, $dapikey)) { //
		include("conf/check_dapikey.inc.php"); // only on demand: check extern, opt. set daksave
		if ($dapikey === false || strcmp($api_key, $dapikey)) exit_json_error("API Key");
	}

	if (check_dirs()) exit_json_error("Error (Directory/MAC not found)");
	if (isset($daksave)) file_put_contents("$dpath/dapikey.dat", $dapikey); // Update Key

	// For Debug: record complete payload (WARNING: A lot of data!)
	if ($dbg) file_put_contents("$dpath/dbg/indata.log", $now_str . ":\n" . $payload . "\n\n", FILE_APPEND);

	// Isolate important vaiabled
	$unix_ts = strtotime(@$data['time']);
	if ($unix_ts < 1000000000) exit_json_error("Illegal date");
	$object = $data['object'] ?? [];
	$fcnt = $data['fCnt'] ?? (-199);
	$rssi = -199;
	$snr = -199;
	// Find strongest Signal
	foreach ($data['rxInfo'] as $rxs) {
		if ($rxs['rssi'] > $rssi) {
			$rssi = $rxs['rssi'];
			$snr = $rxs['snr'];
		}
	}
	if ($fcnt < 0 || $rssi == -199 || $snr == -199) $paylog = true;

	// Working Variables
	$maxlen = strlen(base64_decode($data['data'])); // Uploaded Data Payload
	$devi = read_ini("$dpath/device_info.dat");
	$devi['now'] = $now;
	$devi['pasync'] = '1';    // LORA may be asynchron (und keine Disc)
	// First of all: quota per UTC-day (in Bytes) only for info
	$day = floor($now / 86400); // intdiv >= PHP7
	if (@$devi['day'] != $day) { // new day, new quota
		@$devi['total_in'] += @$devi['quota_in'];    // Keep olds...
		@$devi['total_out'] += @$devi['quota_out']; // out: *todo* 
		$devi['day'] = $day;
		$devi['quota_in'] = $maxlen;
		$devi['quota_out'] = 0;
		$devi['dpack0'] = $fcnt;	// First Packt for this day
	} else {
		$devi['quota_in'] += $maxlen;
	}
	$ccnt = @$devi['trans'];
	if (!isset($ccnt)) $ccnt = 1;
	else $ccnt++;
	$devi['trans'] = $devi['conns'] = $ccnt;

	// Extract known units to array
	$known_units = [];
	$wnunits = false;
	$akunits = @$devi['kunits'];
	if (isset($akunits)) {
		$kunits =  explode(' ', $akunits);
		foreach ($kunits as $ku) {
			$tmp = explode(":", $ku);
			$known_units[$tmp[0]] = $tmp[1];
		}
	} else $wnunits = true; // In jedem Fall schreiben


	// Generate Data line (and units)
	$line = "!$unix_ts";
	$units = "!U";
	$reason = 2;  // Annahme AUTO

	// ----- LTX Payload START -----
	// Add chans from LTX Payload
	foreach ($object['chans'] as $chan) {
		$idx = intval($chan['channel']);	// Chirpstack sends Float? Channel Index
		$unit = @$chan['unit'];
		$val = @$chan['value'] ?? '?';
		$emsg = @$chan['msg']; // If set: Channel has ERROR
		$line .= " $idx:";
		if (isset($emsg)) $line .= $emsg;
		else $line .= $val;
		if (isset($unit)) {
			$unit = str_replace(' ', '', $unit); // No WS allowed
			$units .= " $idx:$unit";
			if (@$known_units[$idx] !== $unit) {
				$wnunits = true;
				$known_units[$idx] = $unit;
			}
		}
	}

	// Add HKs always available: 
	$frel = $fcnt - $devi['dpack0']; // Relative Packet this day
	if ($frel < -199) {
		$frel = -199;
		$paylog = true;
	}
	$rpack = $frel;
	$line .= " 100:$rpack 102:$rssi 103:$snr";
	$units .= " 100:relNo 102:RSSI(dBm) 103:SNR(dB)";

	$edtdata = array($line . "\n");

	$ltxflags = $object['flags'];	// Nur (Reset) pruefen, Rest ignorieren
	if (strpos($ltxflags, '(Reset)') !== false) {
		array_unshift($edtdata, "<RESET>\n");
	}
	$ltxreason = $object['reason']; // Nur Manuelle Uebertragungen explizit anzeigen
	if (strpos($ltxreason, '(Manual)') !== false) {
		array_unshift($edtdata, "<NT MANUAL>\n");
		$reason = 3; // Manual
	}

	// Units changed?
	if ($wnunits) {
		$known_units[100] = 'relNo';
		$known_units[102] = 'RSSI(dBm)';
		$known_units[103] = 'SNR(dB)';
		$akunits = "";
		foreach ($known_units as $kk => $kv) {
			$akunits .= " $kk:$kv";
		}
		$devi['kunits'] = trim($akunits);
		$cookie = $now;
		$devi['cookie'] = $cookie;
		array_unshift($edtdata, $units . "\n");
		array_unshift($edtdata, "<COOKIE $cookie>\n");
	}
	// ----- LTX Payload END -----


	write_ini("$dpath/device_info.dat", $devi);

	$ftemp = gmdate("Ymd_His", $now) . '_lora.edt'; // No File Pos

	file_put_contents("$dpath/in_new/" . $ftemp, $edtdata);
	add_data($edtdata);

	$mtrun = round((microtime(true) - $mtmain_t0) * 1000, 4);
	$xlog .= "(Run:$mtrun msec)"; // Add old log Script Runtime
	$okreply = array("status" => "ok");
	if ($dbg) $okreply['xlog'] = $xlog;

	if ($paylog) $xlog .= "(PAYLOG:'\n$payload\n')"; // Sonderfaelle ins Log aufnehmen

	add_logfile(); // Regular exit, entry in logfile should be first

	// Sende einfache Bestätigung
	echo json_encode($okreply);

	$xlog .= call_trigger($mac, $reason);
} catch (Exception $e) {
	$errm = $e->getMessage();
	exit_json_error($errm);
}
// ***
