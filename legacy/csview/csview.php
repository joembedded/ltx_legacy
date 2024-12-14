<?PHP
/* --------------CSVIEW.PHP --------------------------
CSV - Online Viewer - (C) Joembedded.de
V2.20 02.11.2023
----
  Tested PHP8 and VS with intellisense

  *** Requires GD-Library (php.ini: enable 'extention=gd' and PHP >= 5 *** 

  URL-Parameters:

  - vis: String, containing x for invisible channels, any other char for visible
          channel (e.g. oxoo will not show channel 1). If omitted: All is shown
          Extra function for CSVIEW: If set to Large Caps X, the channel will be
          ignored for this graph, else the user can enable it manually later.
          
  - sizex,sizey: Total size of image. Default is 800x600.
  - xl: If set to any value (not 0) Legend is not shown (e.g. for small images or
          single channel images)
------------------------------------------------------- */
error_reporting(E_ALL);
include("../../sw/conf/api_key.inc.php");

//------------- Functions -----------------

function error_message($e)
{  // Die with Error
  echo <<<ERR
<html><head><title>Error</title>
<style type="text/css">
* {font-family:sans-serif; font-size:10pt ; padding-left:4pt}
h2 {  font-weight:bold; background-color:#8af; font-size:14pt;  }
</style></head>
<body><h2><br>ERROR<br>&nbsp;</h2>
(Script: 'csview.php')<br><br>
ERR;
  echo "Explanation: <b>'$e'</b>";
  echo "<hr></body></html>";
  exit(0);
}


// -------- Datei einlesen, Autobereich festlegen ----------
function read_csv()
{
  global $fname, $miny, $maxy, $units, $data, $vis, $maxx, $title; // Maxcol: Anzahl der Spalzen (inkl. Datum und Events
  $maxx = 0;
  $miny = +1000000;
  $maxy = -1000000;

  $dpath = '../' . S_DATA . "/stemp/$fname";

  $inf = fopen($dpath, "r");
  if (!$inf) error_message("Can't open File '$fname'");
  if (fgets($inf, 4 /*OK, len-1*/) !== "\xef\xbb\xbf")  rewind($inf); // Skip BOM

  $title = $fname;

  while ($line = fgetcsv($inf, 1000)) { // Achtung: Addiert BOM-Header (UTF-8)
    if ($line[0][0] == 'N') {
      $units = $line;
      $units[1] = "Events";       // Umnennen
      while (strlen($vis) < count($units)) $vis .= '0';
      $maxx++;
      continue;
    } else if ($line[0][0] == '<') {
      if (!strncmp($line[0], "<MAC:", 5)) $title = trim($line[0], "<>\r\n");
      $maxx++;
      continue;
    }
    $h = count($line); // Anzahl der Elemente pro Zeile
    if ($h > 2) {
      for ($i = 2; $i < $h; $i++) {
        if ((@$vis[$i - 1] != 'X')) { // ALLE beruecksichtigen und Events: Grosses X: ausgeschaltet
          $val = $line[$i];
          if ($val[0] == '*') {  // That was an ALARM
            $val = substr($val, 1);
          }
          $v = (float)($val);
          if ($v > $maxy) $maxy = $v;
          if ($v < $miny) $miny = $v;
        }
      }
    }
    $maxx++;
  }
  fclose($inf);
  // Enlarge Viewport slightly (analog to PHPLOT)
  $dy = ($maxy - $miny) * 0.025;
  $maxy += $dy;
  $miny -= $dy;
  return $maxx;
}

/* Remove old CSV-files from stemp */
function remove_old_csv()
{
  $dpath = '../' . S_DATA . "/stemp";
  if (!file_exists('../' . S_DATA . "/stemp")) mkdir('../' . S_DATA . "/stemp");



  $servertime = time(); // Sec ab 1.1.1970, GMT
  $handle = opendir($dpath);
  while ($file = readdir($handle)) {
    if ($file == '.' || $file == '..') continue;
    $age = $servertime - filemtime("$dpath/$file");
    // echo("File: $file, Age: $age <br>\n");
    if ($age > 7200) { //2 hours
      unlink("$dpath/$file");
    }
  }
  closedir($handle);
}


//------------- M A I N -----------------

// Presets
$sizex = 0;
$sizey = 0;
$vis = '';  // Visible pro Kanal wenn nicht 'x', 0:
$xl = 0;
$units = array();

if (@$_GET['vis']) $vis = $_GET['vis'];    // $vis='x1xx';  // Visible pro Kanal wenn nicht '.', 0:
if (@$_GET['sizex']) $sizex = $_GET['sizex']; // Darstellungsgroesse
if (@$_GET['sizey']) $sizey = $_GET['sizey'];
if (@$_GET['xl']) $xl = $_GET['xl'];

$mac = @$_GET['s'];  // = ID of LTraX (required)
$fname = @$_GET['f'];  // Filename (required)

remove_old_csv();
if (empty($mac)) error_message("MAC not set");

// Remote File erzeugen lassen
session_start();
$skey = @$_SESSION['key'];
$self = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
$calls = substr($self, 0, strrpos($self, '/'));  // Muss 2 Levels hoch gehen
$call = substr($calls, 0, strrpos($calls, '/'));
$url = "$call/edt_view.php?s=$mac&f=$fname&o=2&k=$skey";
$info = file_get_contents($url); // Remove Blabla
if (strlen($info) < 10) {
  echo "*ERROR: Not DATA*\n";
  exit();
}

$fname = '../' . S_DATA . "/stemp/t" . rand(10000, 99999) . time() . ".csv"; // unique_string
file_put_contents($fname, $info); // Might add UTF8-BOM */

/*
	 // header('Content-Type: text/plain'); //For Debug-Output
     $fline=strchr($info,"<OUTPUT '".S_DATA."stemp/");	// MUSS in stemp liegen!
	 $fname=substr($fline,22);
	 $fname=substr($fname,0,strpos($fname,"'"));
     */


$anz = read_csv();

//echo "Fname '$fname' Anz: $anz\n"; exit();

?>

<!DOCTYPE html>
<html>

<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style type="text/css">
    * {
      font-family: sans-serif;
      font-size: 10pt;
      padding-left: 4pt
    }

    h2 {
      font-weight: bold;
      background-color: #8af;
      font-size: 14pt;
    }
  </style>
</head>

<title><?PHP echo $title; ?> - CSView Online</title>

<script type="text/javascript">
  // Script (C) JoEmbedded.de

  // --- OPTIONAL DYNAMIC Data START ---
  var sizex = <?PHP echo $sizex; ?>; // Size Graphik (Default is 500x400)
  var sizey = <?PHP echo $sizey; ?>;
  var vis = '<?PHP echo $vis; ?>'; // Small Caps x: Unchecked, else Checked (my be left out) (e.g. 'oxoo')
  var hideleg = <?PHP echo $xl; ?>; // Set to 1 to hide legend

  // --- REQUIRED DYNAMIC Data START ---
  var file = '<?PHP echo $fname; ?>'; // Name of CSV-File
  var channels = new Array( // Enthaelt implizit Number of Channels
    <?PHP
    $au = count($units);
    for ($i = 1; $i < $au; $i++) {
      echo "\"$units[$i]\"";
      if ($i != $au - 1) echo ',';
    }
    ?>
  );

  var miny = <?PHP echo $miny; ?>; // Y-Zoomwindow
  var maxy = <?PHP echo $maxy; ?>;
  var minx = 0; // X-Zoomwindow FIRST Index without 2 Lines Header
  var maxx = <?PHP echo $maxx; ?>; // LAST Index without 2 Lines Header

  // --- DYNAMIC Data END ---

  window.onerror = myerror;
  window.onload = myload;
  document.onmousemove = mousemove;
  document.onmousedown = startclip;
  document.onmouseup = stopclip;

  var graphs; // The graphic object style
  var rubbers; // Rubberband object style
  var anzk; // Number of Channels
  var lxpar; // Extra Par. Empty or '&xl' to drop  legend and Random element (to omit Caching)

  var mx = 0; // Mouse coordinates
  var my = 0;
  var mx0 = 0; // Coordiantes Top left
  var my0 = 0;
  var mx1 = 0;
  var my1 = 0;
  var clip = 0; // 1/2: Clipping 0: Invisible
  var tcnt = 0; // Hilfsvariable

  var cx0 = 50; // Clipping Area ohne Size (aus PHP-Script CSPLOT)
  var cy0 = 30;
  var cx1 = 15; // Relative to size
  var cy1 = 85;
  var blinker; // Handle for Clipping-Blinker
  var bflag; // Blinkflag

  var o_miny = 0; // Original Window
  var o_maxy = 0;
  var o_minx = 0;
  var o_maxx = 0;

  var coltab = new Array( // Contains. 16+2 Colors (Standard Colors)
    "#E1E1E1", //0: Event-Grau (ohne Index)
    "#FF0000", //1: Ab hier Std.Tabelle 16 Index 0! Rot
    "#00C800", //2: Gruen
    "#0000FF", //3: Blau
    "#FF00FF", //4: Magenta
    "#FFC800", //5: Orange
    "#00FFC8", //6: Indigo
    "#32FF32", //7: Hellgruen
    "#9696FF", //8: Hellblau
    "#FFC0CB", //9: Pink
    "#A52A2A", //10: Braun
    "#FFF000", //11: Gelb
    "#A020F0", //12: Purple
    "#7FFFD4", //13: Aquamarin
    "#DCC8FF", //14: Lavendel
    "#87CEEB", //15: Graublau
    "#9AFF32", //16: Gelbgruen
    "#F0F0F0" //17: Light-Gray (Deaktivierte Kanaele)
  );

  function myload() { // Initialise all dynamic contents
    // Check minimum Sizes
    if (sizex < 100) sizex = 700;
    if (sizey < 100) sizey = 500;

    // Generate additional parameters, containint randomd ID to disable Image Caching
    lxpar = "&id=" + parseInt(Math.random() * 100000);
    if (hideleg) lxpar += "&xl=1";

    // Save Original Window
    o_miny = miny;
    o_maxy = maxy;
    o_minx = minx;
    o_maxx = maxx;

    // Generate dynamic checkboxes for each channel
    var fdyn = document.getElementById("fdyn");
    var fdhtml = '<nobr><button type="button" onclick="Redraw()"><img src="redraw.gif" align="top"> Redraw</button><br>';


    anzk = channels.length;

    for (var i = 0; i < anzk; i++) { // Generate Channels
      fdhtml += '<input type="checkbox" name="Kan" onclick="Check(' + i + ')">';
      if (vis.charAt(i) != 'X') {
        fdhtml += '<span id="Col' + i + '">&nbsp;&nbsp;</span> ' + channels[i] + '<br>';
      }

    }
    fdhtml += '</nobr>';
    fdyn.innerHTML = fdhtml;

    for (var i = 0; i < anzk; i++) { // Initialise checkboxes and Colors
      if (vis.charAt(i) == 'X') {
        document.getElementsByName("Kan")[i].style.display = "none"; // Don't show
      } else {
        document.getElementsByName("Kan")[i].checked = (vis.charAt(i) != 'x'); // Def. ist false
        Check(i);
      }
    }
    vis = vis.toLowerCase(); // to replace X by x

    // Now Fix graph
    graphs = document.getElementById("graph").style;
    cmd = "url(csplot.php?file=" + file + "&sizex=" + sizex + "&sizey=" + sizey + "&maxy=" + maxy + "&miny=" + miny + "&maxx=" + maxx + "&minx=" + minx + "&vis=" + vis + lxpar + ")";

    //alert("StartReadraw mit cmd:"+cmd);

    console.log("CMD1:", cmd);
    graphs.background = cmd;
    graphs.width = sizex + "px";
    graphs.height = sizey + "px";

    rubbers = document.getElementById("rubber").style;

  }

  function myerror(msg, file, line) {
    var emsg = "*** CSVIEW: JavaScript Runtime-Error:\n\n" + msg + "\n\nFile: '" + file + "'\nLine: " + line;
    alert(emsg);
    return true;
  }

  function Redraw() {
    vis = '';

    if (minx > o_minx) {
      document.getElementById("fi").src = "first.gif";
      document.getElementById("le").src = "left.gif";
    } else {
      document.getElementById("fi").src = "first_g.gif";
      document.getElementById("le").src = "left_g.gif";
    }

    if (maxx < o_maxx) {
      document.getElementById("la").src = "last.gif";
      document.getElementById("ri").src = "right.gif";
    } else {
      document.getElementById("la").src = "last_g.gif";
      document.getElementById("ri").src = "right_g.gif";
    }


    for (var i = 0; i < anzk; i++) {
      if (document.getElementsByName("Kan")[i].checked) vis += 'o';
      else vis += 'x';
    }
    minx = parseInt(minx); // X nur integer!
    maxx = parseInt(maxx);
    if (maxx <= minx) minx = maxx + 1; // Verhindere Div/0
    cmd = "url(csplot.php?file=" + file + "&sizex=" + sizex + "&sizey=" + sizey + "&maxy=" + maxy + "&miny=" + miny + "&maxx=" + maxx + "&minx=" + minx + "&vis=" + vis + lxpar + ")";

    //alert("ZoomReadraw:"+cmd); // TEST
    console.log("CMD2:", cmd);
    graphs.background = cmd;
  }

  function Unzoom() {
    miny = o_miny;
    maxy = o_maxy;
    minx = o_minx;
    maxx = o_maxx;
    Redraw();
  }

  function wmove(typ) {
    dx = (maxx - minx);
    dy3 = (maxy - miny) / 5; //20%
    if (typ == 0) { // Anfang
      minx = o_minx;
      maxx = minx + dx;
    } else if (typ == 1) { // Ende
      minx = o_maxx - dx;
      maxx = o_maxx;
    } else if (typ == 2) { // Rueck
      minx -= dx / 4; // 1/4 Window back
      if (minx < o_minx) minx = o_minx;
      maxx = minx + dx;
    } else if (typ == 3) { // Vorw
      maxx += dx / 4; // 1/4 Window back
      if (maxx > o_maxx) maxx = o_maxx;
      minx = maxx - dx;
    } else if (typ == 4) { // Up 30%
      miny += dy3;
      maxy += dy3;
    } else if (typ == 5) { // Down 30%
      miny -= dy3;
      maxy -= dy3;
    }
    Redraw();
  }


  function Check(nr) {
    var ci = 17; // Fast-Weiss
    if (document.getElementsByName("Kan")[nr].checked) {
      ci = nr;
      if (ci > 16) ci = 1 + ((nr - 1) % 16); // Farben modular wiederholen
    }
    document.getElementById("Col" + nr).style.backgroundColor = coltab[ci];
  }

  function calc_y(my) { // Y-Wert bereichnen, variable: Minx, miny
    return (miny - maxy) / (sizey - cy1 - cy0) * (my - cy0) + maxy;
  }

  function calc_x(mx) { // X-Wert bereichnen, variable: Minx, miny
    return (maxx - minx) / (sizex - cx1 - cx0) * (mx - cx0) + minx;
  }

  function mousemove(e) {
    if (e) {
      // Opera und Firefos
      mx = e.pageX ? e.pageX : e.clientX ? e.clientX : 0;
      my = e.pageY ? e.pageY : e.clientY ? e.clientY : 0;
    } else if (event) {
      // IE
      mx = event.clientX;
      my = event.clientY;
    }
    // Offsets for Browser...
    mx -= 2;
    my -= 2;

    if (mx > sizex || my > sizey) return true; // Ausserhalb

    // Clippen auf Koordiantensystem
    if (mx > sizex - cx1) mx = sizex - cx1;
    else if (mx < cx0) mx = cx0;
    if (my > sizey - cy1) my = sizey - cy1;
    else if (my < cy0) my = cy0;

    //document.getElementById("trace").innerHTML="Clip:"+clip+" Cnt:"+tcnt;

    if (clip) {
      mx1 = mx;
      my1 = my;
      var rw = mx1 - mx0;
      var rh = my1 - my0;

      if (rw >= 5) rubbers.width = rw + "px";
      if (rh >= 5) rubbers.height = rh + "px";
      if (rw < -10 && rh < -10) {
        noclip();
        return true;
      }
    }

    yv = calc_y(my);
    yvs = yv.toString();
    pkt = yvs.indexOf('.');
    document.getElementById("stat").innerHTML = "Value = " + yvs.substring(0, pkt + 5);

    // xv=calc_x(mx); document.getElementById("stat").innerHTML="X = "+xv; // TEST
    return true;
  }

  function clipblink() { //Visible Rubberband
    if (bflag) {
      rubbers.border = "2px dotted silver";
      bflag = 0;
    } else {
      rubbers.border = "2px dotted red";
      bflag = 1;
    }
  }

  function startclip() {
    if (mx > sizex || my > sizey) return; // Ausserhalb
    if (!clip) {
      mx0 = mx;
      my0 = my;
      mx1 = mx;
      my1 = my;
      rubbers.top = (my0 + 1) + "px"; // Drawing coordinates 2 Pixels shifted
      rubbers.left = (mx0 + 1) + "px";
      rubbers.width = 5 + "px"; // Minimum size 5x5
      rubbers.height = 5 + "px";
      rubbers.display = "block";
      clip = 1;
      blinker = window.setInterval("clipblink()", 200);
    } else if (clip) { // 2.nd click
      clip = 0;
      window.clearInterval(blinker);
      rubbers.display = "none";
      //alert("MX0:"+mx0+" MY0:"+my0+" MX1:"+mx1+" MY1:"+my1);
      if (mx1 - mx0 > 5 && my1 - my0 > 5) {
        hy = calc_y(my0);
        miny = calc_y(my1);
        maxy = hy; // Hilfsvariable
        hx = calc_x(mx1);
        minx = calc_x(mx0);
        maxx = hx; // Hilfsvariable
        Redraw();
      }
    }

  }

  function stopclip() {
    //  alert("MX0:"+mx0+" MY0:"+my0+" MX1:"+mx1+" MY1:"+my1);
    if (mx > sizex || my > sizey) return; // Ausserhalb
    if (clip && (mx1 - mx0) > 5 && (my1 - my0) > 5) {
      clip = 0;
      window.clearInterval(blinker);
      rubbers.display = "none";
      if (mx1 - mx0 > 5 && my1 - my0 > 5) {
        hy = calc_y(my0);
        miny = calc_y(my1);
        maxy = hy; // Hilfsvariable
        hx = calc_x(mx1);
        minx = calc_x(mx0);
        maxx = hx; // Hilfsvariable
        Redraw();
      }
    }
  }

  function noclip() {
    if (!clip) return;
    clip = 0;
    window.clearInterval(blinker);
    if (rubbers) rubbers.display = "none";
  }

  function expcsv() {
    var cmd = "export.php?file=" + file;

    //alert("Open "+datafile);
    //window.open(cmd,"CSView_Data");  //Open a new Window
    location.href = cmd;
  }
</script>

</head>

<body style="font-family:Verdana,Arial,sans-serif; font-size:10px">

  <!-- use table and/or divs for the overall layout -->
  <table id="g0" style="position:absolute; top:0px; left:0px; padding:0px; margin:0px; border:0px; background:#E0FFFF">
    <tr>
      <td valign="top">
        <div id="rubber" style="display:none; position:absolute; top:10px; left:10px; width:5px; height:5px; border:2px silver; line-height:0; font-size:0;"></div>
        <div id="graph" style="background-color:#E6F5FF; width:100px; height:100px;"></div>
      </td>
      <td valign="top" style="background:#F0F0F0; overflow:auto; border:1px solid silver; padding:3px;">
        <!--  Form with size: 10 less than sizey -->
        <form id="fdyn" action="">
          <!--  Form contains one Checkbox entry for each channel, filled dynamically -->
        </form>
      </td>
    </tr>
    <tr>
      <td colspan="2" style="background:#F0F0F0; border:1px solid silver; padding:3px;">
        <nobr>
          <xform action="">
            <button type="button" onclick="Unzoom()"><img src="zoom_off.gif" align="top" alt="Zoom Off (View All)">Zoom Off</button>&nbsp;&nbsp;
            <button type="button" onclick="wmove(0)"><img id="fi" src="first_g.gif" align="top" alt="Move to first Position"></button>
            <button type="button" onclick="wmove(2)"><img id="le" src="left_g.gif" align="top" alt="Move left"> </button>
            <button type="button" onclick="wmove(3)"><img id="ri" src="right_g.gif" align="top" alt="Move right"> </button>
            <button type="button" onclick="wmove(1)"><img id="la" src="last_g.gif" align="top" alt="Move to last Position"> </button>&nbsp;&nbsp;
            <button type="button" onclick="wmove(4)"><img src="up.gif" align="top" alt="Move up"> </button>
            <button type="button" onclick="wmove(5)"><img src="down.gif" align="top" alt="Move down"> </button>&nbsp;&nbsp;
            <!--
          <button type="button" onclick="expcsv()"><img src="expcsv.gif" align="top" alt="Export Data as CSV File ('Comma Separated Values')"></button>&nbsp;&nbsp;&nbsp;&nbsp;
		-->
            <span id="stat"> Value = </span>
          </xform>
        </nobr>
      </td>
    </tr>
    <tr>
      <td style="font-family:Verdana,Arial,sans-serif; font-size:10px">
        <b>Info: Zoom Graph with Mouse Button.</b>
        <span id="trace"></span>
      </td>
      <td style="font-family:Verdana,Arial,sans-serif; font-size:10px; text-align:right">
        CSView V2.20
      </td>
    </tr>
  </table>

  <noscript><b>JavaScript must be enabled in order for you to use CSView Online.</b>
    However, it seems JavaScript is either disabled or not supported by your browser.
    Please enable JavaScript by changing your browser options, and then try again.
  </noscript>
</body>

</html>