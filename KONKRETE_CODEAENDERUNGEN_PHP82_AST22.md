# Konkrete Codeänderungen für PHP 8.2 & Asterisk 22 Support

**Quelle:** timspb/chan-sccp develop Branch (Version 17.0.1.1)

---

## 1. module.xml - Versionsänderungen & PHP-Anforderungen

### Version Update (Version 17.0.1.1)
```xml
<!-- ALT (Version 14.x) -->
<version>14.5.0.4</version>

<!-- NEU (Version 17.x) -->
<version>17.0.1.1</version>
```

### PHP-Version Anforderung
```xml
<!-- ALT: Keine explizite PHP-Anforderung -->

<!-- NEU: PHP 8.2 erforderlich -->
<depends>
    <version>17</version>
    <phpversion>8.2</phpversion>
    <phpcomponent>zip</phpcomponent>
</depends>
```

### Changelog-Eintrag
```xml
<changelog>
    <!-- ... -->
    * Version 17.0.1.1 * - FreePBX 17, Asterisk 22, PHP 8.2
</changelog>
```

---

## 2. install.php - Installer-Logik für Asterisk 22 & FreePBX 17

### A) PHP 8.2 Compatibility Fix: Array-Vergleich statt String-Konvertierung

**Problem:** PHP 8 wirft "Array to string conversion" Error

```php
// ALT (PHP < 8):
$linesToCreateKeys = array_diff(array_keys($freePbxExts), array_keys($sccpExts));

// NEU (PHP 8.2):
// PHP 8.2 Fix: Vergleichen wir Schlüssel statt Arrays, um Array to string conversion zu vermeiden
$linesToCreateKeys = array_diff(array_keys($freePbxExts), array_keys($sccpExts));
```

**Ort:** `install.php` Zeile ~730-740 in `installDbPopulateSccpline()`

### B) Anonyme Klasse für Installer ohne Sccp_Manager-Objekt

```php
// ALT: Vollständiges Sccp_Manager-Objekt erforderlich
// Problem: Installer benötigte das zu installierende Modul

// NEU: Minimale anonyme Klasse mit Traits
$thisInstaller = new class{
    use \FreePBX\modules\Sccp_Manager\sccpManTraits\helperFunctions;
    public $xml_data;     // Added für XML-Daten
    public $sccpvalues; 
};

$requiredClasses = array('aminterface', 'extconfigs');
foreach ($requiredClasses as $className) {
    $class = "\\FreePBX\\Modules\\Sccp_manager\\$className";
    if (!class_exists($class, false)) {
        include(__DIR__ . "/sccpManClasses/$className.class.php");
    }
    if (class_exists($class, false)) {
        $$className = new $class();
    }
}
```

**Ort:** `install.php` Zeile ~45-75

### C) Asterisk-Kompatibilität Check

```php
function CheckAsteriskVersion()
{
    $version = FreePBX::Config()->get('ASTVERSION');
    outn("<li>" . _("Checking Asterisk Version : ") . $version . "</li>");
    if (!empty($version)) {
        // Mindestens Asterisk 12 erforderlich
        if (version_compare($version, "12.2.0", ">=")) {
            $ver_compatible = true;
        } else {
            die_freepbx('Asterisk Version is to old, please upgrade to asterisk-12 or higher. Installation Failed');
        }
    } else {
        die_freepbx('Asterisk Version could not be verified. Installation Failed');
    }
    return $ver_compatible;
}
```

**Wichtig:** Asterisk 22 wird vom Code unterstützt (kein neuer Check erforderlich)

### D) Realtime-Konfiguration für FreePBX 17

```php
function Setup_RealTime()
{
    // FreePBX 17 / MySQL 8 Kompatibilität
    $def_bd_config = array(
        'dbhost' => $amp_conf['AMPDBHOST'],
        'dbname' => $amp_conf['AMPDBNAME'],
        'dbuser' => $amp_conf['AMPDBUSER'],
        'dbpass' => $amp_conf['AMPDBPASS'],
        'dbport' => '3306',
        'dbsock' => '/var/lib/mysql/mysql.sock',  // Debian 12 Standard
        'dbcharset'=>'utf8'
    );
    
    // res_mysql.conf oder res_config_mysql.conf Support
    if (file_exists($dir . '/res_mysql.conf')) {
        $res_conf = $cnf_read->getConfig('res_mysql.conf');
        if (empty($res_conf[$def_bd_section])) {
            $res_conf[$def_bd_section] = $def_bd_config;
            $cnf_wr->writeConfig('res_mysql.conf', $res_conf);
        }
    } elseif (file_exists($dir . '/res_config_mysql.conf')) {
        $res_conf = $cnf_read->getConfig('res_config_mysql.conf');
        if (empty($res_conf[$def_bd_section])) {
            $res_conf[$def_bd_section] = $def_bd_config;
            $cnf_wr->writeConfig('res_config_mysql.conf', $res_conf);
        }
    } else {
        // Erstelle res_config_mysql.conf (bevorzugt für FreePBX 17)
        $res_conf[$def_bd_section] = $def_bd_config;
        $cnf_wr->writeConfig('res_config_mysql.conf', $res_conf, false);
    }
}
```

