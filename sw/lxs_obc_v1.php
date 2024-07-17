<?php
/* lxs_obc_v1.php - Periodic Service for Orbcomm (driven by cron)
* 23.05.2024  - (C)JoEmbedded.de
*
* ------------------------------------------------------
* INFO: ORBCOMM IGWS2 muss periodisch gepollt werden.
* Maximal (by default) 10 Aufrufe/Minute moeglich, 
* per Standard CRON aber nur minimal alle 60 Sekunden moeglich.
* Auf Strato-Hosting nur alle 1h.
*
* Wichtig: 
* Wenn als SCRIPT aufgerufen, dann sind keine URL-Parameter/$_SERVER[] vorhanden. 
* Kommandozeile dann z.B.: '/bin/php ./WordPress_SecureMode_04/ltx/sw/lxs_obc_v1.php' (Ausgabe: 'nie')
* Die Satelliten-Modems werden vom 'service.php' nicht verwaltet, lediglich die Daten ('quota').
* (Obsolete) Satelliten-Modems daher manuell im '/orbcomm'-Directory verwalten.
*
* ------------------------------------------------------
*
* https://joembedded.de/ltx/sw/lxs_obc_v1.php
* http://localhost/ltx/sw/lxs_obc_v1.php
* No URL Parameters for PHP CLI
* Maximum run Frequency each 10 seconds!
*
* Anscheinend haelt Orbcomm die Messages nur ca. 4 Tage vor!
* Es ist mgl. in der Zeit zurueckzugehen
*
* *todo*: 
* - iparam und device_ini der gefunden Devices cachen fuer bessere Statistisk und evtl. Werte Verrechnen
* - periodisches Auto-GPS (Command "/x00/x48" oder "/x80/x97")
*
*/

error_reporting(E_ALL);
ini_set("display_errors", true);
ignore_user_abort(true);
set_time_limit(300); // 5 Min runtime
date_default_timezone_set('UTC'); // fuer strtotime

//*** IMPORTANT: ***
//*** For Local access - if CRON not called via HTTP/HTTPS: Edit missing '$_SERVER': ***
//*** Required for call trigger() ***
if (!isset($_SERVER['SERVER_NAME'])) {
	$_SERVER['SERVER_NAME'] = "joembedded.de";
	$_SERVER['REMOTE_ADDR'] = "joembedded.de";
	$_SERVER['PHP_SELF'] = "/ltx/sw/lxs_obc_v1.php";
	$_SERVER['SERVER_PORT'] = 80;
}

include("conf/api_key.inc.php");
include("lxu_loglib.php");

$obx_cred = explode("\n", OBX_ACCESS); // ORBCOMM Credentials
$access_id = @$obx_cred[0];
$password = @$obx_cred[1];

// Adds UTC as URL-Param:
//$getmsg_php = "localhost/wrk/orbcomm/sw/getmsg.php?access_id=$access_id&password=$password";   // URL TEST mit Test-Daten
$getmsg_php = "https://isatdatapro.orbcomm.com/GLGW/2/RestMessages.svc/JSON/get_return_messages/?access_id=$access_id&password=$password"; // URL WORK

// Fuer CMDs keine ALternative PHP
$submitmsg_php = "https://isatdatapro.orbcomm.com/GLGW/2/RestMessages.svc/JSON/submit_messages/"; // URL WORK";

// ---- functions: Return false on Error ----
function runCurlGetMsg($reason, $script)
{
	global $xlog, $dbg;
	$ch = curl_init($script);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$res = curl_exec($ch);
	if ($dbg) {
		$rlen = strlen(@$res);
		if ($rlen > 80) $sres = substr(@$res, 0, 80) . '...';
		else $sres = $res;
		$xlog .= "(Curl: $reason:'$script': [$rlen]'$sres')";
	}
	if (curl_errno($ch)) {
		$xlog .= "(ERROR: $reason:(" . curl_errno($ch) . "):'" . curl_error($ch) . "')";
		$res = false;
	} else {
		$cinfo = curl_getinfo($ch);
		if (intval(@$cinfo['http_code'] != 200)) {
			$xlog .= "(ERROR: $reason '" . @$cinfo['http_code'] . "')"; // z.B 404: NotFound (200: OK)
			$res = false;
		}
	}
	curl_close($ch);
	return $res;
}

