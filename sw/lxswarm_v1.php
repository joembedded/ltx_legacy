<?php
/* lxswarm_v1.php Server-Communication Script for LTrax via SWARM Leo Satellites. Details: see docu
* This is a Webhook
* (C) 05.12.2022 - V1.20 joembedded@gmail.com  - JoEmbedded.de
*
* SWARM has 'F000..' as MAC-Trailer!
*
* Setup in SWARM Hive:
* 1. URL: https://joembedded.de/ltx/sw/lxswarm_v1.php
* 2. Headers:   mailto 	e.g. joembedded@gmail.com  AND apikey D_API_KEY!
* Will allow to Auto-generate device!
*
* if $dbg>0: Allow simulated Payload, e.g.
*  http://localhost/ltx/sw/lxswarm_v1.php?x={"packetId":123,"deviceType":1,"deviceId":8654,"userApplicationId":0,"organizationId":654,"data":"gAAAY312lQH9AAACWhARWwIN","len":18,"status":0,"hiveRxTime":"2022-11-23T01:28:39"}
*   gAACY4pnUwZBTwrmQAPUa0IHfLBAAAAAAAAAAEDA9cJaDjdbAAA=
*
* *todo* 
* - Data may arrive NOT in logical order! Use 'line_ts','id' as sort ORDER for SELECT
* - Linearise/Alarms
*/

error_reporting(E_ALL);
include("conf/api_key.inc.php");
include("lxu_loglib.php");