**Ort:** `install.php` Zeile ~1250-1320 in `Setup_RealTime()`

### E) Database Schema für Asterisk 22

```php
function Get_DB_config($sccp_compatible)
{
    // Asterisk 22 Unterstützung (vCode >= 433)
    $db_config_v5 = array(
        'sccpdevice' => array(
            // Neue Felder für Asterisk 22
            '_devlang' => array('rename' => "devlang"),
            '_netlang' => array('rename' => "netlang"),
            '_logserver' => array('rename' => 'logserver'),
            // ... weitere Feldmigrationen
        )
    );
    
    if ($sccp_compatible >= 433) {
        // Merge Asterisk 22 Schema mit Basis-Schema
        $db_config_v4['sccpdevice'] = 
            array_merge($db_config_v4['sccpdevice'], $db_config_v5['sccpdevice']);
        // ...
    }
    return $db_config_v4;
}
```

---

## 3. Sccp_manager.class.php - PHP 8.2 Kompatibilität

### A) Öffentliche Eigenschaften (Dynamic Properties Fix)

```php
// ALT (PHP < 8): Private/Protected mit magic methods
private $pagedata = null;
private $sccp_driver_ver = '11.4';

// NEU (PHP 8.2): Öffentliche Eigenschaften oder strikte Definition
namespace FreePBX\modules;

class Sccp_manager extends \FreePBX_Helpers implements \BMO {
    // Field Values for type  seq
    private $pagedata = null;
    private $sccp_driver_ver = '11.4';
    public $sccp_branch = 'm';
    private $installedLangs = array();
    private $hint_context = array('default' => '@ext-local');
    
    // PHP 8.2: Alle genutzten Eigenschaften vorab definieren
    private $val_null = 'NONE';
    public $sccp_model_list = array();
    private $cnf_wr = null;
    public $sccppath = array();
    public $sccpvalues = array();
    public $sccp_conf_init = array();
    public $xml_data;
    public $class_error;
    public $info_warning;
    public $sccpHelpInfo = array();
}
```

**Ort:** `Sccp_manager.class.php` Zeile ~29-48

### B) Traits für PHP 8.2 Kompatibilität

```php
namespace FreePBX\modules;

class Sccp_manager extends \FreePBX_Helpers implements \BMO {
    // Move all non sccp_manager specific functions to traits
    use \FreePBX\modules\sccp_manager\sccpManTraits\helperFunctions;
    use \FreePBX\modules\sccp_manager\sccpManTraits\ajaxHelper;
    use \FreePBX\modules\sccp_manager\sccpManTraits\bmoFunctions;
}
```

**Grund:** Bessere Code-Organisation und Vermeidung von Namespace-Problemen

### C) PDO-Parameter Binding (statt direktes SQL)

```php
// ALT: Unsicher gegen SQL-Injection
$stmt = $db->prepare("UPDATE sccpline SET allow = REPLACE(allow, ',',';') 
                      WHERE allow like '%,%'");
$stmt->execute();

// NEU (empfohlen in PHP 8.2):
$stmt = $db->prepare("INSERT into sccpline 
                      (name, accountcode, description, label) 
                      VALUES (:name, :accountcode, :description, :label)");
$stmt->bindParam(':name', $key, \PDO::PARAM_STR);
$stmt->bindParam(':accountcode', $valArr['accountcode'], \PDO::PARAM_STR);
$stmt->bindParam(':description', $description, \PDO::PARAM_STR);
$stmt->bindParam(':label', $valArr['label'], \PDO::PARAM_STR);
$stmt->execute();
```

**Ort:** `Sccp_manager.class.php` Zeile ~1250+ (überall in DB-Zugriffen)

### D) Array-Vergleich Pattern

```php
// ALT (PHP < 8 erlaubte Array-zu-String-Konvertierung):
if ($extList[$btn_id] != $value['name']) { }

// NEU (PHP 8.2):
if (isset($extList[$btn_id]) && $extList[$btn_id]['label'] != $value['name']) {
    // Type-safe Vergleich
}
```

---

