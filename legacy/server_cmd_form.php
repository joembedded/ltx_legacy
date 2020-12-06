<!DOCTYPE HTML>
<html>

<head>
  <title>Legacy LTrax Set Server Command</title>
</head>

<body>
  <?php
  error_reporting(E_ALL);
  // ----------- M A I N ---------------
  $mac = @$_GET['s'];           // exactly 16 Zeichen. api_key and mac identify device
  echo "<p><b><big>Legacy LTrax Set Server Command </big></b><br></p>";
  echo "<p><a href=\"index.php\">LTrax Home</a><br>";
  echo "<a href=\"device_lx.php?s=$mac&show=a\">Device View $mac (All)</a><br></p>";

  echo "<p>Command ('0'..'255') (Device $mac):</p>";
  echo "<form name=\"server_cmd_form\" enctype=\"multipart/form-data\" action=\"./server_cmd_upload.php?s=$mac\" method=\"post\">";
  ?>
  <label for="server_cmd"> Command:</label>
  <input type="text" name="server_cmd" id="server_cmd" maxlength="3">
  <input type="Submit" name="UP">
  </form>
</body>

</html>