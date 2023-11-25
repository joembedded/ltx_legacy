<?php
	// Optionally redirect to secure site with same script - No Output allowed!
	$script=$_SERVER['PHP_SELF'];	// /xxx.php
	$server=$_SERVER['HTTP_HOST'];  // Immer klein
	if(!isset($_SERVER['HTTPS']) && strcmp($server,'localhost')) { // ppt. Redirect
		$url="https://".$server;	// HTTPS on Std. Port
		$url.=$script;
		header("Location: $url");
		echo "Redirect to '$url'...";
		exit();
	}
?>
<!DOCTYPE HTML>
<html>
<head><title>Media-Browser</title></head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="privacy" content="This page is not using Cookies or collecting private data">
	<link rel="stylesheet" href="w3.css">
<body>

<div class="w3-container">
<?php
	// A small file browser for a given subdirectory
	// (C)2020-2023 JoEmbedded.de - save as "index.php" (see '$me')
	error_reporting(E_ALL);

	$me="index.php";
	// --- Convert to Timestring
	function  secs2period($now,$ftime){
		$secs=$now-$ftime;
		if($secs>86400) return gmdate('d. M Y',$ftime);
		return "Age: ".gmdate('H\h\r i\m\i\n s\s\e\c',$secs);
	}


	// --- Write a Logfile ---
	function addlog($xlog){
        $logpath="./";
		if(@filesize($logpath."log.log")>1000000){	// Main LOG
				unlink($logpath."_log_old.log");
				rename($logpath."log.log",$logpath."_log_old.log");
				$xlog.=" ('log.log' -> '_log_old.log')";
		}

		$log=@fopen($logpath."log.log",'a');
		if($log){                
			while(!flock($log,LOCK_EX)) usleep(10000);  // Lock File - Is a MUST
			fputs($log,gmdate("d.m.y H:i:s ",time()).$_SERVER['REMOTE_ADDR']);        // Write file
			fputs($log," $xlog\n");        // evt. add extras
			flock($log,LOCK_UN);
			fclose($log);
        }
	}
	
	//---- MAIN -----
	$now=time();
	$dir=@$_GET['dir'];

	if(!isset($dir) || !strlen($dir) || strlen($dir)>128 ) $dir=".";

	addlog($dir);	// What is displayed?
	
	echo "<div class='w3-panel w3-indigo'><h3><img style=\"vertical-align:middle\" src=\"MediaIcon.ico\">&nbsp;&nbsp;&nbsp;<b>Media-Browser - Directory '$dir'</b></h3></div>";
	
	echo "<ul class='w3-ul w3-leftbar w3-border-green w3-hoverable w3-light-gray'>";
	echo "<li><big>&#127968;</big>  <a href=\"index.php\">Home</a><br></li>";
	echo "</ul><br>";
	
	// --- Test if in allowed range, never higher than script itself! ---
	$minpath=realpath(".");
	$actpath=@realpath($dir);
	//echo "minpath: '$minpath', actpath: '$actpath'<br>";
	if(strncmp($actpath,$minpath,strlen($minpath))){
			echo "<p><b>Invalid path!</b></p>";
			$dir="";	// -> Gen. Error: Directory not found
	}
	// --- Test End ---

	$anz=0;
	if(file_exists($dir)){

		$list=scandir($dir);
		if(count($list)){
			echo "<ul class='w3-ul w3-leftbar w3-border-orange  w3-hoverable w3-light-gray'>";
			// Directories first
			$dircnt=0;
			foreach($list as $file){
				if($file=='.') continue;
				if($file=='..'){
					if(strlen($dir)){
						$p=strrpos($dir,'/');
						if($p>0){
							$up=substr($dir,0,$p);
							echo "<li><big>&#11014;</big> <a href=\"$me?dir=$up\"><small>(Directory up)</small> </a></li>";
						}
					}
					continue;
				}
				if(is_dir("$dir/$file")){
						echo "<li><big>&#128193;</big> <a href=\"$me?dir=$dir/$file\">/$file</a></li>";
						$dircnt++;
				}
			}
			if($dircnt) echo "&nbsp;<small>($dircnt Directories)</small><br></ul><br>";
			else echo "</ul><br>";
		}

		echo "<ul class='w3-ul w3-leftbar w3-border-blue w3-hoverable w3-light-gray'>";
		$dstot=0;
		// Then files
		foreach($list as $file){
			if($file=='.'||$file=='..') continue;

			// don't show PHP and HTML and CSS
			$sym="&#128190;";
			if(stripos($file,'.php')) continue;
			if(stripos($file,'.html')) continue;
			if(stripos($file,'.css')) continue;
			if(stripos($file,'.log')) continue;
			if(stripos($file,'.js')) continue;
			if(stripos($file,'.ico')) continue; // Icon erzeugen: z.B. https://favicon.io/
			
			// Source: z.B. https://emojiguide.org  und https://unicode.org/emoji/charts
			if(stripos($file,'.txt')) $sym="&#128203;";
			else if(stripos($file,'.sec')) $sym="&#128271;";
			else if(stripos($file,'.pdf')) $sym="&#128209;";
			else if(stripos($file,'.mp4') || stripos($file,'.mov') ) $sym="&#127910;";


			if(is_dir("$dir/$file")) continue;

			$ds=filesize("$dir/$file");
			$dstot+=$ds;
			$fa=secs2period($now,filemtime("$dir/$file"));
			echo "<li><big>$sym</big> <a href=\"$dir/$file\">$file</a> <small><i> &nbsp;&nbsp;&nbsp;($ds Bytes, $fa)</i></small></li>";
			$anz++;
		}
		if($anz) echo "&nbsp;<small>($anz Files, total: $dstot Bytes)</small><br>";
		else echo "&nbsp;<small>(No files)</small><br>";
		echo "</ul><br>";


	}else{
		echo "<p><b>ERROR: Directory not found!</b></p>";
	}
?>
</div>
</body>
</html>