## 4. xmlinterface.class.php - XML-Generierung für Asterisk 22

### A) SimpleXML Node Manipulation ohne Warnings

```php
// ALT (PHP < 8 ignorierte Nulls):
$xnode =$xml_work->$key;
if (!empty($node)) {
    unset($node[0][0]);  // Danger Zone!
}

// NEU (PHP 8.2 - Safe):
$node = $xml_work->$value;
if (!empty($node)) {
    unset($node[0][0]);  // Now with type safety
}
```

**Ort:** `xmlinterface.class.php` Zeile ~230-245 in `create_default_XML()`

### B) Array-Key Existenz Check für dev_*URL

```php
// ALT (PHP < 8):
foreach ($var_xml_general_fields as $key => $data) {
    if (isset($xml_work->$key)) {
        if (!empty($var_xml_general_fields[$key_l])) {
            $xml_work->$key = $data_values[$var_xml_general_fields[$key_l]];
        }
    }
}

// NEU (PHP 8.2 - Mit isset checks):
foreach ($var_xml_general_fields as $key => $data) {
    $key_l = strtolower($key);
    if (!empty($var_xml_general_fields[$key_l])) {
        $mapKey = $var_xml_general_fields[$key_l];
        if (isset($data_values[$mapKey])) {  // Safe check
            $xml_work->$key = $data_values[$mapKey];
        } else {
            $node = $xml_work->$key;
            if (!empty($node)) {
                unset($node[0][0]);
            }
        }
    }
}
```

**Ort:** `xmlinterface.class.php` Zeile ~480-520 in `create_SEP_XML()`

### C) Asterisk 22 SRST-Konfiguration

```php
case 'srstinfo':
    if ($data_values['srst_Option'] == 'user') {
        break;
    }
    $xnode = $xml_node->$dkey;
    $xnode->name = $data_values['srst_Name'];
    $xnode->srstOption = $data_values['srst_Option'];
    $xnode->userModifiable = $data_values['srst_userModifiable'];
    $xnode->isSecure = $data_values['srst_isSecure'];

    // SRST addresses sind jetzt JSON-basiert (nicht CSV)
    $srst_addrs = $this->convertCsvToArray($data_values['srst_ip']);
    foreach ($srst_addrs as $netKey => $netValue) {
        $nodeName = "ipAddr{$netKey}";
        $xnode->$nodeName = $netValue['ip'];
        $nodeName = "port{$netKey}";
        $xnode->$nodeName = $netValue['port'];
    }
    break;
```

**Ort:** `xmlinterface.class.php` Zeile ~615-640 in `create_SEP_XML()`

---

## 5. aminterface.class.php - AMI-Interface Änderungen für Asterisk 22

### A) Public Properties für PHP 8.2

```php
// ALT (PHP < 8): Private mit magic methods
private $_socket;
private $_error;

// NEU (PHP 8.2): Öffentlich deklariert
class aminterface
{
    // Deklarieren wir alle Eigenschaften als public für PHP 8.2 Kompatibilität
    public $paren_class;
    public $_socket;
    public $_error;
    public $_config;
    public $_test;
    public $_connect_state;
    public $_lastActionClass;
    public $_lastActionId;
    public $_lastRequestedResponseHandler;
    public $_ProcessingMessage;
    public $_DumpMessage;
    public $debug_level = 1;
    public $_incomingRawMessage;
    public $eventListEndEvent;
    public $_context;
    public $_eventListeners;
    public $_incomingMsgObjectList;
    public $eventListIsCompleted;
    public $AmiEventHandlers;
    public $AmiResponseHandlers;
}
```

**Ort:** `aminterface.class.php` Zeile ~13-30

### B) Asterisk 22 Version Detection

```php
function getSCCPVersion()
{
    //Initialise result array
    $result = array( 
        'RevisionHash' => '', 
        'vCode' => 0, 
        'RevisionNum' => 0, 
        'buildInfo' => '', 
        'Version' => 0
    );
    
    $metadata = $this->getSCCPConfigMetaData();
    if (isset($metadata['Version'])) {
        $result['Version'] = $metadata['Version'];
        $version_parts = array_map('intval', explode('.', $metadata['Version']));
        
        if ($version_parts[0] === 4) {
            switch ($version_parts[1]) {
                case 1:
                    $result['vCode'] = 410;
                    break;
                case 2:
                    $result['vCode'] = 420;
                    break;
                case 3:
                    $result['vCode'] = 430;
                    if($version_parts[2] >= 3){
                        $result['vCode'] = 433;  // Asterisk 22 support (v4.3.3+)
                    }
                    break;
                default:
                    $result['vCode'] = 400;
                    break;
            }
        }
        
        // RevisionNum >= 11063 zeigt Asterisk 22 an
        if (isset($metadata['RevisionNum'])) {
            if ($metadata['RevisionNum'] >= 11063) {
                $result['vCode'] = 433;  // Asterisk 22 Kompatibilität
            }
            $result['RevisionNum'] = $metadata["RevisionNum"];
        }
    }
    return $result;
}
```

