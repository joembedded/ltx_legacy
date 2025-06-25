<?php 
// QR-Generator PHP - Stand 03.02.25

// Quelle: https://www.geeksforgeeks.org/dynamically-generating-a-qr-code-using-php/
// Include the qrlib file 
include '../php_qr/qrlib.php'; // selbiges Verzeichnis

// $text variable has data for QR  
// $text = "geo:20.33470,20.39448"; 
// $text = "https://joembedded.de/ltx";
// $text = "MAC:1EC8F6CD6CA69459 OT:5104A8C388FD4065";
// QR Code generation using png() 
// When this function has only the 
// text parameter it directly 
// outputs QR in the browser 
// Test: http://localhost/ltx/sw/php_qr/ltx_qr.php?text=Hallo%20Welt&ecc=M
// Test: https://joembedded.de/ltx/sw/php_qr/ltx_qr.php?text=Hallo%20Welt&ecc=M

error_reporting(E_ALL);

// Parameters:
$text = $_REQUEST['text'] ?? '(NoText)';
$ecc = $_REQUEST['ecc'] ?? 'L'; // L M Q H mgl.
$pixel_Size = (int)($_REQUEST['px'] ?? 5); // Pixel Groessen
$frame_Size = (int)($_REQUEST['fx'] ?? 5);

if ($pixel_Size < 1) $pixel_Size = 5; // Pixel Groessen
if ($frame_Size < 1) $frame_Size = 5;

//echo "Text:'$text', Ecc:'$ecc', PixelSize:$pixel_Size, FrameSize:$frame_Size\n"; exit(); // DBG

header('Access-Control-Allow-Origin: *');

// Generates QR Code and NOT Stores it
QRcode::png($text, false, $ecc, $pixel_Size, $frame_Size); 
?>
