# Implementierungsplan: Headset Hookswitch Control (`ehookEnable`) pro Telefon

**Ziel:** In der SCCP-Manager-GUI pro Telefon einstellbar machen, ob **Headset Hookswitch Control** (EHS für Jabra/Plantronics) aktiv ist. Wert in MariaDB speichern und beim XML-Provisioning korrekt als `<ehookEnable>` in die `SEP*.cnf.xml` schreiben.

**Stand:** Analyse `sccp_manager` (FreePBX-Modul), Cisco 7975, chan-sccp / TFTP.

---

## 1. Ausgangslage

### Cisco-Verhalten
- Parameter: `<ehookEnable>0|1</ehookEnable>` im Block `<vendorConfig>` (bei manchen Firmware-Versionen zusätzlich auf Root-Ebene unter `<device>`).
- `0` = deaktiviert, `1` = aktiviert (Headset-Taste nimmt legt Anrufe an/ab).
- Am 7975 ist die Option im Telefonmenü oft **schreibgeschützt** → Steuerung nur per TFTP-XML.

### Ist-Zustand in `sccp_manager`
| Komponente | Status |
|------------|--------|
| GUI-Feld | **fehlt** |
| DB-Spalte `ehookenable` in `sccpdevice` | **fehlt** |
| `sccpgeneral.xml.v433` Eintrag | **fehlt** |
| Template `SEP0000000000.cnf.xml_7975_template` | **kein** `<ehookEnable>` |
| `xmlinterface.class.php` → `vendorConfig` | Generischer Mapper vorhanden (`strtolower(XML-Tag)` → DB-Spalte) |
| Andere Templates (791x SIP, 79df SIP) | haben `<ehookEnable>0</ehookEnable>` als Vorbild |

### Referenz: funktionierende Vendor-Felder (z. B. Display-Zeitplan)
Bereits implementiertes Muster (wird für `ehookEnable` wiederverwendet):

1. **Spalte** in `sccpdevice` (z. B. `displayontime`)
2. **GUI** in `conf/sccpgeneral.xml.v433` → `page_group name="sccp_dev_vendor_conf"`, `item type="IS" id="3"` (pro Gerät)
3. **Geräte-Formular** `views/form.adddevice.php` → `showGroup('sccp_dev_vendor_conf', …, 'vendorconfig', …)`
4. **Speichern** `sccpManTraits/ajaxHelper.php` → `saveSccpDevice()` liest `vendorconfig_{feldname}`
5. **XML** `xmlinterface.class.php` → `case 'vendorconfig':` mappt `ehookenable` → `<ehookEnable>`

---

## 2. Architektur-Übersicht

```
┌─────────────────┐     POST vendorconfig_ehookenable=on/off
│  GUI (pro Tel.) │
└────────┬────────┘
         ▼
┌─────────────────┐     REPLACE/UPDATE sccpdevice.ehookenable
│  sccpdevice DB  │     enum('on','off') oder tinyint 0/1
└────────┬────────┘
         ▼
┌─────────────────┐     createSccpDeviceXML() → create_SEP_XML()
│ xmlinterface    │     vendorConfig: ehookEnable ← ehookenable
└────────┬────────┘
         ▼
┌─────────────────┐     TFTP /tftpboot/SEP{MAC}.cnf.xml
│  Cisco-Telefon  │     Neustart / sccp reset
└─────────────────┘
```

**Datenfluss beim Speichern eines Telefons:**
- `form.adddevice.php` lädt Werte via `get_sccpdevice_byid` (direkt `sccpdevice`, nicht View).
- `saveSccpDevice()` schreibt nur **nicht-leere** Felder (`if (!empty($value))`) → Default-Handling beachten (siehe Phase 4).

**Datenfluss bei XML-Erzeugung:**
- `array_merge($data_values, $dev_config)` — Gerätewerte überschreiben Globals.
- `vendorConfig`-Schleife: `on`→`1`, `off`→`0`, sonst Literal.

---

## 3. Implementierungsphasen

### Phase 1 — Datenbank

**Dateien:** `module.xml`, `install.php`

1. In `module.xml` unter `<table name="sccpdevice">` ergänzen:
   ```xml
   <field name="ehookenable" type="string" default="off"/>
   <field name="_ehookenable" type="string" notnull="false"/>
   ```
   (Optional `_`-Spalte nur wenn chan-sccp-Kompatibilität wie bei anderen Feldern nötig.)

2. In `install.php` → `$sccpdevice_fields` (bzw. bestehendes Migrations-Array):
   ```php
   'ehookenable' => array(
       'create' => "enum('on','off') NOT NULL DEFAULT 'off'",
       'modify' => "enum('on','off')"
   ),
   '_ehookenable' => array('rename' => 'ehookenable'), // falls _-Konvention
   ```

3. **Migration ausführen:** `fwconsole ma install sccp_manager` oder Modul-Upgrade.

