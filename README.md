# LTX Microcloud **LEGACY** #
**LEGACY ('TextOnly') Version**

__This is a reduced version of LTX Server, only using PHP and the Server's Filesystem to communicate with LTX LTraX Loggers__

LTX can be installed WITH (named as "LTX_Server") Database and WITHOUT (named as "LTX_Legacy").

In case of "LTX_Legacy" all data will be sent to directories and ALL device's new data will
be added to a file '.../out_total/total.edt' for the device. This file is simple text ('EDT'-Format) 
and might become quite large over time ;-)
Note: using "CS_VIEW.PHP' for graphs requires PHP's gdlib extension enabled.

Some very simple scripts allow rudimentary access to all devices/loggers functions (as well as secure FOTA Updates and generating device Labels)

The input script '../sw/ltu_trigger.php' will add the data (feel free to modify it for your own requirements)

## Important: This repository ('LTX_Legacy') is automatically generated/maintained by scripts! No Feedback to Issues/Request/Comments ##

***Installation:*** 

 1. Simply copy all to your server, Server must run HTTP (by default port 80). It is a good idea to make the Server reachable by HTTP and HTTPS with the same name (see 5.).
 (Data transfer via HTTP for (devices/loggers) takes much less energy than HTTPS (optionally on request devices can also use HTTP-AES128-VPN or HTTPS).

 2. Modify './sw/conf/api_key.inc.php' as in comments (at least set a 'secret' data directory 'S_DATA' and an own 'L_KEY')

 3. Set your Server name and path in the 'sys_param.lxp' file on the devices/loggers.

 4. Make a test transmission
 
 5. Log in to Legacy 'https://SERVER.XYZ/xxx/legacy/index.html'
 (Hint: for fast access bookmark it like this: https://SERVER.XYZ/xxx/legacy/index.php?k=YOURLKEY)

_(Just as Info: In case of "LTX_Server" all new data will be written to the database. There is a quota limit in
'./sw/conf/api_key.inc.php' ("DB_QUOTA" with default "90\n1000"). A file 'quota_days.dat' with 2-3 lines
will automatically be written for each new logger, 1.st line are days (here 90), 2.nd line is lines (in the database, so even a small DB can hold thousands of devices).
The optional 3.rd line is an URL where to send a PUSH notification on new data (only used for LTX_Server).
The input script 'sw\ltu_trigger.php' will automatically remove older data.
Change e.g. to "365\n100000" to allow only the last 365 days or max. 100000 lines per device.
The file 'quota_days.dat' my be set to individual values per logger at any time.

LTX Microcloud adapts maximum upload size for files with Autosync (e.g. logger data) to Network speed (2G/LTE-M is faster than LTE-NB). Set the 2 defines() for "MAXM_2GM"/"MAXM_NB". Default 20k/5k Bytes.
For are transmission intervals at high logging intervals it should be increased to get always all data.
Please note: using SSL encryption if slow connections are enabled (LTE-NB) might work, but is not recommended.

New in V.23: By default all devices use the same D_API_KEY. This OK for small or closed systems. Optionally new devices can use individual keys (attached to MAC and checked via external API) for larger systems.

_(Only for generating device labels (and secure FOTA Updates) the AES-Factory-Key for the device via external 'KEY_SERVER_URL' is requred)_

---

## 3.rd Party Software ##
- PHP QR Code https://sourceforge.net/projects/phpqrcode License: LGPL

---

## Changelog ##
- V1.00 04.12.2020 Initial
- V1.01 06.12.2020 Checked for PHP8 compatibility
- V1.10 09.01.2021 More Docs added
- V1.11 16.03.2022 More Docs added
- V1.50 08.12.2022 SWARM Packet driver added
- V1.52 20.01.2023 ASTOROCAST Packet driver added
- V1.60 21.01.2023 Push-URL added 
- V1.76 06.06.2023 Access Legacy for Admin Users
- V1.77 28.06.2023 Added sw/js/xtract_demo.html: demo to access BLE_API data in IndexDB
- V1.79 05.10.2023 Added CommandConfig as new Parameter in 'iparam.lxp'
- V2.00 15.10.2023 Direct FTP/FTPSSL-Push via CommandConfig (only 'LTX_Server')
- V2.01 18.10.2023 Cosmetics and FTP-push (only 'LTX_Server')
- V2.10 19.10.2023 Decoding of compressed lines (starting with '$'+Base64) added
- V2.20	02.11.2023 Legacy CSView UTF-8 cosmetics
- V2.21	04.11.2023 Added Network Details (2G/4G/..) 
- V2.22 05.11.2023 Max. upload limit depending on Network, set defines(MAXM_xx) in 'api_key.inc.php!
- V2.23 25.11.2023 If DAPIKEY_SERVER defined: indivdual external D_API_KEY check for each NEW device  (only once)


