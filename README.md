# LTX LTraX Server Software **LEGACY** #
**LEGACY ('TextOnly') Version**

__This is a reduced version of LTX Server, only using PHP and the Server's Filesystem to communicate with LTX LTraX Loggers__

LTX can be installed WITH (named as "LTX_server") Database and WITHOUT (named as "LTX_legacy").

In case of "LTX_legacy" all data will be sent to directories and ALL device's new data will
be added to a file ".../out_total/total.edt" for the device. 
This file is simple text ('EDT'-Format) and might become quite large oer time ;-)
The input script 'sw\ltu_trigger.php' will add the data.

_(Just as Info: In case of "LTX_server" all new data will be written to the database. There is a quota limit in
'sw\conf\api_key.inc.php' ("DB_QUOTA" with default "90\n1000"). A file 'quota_days.dat' with 2 lines
will automatically be written for each new logger, 1.st line are days (here 90), 2.nd line is lines (in the database).
The input script 'sw\ltu_trigger.php' will automatically remove older data. )_

---

## Changelog ##
- V1.00 04.12.2020 Initial
- V1.01 06.12.2020 Checked for PHP8 compatibility
