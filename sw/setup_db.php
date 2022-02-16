<?php
// --- setup_db.php - Database Setup 02.01.2021 (C) joembedded.de ---

error_reporting(E_ALL);

include("conf/api_key.inc.php");
include("conf/config.inc.php");	// DB Access Param
include("inc/db_funcs.inc.php");	// DB Access Param
include("lxu_loglib.php");


// --------- MAIN ------------
echo "================================<br>";
echo "LTX Database Setup...<br>";
echo "================================<br>";

$now = time();						// one timestamp for complete run
$xlog= "";
check_dirs();
db_init();

$statement = $pdo->prepare("SHOW TABLES");
$qres = $statement->execute();
if($qres!== false){	// !false: Result of Operation OK
	$cnt=0;
	for(;;){
		$table = $statement->fetch(); // If !false: Result available
		if($table === false) break;
		$cnt++;
		//echo "Table: '".$table."'<br>";
	}
	if($cnt){
		echo "Found $cnt tables!<br>";
		echo "================================<br>";
		echo "FATAL ERROR: Database NOT Empty!<br>";
		echo "================================<br>";
		$xlog= "(FATAL ERROR: Database NOT Empty!)";		
		echo "<a href='login.php'>Login to LTX...</a><br>";
		add_logfile();
		exit();
	}
}

echo "Connection to Database 'mysql:host='". DB_HOST."';dbname='".DB_NAME."';charset=utf8' OK<br>";

/* Just as INFO
echo "Database User: '".DB_USER."'<br>";
echo "Database Password: '".DB_PASSWORD."'<br>";
*/

$init_sql = file_get_contents("docu/database.sql");

$statement = $pdo->prepare($init_sql);
$qres = $statement->execute();
if ($qres !== false) {
	$xlog.= "(Create Initial Tables OK)";
	echo "Create Initial Tables OK<br>";
	
	// Create ADMIN : Same User/PW as Database!
	$admin_un = DB_USER;
	$admin_pw = DB_PASSWORD;
	while(strlen($admin_un) <= 6) $admin_un.= $admin_un;
	if(strlen($admin_pw) <= 6) $admin_pw = $admin_un;

	echo "<br><br>Create ADMIN:<br>";
	echo "ADMIN User: '".$admin_un."'<br>";
	echo "ADMIN User: '".$admin_pw."'<br>";
	echo "ADMIN Servicemail: '".SERVICEMAIL."'<br>";

	
  	$statement = $pdo->prepare("INSERT INTO users (name, email, password, confirmed, rem, ticket, user_role) VALUES ( ? , ?, ? , ?, ?, ?, ?)");
  	$psw_enc=simple_crypt($admin_pw,0);	// use encrypted PW ind DB
  	$qres=$statement->execute(array($admin_un,SERVICEMAIL,$psw_enc, 1, 1, "(ADMIN)", 65535+65536));	// Full ROLE (2e16)
  	$anz=$statement->rowCount(); // No of matches
  	if($anz!=1){
		$xlog.= "(ERROR: Failed to Create ADMIN)";
		echo "ERROR: Failed to Create ADMIN!!!<br>";
  	}else{
		echo "======================<br>";
		echo "LTX Database Setup OK!<br>";
		echo "======================<br>";
	}
}else{
	$xlog.= "(ERROR: Failed to Create Initial Tables)";
	echo "ERROR: Failed to Create Initial Tables!!!<br>";
}
echo "<a href='login.php'>Login to LTX...</a><br>";
add_logfile();
?>