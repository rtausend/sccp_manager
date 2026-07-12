<?php

/*
 *
 * Response class definitions
 *
 */

namespace FreePBX\modules\Sccp_manager\aminterface;

// ************************************************************************** Response *********************************************

abstract class Response extends IncomingMessage
{

    protected $_events;
    protected $_completed;
    protected $keys;
    protected $eventListIsCompleted = false;
    protected $eventListEndEvent = '';
    protected $_tables = array();

    public function __construct($rawContent)
    {

        parent::__construct($rawContent);
        $this->_events = array();
// this logic is false - even if we have an error, we will not get anymore data, so is completed.
        $this->_completed = $this->isSuccess();
    }

    public function __sleep()
    {
        $ret = parent::__sleep();
        $ret[] = '_completed';
        $ret[] = '_events';
        return $ret;
    }

    public function getEvents()
    {
        return $this->_events;
    }
    public function getClosingEvent() {
        return $this->_events['ClosingEvent'] ?? null;
    }
    public function removeClosingEvent() {
        if (isset($this->_events['ClosingEvent'])) {
            unset($this->_events['ClosingEvent']);
        }
    }
    public function getCountOfEvents() {
        return count($this->_events);
    }

    public function isSuccess()
    {
        // returns true if response message does not contain error
        return stristr($this->getKey('Response'), 'Error') === false;
    }

    public function isList()
    {
        if ($this->getKey('EventList') === 'start' ) {
            return true;
        }
    }

    public function getMessage()
    {
        return $this->getKey('Message');
    }

    public function setActionId($actionId)
    {
        $this->setKey('ActionId', $actionId);
    }

    public function getVariable($_rawContent, $_fields = '')
    {
        $lines = explode(Message::EOL, $_rawContent);
        foreach ($_fields as $key => $value) {
            foreach ($lines as $data) {
                $_pst = strpos($data, $value);
                if ($_pst !== false) {
                    $this->setKey($key, substr($data, $_pst + strlen($value)));
                }
            }
        }
    }
}
class GenericResponse extends Response
{
}

//****************************************************************************
// There are two types of Response messages returned by AMI
// Self contained responses which include any data requested;
// List Responses which contain the data in event messages that follow
// the response message.Response and Event
// Following are the self contained Response classes.
//****************************************************************************

class Generic_Response extends Response
{
    public function __construct($rawContent)
    {
        // Only used for self contained responses.
        parent::__construct($rawContent);
        // add dummy closing event
        $this->_events['ClosingEvent'] = new ResponseComplete_Event($rawContent);
        $this->_completed = true;
        $this->eventListIsCompleted = true;

    }
}

class Login_Response extends Generic_Response
{
}

class Command_Response extends Generic_Response
{
    private $_temptable;
    public function __construct($rawContent)
    {
        $this->_temptable = array();
        parent::__construct($rawContent);
        $lines = explode(Message::EOL, $rawContent);
        foreach ($lines as $line) {
            $content = explode(':', $line);
            if (is_array($content)) {
                switch (strtolower($content[0])) {
                    case 'actionid':
                        $this->_temptable['ActionID'] = trim($content[1]);
                        break;
                    case 'response':
                        $this->_temptable['Response'] = trim($content[1]);
                        break;
                    case 'privilege':
                        $this->_temptable['Privilege'] = trim($content[1]);
                        break;
                    case 'output':
                        // included for backward compatibility with earlier versions of chan_sccp_b. AMI api does not precede command output with Output
                        $this->_temptable['Output'] = explode(PHP_EOL,str_replace(PHP_EOL.'--END COMMAND--', '',trim($content[1])));
                        break;
                    default:
                        $this->_temptable['Output'] = explode(PHP_EOL,str_replace(PHP_EOL.'--END COMMAND--', '', trim($line)));
                        break;
                }
            }
        }
    }
    public function getResult()
    {
        return $this->_temptable;
    }
}

class SCCPJSON_Response extends Generic_Response
{
    public function __construct($rawContent)
    {
        parent::__construct($rawContent);
        $this->getVariable($rawContent, array("DataType" => "DataType:", "JSONRAW" => "JSON:"));
        if (null !== $this->getKey('JSONRAW')) {
            $this->setKey('Response', 'Success');
        }
    }
    public function getResult()
    {
        $jsonRaw = $this->getKey('JSONRAW');
        if ($jsonRaw === null || $jsonRaw === '') {
            return array();
        }
        $json = json_decode((string) $jsonRaw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json;
        }
        return array();
    }
}

//***************************************************************************//
// Following are the Response classes where the data is contained in a series.
// of event messages.

class SCCPGeneric_Response extends Response
{
    protected $_tables;
    private $_temptable;

    public function __construct($rawContent)
    {
        parent::__construct($rawContent);
        // Confirm that there is a list following. This overrides any setting
        // made in one of the parent constructs.
        $this->_completed = !$this->isList();
        if ($this->_completed) {
            $this->_events['ClosingEvent'] = new ResponseComplete_Event($rawContent);
        }
    }