function runCurlCmdPost($url, $jdata)
{
	global $xlog, $dbg;
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($ch, CURLOPT_POSTFIELDS, $jdata);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8', 'Content-Length: ' . strlen($jdata)));
	$res = curl_exec($ch);
	if ($dbg) {
		$rlen = strlen(@$res);
		if ($rlen > 80) $sres = substr(@$res, 0, 80) . '...';
		else $sres = $res;
		$xlog .= "(CurlPost: '$url': [$rlen]'$sres')";
	}
	if (curl_errno($ch)) {
		$xlog .= "(ERROR: CurlPost:(" . curl_errno($ch) . "):'" . curl_error($ch) . "')";
		$res = false;
	} else {
		$cinfo = curl_getinfo($ch);
		if (intval(@$cinfo['http_code'] != 200)) {
			$xlog .= "(ERROR: CurlPost '" . @$cinfo['http_code'] . "')"; // z.B 404: NotFound (200: OK)
			$res = false;
		}
	}
	curl_close($ch);
	return $res;
}

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
	$arg = "k=" . S_API_KEY . "&r=$reason&s=$mac";	// Parameter: API-KEY, reason and MAC
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

// Write content Data into LEGACY Directory
function add_data($mac, $cont)
{
	global $xlog, $devi; // devi: *todo*
	if (@filesize(S_DATA . "/$mac/files/data.edt") > 1000000) {	// 2*1MB per Device Maximum
		@unlink(S_DATA . "/$mac/files/data.edt.bak");
		@rename(S_DATA . "/$mac/files/data.edt", S_DATA . "/$mac/files/data.edt.bak");
		$xlog .= " ('data.edt' -> '/data.edt.bak')";
		$dunits = @$devi['units'];
		if (isset($dunits)) array_unshift($cont, $dunits . "\n");
	}
	@file_put_contents(S_DATA . "/$mac/files/data.edt", $cont, FILE_APPEND);
}

// Read/Write INI-File - Gibt leeres array zurueck wenn nicht existent. 
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

// Helpers
function get_be_u32($b4)
{
	return ($b4[0] << 24) + ($b4[1] << 16) + ($b4[2] << 8) + $b4[3];
}
function get_be_u16($b2)
{
	return ($b2[0] << 8) + $b2[1];
}
function get_be_i16($b2)
{
	$ires = ($b2[0] << 8) + $b2[1];
	if ($ires > 32767) $ires -= 65536;
	return $ires;
}
// Float in jedem Fall als FLOAT - Not rounded
function get_f32($b4)
{
	$uval = ($b4[0] << 24) + ($b4[1] << 16) + ($b4[2] << 8) + $b4[3];
	$fval = decode_f32($uval);
	return $fval;
}
// Float als String (rounded) oder Fehler
function get_ef32($b4)
{
	$uval = ($b4[0] << 24) + ($b4[1] << 16) + ($b4[2] << 8) + $b4[3];
	if (($uval >> 24) == 0xFD) {
		$errno = $uval & 0xFFFFFF;
		return get_errstr($errno);
	}
	$fval = decode_f32($uval);
	return round($fval, 8); // Float max. 8 Digits
}
function get_errstr($errno)
{ 	// wie measure.c - Jo Standard Errors
	switch ($errno) {
		case 1:
			return 'NoValue';
		case 2:
			return 'NoReply';
		case 3:
			return 'OldValue';
			// 4,5
		case 6:
			return 'ErrorCRC';
		case 7:
			return 'DataError';
		case 8:
			return 'NoCachedValue';
		default:
			return "Err$errno";
	}
}

// Convcersion U32 -> Float IEEE 754
function decode_f32($bin)
{
	$sign = ($bin & 0x80000000) > 0 ? -1 : 1;
	$exp = (($bin & 0x7F800000) >> 23);
	$mantis = ($bin & 0x7FFFFF);

	if ($mantis == 0 && $exp == 0) {
		return 0;
	}
	if ($exp == 255) {
		if ($mantis == 0) return INF;
		if ($mantis != 0) return NAN;
	}
	if ($exp == 0) { // denormalisierte Zahl
		$mantis /= 0x800000;
		return $sign * pow(2, -126) * $mantis;
	} else {
		$mantis |= 0x800000;
		$mantis /= 0x800000;
		return $sign * pow(2, $exp - 127) * $mantis;
	}
}

