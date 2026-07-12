<?php

namespace FreePBX\modules\Sccp_manager\sccpManTraits;

trait deviceButtonCopy {

    /**
     * Load device-local button rows indexed by instance.
     */
    private function getDeviceButtonRowsByInstance($sepId) {
        $buttons = $this->dbinterface->getSccpDeviceTableData('get_sccpdevice_buttons', array('id' => $sepId));
        if (!is_array($buttons)) {
            return array();
        }
        $byInstance = array();
        foreach ($buttons as $button) {
            if (($button['reftype'] ?? 'sccpdevice') !== 'sccpdevice') {
                continue;
            }
            $byInstance[(int) $button['instance']] = $button;
        }
        return $byInstance;
    }

    private function deviceUsesZeroBasedButtonInstance(array $buttonsByInstance) {
        return array_key_exists(0, $buttonsByInstance);
    }

    private function formatButtonCopySlotLabel($instance, array $buttonsByInstance) {
        $usesZero = $this->deviceUsesZeroBasedButtonInstance($buttonsByInstance);
        $slot = $usesZero ? ((int) $instance + 1) : (int) $instance;
        return sprintf(_('Button %d'), $slot);
    }

    private function formatButtonCopySummary(array $button) {
        $type = (string) ($button['buttontype'] ?? '');
        $name = trim((string) ($button['name'] ?? ''));
        $options = trim((string) ($button['options'] ?? ''));

        switch ($type) {
            case 'empty':
                return _('Empty');
            case 'line':
                return _('Line') . ': ' . preg_replace('/!silent.*/', '', $name);
            case 'speeddial':
                if ($name !== '') {
                    return _('Speed dial') . ': ' . $name;
                }
                return _('Speed dial') . ': ' . $options;
            case 'feature':
                return _('Feature') . ': ' . ($name !== '' ? $name : $options);
            case 'service':
                return _('Service') . ': ' . ($name !== '' ? $name : $options);
            default:
                $summary = ucfirst($type);
                if ($name !== '') {
                    $summary .= ' — ' . $name;
                } elseif ($options !== '') {
                    $summary .= ' — ' . $options;
                }
                return $summary;
        }
    }

    private function isButtonCopySipDeviceRow(array $deviceRow) {
        if (empty($deviceRow['type'])) {
            return (strpos($deviceRow['name'] ?? '', 'sip') !== false);
        }
        return (strpos($deviceRow['type'], 'sip') !== false);
    }

    /**
     * Build catalog of copyable buttons for the source device.
     */
    public function getDeviceButtonCopyCatalog($sepId) {
        $sepId = $this->normalizeSccpSepId($sepId);
        if (!$this->isValidNativeSccpSepId($sepId)) {
            return array('status' => false, 'message' => _('Invalid SCCP device id.'));
        }

        $device = $this->dbinterface->getSccpDeviceTableData('get_sccpdevice_byid', array('id' => $sepId));
        if (empty($device) || empty($device['name'])) {
            return array('status' => false, 'message' => sprintf(_('Device %s not found.'), $sepId));
        }
        if ($this->isButtonCopySipDeviceRow($device)) {
            return array('status' => false, 'message' => _('SIP devices do not support SCCP button copy.'));
        }

        $buttonsByInstance = $this->getDeviceButtonRowsByInstance($sepId);
        $catalog = array();
        ksort($buttonsByInstance, SORT_NUMERIC);
        foreach ($buttonsByInstance as $instance => $button) {
            $catalog[] = array(
                'instance' => (int) $instance,
                'slot' => $this->formatButtonCopySlotLabel($instance, $buttonsByInstance),
                'buttontype' => (string) ($button['buttontype'] ?? ''),
                'name' => (string) ($button['name'] ?? ''),
                'options' => (string) ($button['options'] ?? ''),
                'summary' => $this->formatButtonCopySummary($button),
            );
        }

        return array(
            'status' => true,
            'device' => array(
                'name' => $sepId,
                'description' => $device['description'] ?? '',
                'type' => $device['type'] ?? '',
            ),
            'buttons' => $catalog,
        );
    }

    /**
     * Merge selected source buttons into a target device's button set.
     * When all source buttons are selected, replace the target layout entirely.
     */
    private function mergeButtonsForTarget($targetSep, array $sourceButtonsByInstance, array $selectedInstances) {
        $selectedInstances = array_values(array_unique(array_map('intval', $selectedInstances)));
        sort($selectedInstances, SORT_NUMERIC);

        $sourceInstances = array_map('intval', array_keys($sourceButtonsByInstance));
        sort($sourceInstances, SORT_NUMERIC);
        $fullSourceCopy = ($selectedInstances === $sourceInstances);

        $merged = $fullSourceCopy ? array() : $this->getDeviceButtonRowsByInstance($targetSep);
        foreach ($selectedInstances as $instance) {
            if (!array_key_exists($instance, $sourceButtonsByInstance)) {
                continue;
            }
            $sourceButton = $sourceButtonsByInstance[$instance];
            $merged[$instance] = array(
                'ref' => $targetSep,
                'reftype' => 'sccpdevice',
                'instance' => (string) $instance,
                'buttontype' => (string) ($sourceButton['buttontype'] ?? 'empty'),
                'name' => (string) ($sourceButton['name'] ?? ''),
                'options' => (string) ($sourceButton['options'] ?? ''),
            );
        }
        ksort($merged, SORT_NUMERIC);
        return array_values($merged);
    }