    public function addEvent($event)
    {
        // Start of list is handled by the isList function in the Constructor
        // which also defines the list end event

        if (empty($thisSetEventEntryType)) {
            // This is empty as soon as we have received a TableStart.
            // The next message is the first of the data sets
            // We use this variable in the switch to add set entries
            $eventName = $event->getName();
            if (is_string($eventName) && strpos($eventName, 'Entry') !== false) {
                $thisSetEventEntryType = $eventName;
            } else {
                $thisSetEventEntryType = 'undefinedAsThisIsNotASet';
            }
        }
        // Unknown events will cause an exception.
        // All event classes must be defined within Event.class.
        if (get_class($event) === 'FreePBX\modules\Sccp_manager\aminterface\UnknownEvent') {
            $this->_events[] = $event;
            return;
        }
        switch ( $event->getName()) {
            case $thisSetEventEntryType :
                $this->_temptable['Entries'][] = $event;
                break;
            case 'TableStart':
                //initialise
                $this->_temptable = array();
                $this->_temptable['Name'] = $event->getTableName();
                $this->_temptable['Entries'] = array();
                $thisSetEventEntryType = '';
                break;
            case 'TableEnd':
                //Close
                if (!is_array($this->_tables)) {
                    $this->_tables = array();
                }
                $this->_tables[$event->getTableName()] = $this->_temptable;
                $this->_temptable = array();
                $thisSetEventEntryType = 'undefinedAsThisIsNotASet';

                // Finished the table. Now check to see if everything was received
                // If counts do not match return false and table will not be
                //loaded
                if ($event->getKey('TableEntries') != count($this->_tables[$event->getTableName()]['Entries'])) {
                    return false;
                }
                break;
            default:
                $endEventName = $this->getKey('eventlistendevent');
                if ($endEventName !== null && strcasecmp($event->getName(), $endEventName) === 0) {
                    $this->_events['ClosingEvent'] = $event;
                    break;
                }
                // add regular list event
                $this->_events[] = $event;
        }
    }

    protected function ConvertTableData( $_tablename, array $_fkey, array $_fields)
    {
        $result = array();
        $_rawtable = $this->Table2Array($_tablename);
        // Check that there is actually data to be converted
        if (empty($_rawtable)) { return $result;}
        foreach ($_rawtable as $_row) {
            $all_key_ok = true;
            // No need to test if $_fkey is array as array required
            foreach ($_fkey as $_fid) {
                if (empty($_row[$_fid])) {
                    $all_key_ok = false;
                } else {
                    $set_name[$_fid] = $_row[$_fid];
                }
            }
            $Data = &$result;

            if ($all_key_ok) {
                foreach ($set_name as $value_id) {
                    $Data = &$Data[$value_id];
                }
                // Label converter in case labels and keys are different
                foreach ($_fields as $value_key => $value_id) {
                    $Data[$value_id] = $_row[$value_key];
                }
            }
        }
        return $result;
    }

    protected function ConvertEventData(array $_fkey, array $_fields)
    {
        $result = array();

        foreach ($this->_events as $_row) {
            $all_key_ok = true;
            $tmp_result = $_row->getKeys();
            $set_name = array();
            // No need to test if $_fkey is arrray as array required
            foreach ($_fkey as $_fid) {
                if (empty($tmp_result[$_fid])) {
                    $all_key_ok = false;
                } else {
                    $set_name[$_fid] = $tmp_result[$_fid];
                }
            }
            $Data = &$result;
            if ($all_key_ok) {
                foreach ($set_name as $value_id) {
                    $Data = &$Data[$value_id];
                }
                // Label converter in case labels and keys are different - not actually required.
                foreach ($_fields as $value_id) {
                    $Data[$value_id] = $tmp_result[$value_id];
                }
            }
        }
        return $result;
    }

    public function Table2Array( $tablename )
    {
        $result =array();
        if (empty($tablename) || !is_array($this->_tables)) {
            return $result;
        }
        foreach ($this->_tables[$tablename]['Entries'] as $trow) {
            $result[]= $trow->getKeys();
        }
        return $result;
    }

    public function getResult()
    {
            return $this->getMessage();
    }
}



class SCCPShowSoftkeySets_Response extends SCCPGeneric_Response
{
    public function __construct($rawContent)
    {
        parent::__construct($rawContent);
        $this->setKey('eventlistendevent', 'SCCPShowSoftKeySetsComplete');
    }
    public function getResult()
    {
        return $this->ConvertTableData(
            'SoftKeySets',
            array('set','mode'),
            array('description'=>'description','label'=>'label','lblid'=>'lblid')
            );
    }
}