// Payload Functions START
function get_i16($valstr)
{
	$ui16 = unpack('n', $valstr)[1];
	if ($ui16 > 32767) $ui16 -= 65536;	// there is no unpack for BE-i16
	return $ui16;
}
function get_u32($valstr)
{
	return unpack('N', $valstr)[1];
}
function get_ef32($valstr)
{
	$hval = intval(unpack('N', $valstr)[1]);
	if (($hval >> 24) == 0xFD) {
		$errno = $hval & 0xFFFFFF;
		return get_errstr($errno);
	}
	return round(decode_f32($hval), 8); // Float max. 8 Digits
}
function get_errstr($errno)
{ // wie measure.c
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

function decode_f32($bin) // U32 -> Float IEEE 754
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

function decode_payloadRTN($strdata)
{
	global $pidx, $args, $idx, $devi, $xlog;
	global $iparam, $idxk0, $ikanist, $ikanused;

	$cnt = strlen($strdata);

	$ikanused = 1; // Minimum
	$lines = array();
	//$txtdata = "Payload:";
	if ($cnt - $idx >= 2) {
		$flags = get_i16(substr($strdata, $idx, 2));
		//$txtdata.= sprintf(' Flags:%d', $flags);
		$idx += 2;
		if ($flags >= 0) {
			$devi['reason'] = $flags;
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
			if ($flags & 128) { // Separat
				$xlog .= "(RESET)";
				$lines[] = "<RESET>";
			}

			if ($flags & 256) {
				$rea_str .= ",BatteryLow";
			}
			if ($flags & 512) {
				$rea_str .= ", BatteryEmpty";
			}
			// t.b.d. if ($flags & 64) $rea_str .= ",ALARM"; else if ($flags & 32) $rea_str .= "old ALARM";
		} else {
			$rea_str = "UNKNOWN(reason=$flags)";
		}
		$xlog .= "($rea_str)";
		$lines[] = "<NT $rea_str>";
	}
	if ($cnt - $idx >= 4) {
		$unixsecs = get_u32(substr($strdata, $idx, 4));
		$devi['dtime'] = $unixsecs;
		//$txtdata.= sprintf(' Time(UTC):%s', gmdate("d.m.Y H:i:s",$unixsecs));
		$dline = "!$unixsecs";
		$uline = "!U";
		$idx += 4;
		// Generate Traveltime:
		$traveltime = time() - $unixsecs;
		//$txtdata.= sprintf(' Traveltime(sec):%d',$traveltime); // below
	}
	if ($cnt - $idx >= 1) {
		$anzn = ord($strdata[$idx]);
		$idx++;
		for ($j = 0; $j < $anzn; $j++) {	// Channel-Values
			if ($cnt - $idx >= 4) {
				$ikanused = $j + 1;	// Save last used kan
				if ($j < $ikanist)	$tunit = strtr($iparam[$idxk0 + 14 * $j + 5], "'\"< >:", "______");	// Remove strange chars
				else $tunit = "Chan$j";
				$chanstr = get_ef32(substr($strdata, $idx, 4));
				//$txtdata.= " #$j:$chanstr";
				$dline .= " $j:$chanstr";
				$uline .= " $j:$tunit";
				$idx += 4;
			}
		}
	}
	while ($cnt - $idx >= 3) {	// HK-Values immer 1+2
		$hkno = ord($strdata[$idx]);
		if ($hkno == 90) {
			$hkvbat = get_i16(substr($strdata, $idx + 1, 2));
			$fval = round($hkvbat / 1000, 3);
			//$txtdata.= " HK_Bat(V):$fval";
			$dline .= " 90:$fval";
			$uline .= " 90:V(Bat)";
			$idx += 3;
		} else if ($hkno == 91) {
			$hktemp = get_i16(substr($strdata, $idx + 1, 2));
			$fval = round($hktemp / 100, 2);
			//$txtdata.= " HK_Temp(oC):$fval";
			$dline .= " 91:$fval";
			$uline .= " 91:oC(int)";
			$idx += 3;
		} else break;	// undecoded HK is Error
	}
	if (isset($dline)) {
		$dline .= " 100:$pidx 101:$traveltime";
		$uline .= " 100:PackInd 101:Travel(sec)";
		$dunits = @$devi['units'];
		if (!isset($dunits) || strcmp($dunits, $uline)) {
			$lines[] = $uline;
			$devi['units'] = $uline;
		}
		$lines[] = $dline;
	}

	if ($cnt == $idx) @$devi['trans']++;   // Increment No of (complete) tranmissions	
	//$txtdata.= " HKX_Idx:$pidx";
	return $lines;
}
// Payload Functions END

function add_data($cont)
{
	global $dpath, $xlog, $devi;
	if (@filesize($dpath . "/files/data.edt") > 1000000) {	// 2*1MB pro SWARM
		@unlink($dpath . "/files/data.edt.bak");
		@rename($dpath . "/files/data.edt", $dpath . "/files/data.edt.bak");
		$xlog .= " ('data.edt' -> '/data.edt.bak')";
		$dunits = @$devi['units'];
		if (isset($dunits)) $cont = $dunits . "\n" . $cont;
	}
	$of = @fopen($dpath . "/files/data.edt", 'a');
	fputs($of, $cont);
	fclose($of);
}

// ---------------- Trigger: External async Script lxu_trigger.php -------------------------
function trigger($reason)
{
	global $xlog, $mac, $dbg; // xlog only as parameter

	$self = $_SERVER['PHP_SELF'];
	$port = $_SERVER['SERVER_PORT'];
	$isHttps =  (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')  || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
	if ($isHttps) $server = "https://";
	else $server = "http://";
	$server .= $_SERVER['SERVER_NAME'];
	$rpos = strrpos($self, '/'); // Evtl. check for  backslash (only Windows?)
	$tscript = substr($self, 0, $rpos) . "/lxu_trigger.php";
	$arg = "k=" . S_API_KEY . "&r=$reason&s=$mac";	// Parameter: API-KEY, reason and MAC

	// return;	// Ein-kommentieren um Trigger Script NICHT starten wenn ohne DB

	// First check if Trigger is available
	$xlog = "";

	if ($dbg > 1) {
		echo "Start Trigger '$server:$port/$tscript?$arg'\n";
	}

	if ($dbg) $xlog = "(Trigger: '$server:$port/$tscript?$arg')";

	$ch = curl_init("$server:$port/$tscript?$arg");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
	curl_setopt($ch, CURLOPT_TIMEOUT, 1);
	if ($dbg) {
		$res = curl_exec($ch);	// Might be very long!
		$xlog .= "(Curl Result:\nSTART=====>\n$res\n<=====END)";
	} else curl_exec($ch);
	if (curl_errno($ch)) $xlog .= '(ERROR: Curl:' . curl_error($ch) . ')';
	curl_close($ch);

	if (strlen($xlog)) {
		add_logfile();	// If triger fails: Add ne line to logfile
	}
}

function check_iparam()
{
	global $mac, $xlog, $now, $iparam, $idxk0, $ikanist, $ikanused, $devi;
	if ($ikanist >= $ikanused) return;	// File OK

	if ($ikanist < 1 || $idxk0 < 0 || !isset($iparam) || count($iparam) < 19) {
		//                          Ch HKm Cookie  NameAsAdvertised // Max. PHDR.8+46*Kan=192 (/6HK)
		$iparam = array("@100", $devi['typ'], 44, '3', $now,   "AMS" . substr($mac, -11), '3600', '0', '0', '0', '0', '3600', '1', '0', '1', '1', '0', '-40', '0');
		$ikanist = 0;
	}
	for ($i = $ikanist; $i < $ikanused; $i++) {
		//                           Fl Phy Cap
		$iparam = array_merge($iparam, array("@$i", '1', '0', '', '0', "Chan$i", '8', '0', '0.0', '1.0', '0.0', '0.0', '0', ''));
	}
	$ikanist = $ikanused;
	$res = file_put_contents(S_DATA . "/$mac/files/iparam.lxp", implode("\n", $iparam) . "\n");
	$xlog .= ("'iparam.lxp' updated");
}

// -------------------------------------- M A I N --------------------------------
header('Content-Type: text/plain; charset=UTF-8');

$dbg = 0; // Debug-Level if >0, see docu
if ($dbg) echo "*** DEBUG:$dbg ***\n";

$now = time();						// one timestamp for complete run
$mtmain_t0 = microtime(true);       // for Benchmark 
$dfn = gmdate("Ymd_His", $now);		// 'disk_filename_from_now' (sortable)

$headers = apache_request_headers();

// If not debug META infos required!
if (isset($headers['Mailto'])) $mailto = $headers['Mailto']; // optional Auto-Capitals
else if (isset($headers['mailto'])) $mailto = $headers['mailto'];
if (!$dbg && (!isset($mailto) || !filter_var($mailto, FILTER_VALIDATE_EMAIL))) {
	if (!isset($mailto)) exit_error("Header 'mailto' required");
	exit_error("'$mailto': Invalid email format");
}
if (isset($headers['Apikey'])) $api_key = $headers['Apikey']; // optional Auto-Capitals
else if (isset($headers['apikey'])) $api_key = $headers['apikey'];
if (!$dbg && (!isset($api_key) || strcmp($api_key, D_API_KEY))) {
	exit_error("API Key");
}

// Get the posted data
$entityBody = file_get_contents('php://input');
if ($dbg && !strlen($entityBody) && @$_GET['x']) { // Allow simulated Payload for $dbg
	$entityBody = $_GET['x'];
}

if (!strlen($entityBody)) exit_error("JSON entity missing");
$args = json_decode($entityBody, true); //true: Arg. in $args[] as Ass.Array

if (!isset($args['data'])) exit_error("No Payload");

$data = base64_decode($args['data']); // decode Payload, SWARM: max 192 U.8
$maxlen = strlen($data);
if ($maxlen < 1) exit_error("Empty Data");	// 16 is minimum

if (!isset($args['deviceId'])) xdie("No JSON 'deviceId'");

$mac =  'F' . str_pad(strtoupper(dechex($args['deviceId'])), 15, '0', STR_PAD_LEFT);	// SWARM exactly 'F'+15 Zeichen!
if (strlen($mac) != 16) {
	exit_error("MAC Len");
}

if (@file_exists(S_DATA . "/$mac/cmd/dbg.cmd")) {
	if (!$dbg) $dbg = 1;
}
if (check_dirs(false)) exit_error("Error (Directory/MAC not found)"); // May write some SWARM unnecary files

if ($dbg) {	// log all incomming data local and also txt
	$of = fopen(S_DATA . "/$mac/dbg/$dfn.dat", 'wb'); // first save data
	fwrite($of, $data);
	fclose($of);
	$of = fopen(S_DATA . "/$mac/dbg/$dfn.txt", 'w'); // then open txt, Close after parsing input
	fwrite($of, "JSON: '$entityBody'\n");
}
$dpath = S_DATA . "/$mac";				// Device Path

// Read 'device_info' - load infos about this device
$devi = array();
if (file_exists("$dpath/device_info.dat")) {
	$lines = file("$dpath/device_info.dat", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	foreach ($lines as $line) {
		$tmp = explode("\t", $line);
		$devi[$tmp[0]] = $tmp[1];
	}
}

if (file_exists("$dpath/put/iparam.lxp")) { // New Parameters?
	@unlink("$dpath/files/iparam.lxp");
	rename("$dpath/put/iparam.lxp", "$dpath/files/iparam.lxp");
	@unlink($dpath . "/cmd/iparam.lxp.pmeta");	// Kill Put-Meta-infos
	@unlink($dpath . "/put/iparam.lxp");	// and mod.Par
}
$iparam = @file("$dpath/files/iparam.lxp", FILE_IGNORE_NEW_LINES);
$ikanist = 0; // Nix da - flagged iparam
if ($iparam == false) {
	$idxk0 = -1; // Index of K0 (Steps a 14)
} else {
	$idxk0 = array_search("@0", $iparam);
	if ($idxk0 >= 19) { // Trailer mind. 19 lines
		$kanz = count($iparam) - $idxk0;
		if (!($kanz % 14)) $ikanist = $kanz / 14; // exact 14 lines per Chan
	}
}
$ikanused = 1;

$pidx = $args['userApplicationId'];
$xlog = "($maxlen Bytes Data, Packet:$pidx)";
$opidx = intval(@$devi['pidx']); // Index of last packet
if ($pidx != (($opidx + 1) % 65000)) {	// SWARM: 0..64999
	$xlog .= "(INFO: Packet Order:$opidx->$pidx)";
}
$devi['pidx'] = $pidx;


// First of all: quota per UTC-day (in Bytes) only for info
$day = floor($now / 86400); // intdiv >= PHP7
if (@$devi['day'] != $day) { // new day, new quota
	@$devi['total_in'] += @$devi['quota_in'];	// Keep olds...
	@$devi['total_out'] += @$devi['quota_out'];
	$devi['day'] = $day;
	$devi['quota_in'] = $maxlen;
	$devi['quota_out'] = 0;
} else {
	$devi['quota_in'] += $maxlen;
}

// First: Parse Device-Data
$idx = 0;
$devi['now'] = $now;	// Actual Server Time 
$devi['reason'] = 0; 	// Unknown
$devi['typ'] = '900';	// Typ SWARM (intval()=0)
$devi['pasync'] = '1';	// Pakets may be asynchron
@$devi['conns']++;   // Increment No of Connections/Tries
for (;;) {
	if ($idx >= $maxlen) break;
	$cmd = ord($data[$idx]);
	switch ($cmd) {
		case 128:  // 128: Kennung RTN-Block
			$idx++;
			$declines = decode_payloadRTN($data); // Incrementiert auch $idx
			check_iparam(); // Vor add_data
			if ($ikanist > 0) {
				$cookie = $iparam[4];
				if (@$devi['cookie'] !== $cookie) {
					$devi['cookie'] = $cookie;
					array_unshift($declines, "<COOKIE $cookie>");
				}
			}

			if ($dbg) {
				echo "---Decoded Data:---\n";
				foreach ($declines as $z) echo "$z\n";
			}
			$newcont = implode("\n", $declines) . "\n";

			$ftemp = gmdate("Ymd_His", $now) . "_$pidx";
			$of2 = fopen("$dpath/in_new/$ftemp.edt", 'wb'); // Delta as single file
			fputs($of2, $newcont);
			fclose($of2);

			add_data($newcont);	// Save data

			break;
		default: // Error. CMD byte unknown
			$txtdata = ""; // What was sent by $TD
			for ($i = $idx; $i < $maxlen; $i++) { // Make data printable
				$c = ord($data[$i]);
				if ($c < 32 || $c > 127)  $txtdata .= sprintf('\x%02X', $c);
				else $txtdata .= chr($c);
			}
			$xlog .= "(ERROR: Msg.[$idx..]:'$txtdata')";
			if ($dbg) fwrite($of, "??? Msg.[$idx..]:'$txtdata'\n...\n");
			$idx = $maxlen;	// leave
			break;
	}
} // for - uploaded Data now processed
if ($dbg) fclose($of);


// Write 'device_info'/append Meta infos from new file info
$of2 = fopen("$dpath/device_info.dat", 'w');
foreach ($devi as $key => $val) {
	fwrite($of2, "$key\t$val\n");
}
fclose($of2);

$mtrun = round((microtime(true) - $mtmain_t0) * 1000, 4);
$xlog .= "(Run:$mtrun msec)"; // Script Runtime
add_logfile(); // Regular exit, entry in logfile should be first

// trigger action...
if ($dbg) echo "xlog: '$xlog'\n";
trigger($devi['reason']);	// If trigger fails: New entry in logfile
echo "OK";
//***