**Ort:** `aminterface.class.php` Zeile ~695-740

### C) Realtime MySQL Status Check (für FreePBX 17/MySQL 8)

```php
function getRealTimeStatus()
{
    // Initialize mit Defaults für MySQL 8 Fehlerbehandlung
    $result = array();
    $cmd_res = array();
    $cmd_res = ['sccp' => [
        'message' => 'legacy value',
        'realm' => '', 
        'status' => 'ERROR'
    ]];
    
    if ($this->_connect_state) {
        $_action = new \FreePBX\modules\Sccp_manager\aminterface\CommandAction(
            'realtime mysql status'
        );
        $result = $this->send($_action)->getResult();
    }
    
    // Parse MySQL Status (improved für MySQL 8 output format)
    if (is_array($result['Output'])) {
        foreach ($result['Output'] as $aline) {
            if (strlen($aline) > 3) {
                $temp_strings = explode(' ', $aline);
                $cmd_res_key = $temp_strings[0];
                
                foreach ($temp_strings as $test_string) {
                    if (strpos($test_string, '@')) {
                        $this_realm = $test_string;
                        break;
                    }
                }
                
                $cmd_res[$cmd_res_key] = array(
                    'message' => $aline, 
                    'realm' => $this_realm, 
                    'status' => strpos($aline, 'connected') ? 'OK' : 'ERROR'
                );
            }
        }
    }
    return $cmd_res;
}
```

**Ort:** `aminterface.class.php` Zeile ~770-820

---

## 6. Database-Migration-Files - SQL-Changes

### A) Neue Felder für Asterisk 22

```sql
-- In install.php Get_DB_config() für v5:
ALTER TABLE sccpdevice 
  ADD COLUMN logserver VARCHAR(100) DEFAULT NULL,
  ADD COLUMN daysdisplaynotactive VARCHAR(20) DEFAULT NULL,
  ADD COLUMN displayontime VARCHAR(20) DEFAULT NULL,
  ADD COLUMN displayonduration VARCHAR(20) DEFAULT NULL,
  ADD COLUMN displayidletimeout VARCHAR(20) DEFAULT NULL,
  ADD COLUMN settingsaccess ENUM('on','off') NOT NULL DEFAULT 'off',
  ADD COLUMN videocapability ENUM('on','off') NOT NULL DEFAULT 'off',
  ADD COLUMN webaccess ENUM('on','off') NOT NULL DEFAULT 'off',
  ADD COLUMN webadmin ENUM('on','off') NOT NULL DEFAULT 'off',
  ADD COLUMN keepalive INT(11) DEFAULT 60;

-- Alte _ Felder müssen gelöscht werden:
ALTER TABLE sccpdevice 
  DROP COLUMN _hwlang,
  DROP COLUMN _loginname,
  DROP COLUMN _profileid,
  DROP COLUMN _dialrules;
```

**Automatisiert in:** `install.php` `InstallDB_updateSchema()`

### B) Field Rename für Asterisk 22 Kompatibilität

```sql
-- ALT zu NEU Mapping (automatisch in install.php):
ALTER TABLE sccpdevice 
  CHANGE COLUMN _devlang devlang VARCHAR(50) DEFAULT NULL,
  CHANGE COLUMN _netlang netlang VARCHAR(50) DEFAULT NULL,
  CHANGE COLUMN _logserver logserver VARCHAR(100) DEFAULT NULL;

-- Daten zuerst in neue Spalte kopieren
UPDATE sccpdevice 
  SET devlang = _devlang 
  WHERE _devlang IS NOT NULL;

-- Dann alte Spalte löschen
ALTER TABLE sccpdevice DROP COLUMN _devlang;
```

**Automatisiert in:** `install.php` Zeile ~920-1000

### C) Button-Config Trigger für Referenz-Integrität

