<?PHP // periodically refresh page (all 15 secs)
error_reporting(E_ALL);
include("../sw/conf/api_key.inc.php");
@include("../sw/conf/config.inc.php");
include("mcclist.inc.php");

session_start();
if (isset($_REQUEST['k'])) {
	$api_key = $_REQUEST['k'];
	$_SESSION['key'] = L_KEY;
} else $api_key = @$_SESSION['key'];
if (!strcmp($api_key, L_KEY)) {
	$demo = 0;	// Dev-Funktionen anzeigen
} else {
	$demo = 1;
}
echo "<!DOCTYPE HTML><html><head>";

$qs = $_SERVER['QUERY_STRING'];
$self = $_SERVER['PHP_SELF'];
echo "<meta http-equiv=\"refresh\" content=\"15; URL=$self?$qs\"></head>";
?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- <link rel="stylesheet" href="css/w3.css"> -->

<?php
	// Legacy - device_lx.php Device View Script for LTX. Details: see docu
	// (C)joembedded@gmail.com  - jomebedded.de
	// Version: 0.55 25.11.2023
	
	// todo: Kann sein, dass bei put/get/dir/del/-remove n File vergessen worden ist: pruefen!
	// todo: maybe LOCK makes sense for several files
	if(!isset($self) || strlen($self)<4) echo "WARNING: 'PHP_SELF' not set<br>";

	// Fkt. --- Convert to Zeitstring
	function  secs2period($secs)
	{
		if ($secs >= 259200) return "over " . floor($secs / 86400) . "d";
		if ($secs >= 86400) return "over " . floor($secs / 3600) . "h";
		return gmdate('H\h\r i\m\i\n s\s\e\c', $secs);
	}

	// ----------------- M A I N -------------------
	$mtmain_t0 = microtime(true);         // for Benchmark 
	$mac = @$_REQUEST['s'];
	$now = time();
	$dbg = 0;	// Biser noch ohne Fkt.
	$dpath = S_DATA . "/$mac";

	if(!isset($dname)){
		$iparam_info =  @file(S_DATA . "/$dpath/files/iparam.lxp", FILE_IGNORE_NEW_LINES);
		$dname = @htmlspecialchars(@$iparam_info[5]);
	}
	echo"<title>LegacyLTX ";
	if(isset($dname)) echo"'$dname'";
	else echo "MAC:$mac";
	echo"</title></head><body>";


	if (!isset($mac)) {
		echo "ERROR: MAC required";
		exit();
	}
	if (strlen($mac) != 16) {
		echo "ERROR: MAC Len";
		exit();
	}
	if (!@file_exists($dpath)) {
		echo "ERROR: Unknown MAC";
		exit();
	}


	echo "<p><b><big>LTX Device View $mac</big></b><br></p>";
	echo "<p><a href=\"index.php\">LegacyLTX Home</a><br>";

	if ($demo) echo "<b><font color=\"red\">*** Guest Mode (Read-Only) ***</font></b>";

	$tmp = array();
	$infofile = "$dpath/device_info.dat";
	if (!file_exists($infofile)) {
		echo "(No Data found)<br>";
	} else {
		$lines = file($infofile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		// Hier noch mehr Mgl.: Letzter Transfer, Bat, ...
		if ($lines) foreach ($lines as $line) {
			$tmp = explode("\t", $line);
			$val = $tmp[1];
			$devi[$tmp[0]] = $tmp[1];
			// echo "Line: $line<br>";
		}
	}
	$pasync = intval(@$devi['pasync']); // >0: Asynchron with packets (e.g. LTE-NB UDP only, LoRa, ..)
	//--- Show avilabale infos ---
	echo "</p><p><b>Device Info:</b><br>";

	echo "Name: ";
	if (isset($dname)) echo "'<b>$dname</b>'";
	else echo "(NO 'iparam.lxp')";
	echo "<br>";

	$user_info = @file("$dpath/user_info.dat", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	if (@$user_info[0]) echo "Legacy Name: '<b>" . htmlspecialchars($user_info[0]) . "</b>'";
	else echo "Legacy Name: (<i>not set</i>)";
	if (!$demo) echo " <a href=\"edit_userinfoname.php?s=$mac\">[Edit Legacy Name ('user_info.dat')]</a>";
	echo "<br>";

	$fage = "???";
	if (!empty($devi['now'])) {
		$dt = $now - $devi['now'];
		$fage = secs2period($dt);
	}
	echo "Last Contact: $fage";

	echo " - Reason: ";
	$reason = $devi['reason'];
	switch ($reason & 15) { //
			//case 1:	echo "RADIO"; break;
		case 2:
			echo "AUTO";
			break;
		case 3:
			echo "MANUAL";
			break;
			// case 4:	echo "SMS";	break;
		default:
			echo "UNKNOWN(reason=$reason)";
			break;	// Alarm e.g. t.b.d
	}
	if ($reason & 128) echo ", <b><font color=blue>RESET</font></b>";
	if ($reason & 64) echo ", <b><font color=red>ALARM</font></b>";
	else if ($reason & 32) echo ", <b><font color=orange>old ALARM</font></b>";
	echo "<br>";

	if (@$devi['expmore'] > 0) {
		echo "<b>WARNING: Last Transfer pending or incomplete</b><br>";
	}

	if (!$pasync) {
		$dt = $devi['sdelta'];  // $devi['dtime']-$devi['now']; different for long transfers
		echo "Deviation to Server: $dt secs<br>";
	}

	echo "Transmission Count (All/OK): " . $devi['conns'] . '/' . @$devi['trans'] . '<br>';

	if (defined("DB_NAME")) {
		$quota = @file("$dpath/quota_days.dat", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		echo "Quota Days: ";
		if (isset($quota[0])) echo $quota[0];
		else echo "(Unknown)";
		echo " / Lines: ";
		if (isset($quota[1])) echo $quota[1];
		else echo "(Unknown)";
		if (!$demo) {
			if (isset($quota[2])) echo ", Push: '".htmlspecialchars($quota[2])."'";
			else echo ", Push: (<i>not set</i>)";
			echo " <a href=\"edit_quota.php?s=$mac\">[Edit Quota/Push ('quota_days.dat')]</a>";
		}
		$dapikey = @file_get_contents("$dpath/dapikey.dat"); // false oder KEY
		echo "<br>";
		if($dapikey!==false) echo "DApiKey: '$dapikey'<br>";
	}

	if (!$demo) {
		echo "Bytes/UTC-Day (In/Out): " . @$devi['quota_in'] . '/' . @$devi['quota_out'];
		echo " - Total (In/Out): " . (@$devi['quota_in'] + @$devi['total_in']) . '/' . (@$devi['quota_out'] + @$devi['total_out']);
		echo " Since: [" . gmdate("d.m.Y H:i:s", @file_get_contents("$dpath/date0.dat")) . "](UTC)<br>"; // filectime() scheint sich fuer DIRs zu aendern..

		if (!$pasync) {
			echo "Device Firmware ";
			if (!empty($devi['fw_ver'])) echo "Version: V" . ($devi['fw_ver'] / 10);
			else echo "(unknown)";
			$fwt = @$devi['fw_cookie'];
			if ($fwt < 1526030617 || $fwt > 0xF0000000) $cookie_str = "<b>(WARNING: Bootloader Release unknown)</b>"; // 2097
			else $cookie_str = '[' . gmdate("d.m.Y H:i:s", $fwt) . '](UTC)';
			echo " (dated: $cookie_str)";
			echo "<br>";
			echo "Device Type: ";
			if (!empty($devi['typ'])) echo $devi['typ'] . "<br>";
			else echo "(unknown)<br>";
			echo "SIM (ICCID): ";
			if (!empty($devi['imsi'])) echo "'" . $devi['imsi'] . "'<br>";
			else echo "(unknown)<br>";

			if (@file("$dpath/cmd/_firmware.sec.umeta")) { // META is important, not secured file...
				$fwinfo = array();
				$lines = file("$dpath/cmd/_firmware.sec.umeta", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
				foreach ($lines as $line) {
					$tmp = explode("\t", $line);
					$fwinfo[$tmp[0]] = $tmp[1];
				}
				echo "Firmware Bin-File found: '" . $fwinfo['fname_original'] . "'";
				if (@$fwinfo['check'] > 0) {
					echo " is OK, Send-Cnt:" . $fwinfo['sent'];
					if (@$fwinfo['check']) echo " (Transfer OK: " . gmdate("d.m.Y H:i:s", $fwinfo['check']) . '](UTC))';
					echo '<br>';
				} else {
					echo ", <b><font color='red'>Update pending</font></b> (Send-Cnt:" . $fwinfo['sent'] . ')<br>';
				}
			} else {
				echo "(No Firmware Bin-File)<br>";
			}
		} else {
			echo "Device Type: " . $devi['typ'] . " (No Disk)<br>";
		}
	}

	if (!$demo) {
		if (!$pasync) {
			echo "Disk available: ";
			if (isset($devi['dsize'])) {
				echo "ca. " . $devi['davail'] . 'kb/' . $devi['dsize'] . "kb - Formated:";
				$ddate = $devi['ddate'];
				if ($ddate != 0xFFFFFFFF) echo "[" . gmdate("d.m.Y H:i:s", $ddate) . "]<br>";
				else echo "(DATE ERROR)<br>";
			} else echo "(NO DINFO)<br>";
		}
	}


	if (!empty($devi['signal']) && strlen(G_API_KEY)) {
		$sig = $devi['signal'];
		//echo "Signal: $sig ";
		echo "Signal: ";
		$siga = explode(' ', $sig);
		$asig = array();	// KeyVal-Array with Signal Info
		foreach ($siga as $sigv) {
			$tmp = explode(":", $sigv);
			$asig[$tmp[0]] = $tmp[1];
		}
		echo $asig['dbm'] . " dbm";
		$act = @$asig['act'];
		if($act){
			$acts = array("No/unkn.", "GSM", "GPRS", "EDGE", "LTE_M", "LTE_NB", "LTE");
			$actn=@$acts[$act];
			echo " ($actn)";
		}

		$sqs = 'k=' . G_API_KEY . "&s=$mac&lnk=1&mcc=" . $asig['mcc'] . "&net=" . $asig['net'] . "&lac=" . $asig['lac'] . "&cid=" . $asig['cid'];

		if ($asig['ta'] == 255) $radius = "";
		else $radius = "ca. " . ($asig['ta'] * 500 + 500) . " mtr ";

		echo " - Device located " . $radius . "arround <a href=\"" . CELLOC_SERVER_URL . "?$sqs\" target=\"_blank\" title=\"Estimated Position of last Cell Tower\">[Here]</a>";

		$mcc=@$asig['mcc'];
		if($mcc){
			$country=@$mcca[intval($mcc)];
			if(!$country) $country=$mcca[intval($mcc[0])]; // Fallback
			echo "  &nbsp; (<i>$country</i>)";
		}
		echo "<br>";
	}

	if (!empty($devi['lut_cont'])) {
		$lut_dstr = gmdate("d.m.Y H:i:s", $devi['lut_date']);
		echo "Last User Content (from Device): '" . htmlspecialchars($devi['lut_cont']) . "' [$lut_dstr]<br>";
	}
	if (!empty($devi['luc_cmd'])) {
		$luc_dstr = gmdate("d.m.Y H:i:s", $devi['luc_date']);
		echo "Last User Command (to Device): '" . htmlspecialchars($devi['luc_cmd']) . "' [$luc_dstr] (" . $devi['luc_state'] . ")<br>";
	}

	$etext=@file_get_contents("$dpath/cmd/okreply.cmd"); 
	if($etext){ // Leerstring zaehlen als false
		echo "Last Transmission OK: '".htmlspecialchars(substr(str_replace("\n"," ",$etext),0,40))."'";
		if (!$demo) {
			echo " <a href=\"unlink_lx.php?s=$mac&f=cmd/okreply.cmd\">[OK]</a>";
		}
		echo "<br>";
	}

	// Show Directory
	// Directory-Show: Normally: Always FIRST: Get Directory, then get files...

	// What we really have as files: (physically)
	$phflist = scandir("$dpath/files", SCANDIR_SORT_NONE); // Contains at least . and ..
	$getfilecmd = 0;
	$delfilecmd = 0;


	if (!$pasync) {
		echo "<br><b>Files on Device:</b>";
		if (!empty($devi['dirtime'])) { // Generated after 1.st contact
			$dt = $now - $devi['dirtime']; // merken
			if (!$demo) echo " (last Directory Scan " . secs2period($dt) . " ago)";
		} else {
			if (!$demo) echo " (no Directory Scan)";
		}
		if (!$demo) {
			if (@file_exists("$dpath/cmd/getdir.cmd")) {
				echo " <a href=\"unlink_lx.php?s=$mac&f=cmd/getdir.cmd\">[Remove GET DIR]</a>";
				$getdircmd = 1;
			} else {
				echo " <a href=\"setcmd_lx.php?s=$mac&f=cmd/getdir.cmd&l=4\">[GET DIR]</a>";
				$getdircmd = 0;
			}
		}
	} else {
		echo "<br><b>Files: (local)</b>";
	}
	echo "<br>";

	$flist = scandir("$dpath/cmd"); // Sorted
	foreach ($flist as  $fmeta) {
		$pos = strrpos($fmeta, '.');
		if (strcmp(substr($fmeta, $pos), ".vmeta")) continue;	// only interested in vmeta
		$fname = substr($fmeta, 0, $pos);
		echo ("'$fname': ");
		$key = array_search($fname, $phflist);
		if ($key !== false) {	// File found, mark as processed
			$phflist[$key] = "";	// Remove from List
		}

		$fostat = array();
		if (file_exists("$dpath/cmd/$fname.vmeta")) {
			$lines = file("$dpath/cmd/$fname.vmeta", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			foreach ($lines as $line) {
				$tmp = explode("\t", $line);
				$fostat[$tmp[0]] = $tmp[1];
			}
		}
		if (isset($fostat['vd_len'])) { // empty is true if '0'
			$dtdir = $fostat['vd_dir'];
			if (strlen($dtdir)) {
				$dtf = $now - $dtdir;
				if ($dtf > $dt && !empty($devi['dirtime'])) {	// First check if valid, if Entry older than DIR: File deleted!
					echo " (WARNING: Old META Infos)";	// Todo; mark old meta
				}
			}
			echo "Len:" . $fostat['vd_len'] . " Bytes";
			$ffl = $fostat['vd_flags'];
			if (!$demo) {
				if ($ffl & 32) echo " (unclosed)";
				if ($ffl & 16) {
					$val = (float)$fostat['vd_crc'];	// !float required, int geht nicht!
					echo " CRC:" . dechex($val);
				}
				if ($ffl & 64) echo " SYNC";
				if ($ffl & 128) echo " HIDDEN";
				if ($ffl & 256) echo " (NEW PUT)";	// newly coppied (not re-read)
				echo " [" . gmdate("d.m.Y H:i:s", $fostat['vd_date']) . ']';
			}
		} else {
			echo " (NO VINFO)";
			$ffl = 0;	// No Infos
		}

		$ffstat = array();
		if (file_exists("$dpath/cmd/$fname.fmeta")) {
			$lines = file("$dpath/cmd/$fname.fmeta", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			foreach ($lines as $line) {
				$tmp = explode("\t", $line);
				$ffstat[$tmp[0]] = $tmp[1];
			}
		}
		$ffname = "$dpath/files/$fname"; // Full File Name
		if (@file_exists($ffname))	$loflen = @filesize($ffname);
		else $loflen = -1;	// Local File len

		if (!empty($ffstat['date'])) {
			echo " (Local: ";
			if ($ffstat['pos0']) echo " (Pos0:" . $ffstat['pos0'] . ', Local:' . ($ffstat['len'] - $ffstat['pos0']) . 'Bytes)';
			// Truncated local file + Startpos must be identical  to reported size, and datee must be equal)
			if (($ffstat['pos0'] + $loflen) == $ffstat['len'] &&  $ffstat['len'] == $fostat['vd_len'] && $ffstat['date'] == $fostat['vd_date']) {
				if ($ffstat['pos0']) {
					echo " *UP-TO-DATE (But only last $loflen Bytes on Server!)*";
				} else echo " *UP-TO-DATE*"; // complete!
			} else {
				echo " Len:" . $fostat['vd_len'] . " Bytes";
				if ($ffstat['len'] != $loflen) echo " (<b>ERROR: Real-Local: $loflen Bytes!</b>)";
				if ($ffstat['len'] != $fostat['vd_len']) echo " LENGTH";
				if ($ffstat['date'] != $fostat['vd_date']) echo " TIMESTAMP";
			}
			echo ')';
		}
		if ($loflen > 0) { // Size 0 makes no sense
			echo " <a href=\"view.php?s=$mac&f=files/$fname\" title=\"View raw content of File\">[Open local]</a> ";
			// Check Extensions: EDT .EasyDaTa
			if (strpos($fname, '.edt')) { // if pos 0 is allowed: use !== false..
				echo "<a href=\"edt_view.php?s=$mac&f=$fname\" title=\"View as CSV (Text)\">[View CSV]</a> ";
				echo "<a href=\"edt_view.php?s=$mac&f=$fname&o=135\" title=\"Download as CSV, Float Format: German\">[CSV(D)]</a> ";
				echo "<a href=\"edt_view.php?s=$mac&f=$fname&o=131\" title=\"Download as CSV, Float Format: International\">[CSV(Int)]</a> ";
				echo "<a href=\"csview/csview.php?s=$mac&f=$fname\" title=\"Graphical View Online\">[CSVIEW]</a>&nbsp;&nbsp;"; // Space wg. DELETE
				echo "<a href=\"gps_view.php?s=$mac&f=$fname\" title=\"View as GPS (Map)\">[GPSVIEW]</a> ";
			} else {
				if (!$demo) {
					if (strpos($fname, '.lxp') > 0) echo " <a href=\"edit_lxp.php?s=$mac&f=files/$fname\" title=\"Edit raw content of File as Text\">[Edit]</a> ";
				}
			}
		}

		if ($ffl & 64) {
			//echo "(No GET because SYNC)";
		} else if ($ffl & 128) {
			//echo "(No GET because HIDDEN)";
		} else {
			if (!$demo) {
				if (@file_exists("$dpath/get/$fname")) {
					echo " <a href=\"unlink_lx.php?s=$mac&f=get/$fname\">[Remove GET(remote)]</a>";
					$getfilecmd++;
				} else {
					echo " <a href=\"setcmd_lx.php?s=$mac&l=4&f=get/$fname\">[GET(remote)]</a>"; // 4 tries
				}
			}
		}

		if (!$demo) {
			if (@file_exists("$dpath/del/$fname")) {
				echo "<a href=\"unlink_lx.php?s=$mac&f=del/$fname\">[Remove DEL(remote)]</a>";
				$getfilecmd++;
			} else {
				echo "&nbsp;&nbsp;<a href=\"setcmd_lx.php?s=$mac&l=4&f=del/$fname\">[DEL(remote)]</a>"; // 4 tries
			}
		}
		echo "<br>";
	}


	// Now list other files
	foreach ($phflist as $phfname) {
		if (!strlen($phfname) || !strcmp($phfname, ".") || !strcmp($phfname, "..")) continue;

		if (!$pasync) echo "( '$phfname': " . filesize("$dpath/files/$phfname") . " Bytes (Backup)))";
		else echo "$phfname': " . filesize("$dpath/files/$phfname") . " Bytes &nbsp; ";
		echo " <a href=\"view.php?s=$mac&f=files/$phfname\" title=\"View raw content of File as Text\">[Open local]</a> ";
		// Check Extensions: EDT .EasyDaTa

		if (strpos($phfname, '.edt')) {
			echo "<a href=\"edt_view.php?s=$mac&f=$phfname\" title=\"View as CSV (Text)\">[View CSV]</a> ";
			echo "<a href=\"edt_view.php?s=$mac&f=$phfname&o=135\" title=\"Download as CSV, Float Format: German\">[CSV(D)]</a> ";
			echo "<a href=\"edt_view.php?s=$mac&f=$phfname&o=131\" title=\"Download as CSV, Float Format: International\">[CSV(Int)]</a> ";
			echo "<a href=\"csview/csview.php?s=$mac&f=$phfname\" title=\"Graphical View Online\">[CSVIEW]</a>";
		} else if ($pasync && strpos($phfname, '.lxp') > 0) {
			echo " <a href=\"edit_lxp.php?s=$mac&f=files/$phfname\" title=\"Edit raw content of File as Text\">[Edit]</a> ";
		}
		if (!$demo) echo "&nbsp;&nbsp;<a href=\"unlink_lx.php?s=$mac&f=files/$phfname\">[DEL]</a>)"; // 4 tries
		echo "<br>";
	}

	// Footer-info
	if (($delfilecmd > 0 || $getfilecmd > 0) && !$getdircmd) echo "<i>(INFO: Recommended to set both: DIR and GET/DEL FILE)</i><br>";
	if ($getfilecmd > 3) echo "<i>(INFO: Maybe 'notepad' on Device is not large enough for $getfilecmd Files)</i><br>";


	$putlist = scandir("$dpath/put", SCANDIR_SORT_NONE); // Contains at least . and ..

	if (sizeof($putlist) > 2) {
		echo "<br><b>Files to 'put':</b><br>";
		foreach ($putlist as $putfn) {
			if (!strcmp($putfn, '.') || !strcmp($putfn, '..')) continue;
			// File found, get META-Data
			echo "'$putfn': ";
			echo "Len:" . filesize("$dpath/put/$putfn") . " Bytes ";
			$lines = @file("$dpath/cmd/$putfn.pmeta", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			if ($lines != false) {
				$fputa = array();
				foreach ($lines as $line) {
					$tmp = explode("\t", $line);
					$fputa[$tmp[0]] = $tmp[1];
				}
				if ($fputa['sent'] > 0) {
					echo "(Sent-Cnt:" . $fputa['sent'] . " Stage:" . $fputa['stage'] . " )[" . gmdate("d.m.Y H:i:s", $fputa['now']) . "] Unconfirmed!)";
					// Retry-Button difficult...
				} else echo "(Pending)";
			} else {
				echo "(ERROR: No Meta)";
			}
			if (!$demo) {
				echo " <a href=\"view.php?s=$mac&f=put/$putfn\">[Open local]</a>";
				echo " <a href=\"unlink_lx.php?s=$mac&f=put/$putfn\">[Remove]</a>";
			}
			echo "<br>";
		}
	}

	if (!$demo) {

		echo "</p><p><b>Manage:</b><br>";
		if (strlen(KEY_API_GL) && strlen(KEY_SERVER_URL)) {
			echo "<a target='_blank' href='gen_key_badge.php?s=$mac'>Generate Key Badge</a><br><br>";
		}

		$ds = @filesize("$dpath/log.txt");
		if ($ds > 0) {
			$dt = $now - filemtime("$dpath/log.txt");
			$fa = secs2period($dt);

			echo "<a href=\"view.php?s=$mac&f=log.txt\" title=\"View Logfile as Text\">Logfile 'log.txt'</a> ($ds Bytes, Age: $fa)<br>";
		} else {
			echo "Logfile 'log.txt' (not found)<br>";
		}
		$ds = @filesize("$dpath/_log_old.txt");
		if ($ds > 0) {
			$fa = secs2period($now - filemtime("$dpath/_log_old.txt"));
			echo "<a href=\"view.php?s=$mac&f=_log_old.txt\" title=\"View old Logfile as Text\">Old Logfile '_log_old.txt'</a> ($ds Bytes, Age: $fa)<br>";
		}

		$ds = @filesize("$dpath/pcplog.txt");
		if ($ds > 0) {
			$dt = $now - filemtime("$dpath/pcplog.txt");
			$fa = secs2period($dt);

			echo "<a href=\"view.php?s=$mac&f=pcplog.txt\" title=\"View PCP-Logfile as Text\">Logfile 'pcplog.txt'</a> ($ds Bytes, Age: $fa)<br>";
		} else {
			echo "PCP-Logfile 'pcplog.txt' (not found)<br>";
		}
		$ds = @filesize("$dpath/_pcplog_old.txt");
		if ($ds > 0) {
			$fa = secs2period($now - filemtime("$dpath/_pcplog_old.txt"));
			echo "<a href=\"view.php?s=$mac&f=_pcplog_old.txt\" title=\"View old PCP-Logfile as Text\">Old Logfile '_pcplog_old.txt'</a> ($ds Bytes, Age: $fa)<br>";
		}

		$ds = @filesize("$dpath/conn_log.txt");
		if ($ds > 0) {
			$dt = $now - filemtime("$dpath/conn_log.txt");
			$fa = secs2period($dt);
			echo "<a href=\"con_view.php?s=$mac&f=conn_log.txt\">Connection Logfile 'conn_log.txt'</a> ($ds Bytes, Age: $fa)<br>";
		} else {
			echo "Connection Logfile 'conn_log.txt' (not found)<br>";
		}
		$ds = @filesize("$dpath/_conn_log_old.txt");
		if ($ds > 0) {
			$dt = $now - filemtime("$dpath/_conn_log_old.txt");
			$fa = secs2period($dt);
			echo "<a href=\"con_view.php?s=$mac&f=_conn_log_old.txt\">Old Connection Logfile '_conn_log_old.txt'</a> ($ds Bytes, Age: $fa)<br>";
		}

		$ds = @filesize("$dpath/info_wea.txt");
		if ($ds > 0) {
			$dt = $now - filemtime("$dpath/info_wea.txt");
			$fa = secs2period($dt);
			echo "<a href=\"view.php?s=$mac&f=info_wea.txt\">Info (Warnings/Errors/Alarms) 'info_wea.txt'</a> ($ds Bytes, Age: $fa)<br>";
		}
		$ds = @filesize("$dpath/_info_wea_old.txt");
		if ($ds > 0) {
			$dt = $now - filemtime("$dpath/_info_wea_old.txt");
			$fa = secs2period($dt);
			echo "<a href=\"view.php?s=$mac&f=_info_wea_old.txt\">Old Info (Warnings/Errors/Alarms) '_info_wea_old.txt'</a> ($ds Bytes, Age: $fa)<br>";
		}

		$ds = @filesize("$dpath/userio.txt");
		if ($ds > 0) {
			$dt = $now - filemtime("$dpath/userio.txt");
			$fa = secs2period($dt);
			echo "<a href=\"view.php?s=$mac&f=userio.txt\">User Commands 'userio.txt'</a> ($ds Bytes, Age: $fa)<br>";
		}
		$ds = @filesize("$dpath/_userio_old.txt");
		if ($ds > 0) {
			$dt = $now - filemtime("$dpath/_userio_old.txt");
			$fa = secs2period($dt);
			echo "<a href=\"view.php?s=$mac&f=_userio_old.txt\">Old User Commands '_userio_old.txt'</a> ($ds Bytes, Age: $fa)<br>";
		}

		echo '<br>';
		if (!$pasync) {
			echo "<a href=\"fw_upload_form.php?s=$mac\">Upload new Firmware File</a><br>";
			if (!empty($fwinfo['cookie'])) echo "<a href=\"unlink_lx.php?s=$mac&f=cmd/_firmware.sec.umeta\">Delete Firmware File</a><br>"; // Attention, not safe
			echo "<a href=\"files_upload_form.php?s=$mac\">Upload Files to Device's Filesystem</a><br>";
			echo "<a href=\"server_cmd_form.php?s=$mac\">Send Server Command (Byte)</a>";
			if (@file_exists(S_DATA . "/$mac/cmd/server.cmd")) {
				$server_cmd = file_get_contents(S_DATA . "/$mac/cmd/server.cmd");
				$scmd_val = ord($server_cm[0]);
				echo " (Pending: '$scmd_val' ";
				echo " <a href=\"unlink_lx.php?s=$mac&f=cmd/server.cmd\">[Remove]</a> )";
			}
			echo '<br>';
		}
		echo "<a href=\"user_cmd_form.php?s=$mac\">Send User Command (Text)</a>";
		if (@file_exists(S_DATA . "/$mac/cmd/usercmd.cmd")) {
			$user_cmd = file_get_contents(S_DATA . "/$mac/cmd/usercmd.cmd");
			echo " (Pending: '" . htmlspecialchars($user_cmd) . "' ";
			echo " <a href=\"unlink_lx.php?s=$mac&f=cmd/usercmd.cmd\">[Remove]</a> )";
		}
		echo '<br>';

		if (@file_exists(S_DATA . "/$mac/cmd/dbg.cmd")) {
			echo "<br><b>***Debug enabled:***</b><br>";
			echo "<a href=\"unlink_lx.php?s=$mac&f=cmd/dbg.cmd\">[Disable DEBUG]</a><br>";
			// Only via Dev.php! echo "<a href=\"browse.php?dir=$dpath\">Browse Device Files ('$mac')</a><br>";
		} else {
			echo "<br>(Debug disabled) ";
			echo "<a href=\"setcmd_lx.php?s=$mac&f=cmd/dbg.cmd\">[Enable DEBUG]</a><br>";
		}
	}
	if ($demo) {
	?>
		<form method="post" action="device_lx.php?s=<?php echo $mac; ?>">
			<!-- User (Legacy): --><input placeholder="Enter User" type="input" name="user" value="legacy" hidden>
			<b>Password ('L_KEY'): </b><input placeholder="Enter Password" type="password" name="k"> 	<input type="Submit" value="Login">
			
		</form>
	<?php
	} else {
	?>
		<form method="post" action="device_lx.php?s=<?php echo $mac; ?>">
			<input type="hidden" value="" name="k"> 
			<input type="Submit" value="Logout">
		</form>
	<?php
	}

	$mtrun = round((microtime(true) - $mtmain_t0) * 1000, 4);
	echo "<small>(Runtime: $mtrun msec)</small></p></body>";
	?>
</html>