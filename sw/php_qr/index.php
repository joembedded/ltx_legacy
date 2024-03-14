<!DOCTYPE html>
<!-- Template -->
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
   
    <title>QR Text Generator</title>
</head>
<body> <!-- BODY START -->

<?php
	// Test: http://localhost/ltx/sw/php_qr/
	// Test: https://joembedded.de/ltx/sw/php_qr/

	$txt = urldecode(@$_REQUEST['txt']);
	if(!strlen($txt)) $txt = "Hello World";
	$ln = @$_REQUEST['ln'];
	if(!isset($ln) || strlen($ln)!==2) $ln = "EN";
	else $ln = strtoupper($ln);
	$qrtext = "TXT-$ln:$txt";
?>
	<h1>QR Text Generator</h1>
	Generator fuer QR Codes fuer LTX-Texte.<br><br>
	
	<form action="./">
	<label for="qrlang">Enter Language (2 Chars, e.g. 'en','de','it',..):</label><br>
	<input type="text" id="ln" name="ln" size = "2" value="<?php echo(strtoupper($ln)); ?>"><br>
	<label for="qrtext">Enter Text:</label><br>
	<input type="text" id="txt" name="txt" size = "80" value="<?php echo($txt); ?>"><br>
	<br>
	<input type="submit" value="Submit">
	</form> 
	<br>
	QR: '<?php echo($qrtext); ?>'<br>
	<img src="./ltx_qr.php?text=<?php echo(urlencode($qrtext)); ?>">

</body> <!-- BODY END -->
</html>