4. **Optional — Site-Default (Advanced):** Gleiches Feld in `server.advanced.php` / Tabellen-Default setzbar lassen (wie `settingsaccess`). Dafür reicht `updateTableDefaults()` — kein Extra-Code nötig, wenn GUI in `sccp_dev_vendor_conf` mit `id` passend zu Advanced-Formular bleibt.

**Hinweis `sccpdeviceconfig`-View:** Die View listet nicht alle Spalten explizit auf; Realtime nutzt sie. Für XML-Generierung wird **`get_sccpdevice_byid`** (`SELECT t1.*`) verwendet — neue Spalte ist dort automatisch sichtbar. View-Erweiterung nur nötig, wenn chan-sccp Realtime die Spalte braucht (prüfen nach Bedarf).

---

### Phase 2 — GUI (`sccpgeneral.xml.v433`)

In `page_group name="sccp_dev_vendor_conf"` (nach bestehenden Vendor-Feldern, `id="3"` = pro Gerät):

```xml
<item type="IS" id="3" seq="98">
    <name>ehookenable</name>
    <label>Headset Hookswitch Control (EHS)</label>
    <default>off</default>
    <button value="off">Disabled</button>
    <button value="on">Enabled</button>
    <help>
        Aktiviert die Headset-Hookswitch-Steuerung (EHS) für externe Headsets
        (z. B. Jabra, Plantronics). Am Telefon oft schreibgeschützt — Wert wird
        per TFTP-Provisioning gesetzt. Nur für unterstützte Modelle (z. B. 79xx/7975).
    </help>
</item>
```

**Modell-Einschränkung (optional, Phase 6):** Feld nur anzeigen wenn `type` in 7970, 7971, 7975, 796x, … — per JS in `sccp_manager.js` oder `class` + CSS, analog andere hardware-spezifische Felder.

---

### Phase 3 — Speichern (`ajaxHelper.php`)

**Prüfen:** `saveSccpDevice()` mappt bereits:
```php
if (!empty($get_settings["{$hdr_vendPrefix}{$key}"])) {
    $value = $get_settings["{$hdr_vendPrefix}{$key}"];
}
```
→ Formularname: `vendorconfig_ehookenable` mit Werten `on`/`off`.

**Anpassung empfohlen (Bugfix-Pattern):**
- Radio `off` wird aktuell evtl. **nicht** gespeichert, weil `!empty('off')` in PHP wahr ist — OK.
- Problem: Wenn Feld fehlt, bleibt alter DB-Wert bei `REPLACE` — Verhalten dokumentieren.
- Für konsistentes `off`: explizit `case 'ehookenable':` oder allgemeine Enum-Felder immer aus POST übernehmen (nicht nur `!empty`).

**Kein Eintrag in `sccpsettings` nötig** — reines `sccpdevice`-Feld (anders als `dev_messagesURL`).

---

### Phase 4 — XML-Templates

**Datei:** `conf/SEP0000000000.cnf.xml_7975_template` (und ggf. `797x`, `796x`, `7940` je nach Einsatz)

In `<vendorConfig>` einfügen (z. B. nach `disableSpeakerAndHeadset`):
```xml
<ehookEnable>0</ehookEnable>
```

**Wichtig:** Der generische `vendorConfig`-Handler in `xmlinterface.class.php` (ca. Zeile 302–330) setzt den Wert nur, wenn:
- Tag im Template existiert (`<ehookEnable>`),
- DB-Wert gesetzt und nicht leer,
- Mapping `ehookenable` → `ehookEnable` via `strtolower()`.

**Kein Eintrag in `$vendorFieldMap` nötig** (Name stimmt nach lowercasing überein).

**7975-Sonderfall:** In einer produktiven `SEP001122334455.cnf.xml` steht `<ehookEnable>1</ehookEnable>` am Ende von `vendorConfig`, obwohl das 7975-Template im Repo kein Tag hat — vermutlich manuell oder aus extended Template. Nach Implementierung kommt der Wert aus der DB.

---

### Phase 5 — XML-Generator (`xmlinterface.class.php`)

**Minimal:** Keine Code-Änderung, wenn Template + DB-Spalte + GUI stimmen.

**Empfohlene Robustheit (kleiner Patch):**
```php
// In vendorconfig-Schleife, vor dem switch:
if ($vtmp_data === 'NULL' || $vtmp_data === null || $vtmp_data === '') {
    continue; // Template-Default behalten
}
```
(Gleiches Problem wie bei Display-Feldern mit Literal-String `'NULL'` in der DB — siehe Abschnitt 5.)

**Optional:** `$vendorFieldMap` erweitern, falls Cisco-Tag anders heißt.

**SIP-Geräte:** `create_SEP_SIP_XML()` — prüfen ob `vendorConfig`-Case analog existiert; ggf. gleiche Logik.

---