class SCCPShowDevices_Response extends SCCPGeneric_Response
{
    public function __construct($rawContent)
    {
        parent::__construct($rawContent);
        $this->setKey('eventlistendevent', 'SCCPShowDevicesComplete');
    }
    public function getResult()
    {
        return $this->ConvertTableData(
            'Devices',
            array('mac'),
            array('mac'=>'name','address'=>'address','descr'=>'descr','regstate'=>'status',
                  'token'=>'token','act'=>'act', 'lines'=>'lines','nat'=>'nat','regtime'=>'regtime')
            );
    }
}

class SCCPShowDevice_Response extends SCCPGeneric_Response
{
    public function __construct($rawContent)
    {
        parent::__construct($rawContent);
        $this->setKey('eventlistendevent', 'SCCPShowDeviceComplete');
    }
    public function getResult()
    {
        // This object has a list of events _events, and a list of tables _tables.
        $result = array();

        foreach ($this->_events as $trow) {
            $keys = $trow->getKeys();
            if (is_array($keys)) {
                $result = array_merge($result, $keys);
            }
        }
        // Now handle label changes so that keys from AMI correspond to db keys in _tables
        $result['Buttons'] = $this->ConvertTableData(
            'Buttons',
            array('id'),
            array('id'=>'id','channelobjecttype'=>'channelobjecttype','inst'=>'inst',
                  'typestr'=>'typestr', 'type'=>'type', 'pendupdt'=>'pendupdt', 'penddel'=>'penddel', 'default'=>'default'
                  )
        );
        $result['SpeeddialButtons'] = $this->ConvertTableData(
            'SpeeddialButtons',
            array('id'),
            array('id'=>'id','channelobjecttype'=>'channelobjecttype','name'=>'name','number'=>'number','hint'=>'hint')
        );
        $result['LineButtons'] = $this->ConvertTableData(
            'LineButtons',
            array('id'),
            array('id'=>'id','channelobjecttype'=>'channelobjecttype','name'=>'name','subid'=>'subid','label'=>'label','callforward'=>'callforward')
        );
        $result['FeatureButtons'] = $this->ConvertTableData(
            'FeatureButtons',
            array('id'),
            array('id'=>'id','channelobjecttype'=>'channelobjecttype','name'=>'name','status'=>'status','options'=>'options','args'=>'args')
        );
        $result['ServiceURLButtons'] = $this->ConvertTableData(
            'ServiceURLButtons',
            array('id'),
            array('id'=>'id','channelobjecttype'=>'channelobjecttype','name'=>'name','url'=>'url')
        );
        $result['CallStatistics'] = $this->ConvertTableData(
            'CallStatistics',
            array('type'),
            array('type'=>'type','channelobjecttype'=>'channelobjecttype','calls'=>'calls','pcktsnt'=>'pcktsnt','pcktrcvd'=>'pcktrcvd',
                  'lost'=>'lost','jitter'=>'jitter','latency'=>'latency', 'quality'=>'quality','avgqual'=>'avgqual','meanqual'=>'meanqual',
                  'maxqual'=>'maxqual', 'rconceal'=>'rconceal', 'sconceal'=>'sconceal'
                  )
        );
        // Parse skinnyphonetype (e.g., "Cisco 7945 (SCCP)") into vendor/model
        $vendor = 'Undefined';
        $model = '';
        $model_id = '';
        if (!empty($result['skinnyphonetype'])) {
            if (preg_match('/^(\S+)\s+(.+?)\s*\((.+?)\)/', $result['skinnyphonetype'], $matches)) {
                $vendor = $matches[1];
                $model = $matches[2];
                $model_id = $matches[3];
            } else {
                // Fallback: just take first word as vendor
                $parts = explode(' ', $result['skinnyphonetype']);
                $vendor = $parts[0] ?? 'Undefined';
                $model = implode(' ', array_slice($parts, 1)) ?: $result['skinnyphonetype'];
            }
        }
        
        // Parse configphonetype for addon vendor/model
        $vendor_addon = '';
        $model_addon = '';
        if (!empty($result['configphonetype'])) {
            $addon_parts = explode(' ', $result['configphonetype'], 2);
            $vendor_addon = $addon_parts[0] ?? '';
            $model_addon = $addon_parts[1] ?? '';
        }
        
        $result['SCCP_Vendor'] = array('vendor' => $vendor, 'model' => $model,
                                       'model_id' => $model_id, 'vendor_addon' => $vendor_addon,
                                       'model_addon' => $model_addon);
        $result['MAC_Address'] = $result['macaddress'] ?? ($result['MAC-Address'] ?? '');
        return $result;
    }
}

class ExtensionStateList_Response extends SCCPGeneric_Response
{
    public function __construct($rawContent)
    {
        parent::__construct($rawContent);
        $this->setKey('eventlistendevent', 'ExtensionStateListComplete');
    }
    public function getResult()
    {
        $result = $this->ConvertEventData(array('exten','context'), array('exten','context','hint','status','statustext'));
        return $result;
    }
}
