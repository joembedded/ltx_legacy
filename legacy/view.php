<?PHP
// -------------------------------------------------------------------
// view.php - Show data (filtered)
// 01.3.2020 j.wickenh

error_reporting(E_ALL);
include("../sw/conf/api_key.inc.php");

// ---------------------------- M A I N --------------
header('Content-Type: text/plain');
$mac = $_GET["s"];
$fname = $_GET["f"];

$rfile = S_DATA . "/$mac/$fname";

$ext = substr($fname, strpos($fname, '.'));
if (!strcasecmp($ext, ".php")) {
	header('Content-Type: text/plain');
	echo "<ERROR: Not allowed Format>\n"; // Filter illegal formats (Never display contents of PHP)
} else if (strpos("$mac/$fname", "..") || !file_exists($rfile)) { // prevent upper dirs
	header('Content-Type: text/plain');
	echo "<ERROR: File not found: '$fname' (maybe too old?)>\n";
} else {
	$data = file_get_contents($rfile);
	$len = strlen($data);
	if (!strcasecmp($ext, ".jpg") || !strcasecmp($ext, ".jpeg")) {
		// Auto-header
	} else {
		header('Content-Type: text/plain');
		echo "------------------------- '$mac/$fname': $len Bytes: ---------------------------\n";
	}
	echo $data;
}