### Phase 6 — Site-Defaults vs. pro Gerät

| Ort | Prefix | Wirkung |
|-----|--------|---------|
| Telefon bearbeiten | `vendorconfig_` | Wert in **Zeile** `sccpdevice` |
| Advanced → Device Vendor | `sccpdevice_` | **Spalten-Default** (`ALTER TABLE … SET DEFAULT`) |

**Empfehlung:** Dokumentieren in GUI-Hilfetext. Optional: Bei neuem Gerät `getTableDefaults('sccpdevice')` für Anzeige nutzen (bereits so).

---

### Phase 7 — Tests

1. **DB:** Nach Speichern `SELECT name, ehookenable FROM sccpdevice WHERE name='SEP…'`.
2. **XML:** `grep ehookEnable /tftpboot/SEP….cnf.xml` → `0` oder `1`.
3. **Telefon:** `asterisk -rx "sccp reset SEP…"` → Einstellungen → Headset Hookswitch Control.
4. **Regression:** Anderes Vendor-Feld (`webaccess`) unverändert.
5. **PHPUnit/Smoke:** Optional kleiner Test in `tests/` falls vorhanden.

---

### Phase 8 — Datenbereinigung (einmalig, optional)

Falls Altbestand mit manuell gesetzten Werten:
```sql
UPDATE sccpdevice SET ehookenable = 'on' WHERE name IN ('SEP001122334455', ...);
```
Danach `fwconsole reload` / XML neu generieren (Gerät speichern oder Bulk-Regenerate falls vorhanden).

---

## 4. Dateien-Checkliste

| Datei | Aktion |
|-------|--------|
| `module.xml` | Spalte `ehookenable` |
| `install.php` | Migration `ehookenable` |
| `conf/sccpgeneral.xml.v433` | GUI `IS` in `sccp_dev_vendor_conf` |
| `conf/SEP0000000000.cnf.xml_7975_template` | `<ehookEnable>0</ehookEnable>` |
| `conf/SEP0000000000.cnf.xml_797x_template` | ggf. gleich |
| `sccpManTraits/ajaxHelper.php` | ggf. Enum-Speichern verbessern |
| `sccpManClasses/xmlinterface.class.php` | ggf. `'NULL'`-Guard |
| `i18n/sccp_manager.pot` | Neue Strings (optional) |

**Nicht zwingend:** `form.adddevice.php` (nutzt bereits `sccp_dev_vendor_conf`), `dbinterface.class.php` (`get_sccpdevice_byid` = `SELECT *`).

---

## 5. Bekannte Stolpersteine (aus Display-Zeitplan-Analyse)

Diese Punkte beim Implementieren von `ehookEnable` **von Anfang an vermeiden**:

1. **Literal `'NULL'` in DB:** `module.xml` nutzt `default="NULL"` als XML-Attribut — darf nicht als String in Gerätereihen landen. Speichern und Migration prüfen.
2. **`saveSccpDevice` + `!empty($value)`:** Leere/`off`-Werte können fehlen; explizit alle Enum-Felder aus Request übernehmen.
3. **Advanced vs. pro Gerät:** Column-Default in Advanced betrifft **nicht** bestehende Zeilen — nur neue Inserts / NULL-Spalten.
4. **`sccpdeviceconfig`-View:** Enthält nicht alle Vendor-Spalten; XML-Gen nutzt direkte Tabelle — OK für uns.
5. **Template muss Tag enthalten:** Ohne `<ehookEnable>` im Template wird der Mapper den Knoten **nicht** anlegen (nur bestehende Kinder werden iteriert).

---

## 6. Aufwandsschätzung

| Phase | Aufwand |
|-------|---------|
| DB + module.xml + install | ~30 Min |
| GUI + Templates | ~30 Min |
| ajaxHelper / xmlinterface Hardening | ~45 Min |
| Test am 7975 | ~30 Min |
| **Gesamt** | **~2–3 h** |

---

## 7. Ausführungsreihenfolge (für späteren PR)

1. Branch anlegen
2. Phase 1 (DB) + `fwconsole ma install sccp_manager`
3. Phase 2 + 4 (GUI + Templates)
4. Phase 3 + 5 (Save/XML robustness)
5. Ein Testtelefon konfigurieren, XML prüfen, `sccp reset`
6. Optional: SQL-Update für Bestandsgeräte mit gewünschtem Default `on`

---

## 8. Referenzen im Repo

- `Technical.notes/SEP0000000000.cnf.xml_annotated` — Dokumentation `ehookEnable`
- `sccpManClasses/xmlinterface.class.php:302` — `vendorconfig`-Mapper
- `sccpManTraits/ajaxHelper.php:694` — `saveSccpDevice()`
- `views/form.adddevice.php:132` — Vendor-Config-Gruppe
- `conf/sccpgeneral.xml.v433:526` — `sccp_dev_vendor_conf` (Display-Felder als Vorbild)
