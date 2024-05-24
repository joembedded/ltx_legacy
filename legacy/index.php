<?php
// Devloper-Login via S_API_KEY
error_reporting(E_ALL);

include("../sw/conf/api_key.inc.php");
include("../sw/lxu_loglib.php");
include("mcclist.inc.php");

session_start();
if (isset($_REQUEST['k'])) {
	sleep(1);
	$api_key = $_REQUEST['k'];
	$_SESSION['key'] = L_KEY;
} else {
	$api_key = @$_SESSION['key'];
}
if (!isset($api_key)) $api_key = "";
if (!strcmp($api_key, L_KEY)) {
	$dev = 1;	// Dev-Funktionen anzeigen
} else {
	$dev = 0;	// Dev-Funktionen anzeigen
	$_SESSION['key'] = "";
}
echo "<!DOCTYPE HTML><html><head>";

if ($dev) $title = "LegacyLTX Develop-Login V0.55";
else $title = "LegacyLTX Home and Guest/Demo-Login V0.55";

$self = $_SERVER['PHP_SELF']; // Periodisch alle 30 Sekunden  auffrischen
echo "<meta http-equiv=\"refresh\" content=\"30; URL=$self\">";
?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- <link rel="stylesheet" href="css/w3.css"> -->
<meta name=\"robots\" content=\"noindex,nofollow\">
<title>
	<?php echo $title; ?>
</title>
</head>