    private function resolveButtonCopyInstances($sourceSep, $rawInstances, $copyAll = false) {
        $sourceButtons = $this->getDeviceButtonRowsByInstance($sourceSep);
        $sourceInstances = array_map('intval', array_keys($sourceButtons));
        sort($sourceInstances, SORT_NUMERIC);

        if ($copyAll || $rawInstances === null || $rawInstances === '' || $rawInstances === array()) {
            return $sourceInstances;
        }

        $parsed = $this->parseButtonCopyInstances($rawInstances);
        $parsed = array_values(array_unique(array_map('intval', $parsed)));
        sort($parsed, SORT_NUMERIC);

        if (empty($parsed) && !empty($sourceInstances)) {
            return $sourceInstances;
        }

        return $parsed;
    }

    private function validateButtonCopyRequest($sourceSep, array $targetSeps, array $selectedInstances) {
        $errors = array();
        $warnings = array();

        $sourceSep = $this->normalizeSccpSepId($sourceSep);
        if (!$this->isValidNativeSccpSepId($sourceSep)) {
            $errors[] = _('Invalid source device id.');
            return array('errors' => $errors, 'warnings' => $warnings, 'source' => $sourceSep, 'targets' => array());
        }
        if (empty($targetSeps)) {
            $errors[] = _('Select at least one target device.');
        }
        if (empty($selectedInstances)) {
            $errors[] = _('Select at least one button to copy.');
        }

        $sourceDevice = $this->dbinterface->getSccpDeviceTableData('get_sccpdevice_byid', array('id' => $sourceSep));
        if (empty($sourceDevice) || empty($sourceDevice['name'])) {
            $errors[] = sprintf(_('Source device %s not found.'), $sourceSep);
        } elseif ($this->isButtonCopySipDeviceRow($sourceDevice)) {
            $errors[] = _('Source device is a SIP device.');
        }

        $normalizedTargets = array();
        foreach ($targetSeps as $targetSep) {
            $targetSep = $this->normalizeSccpSepId($targetSep);
            if (!$this->isValidNativeSccpSepId($targetSep)) {
                $errors[] = sprintf(_('Invalid target device id: %s'), $targetSep);
                continue;
            }
            if ($targetSep === $sourceSep) {
                $errors[] = sprintf(_('Target %s cannot be the same as the source device.'), $targetSep);
                continue;
            }
            if (in_array($targetSep, $normalizedTargets, true)) {
                continue;
            }
            $targetDevice = $this->dbinterface->getSccpDeviceTableData('get_sccpdevice_byid', array('id' => $targetSep));
            if (empty($targetDevice) || empty($targetDevice['name'])) {
                $errors[] = sprintf(_('Target device %s not found.'), $targetSep);
                continue;
            }
            if ($this->isButtonCopySipDeviceRow($targetDevice)) {
                $errors[] = sprintf(_('Target %s is a SIP device.'), $targetSep);
                continue;
            }
            $normalizedTargets[] = $targetSep;
        }

        if (empty($normalizedTargets) && empty($errors)) {
            $errors[] = _('No valid target devices selected.');
        }

        $sourceButtons = $this->getDeviceButtonRowsByInstance($sourceSep);
        $selectedInstances = array_values(array_unique(array_map('intval', $selectedInstances)));
        $missingInstances = array();
        foreach ($selectedInstances as $instance) {
            if (!array_key_exists($instance, $sourceButtons)) {
                $missingInstances[] = $instance;
            }
        }
        if (!empty($missingInstances)) {
            $warnings[] = sprintf(
                _('Source has no configuration for button instance(s): %s'),
                implode(', ', $missingInstances)
            );
        }

        return array(
            'errors' => $errors,
            'warnings' => $warnings,
            'source' => $sourceSep,
            'targets' => $normalizedTargets,
            'selected_instances' => $selectedInstances,
            'source_buttons' => $sourceButtons,
        );
    }

