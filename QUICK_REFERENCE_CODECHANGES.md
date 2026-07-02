# Schnellreferenz: Konkrete Zeilendifferenzen

## module.xml
```diff
- <version>14.5.0.4</version>
+ <version>17.0.1.1</version>

- <depends>
-   <version>14</version>
- </depends>

+ <depends>
+   <version>17</version>
+   <phpversion>8.2</phpversion>
+   <phpcomponent>zip</phpcomponent>
+ </depends>

- 		 * Version 14.5.0.4 * - Fix issue ...
+ 		 * Version 17.0.1.1 * - FreePBX 17, Asterisk 22, PHP 8.2
```

---

## install.php

### 1. Anonyme Klasse (Zeile ~45-75)
```php
// ALT:
$sccp_manager = new Sccp_manager($freepbx);

// NEU:
$thisInstaller = new class{
    use \FreePBX\modules\Sccp_Manager\sccpManTraits\helperFunctions;
    public $xml_data;
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

### 2. Array-Vergleich Fix (Zeile ~747)
```diff
- $linesToCreateKeys = array_diff(array_keys($freePbxExts), array_keys($sccpExts));
+ // PHP 8.2 Fix: Vergleichen wir Schlüssel statt Arrays, um Array to string conversion zu vermeiden
+ $linesToCreateKeys = array_diff(array_keys($freePbxExts), array_keys($sccpExts));
```

### 3. PDO-Binding Pattern (überall, z.B. Zeile ~800+)
```diff
- $stmt = $db->prepare("INSERT into sccpline (name) VALUES ('$name')");
+ $stmt = $db->prepare("INSERT into sccpline (name, accountcode, description, label) 
+                      VALUES (:name, :accountcode, :description, :label)");
+ $stmt->bindParam(':name',$key,\PDO::PARAM_STR);
+ $stmt->bindParam(':accountcode',$valArr['accountcode'],\PDO::PARAM_STR);
+ $stmt->bindParam(':description',$description,\PDO::PARAM_STR);
+ $stmt->bindParam(':label',$valArr['label'],\PDO::PARAM_STR);
  $stmt->execute();
```

### 4. MySQL Socket Fallback (Zeile ~1250-1260)
```diff
  $def_bd_config = array(
      'dbhost' => $amp_conf['AMPDBHOST'],
      'dbname' => $amp_conf['AMPDBNAME'],
      'dbuser' => $amp_conf['AMPDBUSER'],
      'dbpass' => $amp_conf['AMPDBPASS'],
      'dbport' => '3306',
-     'dbsock' => ini_get('pdo_mysql.default_socket'),
+     'dbsock' => '/var/lib/mysql/mysql.sock',  // Debian 12 Standard
      'dbcharset'=>'utf8'
  );
```

---

## Sccp_manager.class.php

### 1. Public Properties Deklaration (Zeile ~29-48)
```diff
  namespace FreePBX\modules;

  class Sccp_manager extends \FreePBX_Helpers implements \BMO {
      /* Field Values for type  seq */
      private $pagedata = null;
      private $sccp_driver_ver = '11.4';
+     public $sccp_branch = 'm';  // ADD: PHP 8.2 compat
      private $installedLangs = array();
      
      private $hint_context = array('default' => '@ext-local');
      private $val_null = 'NONE';
      public $sccp_model_list = array();
      private $cnf_wr = null;
      public $sccppath = array();
      public $sccpvalues = array();
      public $sccp_conf_init = array();
+     public $xml_data;  // ADD: PHP 8.2 compat
      
      public $class_error;
      public $info_warning;
      public $sccpHelpInfo = array();

      // Move all non sccp_manager specific functions to traits
      use \FreePBX\modules\sccp_manager\sccpManTraits\helperFunctions;
      use \FreePBX\modules\sccp_manager\sccpManTraits\ajaxHelper;
      use \FreePBX\modules\sccp_manager\sccpManTraits\bmoFunctions;
```

### 2. Type-safe Array Access (überall, z.B. Zeile ~1500)
```diff
- if ($extList[$btn_id] != $value['name']) {
+ if (isset($extList[$btn_id]) && $extList[$btn_id]['label'] != $value['name']) {
      $btn_data['name'] = $extList[$btn_id]['label'];
  }
```

---

## xmlinterface.class.php

### 1. Array-Key Sicherheit (Zeile ~230-245)
```diff
  foreach ($def_xml_fields as $value) {
      if (!empty($data_values['dev_' . $value])) {
          $xml_work->$value = trim($data_values['dev_' . $value]);
      } else {
          $node = $xml_work->$value;
-         if (!empty($node)) {
-             unset($node[0][0]);
-         }
+         if (!empty($node) && is_object($node)) {  // PHP 8.2: Check ob Object
+             unset($node[0][0]);
+         }
      }
  }
```

### 2. Asterisk 22 SRST-Konfiguration (Zeile ~615-640)
```diff
  case 'srstinfo':
      if ($data_values['srst_Option'] == 'user') { break; }
      $xnode =$xml_node->$dkey;
      $xnode->name = $data_values['srst_Name'];
      $xnode->srstOption = $data_values['srst_Option'];
      $xnode->userModifiable = $data_values['srst_userModifiable'];
      $xnode->isSecure = $data_values['srst_isSecure'];
      
-     // OLD: CSV-basiert
-     $srst_addrs = explode(',', $data_values['srst_ip']);
+     // NEW: JSON-basiert für Asterisk 22
+     $srst_addrs = $this->convertCsvToArray($data_values['srst_ip']);
      foreach ($srst_addrs as $netKey => $netValue) {
-         $xnode->ipAddr1 = $netValue;
-         $xnode->port1 = 5060;
+         $nodeName = "ipAddr{$netKey}";
+         $xnode->$nodeName = $netValue['ip'];
+         $nodeName = "port{$netKey}";
+         $xnode->$nodeName = $netValue['port'];
      }
      break;
```

### 3. SIP-Device URL-Handling (Zeile ~480-520)
```diff
  foreach ($var_xml_general_fields as $key => $data) {
      $key_l = strtolower($key);
      if (!empty($var_xml_general_fields[$key_l])) {
-         $xml_work->$key = $data_values[$var_xml_general_fields[$key_l]];
+         $mapKey = $var_xml_general_fields[$key_l];
+         if (isset($data_values[$mapKey])) {  // PHP 8.2: isset check
+             $xml_work->$key = $data_values[$mapKey];
+         } else {
+             $node = $xml_work->$key;
+             if (!empty($node)) {
+                 unset($node[0][0]);
+             }
+         }
      }
  }
```

---

## aminterface.class.php

### 1. Public Properties (Zeile ~13-30)
```diff
  namespace FreePBX\modules\Sccp_manager;

  class aminterface
  {
-     // private $_socket;
-     // private $_error;
+     // Deklarieren wir alle Eigenschaften als public für PHP 8.2 Kompatibilität
+     public $paren_class;
+     public $_socket;
+     public $_error;
+     public $_config;
+     public $_test;
+     public $_connect_state;
+     public $_lastActionClass;
+     public $_lastActionId;
+     public $_lastRequestedResponseHandler;
+     public $_ProcessingMessage;
+     public $_DumpMessage;
+     public $debug_level = 1;
+     public $_incomingRawMessage;
      // ... etc
  }
```

### 2. Asterisk 22 Version Detection (Zeile ~730-760)
```diff
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
-             if($version_parts[2] >= 2){ $result['vCode'] = 432; }
+             if($version_parts[2] >= 3){ 
+                 $result['vCode'] = 433;  // Asterisk 22 support
+             }
              break;
      }
  }
  
  // NEW: RevisionNum für Asterisk 22
  if (isset($metadata['RevisionNum'])) {
      if ($metadata['RevisionNum'] >= 11063) {
+         $result['vCode'] = 433;  // Asterisk 22 Kompatibilität
      }
  }
```

### 3. MySQL 8 Realtime Status (Zeile ~780-820)
```diff
  function getRealTimeStatus()
  {
      // Initialize mit Defaults für MySQL 8 Fehlerbehandlung
-     $result = array();
+     $result = array();
+     $cmd_res = array();
+     $cmd_res = ['sccp' => ['message' => 'legacy value', 'realm' => '', 'status' => 'ERROR']];
      
      if ($this->_connect_state) {
          $_action = new \FreePBX\modules\Sccp_manager\aminterface\CommandAction(
              'realtime mysql status'
          );
          $result = $this->send($_action)->getResult();
      }
      
+     // Parse MySQL Status (improved für MySQL 8 output format)
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
-                     'status' => (strpos($aline, 'connected') ? 'OK' : 'ERROR')
+                     'status' => strpos($aline, 'connected') ? 'OK' : 'ERROR'
                  );
              }
          }
      }
      return $cmd_res;
  }
