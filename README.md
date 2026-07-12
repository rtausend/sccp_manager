# SCCP Manager — Fork 14.6.0.1

| [Deutsch](README.md) | [Russian :ru:](README.ru.md) |
|----------------------|------------------------------|

> **Fork-Hinweis:** Dieses Repository basiert auf [chan-sccp/sccp_manager](https://github.com/chan-sccp/sccp_manager) (Upstream-Stand `14.5.0.4` / Branch `develop`).
> Version **14.6.0.1** enthält umfangreiche lokale Erweiterungen und Fixes für **FreePBX 16/17**, **PHP 8.2** und **Asterisk 22**.
>
> Maintainer dieses Forks: [rtausend/sccp_manager](https://github.com/rtausend/sccp_manager)

FreePBX-Modul zur Verwaltung von Cisco-SCCP-Telefonen über den [chan-sccp](https://github.com/chan-sccp/chan-sccp)-Treiber — ähnlich wie Cisco CallManager: Geräte, Leitungen, Buttons, TFTP-Provisioning und AMI-Steuerung aus der Weboberfläche.

---

## Inhaltsverzeichnis

- [Neu in Version 14.6.0.1](#neu-in-version-14601)
- [Voraussetzungen](#voraussetzungen)
- [Installation](#installation)
- [Bedienung der neuen Funktionen](#bedienung-der-neuen-funktionen)
- [Bugfixes und technische Verbesserungen](#bugfixes-und-technische-verbesserungen)
- [Dokumentation](#dokumentation)
- [Entwicklung und Packaging](#entwicklung-und-packaging)
- [Upstream und Lizenz](#upstream-und-lizenz)

---

## Neu in Version 14.6.0.1

### Firmware pro Telefon

Bisher war die Firmware nur **modellweit** über `sccpdevmodel.loadimage` festgelegt. Jetzt kann jedes Telefon eine eigene Firmware erhalten.

| Funktion | Beschreibung |
|----------|--------------|
| **Einzelgerät** | Dropdown *Firmware Version (Load Image)* im Geräteformular (`imageversion` in der DB) |
| **Massen-Update** | Toolbar-Button *Assign Firmware* — Wizard für mehrere Geräte, Firmware pro Modell wählbar |
| **XML-Provisioning** | Wert landet in `SEP{MAC}.cnf.xml` als `<loadInformation>` |
| **Phone-Grid** | Spalten *Assigned Firmware*, *Loaded Firmware*, *FW Status* (ok / pending / offline) |
| **AMI-Reset** | Nach Zuweisung optional `sccp reset` ans Telefon (Checkbox im Wizard) |
| **Performance** | Firmware-Status wird nach dem Grid-Laden asynchron nachgeladen (kein Blockieren der Übersicht) |

Leer oder `NONE` = Modell-Standard aus der Datenbank.

Details: [documentation/Implementierungsplan_Firmware_pro_Telefon.md](documentation/Implementierungsplan_Firmware_pro_Telefon.md)

---

### SEP-Tausch (Telefon austauschen)

Telefone tauschen oder ersetzen — inklusive vollständiger Migration von Gerätedaten und Button-Konfiguration.

| Modus | Verhalten |
|-------|-----------|
| **Swap** | Konfiguration zweier vorhandener Geräte (SEP/ATA/VG) gegeneinander tauschen |
| **Replace** | Konfiguration von Gerät A auf Gerät B kopieren; altes Gerät behalten oder löschen |

- GUI: **Phones Manager → SCCP Phone → Exchange SEP**
- 3-Schritt-Wizard: Modus → Geräte wählen → Vorschau → Ausführen
- Automatisch: TFTP-XML neu generieren, optional AMI-Reset

Details: [documentation/Implementierungsplan_SEP_Tausch.md](documentation/Implementierungsplan_SEP_Tausch.md)

---

### ehookEnable — Headset Hookswitch (EHS)

Pro Telefon steuerbar, ob **Headset Hookswitch Control** für Jabra/Plantronics-EHS aktiv ist.

- Neues Feld in der Gerätekonfiguration (Vendor Config)
- Wert wird als `<ehookEnable>` in die TFTP-XML geschrieben
- DB-Spalten `ehookenable` / `_ehookenable` (Migration im Installer)

Details: [documentation/Implementierungsplan_ehookEnable_pro_Telefon.md](documentation/Implementierungsplan_ehookEnable_pro_Telefon.md)

---

### Named Groups (Call/Pickup Groups)

Verwaltung benannter Gruppen für chan-sccp Call Groups und Pickup Groups.

- Neuer Tab **Named Groups** unter **System Parameters**
- Anlegen, Bearbeiten, Löschen über die GUI
- Nutzung in der SCCP-Leitungskonfiguration (FreePBX-Driver)

---

### SCCP-Leitung direkt bearbeiten

SCCP-Leitungen (`sccpline`) können direkt aus dem Phones Manager bearbeitet werden — ohne Umweg nur über die FreePBX-Extensions-Oberfläche.

- Neues Formular `form.editline.php`
- Speichern per AJAX (`saveSccpLine`)

---

### Gerätetyp ändern

Beim Bearbeiten eines SCCP-Geräts ist der **Gerätetyp** nicht mehr nur lesbar, sondern als Dropdown wählbar (mit Validierung bei Modellwechsel).

---

### Button-Konfiguration (robuster)

- Unterstützung für Button-Typ `service`
- Korrektes Mapping bei 0-basierten und 1-basierten Button-`instance`-Werten in der DB
- Validierung ungültiger DB-Zeilen in der Button-GUI

---

### XML-Provisioning und Vendor-Config

- Null-sichere XML-Generierung (PHP 8.2)
- Korrekte Vendor-Defaults und `daysdisplaynotactive`-Handling
- `<loadInformation>` wird auch in Templates ohne Platzhalter gesetzt (z. B. 7975)
- Globale Spalten-Defaults bei XML-Generierung (`applySccpDeviceTableDefaults`)

---

### PHP 8.2 / FreePBX 17 / Asterisk 22

| Bereich | Verbesserung |
|---------|--------------|
| **PHP 8.2** | Deprecation-Warnings behoben, null-sichere Zugriffe, typisierte Patterns |
| **Installer** | PDO-Binding, `array_diff_key` statt `array_diff_assoc`, robustere Migrationen |
| **FreePBX-Driver** | `Sccp.class.php.v433` angepasst für Extensions-Tab und Named Groups |
| **AMI** | Robustere Response-Parsing (`Response.class.php`, `Message.class.php`) |
| **module.xml** | `phpversion` 8.2; korrigierte Feldlängen (`audio_cos`, `video_cos`) |

Ausführlich: [KONKRETE_CODEAENDERUNGEN_PHP82_AST22.md](KONKRETE_CODEAENDERUNGEN_PHP82_AST22.md)

---

### Lokales Packaging und Modul-Pfade

| Komponente | Beschreibung |
|------------|--------------|
| `scripts/package-module.sh` | Erzeugt installierbares `.tgz` für FreePBX Module Admin |
| `scripts/install-local.sh` | Deployment + `fwconsole ma install` |
| `scripts/scan-for-sensitive-data.sh` | Prüfung vor GitHub-Push |
| `sccp_manager_path.php` | Modul-Pfade relativ zur installierten Kopie (nicht hardcoded Webroot) |
| `module.xml` | Kein Upstream-`updateurl` — kein automatisches Downgrade auf alte GitHub-Version |

---

## Voraussetzungen

- **FreePBX** 15 / 16 / 17 (getestet mit FreePBX 17)
- **PHP** 8.2+ mit `zip`-Extension (`apt install php8.2-zip`)
- **Asterisk** 12.2+ (getestet mit Asterisk 22)
- **chan-sccp** 4.3.4+ (v433), kompiliert mit:
  ```bash
  ./configure --enable-conference --enable-advanced-functions \
              --enable-distributed-devicestate --enable-video
  ```
- **TFTP-Server** (empfohlen: `/tftpboot/`) mit Schreibrechten
- **Asterisk Realtime** für chan-sccp ([Wiki](https://github.com/chan-sccp/chan-sccp/wiki/Realtime-Configuration))

---

## Installation

### Variante A — aus diesem Fork (empfohlen)

```bash
git clone https://github.com/rtausend/sccp_manager.git
cd sccp_manager
git checkout fork/14.6.0.1-custom   # oder dein Hauptbranch

./scripts/package-module.sh -u
./scripts/install-local.sh
```

Oder manuell:

```bash
./scripts/package-module.sh -u
tar -xzf dist/sccp_manager-14.6.0.1.tgz -C /tmp
cp -a /tmp/sccp_manager /var/www/html/admin/modules/
fwconsole ma install sccp_manager -f
fwconsole reload
```

**Wichtig:** `fwconsole ma install` erwartet den **Modulnamen** (`sccp_manager`), nicht den Pfad zur `.tgz`-Datei.

### Variante B — Entwicklung per Symlink

```bash
ln -sfn /pfad/zu/sccp_manager /var/www/html/admin/modules/sccp_manager
fwconsole ma install sccp_manager -f
fwconsole reload
```

### Variante C — Upload in Module Admin

1. `./scripts/package-module.sh` ausführen
2. FreePBX → **Admin → Module Admin → Upload Modules**
3. `dist/sccp_manager-14.6.0.1.tgz` hochladen
4. Installieren und **Apply Config**

Der Installer erstellt automatisch ein Backup unter `/etc/asterisk/sccp_install_backup*.zip`.

---

## Bedienung der neuen Funktionen

### Firmware zuweisen (Einzelgerät)

1. **Phones Manager → SCCP Phone** → Gerät bearbeiten
2. Feld **Firmware Version (Load Image)** wählen (oder leer für Modell-Standard)
3. Speichern → XML wird neu generiert

### Firmware Massen-Update

1. Geräte in der Tabelle auswählen
2. **Assign Firmware** → Wizard durchlaufen
3. Optional: *Send SCCP reset after assignment* aktivieren
4. **Assign** — Grid zeigt den Status nach kurzer Ladezeit

### SEP tauschen

1. **Phones Manager → SCCP Phone → Exchange SEP**
2. Modus *Swap* oder *Replace* wählen
3. Quell- und Zielgerät festlegen
4. Vorschau prüfen → **Execute exchange**

### Named Groups

1. **System Parameters → Named Groups**
2. Gruppe anlegen und in der Leitungskonfiguration verwenden

### ehookEnable

1. Gerät bearbeiten → Bereich **Vendor Config**
2. **Headset Hookswitch Control** auf on/off
3. Speichern → TFTP-XML prüfen: `<ehookEnable>0|1</ehookEnable>`

---

## Bugfixes und technische Verbesserungen

Gegenüber Upstream `14.5.0.4` u. a.:

- AMI `strpos()` / `array_merge()` null-sicher bei leeren Device-Info-Responses
- Installer: `array_diff_key` für Extension-Sync (PHP 8.2 „Array to string conversion“)
- `Sccp.class.php.v433`: Namespace-Deklaration korrigiert
- Firmware-Katalog: `.loads` plus Legacy `.bin`/`.zup` (7985, ATA); moderne Modelle weiterhin primär über `.loads`; `.sbn`-Komponenten ausgeschlossen
- Massen-Firmware-Update nutzt `sccp reset` (nicht `restart`)
- Phone-Grid: kein doppelter `SCCPShowDevices`-Aufruf; Firmware-Status lazy-loaded
- Modul-Backups landen außerhalb von `admin/modules/` (kein Doppel-Eintrag in Module Admin)
- `sccpManagerUpdater.json` ohne lokale `file://`-Pfade

Vollständige technische Übersicht: [documentation/Aenderungsuebersicht_2026-06-30.md](documentation/Aenderungsuebersicht_2026-06-30.md)

---

## Dokumentation

| Datei | Inhalt |
|-------|--------|
| [documentation/Aenderungsuebersicht_2026-06-30.md](documentation/Aenderungsuebersicht_2026-06-30.md) | Gesamtübersicht aller Änderungen |
| [documentation/Legacy-Firmware-Validierung-7985.md](documentation/Legacy-Firmware-Validierung-7985.md) | LoadImage-Validierung 7985/ATA (.bin/.zup) |
| [documentation/Implementierungsplan_Firmware_pro_Telefon.md](documentation/Implementierungsplan_Firmware_pro_Telefon.md) | Firmware pro Telefon |
| [documentation/Implementierungsplan_SEP_Tausch.md](documentation/Implementierungsplan_SEP_Tausch.md) | SEP-Tausch |
| [documentation/Implementierungsplan_ehookEnable_pro_Telefon.md](documentation/Implementierungsplan_ehookEnable_pro_Telefon.md) | ehookEnable / EHS |
| [documentation/GITHUB_FORK_ANLEITUNG.md](documentation/GITHUB_FORK_ANLEITUNG.md) | Fork pflegen, GitHub-Push, Sensibilitäts-Scan |
| [KONKRETE_CODEAENDERUNGEN_PHP82_AST22.md](KONKRETE_CODEAENDERUNGEN_PHP82_AST22.md) | PHP 8.2 / Asterisk 22 im Detail |
| [QUICK_REFERENCE_CODECHANGES.md](QUICK_REFERENCE_CODECHANGES.md) | Schnellreferenz Codeänderungen |

---

## Entwicklung und Packaging

```bash
# Installierbares Modul-Archiv bauen
./scripts/package-module.sh -u

# Auf der PBX installieren
./scripts/install-local.sh

# Vor GitHub-Push: sensible Daten prüfen
./scripts/scan-for-sensitive-data.sh
```

Upstream-Updates einspielen:

```bash
git fetch upstream
git merge upstream/develop
```

---

## Upstream und Lizenz

- **Upstream:** [chan-sccp/sccp_manager](https://github.com/chan-sccp/sccp_manager)
- **chan-sccp Wiki:** [github.com/chan-sccp/chan-sccp/wiki](https://github.com/chan-sccp/chan-sccp/wiki)
- **Lizenz:** GPL (siehe Upstream-Repository)

Ursprünglich entwickelt von Steve Lad, Alex GP und der chan-sccp-Community.
Dieser Fork wird unabhängig vom Upstream-Release-Kanal gepflegt.
