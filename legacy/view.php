<?PHP
// -------------------------------------------------------------------
// view.php - Show data (filtered and source directory hidden)
// 29.06.2022 j.wickenh

error_reporting(E_ALL);
include("../sw/conf/api_key.inc.php");

// ---------------------------- M A I N --------------
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
		header('Content-Type: image/jpeg');
	}else if(!strcasecmp($ext, ".png")){
		header("Content-type: image/png");		
	} else {
		header('Content-Type: text/plain');
		echo "\xEF\xBB\xBF";	// UTF8-Byte-Order-Mark
		echo "------------------------- '$mac/$fname': $len Bytes: ---------------------------\n";
	}
	echo $data;
}