// Flags nach Text , evtl. noch Bits als Reason speichern fier das Geraet
function flags2txt($flags, &$xlines, $msno)
{
	global $modem_list;

	if ($flags >= 0) {
		// $devi['reason'] = $flags; // *todo*
		if ($flags & 128) { // Separat
			$xlines[] = "<RESET>";
		}

		switch ($flags & 15) { //
			case 2:
				$rea_str = "AUTO";
				break;
			case 3:
				$rea_str = "MANUAL";
				break;
			default:
				$rea_str = "UNKNOWN(reason=$flags)";
				break;	// Alarm e.g. t.b.d
		}
		if ($flags & 512) {
			$xlines[] = "<ERROR: BatteryEmpty>";
		} else if ($flags & 256) {
			$xlines[] = "<WARNING: BatteryLow>";
		}
		// t.b.d. if ($flags & 64) $rea_str .= ",ALARM"; else if ($flags & 32) $rea_str .= "old ALARM";
		$modem_list[$msno][1] = ($flags & 255);	// Save last Reason
	} else { // <0 Reserved for Errors
		$rea_str = "UNKNOWN(reason=$flags)";
	}
	$xlines[] = "<NT $rea_str OK>";
}
// HK als String mit Leerzeichen
function get_hke16($hk, $b2)
{
	$hkval16 = get_be_i16($b2);
	if ($hkval16 == -32515) return "Error"; // == 0x80FD Unknown
	switch ($hk) {
		case 90:
			return round($hkval16 / 1000, 3);	// 90 Geräte-Batterie VBat in mV
		case 91:
			return round($hkval16 / 100, 2); // 91 Geräte-Temperatur in 0.01°C
		case 94: // 94 Geräte-Baro in 0.1 mBar
		case 92:
			return round($hkval16 / 10, 1); // 92 Geräte-Feuchtigkeit in 0.1%rH
		case 93: // 93 Verbrauchte Batteriekapazität in mAh
		default: // 95-99 (noch nicht verwendet)
			return $hkval16;
	}
}

// expand Payload and add to List. Return Text added if set
function jopay_expand($dbytes, $mutc_ts, $msno, $mid)
{
	global $xlog;

	$dbcnt = count($dbytes);

	$xlines = array();
	unset($traveltime);
	unset($pidx);
	$dbidx = 0;
	$line = '';
	for (;;) {
		if ($dbidx >= $dbcnt) break;
		$tok = $dbytes[$dbidx++];
		switch ($tok) {
				// 00-89 als Kanaele direkt
			case 90:	// HKs
			case 91:
			case 92:
			case 93:
			case 94:
			case 95:
			case 96:
			case 97:
			case 98:
			case 99:
				if ($dbcnt - $dbidx < 2) goto swexit;
				$hkvaltxt = get_hke16($tok, array_slice($dbytes, $dbidx, 2));
				$dbidx += 2;
				$line .= " $tok:$hkvaltxt";
				break;

			case 150: // Values
				if ($dbcnt - $dbidx < 8) goto swexit;
				$flagsu16 = get_be_u16(array_slice($dbytes, $dbidx, 2));
				$dbidx += 2;
				$pidx = get_be_u16(array_slice($dbytes, $dbidx, 2));
				$dbidx += 2;
				$lutc = get_be_u32(array_slice($dbytes, $dbidx, 4));
				$dbidx += 4;
				flags2txt($flagsu16, $xlines, $msno);	// May generate Line
				$line .= "!$lutc";
				if ($dbcnt - $dbidx > 0) {
					$anz = $dbytes[$dbidx++];	// Anz. Values
					if ($dbcnt - $dbidx < ($anz * 4) || $anz > 90) goto swexit;
					for ($i = 0; $i < $anz; $i++) {
						$valtxt = get_ef32(array_slice($dbytes, $dbidx, 4));
						$dbidx += 4;
						$line .= " $i:$valtxt";
					}
				}
				// Synthesizd Packets Packet Index and Traveltime
				$traveltime = $mutc_ts - $lutc;
				break;
			case 151: // GNSS GPS-Position 
				if ($dbcnt - $dbidx < 8) goto swexit;
				$latitude = round(get_f32(array_slice($dbytes, $dbidx, 4)), 5);
				$dbidx += 4;
				$longitude = round(get_f32(array_slice($dbytes, $dbidx, 4)), 5);
				$dbidx += 4;
				$line = "<GNSS GPS $latitude $longitude>";
				$xlines[] = $line;
				$line = '';
				break;
			case 152: // MAC-Report (Automatic after PowerOn)
				if ($dbcnt - $dbidx < 8) goto swexit;
				$mach = get_be_u32(array_slice($dbytes, $dbidx, 4));
				$dbidx += 4;
				$macl = get_be_u32(array_slice($dbytes, $dbidx, 4));
				$dbidx += 4;
				$newmac = strtoupper(str_pad(dechex($mach), 8, '0') . str_pad(dechex($macl), 8, '0'));
				$line = "<MAC $newmac>";
				$xlines[] = $line;
				$line = '';
				// MAC optional speichern
				$devi = read_ini(S_DATA . "/orbcomm/$msno.dat");
				if (@$devi['MAC'] !== $newmac) { // Both Strings
					$xlog .= "(Attach Modem '$msno' to MAC:$newmac)";
					$devi['MAC'] = $newmac;
					$devi['mtime0'] = $mutc_ts;
					write_ini(S_DATA . "/orbcomm/$msno.dat", $devi);
				}
				break;
				swexit:
			default:
				$line = "<ERROR: INVALID TOKEN $tok>";
				if (strlen($xlog < 256)) $xlog .= "(ERROR: INVALID TOKEN $tok in Msg. $mid)";
				$dbidx = $dbcnt; // Force Exit
		}
	}
	if (isset($traveltime) && isset($pidx))	$line .= " 100:$pidx 101:$traveltime";
	if (strlen($line)) $xlines[] = $line;
	$anz = count($xlines);
	//$lutc might be undefined
	for ($i = 0; $i < $anz; $i++) add2decoded($msno, $mid, $mutc_ts, @$lutc, $xlines[$i]);

	return null; // No Error
}

