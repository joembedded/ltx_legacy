<?php
/*
 * fw_upload.php
 * 
 * Dieses Script dient dazu, eine neue Firmware-Datei für ein Gerät hochzuladen und verschlüsselt als '_firmware.sec' zu speichern.
 * 
 * Funktionsweise:
 * - Zugriffsschutz: Nur mit gültigem API-Key möglich.
 * - Die Firmware-Datei kann entweder per POST (Dateiupload) oder über einen lokalen Dateipfad (Parameter 'lfname') bereitgestellt werden.
 * - Es werden ausschließlich verschlüsselte .SEC-Dateien akzeptiert (seit Firmware 1.0).
 * - Die MAC-Adresse des Zielgeräts muss als Parameter übergeben werden.
 * - Die hochgeladene Datei wird auf Größe und Format geprüft.
 * - Die Datei wird in das entsprechende Geräteverzeichnis gespeichert.
 * - Metadaten zur Firmware werden in einer separaten Datei abgelegt.
 * - Alle Aktionen werden protokolliert.
 * 
 * Sicherheit:
 * - Der Factory-Key wird als PHP-Definition gespeichert, um externen Zugriff zu verhindern.
 * - Zugriff ist nur mit gültigem API-Key möglich.
 * 
 * Hinweise:
 * - Das Script erwartet, dass die Verzeichnisstruktur und die notwendigen Schlüsseldateien vorhanden sind.
 * - Fehlerhafte oder unvollständige Uploads werden mit einer Fehlermeldung abgebrochen.
 * 
 * V1.3 - 27.06.2022 (C) JoEmbedded
 */

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
if (!$dev) {
	echo "ERROR: Access denied!";
	exit();
}
//---------------- Functions ---------------


//---------------- MAIN ---------------
header('Content-Type: text/plain');

$dbg = 0;
$mac = @$_GET['s']; // 16 Chars, Uppercase
$now = time(); // Sec ab 1.1.1970, GMT

$lfname = @$_GET['lfname'];	// Opionally File from File System (with Spaces) (wg. BULK)
if (!empty($lfname)) {
	$fname = S_DATA . "/stemp/$lfname";        // File is name on Disk (in stemp)
	$freal_name = @$_GET['lreal']; // Optionally REAL name
	if (empty($freal_name)) $freal_name = $fname;
	$fsize = filesize($fname);
} else {
	$fname = @$_FILES['X']['tmp_name'];        // File is (temp) name on Disk (Temp.-Name)
	$freal_name = @$_FILES['X']['name'];        // Real Name
	$fsize = @$_FILES['X']['size'];
}


if (!isset($mac) || strlen($mac) != 16) {
	exit_error("MAC Len");
}
// Never generate Updates for unexisting Loggers...
if (!file_exists(S_DATA . "/$mac") || check_dirs()) exit_error("Error (Directory/MAC not found)");
if (empty($fname)) exit_error("No Data (Need Firmware File)");

echo "Check File '$freal_name'...\n";
if ($fsize < 10000 || $fsize > 1000000) {	// firmware <10k impossible, >1M ?!
	exit_error("No Firmware File '$freal_name' Size:$fsize Bytes");
}
$xlog = "(Firmware:'$freal_name' Size:$fsize)";

if ($dbg) echo "Loaded File:'$freal_name' Size:$fsize\n";

$fw_bin = file_get_contents($fname);

$dpath = S_DATA . "/$mac/cmd/";

if(substr($freal_name,-4) === '.sec' ){
	if((strlen($fw_bin)%16)){
		exit_error("File is either no firmware file or corrupt! (SIZE)");
	}
	$fw_sec = $fw_bin;
}

$of = fopen($dpath . '_firmware.sec', 'wb');
fwrite($of, $fw_sec);
fclose($of);

$of = fopen($dpath . '_firmware.sec.umeta', 'w');	// Keep some (u)meta data
fwrite($of, "fname_original\t$freal_name\n");		// Only for info
fwrite($of, "sent\t0\n");					// Count Number of transmits
fclose($of);
// Add to protocol

echo "OK (Back with Browser '<-')\n";
add_logfile(); // Regular exit
