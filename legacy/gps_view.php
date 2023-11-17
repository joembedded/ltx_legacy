<?PHP
/* -------------------------------------------------------------------
    * gps_view - Convert FILE.edt -> Leaflet
    * ---------------------------------------------------------------------- */

error_reporting(E_ALL);
include("../sw/conf/api_key.inc.php");
include("../sw/lxu_loglib.php");
session_start();
if (isset($_REQUEST['k'])) {
	$api_key = $_REQUEST['k'];
	$_SESSION['key'] = L_KEY;
} else $api_key = @$_SESSION['key'];
if (!strcmp($api_key, L_KEY)) {
	$dev = 1;	// Dev-Funktionen anzeigen
} else {
	$dev = 0;	// Dev-Funktionen anzeigen
}
/*
	if(!$dev) {
		echo "ERROR: Access denied!";
		exit();
	}
*/

// ---------------------------- M A I N --------------
// Aufruf: z.B http://localhost/ltx/legacy/gps_view.php?s=FADCE6452A5FF555&f=data.edt
$dbg = 0;

$mac = @$_GET["s"];
if (!isset($mac) || strlen($mac) != 16) {
	exit_error("MAC Len");
}
$fname = @$_GET["f"];
$utc_offset = @$_GET["utc"]; // Or 0

if ($dbg) {
	header('Content-Type: text/plain');
	echo "<DEBUG>\n";
}
?>
<!DOCTYPE html>
<html>

<head>
	<title>LTX GPS View V0.1b</title>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" integrity="sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A==" crossorigin="" />
	<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js" integrity="sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA==" crossorigin=""></script>

</head>

<body>
	<style>
		body {
			margin: 0;
			padding: 0;
		}

		#map {
			position: absolute;
			top: 0;
			bottom: 0;
			width: 100%;
		}

		#navi {
			position: absolute;
			right: 10px;
			top: 10px;
			width: 80%;
			z-index: 999;
			background: white;
			padding: 5px;
			border: 1px solid gray;
		}
	</style>

	<div id="map"></div>
	<div id="navi">
		<input id="posidx" oninput="slider()" type="range" min="0" max="1000" value="0" style="width: 99%" /><br>
		<span id="poslab"></span><br>
		<span id="posunits"></span><br>
		<span id="posdet"></span>
	</div>

	<script>
		var latlng = L.latLng(48.9463, 8.4079);
		var mymap = L.map('map').setView(latlng, 5);

		L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token={accessToken}', {
			maxZoom: 20,
			attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, ' +
				'<a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, ' +
				'Imagery © <a href="https://www.mapbox.com/">Mapbox</a>',
			id: 'mapbox/streets-v11',
			//accessToken: 'pk.eyJ1Ijoiam9lbWIiLCJhIjoiY2syb25qcmN4MGw2MjNtczdnb2tob3c1NiJ9.DZf2oLfpj9XSgWlogdlqJg'
			accessToken: 'pk.eyJ1Ijoiam9lbWJlZGRlZCIsImEiOiJjazZueXpzbWsweGRnM21xczQwaXFmZ2RzIn0.D0YYu24s_MTgg6ctAQZvXg'

		}).addTo(mymap);
		var points = [
			// {"t:"1521230123,  "co":[48.963211,8.403837] },
			// Hier Koordinaten ENDE

			<?php
			if (!@file_exists(S_DATA . "/$mac")) exit_error("Error (Directory/MAC not found)");
			//if(check_dirs()) exit_error("Error (Directory/MAC not found)");
			if (empty($fname)) exit_error("No Data (Need File)");

			$rfile = S_DATA . "/$mac/files/$fname";
			if (!file_exists($rfile)) {
				exit_error("ERROR: File not found: '$rfile' (maybe too old?)");
			}

			$data = file($rfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			if (count($data) < 1) exit_error("ERROR: File is empty: '$rfile')");

			$plat = -1;
			$plng = -1; // Inices der Koordianten
			$time = time();
			foreach ($data as $line) {
				if ($line[0] != '!') continue;
				$vals = explode(" ", $line);
				if ($line[1] == 'U') {
					$units = $line;	// Units merken
					$plat = -1;
					$plng = -1;
					foreach ($vals as $unip) {
						if (strpos($unip, ":Lat") > 0) $plat = intval($unip);
						else if (strpos($unip, ":Lng") > 0) $plng = intval($unip);
					}
					if ($dbg) {
						echo "DBG: Index plat/plng:$plat/$plng\n";
					}
				} else {
					if ($line[1] == '+') $time += intval(substr($line, 2));
					else $time = intval(substr($line, 1));

					if ($plat >= 0 && $plng >= 0) {
						unset($flat, $flng);
						foreach ($vals as $unip) {
							$iv = explode(":", $unip);
							if (count($iv) == 2) { // Sonst kein Array
								$iv0 = intval($iv[0]);
								if ($iv0 == $plat) {
									$flat = $iv[1];
								} else if ($iv0 == $plng) {
									$flng = $iv[1];
								}
							}
						}

						if (isset($flat, $flng)) {
							$timestr = date("d.m.Y H:i:s", $time);
							echo "{\"t\":\"$timestr\", \"co\":[\"$flat\",\"$flng\"], \"o\":\"$line\"}, \n";
						}
					}
				}
			}
			?>
			// Hier Koordinaten ENDE
		];
		var gps_points = [];

		for (var i = 0; i < points.length; i++) {
			var co = points[i].co;
			if (!isNaN(co[0]) && !isNaN(co[0])) {
				// console.log(points[i]); "4: Moves"
				var mvx = points[i].o.indexOf(" 4:");
				var mct = 0;
				if (mvx) {
					mct = parseInt(points[i].o.substr(mvx + 3));
				}
				gps_points.push(co);
				if (mct) {
					L.circle(co, 1, {
						color: 'red',
						opacity: 0.2,
						fillColor: 'red',
						fillOpacity: 0.8
					}).addTo(mymap); // .bindPopup("I am a circle.");
				} else {
					L.circle(co, 1, {
						color: 'green',
						opacity: 0.2,
						fillColor: 'green',
						fillOpacity: 0.8
					}).addTo(mymap); // .bindPopup("I am a circle.");
				}
			}
		}

		var polyline = L.polyline(gps_points, {
			color: 'red',
			opacity: 0.3
		}).addTo(mymap);

		if (gps_points.length > 0) {
			var markerx = L.marker(gps_points[gps_points.length - 1], {
				opacity: 0.9
			}).addTo(mymap);
			var marker = L.marker(gps_points[gps_points.length - 1], {
				opacity: 0.5
			}).addTo(mymap);
			mymap.fitBounds(polyline.getBounds());
			slider();
			document.getElementById("posunits").innerText = "<?php echo "$units"; ?>";
		}

		function slider() {
			var spos = document.getElementById("posidx").value;
			var anz = points.length;
			var idx = (spos / 1000 * anz).toFixed(0);
			if (idx > points.length - 1) idx = points.length - 1;
			var dc = points[points.length - 1 - idx].co;
			if (!isNaN(dc[0]) && !isNaN(dc[1])) {
				markerx.setLatLng(dc);
			}
			document.getElementById("poslab").innerText = "Position " + idx + " von " + anz + " (Lat,Lng: " + dc[0] + "," + dc[1] + ") [" + points[points.length - 1 - idx].t + "]";
			document.getElementById("posdet").innerText = points[points.length - 1 - idx].o;
		}
	</script>
</body>

</html>


</script>
</body>

</html>