// Add Line to List
function add2decoded($msno, $mid, $mutc, $dutc, $ltxt)
{
	global $decoded_lines;
	$nentry = array($msno, $mid, $mutc, $dutc, $ltxt);
	$decoded_lines[] = $nentry;
}

function gen_default_iparam($mac)
{ // Default-iparam generieren - mit 2 Channels
	global $now, $xlog;
	$typ = 920;
	$prefix = "OBC";
	$iparam = array("@100", $typ, 48 /*channels*/, '3' /*HK_FLAGS*/, $now,   $prefix . substr($mac, -11), '0' /* Period 0:Unknown*/, '0', '0', '0', '0', '0', '1', '0', '1', '1', '0', '-40', '0', '');
	for ($i = 0; $i < 2; $i++) {
		//                           Fl Phy Cap   x
		$iparam = array_merge($iparam, array("@$i", '255', '0', '', '0', "Chan$i", '8', '0', '0.0', '1.0', '0.0', '0.0', '0', ''));
	}
	// Ohne sys_param fragt trigger nach sys_param.. Ignorieren
	file_put_contents(S_DATA . "/$mac/files/iparam.lxp", implode("\n", $iparam) . "\n");
	$xlog .= ("'iparam.lxp' generated");
	return $iparam;
}

function gen_units_iparam(&$iparam)
{
	$units = '!U';
	$mi = count($iparam);
	for ($i = 1; $i < $mi; $i++) {
		if (@$iparam[$i][0] == '@') {
			$kn = intval(substr($iparam[$i], 1));
			$ku = @$iparam[$i + 5];
			$units .= " $kn:$ku";
		}
	}
	$units .= " 90:V(Bat) 91:oC(int) 100:PackInd 101:Travel(sec)\n";
	return $units;
}

// ---Debug--- Show List (Debug)
function showdecoded()
{
	global $decoded_lines;
	global $modem_list;
	$danz = count($decoded_lines);
	foreach ($modem_list as $msno => $mtmp) {
		$manz = $mtmp[0];
		$mlr = $mtmp[1]; // LastReason
		$mota = $mtmp[2]; // Bytes OverTheAir
		$modem_meta = read_ini(S_DATA . "/orbcomm/$msno.dat");
		$mac = @$modem_meta['MAC']; // null if undefined
		if (!isset($mac)) $mac = "0000000000000000";

		echo "'$msno' (MAC:$mac) $manz Msg./ $mota Bytes(Last Reason:$mlr):\n";

		for ($i = 0; $i < $danz; $i++) {
			$lentry = $decoded_lines[$i];
			$msg_msno = $lentry[0];
			if ($msg_msno == $msno) {
				$mid = $lentry[1]; // Message ID
				$mutc = $lentry[2];	// Message TS
				// $dutc= $lentry[3]; // only info
				$ltxt = $lentry[4];
				echo "$i: [$mid] $mutc - $ltxt\n";
			}
		}
	}
}

