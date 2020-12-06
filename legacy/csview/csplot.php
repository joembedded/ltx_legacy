<?php
/* --------------CSPLOT.PHP --------------------------
      CSV to IMAGE-Generator - (C) JoEmbedded.de

      V2.12 06.12.2020
      ----
      V2.12: Tested PHP8 and VS with intellisense

      Requires GD-Library and PHP >= 5

      This Script is able to draw a graph from CSV or raw data. It can also
      cumulate data from several files automatically. This is a very
      easy-to-use script for including 'online' - graphs in own WEB-pages!
      
      URL-Parameters:

      - maxy, miny: Y-Range, if set: autozoom is disabled, else autozomm enabled.
             Should be set both or none!
      - maxx, miny: First and/or last data column in CSV-File (without the
             first 2 lines (= header and channels). So normally 2 less then lines
             in the CSV-File. Could be set single or both. if not set: unlimited
      - vis: String, containing x for invisible channels, any other char for visible
             channel (e.g. oxoo will not show channel 1). If omitted: All is shown
      - sizex,sizey: Total size of image. Default is 800x600.
      - xl: If set to any value (not 0) Legend is not shown (e.g. for small images or
             single channel images)

      - fname: Filename of CSV-File in ./stemp/. Set this parameter ONLY if valid
             CSV Files are already in the /stemp. (Normally used only from CSVIEW.PHP).
             Do NOT set if RAW data are requested via cmd (see below)
     ------------------------------------------------------- */

include("../../sw/conf/api_key.inc.php");


// Festgelegtes
define('MARGL', 50);
define('MARGR', 15);
define('MARGB', 85);
define('MARGT', 30);
define('FONTX', 2); // Font fuer X-Ache 90 Grad
define('FONTY', 2); // Font fuer Y-Achse
define('FONTT', 5); // Font Ueberschrift

// Name der Datendatei
//$fname="../../data/stemp/test.csv";
$fname = "***File not found/No data***";
$sizex = 800;
$sizey = 600;
$vis = '';  // Visible pro Kanal wenn nicht 'x', 0:

$azoomy = 1;  // Vorgabe: $miny/maxy->azoomy=0; +/- 5%
$miny = +1e20;
$maxy = -1e20;

$minx = 0;
$maxx = 1e6;   // 1 Million Datensaetze max.

// -- Ab hier Benutzerwerte, arbeiten nur im Unterverzeichnis stemp erlaubt ---
if (@$_GET['file']) $fname = '../' . S_DATA . '/stemp/' . $_GET['file']; // AUfrufen: file=xxx
if (@$_GET['maxy']) {
  $azoomy = 0;  // Nicht zoomen
  $maxy = $_GET['maxy'];
  $miny = $_GET['miny'];
}
if (@$_GET['maxx']) $maxx = $_GET['maxx'];
if (@$_GET['minx']) $minx = $_GET['minx'];
if (@$_GET['vis']) $vis = $_GET['vis'];    // $vis='x1xx';  // Visible pro Kanal wenn nicht '.', 0:
if (@$_GET['sizex']) $sizex = $_GET['sizex']; // Darstellungsgroesse
if (@$_GET['sizey']) $sizey = $_GET['sizey'];
// if(!(@$_GET['xl'])) -> spaeter: draw_legend();


// ----- Nachricht ausgeben und evtl. beenden -----
function message($err, $xit = 0)
{
  // echo"MESSAGE<br>";
  global $black, $bild, $sizey;
  static $y = 10;
  Imagestring($bild, 3, 10, $y, $err, $black);
  $y += 16;
  if ($xit || $y > $sizey) {
    header("Content-type: image/png");
    ImagePng($bild);
    exit();
  }
}

