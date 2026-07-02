# Änderungsübersicht sccp_manager (Stand 30.06.2026)

Diese Datei dokumentiert alle lokalen Änderungen am Modul `sccp_manager`, die im Entwicklungsstand gegenüber dem letzten Git-Commit (`5752d9f`, 2022-01-30) vorgenommen wurden. Der Schwerpunkt der Session vom 30.06.2026 war die **SEP-Tausch-Funktion**; zusätzlich sind weitere Verbesserungen und Features im Working Tree enthalten.

**Statistik:** 23 geänderte Dateien, ca. +1407 / −218 Zeilen; 7 neue Dateien.

---

## Inhaltsverzeichnis

1. [SEP-Tausch (Telefon austauschen)](#1-sep-tausch-telefon-austauschen)
2. [ehookEnable – Headset Hookswitch Control](#2-ehookenable--headset-hookswitch-control)
3. [Named Groups (Call/Pickup Groups)](#3-named-groups-callpickup-groups)
4. [SCCP-Leitung bearbeiten](#4-sccp-leitung-bearbeiten)
5. [Button-Konfiguration (GUI-Robustheit)](#5-button-konfiguration-gui-robustheit)
6. [XML-Provisioning & Vendor-Config](#6-xml-provisioning--vendor-config)
7. [PHP 8.2 / FreePBX-Kompatibilität](#7-php-82--freepbx-kompatibilität)
8. [AMI / chan-sccp Interface](#8-ami--chan-sccp-interface)
9. [Installer & Datenbank](#9-installer--datenbank)
10. [Weitere Dateien](#10-weitere-dateien)
11. [Neue Dokumentation](#11-neue-dokumentation)
12. [Testempfehlungen](#12-testempfehlungen)

---

## 1. SEP-Tausch (Telefon austauschen)

**Zweck:** Cisco-SCCP-Telefone (SEP/ATA/VG) tauschen — entweder zwei vorhandene Geräte gegeneinander oder ein vorhandenes gegen ein neues ersetzen. Vollständige Migration von `sccpdevice` + `sccpbuttonconfig`.

**Detaildokumentation:** [Implementierungsplan_SEP_Tausch.md](Implementierungsplan_SEP_Tausch.md)

### Neue Datei

| Datei | Beschreibung |
|-------|--------------|
| `sccpManTraits/deviceSwap.php` | Service-Trait mit Validierung, Transaktionen, Swap/Replace, TFTP/AMI-Nachbearbeitung |

### Geänderte Dateien

| Datei | Änderung |
|-------|----------|
| `Sccp_manager.class.php` | `use deviceSwap`-Trait eingebunden |
| `sccpManTraits/ajaxHelper.php` | AJAX-Commands `preview_swap_device`, `swap_device` in `ajaxRequest()` und `ajaxHandler()` |
| `views/hardware.phone.php` | Toolbar-Button **Exchange SEP**, Bootstrap-Modal (3 Schritte: Modus → Geräte → Vorschau) |
| `assets/js/sccp_manager.js` | Wizard-Logik (~260 Zeilen): Gerätelisten aus Grid, Preview-AJAX, Ausführung, Vorausfüllung bei Grid-Selektion |

### Funktionsumfang

| Modus | Parameter | Verhalten |
|-------|-----------|-----------|
| `swap` | `source`, `target` | Konfiguration zweier DB-Geräte tauschen (PDO-Transaktion) |
| `replace` | `source`, `target`, `old_action` | Config von Quelle auf Ziel kopieren; `keep` = Quelle unverändert, `delete` = Quelle löschen + `homedevice`-Update |

### Wichtige Methoden (`deviceSwap.php`)

- `normalizeSccpSepId()` / `isValidNativeSccpSepId()` — SEP/ATA/VG-Formatierung
- `getDeviceFullConfig()` / `applyDeviceFullConfig()` — Lesen/Schreiben inkl. Buttons (`clear`+`add`)
- `swapSccpDeviceConfigs()` / `replaceSccpDevice()` — Geschäftslogik
- `validateSwapRequest()` / `previewSwapDevice()` — Validierung + Warnungen
- `provisionSwappedDevices()` — `createSccpDeviceXML()` + `sccpDeviceReset()`
- `deleteSccpDeviceXmlFile()` — TFTP-Löschung für SEP/ATA/VG (nicht nur SEP-Prüfix wie in `deleteSccpDeviceXML()`)

### Bedienung (GUI)

1. **Phones Manager → SCCP Phone**
2. Button **Exchange SEP**
3. Modus wählen, Quelle/Ziel festlegen
4. Vorschau prüfen, Checkbox setzen, **Execute exchange**

---

## 2. ehookEnable – Headset Hookswitch Control

**Zweck:** Pro Telefon steuerbar, ob **Headset Hookswitch Control** (EHS für Jabra/Plantronics) aktiv ist — Wert in DB und als `<ehookEnable>` in der TFTP-XML.

**Planungsdokument:** [Implementierungsplan_ehookEnable_pro_Telefon.md](Implementierungsplan_ehookEnable_pro_Telefon.md)

### Geänderte Dateien

| Datei | Änderung |
|-------|----------|
| `module.xml` | Spalten `ehookenable`, `_ehookenable` in `sccpdevice` |
| `install.php` | Migration für neue Spalten |
| `conf/sccpgeneral.xml.v433` | GUI-Eintrag unter `sccp_dev_vendor_conf` |
| `conf/SEP0000000000.cnf.xml_7975_template` | `<ehookEnable>` ergänzt |
| `conf/SEP0000000000.cnf.xml_797x_template` | `<ehookEnable>` ergänzt |
| `conf/SEP0000000000.cnf.xml_796x_template` | Anpassung Template |
| `sccpManTraits/ajaxHelper.php` | `ehookenable` in `$vendorEnumFields` — explizites Speichern auch bei `off` |
| `sccpManClasses/xmlinterface.class.php` | Vendor-Mapping `ehookenable` → `ehookEnable` |

---

## 3. Named Groups (Call/Pickup Groups)

**Zweck:** Verwaltung benannter Gruppen für Call Groups / Pickup Groups (chan-sccp Named Groups).

### Neue Dateien

| Datei | Beschreibung |
|-------|--------------|
| `views/advserver.namedgroups.php` | GUI: Bootstrap-Table, Add/Edit-Modals |

### Geänderte Dateien

| Datei | Änderung |
|-------|----------|
| `module.xml` | Neue Tabelle `sccpnamedgroups` (id, groupname, grouptype, description, created_on) |
| `install.php` | Tabellenanlage/Migration |
| `Sccp_manager.class.php` | Tab **Named Groups** unter System Parameters (`advServerShowPage`) |
| `sccpManTraits/ajaxHelper.php` | CRUD: `getNamedGroups`, `addNamedGroup`, `updateNamedGroup`, `deleteNamedGroup` |
| `sccpManClasses/Sccp.class.php.v433` | Nutzung von `getNamedGroup()` im FreePBX-Driver |
| `sccpManClasses/dbinterface.class.php` | DB-Zugriff Named Groups |

---

## 4. SCCP-Leitung bearbeiten

**Zweck:** Direktes Bearbeiten von SCCP-Leitungen (`sccpline`) aus dem Phones Manager.

### Neue Datei

| Datei | Beschreibung |
|-------|--------------|
| `views/form.editline.php` | Formular zum Bearbeiten einer SCCP-Extension |

### Geänderte Dateien

| Datei | Änderung |
|-------|----------|
| `Sccp_manager.class.php` | Routing `tech_hardware=sccp_custom` + `extdisplay` → `form.editline.php` |
| `sccpManTraits/ajaxHelper.php` | `saveSccpLine()` — Mapping Formularfelder → `sccpline`-Spalten |
| `sccpManTraits/bmoFunctions.php` | POST-Handler `category=edit_sccp_line` |

---

## 5. Button-Konfiguration (GUI-Robustheit)

**Zweck:** Korrekte Anzeige und Validierung der Button-Konfiguration in der GUI, unabhängig davon ob die DB `instance` 0-basiert (chan-sccp) oder 1-basiert (Manager-Save) verwendet.

### Geänderte Dateien

| Datei | Änderung |
|-------|----------|
| `views/form.buttons.php` | Button-Typ `service` ergänzt; Index-Mapping 0/1-basiert; Validierung ungültiger DB-Zeilen; Hilfsfunktionen für Line-Parsing |
| `Sccp_manager.class.php` | Anpassungen an `getPhoneButtons()` / Button-Parsing |
| `assets/js/sccp_manager.js` | JS-Unterstützung für Button-UI |

---

## 6. XML-Provisioning & Vendor-Config

**Zweck:** Robustere SEP-XML-Generierung, korrekte Vendor-Defaults, Display-Zeitplan.

### Geänderte Dateien

| Datei | Änderung |
|-------|----------|
| `sccpManClasses/xmlinterface.class.php` | Null-Safe Zugriffe; `shouldWriteVendorValue()`; `daysdisplaynotactive`-Handling; Template-Pfad korrigiert; NTP-Fallbacks; diverse Vendor-Felder |
| `sccpManTraits/helperFunctions.php` | `applySccpDeviceTableDefaults()` — globale Spalten-Defaults bei XML-Gen; `daysdisplaynotactive`: leer = jeden Tag (nicht `0`) |
| `Sccp_manager.class.php` | `createSccpDeviceXML()` nutzt `applySccpDeviceTableDefaults()` |

---

## 7. PHP 8.2 / FreePBX-Kompatibilität

**Zweck:** Warnungen und Fehler unter PHP 8.2 beheben; Vorbereitung FreePBX 17 / Asterisk 22.

### Geänderte Dateien

| Datei | Änderung |
|-------|----------|
| `module.xml` | `<phpversion>8.2</phpversion>` |
| `install.php` | Umfangreiche Anpassungen: anonyme Installer-Klasse, PDO-Binding, Array-Vergleiche, Migrationen |
| `sccpManTraits/helperFunctions.php` | Null-Coalescing (`??`); String-Interpolation; `ensureSccpSettingsComplete()` |
| `sccpManClasses/formcreate.class.php` | PHP 8.2-kompatible Patterns |
| `sccpManClasses/dbinterface.class.php` | Typisierung, robustere Queries |
| `sccpManClasses/extconfigs.class.php` | Kleinere Fixes |
| `sccpManClasses/Sccp.class.php.v433` | Driver-Anpassungen FreePBX 17 |

### Referenzdokumente (Root)

| Datei | Inhalt |
|-------|--------|
| `QUICK_REFERENCE_CODECHANGES.md` | Schnellreferenz Zeilendifferenzen |
| `KONKRETE_CODEAENDERUNGEN_PHP82_AST22.md` | Ausführliche PHP 8.2 / Asterisk 22 Änderungen |

---

## 8. AMI / chan-sccp Interface

### Geänderte Dateien

| Datei | Änderung |
|-------|----------|
| `sccpManClasses/aminterface.class.php` | Erweiterungen AMI-Kommunikation |
| `sccpManClasses/amInterfaceClasses/Message.class.php` | Parsing/Robustheit |
| `sccpManClasses/amInterfaceClasses/Response.class.php` | Response-Handling verbessert |

---

## 9. Installer & Datenbank

### `module.xml` – Schema-Erweiterungen

- `sccpdevice.ehookenable` / `_ehookenable`
- Tabelle `sccpnamedgroups`

### `install.php` – wesentliche Punkte

- Migration neuer Spalten und Tabellen
- View/Trigger-Neuerstellung (`sccpdeviceconfig`, `sccp_trg_buttonconfig`)
- PHP-8.2-sichere DB-Operationen
- Sync FreePBX-Extensions ↔ `sccpline`

---

## 10. Weitere Dateien

| Datei | Änderung |
|-------|----------|
| `views/form.adddevice.php` | Anpassungen Geräteformular (Vendor-Felder) |
| `views/server.info.php` | Kleinere Anzeige-Anpassung |
| `conf/sccpgeneral.xml.v433` | Neue/angepasste Konfigurationseinträge (u. a. Named Groups, ehookEnable) |

---

## 11. Neue Dokumentation

| Datei | Inhalt |
|-------|--------|
| `documentation/Implementierungsplan_SEP_Tausch.md` | SEP-Tausch: Plan + Implementierungsstatus |
| `documentation/Implementierungsplan_ehookEnable_pro_Telefon.md` | ehookEnable: Plan |
| `documentation/Aenderungsuebersicht_2026-06-30.md` | Diese Übersicht |

---

## 12. Testempfehlungen

### SEP-Tausch

| # | Szenario |
|---|----------|
| 1 | Swap zwei gleiche Modelle |
| 2 | Swap unterschiedliche Modelle (Warnung) |
| 3 | Replace → new_hw, `old_action=keep` |
| 4 | Replace → new_hw, `old_action=delete` + Roaming-User `homedevice` |
| 5 | Replace mit manueller MAC |
| 6 | Fehler: gleiche SEP / SIP-Gerät |

### ehookEnable

- GUI: on/off speichern, TFTP-XML prüfen (`<ehookEnable>0|1</ehookEnable>`), Telefon-Neustart

### Named Groups

- Gruppe anlegen, bearbeiten, löschen; Verwendung in Leitungs-Config prüfen

### Buttons

- Gerät mit 0-basierten und 1-basierten `instance`-Werten in DB — GUI muss korrekt laden

### PHP 8.2

- Modul installieren/upgraden ohne Deprecation-Warnings
- SEP-XML für Gerät ohne gesetzte Vendor-Felder (Defaults aus Advanced Settings)

---

## Dateiübersicht (vollständig)

### Geändert (23)

```
Sccp_manager.class.php
assets/js/sccp_manager.js
conf/SEP0000000000.cnf.xml_796x_template
conf/SEP0000000000.cnf.xml_7975_template
conf/SEP0000000000.cnf.xml_797x_template
conf/sccpgeneral.xml.v433
install.php
module.xml
sccpManClasses/Sccp.class.php.v433
sccpManClasses/amInterfaceClasses/Message.class.php
sccpManClasses/amInterfaceClasses/Response.class.php
sccpManClasses/aminterface.class.php
sccpManClasses/dbinterface.class.php
sccpManClasses/extconfigs.class.php
sccpManClasses/formcreate.class.php
sccpManClasses/xmlinterface.class.php
sccpManTraits/ajaxHelper.php
sccpManTraits/bmoFunctions.php
sccpManTraits/helperFunctions.php
views/form.adddevice.php
views/form.buttons.php
views/hardware.phone.php
views/server.info.php
```

### Neu (7)

```
sccpManTraits/deviceSwap.php
views/advserver.namedgroups.php
views/form.editline.php
documentation/Implementierungsplan_SEP_Tausch.md
documentation/Implementierungsplan_ehookEnable_pro_Telefon.md
documentation/Aenderungsuebersicht_2026-06-30.md
(+ ggf. Root-MDs: QUICK_REFERENCE_CODECHANGES.md, KONKRETE_CODEAENDERUNGEN_PHP82_AST22.md)
```

---

*Erstellt: 30.06.2026 — Automatisch aus Working-Tree-Analyse und Implementierungssession.*
