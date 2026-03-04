# Dokumentation `sw/lxu_v1.php` (LTX Server-Protokoll V1)

## Vorwort: Vorteile eines gespiegelten Dateisystems

Das Spiegeln des JES-FS (Dateisystem der IoT-Logger) auf den Server schafft eine robuste, nachvollziehbare und automatisierbare Betriebsgrundlage:

1. **Asynchrone Verarbeitung und Entkopplung**  
   Logger und Backend werden zeitlich entkoppelt. Daten und Dateistände liegen serverseitig vor, auch wenn Geräte später offline sind.
2. **Inkrementelle Übertragung statt Vollabzug**  
   Durch Metadaten (`.vmeta`, `.fmeta`) kann der Server gezielt nur fehlende Segmente anfordern. Das reduziert Mobilfunkvolumen und Laufzeit.
3. **Revisionssicherheit und Fehlertoleranz**  
   Backup/Append-Logik, Retry-Dateien („telomeric requests“) und CRC-Prüfung machen den Transfer robust gegen Verbindungsabbrüche, doppelte Pakete und Teilübertragungen.
4. **Bidirektionale Datei-Orchestrierung**  
   Neben dem Spiegeln (Device ➜ Server) lassen sich Dateien vom Server auf das Gerät ausrollen (`put`) und löschen (`del`) – mit Quittierungslogik über Stages/Connection-ID.
5. **Bessere Diagnosefähigkeit**  
   Verbindungszustände, Quoten, User-Kommandos, Funkzelleninfos und Delta-Fragmente werden persistent abgelegt und sind für Betrieb/Support auswertbar.

---

## 1. Zweck und Rolle von `lxu_v1.php`

`sw/lxu_v1.php` ist der zentrale HTTP-Endpunkt für den Datenaustausch zwischen LTrax-Loggern und Server. Das Skript:

- authentifiziert Gerät + API-Key,
- parst ein binäres Multi-Command-Protokoll mit CRC pro Block,
- schreibt Spiegel-/Statusdaten in den Gerätebaum unter `S_DATA/<MAC>/...`,
- generiert im gleichen Request serverseitige Antworten (Kommandoblöcke),
- führt Retry-/Quittungslogik über Stage-Transfers,
- triggert nach abgeschlossener Übertragung optional ein asynchrones Folge-Skript.

Der Request transportiert Rohdaten in `$_FILES['X']`; Identifikation erfolgt über GET-Parameter:

- `k`: API-Key,
- `s`: MAC (16 Zeichen),
- `v`: optionaler VPN-Forward-Flag.

---

## 2. Verzeichnis- und Dateimodell pro Gerät

Die Struktur wird über `check_dirs()` (aus `sw/lxu_loglib.php`) bereitgestellt:

- `S_DATA/log/` – globales Serverlog,
- `S_DATA/<MAC>/cmd/` – Server-Steuerdateien, Meta-Dateien,
- `S_DATA/<MAC>/files/` – eigentlicher Dateispiegel des Device-FS,
- `S_DATA/<MAC>/get/` – Pull-Warteschlange: Device-Dateien anfordern,
- `S_DATA/<MAC>/put/` – Push-Warteschlange: Dateien zum Device senden,
- `S_DATA/<MAC>/del/` – Delete-Warteschlange: Dateien am Device löschen,
- `S_DATA/<MAC>/in_new/` – eingehende Fragmente/Ereignisartefakte,
- `S_DATA/<MAC>/dbg/` – optionale Roh-/Debugablagen.

Initial für neue Geräte werden standardmäßig Requests angelegt (`cmd/getdir.cmd`, `get/sys_param.lxp`), um den Spiegelaufbau zu starten.

---

## 3. Protokollrahmen (binär)

### 3.1 Eingehende Blöcke (Device ➜ Server)

Jeder Block ist aufgebaut als:

- `CMD` (1 Byte)
- `LEN` (4 Byte, Big Endian)
- `PAYLOAD` (`LEN` Bytes)
- `CRC32` (4 Byte, Big Endian)

CRC-Bildung im Skript: `~crc32(CMD+LEN+PAYLOAD) & 0xFFFFFFFF`.

Ein Stream enthält mehrere Blöcke und endet bei `CMD == 0xFF` oder Dateiende.

### 3.2 Ausgehende Blöcke (Server ➜ Device)

Gleiches Grundschema. Der Server hängt am Ende zusätzlich an:

- `0xFE:More` wenn weitere Runden erwartet werden (`$expmore=1`),
- `0xFF:Done` wenn Transferzyklus abgeschlossen ist.