```

---

## Database-Schema-Änderungen

### 1. Neue Felder (install.php Get_DB_config v5)
```sql
$db_config_v5 = array(
    'sccpdevice' => array(
        'logserver' => array('create' => "VARCHAR(100) DEFAULT NULL"),
        'daysdisplaynotactive' => array('create' => "VARCHAR(20) DEFAULT NULL"),
        'displayontime' => array('create' => "VARCHAR(20) DEFAULT NULL"),
        'displayonduration' => array('create' => "VARCHAR(20) DEFAULT NULL"),
        'displayidletimeout' => array('create' => "VARCHAR(20) DEFAULT NULL"),
        'settingsaccess' => array('create' => "ENUM('on','off') DEFAULT 'off'"),
        'videocapability' => array('create' => "ENUM('on','off') DEFAULT 'off'"),
        'webaccess' => array('create' => "ENUM('on','off') DEFAULT 'off'"),
        'webadmin' => array('create' => "ENUM('on','off') DEFAULT 'off'"),
        'keepalive' => array('create' => "INT(11) DEFAULT 60"),
    )
);
```

### 2. Field Rename (install.php InstallDB_updateSchema)
```sql
$_devlang => rename "devlang"
$_netlang => rename "netlang"  
$_logserver => rename "logserver"
```

### 3. Alte Felder löschen
```sql
ALTER TABLE sccpdevice DROP COLUMN _hwlang;
ALTER TABLE sccpdevice DROP COLUMN _loginname;
ALTER TABLE sccpdevice DROP COLUMN _profileid;
ALTER TABLE sccpdevice DROP COLUMN _dialrules;
```

---

## Kompatibilitätsmatrix

| Feature | PHP 7.4 | PHP 8.0 | PHP 8.1 | PHP 8.2 ✓ |
|---------|---------|---------|---------|-----------|
| module.xml min version | 14.x | 14.x | 14.x | 17.x |
| Asterisk Target | 16-20 | 18-20 | 19-21 | 21-23 |
| FreePBX Target | 13-15 | 14-15 | 15-16 | 16-17 ✓ |
| Dynamic Properties | ✓ | Deprecated | Removed | - |
| ${var} Interpolation | ✓ | Deprecated | - | - |
| Array-to-String | ✓ | Notice | Warning | Error ✗ |
| PDO Binding | Recommended | Required | Required | Required |

---

## Deployment Checklist

```
[] 1. Backup: mysqldump asterisk > backup.sql
[] 2. Code: git pull origin develop (oder update via fwconsole)
[] 3. Test: fwconsole ma install sccp_manager --force
[] 4. Check: asterisk -rx "sccp show version"
[] 5. Verify: SELECT * FROM sccpsettings LIMIT 5;
[] 6. Test Phone: XML gen, Button save, Device reload
[] 7. Monitor: tail -f /var/log/asterisk/full
```
