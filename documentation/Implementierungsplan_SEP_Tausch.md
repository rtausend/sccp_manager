# Implementierungsplan: SEP-Tausch (Cisco-Telefon austauschen)

**Ziel:** Im FreePBX-Modul `sccp_manager` eine geführte Funktion bereitstellen, um Cisco-SCCP-Telefone (SEP/ATA/VG) auszutauschen — entweder durch gegenseitigen Tausch zweier vorhandener Geräte oder durch Ersetzen eines vorhandenen Geräts durch ein neues. Die **komplette Konfiguration** (inkl. `type`, `addon`, Vendor-Settings, Roaming-Felder und `sccpbuttonconfig`) wird migriert.

**Stand:** Implementiert (30.06.2026). Gesamtübersicht aller Änderungen: [Aenderungsuebersicht_2026-06-30.md](Aenderungsuebersicht_2026-06-30.md)

---

## Implementierungsstatus

| Komponente | Datei | Status |
|------------|-------|--------|
| Service-Trait | `sccpManTraits/deviceSwap.php` | Fertig |
| Trait-Einbindung | `Sccp_manager.class.php` (Zeile ~116) | Fertig |
| AJAX `preview_swap_device`, `swap_device` | `sccpManTraits/ajaxHelper.php` | Fertig |
| GUI Modal-Wizard | `views/hardware.phone.php` | Fertig |
| Frontend-Logik | `assets/js/sccp_manager.js` (ab ~Zeile 1320) | Fertig |

---

## Anforderungen (bestätigt)

| Anforderung | Entscheidung |
|-------------|--------------|
| Modus A | Zwei **vorhandene** SCCP-Geräte tauschen Konfiguration gegeneinander |
| Modus B | **Vorhandenes** Gerät gegen **neues** Gerät ersetzen (Grid `*NEW*` oder MAC) |
| Altes Gerät (Modus B) | **Beibehalten** (unverändert) oder **löschen** |
| Migrationsumfang | **Vollständig:** alle `sccpdevice`-Felder (außer `name`) + `sccpbuttonconfig` |
| Gerätetypen | Nur native SCCP: SEP, ATA, VG — kein Cisco-SIP |
| Altes Gerät bei „behalten“ | Konfiguration bleibt am alten SEP → Warnung wegen doppelter Leitungen |

---

## Konkrete Codeänderungen

### 1. Neues Trait `sccpManTraits/deviceSwap.php`

Zentrale Geschäftslogik (~420 Zeilen):

```
normalizeSccpSepId()          → MAC/SEP-Normalisierung (SEP/ATA/VG)
isValidNativeSccpSepId()      → Regex-Validierung
getDeviceFullConfig()         → sccpdevice + sccpbuttonconfig laden
prepareDeviceRowForSave()     → JOIN-Spalten entfernen, NULL → 'NONE'
applyDeviceFullConfig()       → REPLACE device + buttons clear/add
swapSccpDeviceConfigs()       → Modus swap mit PDO-Transaktion
replaceSccpDevice()           → Modus replace mit keep/delete
validateSwapRequest()         → Fehler + Warnungen + Summary
previewSwapDevice()           → AJAX-Vorschau
handleSwapDeviceRequest()     → Dispatcher für swap_device
provisionSwappedDevices()     → createSccpDeviceXML + sccpDeviceReset
updateHomedeviceReferences()  → sccpuser.homedevice bei delete
deleteSccpDeviceXmlFile()     → TFTP-Datei löschen (alle Präfixe)
```

**JOIN-Spalten** aus `get_sccpdevice_byid` werden vor dem Speichern entfernt: `dns`, `buttons`, `loadimage`, `nametemplate`, `addon_buttons`.

### 2. `Sccp_manager.class.php`

```php
use \FreePBX\modules\Sccp_Manager\sccpManTraits\deviceSwap;
```

### 3. `sccpManTraits/ajaxHelper.php`

In `ajaxRequest()` und `ajaxHandler()`:

- `preview_swap_device` → `previewSwapDevice($request)`
- `swap_device` → `handleSwapDeviceRequest($request)`

### 4. `views/hardware.phone.php`

- Toolbar: Button `#btn-exchange-sep` → Modal `#modal-swap-sep`
- Modal mit 3 Tabs: Modus / Geräte / Vorschau
- Replace-Modus: Dropdown für `*NEW*`-Geräte, MAC-Eingabe, Radio keep/delete

### 5. `assets/js/sccp_manager.js`

IIFE am Dateiende mit:

- `populateSwapDeviceLists()` aus Bootstrap-Table-Daten
- 3-Schritt-Navigation (Next/Back)
- AJAX Preview vor Schritt 3
- Ausführung mit Bestätigungs-Checkbox
- Vorausfüllung bei 1–2 Grid-Selektionen

---

## Datenfluss

```
GUI Modal
    → preview_swap_device (optional)
    → swap_device
        → validateSwapRequest()
        → swapSccpDeviceConfigs() | replaceSccpDevice()
            → BEGIN TRANSACTION
            → applyDeviceFullConfig() × n
            → COMMIT
        → provisionSwappedDevices()
            → createSccpDeviceXML()
            → sccpDeviceReset(restart|reset)
```

---

## AJAX-API

| Command | Parameter | Response |
|---------|-----------|----------|
| `preview_swap_device` | `mode`, `source`, `target`, `old_action` | `{status, warnings[], summary}` |
| `swap_device` | wie oben | `{status, message, table_reload: true}` |

**Beispiel Replace + Delete:**

```
POST ajax.php?module=sccp_manager&command=swap_device
mode=replace&source=SEP001122334455&target=SEPAABBCCDDEEFF&old_action=delete
```

**Beispiel Swap:**

```
POST ajax.php?module=sccp_manager&command=swap_device
mode=swap&source=SEP001122334455&target=SEPFFEEDDCCBBAA
```

---

## Bedienung

1. **Phones Manager → SCCP Phone**
2. **Exchange SEP** klicken
3. Modus wählen (Swap / Replace)
4. Quell- und Zielgerät wählen
5. Vorschau prüfen, Bestätigung setzen, **Execute exchange**

---

## Testplan (manuell)

| # | Szenario | Erwartung |
|---|----------|-----------|
| 1 | Swap SEP_A ↔ SEP_B (gleiches Modell) | Config getauscht, TFTP neu, restart |
| 2 | Swap unterschiedliche Modelle | Warnung, Tausch OK |
| 3 | Replace alt → new_hw, keep | Ziel = Config alt; alt unverändert |
| 4 | Replace alt → new_hw, delete | Ziel = Config; alt gelöscht; homedevice updated |
| 5 | Replace manuelle MAC | SEP formatiert, Config kopiert |
| 6 | Gleiche SEP | Fehler |
| 7 | SIP-Gerät | Fehler |
| 8 | DB-Fehler mid-flight | Rollback |
| 9 | Roaming homedevice=alt | Nach delete → neu |

---

## Nicht im Scope (v1)

- Cisco-SIP-Geräte
- Automatisches Bereinigen doppelter Leitungen bei keep
- Bulk-Tausch (>2 Geräte)
- Audit-Log / Undo

---

## v2 (optional)

- Physisches Modell vs. DB-`type` per AMI prüfen
- Snapshot/Undo vor Tausch
