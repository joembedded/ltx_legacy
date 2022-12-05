<?php
/*************************************************************
 * trigger for LTrax V1.20-LEGACY
 *
 * 05.12.2022 - (C)JoEmbedded.com
 *
 * This is non-database version for a trigger that accepts 
 * all incomming data, but nothing else!
 * Can/will be triggered externally, see docu.
 * By default all incomming data will be simply accumulated to 
 * a file '..$mac/out_total/total.edt'
 ***************************************************************/

error_reporting(E_ALL);

ignore_user_abort(true);
set_time_limit(120); // 2 Min runtime

include("conf/api_key.inc.php");
// include("conf/config.inc.php");	// DB Access Param
include("lxu_loglib.php");

// Filename-sort-callback
function flcmp($a, $b)
{			// Compare Filenames, containing the dates
	if ($a[0] == '.') return 1;	// Points at to the end
	if ($b[0] == '.') return -1;
	$ea = explode('_', $a);
	$eb = explode('_', $b);
	$res = intval($ea[0]) - intval($eb[0]);
	if ($res) return $res;
	$res = intval(@$ea[1]) - intval(@$eb[1]);
	if ($res) return $res;
	$res = intval(@$ea[2]) - intval(@$eb[2]);
	return $res;
}
// ----------------MAIN----------------
$dbg = 0;	// Debug-Level if >0, see docu

header('Content-Type: text/plain');

$api_key = @$_GET['k'];				// max. 41 Chars KEY
$mac = strtoupper(@$_GET['s']); 		// exactly 16 UC Chars. api_key and mac identify device
$reason = @$_GET['r'];				// Opt. Reason (ALARMS) (as in device_info.dat also) *t.b.d* (e.g. timeout or HK-Service-Meta)
// reason&256: SEND Contact
$now = time();						// one timestamp for complete run
$mttr_t0 = microtime(true);           // Benchmark trigger
$xlog = "(Import)";

if (strlen($mac) != 16) {
	if (strlen($mac) > 24) exit();		// URL Attacked?
	exit_error("MAC Len");
}

if (@file_exists(S_DATA . "/$mac/cmd/dbg.cmd")) $dbg = 1; // Allow Individual Debug

// Check Key before loading data
echo "API-KEY: '$api_key' (Exp:" . S_API_KEY . ")\n"; // TEST
if (!$dbg && strcmp($api_key, S_API_KEY)) {
	exit_error("API Key");
}


// --- Now check files ---
$dpath = S_DATA . "/$mac/in_new";		// Device Path (must exist)

$flist = @scandir($dpath, SCANDIR_SORT_NONE);
if (!$flist) {
	exit_error("MAC Unknown");
}
usort($flist, "flcmp");	// Now Compared by Filenames
$cnt = count($flist) - 2;   // Without . and ..
// foreach($flist as $fl) echo "$fl\n"; exit();
$cpath = S_DATA . "/$mac/cmd";		// Path (UPPERCASE recommended, must exist)

$res = 0;	// No Data

if ($dbg) echo "*$cnt Files in '$dpath'*\n";


$line_cnt = 0;

$warn_new = 0;	// See Text for explanation
$err_new = 0;
$alarm_new = 0;
$info_wea = array();

$units = ""; // Units for ALL entries
$lvala = array();	// Last Values as array;

$ign_cnt = 0;
$file_cnt = 0;

$opath = S_DATA . "/$mac/out_total";
if (!file_exists($opath)) {
	mkdir($opath);  // Output Directory - DATA HERE ARE NEVER DELETED
	$xlog = "(Generated Output Directory)";
}

$outfile = fopen($opath . "/total.edt", "a");

