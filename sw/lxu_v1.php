<?php
// lxu_v1.php Server-Communication Script for LTrax. Details: see docu
// (C) 05.11.2023 - V1.41 joembedded@gmail.com  - JoEmbedded.de
// Evtl. "schnelle Hilfe": error_reporting (E_ALL & ~E_DEPRECATED);

error_reporting(E_ALL);
include("conf/api_key.inc.php");
include("lxu_loglib.php");

// ----- reads u32 from $data  BE -----   32Bit.u from String
function r4u_data($dix)
{
	global $data;
	return unpack("N", $data, $dix)[1]; // U32
}
// ----- reads u16 from $data  BE -----   16Bit.u from String
function r2u_data($dix)
{
	global $data;
	return unpack("n", $data, $dix)[1]; // U16
}
// ------ u32 to string BE ----
function str_u32($uv32)
{
	return pack("N", $uv32);
}

// ------- Debug function: Show HEX contents of a string -----
function show_str($rem, $str)
{
	$size = strlen($str);
	echo "$rem [$size]:";
	for ($i = 0; $i < $size; $i++) {
		$bval = ord($str[$i]);
		echo '-', dechex($bval);
	}
	echo "\n";
}

// ---------------- Trigger: External async Script lxu_trigger.php -------------------------
function trigger($reason, $vflag)
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
	if ($vflag) $arg .= '&v';	// Enable VPNF-Mode
	// return;	// Ein-kommentieren um Trigger Script NICHT starten wenn ohne DB

	// First check if Trigger is available
	$xlog = "";
	if ($dbg > 1) echo "Start Trigger '$server:$port/$tscript?$arg'\n";
	if ($dbg) $xlog = "(Trigger: '$server:$port/$tscript?$arg')";

	$ch = curl_init("$server:$port/$tscript?$arg");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
	curl_setopt($ch, CURLOPT_TIMEOUT, 1);
	if ($dbg) {
		$res = curl_exec($ch);	// Might be very long!
		$xlog .= "(Curl Result:\nSTART=====>\n$res\n<=====END)";
	} else curl_exec($ch);
	if (curl_errno($ch)) $xlog .= '(CURL:' . curl_error($ch) . ')';
	curl_close($ch);

	if (strlen($xlog)) {
		$xlog = '(call trigger)' . $xlog;
		add_logfile();	// If triger fails: Add ne line to logfile
	}
}

// -------------------------------------- M A I N --------------------------------
header('Content-Type: text/plain');

$dbg = 0; // Debug-Level if >0, see docu

$fname = @$_FILES['X']['tmp_name'];   // all data is contained in file 'X' RAW MODE
$api_key = @$_GET['k'];				// max. 41 Chars KEY
$mac = @$_GET['s']; 				// 16 Zeichen. api_key and mac identify device
$vpnf = @$_GET['v']; 				// If set direct formward
$now = time();						// one timestamp for complete run
$mtmain_t0 = microtime(true);         // for Benchmark 
$dfn = gmdate("Ymd_His", $now);		// 'disk_filename_from_now' (sortable)

$send_cmd = -1;						// If set (0-255) send as Flags-cmd
if (!isset($mac) || strlen($mac) != 16) {
	exit_error("MAC Len");
}

if (@file_exists(S_DATA . "/$mac/cmd/dbg.cmd")) {
	if (!$dbg) $dbg = 1;
}

// Check Key before loading data
if (!$dbg && (!isset($api_key) || strcmp($api_key, D_API_KEY))) {
	exit_error("API Key");
}

$xlog = "(lxu_v1)";
if (empty($fname)) {
	exit_error("No Data");
}

if (check_dirs()) exit_error("Error (Directory/MAC not found)");

$data = file_get_contents($fname);

$maxlen = strlen($data);
if ($maxlen < 16) exit_error("Empty Data");	// 16 is minimum
if ($dbg) {	// log all incomming data local and also txt
	$of = fopen(S_DATA . "/$mac/dbg/$dfn.dat", 'wb'); // first save data
	fwrite($of, $data);
	fclose($of);
	$of = fopen(S_DATA . "/$mac/dbg/$dfn.txt", 'w'); // then open txt
}

$xlog .= "($maxlen Bytes Data)";

$dpath = S_DATA . "/$mac";				// Device Path
$stage = -1;	// Assume Stage no stage for this communication
$expmore = 0; // Asume no reply
$extratxt = "";	// Added ASCII (Quectel-Cache-Prob)

$idx = 0;		// Index in data
$ecmd = "";	// echo-command
// $etext = "OK"; // later

