<?PHP
/* -------------------------------------------------------------------
    * edt_view - Convert FILE.edt -> CSV
	* EDT ("EasyDaTa") is a very flexible and easy-2-use file format to 
	* store logged data. Read the docu!
	*
    * Version: V1.11 - 27.11.2022
	* (C) JoEmbedded.de
    * ---------------------------------------------------------------------- */

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
/*
	if(!$dev) {
		echo "ERROR: Access denied!";
		exit();
	}
*/

// ---------------------------- M A I N --------------
$err_cnt = 0;
$warn_cnt = 0;
$xerr = "";	// After-Line Error Text
$dbg = 0;
$mtmain_t0 = microtime(true);         // for Benchmark 

$mac = @$_GET["s"];
if (strlen($mac) != 16) {
	if (strlen($mac) > 24) exit();		// URL Attacked?
	exit_error("MAC Len");
}
$fname = @$_GET["f"];
$utc_offset = @$_GET["utc"]; // Or 0

// some option bits: 
// 1: No Alarms ('*', but last <ALARM> Token, if found)
// 2: Remove unimportant Tokens (but not <NT ..>)
// 4: Remove also <NT (requires opt 2 too)
// 8: No Numbering (Count Lines)
// 16: Deutsches Float-Format
// 64: Unix-Timestamp Datenformat
// 128: DOWNLOAD CSV

define("NO_ALARMS", 1);
define("NO_UNIMP_TOK", 2);
define("NO_NT", 4);
define("NO_LINECNT", 8);

define("GERMAN_FLOAT", 32);
define("UNIX_TIMESTAMP", 64);
define("DOWNLOAD_CSV", 128);

$opt = @$_GET["o"];

if ($opt & DOWNLOAD_CSV) {
	header('Content-type: text/csv');
	header("Content-Disposition: attachment; filename=$fname.csv"); // RFC2183: Querry Open/Save
} else header('Content-Type: text/plain');

echo "\xEF\xBB\xBF";	// UTF8-Byte-Order-Mark

if ($dbg) {
	echo "<DEBUG>\n";
}

if (!@file_exists(S_DATA . "/$mac")) exit_error("Error (Directory/MAC not found)");
//if(check_dirs()) exit_error("Error (Directory/MAC not found)");
if (empty($fname)) exit_error("No Data (Need File)");

$rfile = S_DATA . "/$mac/files/$fname";
if (!file_exists($rfile)) {
	exit_error("ERROR: File not found: '$rfile' (maybe too old?)");
}