// -------------------------------------- M A I N --------------------------------

header('Content-Type: text/plain');

$dbg = 0; // Debug-Level if >0, see docu

$now = time();						// one timestamp for complete run
$mtmain_t0 = microtime(true);         // for Benchmark 

// ---- Data sort in 2 global arrays: ---
$decoded_lines = array(); // Messages: array($msno, $mid, $mutc, $dutc, $ltxt); 
$cmd_list = array();	// Falls commadns gesendet werden sollen array($msno,$cmdid,$usercmd,$mac);
$modem_list = array(); // Modmes: array($cnt, $lastreason, $anzbytes) Counts No of Messages for this Modem and stores last reason

$xlog = "(lxs_obc_v1_MSG)";
$mac = '';		// Must be defined for logfiles

// Evtl. generate Directories
if (!file_exists(S_DATA)) {
	mkdir(S_DATA);  // MainDirectory
	$xlog .= "(Generated Device Directory '" . S_DATA . "/')";
}
if (!file_exists(S_DATA . "/log")) {
	mkdir(S_DATA . "/log");  // Log-Files
	$xlog .= "(Generated 'log/')";
}
if (!file_exists(S_DATA . "/orbcomm")) {
	mkdir(S_DATA . "/orbcomm");
	$xlog .= "(Generated 'orbcomm/')";
}

$obx_meta = read_ini(S_DATA . "/orbcomm/obx_meta.dat");
$start_utc = @$obx_meta['StartUTC'];

$lastrun_ts = @$obx_meta['lastrun_ts'];

if (($now - $lastrun_ts) < 10) exit_error("Frequency");

$obx_meta['lastrun_ts'] = $now;
$now_str = gmdate("Y-m-d H:i:s", $now); // Readable
$obx_meta['lastrun'] = $now_str;
if (!isset($start_utc)) $start_utc = "2024-01-01 00:00:00"; // Get everything old

// Nur zum Test, spaer raus
if ($dbg) file_put_contents(S_DATA . "/orbcomm/obx_call.log", $now_str . " 'totalcnt':" . $obx_meta['totalcnt'] . "\n", FILE_APPEND);

for (;;) { // break-Dummy
	// Get all new messages

	$jobc_msgs = runCurlGetMsg("Get Messages", $getmsg_php . "&start_utc=" . rawurlencode($start_utc));
	if ($jobc_msgs === false) break;
	$obc_msgs = json_decode($jobc_msgs); // Als Object
	$msgs = @$obc_msgs->Messages;
	$nextStartUTC = $obc_msgs->NextStartUTC; // Save for next call
	if (!isset($msgs)) break; // Nothing

	$msg_cnt = count($msgs);
	@$obx_meta['totalcnt'] += $msg_cnt; // Stats (Count all Msg)

	$xlog .= "($msg_cnt New Messages)";
	foreach ($msgs as $msg) {
		$mid = $msg->ID;	// ID der Msg (only relevant for dbg)
		$modemsno = $msg->MobileID;	// SNO of modem - Key
		$dsize = @$msg->OTAMessageSize;
		$thism = @$modem_list[$modemsno];
		if (!isset($thism)) $modem_list[$modemsno] = array(1, 15, $dsize);	// 1 Cnts, unknown reason, 0 Bytes
		else {
			$modem_list[$modemsno][0]++; // Cnt
			$modem_list[$modemsno][2] += $dsize;
		}
		$sin = $msg->SIN;
		$mutc = $msg->MessageUTC;
		$mutc_ts = strtotime($mutc);

		$mline_txt = "<WARNING: OBC_UNKNOWN ID:$mid SIN:$sin>";	// Info after 1.st SPACE

		if ($sin < 128) {	// Scan Auto/Internal Msg (decoded)
			$payload = $msg->Payload;
			$pname = $payload->Name;
			$min = $payload->MIN;
			$mline_txt = "<WARNING: OBC_UNKNOWN $pname>";
			$map = array();
			foreach ($payload->Fields as $field) $map[$field->Name] = $field->Value;
			if ($sin == 0) {
				if ($min == 72) {	// Modem Auto-Position (speed/direction noch offen)
					$fix = intval($map['fixStatus']);
					$latitude = round(intval($map['latitude']) / 60000, 5); // WGS84
					$longitude = round(intval($map['longitude']) / 60000, 5);
					$altitude = intval($map['altitude']); // in m
					$mline_txt = "<GNSS OBCGPS $latitude $longitude $altitude(Alt:m)";
					if ($fix !== 1) $mline_txt .= " (NoValidFix:$fix)";
					$mline_txt .= ">";
				} else if ($min == 0) {
					$reason = $map['lastResetReason'];
					$mline_txt = "<OBC_RESET ($reason)>";
				}
			} else if (strlen($xlog < 256)) $xlog .= "(ERROR: SIN/MIN: $sin/$min in Msg. $mid)";
		} else if ($sin == 128) { // User-Messages
			$rawPayload = $msg->RawPayload;
			if (count($rawPayload) >= 2) { // [0] immer 128, ab 1 Jo-Payload
				$mline_txt = jopay_expand(array_slice($rawPayload, 1), $mutc_ts, $modemsno, $mid);
			}
		} else if (strlen($xlog < 256)) $xlog .= "(ERROR: SIN: $sin in Msg. $mid)";
		if (isset($mline_txt)) add2decoded($modemsno, $mid, $mutc_ts, null, $mline_txt); // DeviceUTC only for Datalines
	}

	break;
} // for(;;)
// Part 1 finished - Save log
$hxlog = $xlog;

