# LTX Microcloud Legacy

**LEGACY ("TextOnly") version**

This is a reduced version of LTX Server. It uses only PHP and the server's filesystem to communicate with LTX LTraX loggers.

LTX can be installed in two modes:

- **LTX_Server**: with database support.
- **LTX_Legacy**: without database support.

In **LTX_Legacy** mode, all data is written to directories. Each device's new data is appended to `.../out_total/total.edt`. This file is plain text in `EDT` format and can become quite large over time.

Note: using `CS_VIEW.PHP` for graphs requires PHP's `gdlib` extension to be enabled.

Some very simple scripts allow rudimentary access to all device/logger functions, including secure FOTA updates and device label generation.

The input script `../sw/ltu_trigger.php` adds the data. Modify it as needed for your own requirements.

## Important

This repository (`LTX_Legacy`) is automatically generated and maintained by scripts. No feedback to issues, requests, or comments.

## Installation

1. Copy all files to your server. The server must run HTTP, by default on port 80. It is recommended to make the server reachable by HTTP and HTTPS under the same name (see step 5).

   Data transfer via HTTP for devices/loggers uses much less energy than HTTPS. Devices can optionally use HTTPS on request. HTTP is recommended for communication between server and loggers; HTTPS is recommended for user access.

2. Modify `./sw/conf/api_key.inc.php` as described in its comments. At minimum, change everything marked `*** SECRET ***`, set a secret data directory `S_DATA`, and define your own `L_KEY`.

3. Set the server name and path in the `sys_param.lxp` file on the devices/loggers.

4. Make a test transmission.

5. Log in to Legacy at `https://SERVER.XYZ/xxx/legacy/index.html`.

   Hint: for fast access, bookmark `https://SERVER.XYZ/xxx/legacy/index.php?k=YOURLKEY`, where `YOURLKEY` is your `L_KEY` from `api_key.inc.php`.

## LTX_Server Notes

In **LTX_Server** mode, all new data is written to the database. The quota limit is configured in `./sw/conf/api_key.inc.php` via `DB_QUOTA` (default: `"90\n1000"`). For each new logger, a `quota_days.dat` file with two or three lines is created automatically:

1. Number of days to keep, for example `90`.
2. Maximum number of database lines per device, for example `1000`.
3. Optional URL for PUSH notifications on new data. This is used only by `LTX_Server`.

The input script `sw/ltu_trigger.php` automatically removes older data. For example, set `DB_QUOTA` to `"365\n100000"` to keep only the last 365 days or a maximum of 100,000 lines per device. `quota_days.dat` can be changed per logger at any time.

LTX Microcloud adapts the maximum upload size for files with Autosync, such as logger data, to the network speed. `2G/LTE-M` is faster than `LTE-NB`. Configure the two `define()` values `MAXM_2GM` and `MAXM_NB`; the defaults are `20k` and `5k` bytes.

For rare transmission intervals with high logging intervals, increase these limits to ensure that all data is transferred. SSL encryption over slow connections such as `LTE-NB` might work, but is not recommended.

New in V2.23: by default, all devices use the same `D_API_KEY`. This is acceptable for small or closed systems. Larger systems can optionally use individual keys, attached to the MAC and checked via an external API.

Only for generating device labels and secure FOTA updates, the AES factory key for the device is required via external `KEY_SERVER_URL`.

---

## Third-party Software

- PHP QR Code: <https://sourceforge.net/projects/phpqrcode> (LGPL)

---

## Changelog

- V1.00 04.12.2020 Initial
- V1.01 06.12.2020 Checked for PHP 8 compatibility
- V1.10 09.01.2021 More docs added
- V1.11 16.03.2022 More docs added
- V1.50 08.12.2022 SWARM packet driver added
- V1.52 20.01.2023 ASTROCAST packet driver added
- V1.60 21.01.2023 Push URL added
- V1.76 06.06.2023 Access Legacy for admin users
- V1.77 28.06.2023 Added `sw/js/xtract_demo.html`: demo to access BLE_API data in IndexDB
- V1.79 05.10.2023 Added `CommandConfig` as new parameter in `iparam.lxp`
- V2.00 15.10.2023 Direct FTP/FTPSSL push via `CommandConfig` (only `LTX_Server`)
- V2.01 18.10.2023 Cosmetics and FTP push (only `LTX_Server`)
- V2.10 19.10.2023 Decoding of compressed lines, starting with `$` + Base64, added
- V2.20 02.11.2023 Legacy CSView UTF-8 cosmetics
- V2.21 04.11.2023 Added network details (2G/4G/...)
- V2.22 05.11.2023 Max. upload limit depending on network; set `MAXM_xx` in `api_key.inc.php`
- V2.23 25.11.2023 If `DAPIKEY_SERVER` is defined: individual external `D_API_KEY` check for each new device (only once)
- V2.31 13.05.2024 Drivers for SWARM (product shut down) and ASTROCAST removed
- V2.32 24.05.2024 Added drivers from ORBCOMM IGWS2 (INMARSAT)
- V2.53 25.06.2025 Added LoRaWAN support for ChirpStack V4 and TTN V3 (`lxu_ltxlora_v1.php`)
