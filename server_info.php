<!DOCTYPE HTML>
<html>
 <head>
  <title>Server Info</title>
 </head>
 <body>
 <pre>
 --- Server-Info: ----<br>
 <?PHP
	  // Aufrufen /server_info.php?k=geheim1609
	  $pw = @$_REQUEST['k'];
	  $self = $_SERVER['PHP_SELF'];
      if($pw === 'geheim1609') {
		  print_r($_SERVER);
	  }else{
		  echo "Hello from '$self'";
	  }
 ?>
 </pre>
 </body>
</html>