// Part 2: Macs
if ($dbg > 1) showdecoded();

// Export Data plus Trigger 
$danz = count($decoded_lines);
foreach ($modem_list as $msno => $mtmp) {
	$xlog = "(lxs_obc_v1_MAC)"; // Per Device
	$manz = $mtmp[0]; // Messages
	$mlr = $mtmp[1]; // LastReason
	$maxlen = $mtmp[2];	// Anzahl Bytes
	$modem_meta = read_ini(S_DATA . "/orbcomm/$msno.dat");
	$mac = @$modem_meta['MAC']; // null if undefined
	if (!isset($mac)) $mac = "0000000000000000";
	check_dirs(false);	// false for devices without FS

	// New Parameters?
	if (file_exists(S_DATA . "/$mac/put/iparam.lxp")) {
		@unlink(S_DATA . "/$mac/files/iparam.lxp");
		rename(S_DATA . "/$mac/put/iparam.lxp", S_DATA . "/$mac/files/iparam.lxp");
		@unlink(S_DATA . "/$mac/cmd/iparam.lxp.pmeta");	// Kill Put-Meta-infos
		@unlink(S_DATA . "/$mac/put/iparam.lxp");	// and mod.Par
	}

	// Param/Data-Files
	if (!file_exists(S_DATA . "/$mac/files/iparam.lxp"))	$iparam = gen_default_iparam($mac);
	else $iparam = file(S_DATA . "/$mac/files/iparam.lxp", FILE_IGNORE_NEW_LINES);

	$devi = read_ini(S_DATA . "/$mac/device_info.dat");

	$devi['now'] = $now;
	$devi['imsi'] = $msno;
	$devi['typ'] = @$iparam[1];
	$devi['reason'] = $mlr;
	$devi['pasync'] = '1';	// Pakets may be asynchron

	// First of all: quota per UTC-day (in Bytes) only for info
	$day = floor($now / 86400); // intdiv >= PHP7
	if (@$devi['day'] != $day) { // new day, new quota
		@$devi['total_in'] += @$devi['quota_in'];	// Keep olds...
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

	// Uploader darf device_info.dat beschreiben!
	$ldata = array();

	for ($i = 0; $i < $danz; $i++) {
		$lentry = $decoded_lines[$i];
		$msg_msno = $lentry[0];
		if ($msg_msno == $msno) {
			//$mid = $lentry[1]; // Message ID
			$mutc = $lentry[2];	// Message TS
			// $dutc= $lentry[3]; // only info
			$ltxt = $lentry[4]; // Zeilentext
			$ldata[] = $ltxt . "\n";
			if (!strncmp($ltxt, "<GNSS ", 6)) { // 0 0 ist invalid!
				$devi['pos'] = trim(substr($ltxt, 6), ">\n");
				$devi['pos_ts'] = $mutc;
			}
		}
	}

	$cookie = $iparam[4];
	if (@$devi['cookie'] !== $cookie) {
		$devi['cookie'] = $cookie;
		$units = gen_units_iparam($iparam);
		array_unshift($ldata, $units);
		array_unshift($ldata, "<COOKIE $cookie>\n");
		$nperiod = $iparam[6];
		$aperiod = @$devi['period'];
		// Optionally send p-command with highest priority to device, may overwrite other user.cmds
		if (($aperiod != $nperiod) && $nperiod > 60) {
			$devi['period'] = $nperiod;
			file_put_contents(S_DATA . "/$mac/cmd/usercmd.cmd", '\x80\x96p' . $nperiod); // SIN:128 MIN:150 'cmd'
		}
	}

	$ftemp = gmdate("Ymd_His", $now) . '_orbcomm.edt'; // No File Pos
	file_put_contents(S_DATA . "/$mac/in_new/" . $ftemp, $ldata);

	add_data($mac, $ldata);

	$xlog .= "($manz Messages)"; // Messages, die in Daten umgesetzt weden konnten

	// Last Step: Command2Send?
	if (file_exists(S_DATA . "/$mac/cmd/usercmd.cmd")) {
		$usercmd = file_get_contents(S_DATA . "/$mac/cmd/usercmd.cmd");
		$cmdid = intval(@$obx_meta['cmdcnt']) + 1;
		$xlog .= "(Send User Command(ID:$cmdid):'$usercmd')";

		$devi['luc_cmd'] = $usercmd;	// The command
		$devi['luc_state'] = "Sent(ID:$cmdid)"; // State
		$devi['luc_date'] = $now;	// Time of sending

		$obx_meta['cmdcnt'] = $cmdid;
		$cmd_list[] = array($msno, $cmdid, $usercmd, $mac);
		@$devi['quota_out'] += strlen($usercmd);	// Angaben nur Ungefaehr!
	}

	add_logfile(); // Regular exit, entry in logfile should be first

	$hxlog .= call_trigger($mac, $mlr);	// Call Trigger for this MAC // **todo: move to outside loop! ***
	write_ini(S_DATA . "/$mac/device_info.dat", $devi);
}

// Part 2:  Messages finished
$xlog = $hxlog;
$mac = '';
if (isset($obc_msgs->NextStartUTC) && strlen($obc_msgs->NextStartUTC)) {
	$obx_meta['StartUTC'] = $obc_msgs->NextStartUTC;
	write_ini(S_DATA . "/orbcomm/obx_meta.dat", $obx_meta);

	// Part 2a: Optionally send Commands
	$cmdcnt = count($cmd_list);
	if ($cmdcnt) { // Commands to send
		$cmdmessages = array();
		for ($i = 0; $i < $cmdcnt; $i++) {
			$tcm = $cmd_list[$i]; // $msno,$cmdid,$usercmd,$mac
			$RawPayload = array();
			$RawPayloadStr = stripcslashes($tcm[2]); // Unescape \x80\x96p1800 -> 128 150 'p1800' oder \x80\x97 fuer GPS
			$pllen = strlen($RawPayloadStr);
			for ($idx = 0; $idx < $pllen; $idx++) { // Convert to Hexstring
				$RawPayload[] = ord($RawPayloadStr[$idx]);
			}
			$cmdmessages[] = array('DestinationID' => $tcm[0], 'UserMessageID' => $tcm[1], 'RawPayload' => $RawPayload);
		}
		$cmdarg = array('access_id' => $access_id, 'password' => $password, 'messages' => $cmdmessages);
		$jcmdarg = json_encode($cmdarg);

		if ($dbg > 1) echo "\n\nSend Commands: URL:$submitmsg_php TEST: '$jcmdarg'\n\n";

		$res = runCurlCmdPost($submitmsg_php, $jcmdarg);
		if ($res !== false) { // Post war OK, CMDs loeschen
			for ($i = 0; $i < $cmdcnt; $i++) {
				$tcm = $cmd_list[$i]; // $msno,$cmdid,$usercmd,$mac
				@unlink(S_DATA . "/" . $tcm[3] . "/cmd/usercmd.cmd");
			}
		}
	}
}

$mtrun = round((microtime(true) - $mtmain_t0) * 1000, 4);
$xlog .= "(Run:$mtrun msec)"; // Add old log Script Runtime
/* V1.0: record each call! */
add_logfile(); // Regular exit, entry in logfile should be first

echo "*** Result: $xlog ***\n";

// ***