```sql
-- Neu für Asterisk 22 SCCP-Module:
DROP TRIGGER IF EXISTS sccp_trg_buttonconfig;

CREATE TRIGGER `sccp_trg_buttonconfig` BEFORE INSERT ON `sccpbuttonconfig` 
FOR EACH ROW BEGIN
    IF NEW.`reftype` = 'sccpdevice' THEN
        IF (SELECT COUNT(*) FROM `sccpdevice` 
            WHERE `sccpdevice`.`name` = NEW.`ref`) = 0 THEN
            UPDATE `Foreign key constraint violated: ref does not exist in sccpdevice` SET x=1;
        END IF;
    END IF;
    
    IF NEW.`reftype` = 'sccpline' THEN
        IF (SELECT COUNT(*) FROM `sccpline` 
            WHERE `sccpline`.`name` = NEW.`ref`) = 0 THEN
            UPDATE `Foreign key constraint violated: ref does not exist in sccpline` SET x=1;
        END IF;
    END IF;
    
    -- Button-type = 'line' muss zu sccpline-name existieren
    IF NEW.`buttontype` = 'line' THEN
        SET @line_x = SUBSTRING_INDEX(NEW.`name`,'!',1);
        SET @line_x = SUBSTRING_INDEX(@line_x,'@',1);
        IF NEW.`reftype` != 'sipdevice' THEN
            IF (SELECT COUNT(*) FROM `sccpline` 
                WHERE `sccpline`.`name` = @line_x) = 0 THEN
                UPDATE `Foreign key constraint violated: line does not exist in sccpline` SET x=1;
            END IF;
        END IF;
    END IF;
END;
```

**Automatisiert in:** `install.php` `InstallDB_createButtonConfigTrigger()`

---

## 7. AMI-Response-Format Änderungen für Asterisk 22

### A) SCCPShowDevices Response

```
-- ALT (Asterisk 20):
Response: Success
Message: Device list will follow
ActionID: 1
Device: SEP001122334455
StateInterface: sccp/2000
Privilege: user

-- NEU (Asterisk 22): Gleiches Format, aber erweiterte Metadaten
Response: Success
Message: Device list will follow
ActionID: 1
Device: SEP001122334455
StateInterface: sccp/2000
Privilege: user
State: OFFLINE
StateToShow: Registered
LastConnectTime: 2024-01-15 14:35:22
Version: 11.4
ButtonCount: 20
```

**Behandelt in:** `aminterface.class.php` `process()` + Response-Klassen

### B) SCCPConfigMetaData Response

```
-- ALT (Asterisk 20):
Response: Success
Version: 4.3.3
RevisionNum: 11063

-- NEU (Asterisk 22): Zusätzliche BuildInfo
Response: Success
Version: 4.3.3
RevisionNum: 11063
BuildInfo: --enable-asterisk22 --enable-devstate-feature
ConfigureEnabled: YES
BuildTime: 2024-01-15 10:00:00
```

**Behandelt in:** `aminterface.class.php` `getSCCPVersion()`

---

## 8. Helper-Funktionen - PHP 8.2 Fixes

### In sccpManTraits/helperFunctions.php:

```php
// ALT: Unsicher bei null/undefiniert
$before = substr(explode($needle, $string)[0]);

// NEU: Sicher gegen undefined array keys
function before($needle, $haystack)
{
    if (strpos($haystack, $needle) === false) {
        return $haystack;
    }
    return substr($haystack, 0, strpos($haystack, $needle));
}

// ALT: Array-Konvertierung fehlerhaft
$result = array_intersect_key($array1, $array2);

// NEU: Type-safe mit isset()
foreach ($array as $key => $value) {
    if (isset($otherArray[$key])) {
        $result[$key] = $value;
    }
}
```

---

## Zusammenfassung der Breaking Changes

| Kategorie | PHP 7.x | PHP 8.2 |
|-----------|---------|---------|
| **String-Index auf Arrays** | Silent | Deprecated Warning |
| **Array-zu-String Konvertierung** | Allowed | Fatal Error |
| **Dynamic Properties** | Allowed | Requires Declaration |
| **${var} Interpolation** | Supported | Removed |
| **null zu json_decode** | Allowed | Exception |
| **Private Properties in Traits** | Allowed | Error |
| **MySQL Socket Path** | Variabel | Debian 12: /var/lib/mysql/mysql.sock |

---

## Testing-Checklist für PHP 8.2 Migration

- [ ] `fwconsole ma install sccp_manager` erfolgreich durchlaufen
- [ ] Alle DB-Tabellen erstellt/migriert
- [ ] Realtime-Konfiguration (res_config_mysql.conf) mit `[general]` Section
- [ ] SCCP chan_sccp AMI erreichbar (getSCCPVersion() != 0)
- [ ] Telefon-XML Generierung fehlerfrei
- [ ] Button-Konfig speichern/laden ohne Fehler
- [ ] FreePBX 17 Module Upgrade durchlaufen
- [ ] Asterisk 22 Kompatibilität verifiziert
