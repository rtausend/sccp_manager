<?php

namespace FreePBX\modules\Sccp_manager\sccpManTraits;

trait deviceSwap {

    private static $swapJoinColumns = array('dns', 'buttons', 'loadimage', 'nametemplate', 'addon_buttons');

    /**
     * Validate and normalize a SCCP device id (SEP/ATA/VG + MAC).
     */
    public function normalizeSccpSepId($sepOrMac, $prefixHint = 'SEP') {
        if (empty($sepOrMac)) {
            return '';
        }
        $value = strtoupper(str_replace(array('.', '-', ':', ' '), '', (string) $sepOrMac));
        if (preg_match('/^(SEP|ATA|VG)([0-9A-F]+)$/', $value, $matches)) {
            $prefix = $matches[1];
            $mac = sprintf('%012s', $matches[2]);
            if ($prefix === 'VG') {
                return $prefix . $mac . '0';
            }
            return $prefix . $mac;
        }
        $mac = sprintf('%012s', $value);
        $prefix = in_array($prefixHint, array('SEP', 'ATA', 'VG'), true) ? $prefixHint : 'SEP';
        if ($prefix === 'VG') {
            return $prefix . $mac . '0';
        }
        return $prefix . $mac;
    }

    public function isValidNativeSccpSepId($sepId) {
        if (empty($sepId)) {
            return false;
        }
        if (preg_match('/^SEP[0-9A-F]{12}$/', $sepId)) {
            return true;
        }
        if (preg_match('/^ATA[0-9A-F]{12}$/', $sepId)) {
            return true;
        }
        if (preg_match('/^VG[0-9A-F]{13}$/', $sepId)) {
            return true;
        }
        return false;
    }

    private function isSipSccpDeviceRow($deviceRow) {
        if (empty($deviceRow['type'])) {
            return (strpos($deviceRow['name'] ?? '', '-sip') !== false);
        }
        return (strpos($deviceRow['type'], 'sip') !== false);
    }

    public function sccpDeviceExistsInDb($sepId) {
        $row = $this->dbinterface->getSccpDeviceTableData('get_sccpdevice_byid', array('id' => $sepId));
        return !empty($row) && !empty($row['name']);
    }

    public function getDeviceFullConfig($sepId) {
        $device = $this->dbinterface->getSccpDeviceTableData('get_sccpdevice_byid', array('id' => $sepId));
        if (empty($device) || empty($device['name'])) {
            throw new \RuntimeException(sprintf(_('Device %s not found in database.'), $sepId));
        }
        if ($this->isSipSccpDeviceRow($device)) {
            throw new \RuntimeException(sprintf(_('Device %s is a SIP device and cannot be swapped via SCCP exchange.'), $sepId));
        }
        $buttons = $this->dbinterface->getSccpDeviceTableData('get_sccpdevice_buttons', array('id' => $sepId));
        if (!is_array($buttons)) {
            $buttons = array();
        }
        $deviceRow = $this->prepareDeviceRowForSave($device, $sepId);
        $buttonRows = array();
        foreach ($buttons as $button) {
            if (($button['reftype'] ?? 'sccpdevice') !== 'sccpdevice') {
                continue;
            }
            $buttonRows[] = array(
                'ref' => $sepId,
                'reftype' => 'sccpdevice',
                'instance' => (string) $button['instance'],
                'buttontype' => (string) $button['buttontype'],
                'name' => (string) ($button['name'] ?? ''),
                'options' => (string) ($button['options'] ?? ''),
            );
        }
        return array(
            'device' => $deviceRow,
            'buttons' => $buttonRows,
        );
    }

    private function prepareDeviceRowForSave(array $device, $targetSep) {
        $columns = array_keys($this->dbinterface->getSccpDeviceTableData('get_columns_sccpdevice'));
        $row = array();
        foreach ($columns as $column) {
            if (array_key_exists($column, $device)) {
                $value = $device[$column];
                if ($value === null || $value === 'NULL') {
                    $row[$column] = 'NONE';
                } else {
                    $row[$column] = $value;
                }
            }
        }
        foreach (self::$swapJoinColumns as $joinColumn) {
            unset($row[$joinColumn]);
        }
        $row['name'] = $targetSep;
        return $row;
    }

    public function applyDeviceFullConfig($targetSep, array $config, $inTransaction = false) {
        if (empty($config['device']) || !is_array($config['device'])) {
            throw new \RuntimeException(_('Device configuration is empty.'));
        }
        $deviceRow = $this->prepareDeviceRowForSave($config['device'], $targetSep);
        $this->dbinterface->write('sccpdevice', $deviceRow, 'replace');
        $buttons = array();
        foreach ($config['buttons'] as $button) {
            $buttons[] = array(
                'ref' => $targetSep,
                'reftype' => 'sccpdevice',
                'instance' => (string) $button['instance'],
                'buttontype' => (string) $button['buttontype'],
                'name' => (string) ($button['name'] ?? ''),
                'options' => (string) ($button['options'] ?? ''),
            );
        }
        $this->dbinterface->write('sccpbuttons', $buttons, 'clear', '', $targetSep);
    }

