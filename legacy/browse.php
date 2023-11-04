<?php
// A small file browser for a given subdirectory for the
// incomming LTX data...
// Show Header in RED for filebrowser
// (C) JoEmbedded.de
//
// V1.10: Tested PHP8
error_reporting(E_ALL);

include("../sw/conf/api_key.inc.php");
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
?>
<!DOCTYPE HTML>
<html>

<head>
	<title>Legacy LTX Browser V1.10 </title>
</head>

<body>
	<?php
	if (!$dev) {
		echo "ERROR: Access denied!";
		exit();
	}

	// --- Convert to Timestring
	function  secs2period($secs)
	{
		if ($secs >= 86400) return "over " . floor($secs / 3600) . "h";
		return gmdate('H\h\r i\m\i\n s\s\e\c', $secs);
	}

	//---- MAIN -----
	$now = time();
	$dir = @$_GET['dir'];

	// echo "MIN: $minpath<br>";
	if (!$dir) $dir = S_DATA; 	// Default Dir is DATA

	$anz = 0;
	echo "<p><b><big><font color=\"red\">Legacy LTX Browser V1.10 - Directory '$dir'</font></big></b><br></p>";
	echo "<p><a href=\"index.php\">Legacy LTX Home</a><br></p>";

	// --- Test if in allowed range ---
	$minpath = substr(__DIR__, 0, strrpos(__DIR__, '/'));	// __DIR__ global Instllationi path
	$actpath = @realpath($dir);
	// echo "minpath: '$minpath', actpath: '$actpath'<br>";
	if (strncmp($actpath, $minpath, strlen($minpath))) {
		echo "<p><b>Invalid path!</b></p>";
		$dir = "";	// -> Gen. Error: Directory not found
	}
	// --- Test End ---
	if (strcmp($dir, S_DATA)) {	// Normally not higher than data...
		$p = strrpos($dir, '/');
		if ($p > 1) {
			$up = substr($dir, 0, $p);
			echo "<p><a href=\"browse.php?dir=$up\">[ . . ]</a><br></p>";
		}
	} // else don't show up

	if (file_exists($dir)) {
		$list = scandir($dir);
		// Directories first
		$dircnt = 0;
		foreach ($list as $file) {
			if ($file == '.' || $file == '..') continue;
			if (is_dir("$dir/$file")) {
				echo "<a href=\"browse.php?dir=$dir/$file\">[$file]</a><br>";
				$dircnt++;
			}
		}
		if ($dircnt) echo "($dircnt Directories)<br>";
		echo "</p><p>";

		$dstot = 0;
		// Then files
		foreach ($list as $file) {
			if ($file == '.' || $file == '..') continue;
			if (is_dir("$dir/$file")) continue;
			$ds = filesize("$dir/$file");
			$dstot += $ds;
			$fa = secs2period($now - filemtime("$dir/$file"));
			echo "<a href=\"$dir/$file\">$file</a> ($ds Bytes, Age: $fa)<br>";
			$anz++;
		}
		if ($anz) echo "($anz Files, total: $dstot Bytes)<br>";
		else echo "(No files)<br>";
		echo "</p>";
	} else {
		echo "<p><b>ERROR: Directory not found!</b></p>";
	}
	?>
</body>

</html>