// load infos about this device
$devi = array();
if (file_exists("$dpath/device_info.dat")) {
	$lines = file("$dpath/device_info.dat", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	foreach ($lines as $line) {
		$tmp = explode("\t", $line);
		$devi[$tmp[0]] = $tmp[1];
	}
}

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
for (;;) {
	if ($idx >= $maxlen) break;
	$cmd = ord($data[$idx]);
	if ($cmd == 0xFF) break;
	$len = r4u_data($idx + 1);
	$cmdblock = substr($data, $idx, $len + 5);	// without CRC32
	// show_str('CMDBLOCK: ',$cmdblock);	// One block
	$idx += 5;
	$bp0 = $idx;	// Pos 0 of Datablock
	$idx += $len;
	$sollcrc = substr($data, $idx, 4);	// Check CRC32
	$istcrc = str_u32((~crc32($cmdblock)) & 0xFFFFFFFF);
	$idx += 4;	// points to next block
	// echo "CMD: $cmd LEN: $len \n";
	if (strcmp($sollcrc, $istcrc)) {
		$xlog .= "(CRC Error Pos. $bp0)";
		$etext = "ERROR: CRC";
		break;
	}
	$devi['now'] = $now;	// Actual Server Time 
	switch ($cmd) {
		case 0xA0: // CHEAD - always sent
			$stage = ord($data[$bp0]);
			$dtime = r4u_data($bp0 + 1); // Devicetime
			$sdelta = $now - $dtime; // normally slightly positive
			if (!$stage) $devi['sdelta'] = $sdelta;	// Delta best for stage0
			$last_result = r2u_data($bp0 + 5);
			if ($last_result > 32767) $last_result -= 65536; // to int.16
			if ($stage == 0) {
				@$devi['conns']++;   // Increment No of Connections/Tries
				if (@$devi['expmore'] > 0) {
					$xlog .= "(WARNING: Last Transfer incomplete)";
				}
			}
			$conid = @$devi['conns']; // Connection ID (if available)
			$xlog .= "(Id:$conid, Stage:$stage, dUTC:$sdelta sec)";
			if ($last_result && !$stage) $xlog .= "(LastResult:$last_result)"; // Rec. only  once
			$devi['stage'] = $stage;	// Communicatio stage
			$devi['dtime'] = $dtime;	// Device Time UTC
			if ($dbg) fwrite($of, "A0: HEAD Stage:$stage, TIME:$dtime, LastResult:$last_result\n");
			break;
		case 0xA1: // CHELLO - sent only in Stage 0
			$typ = r2u_data($bp0);
			$fw = r2u_data($bp0 + 2);
			$cookie = r4u_data($bp0 + 4);
			$reason = ord($data[$bp0 + 8]);	// Reason for this connection
			$devi['con_id'] = $now;	// Identifies Connection
			$devi['typ'] = $typ;		// Device Type
			$devi['fw_ver'] = $fw;	// Firmware Version
			$devi['fw_cookie'] = $cookie;	// Firmware Cookie (Identifies BIN, sec since 1.1970)
			$devi['reason'] = $reason;	// Why? 

			switch ($reason & 15) { //
				case 2:
					$rea_str = "(AUTO";
					break;
				case 3:
					$rea_str = "(MANUAL";
					break;
				case 4:
					$rea_str = "(START";
					break;
				default:
					$rea_str = "(UNKNOWN(reason=$reason)";
					break;	// Alarm e.g. t.b.d
			}
			if ($reason & 128) $rea_str .= ",RESET";
			if ($reason & 64) $rea_str .= ",ALARM";
			else if ($reason & 32) $rea_str .= "old ALARM";
			$rea_str .= ")";
			$xlog .= $rea_str;

			if ($dbg) {
				$cookie_str = gmdate("d.m.Y H:i:s", $cookie); // UTC-String
				fwrite($of, "A1: HELLO Typ:$typ, FW:$fw COOKIE:$cookie_str Reason:$reason\n");
			}
			break;
		case 0xA2: // CDISKINFO - automatically sent in Stage 0 for Sync-Files or on user request
			$dmode = ord($data[$bp0]);
			$dsize = r4u_data($bp0 + 1) / 1024;
			$davail = r4u_data($bp0 + 5) / 1024;
			$ddate = r4u_data($bp0 + 9);
			if ($dmode == 255) {	// Clear old VMETA if complete directory scan
				$getlist = scandir("$dpath/cmd", SCANDIR_SORT_NONE); // Contains at least . and ..
				foreach ($getlist as $getfn) {
					if (!strcmp($getfn, '.') || !strcmp($getfn, '..')) continue;
					$pos = strrpos($getfn, '.');
					if (strcmp(substr($getfn, $pos), ".vmeta")) continue;	// only interested in meta
					$fname = substr($getfn, 0, $pos);
					unlink("$dpath/cmd/$getfn");	// Clear old vdir data
				}
				@unlink("$dpath/cmd/getdir.cmd"); // remove Request if any
				$devi['dirtime'] = $now;		// Save last Directory time
			}
			$devi['dmode'] = $dmode;	// If Flag set o ALL-> Clear getdir.cmd
			$devi['dsize'] = $dsize;	// Disk Size in KB 
			$devi['davail'] = $davail;	// Available Size in KB
			$devi['ddate'] = $ddate;	// Disk format date
			if ($dbg) fwrite($of, "A2: DISK M:$dmode, Size_kb:$dsize, Avail_kb:$davail, Formated:$ddate\n");
			break;
		case 0xA3: // CDIRENTRY - sent for each File, after CDISKINFO
			//OFLAGS.1 FLEN.4 FILE_CRC32.4 DATE.4  LEN.1 NAME.LEN
			$fflags = ord($data[$bp0]);
			$flen = r4u_data($bp0 + 1);
			$fcrc = r4u_data($bp0 + 5);
			$fdate = r4u_data($bp0 + 9);
			$fnlen = ord($data[$bp0 + 13]);
			$fname = substr($data, $bp0 + 14, $fnlen); // fname on Device
			if (!strcasecmp(substr($fname, strrpos($fname, '.')), ".php")) $fname .= '_';

			if ($dbg) fwrite($of, "A3: FILE:$fname, Len:$flen, Date:$fdate, F:$fflags CRC:" . dechex($fcrc) . "\n");

			// Save to VDISK-Data
			$of2 = fopen("$dpath/cmd/$fname.vmeta", 'w');
			fwrite($of2, "vd_flags\t$fflags\n");
			fwrite($of2, "vd_len\t$flen\n");
			fwrite($of2, "vd_crc\t$fcrc\n");
			fwrite($of2, "vd_date\t$fdate\n");
			fwrite($of2, "vd_dir\t$now\n");
			fclose($of2);

			// check what to do
			if ($fflags & 64) {	// SYNC-Flag for this file?
				// Read existing Meta infos in array
				$fostat = array();
				if (file_exists("$dpath/cmd/$fname.fmeta")) {
					$lines = file("$dpath/cmd/$fname.fmeta", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
					foreach ($lines as $line) {
						$tmp = explode("\t", $line);
						$fostat[$tmp[0]] = $tmp[1];
					}
				}

				$la = 0;	// 2 Positions
				$le = $flen;
				if (!isset($fostat['date']) || $fdate != $fostat['date'] || $flen < $fostat['len']) { // CRC not helpful..
					if ($dbg) fwrite($of, "- New File\n"); // Take all!
				} else {
					$la = $fostat['len'];
					$ln = $le - $la;
					if ($dbg) fwrite($of, "- Already uploaded to Pos. $la, $ln Bytes new\n");
				}
				// Decide how much to uploade, $act is known at this stage

				if ($le > $la) {	// New Data?
					if(@$act==5) $maxmem = MAXM_NB;
					else $maxmem = MAXM_2GM;
					if ($le - $la > $maxmem) {
						$la = $le - $maxmem;
						$xlog .= "(WARNING: File:'$fname' Sizelimit $maxmem )";
					}
					// Get the file or parts
					// POS0.4 ANZ.4 FLEN.1 NAME.FLEN
					$payload = str_u32($la) . str_u32($le - $la) . chr(strlen($fname)) . $fname;
					$tecmd = "\xC0" . str_u32(strlen($payload)) . $payload; // Blocklen is 5+datalen+4_crclen $C0: CFILESEND
					$ecmd .= $tecmd . str_u32((~crc32($tecmd)) & 0xFFFFFFFF); // Append CRC
					$expmore = 1;	// If set: expect more!
				} else {
					if ($dbg) fwrite($of, "- File OK\n"); // Nothing to do..
				}
				// No fmeta writeback!
			}
			break;

		case 0xA4: // CFILE_DATA - Contains requested (or unsolicited also possible) Data
			// POS0.4 TIME0.4 OFLAGS.1 LEN.1 NAME.LEN  DATA.(LEN-6-NAME_LEN) 
			$fpos0 = r4u_data($bp0);
			$fdate = r4u_data($bp0 + 4);
			$fflags = ord($data[$bp0 + 8]);
			$fnlen = ord($data[$bp0 + 9]);
			$fname = substr($data, $bp0 + 10, $fnlen);	// extract name
			if (!strcasecmp(substr($fname, strrpos($fname, '.')), ".php")) $fname .= '_'; // 

			$flen = $len - $fnlen - 10; // Only len of this block!
			if ($dbg) fwrite($of, "A4: FILE $fname Pos0:$fpos0, Len:$flen, Date:$fdate, F:$fflags (uploaded)\n");

			// Read existing Meta infos in array
			$fostat = array();
			if (file_exists("$dpath/cmd/$fname.fmeta")) {
				$lines = file("$dpath/cmd/$fname.fmeta", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
				foreach ($lines as $line) {
					$tmp = explode("\t", $line);
					$fostat[$tmp[0]] = $tmp[1];
				}
			} // No data for new files
			$wmode = 1;	// Assume new file (mode 'wb')
			if (!file_exists("$dpath/get/$fname")) { // for Automatic files Add data
				// Check if we got, what we ordered (only for Auty-Sync-Files)
				if (!empty($fostat['date']) && $fostat['date'] == $fdate && $fostat['len'] > 0) {
					// date is equal and file exists and contains already data
					$fgap = $fpos0 - $fostat['len'];
					if ($fgap > 0) {	// >0 possible, <0: Double sended?
						$xlog .= "(WARNING: '$fname': Inter-File GAP:$fgap Bytes)";
					} else if ($fgap < 0) {
						$fgap = -$fgap;
						if ($fgap <= $flen) {
							// If this upload was requested: Clear request-file
							$xlog .= "(WARNING: '$fname': Duplicate $fgap Bytes ignored)";
							if (!$dbg) break; // Ignore this block for Auto-Files 
						} else {
							$xlog .= "(WARNING: '$fname': Overlapping Duplicate $fgap Bytes)";
						}
					} else {
						$xlog .= "('$fname': $flen Bytes new)";
						$wmode = 0; 	// Existing File and Pos OK: 'ab'; Append
					}
				} else {
					if ($fpos0) {
						$xlog .= "(WARNING: '$fname': GAP:$fpos0 Bytes at Start of File)";
					}
				}
			}

			if ($wmode) {	 // Write NEW
				@unlink("$dpath/files/$fname.bak");	// Keep the last Backup as "bak"
				@rename("$dpath/files/$fname", "$dpath/files/$fname.bak"); // O N
				$xlog .= "(New '$fname': $flen Bytes new)";
				$fostat['pos0'] = $fpos0;	// Add (opt.) Offset of first Byte
				$of2 = fopen("$dpath/files/$fname", 'wb'); // Full File
			} else {	// Append to existing file
				$of2 = fopen("$dpath/files/$fname", 'ab'); // Else append
			}
			$newdata = substr($data, $bp0 + 10 + $fnlen, $flen);
			fputs($of2, $newdata);
			fclose($of2);

			// If this upload was requested: Clear request-file
			@unlink("$dpath/get/$fname");

			// Store new infos about this file in Meta
			$fostat['flags'] = $fflags;
			$fostat['len'] = $fpos0 + $flen;	// As on Disk
			$fostat['date'] = $fdate;

			$of2 = fopen("$dpath/cmd/$fname.fmeta", 'w');
			foreach ($fostat as $key => $val) {
				fwrite($of2, "$key\t$val\n");
			}
			fclose($of2);

			// Store also the fragment in in_new
			// Temp_filename is preceeded by filedate plus offset
			$ftemp = gmdate("Ymd_His", $fdate) . "_$fpos0" . '_';
			$nsfname = $ftemp . $fname;
			$of2 = fopen("$dpath/in_new/$nsfname", 'wb'); // Delta as single file
			fputs($of2, $newdata);
			fclose($of2);
			break;

		case 0xA5: 	// CSIGNAL_SG3G - Cell IDs
			// MCC.2 NET.2 LAC.2 CID.4 TA.8 MDBM.8
			$mcc = r2u_data($bp0);
			$net = r2u_data($bp0 + 2);
			$lac = r2u_data($bp0 + 4);
			$cid = r4u_data($bp0 + 6);
			$ta = ord($data[$bp0 + 10]);
			$dbm = -ord($data[$bp0 + 11]);
			$act = ($len>12)?ord($data[$bp0 + 12]):0;
			$devi['signal'] = "mcc:$mcc net:$net lac:$lac cid:$cid ta:$ta dbm:$dbm act:$act";
			if ($dbg) fwrite($of, "A5: Net: $mcc-$net-$lac-$cid T:$ta $dbm dbm\n");
			$of2 = fopen("$dpath/conn_log.txt", 'a'); // Connection log
			fputs($of2, gmdate("d.m.y H:i:s", $now) . ' UTC ' . $devi['signal'] . "\n");
			fclose($of2);
			break;
		case 0xA6: 	// User_info (Annahme: TEXT-String)
			$user_content = addcslashes(substr($data, $bp0, $len), "\n\r<>&"); // 
			if ($dbg) fwrite($of, "A6: User '$user_content'($len)\n");
			$of2 = fopen("$dpath/user_contents.txt", 'a'); // List of User Data
			fputs($of2, gmdate("d.m.y H:i:s ", $now) . "(Stage:$stage) '$user_content'\n");
			fclose($of2);
			$devi['lut_cont'] = $user_content;
			$devi['lut_date'] = $now;
			file_put_contents("$dpath/userio.txt", gmdate("d.m.y H:i:s", $now) . " UTC Reply: '$user_content'\n", FILE_APPEND);
			file_put_contents("$dpath/in_new/" . gmdate("Ymd_His", $now) . "_userio.edt", "<UCMD:$user_content>"); // edt: DirectWay...
			break;
		case 0xA7: 	// ICCID (String)
			$imsi = trim(substr($data, $bp0, $len));
			if ($dbg) fwrite($of, "A7: IMSI '$imsi'\n");
			$devi['imsi'] = $imsi;
			break;

		default: // Error. Block OK, but CMD unknown
			$xlog .= "(ERROR: CMD:$cmd len:$len)";
			if ($dbg) fwrite($of, "??? CMD:$cmd len:$len\n...\n");
			$etext = "ERROR: Unknown CMD($cmd)";
			break;
	}
} // for - uploaded Data now processed
if ($dbg) fclose($of);

if (file_exists("$dpath/cmd/server.cmd")) {	// Server CMD - More important than everything!
	if ($stage > 0) {	// Bereits gesendet!
		unlink("$dpath/cmd/server.cmd");
		$xlog .= "(Server Cmd confirmed)";
	} else {
		$cmdstr = file_get_contents("$dpath/cmd/server.cmd"); // telomeric requesting
		$clen = strlen($cmdstr);
		if ($clen > 4) $clen = 4;	// Limit to 4 tries
		if ($clen == 0) { // Game over
			unlink("$dpath/cmd/server.cmd");
			$xlog .= "(WARNING: Server Cmd failed)";
		} else {
			$of = fopen("$dpath/cmd/server.cmd", 'wb');
			fwrite($of, substr($cmdstr, 0, $clen - 1));
			fclose($of);
			$send_cmd = ord($cmdstr[0]);
			$expmore = 1;	// If set: expect more!
			$xlog .= "(Server Cmd '$send_cmd' sent($clen))";
		}
	}
}

if ($send_cmd <= 0 && file_exists("$dpath/cmd/_firmware.sec.umeta")) { // Check if FIRMWARE is present - Then next
	$fwinfo = array();
	$lines = file("$dpath/cmd/_firmware.sec.umeta", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	foreach ($lines as $line) {
		$tmp = explode("\t", $line);
		$fwinfo[$tmp[0]] = $tmp[1];
	}
	if (@$fwinfo['check'] < 1) {	// New Firmware
		if ($stage == 0) {	// Stage0: Send firmware
			$fwinfo['sent']++;	// Save Sent No.
			if ($fwinfo['sent'] > 4) {
				$xlog .= "(ERROR: Firmware Update failed! (4 tries))";	// Bad Net? 
				@unlink("$dpath/cmd/_firmware.sec.umeta");
				@unlink("$dpath/cmd/_firmware.sec.vmeta");
				@unlink("$dpath/cmd/_firmware.sec");
			} else if (@$fwinfo['check'] > 0) {
				$xlog .= "(ERROR: Firmware already confirmed! Not Re-Sent!)"; // Local Update by User? Keep Msg.
				@unlink("$dpath/cmd/_firmware.sec.umeta");
				@unlink("$dpath/cmd/_firmware.sec.vmeta");
				@unlink("$dpath/cmd/_firmware.sec");
			} else {
				// Send Firmware file
				$fname = "_firmware.sec";	// Target file name     
				$payload = chr(6) . chr(strlen($fname)) . $fname . file_get_contents("$dpath/cmd/_firmware.sec");	// 6 WRITE|CREATE (CRC not required for FW)
				$tecmd = "\xC1" . str_u32(strlen($payload)) . $payload; // Blocklen ist 5+datalen+4_crclen $C1: Download
				$ecmd .= $tecmd . str_u32((~crc32($tecmd)) & 0xFFFFFFFF); // Append CRC

				$xlog .= "(New Firmware sent(" . $fwinfo['sent'] . "))"; // Cnt
				$expmore = 1;		// Reply once after transfer
				$send_cmd = 128;	// With RESET afterwards in first round
				$extratxt = str_repeat("Stuff", 1000); // 5k Dummy wg. Quectel-Problem

				$of2 = fopen("$dpath/cmd/_firmware.sec.umeta", 'w');
				foreach ($fwinfo as $key => $val) {
					fwrite($of2, "$key\t$val\n");
				}
				fclose($of2);
			}
		} else if ($fwinfo['sent'] <= 4 && @$fwinfo['check'] < 1) { // Has still the old firmware, but stage>0
			$xlog .= "(Firmware File: Transfer OK)";	// Now we can do the rest
			$fwinfo['check'] = $now;		// Confirm Transfer
			$of2 = fopen("$dpath/cmd/_firmware.sec.umeta", 'w');
			foreach ($fwinfo as $key => $val) {
				fwrite($of2, "$key\t$val\n");
			}
			fclose($of2);
		}
	}
}

// else(No Firmware transfer required): Check other commands (with send_cmd: nothing extra)!
if ($send_cmd <= 0) {
	// todo: --- correct access to commands!
	// --- Problems: examples: - getdir followed by del in one stage will fail, because notepad() on device is filled first
	//   - get and put in one stage might fail, because meta-infos might be incomplete ...
	if (file_exists("$dpath/cmd/getdir.cmd")) { // Dir highes priority as command, sets expmore
		$dirstr = file_get_contents("$dpath/cmd/getdir.cmd"); // telomeric requesting
		$dslen = strlen($dirstr);
		if ($dslen > 4) $dslen = 4;	// Limit to 4 tries
		if ($dslen == 0) { // Game over
			unlink("$dpath/cmd/getdir.cmd");
			$xlog .= "(WARNING: cmd 'Dir' failed)";
		} else {
			$of = fopen("$dpath/cmd/getdir.cmd", 'wb');
			fwrite($of, substr($dirstr, 0, $dslen - 1));
			fclose($of);

			$payload = chr(255);	// 1 Byte command, 255: scan ALL
			$tecmd = "\xC4" . str_u32(strlen($payload)) . $payload; // Same proc..
			$ecmd .= $tecmd . str_u32((~crc32($tecmd)) & 0xFFFFFFFF); // Append CRC
			$expmore = 1;	// If set: expect more!
			$xlog .= "(cmd($dslen): Get Dir)";
		}
	} else {
		// GET Files. Requesting nonexisten files stops tranmission, wrong sizes gets false files on V1.0
		$getlist = scandir("$dpath/get", SCANDIR_SORT_NONE); // Contains at least . and ..
		foreach ($getlist as $getfn) {
			if (!strcmp($getfn, '.') || !strcmp($getfn, '..')) continue;
			// File found, get META-Data
			$fgeta = array();
			$getstr = file_get_contents("$dpath/get/$getfn"); // telomeric requesting
			$gslen = strlen($getstr);
			if ($gslen > 4) $gslen = 4;	// Limit to 4 tries
			else if ($gslen == 0) { // Game over
				unlink("$dpath/get/$getfn");
				$xlog .= "(WARNING: File '$getfn' not found (get))";
				continue;
			}
			$of = fopen("$dpath/get/$getfn", 'wb');
			fwrite($of, substr($getstr, 0, $gslen - 1));
			fclose($of);

			$lines = @file("$dpath/cmd/$getfn.vmeta", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			if ($lines != false) {
				foreach ($lines as $line) {
					$tmp = explode("\t", $line);
					$fgeta[$tmp[0]] = $tmp[1];
				}
				$xlog .= "(get($gslen): '$getfn')";
				// Get the file or parts
				// POS0.4 ANZ.4 FLEN.1 NAME.FLEN
				$payload = str_u32(0) . str_u32($fgeta['vd_len']) . chr(strlen($getfn)) . $getfn;
				$tecmd = "\xC0" . str_u32(strlen($payload)) . $payload; // Blocklen is 5+datalen+4_crclen $C0: CFILESEND
				$ecmd .= $tecmd . str_u32((~crc32($tecmd)) & 0xFFFFFFFF); // Append CRC
				$expmore = 1;	// If set: expect more!

			} else {
				$xlog .= "(ERROR: get: '$getfn' no metadata)"; // possibly deleted file
				unlink("$dpath/get/$getfn");	// Ignored
			}
		}
	}

	// If everything is transferred, check if there is something to delete
	if (!$expmore) {
		// Something to delete?
		$dellist = scandir("$dpath/del", SCANDIR_SORT_NONE); // Contains at least . and ..
		foreach ($dellist as $delfn) {
			if (!strcmp($delfn, '.') || !strcmp($delfn, '..')) continue;

			$lines = @file("$dpath/cmd/$delfn.dmeta", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			if ($lines != false) {
				$fdela = array();
				foreach ($lines as $line) {
					$tmp = explode("\t", $line);
					$fdela[$tmp[0]] = $tmp[1];
				}
				if (($fdela['now'] == $devi['con_id']) && ($fdela['stage'] + 1 == $stage)) {
					unlink("$dpath/del/$delfn");
					unlink("$dpath/cmd/$delfn.dmeta");
					unlink("$dpath/cmd/$delfn.vmeta");
					$xlog .= "(Del File '$delfn' confirmed)";
					continue;
				}
			}

			$delstr = file_get_contents("$dpath/del/$delfn"); // telomeric requesting
			$dslen = strlen($delstr);
			if ($dslen > 4) $dslen = 4;	// Limit to 4 tries
			else if ($dslen == 0) { // Game over
				unlink("$dpath/del/$delfn");
				unlink("$dpath/cmd/$delfn.dmeta"); // Delete-Meta

				$xlog .= "(WARNING: Del File '$delfn' failed)";
				continue;
			}

			$of = fopen("$dpath/del/$delfn", 'wb'); // Write Telomerfile
			fwrite($of, substr($delstr, 0, $dslen - 1));
			fclose($of);

			$xlog .= "(del($dslen): '$delfn')";

			$of = fopen("$dpath/cmd/$delfn.dmeta", 'w'); // Delete-Meta
			fwrite($of, "now\t" . $devi['con_id'] . "\n"); // Identifies Connection.
			fwrite($of, "stage\t$stage\n");
			fclose($of);

			$payload = chr(strlen($delfn)) . $delfn;
			$tecmd = "\xC5" . str_u32(strlen($payload)) . $payload; // Blocklen is 5+datalen+4_crclen $C0: CFILESEND
			$ecmd .= $tecmd . str_u32((~crc32($tecmd)) & 0xFFFFFFFF); // Append CRC
			$expmore = 1;	// If set: expect more! Not necessary if unconfirmed

		}
	} // Delete

	// If everything is transferred, check if there is something to put
	if (!$expmore) {
		// Something to put? (same step as put is OK)
		$putlist = scandir("$dpath/put", SCANDIR_SORT_NONE); // Contains at least . and ..
		foreach ($putlist as $putfn) {
			if (!strcmp($putfn, '.') || !strcmp($putfn, '..')) continue;
			// File found, get META-Data
			$lines = @file("$dpath/cmd/$putfn.pmeta", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			if ($lines != false) {
				$fputa = array();
				foreach ($lines as $line) {
					$tmp = explode("\t", $line);
					$fputa[$tmp[0]] = $tmp[1];
				}
				$fputa['sent']++;	// Save Sent No.

				if ($fputa['sent'] > 1) {
					// Sende-Stage und Time merkene und nur wenn stage+1 = OK entfernen
					if (($fputa['now'] == $devi['con_id']) && ($fputa['stage'] + 1 == $stage)) {
						$xlog .= "(Put File '$putfn' confirmed)";
						// Copy File to files
						@unlink("$dpath/files/$putfn");
						@rename("$dpath/put/$putfn", "$dpath/files/$putfn"); // Move File to Files

						$flen = filesize("$dpath/files/$putfn");
						$of2 = fopen("$dpath/cmd/$putfn.fmeta", 'w');	// Generate F-Meta Infos
						fwrite($of2, "pos0\t0\n");
						fwrite($of2, "flags\t256\n");	// NewFlag
						fwrite($of2, "len\t$flen\n");
						fwrite($of2, "date\t$now\n");
						fclose($of2);
						$of2 = fopen("$dpath/cmd/$putfn.vmeta", 'w');	// Generate V-Meta Infos
						fwrite($of2, "vd_flags\t256\n");	// NewFlag
						fwrite($of2, "vd_len\t$flen\n");
						fwrite($of2, "vd_date\t$now\n");	// Take today first...
						fwrite($of2, "vd_dir\t" . @$devi['dirtime'] . "\n");	// Theoretically NEW
						fclose($of2);
						continue;
					}
					if ($fputa['sent'] > 4) {
						unlink("$dpath/put/$putfn");
						unlink("$dpath/cmd/$putfn.pmeta");
						$xlog .= "(ERROR: Put File '$putfn' failed)";
						continue;
					}
				}
				// Write PUT Data file with CRC32
				$pflags = 22;	// 22 WRITE|CREATE|CRC - Standard
				if (!strcmp($putfn, "iparam.lxp")) $pflags |= 64; // Exception: 'iparam.lxp' always synced!
				$payload = chr($pflags) . chr(strlen($putfn)) . $putfn . file_get_contents("$dpath/put/$putfn");
				$tecmd = "\xC1" . str_u32(strlen($payload)) . $payload; // Blocklen ist 5+datalen+4_crclen $C1: Download
				$ecmd .= $tecmd . str_u32((~crc32($tecmd)) & 0xFFFFFFFF); // Append CRC

				$xlog .= "(File '$putfn' sent(" . $fputa['sent'] . "))"; // Cnt
				$expmore = 1;		// Reply once after transfer to signal OK

				$fputa['now'] = $devi['con_id'];	// Identifies Connection
				$fputa['stage'] = $stage;

				$of2 = fopen("$dpath/cmd/$putfn.pmeta", 'w');
				foreach ($fputa as $key => $val) {
					fwrite($of2, "$key\t$val\n");
				}
				fclose($of2);
			} else {
				$xlog .= "(ERROR: put: '$putfn' no metadata)"; // possibly deleted file
				unlink("$dpath/put/$putfn");	// Ignored
			}
		} // Put
	}

	/* Check if there is a user-command */
	if (file_exists("$dpath/cmd/usercmd.cmd")) { // Dir highes priority as command, sets expmore
		$ucmd = file_get_contents("$dpath/cmd/usercmd.cmd");
		$cinfo = array();
		$lines = @file("$dpath/cmd/usercmd.cmeta", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if ($lines != false) {
			foreach ($lines as $line) {
				$tmp = explode("\t", $line);
				$cinfo[$tmp[0]] = $tmp[1];
			}
		}
		@$cinfo['sent']++;
		for (;;) { // loop only for flow
			if ($cinfo['sent'] > 1) {
				$cres = "";
				if (($cinfo['now'] == $devi['con_id']) && ($cinfo['stage'] + 1 == $stage)) {
					$xlog .= "(User Command '$ucmd' confirmed)";
					@unlink("$dpath/cmd/usercmd.cmd");
					@unlink("$dpath/cmd/usercmd.cmeta");
					$cres = "Confirmed";
				} else if ($cinfo['sent'] > 4) {
					$xlog .= "(ERROR: User Command '$ucmd' send failed)";
					$cres = "Send failed";
				}
				if (strlen($cres)) {
					@unlink("$dpath/cmd/usercmd.cmd");
					@unlink("$dpath/cmd/usercmd.cmeta");
					$devi['luc_state'] = $cres;
					file_put_contents("$dpath/userio.txt", gmdate("d.m.y H:i:s", $now) . " UTC $cres: '$ucmd' \n", FILE_APPEND);
					break;
				}
			}
			// Send unser command
			$payload = $ucmd;
			$tecmd = "\xC6" . str_u32(strlen($payload)) . $payload; // Blocklen ist 5+datalen+4_crclen $C6: Cmd
			$ecmd .= $tecmd . str_u32((~crc32($tecmd)) & 0xFFFFFFFF); // Append CRC
			$xlog .= "(User Command '$ucmd' sent(" . $cinfo['sent'] . "))";
			$expmore = 1;		// Reply once after transfer to signal OK

			$devi['luc_cmd'] = $ucmd;	// The command
			$devi['luc_state'] = 'Sent(' . $cinfo['sent'] . ')'; // State
			$devi['luc_date'] = $now;	// Time of sending

			$cinfo['now'] = $devi['con_id'];	// Identifies Connection
			$cinfo['stage'] = $stage;

			$of2 = fopen("$dpath/cmd/usercmd.cmeta", 'w');
			foreach ($cinfo as $key => $val) {
				fwrite($of2, "$key\t$val\n");
			}
			fclose($of2);
			break;
		} // for(;;)
	} // Usercmd
}

// Start Output with a last TEXT\n\n
if (isset($vpnf) && !isset($etext)) {
	$etext = substr(str_replace("\n", " ", @file_get_contents("$dpath/cmd/okreply.cmd")), 0, 40); // NL etc..
} else $etext = "OK"; // Default
echo "$etext(Id:$conid)\n\n"; // Default: OK

if (!$expmore && strlen($ecmd) < 50) {	// If not: send at least curent server time
	$payload = chr(0) . str_u32(time());	// 5 Bytes (Flag 0 ignored)
	$tecmd = "\xC2" . str_u32(strlen($payload)) . $payload; // 	CSERVERTIME
	$ecmd .= $tecmd . str_u32((~crc32($tecmd)) & 0xFFFFFFFF); // Append CRC
}

if ($send_cmd >= 0) { // preset to -1
	$payload = chr($send_cmd);	// 1 Byte command
	$tecmd = "\xC3" . str_u32(strlen($payload)) . $payload; // Same proc..
	$ecmd .= $tecmd . str_u32((~crc32($tecmd)) & 0xFFFFFFFF); // Append CRC
}

if ($expmore) $recmd = "\xFE:ServerRepeat**"; 	// Repeat
else $recmd = "\xFF:ServerDone****"; // OK
$ecmd .= $extratxt;
$elen = strlen($ecmd);
$aesgap = 16 - ($elen & 15); // Add 1..16 Bytes (but at least 1)
$ecmd .= substr($recmd, 0, $aesgap); // prepare pption for HTTP-AES
$elen += $aesgap;

$xlog .= "($elen Bytes Reply)";
$devi['quota_out'] += $elen; // Save Quota out
echo $ecmd;

if ($dbg) {
	if ($dbg > 1) show_str("ECMD: ", $ecmd);
	$of2 = fopen(S_DATA . "/$mac/dbg/$dfn.ecmd", 'wb'); // save response
	fwrite($of2, $ecmd);
	fclose($of2);
}

// Gen. some meta info
if (!$expmore) @$devi['trans']++;   // Increment No of (complete) tranmissions
$devi['expmore'] = $expmore;  // set until complete  

// Write/append Meta infos from new file info
$of2 = fopen("$dpath/device_info.dat", 'w');
foreach ($devi as $key => $val) {
	fwrite($of2, "$key\t$val\n");
}
fclose($of2);

$mtrun = round((microtime(true) - $mtmain_t0) * 1000, 4);
$xlog .= "(Run:$mtrun msec)"; // Script Runtime
add_logfile(); // Regular exit, entry in logfile should be first

if (!$expmore) {	// Finished! Start async trigger
	trigger($devi['reason'], isset($vpnf));	// If trigger fails: New entry in logfile
}
