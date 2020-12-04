<!DOCTYPE HTML>
<html>
  <head>
    <title>LTrax Set User Command</title>
  </head>
  <body>
<?php
	error_reporting(E_ALL);
	// ----------- M A I N ---------------
    $mac=@$_GET['s']; 					// exactly 16 Zeichen. api_key and mac identify device
	echo "<p><b><big> LTrax Set User Command </big></b><br></p>";
	echo "<p><a href=\"index.php\">LTrax Home</a><br>";
	echo "<a href=\"device_lx.php?s=$mac&show=a\">Device View $mac (All)</a><br></p>";
	   
    echo "<p>Enter User Command (max. 245 Chars) (Device $mac):</p>";
    echo "<form name=\"user_cmd_form\" enctype=\"multipart/form-data\" action=\"./user_cmd_upload.php?s=$mac\" method=\"post\">";
?>
   <label for="user_cmd"> Command:</label>
     <input type="text" name="user_cmd" id="user_cmd" maxlength="245">   
	 <input type="Submit" name="UP">
    </form>
  </body>
</html>