Vor den Binärblöcken sendet der Server immer zuerst eine Textzeile: `OK(Id:<conid>)` bzw. Fehler.

---

## 4. Authentifizierung, Initialisierung, Laufzeitstatus

1. MAC-Länge muss exakt 16 sein.
2. API-Key wird zuerst lokal gegen `dapikey.dat` geprüft.
3. Falls kein Treffer: optionaler externer Check via `conf/check_dapikey.inc.php`, danach optionales Persistieren.
4. Rohpayload wird geladen, Mindestlänge geprüft.
5. `device_info.dat` wird als Key-Value-Store geladen und am Ende vollständig zurückgeschrieben.
6. Tagesquoten (`quota_in`, `quota_out`) werden pro UTC-Tag fortgeschrieben.

---

## 5. Eingehende Kommandos (A0–A7)

### 5.1 `0xA0` CHEAD

Enthält Stage, Device-Zeit und `last_result`.

- Stage 0 markiert den Start einer Übertragungsrunde.
- `conns` wird in Stage 0 erhöht; daraus wird `conid` abgeleitet.
- Bei offenem `expmore` aus vorheriger Runde wird „incomplete transfer“ protokolliert.
- Zeitdelta `sdelta` wird gepflegt.

### 5.2 `0xA1` CHELLO

Liefert Gerätetyp, Firmwareversion, Cookie und Verbindungsgrund.

- `reason` wird dekodiert (AUTO/MANUAL/START + RESET/ALARM-Bits).
- Werte werden in `device_info.dat` persistiert.

### 5.3 `0xA2` CDISKINFO

Liefert Device-FS-Metadaten (Modus, Größe, frei, Formatdatum).

- Bei `dmode == 255` wird ein kompletter Verzeichnis-Scan signalisiert:
  - alte `.vmeta` in `cmd/` werden gelöscht,
  - `cmd/getdir.cmd` wird entfernt,
  - `dirtime` wird auf Serverzeit gesetzt.

Damit wird ein alter Verzeichniszustand invalidiert und sauber neu aufgebaut.

### 5.4 `0xA3` CDIRENTRY (zentral für Spiegelplanung)

Für jede Datei sendet das Device Flags, Länge, CRC, Datum, Dateiname.

Serveraktion:

1. `.vmeta` schreiben (`vd_flags`, `vd_len`, `vd_crc`, `vd_date`, `vd_dir`).
2. Bei Sync-Flag (`fflags & 64`) wird entschieden, ob Daten angefordert werden müssen:
   - Vergleich mit lokaler `.fmeta` (Dateidatum/Länge),
   - fehlende Bytes werden als `C0`-Request geplant,
   - bei Größenlimits ggf. auf letzten Bereich gekappt,
   - optional segmentierter Mehrfachabruf über `get/<fname>` (wenn `MXGET_MEM` definiert).

Ergebnis: CDIRENTRY ist der eigentliche „Soll-Ist-Abgleich“ zwischen JES-FS und Spiegel.

### 5.5 `0xA4` CFILE_DATA (zentral für Spiegelaufbau)

Transportiert Dateidaten inklusive Offset (`pos0`) und Dateizeit.

Serveraktion:

- schreibt/appendet in `files/<fname>` (bei Neuschreiben vorher `.bak`),
- erkennt Gaps, Duplikate, Overlaps und protokolliert diese,
- aktualisiert `.fmeta` (`flags`, `len`, `date`, optional `pos0`),
- löscht ggf. offene `get/<fname>`-Anforderung,
- schreibt Delta-Fragment zusätzlich nach `in_new/<timestamp>_<fname>`.

So entsteht der tatsächliche Dateispiegel auf dem Server.

### 5.6 `0xA5` CSIGNAL_SG3G

Funkzell-/Signalinfos werden nach `conn_log.txt` geschrieben und in `device_info.dat` übernommen.

### 5.7 `0xA6` User-Info

Text-Userdaten werden in `user_contents.txt`, `userio.txt` und als Eventdatei in `in_new/` abgelegt.

### 5.8 `0xA7` IMSI/ICCID

String wird als `imsi` gespeichert.

---

## 6. Wie das JES-FS auf den Server gespiegelt wird (Kernablauf)

Dieser Abschnitt beschreibt den vollständigen Mirror-Mechanismus End-to-End.

### Phase 0: Verzeichniszustand erfassen

1. Server fordert ggf. per `getdir.cmd` einen kompletten Verzeichnislauf an (`C4`, Mode 255).
2. Device sendet `A2` + viele `A3`.
3. Server schreibt für jede Device-Datei eine `.vmeta`.

