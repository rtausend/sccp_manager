# Legacy-Firmware-Validierung (7985, ATA186, …)

**Stand:** 12.07.2026  
**Code:** `sccpManTraits/deviceFirmware.php`  
**Commit:** Legacy-Firmware-Validierung für 7985/ATA (.bin/.zup)

---

## Problem

In **Advanced → SCCP Model information** erscheint bei Modell **7985** in der Spalte **LoadImage**:

```
File not found
cmterm_7985.4-1-7-0
```

…obwohl die Datei physisch vorhanden ist:

```
/tftpboot/firmware/7985/cmterm_7985.4-1-7-0.bin
```

Gleiches Muster bei **ATA 186/187** (`.zup` statt `.loads`).

---

## Ursache

Die Validierung in `firmwareFileExistsForModel()` suchte **nur** nach:

```
{tftp_firmware_path}/{model}/{loadimage}.loads
```

Moderne Cisco-Telefone (7975, 7941, …) liefern ein **`.loads`-Manifest** plus `.sbn`-Bundles.  
Legacy-Geräte (7985, ATA) haben **eine einzelne Image-Datei** (`.bin` / `.zup`) **ohne** `.loads`.

Datenbank-Standard für 7985 (`install.php`):

| Feld | Wert |
|------|------|
| model | 7985 |
| loadimage | cmterm_7985.4-1-7-0 |
| loadinformationid | loadInformation302 |

---

## Lösung

### Prüfreihenfolge (pro Suchverzeichnis)

1. `{loadimage}.loads` / `.LOADS` — **modern** (Priorität)
2. `{loadimage}.bin` — **legacy** (7985)
3. `{loadimage}.zup` — **legacy** (ATA186)

**.sbn**-Dateien (`apps41…`, `dsp75…`) werden **nicht** als LoadImage gezählt — sie sind Manifest-Komponenten, keine Load-Image-Namen.

### Suchverzeichnisse (unverändert)

1. `{tftp_firmware_path}/{model}/` → typ. `/tftpboot/firmware/7985/`
2. `{tftp_path}/firmware/{model}/`
3. `{tftp_path}/{model}/`

### Neue / geänderte API im Trait

| Methode | Zweck |
|---------|--------|
| `resolveFirmwareFilePath()` | Existenz: `.loads` zuerst, dann `.bin`/`.zup` |
| `resolveFirmwareLoadsFilePath()` | Nur `.loads`-Manifest-Pfad (modern) |
| `firmwareFileExistsForModel()` | Nutzt `resolveFirmwareFilePath()` |
| `findFirmwareBasenamesInDirectory()` | Katalog-Scan für UI / Assign-Firmware |

---

## Regressionsschutz (moderne Modelle)

Beispiel **7975**, loadimage `SCCP75.9-4-2SR3-1S`:

- Datei `SCCP75.9-4-2SR3-1S.loads` existiert → **Treffer in Schritt 1**
- Fallback auf `.bin` wird **nicht** ausgewertet
- Verhalten identisch zum Stand vor dem Fix

---

## TFTP / Rewrite (7985)

Telefon fragt oft flach ab; Datei liegt im Unterordner (`contrib/rewrite.rules`):

```
ri ^(cmterm_7985.4-1-7-0.bin)$ firmware/7985/\1
```

---

## Prüfen auf der PBX

```bash
ls -la /tftpboot/firmware/7985/
# Erwartet: cmterm_7985.4-1-7-0.bin

# Nach Modul-Update: Model-Tabelle neu laden → LoadImage ohne „File not found“
```
