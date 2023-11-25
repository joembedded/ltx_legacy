<?php
// FILE: lxu_loglib.php  - Version:04.12.2022
// include-module logfile only for LXU modules (path on 1.st level)!

// ---- basic directory service ---
function check_dirs($wcmd = true)
{
	global $xlog, $dbg, $mac, $dapikey;
	$sdata = S_DATA;

	$newdev = false;
	// Data and LOG always required
	if (!file_exists($sdata)) mkdir($sdata);  // MainDirectory
	if (!file_exists($sdata . "/log")) mkdir($sdata . "/log");  // Logfiles
	if(!isset($mac) || strlen($mac)!==16) return;	// No MAC
	if($dapikey === false) return -1; // dir must exist
	if (!file_exists($sdata . "/$mac")) {
		mkdir($sdata . "/$mac");
		file_put_contents($sdata . "/$mac/date0.dat", time()); // Note initial date
		file_put_contents($sdata . "/$mac/quota_days.dat", DB_QUOTA); 
		file_put_contents($sdata . "/$mac/dapikey.dat", $dapikey); 
		$newdev = $wcmd;
	}
	if (!file_exists($sdata . "/$mac/cmd")) {
		mkdir($sdata . "/$mac/cmd");
		// Test to get server.lxp
		if ($newdev == true) {
			file_put_contents($sdata . "/$mac/cmd/getdir.cmd", "123");	// 3 Tries to get Directoy
		}
	}
	if (!file_exists($sdata . "/$mac/files")) {	// File mirror
		mkdir($sdata . "/$mac/files");
	}
	if (!file_exists($sdata . "/$mac/get")) {	// List of Files to upload
		mkdir($sdata . "/$mac/get");
		// Test to get server.lxp
		if ($newdev == true) {
			file_put_contents($sdata . "/$mac/get/sys_param.lxp", "123");	// 3 Tries to get sys_param.lxp
		}
	}
	if (!file_exists($sdata . "/$mac/put")) {	// List of Files to download
		mkdir($sdata . "/$mac/put");
	}
	if (!file_exists($sdata . "/$mac/del")) {	// List of Files to delete
		mkdir($sdata . "/$mac/del");
	}
	if (!file_exists($sdata . "/$mac/in_new")) {	// New incomming
		mkdir($sdata . "/$mac/in_new");
	}
	if ($dbg && !file_exists($sdata . "/$mac/dbg")) {
		mkdir($sdata . "/$mac/dbg");
	}
	return 0;	// OK
}

// ----- Quick Exit on errors -----
function exit_error($err)
{
	global $xlog;
	echo "ERROR: '$err'\n";
	$xlog .= "(ERROR:'$err')";
	add_logfile();
	exit();
}

// ------ Write LogFile (carefully) -------- (similar to lxu_xxx.php)
function add_logfile()
{
	global $xlog, $dbg, $mac, $now;

	$sdata = S_DATA;
	$logpath = $sdata . "/log/";
	if (@filesize($logpath . "log.txt") > 100000) {	// Main LOG
		@unlink($logpath . "_log_old.txt");
		rename($logpath . "log.txt", $logpath . "_log_old.txt");
		$xlog .= " (Main 'log.txt' -> '_log_old.txt')";
	}

	if(!isset($mac)) $mac="UNKNOWN_MAC";
	if ($dbg) $xlog .= "(DBG:$dbg)";

	$log = @fopen($sdata . "/log/log.txt", 'a');
	if ($log) {
		while (!flock($log, LOCK_EX)) usleep(10000);  // Lock File - Is a MUST
		fputs($log, gmdate("d.m.y H:i:s ", $now) . "UTC " . $_SERVER['REMOTE_ADDR'] );        // Write file
		if (strlen($mac)) fputs($log, " MAC:$mac"); // mac only for global lock
		fputs($log, " $xlog\n");        // evt. add extras
		flock($log, LOCK_UN);
		fclose($log);
	}
	// User Logfile - Text
	if (strlen($mac) == 16 && file_exists($sdata . "/$mac")) {
		$logpath = $sdata . "/$mac/";
		if (@filesize($logpath . "log.txt") > 50000) {	// Device LOG
			@unlink($logpath . "_log_old.txt");
			rename($logpath . "log.txt", $logpath . "_log_old.txt");

			if (@filesize($logpath . "userio.txt") > 50000) {	// User Commands
				@unlink($logpath . "_userio_old.txt");
				rename($logpath . "userio.txt", $logpath . "_userio_old.txt");
			}
			if (@filesize($logpath . "conn_log.txt") > 50000) {	// Connection Log
				@unlink($logpath . "_conn_log_old.txt");
				rename($logpath . "conn_log.txt", $logpath . "_conn_log_old.txt");
			}
		}

		$log = fopen($logpath . "log.txt", 'a');
		if (!$log) return;
		while (!flock($log, LOCK_EX)) usleep(10000);  // Lock File - Is a MUST
		//fputs($log,gmdate("d.m.y H:i:s ",$now)."UTC ".$_SERVER['REMOTE_ADDR']]);
		fputs($log, gmdate("d.m.y H:i:s ", $now) . "UTC");
		fputs($log, " $xlog\n");        // evt. add extras
		flock($log, LOCK_UN);
		fclose($log);
	}
}
