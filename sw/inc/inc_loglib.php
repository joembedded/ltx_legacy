<?php
	// FILE: inc_loglib.php  - Version:23.05.2019
	// include-module logfile and helpers for 2.nd level)
	// Logs only to MAC
	
	// ----- Quick Exit on errors -----
	function exit_error($err){
		global $xlog;
		echo "ERROR: '$err'\n";
		$xlog.="(ERROR:'$err')";
		add_logfile();
		exit();
	}

    // ------ Write LOCAL ONLY  LogFile (carefully) -------- (similar to lxu_xxx.php)
    function add_logfile(){
		global $xlog, $dbg, $mac, $now;

		$sdata="../".S_DATA;
        $logpath=$sdata."/$mac/";
		if(@filesize($logpath."log.txt")>50000){	// Device LOG
				@unlink($logpath."_log_old.txt");
				rename($logpath."log.txt",$logpath."_log_old.txt");
				//$xlog.=" (Device 'log.txt' -> '_log_old.txt')";
		}

		if($dbg) $xlog.="(DBG:$dbg)";

        // User Logfile - Text
        if(strlen($mac)==16 && file_exists($sdata."/$mac")){
			$log=fopen($sdata."/$mac/log.txt",'a');
			if(!$log) return;
			while(!flock($log,LOCK_EX)) usleep(10000);  // Lock File - Is a MUST
			//fputs($log,gmdate("d.m.y H:i:s ",$now)."UTC ".$_SERVER['REMOTE_ADDR'].' '.$_SERVER['PHP_SELF']);
			fputs($log,gmdate("d.m.y H:i:s ",$now)."UTC");
			fputs($log," $xlog\n");        // evt. add extras
			flock($log,LOCK_UN);
			fclose($log);
		}
	}
?>