$data = file($rfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (count($data) < 1) exit_error("ERROR: File is empty: '$rfile')");

// Add 2 Lines of META-Info
if (!($opt & NO_UNIMP_TOK)) {
	echo "<FILE: '$fname'>\n"; // 34=32+2 Remove min + max
}

echo "<MAC: $mac>\n";  // Opt. with name

// FIRST STEP: Find out all used channels
$chan = array(); // Mapps channel numbers to units
$ch_cnt = array(); // Counts number of values for this channel
$cnt = 0;  // Line in the RAW Data!
$talarm_cnt = 0;	// Total Alarm Count
foreach ($data as $line) {
	$cnt++;
	if ($line[0] != '!') continue;	// Ignore Lines without leading !
	if ($line[1] == 'U') continue;	// Ignore Lines with UNITS
	$tmp = explode(' ', $line);
	$idx = 0;
	foreach ($tmp as $iv) {
		if ($idx) {
			$ds = explode(':', $iv); // As Key/Val
			$key = $ds[0];
			if (!strlen($key)) {
				$err_cnt++;
				$xerr .= "<ERROR: Empty Channel ('$iv' ignored) in Line $cnt>\n";
			} else {
				$chan[$key] = $key;	// Mark unit as physically used (map to itself)
				$ch_cnt[$key] = 1;	// Channel is used!
			}
		}
		$idx++;
	}
	if (strlen($xerr)) {
		echo $xerr;
		$xerr = "";
	}
}
if (!($opt & NO_UNIMP_TOK)) echo "<DATA: $cnt Lines Input>\n";

// Ensure that channels in the output are in numeric order
asort($chan);

// SECOND STEP: Output CSV-Style Data
$cnt = 0;
$shdr = 1;	// if set: Show Header
$st_flag = 0;		// Strange-Time-Flag (emit Warning only once)
$ltimes = 0;	// Lst Timestamp
$lalarm_cnt = 0;	// Local Alarm Count
$oline = "";	// Output line with values;
foreach ($data as $line) {
	$cnt++;
	if ($line[0] != '!') {
		if ($opt & NO_UNIMP_TOK) { // might be unimportant
			if (!($opt & NO_NT)) {
				if (strcmp(substr($line, 0, 4), "<NT ")) continue;
			} else continue;
		}
		echo "$line\n";
		continue;
	}
	if ($line[1] == 'U') { // UNITS found
		$tmp = explode(' ', $line);
		$idx = 0;
		foreach ($tmp as $iv) {
			if ($idx) {
				$ds = explode(':', $iv); // As Key/Val
				$key = $ds[0];
				if (!@$ch_cnt[$key]) continue;	// Unit not used, ignore!
				$val = $ds[1];
				if (strlen($key)) {
					@$chan[$key] = $val;	// Give Channel a Name
				} else {
					$warn_cnt++;
					$xerr .= "<WARNING: Whitespace Chars ignored in Line $cnt>\n";
				}
			}
			$idx++;
		}
		$shdr = 1;	// Next Round show Header
		continue;
	}

	// Data Line. 
	if ($shdr) {	// If necessary: Show CSV Header
		if ($opt & NO_LINECNT) $oline .= "TIME";	// Time Only
		else $oline .= "NO, TIME";	// Standard

		foreach ($chan as $hdr => $htxt) {
			if (strcmp($hdr, $htxt)) $oline .= ", $htxt($hdr)";	// Different Name
			else $oline .= ", $hdr";
		}
		if ($opt & GERMAN_FLOAT) {	// Deutsches Float-Format: Comma and Semicolon separated
			$oline = strtr($oline, ",", ";");
			$oline = strtr($oline, ".", ",");
			$oline = strtr($oline, "~", ".");	// For the Date
		}

		echo "$oline\n";	// Header
		$oline = "";
		$shdr = 0;
	}

	$tmp = explode(' ', $line);
	$idx = 0;
	$lina = array();	// Create an empty Output Array for Mapping Channels to Units
	$toval = 0;		// Total Number of Values in this line
	$nnerr = 0;
	foreach ($tmp as $iv) {
		if (!$idx) {
			if ($iv[1] == '+') {
				$secs = intval(substr($iv, 2));	// Realtive Seconds to last TS
				if (!$ltimes) {
					$warn_cnt++;
					$xerr .= "<ERROR: No Timestamp in Line $cnt>\n";
				} else {
					$secs += $ltimes;
				}
			} else {
				$secs = intval(substr($iv, 1));	// Seconds since 1970
			}
			$ltimes = $secs + $utc_offset;
			// Omit . because  of Deutsches float format
			if ($opt & UNIX_TIMESTAMP) $ts = $secs; // Unix-Timestamp plu Offset
			else if ($opt & GERMAN_FLOAT) $ts = gmdate("d~m~y H:i:s", $secs); // Prepare for German
			else $ts = gmdate("d.m.y H:i:s", $secs); // Standare Europe

			if ($opt & NO_LINECNT) $oline .= $ts;	// Time Only
			else $oline .= "$cnt, $ts"; // No and Timestamp
			if ($secs < 1526030617 || $secs >= 0xF0000000) {  // 2097
				if ($st_flag == 0) {
					$st_flag = 1;	// Emit Warning only once
					$warn_cnt++;
					$xerr .= "<WARNING: Strange Time in Line $cnt>\n";
				}
			} else $st_flag = 0;	// Time is OK
		} else {
			// Now: Logically Difficult step: MAP Index to Channel, because Idx->Val 
			$ds = explode(':', $iv); // As Key/Val
			$key = $ds[0];
			$val = @$ds[1];
			if (!isset($val)) {
				$err_cnt++;
				$xerr .= "<ERROR: No Value for Channel ('$iv') in Line $cnt>\n";
			}
			if (isset($lina[$key])) {	// Can not set twice per line!
				$err_cnt++;
				$xerr .= "<ERROR: Channel '$key' already used ('$iv' ignored) in Line $cnt>\n";
			} else {
				if ($val[0] == '*') {	// Line marked as ALARM
					$val = substr($val, 1);
					if (!is_numeric($val)) $nnerr++;
					// Opt. to process e.g. HTML
					if (!($opt & NO_ALARMS)) {
						$val = '*' . $val;
					}
					$lalarm_cnt++; 	// Count ALARMS
					$talarm_cnt++;
				} else if (!is_numeric($val)) $nnerr++;
				$lina[$key] = $val;
				$toval++;
			}
		}
		$idx++;
	}

	// Output according to Tab Header!
	$oval = 0;
	foreach ($chan as $lchan => $cno) {
		$val = @$lina[$lchan];
		if (isset($val)) {
			$oline .= ", $val";
			$oval++;
		} else {
			if ($toval == $oval) break;	// Rest of Line will contain no Data...
			$oline .= ", ";
		}
	}
	//if($lalarm_cnt) echo "Alarm: ";	// Opt. Mark Alarm-Line
	if ($opt & GERMAN_FLOAT) {	// Deutsches Float-Format: Comma and Semicolon separated
		$oline = strtr($oline, ",", ";");
		$oline = strtr($oline, ".", ",");
		$oline = strtr($oline, "~", ".");	// For the Date
	}
	echo "$oline\n";

	$oline = "";
	if ($nnerr && !($opt & NO_UNIMP_TOK)) {
		if ($nnerr > 1) echo "<INFO: Non numeric Values ($nnerr) in Line $cnt>\n"; // Mark in Colors
		else echo "<INFO: Non numeric Value in Line $cnt>\n"; // Mark in Colors
	}
	if (strlen($xerr)) {
		echo $xerr;
		$xerr = "";
	}
}

$mtrun = round((microtime(true) - $mtmain_t0) * 1000, 4);
if ($err_cnt || $warn_cnt) echo "<TOTAL ERRORS: $err_cnt, WARNINGS: $warn_cnt>\n";
if ($talarm_cnt && !($opt & NO_UNIMP_TOK)) echo "<ALARMS: $talarm_cnt>\n";
if (!($opt & NO_UNIMP_TOK)) echo "<RUNTIME: $mtrun msec>\n";
