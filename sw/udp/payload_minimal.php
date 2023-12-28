<?php
/* Payload-Script ***Minimal_for_Tests*** 'p': Payload
* Payload must be a HEX String in BE Order (for easier debugging) 
* e.g. 202122 represents 32,33,33
* Call e.g. : http://localhost/ltx/sw/udp/payload_minimal.php?p=48616c6c6f2057656c74
* Call e.g. : http://joembedded.eu/ltx/sw/udp/payload_minimal.php?p=48616c6c6f2057656c74
* Reply is an Hex string of the reversed Payload in BE Order
* e.g. here: 746c6557206f6c6c6148
*/

header('Content-Type: text/plain');

$hexplbe = @$_REQUEST['p'];
if (!isset($hexplbe)) die("#ERROR: No Payload");
$payload=@hex2bin($hexplbe);
if(!strlen($payload)) die("#ERROR: Payload Format ('$hexplbe')"); // Odd size or wrong Chars?
$payrep =  bin2hex(strrev($payload)); // Reverse
echo $payrep;
// --- END ---
