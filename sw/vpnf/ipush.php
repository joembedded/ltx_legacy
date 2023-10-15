<?php
/* ipush.php - internal immerdiate push 
	http://localhost/ltx/sw/vpnf/ipush.php?s=DDC2FB99207A7E7E&k=S_API_KEY
	http://localhost/ltx/sw/w_php/w_pcp.php?s=DDC2FB99207A7E7E&k=S_API_KEY&cmd=getdata&minid=80

	ConfCmd: PROTOCOL FORMAT STATIONID  URL PORT USER PW 
    Bsp:     FTPSSL CSV1 Bach123 s246.goserver.host 21 web28f3 qfile57

	Protocol:
		FTP: unencrypted FTP (normally Port 21)
		FTPSSL FTP with implicit encryption (normally Port 21)

	Format: 
		CSV0: All lines as CSV (including '<>' meta lines)
		CSV1: Only data lines as CSV

	StationId: 
		String,1-8 characters, used as filename-prefix for upload 
		(e.g. 'Bach123' writes files 'Bach123_20231015181223.txt')
	
	URL / PORT / USER: FTP credentials
*/

error_reporting(E_ALL);
ini_set("display_errors", true);
include("../conf/api_key.inc.php");
include("../conf/config.inc.php");	// DB Access Param
include("../inc/db_funcs.inc.php"); // Init DB

set_time_limit(600); // 10 Min runtime

define('VERSION', "V0.10 10.10.2023");

// --- Functons --------
function exit_error($err)
{
	global $xlog;
	echo "ERROR: '$err'\n";
	$xlog .= "(ERROR:'$err')";
	add_logfile();
	exit();
}

function add_logfile()
{ // Nur globales log!
	global $xlog, $dbg,  $now;

	$sdata = "../" . S_DATA;
	$logpath = $sdata . "/log/";
	if (@filesize($logpath . "log.txt") > 100000) {	// Main LOG
		@unlink($logpath . "_log_old.txt");
		rename($logpath . "log.txt", $logpath . "_log_old.txt");
		$xlog .= " (Main 'log.txt' -> '_log_old.txt')";
	}

	if ($dbg) $xlog .= "(DBG:$dbg)";

	$log = @fopen($sdata . "/log/log.txt", 'a');
	if ($log) {
		while (!flock($log, LOCK_EX)) usleep(10000);  // Lock File - Is a MUST
		fputs($log, gmdate("d.m.y H:i:s ", $now) . "UTC " . $_SERVER['REMOTE_ADDR'] . ' ' . $_SERVER['PHP_SELF']);        // Write file
		fputs($log, " $xlog\n");        // evt. add extras
		flock($log, LOCK_UN);
		fclose($log);
	}
}

// Transferiert lokales File auf FTP mit Namensaenderung
function transfer_ftp($prot, $local_filename, $remote_filename, $ftp_server, $ssl_flag, $ftp_port, $ftp_user_name, $ftp_user_pass)
{
	global $xlog, $dpath;
	$transfer_modus = FTP_BINARY; // Der Transfer-Modus muss entweder FTP_ASCII oder FTP_BINARY sein.
	if ($ssl_flag) $conn_id = ftp_ssl_connect($ftp_server, $ftp_port);
	else $conn_id = ftp_connect($ftp_server, $ftp_port);
	if ($conn_id == false) {
		file_put_contents("$dpath/cmd/okreply.cmd", "$prot:Connection Error");
		exit_error("Connection to '$ftp_server' failed");
	}
	if (ftp_login($conn_id, $ftp_user_name, $ftp_user_pass) == false) {
		file_put_contents("$dpath/cmd/okreply.cmd", "$prot:Login Error");
		exit_error("Login '$ftp_user_name' failed");
	}
	ftp_pasv($conn_id, true); // Passiven Modus wg. Firewall besser
	// Optional z.B. ftp_chdir($conn_id, $server_path)..
	$loc_filehandle = @fopen($local_filename, "r");
	if ($loc_filehandle == false) {
		file_put_contents("$dpath/cmd/okreply.cmd", "$prot:Read Error");
		exit_error("File '$local_filename' not found");
	}
	$putfilesize = filesize($local_filename);
	$remote_dir = ftp_pwd($conn_id);
	if (ftp_fput($conn_id, $remote_filename, $loc_filehandle, $transfer_modus) == false) {
		file_put_contents("$dpath/cmd/okreply.cmd", "$prot:Put Error");
		exit_error("Put '$remote_dir$remote_filename' failed");
	}
	fclose($loc_filehandle);
	ftp_close($conn_id);
	$xlog .= "($prot:Put '$local_filename' as '$remote_dir$remote_filename', $putfilesize Bytes)";
}


function get_pcp($xcmd) // xcmd ohne cmd, aber Parameter URL codiert, e.g. iparam&minid=123
{
	global $mac;
	$script = $_SERVER['PHP_SELF'];	// /xxx.php
	$lp = strpos($script, "sw"); // Path
	$sroot = substr($script, 0, $lp - 1);
	if (HTTPS_SERVER != null) $sec = "https://" . HTTPS_SERVER;
	else $sec = "http://" . $_SERVER['HTTP_HOST'];
	$sqs = $sec . $sroot . "/sw/w_php/w_pcp.php?k=" . S_API_KEY . "&s=$mac&cmd=$xcmd";
	$ch = curl_init($sqs);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec($ch);
	if (curl_errno($ch)) exit_error("CurlErrno'" . curl_error($ch) . "'");
	curl_close($ch);
	$obj = @json_decode($result);
	if (!isset($obj->status) || strcmp(substr($obj->status, 0, 4), "0 OK")) exit_error("CurlResult:'" . trim($result) . "'");
	return $obj;
}