**Effekt:** Server kennt danach den Soll-Zustand des JES-FS.

### Phase 1: Bedarf je Datei bestimmen

Für jede `A3` mit Sync-Flag:

- Existiert keine passende lokale `.fmeta` ➜ komplette Datei anfordern.
- Ist Datei gewachsen ➜ nur den fehlenden Endbereich anfordern.
- Ist lokale Länge größer oder Datum anders ➜ Datei neu anfordern.
- Überschreitet die Differenz das Blocklimit ➜ begrenzter/segmentierter Abruf.

Die Requests werden als `C0`-Blöcke in die Antwort gelegt; `expmore=1` hält den Dialog offen.

### Phase 2: Nutzdaten übernehmen

Bei `A4`:

- Konsistenzprüfung über `pos0` gegenüber lokalem Spiegelstand,
- `wb` bei neuer Datei / `ab` bei korrektem Anschluss,
- Aktualisierung von `.fmeta`,
- optionales Fortschreiben eines segmentierten `get`-Status (Pos/Restlen/Blocklen),
- Löschen der Anforderung nach Abschluss.

### Phase 3: Abschluss und Persistenz

- Wenn keine offenen Transfers mehr: `0xFF:Done`, sonst `0xFE:More`.
- `device_info.dat` wird aktualisiert (`expmore`, Quoten, Statistik).
- Trigger `lxu_trigger.php` wird nur bei abgeschlossenem Transfer gestartet (`!expmore`).

### Wichtige Robustheitsmechanismen

- **CRC pro Block** verhindert stilles Übernehmen korrupten Payloads.
- **Stage/Connection-ID** verhindert verfrühte Quittierung bei `put/del/usercmd`.
- **Telomerische Retry-Dateien** (`cmd/*.cmd`, `get/*`, `del/*`) begrenzen Wiederholungen.
- **`.bak` + Fragmentablage** erleichtern Recovery und Nachanalyse.

---

## 7. Serverseitige Gegenrichtung (kurz)

Obwohl Fokus auf Spiegelung JES-FS ➜ Server liegt, nutzt dasselbe Protokoll auch Steuerung in Gegenrichtung:

1. `server.cmd`: 1-Byte-Flagkommando (`C3`) mit Retry.
2. Firmware `_firmware.sec` via `C1` mit eigener Quittungslogik.
3. `getdir.cmd` via `C4`.
4. `get/*` via `C0` (Datei vom Device holen).
5. `del/*` via `C5` (Datei auf Device löschen).
6. `put/*` via `C1` (Datei auf Device schreiben).
7. `usercmd.cmd` via `C6`.

Priorität: `server.cmd` > Firmware > `getdir` > `get` > `del` > `put` > `usercmd`.

---

## 8. Relevante Metadateien im Spiegelbetrieb

- `cmd/<file>.vmeta`: letzter bekannter Device-Verzeichniszustand.
- `cmd/<file>.fmeta`: letzter serverseitig gespeicherter Dateistand.
- `get/<file>`: Retry-/Fortschrittsstatus für Pulls (optional mit Pos/Len/Blocksize).
- `cmd/<file>.pmeta`: Zustandsautomat für Push (`put`).
- `cmd/<file>.dmeta`: Zustandsautomat für Delete (`del`).
- `device_info.dat`: globaler Gerätezustand/Statistik.

---

## 9. Bekannte Besonderheiten aus dem Code

- Dateiname mit `.php` wird serverseitig zu `<name>.php_` umgebogen (Sicherheitsmaßnahme).
- Bei kleiner Antwort ohne offene Folgeaktion sendet der Server mindestens `C2` (Serverzeit).
- Optionaler Quectel-Workaround: zusätzlicher Dummy-Text bei Firmwaretransfer.
- Trigger-Aufruf erfolgt per CURL kurz angebunden (Timeouts), Fehler werden geloggt.

---

## 10. Zusammenfassung

`lxu_v1.php` implementiert kein simples Upload-Skript, sondern ein zustandsbehaftetes Datei-Synchronisationsprotokoll mit robustem Retry-/Quittungsmodell. Die JES-FS-Spiegelung basiert auf:

1. Verzeichnis-Sollzustand (`A2/A3` ➜ `.vmeta`),
2. differenzieller Nachforderung (`C0`),
3. konsistenter Übernahme (`A4` ➜ `files/` + `.fmeta` + `in_new/`),
4. zyklischer Abschlusssteuerung über `More/Done` und Stage-Logik.

Damit ist ein bandbreiten- und fehlertoleranter Betrieb über instabile Mobilfunkstrecken möglich.
