<?php
	// ---- Blanco File for CUSTOMERS ---
	// api_key.php - Include File, holding API-Keys. 'include'
	// Key stored as defines to prevent access via webserver
	
	// *** Change Directory for S_DATA and internal access key to prevent external call of scripts!!! ***
	define ("S_API_KEY","xSintXtl"); 	// This the Server's Internal-API_KEY (for triggers, auto-cleanup,..) (keep secret!!!)
	define ("S_DATA","../data_secret");	// Server's ***SECRET*** data directory (could be secret or (later) dynamic (e.g. from database)
	
	define ("DB_QUOTA","90\n1000"); // Default Quota for new Devices (if Database is used: 'Days\nLines')
	define ("L_KEY","LegacyLTX");	// legacyKey for ***LEGACY Login***
	define ("MAXM_2GM", 20000);	// max. auto upload limit for 2G/LTE-M
	define ("MAXM_NB", 5000);	// LTE-NB is slow
	// Optionally (if GPS_VIEW used):
	define ("MAPKEY","pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpejY4NXVycTA2emYycXBndHRqcmZ3N3gifQ.rJcFIG214AriISLbB6B5aw"); // Key for MAPBOX.COM Mapserver, Request an OWN one if necessary!

	// *** Change the following Key ONLY after checking with *** joembedded.de, (xxx): Set Values to "" if not in use***
	// Key to access AES-Factory-Key-Server for (own) Firmware Updates and Devices
	define ("KEY_API","SEC_MacCheck"); // Key to access AES-Factory-Key-Server Firmware Update (xxx)
	define ("KEY_API_GL","Leg1310LX"); // Key to generate Badge (xxx) 
	define ("KEY_SERVER_URL","https://joembedded.de/x3/sec/maccheck.php");
	
	// A Public Geoserver from JoEmbedded with limited access (1000 calls/day) Free for use 
	define ("G_API_KEY","TESTTOKEN"); // This the API_KEY to access the GeoApi
	define ("CELLOC_SERVER_URL","https://flexgate.org/ltx_api/gcells/gcells.php"); // Public implementation

	// Only change with changes in the Firmware:
	define ("D_API_KEY","LX1310"); // This the DEVICE-API_KEY to access the Server (used by Device's Firmware)
?>
