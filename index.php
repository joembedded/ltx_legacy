<?php
	/* Redirect to a different page in the current directory that was requested */
	$host  = $_SERVER['HTTP_HOST'];
	$uri   = $_SERVER['PHP_SELF'];
	if(!isset($uri) || strlen($uri)<4) exit("ERROR: 'PHP_SELF' not set");
	$extra = substr($uri,0,strrpos($uri,'/')).'/legacy/index.php';
	$nloc = "Location: //$host$extra";
	header($nloc);	// PHP-Redirection
	//exit;	(if redirection does not work, continue) // 
?>
<!DOCTYPE HTML>
<html>
 <head>
	<title>Login...</title><meta name=\"robots\" content=\"noindex,nofollow\">
 </head>
 <body>
	 <?php echo "<a href='$extra'>Login: ' $extra; '...</a>"; ?>
 </body>
</html>