//------------- MAIN ---------------
header("Content-Type: text/plain; charset=UTF-8");

$dbg = 0;
$xlog = "";
$tzo = timezone_open('UTC'); 		// Oder LOKAL - Wie ists mit Sommer-Winterzeit?
$now = time();						// one timestamp for complete run

$mtmain_t0 = microtime(true);         // for Benchmark 

$mac = @$_REQUEST['s'];
if (!isset($mac) || strlen($mac) != 16) exit_error("MAC Len");
$api_key = @$_GET['k'];				// max. 41 Chars KEY

$dpath = "../" . S_DATA . "/$mac";	// Device Path
if (@file_exists("$dpath/cmd/dbg.cmd")) {
	if (!$dbg) $dbg = 1;
}

// Check Key before loading data
if (!$dbg && (!isset($api_key) || strcmp($api_key, S_API_KEY))) {
	exit_error("API Key");
}
if ($dbg) $xlog .= $_SERVER['REQUEST_URI'] . ' ';

if ($dbg) echo "*** ipush.php " . VERSION . " ***\n";

// --- START ---
$tempfile  = '../' . S_DATA . "/stemp/$mac.ftp"; // unique_string - working file
$ipar_obj = get_pcp("iparam"); // No Return on Error
if ($ipar_obj->iparam_meta->chan0_idx < 20) exit_error("No ConfigCommand in iparam");
$okreply = "OK";
$configCmd = trim($ipar_obj->iparam[19]->line);
$pdevi = @file($dpath . "/ppinfo.dat", FILE_IGNORE_NEW_LINES);
$minid = intval(@$pdevi[0]);

if ($dbg) echo "ConfigCmd: '$configCmd' minid:$minid\n";

$prot = strtok($configCmd, " ");
if ($prot !== false) {
	if ($prot !== "FTP" && $prot !== "FTPSSL") {
		file_put_contents("$dpath/cmd/okreply.cmd","Error:Unkn.Protocol");
		exit_error("Unkn.Protocol");
	}
	$format = strtok(" ");
	if($format !== "CSV0" && $format !== "CSV1") {
		file_put_contents("$dpath/cmd/okreply.cmd","Error:Unkn.Format");
		exit_error("Unkn.Format");
	}
	$station = strtok(" ");
	$fhost = strtok(" ");
	$fport = intval(strtok(" "));
	$fuser = strtok(" ");
	$fpassword = strtok(" ");

	//echo "Protocol: '$prot' Format: '$format' Station: '$station' Host:$fhost:$fport $fuser/$fpassword\n";
	$fdata = get_pcp("getdata&minid=$minid");


	$funits = explode(' ', $fdata->overview->units);
	$fuarr = array();
	$frema = array();	// Reverse search

	$xhdr = "TIME(UTC)";
	foreach ($funits as $fkuv) {
		$fka = @explode(':', $fkuv);
		$kan = intval(@$fka[0]);
		$val = @$fka[1];
		$fuarr[$kan] = $val;
		$frema[] = $kan;
		$xhdr .= ";$val($kan)";
	}
	$xlines = array($xhdr."\n");	// Exportierte Daten

	$danz = $fdata->get_count; // evt. $danz limitieren, Index startet mit 1
	$minid = min($minid + $danz, $ipar_obj->overview->max_id+1) 

	for ($i = 0; $i < $danz; $i++) {
		$typ = $fdata->get_data[$i]->type;
		$lcont = $fdata->get_data[$i]->line;
		if ($typ == 'msg' && @$lcont[0] == '<' && $format == "CSV0") {
			$xline = $fdata->get_data[$i]->calc_ts . ";" . $lcont;
			$xlines[] = $xline."\n";
		} else if ($typ == 'val') {
			$xline = $fdata->get_data[$i]->calc_ts;
			$larr = explode(' ', $lcont);
			$pox = 0;
			foreach ($larr as $lcuv) {
				$kerw = $frema[$pox++]; // Erwarteter Kanal hier
				$lka = @explode(':', $lcuv);
				$kist = intval(@$lka[0]); // Was ist (evtl. schon weiter)
				while ($kerw < $kist && $pox < 100) { // Sicherheitsgrenze
					$xline .= ";";
					$kerw = $frema[$pox++];
				}
				$val = @$lka[1];
				$xline .= ";$val";
			}
			$xlines[] = $xline."\n";
		}
	}
	$xlog .= "($danz Data Lines)";
	//print_r($xlines);
	file_put_contents($tempfile, $xlines); // Fkt OK for array

	$sslflag = ($prot == "FTPSSL");
	$sfile = $station."_".date("YmsHis").".dat";
	transfer_ftp($prot,$tempfile, $sfile,$fhost,$sslflag,$fport,$fuser,$fpassword);
	@unlink($tempfile);
	$okreply = "$prot:OK";
}else{
	$minid = $ipar_obj->overview->max_id+1;	// Ignore
}

file_put_contents("$dpath/cmd/okreply.cmd", $okreply); // Hat funktioniert
file_put_contents($dpath . "/ppinfo.dat", $minid);

// --- END ---
$mtrun = round((microtime(true) - $mtmain_t0) * 1000, 4);
$xlog .= "(Run:$mtrun msec)"; // Script Runtime

echo "*IPUSH(DBG:$dbg) RES: ('$xlog')*\n"; // Always
add_logfile($xlog); // Regular exit, entry in logfile should be first
//