// ----- Zeichent die Arbeitsflache -----
function draw_workspace()
{
  global $bild, $black, $white, $cx0, $cx1, $cy0, $cy1, $logger, $sizex;

  imagefilledrectangle($bild, $cx0, $cy1, $cx1, $cy0, $white);
  $p = array();
  $p[0] = $cx0 - 1;
  $p[1] = $cy1 - 1;
  $p[2] = $cx1 + 1;
  $p[3] = $cy1 - 1;
  $p[4] = $cx1 + 1;
  $p[5] = $cy0 + 1;
  $p[6] = $cx0 - 1;
  $p[7] = $cy0 + 1;
  imagepolygon($bild, $p, 4, $black);
  // Arbeitsflaeche ist nun vorbereitet
  $xpos = ($sizex - strlen($logger) * imagefontwidth(FONTT)) / 2;
  Imagestring($bild, FONTT, $xpos, MARGT / 4, $logger, $black);
  //Imagestring($bild,1,$sizex-60,0,"GeoPrecision",$black);
}

// -------- Datei einlesen, mit scany: Autobereich festlegen ----------
function read_csv($azoomy)
{
  global $fname, $miny, $maxy, $logger, $units, $data, $vis, $minx, $maxx; // Maxcol: Anzahl der Spalzen (inkl. Datum und Events
  $cnt = 0;
  $data = array();
  $inf = @fopen($fname, "r");
  if (!$inf) message("*** CSPLOT ERROR: Can't open File '$fname'", 1);

  while ($tline = fgets($inf, 1000)) {
    if ($tline[0] == '<') {        // Blabla
      if ($cnt >= $minx && $cnt <= $maxx) {
        $data[] = rtrim($tline);
      }
      if (!strncmp($tline, "<MAC:", 5)) $logger = trim($tline, "\r\n<>");
    } else if ($tline[0] == 'N') {     // No units, ...
      $units = explode(',', rtrim($tline));
      $units[1] = "Events";       // Umnennen
      while (strlen($vis) < count($units)) $vis .= '0';  // vis lange genug machen
      if ($cnt >= $minx && $cnt <= $maxx) {
        $data[] = $tline;
      }
    } else { // Normale Daten
      if ($cnt >= $minx && $cnt <= $maxx) {
        $line = explode(',', rtrim($tline));
        $data[] = $line;
        $h = count($line); // Anzahl der Elemente pro Zeile
        if ($azoomy && $h > 2) {
          for ($i = 2; $i < $h; $i++) {
            if ((@$vis[$i - 1] != 'x') && $azoomy) { // Nur sichtbare beruecksichtigen und Events
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
      }
    }
    $cnt++;
  }
  fclose($inf);
}

// -------- Fensterbereich Y festlegen und Y-Skala zeichnen ----------
function set_zoom()
{
  global $bild, $black, $gray, $miny, $maxy, $cy0, $cy1, $cx0, $cx1, $fxa, $fxb, $azoomy;
  if ($azoomy) $dy = ($maxy - $miny) * 1.05;              // Differenz mit 5% Reserve
  else $dy = ($maxy - $miny);                          // Exakt
  $dpix = $cy0 - $cy1;
  $step = pow(10, ((int)floor(log10($dy))) - 1); // Schrittweite 10-er basiert
  if ($azoomy) $by0 = $miny - 0.025 * $dy; // Untere basis 2.5% runtersetzen
  else $by0 = $miny;                      // Exakt
  $anzs = (int)($dy / $step) + 1;              // Anzahl der Schritte (ideal 5-ca. 20)
  $fxa = ($cy1 - $cy0) / $dy;
  $fxb = $cy0 - $by0 * $fxa;

  // Hier noch step/anzs/shy skalieren!
  $shy = $dpix / $anzs;             // 1 Schritt entspricht shy Pixel (evtl. skalieren)

  if ($shy < 3) {
    $step *= 10;
    $anzs /= 10;
  } else if ($shy < 6) {
    $step *= 5;
    $anzs /= 5;
  } else if ($shy < 11) {
    $step *= 2;
    $anzs /= 2;
  }
  $idx0 = (int)floor($miny / $step);        // Das ist der unterste Index


  $hfo2 = imagefontheight(FONTY) / 2;
  $wfo = imagefontwidth(FONTY);
  for ($i = 0; $i < $anzs; $i++) {
    $ey = ($i + $idx0) * $step;
    $ay = $fxa * $ey + $fxb;
    if ($ay < $cy0 && $ay > $cy1) {
      $etx = "$ey";
      if ($etx == '0') Imageline($bild, $cx0, $ay, $cx1, $ay, $black);
      else Imageline($bild, $cx0, $ay, $cx1, $ay, $gray);

      Imageline($bild, $cx0 - 5, $ay, $cx0, $ay, $black);
      $tp0 = $cx0 - 8 - $wfo * strlen($etx);
      if ($tp0 < 0) $tp0 = 0;
      Imagestring($bild, FONTY, $tp0, $ay - $hfo2, $etx, $black);
    }
  }
}
// ------ Draw_lines: Die Linien einzeichnen
function draw_lines()
{
  global $bild, $black, $gray, $cy0, $cy1, $cx0, $cx1, $fxa, $fxb, $data, $col, $units, $vis;

  $oay = array();
  $ox = array();
  $sd = 1;      // Zeiten vorzugsweise nach Events und/oder HK-Werten zeigen
  $ls = 0;      // Letzte Skala
  $mu = count($units);    // Anzahl der Einheiten
  $oday = '';   // Kein Tag
  $dd = 1;      // Draw Day
  $ldx = -100;     // Letzter Tag X

  $alarmcolor = imagecolorallocatealpha($bild, 255, 0, 0, 100);  // Very Alpha

  for ($i = 0; $i < $mu; $i++) { // Keine Y-Werte
    $oay[$i] = -1e6;
  }

  $dx = count($data);
  if (!$dx) return;
  if ($dx > 1) $fca = ($cx1 - $cx0) / ($dx - 1);
  else $fca = 0; // Verhindere Div/0
  $fcb = $cx0;           // Erstmal kein Offset vorhanden


  $hfo2 = imagefontheight(FONTX) / 2;
  $wfo = imagefontwidth(FONTX);

  //Optionally show Events
  $she = false;
  if ($fca > $hfo2 * 2) $she = true;

  for ($i = 0; $i < $dx; $i++) {
    $cax = $fca * $i + $fcb;   // Das ist die Y-Position
    $ay = $data[$i];

    if (is_string($ay)) $ayc = 1;  // PHP8 typkritischer als 7
    else $ayc = count($ay);

    if ($ayc == 1) {    // EVENT
      if ($vis[0] != 'x') {
        // Tag Start
        if ($ay[0] == '<') {
          if (!strncmp($ay, "<NEW", 4)) $ecol = $col[7];     // MSG HellBlue
          else if (!strncmp($ay, "<RESET", 6)) $ecol = $col[3];   // MSG: Magenta
          else if (!strncmp($ay, "<E", 2)) $ecol = $col[0];   // MSG: ROT <E rror
          else if (!strncmp($ay, "<W", 2)) $ecol = $col[4];   // MSG: Orange <W arning
          else if (!strncmp($ay, "<TOTAL", 6)) $ecol = $col[5];   // MSG: Ende: PINK
          // Inner String
          else if (strpos($ay, "ERROR") > 0) $ecol = $col[0];   // MSG: ROT 'ERROR'
          else if (strpos($ay, " OK") > 0) $ecol = $col[1];   // MSG: Green ' OK' (coOKie)
          // Other Colors/Tags: Enter here...
          else $ecol = $col[10]; // MSG ??? unknown: Yellow
        } else $ecol = $col[9]; // User MSG: Brown

        Imagefilledrectangle($bild, $cax - 2, $cy0 - 8, $cax + 2, $cy0, $ecol);

        if ($she) {
          ImageStringUp($bild, FONTX, $cax - $hfo2, $cy0 - 10, $ay, $black);
        }
      }
      $sd = 1;
    } else {  // Regulaerer Messwert
      $xd = substr($ay[1], 0, 10); // Tag extrahieren
      if (strcmp($xd, $oday)) { // Neuer Tag?
        Imageline($bild, $cax, $cy0, $cax, $cy1, $gray);
        $dd = 1; // Draw Day a.s.a.p
        $oday = $xd;         // Merken Tag (String)
      }
      if ($dd) {
        if ($cax - $ldx > 11 * $wfo) {
          $dd = 0;
          $iy = $cy0 + 9 * $wfo + 7;    //Zeit immer 8 Zeichen lang, drunterdrucken
          Imageline($bild, $cax, $cy0, $cax, $iy + $hfo2 * 2, $gray);
          ImageString($bild, FONTX, $cax, $iy, $xd, $black);
          $ldx = $cax;
        }
      }

      if (($sd || $ayc == $mu || $cax - $ls > 50) && ($cax - $ls > $hfo2 * 3)) {  // Skala nach Events oder HK-Werten oder wenn SEHR lange her
        Imageline($bild, $cax, $cy0, $cax, $cy0 + 5, $black);
        $xs = substr($ay[1], 10);
        $iy = $cy0 + 8 * $wfo + 7;    //Zeit immer 8 Zeichen lang
        ImageStringUp($bild, FONTX, $cax - $hfo2, $iy - 3, $xs, $black);
        $ls = $cax;
        $sd = 0;
      }

      for ($j = 2; $j < $ayc; $j++) {
        if (@$vis[$j - 1] != 'x') {
          $val = trim($ay[$j]);
          if (@$val[0] == '*') {  // Alarm
            $val = substr($val, 1);
            $y = $fxa * floatval($val) + $fxb;
            //Imagefilledrectangle($bild,$cax-5,$y-5,$cax+5,$y+5,$alarmcolor);
            imagefilledellipse($bild, $cax, $y, 15, 15, $alarmcolor);
          } else $y = $fxa * (float)$val + $fxb;


          //if($y<$cy0 && $y>$cy1){ // Clippen (deaktiviert)
          $cc = $col[($j - 2) & 15]; // Farben begrenzen 0..15
          Imagefilledrectangle($bild, $cax - 1, $y - 1, $cax + 1, $y + 1, $cc);
          if (@$oay[$j] > -1e6) { // Alte Pos. vorhanden: Linie nachziehen
            Imageline($bild, $ox[$j], $oay[$j], $cax, $y, $cc);
          }
          $oay[$j] = $y;      // y-Pos. inplace merken
          $ox[$j] = $cax;
          //}$oay[$j]=-1e6; // Clippen: 0: Unsichtbar
        }
      }
    }
  }
}

// ------ Draw_legend(): Legende zeichnen
function draw_legend()
{
  global $bild, $white, $black, $gray, $cy0, $cy1, $cx0, $cx1, $units, $col, $vis;
  $hfo = imagefontheight(FONTY);
  $wfo = imagefontwidth(FONTY);
  $max = 0;
  $anzk = 0;
  for ($i = 1; $i < count($units); $i++) {
    if ($vis[$i - 1] != 'x') {
      $len = strlen($units[$i]);
      if ($len > $max) $max = $len;
      $anzk++;
    }
  }
  /* Top left
       $lx1=$cx1-5;
       $lx0=$lx1-25-$max*$wfo;
       */
  /* Top right */
  $lx0 = $cx0 + 5;
  $lx1 = $lx0 + 25 + $max * $wfo;

  $ly1 = $cy1 + 5;
  $ly0 = $ly1 + $anzk * $hfo;

  $p = array();
  $p[0] = $lx0 - 1;
  $p[1] = $ly1 - 1;
  $p[2] = $lx1 + 1;
  $p[3] = $ly1 - 1;
  $p[4] = $lx1 + 1;
  $p[5] = $ly0 + 1;
  $p[6] = $lx0 - 1;
  $p[7] = $ly0 + 1;
  imagepolygon($bild, $p, 4, $black);

  imagefilledrectangle($bild, $lx0, $ly1, $lx1, $ly0, $white);
  for ($i = 1; $i < count($units); $i++) {
    if ($vis[$i - 1] != 'x') {
      ImageString($bild, FONTY, $lx0 + 23, $ly1, $units[$i], $black);
      $ay = $ly1 + $hfo / 2;
      if ($i == 1) {  // Event ist 4x8 Pixel gross
        Imagefilledrectangle($bild, $lx0 + 8, $ay - 3, $lx0 + 12, $ay + 5, $gray);
      } else {
        $cc = $col[($i - 2) & 15]; // Farben begrenzen 0..15
        Imagefilledrectangle($bild, $lx0 + 2, $ay - 1, $lx0 + 18, $ay + 1, $cc);
      }
      $ly1 += $hfo;
    }
  }
}

//-----------------------------------------
//--- MAIN und erst mal Canvas erzeugen ---
//-----------------------------------------
$cx0 = MARGL;   // 00: Links unten, 11: Rechts oben
$cx1 = $sizex - MARGR;
$cy0 = $sizey - MARGB;
$cy1 = MARGT;
$fxa = 1;    // Steilheit
$fxb = 0;    // Offet der Umrechnung
$bild = ImageCreate($sizex, $sizey);
// Drei neutrale Farben erzeugen
ImageColorAllocate($bild, 230, 245, 255); // Background (Lichtblau)
$black = ImageColorAllocate($bild, 0, 0, 0); // Schwarz
$white = ImageColorAllocate($bild, 255, 255, 255); // Schwarz
$gray = ImageColorAllocate($bild, 225, 225, 225); // Hell-Grau fuer Grid

// 16 Grundfarben festlegen, alles darueber ist modular
$col[0] = ImageColorAllocate($bild, 255, 0, 0);  // Farben Rot
$col[1] = ImageColorAllocate($bild, 0, 200, 0);  // Farben Gruen
$col[2] = ImageColorAllocate($bild, 0, 0, 255);  // Farben Blau
$col[3] = ImageColorAllocate($bild, 255, 0, 255);  // Farben Magenta
$col[4] = ImageColorAllocate($bild, 255, 200, 0);  // Farben Orange
$col[5] = ImageColorAllocate($bild, 0, 255, 200);  // Farben Indigo
$col[6] = ImageColorAllocate($bild, 50, 255, 50);  // Hellgruen
$col[7] = ImageColorAllocate($bild, 150, 150, 255);  // Hellblau

$col[8] = ImageColorAllocate($bild, 255, 192, 203); // Pink
$col[9] = ImageColorAllocate($bild, 165, 42, 42); // Braun
$col[10] = ImageColorAllocate($bild, 255, 240, 0); // Yellow
$col[11] = ImageColorAllocate($bild, 160, 32, 240);  // Purple
$col[12] = ImageColorAllocate($bild, 127, 255, 212); // Aquamarin
$col[13] = ImageColorAllocate($bild, 220, 200, 255); // Lavendel
$col[14] = ImageColorAllocate($bild, 135, 206, 235); // Graublau
$col[15] = ImageColorAllocate($bild, 154, 255, 50);  // Gelbgruen

// CSV-Datei erst noch erstellen!
read_csv($azoomy); // 1: Mit Autozoomy

draw_workspace(); // Flaeche vorbelegen
set_zoom();   //
draw_lines();

// Legende nicht zeichnen wenn nicht gewuenscht
if (!(@$_GET['xl'])) draw_legend();

// TEST: Enable to show all Colors
// for($i=0;$i<17;$i++) Imagefilledrectangle($bild,$cx0+$i*10,$cy1,$cx0+$i*10+6,$cy1+20,$col[$i]);
// ImageStringUp($bild,1,0,$sizey,"(C) Joembedded.de",$black);

header("Content-type: image/png");
ImagePng($bild);