    public function validateSwapRequest(array $params) {
        $errors = array();
        $warnings = array();
        $mode = $params['mode'] ?? '';
        $source = $this->normalizeSccpSepId($params['source'] ?? '');
        $target = $this->normalizeSccpSepId($params['target'] ?? '', substr($source, 0, 3));
        $oldAction = $params['old_action'] ?? 'keep';

        if (!in_array($mode, array('swap', 'replace'), true)) {
            $errors[] = _('Invalid swap mode.');
        }
        if (!$this->isValidNativeSccpSepId($source)) {
            $errors[] = _('Source device id is invalid.');
        }
        if (!$this->isValidNativeSccpSepId($target)) {
            $errors[] = _('Target device id is invalid.');
        }
        if (!empty($source) && $source === $target) {
            $errors[] = _('Source and target device must be different.');
        }
        if (!empty($errors)) {
            return array('status' => false, 'errors' => $errors, 'warnings' => $warnings, 'source' => $source, 'target' => $target);
        }

        if (!$this->sccpDeviceExistsInDb($source)) {
            $errors[] = sprintf(_('Source device %s does not exist in the database.'), $source);
        }

        if ($mode === 'swap') {
            if (!$this->sccpDeviceExistsInDb($target)) {
                $errors[] = sprintf(_('Target device %s does not exist in the database.'), $target);
            }
        }

        if ($mode === 'replace' && !in_array($oldAction, array('keep', 'delete'), true)) {
            $errors[] = _('Invalid action for the old device.');
        }

        $summary = array();
        if (empty($errors)) {
            try {
                $sourceConfig = $this->getDeviceFullConfig($source);
                $summary['source'] = $this->buildDeviceSwapSummary($source, $sourceConfig);
            } catch (\RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if (empty($errors) && $mode === 'swap' && $this->sccpDeviceExistsInDb($target)) {
            try {
                $targetConfig = $this->getDeviceFullConfig($target);
                $summary['target'] = $this->buildDeviceSwapSummary($target, $targetConfig);
                if (($summary['source']['type'] ?? '') !== ($summary['target']['type'] ?? '')) {
                    $warnings[] = _('Source and target use different phone models; button layouts may not match.');
                }
                if (($summary['source']['button_count'] ?? 0) !== ($summary['target']['button_count'] ?? 0)) {
                    $warnings[] = _('Source and target have a different number of configured buttons.');
                }
            } catch (\RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if (empty($errors) && $mode === 'replace') {
            if ($this->sccpDeviceExistsInDb($target)) {
                $warnings[] = sprintf(_('Target device %s already exists and will be overwritten.'), $target);
            }
            if ($oldAction === 'keep') {
                $warnings[] = _('The old device will keep its configuration; line buttons may be assigned twice.');
            }
            if ($oldAction === 'delete') {
                $users = $this->getSccpUsersReferencingDevice($source);
                if (!empty($users)) {
                    $warnings[] = sprintf(
                        _('Roaming user home device references will be updated from %s to %s.'),
                        $source,
                        $target
                    );
                }
            }
            $summary['target'] = array(
                'name' => $target,
                'exists' => $this->sccpDeviceExistsInDb($target),
                'will_receive' => $summary['source']['description'] ?? '',
            );
        }

        return array(
            'status' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'summary' => $summary,
            'source' => $source,
            'target' => $target,
            'mode' => $mode,
            'old_action' => $oldAction,
        );
    }

    private function buildDeviceSwapSummary($sepId, array $config) {
        $lines = array();
        foreach ($config['buttons'] as $button) {
            if ($button['buttontype'] === 'line') {
                $lines[] = $button['name'];
            }
        }
        return array(
            'name' => $sepId,
            'description' => $config['device']['description'] ?? '',
            'type' => $config['device']['type'] ?? '',
            'addon' => $config['device']['addon'] ?? '',
            'button_count' => count($config['buttons']),
            'lines' => $lines,
        );
    }

    private function getSccpUsersReferencingDevice($sepId) {
        $db = \FreePBX::Database();
        $stmt = $db->prepare('SELECT name FROM sccpuser WHERE homedevice = ?');
        $stmt->execute(array($sepId));
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function updateHomedeviceReferences($sourceSep, $targetSep) {
        $db = \FreePBX::Database();
        $stmt = $db->prepare('UPDATE sccpuser SET homedevice = ? WHERE homedevice = ?');
        $stmt->execute(array($targetSep, $sourceSep));
    }

    public function deleteSccpHardwareDevice($sepId) {
        if (!$this->isValidNativeSccpSepId($sepId)) {
            throw new \RuntimeException(_('Invalid device id.'));
        }
        $this->dbinterface->write('sccpdevice', array('name' => $sepId), 'delete', 'name');
        $this->dbinterface->write('sccpbuttons', array(), 'delete', '', $sepId);
        $this->deleteSccpDeviceXmlFile($sepId);
        $this->aminterface->sccpDeviceReset($sepId, 'reset');
    }

    private function deleteSccpDeviceXmlFile($sepId) {
        if (empty($this->sccppath['tftp_store_path'])) {
            return;
        }
        $xmlName = $this->sccppath['tftp_store_path'] . '/' . $sepId . '.cnf.xml';
        if (file_exists($xmlName)) {
            unlink($xmlName);
        }
    }

    public function swapSccpDeviceConfigs($sepA, $sepB) {
        $validation = $this->validateSwapRequest(array(
            'mode' => 'swap',
            'source' => $sepA,
            'target' => $sepB,
        ));
        if (!$validation['status']) {
            return array('status' => false, 'message' => implode(' ', $validation['errors']));
        }
        $sepA = $validation['source'];
        $sepB = $validation['target'];

        $db = \FreePBX::Database();
        $db->beginTransaction();
        try {
            $configA = $this->getDeviceFullConfig($sepA);
            $configB = $this->getDeviceFullConfig($sepB);
            $this->applyDeviceFullConfig($sepA, $configB, true);
            $this->applyDeviceFullConfig($sepB, $configA, true);
            $db->commit();
        } catch (\Exception $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return array('status' => false, 'message' => $exception->getMessage());
        }

        $this->provisionSwappedDevices(array($sepA, $sepB), array('restart', 'restart'));
        return array(
            'status' => true,
            'message' => sprintf(_('Configuration swapped between %s and %s.'), $sepA, $sepB),
            'table_reload' => true,
            'affected' => array($sepA, $sepB),
        );
    }

    public function replaceSccpDevice($sourceSep, $targetSep, $oldAction = 'keep') {
        $validation = $this->validateSwapRequest(array(
            'mode' => 'replace',
            'source' => $sourceSep,
            'target' => $targetSep,
            'old_action' => $oldAction,
        ));
        if (!$validation['status']) {
            return array('status' => false, 'message' => implode(' ', $validation['errors']));
        }
        $sourceSep = $validation['source'];
        $targetSep = $validation['target'];
        $oldAction = $validation['old_action'];
        $targetExisted = $this->sccpDeviceExistsInDb($targetSep);

        $db = \FreePBX::Database();
        $db->beginTransaction();
        try {
            $sourceConfig = $this->getDeviceFullConfig($sourceSep);
            $this->applyDeviceFullConfig($targetSep, $sourceConfig, true);
            if ($oldAction === 'delete') {
                $this->updateHomedeviceReferences($sourceSep, $targetSep);
                $this->dbinterface->write('sccpdevice', array('name' => $sourceSep), 'delete', 'name');
                $this->dbinterface->write('sccpbuttons', array(), 'delete', '', $sourceSep);
            }
            $db->commit();
        } catch (\Exception $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return array('status' => false, 'message' => $exception->getMessage());
        }

        $resetTypes = array($targetExisted ? 'restart' : 'reset');
        $affected = array($targetSep);
        $this->provisionSwappedDevices($affected, $resetTypes);

        if ($oldAction === 'delete') {
            $this->deleteSccpDeviceXmlFile($sourceSep);
            $this->aminterface->sccpDeviceReset($sourceSep, 'reset');
        }

        $message = sprintf(_('Configuration from %s was applied to %s.'), $sourceSep, $targetSep);
        if ($oldAction === 'keep') {
            $message .= ' ' . sprintf(_('Device %s was left unchanged.'), $sourceSep);
        } else {
            $message .= ' ' . sprintf(_('Device %s was deleted.'), $sourceSep);
        }

        return array(
            'status' => true,
            'message' => $message,
            'table_reload' => true,
            'affected' => $affected,
        );
    }

    private function provisionSwappedDevices(array $sepIds, array $resetTypes = array()) {
        foreach ($sepIds as $index => $sepId) {
            $this->createSccpDeviceXML($sepId);
            $resetType = $resetTypes[$index] ?? 'restart';
            if ($this->strpos_array($sepId, array('SEP', 'ATA', 'VG')) !== false) {
                $this->aminterface->sccpDeviceReset($sepId, $resetType);
            }
        }
    }

    public function handleSwapDeviceRequest(array $request) {
        $mode = $request['mode'] ?? '';
        $source = $request['source'] ?? '';
        $target = $request['target'] ?? '';
        $oldAction = $request['old_action'] ?? 'keep';

        if ($mode === 'swap') {
            return $this->swapSccpDeviceConfigs($source, $target);
        }
        if ($mode === 'replace') {
            return $this->replaceSccpDevice($source, $target, $oldAction);
        }
        return array('status' => false, 'message' => _('Invalid swap mode.'));
    }

    public function previewSwapDevice(array $request) {
        $validation = $this->validateSwapRequest($request);
        if (!$validation['status']) {
            return array(
                'status' => false,
                'message' => implode(' ', $validation['errors']),
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
            );
        }
        return array(
            'status' => true,
            'message' => _('Preview generated.'),
            'warnings' => $validation['warnings'],
            'summary' => $validation['summary'],
            'source' => $validation['source'],
            'target' => $validation['target'],
            'mode' => $validation['mode'],
            'old_action' => $validation['old_action'],
        );
    }
}
