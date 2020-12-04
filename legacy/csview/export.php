<?php
     // -------------------------------------------------------
     // export.php - Export of CSVIEW-Files V2.01 as CSV
     // Submodule for CSVIEW
     // -------------------------------------------------------
    error_reporting(E_ALL);
	include("../api_key.php");

     // Name der Datendatei
     $fname="(unknown).xxx";

     if(@$_GET['file']){
       $fname=$_GET['file']; // AUfrufen: file=xxx
    $srcf="../".S_DATA."/stemp/$fname";
       if (!file_exists($srcf)){
         $e="File '$fname' not found";
       }else{
         header('Content-type: text/csv');
         header("Content-Disposition: attachment; filename=$fname"); // RFC2183: Querry Open/Save
         //header("Content-Disposition: inline; filename=$fname"); // RFC2183: Display immediatelly
         readfile($srcf);  // OK
       }
     }else $e="Filename missing";

     if(@$e){
echo <<<ERR
<html><head><title>Error</title>
<style type="text/css">
* {font-family:sans-serif; font-size:10pt ; padding-left:4pt}
h2 {  font-weight:bold; background-color:#8af; font-size:14pt;  }
</style></head>
<body><h2><br>ERROR<br>&nbsp;</h2>
(Script: '$_SERVER[SCRIPT_NAME]')<br><br>
ERR;
          echo "Explanation: <b>'$e'</b>";
          echo "<hr></body></html>";
    }
?>