    public function previewCopyDeviceButtons($sourceSep, array $targetSeps, array $selectedInstances) {
        $validation = $this->validateButtonCopyRequest($sourceSep, $targetSeps, $selectedInstances);
        if (!empty($validation['errors'])) {
            return array(
                'status' => false,
                'message' => implode(' ', $validation['errors']),
                'errors' => $validation['errors'],
            );
        }

        $rows = array();
        foreach ($validation['targets'] as $targetSep) {
            $targetButtons = $this->getDeviceButtonRowsByInstance($targetSep);
            foreach ($validation['selected_instances'] as $instance) {
                if (!array_key_exists($instance, $validation['source_buttons'])) {
                    continue;
                }
                $sourceButton = $validation['source_buttons'][$instance];
                $currentButton = $targetButtons[$instance] ?? array(
                    'buttontype' => 'empty',
                    'name' => '',
                    'options' => '',
                );
                $rows[] = array(
                    'target' => $targetSep,
                    'instance' => $instance,
                    'slot' => $this->formatButtonCopySlotLabel($instance, $validation['source_buttons']),
                    'current_summary' => $this->formatButtonCopySummary($currentButton),
                    'new_summary' => $this->formatButtonCopySummary($sourceButton),
                    'changed' => (
                        ($currentButton['buttontype'] ?? '') !== ($sourceButton['buttontype'] ?? '')
                        || ($currentButton['name'] ?? '') !== ($sourceButton['name'] ?? '')
                        || ($currentButton['options'] ?? '') !== ($sourceButton['options'] ?? '')
                    ),
                );
            }
        }

        return array(
            'status' => true,
            'source' => $validation['source'],
            'targets' => $validation['targets'],
            'instances' => $validation['selected_instances'],
            'warnings' => $validation['warnings'],
            'rows' => $rows,
        );
    }

    public function copyDeviceButtonsToTargets($sourceSep, array $targetSeps, array $selectedInstances, $triggerReset = true) {
        $preview = $this->previewCopyDeviceButtons($sourceSep, $targetSeps, $selectedInstances);
        if (!$preview['status']) {
            return array(
                'status' => false,
                'message' => $preview['message'] ?? _('Button copy failed.'),
                'errors' => $preview['errors'] ?? array($preview['message'] ?? ''),
                'results' => array(),
            );
        }

        $sourceButtons = $this->getDeviceButtonRowsByInstance($preview['source']);
        $results = array();
        foreach ($preview['targets'] as $targetSep) {
            $mergedButtons = $this->mergeButtonsForTarget($targetSep, $sourceButtons, $preview['instances']);
            $this->dbinterface->write('sccpbuttons', $mergedButtons, 'clear', '', $targetSep);

            $deviceResult = array('name' => $targetSep, 'buttons' => count($preview['instances']));
            if ($triggerReset) {
                $xmlOk = $this->createSccpDeviceXML($targetSep);
                $resetResult = ($xmlOk !== false)
                    ? $this->aminterface->sccpDeviceReset($targetSep, 'reset')
                    : null;
                $deviceResult['xml'] = ($xmlOk !== false);
                $deviceResult['reset'] = $resetResult;
            } else {
                $deviceResult['xml'] = $this->createSccpDeviceXML($targetSep) !== false;
            }
            $results[$targetSep] = $deviceResult;
        }

        $message = $triggerReset
            ? sprintf(_('Copied %d button(s) to %d device(s). XML updated and reset sent.'), count($preview['instances']), count($results))
            : sprintf(_('Copied %d button(s) to %d device(s). XML updated.'), count($preview['instances']), count($results));

        return array(
            'status' => true,
            'message' => $message,
            'warnings' => $preview['warnings'],
            'results' => $results,
            'table_reload' => true,
        );
    }

    public function handleGetDeviceButtonCopyCatalogRequest($request) {
        $source = $request['source'] ?? '';
        if ($source === '') {
            return array('status' => false, 'message' => _('Source device is required.'));
        }
        return $this->getDeviceButtonCopyCatalog($source);
    }

    public function handlePreviewCopyDeviceButtonsRequest($request) {
        $source = $request['source'] ?? '';
        $targets = $this->extractButtonCopyDeviceIds($request, 'targets');
        $copyAll = !isset($request['copy_all']) || $request['copy_all'] !== '0';
        $instances = $this->resolveButtonCopyInstances($source, $request['instances'] ?? '', $copyAll);
        if ($source === '') {
            return array('status' => false, 'message' => _('Source device is required.'));
        }
        if (empty($targets)) {
            return array('status' => false, 'message' => _('No target devices selected.'));
        }
        return $this->previewCopyDeviceButtons($source, $targets, $instances);
    }

    public function handleCopyDeviceButtonsRequest($request) {
        $source = $request['source'] ?? '';
        $targets = $this->extractButtonCopyDeviceIds($request, 'targets');
        $copyAll = !isset($request['copy_all']) || $request['copy_all'] !== '0';
        $instances = $this->resolveButtonCopyInstances($source, $request['instances'] ?? '', $copyAll);
        $triggerReset = !isset($request['trigger_restart']) || $request['trigger_restart'] !== '0';
        if ($source === '') {
            return array('status' => false, 'message' => _('Source device is required.'));
        }
        if (empty($targets)) {
            return array('status' => false, 'message' => _('No target devices selected.'));
        }
        return $this->copyDeviceButtonsToTargets($source, $targets, $instances, $triggerReset);
    }

    private function extractButtonCopyDeviceIds($request, $field) {
        $ids = array();
        if (!empty($request[$field]) && is_array($request[$field])) {
            $ids = $request[$field];
        } elseif (!empty($request[$field])) {
            $ids = array($request[$field]);
        }
        return $ids;
    }

    private function parseButtonCopyInstances($rawInstances) {
        if (is_array($rawInstances)) {
            return $rawInstances;
        }
        if ($rawInstances === '' || $rawInstances === null) {
            return array();
        }
        $decoded = json_decode($rawInstances, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        return array();
    }
}
