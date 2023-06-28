# LTX Microcloud **LEGACY** #
**LEGACY ('TextOnly') Version**

__This is a reduced version of LTX Server, only using PHP and the Server's Filesystem to communicate with LTX LTraX Loggers__

LTX can be installed WITH (named as "LTX_Server") Database and WITHOUT (named as "LTX_Legacy").

In case of "LTX_Legacy" all data will be sent to directories and ALL device's new data will
be added to a file '.../out_total/total.edt' for the device. This file is simple text ('EDT'-Format) 
and might become quite large over time ;-)

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
'./sw/conf/api_key.inc.php' ("DB_QUOTA" with default "3650\n100000000"). A file 'quota_days.dat' with 2-3 lines
will automatically be written for each new logger, 1.st line are days (here 3650), 2.nd line is lines (in the database).
The optional 3.rd line is an URL where to send a PUSH notification on new data (only used for LTX_Server).
The input script 'sw\ltu_trigger.php' will automatically remove older data.
Change e.g. to "90\n1000" to allow only the last 90 days or max. 1000 lines per device (so even a small DB can hold thousands of devices).
The file 'quota_days.dat' my be set to individual values per logger at any time. )_

_(Only for generating device labels (and secure FOTA Updates) the AES-Factory-Key for the device via external 'KEY_SERVER_URL' is requred)_

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