// Regard only EDT-Files! 
foreach ($flist as $fname) {
	if (!strcmp($fname, '.') || !strcmp($fname, '..')) continue;
	if (!is_file("$dpath/$fname")) {
		$ign_cnt++;
		//$xlog.="(NOFILE '$fname' ignored)";
		continue;	// ONLY Files
	}
	if (!strpos($fname, '.edt')) { // Ignore other files than EDT
		$ign_cnt++;
		$xlog .= "('$fname' ignored)";

		// echo "ignore '$fname'";		
		if (!$dbg) @unlink("$dpath/$fname");
		continue;	// ONLY Files
	}

	// -- Insert EDT-FILE --

	fputs($outfile, "<File '$fname'>\n");

	$file_cnt++;
	$lines = file("$dpath/$fname", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	$anz = count($lines);
	if ($anz < 1) {
		$warn_new++;	// Warning: EMPTY FILE (Warning not visible)
		$xlog .= "(WARNING: File '$fname' is empty)";
		$info_wea[] = "WARNING: File '$fname' is empty";
		if (!$dbg) @unlink("$dpath/$fname");	// Strange...
		continue;	// ONLY Files
	}

	$unixt = 0; // Start with unix-Time unknown
	foreach ($lines as $line) { // Find 1.st time 
		if ($line[0] != '!') continue;
		$ht = intval(substr($line, 1));
		if ($ht > 1526030617 && $ht < 0xF0000000) {
			$unixt = $ht;
			break;
		}
	}

	foreach ($lines as $line) {
		if ($line[0] == '!') {
			if ($line[1] == 'U') {		// Units follow
				if ($line[2] == ' ') $units = $line;		// Unit-line. Keep!
				else {
					$warn_new++;				// WARNING: Format-ERROR
					if (strlen($xlog) < 128) $xlog .= "(WARNING: '!U'-Format)";
					if (count($info_wea) < 20) $info_wea[] = "WARNING: '!U'-Format";
				}
			} else {					// Line contains VALUES
				$lina = array();	// Create an empty Output Array for Mapping Channels to Units

				$tmp = explode(' ', $line);
				if ($tmp[0][1] == '+') {
					$rtime = intval(substr($tmp[0], 2));
					$unixt += $rtime;	// If <100000 strange!
					$tmp[0] = "!$unixt";	// Replace +Time by real
				} else {
					$unixt = intval(substr($tmp[0], 1));
				}
				if ($unixt < 1526030617 || $unixt >= 0xF0000000) {  // 2097
					$warn_new++;	// Warning: Strange Times
					if (strlen($xlog) < 128) $xlog .= "(WARNING: Unknown Time)";
					if (count($info_wea) < 20) $info_wea[] = "WARNING: Unknown Time";
				}
				// Check Values
				$anz = count($tmp);
				for ($i = 1; $i < $anz; $i++) {
					$ds = explode(':', $tmp[$i]); // As Key/Val

					$key = $ds[0];
					$val = @$ds[1];
					if (!isset($val)) {
						$err_new++;	// ERROR: No Value for Channel  
						if (count($info_wea) < 20) $info_wea[] = "ERROR(T:$unixt): Channel #$key: No Value";
					}
					if (isset($lina[$key])) {	// Can not set twice per line!
						$err_new++;	// ERROR: Channel '$key' already used ('$iv' ignored) in Line
						if (count($info_wea) < 20) $info_wea[] = "ERROR(T:$unixt): Channel #$key: Channel already used";
					} else {
						$lvala[$key] = $val;	// Save last channel
						$lina[$key] = $val;	// Allocate Channel for this line
						if ($val[0] == '*') {	// Line marked as ALARM
							$val = substr($val, 1);
							if (!is_numeric($val)) {
								$err_new++;	// ERROR: Not Numeric Value
								if (count($info_wea) < 20) $info_wea[] = "ERROR(T:$unixt): Channel #$key: '$val'";
							}
							$alarm_new++; 	// Count ALARMS
							if (count($info_wea) < 20) $info_wea[] = "ALARM(T:$unixt): Channel #$key";
						} else if (!is_numeric($val)) {
							$err_new++; // ERROR: Not Numeric Value
							if (count($info_wea) < 20) $info_wea[] = "ERROR(T:$unixt): Channel #$key: '$val'";
						}
					}
				}
				// Recombine exploded line to string with corect time
				$line = implode(' ', $tmp);
			}
		} else {
			if ($line[0] != '<' || $line[strlen($line) - 1] != '>') {	// No valid Meta Line
				$line = "<LINE ERROR '$line'>";
				$err_new++;
				if (count($info_wea) < 20) $info_wea[] = "ERROR: In line $line_cnt";
			} else {
				if (!strncmp($line, "<COOKIE ", 8)) {
					$cookie = intval(substr($line, 8));
					if ($cookie < 1000000000) {
						$info_wea[] = "ERROR: Cookie($cookie)";
						$err_new++;
					}
				} else if (strpos($line, "<RESET") !== false || strpos($line, "ERROR")) {
					$info_wea[] = "WARNING: '" . trim($line, "<>") . "'";
					$warn_new++;
				}
			}
		}
		if ($dbg) echo "$line_cnt: '$line'\n";

		fputs($outfile, $line . "\n");

		$line_cnt++;
	}

	if ($dbg) echo "*File '$fname'\n"; // *** With DBG set: multiple imports in DB ***
	else @unlink("$dpath/$fname");	// Unlinked processed File
}
fclose($outfile);


if ($dbg) {
	echo "Wn:$warn_new An:$alarm_new En:$err_new\n";
	echo "Lines:$line_cnt\n";
}

if ($ign_cnt) $xlog = "($file_cnt Files, $ign_cnt ignored)" . $xlog;
else $xlog = "($file_cnt Files)" . $xlog;

// Save ERROR WARNING ALARM File
if (count($info_wea)) {
	$logpath = S_DATA . "/$mac/";
	if (@filesize($logpath . "info_wea.txt") > 50000) {	// ErrorWarningAlarm Log
		@unlink($logpath . "_info_wea_old.txt");
		rename($logpath . "info_wea.txt", $logpath . "_info_wea_old.txt");
	}
	$log = @fopen($logpath . "info_wea.txt", 'a');
	$nowdate = gmdate("d.m.y H:i:s", $now) . " UTC ";
	foreach ($info_wea as $line) {
		fputs($log, $nowdate . $line . "\n");
	}
	fclose($log);
}

$mtrun = round((microtime(true) - $mttr_t0) * 1000, 4);
$xlog .= "(Run:$mtrun msec)"; // Script Runtime

echo "*TRIGGER(DBG:$dbg) RES:$res ('$xlog')*\n"; // Always
add_logfile();