<body>
	<?php

	// dev.php DevPortal Script for LTrax. Details: see docu
	// Only for Low-Level Developer Access!!!
	// (C)joembedded@gmail.com  - jomebedded.de
	// V0.56 / 20.01.2024
	// todo: --- maybe LOCK makes sense for several files
	if (!isset($self) || strlen($self) < 4) echo "WARNING: 'PHP_SELF' not set<br>";

	// --- ensure user access (e.g. via keys)

	// Fkt. --- Convert to Zeitstring
	function  secs2period($secs)
	{
		if ($secs >= 259200) return "over " . floor($secs / 86400) . "d";
		if ($secs >= 86400) return "over " . floor($secs / 3600) . "h";
		return gmdate('H\h\r i\m\i\n s\s\e\c', $secs);
	}

	// ----------- M A I N ---------------
	$mtmain_t0 = microtime(true);         // for Benchmark 
	echo "<p><b><big>$title</big></b><br>";

	if (!$dev) echo "<br><b><font color=\"red\">*** Guest Mode (Read-Only) ***</font></b>";

	echo "</p>";
	$dbg = 0;	// No Debug enabled

	$now = time();						// one timestamp for complete run
	$dir = S_DATA;

	// Evtl. gen Dirs
	if (!file_exists(S_DATA)) {
		mkdir(S_DATA);  // MainDirectory
		$xlog = "(Generated Device Directory '$dir/')";
	}
	if (!file_exists(S_DATA . "/log")) {
		mkdir(S_DATA . "/log");  // Log-Files
		if (isset($xlog)) {
			add_logfile();
			$xlog = "";
		}
	}

	if ($dev) echo "<b>Devicelist (DIR: '$dir'):</b><br>"; // Show Directory
	else echo "<b>Devicelist:</b><br>"; // Show Directory

	$list = scandir($dir);
	$anz = 0;
	foreach ($list as $file) {
		if ($file == '.' || $file == '..') continue;
		if (!ctype_xdigit($file)) continue;	
		if (!is_dir("./$dir/$file")) continue;	// Should not be, but..
		if (!$dev && !@file_exists(S_DATA . "/$file/demo.cmd")) continue;

		$devi = array();
		$fage = "???";
		$lines = @file(S_DATA . "/$file/device_info.dat", FILE_IGNORE_NEW_LINES  | FILE_SKIP_EMPTY_LINES);
		if ($lines) {
			// Hier noch mehr Mgl.: Letzter Transfer, Bat, ...
			foreach ($lines as $line) {
				$tmp = explode("\t", $line);
				$val = $tmp[1];
				$devi[$tmp[0]] = $tmp[1];
				// echo "Line: $line<br>";
			}
			if (!empty($devi['now'])) {
				$dt = $now - $devi['now'];
				$fage = secs2period($dt);
			}
			$bakg = "";
			if ($dt > 259200) { // 14d
				$bakg = "magenta";
			} else if ($dt > 259200) { // 3d
				$bakg = "red";
			} else if ($dt > 14400) { // 24h
				$bakg = "deeppink";
			} else if ($dt > 43200) { // 12h
				$bakg = "lightpink";
			} else if ($dt > 14400) { // 4h
				$bakg = "yellow";
			} else if ($dt > 7200) {	// 2h
				$bakg = "lightyellow";
			} else if ($dt < 120) {	// 2 min
				$bakg = "lawngreen";
			}

			if ($bakg) $fage = "<span style='background-color:$bakg'> $fage </span>";
		}
		// Link to this device
		echo "<a href=\"device_lx.php?s=$file\" target='_blank'>$file</a>";

		echo " (Name LTX: ";
		$iparam_info =  @file(S_DATA . "/$file/files/iparam.lxp", FILE_IGNORE_NEW_LINES);
		if (@isset($iparam_info[5])) echo "'<b>" . htmlspecialchars($iparam_info[5]) . "</b>'";
		else echo "(NO 'iparam.lxp')";


		echo " , Legacy: "; // Only Legac
		$user_info = @file(S_DATA . "/$file/user_info.dat", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if (@$user_info[0]) echo "'<b>" . htmlspecialchars($user_info[0]) . "</b>'";
		else echo '(<i>not set</i>)';
		echo ")";

		$sig=@$devi['signal'];
		if($sig){
			$mccs = substr($sig,4,3);
			$country=@$mcca[intval($mccs)];
			if(!$country) $country=$mcca[intval($mccs[0])]; // Fallback
			echo " &nbsp; (<i>$country</i>) &nbsp; ";
		}
		echo " Last Contact: $fage";
		if ($dev && @file_exists(S_DATA . "/$file/cmd/dbg.cmd")) {
			echo " <b>***Debug enabled***</b>";
			$dbg++;
		}

		if ($dev) {
			if (@file_exists(S_DATA . "/$file/demo.cmd")) {
				echo " <b>Demo-Mode</b> <a href=\"unlink_lx.php?s=$file&f=demo.cmd\">[Remove]</a>";
			} else {
				echo " <a href=\"setcmd_lx.php?s=$file&f=demo.cmd\">[Enable Demo-Mode]</a>";
			}
		}

		echo '<br>';
		$anz++;
	}
	if (!$anz) echo "Sorry, no Devices available<br>\n";
	echo "($anz Devices found)<br>";

	if ($dev) {
		echo "</p><p><b>Manage:</b><br>";
		$dpath = "$dir/log";
		$ds = @filesize("$dpath/log.txt");
		if ($ds > 0) {
			$dt = $now - filemtime("$dpath/log.txt");
			$fa = secs2period($dt);

			echo "<a href=\"view.php?s=log&f=log.txt\">Main Logfile 'log.txt'</a> ($ds Bytes, Age: $fa)<br>";
		} else {
			echo "Main Logfile 'log.txt' (not found)<br>";
		}
		$ds = @filesize("$dpath/_log_old.txt");
		if ($ds > 0) {
			$fa = secs2period($now - filemtime("$dpath/_log_old.txt"));
			echo "<a href=\"view.php?s=log&f=_log_old.txt\">Old Main Logfile '_log_old.txt'</a> ($ds Bytes, Age: $fa)<br>";
		}
		$ds = @filesize("$dpath/pcplog.txt");
		if ($ds > 0) {
			$dt = $now - filemtime("$dpath/pcplog.txt");
			$fa = secs2period($dt);

			echo "<a href=\"view.php?s=log&f=pcplog.txt\">Main Push-Pull-Logfile 'pcplog.txt'</a> ($ds Bytes, Age: $fa)<br>";
		} else {
			echo "Main PCP-Logfile 'pcplog.txt' (not found)<br>";
		}
		$ds = @filesize("$dpath/_pcplog_old.txt");
		if ($ds > 0) {
			$fa = secs2period($now - filemtime("$dpath/_pcplog_old.txt"));
			echo "<a href=\"view.php?s=log&f=_pcplog_old.txt\">Old Main Push-Pull-Logfile '_pcplog_old.txt'</a> ($ds Bytes, Age: $fa)<br>";
		}

		if (@file_exists("../sw/service/index.php")) {
			echo "<br><a href=\"../sw/service/index.php\" target='_blank'>Service...</a>";
		}

		echo "<br><a href=\"browse.php\" target='_blank'>Browse LTrax Files (Main Directory)</a> ";
		if ($dbg) echo "<b>***Debug enabled ($dbg)***</b>";
		echo "<br><a href=\"php_info.php\" target='_blank'>PHP-Info</a><br>";
	}
	if (!$dev) {
	?>
		<br>
		<form method="post" action="index.php">
			<!-- User (Legacy): --><input placeholder="Enter User" type="input" name="user" value="legacy" hidden>
			<b>Password ('L_KEY'): </b><input placeholder="Enter Password" type="password" name="k"> <input type="Submit" value="Login">
		</form>
	<?php
	} else {
	?>
		<br>
		<form method="post" action="index.php">
			<input type="hidden" value="" name="k"> <input type="Submit" value="Logout">
		</form>
	<?php
	}

	$mtrun = round((microtime(true) - $mtmain_t0) * 1000, 4);
	echo "<small>(Runtime: $mtrun msec)</small>";

	?>

</body>

</html>