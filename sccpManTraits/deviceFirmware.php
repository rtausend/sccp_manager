<?php

namespace FreePBX\modules\Sccp_manager\sccpManTraits;

trait deviceFirmware {

    /** Modern SCCP: manifest + .sbn bundles (797x, 79xx, …). */
    private static function firmwareManifestExtensions() {
        return array('.loads', '.LOADS');
    }

    /**
     * Legacy single-file images (7985 .bin, ATA186 .zup, …).
     * Checked only after no matching .loads exists — modern models unaffected.
     */
    private static function firmwareLegacyImageExtensions() {
        return array('.bin', '.zup');
    }

    /** Extensions scanned for firmware catalog entries (load image basenames). */
    private static function firmwareCatalogExtensions() {
        return array_merge(
            self::firmwareManifestExtensions(),
            self::firmwareLegacyImageExtensions()
        );
    }

    /** Extension priority for on-disk existence checks (.loads always first). */
    private function firmwareExistenceCheckExtensions() {
        return self::firmwareCatalogExtensions();
    }

    /**
     * Effective load image for XML generation: device override or model default.
     */
    public function resolveDeviceLoadImage(array $deviceRow) {
        $override = $this->normalizeFirmwareValue($deviceRow['imageversion'] ?? '');
        if ($override !== '') {
            return $override;
        }
        if (!empty($deviceRow['loadimage'])) {
            return (string) $deviceRow['loadimage'];
        }
        if (!empty($deviceRow['model_loadimage'])) {
            return (string) $deviceRow['model_loadimage'];
        }
        return '';
    }

    /**
     * Normalize firmware/imageversion values from DB or form.
     */
    public function normalizeFirmwareValue($value) {
        $value = trim((string) $value);
        if ($value === '' || strtoupper($value) === 'NONE') {
            return '';
        }
        return $value;
    }

    /**
     * List firmware files available for a device model on the TFTP server.
     */
    public function getFirmwareCatalogForModel($model) {
        $model = trim((string) $model);
        $catalog = array(
            'model' => $model,
            'model_default' => '',
            'files' => array(),
        );
        if ($model === '') {
            return $catalog;
        }

        $modelInfo = $this->getSccpModelInformation('byid', false, 'all', array('model' => $model));
        if (!empty($modelInfo[0]['loadimage'])) {
            $catalog['model_default'] = $modelInfo[0]['loadimage'];
        }

        $catalog['files'] = $this->scanFirmwareFilesForModel($model);
        $catalog['firmware_dir'] = $this->getPrimaryFirmwareDirectoryForModel($model);
        if (!empty($catalog['model_default']) && !in_array($catalog['model_default'], $catalog['files'], true)) {
            $catalog['files'][] = $catalog['model_default'];
        }
        sort($catalog['files'], SORT_NATURAL | SORT_FLAG_CASE);
        return $catalog;
    }

    /**
     * Primary firmware directory for a model (same target as Provisioner download).
     */
    public function getPrimaryFirmwareDirectoryForModel($model) {
        $baseDir = rtrim($this->sccppath['tftp_firmware_path'] ?? '', '/');
        $model = trim((string) $model);
        if ($baseDir === '' || $model === '') {
            return '';
        }
        return "{$baseDir}/{$model}";
    }

    /**
     * Resolve the on-disk path of a .loads manifest for a model (modern phones).
     */
    public function resolveFirmwareLoadsFilePath($model, $loadimage) {
        $loadimage = $this->normalizeFirmwareValue($loadimage);
        if ($loadimage === '') {
            return '';
        }
        foreach ($this->getFirmwareSearchDirectories($model) as $searchDir) {
            foreach (self::firmwareManifestExtensions() as $ext) {
                $path = "{$searchDir}/{$loadimage}{$ext}";
                if (is_file($path)) {
                    return $path;
                }
            }
        }
        return '';
    }

    /**
     * Resolve any supported firmware image on disk (.loads preferred, then legacy .bin/.zup).
     */
    public function resolveFirmwareFilePath($model, $loadimage) {
        $loadimage = $this->normalizeFirmwareValue($loadimage);
        if ($loadimage === '') {
            return '';
        }
        foreach ($this->getFirmwareSearchDirectories($model) as $searchDir) {
            foreach ($this->firmwareExistenceCheckExtensions() as $ext) {
                $path = "{$searchDir}/{$loadimage}{$ext}";
                if (is_file($path)) {
                    return $path;
                }
            }
        }
        return '';
    }

    /**
     * Check whether firmware for the given model load image exists on the TFTP server.
     */
    public function firmwareFileExistsForModel($model, $loadimage) {
        return $this->resolveFirmwareFilePath($model, $loadimage) !== '';
    }

    /**
     * Scan TFTP firmware directory for a model.
     */
    private function scanFirmwareFilesForModel($model) {
        if ($model === '') {
            return array();
        }

        $catalogExtensions = self::firmwareCatalogExtensions();
        $files = array();

        foreach ($this->getFirmwareSearchDirectories($model) as $searchDir) {
            if (!is_dir($searchDir)) {
                continue;
            }
            $found = $this->findFirmwareBasenamesInDirectory($searchDir, $catalogExtensions);
            if (!empty($found)) {
                $files = array_merge($files, $found);
            }
        }

        if (empty($files)) {
            $files = $this->scanLegacyFlatFirmwareForModel($model, $catalogExtensions);
        }

        $files = array_values(array_unique(array_filter($files)));
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);
        return $files;
    }

    /**
     * List firmware basenames in one directory (non-recursive).
     */
    private function findFirmwareBasenamesInDirectory($searchDir, array $extensions) {
        $files = array();
        if (!is_dir($searchDir)) {
            return $files;
        }
        foreach (array_diff(scandir($searchDir), array('.', '..')) as $entry) {
            $path = "{$searchDir}/{$entry}";
            if (!is_file($path)) {
                continue;
            }
            foreach ($extensions as $ext) {
                if (strlen($entry) > strlen($ext) && substr($entry, -strlen($ext)) === $ext) {
                    $files[] = substr($entry, 0, -strlen($ext));
                    break;
                }
            }
        }
        return $files;
    }

    /**
     * @deprecated Use findFirmwareBasenamesInDirectory()
     */
    private function findFirmwareLoadNamesInDirectory($searchDir, array $loadsOnly) {
        return $this->findFirmwareBasenamesInDirectory($searchDir, $loadsOnly);
    }

    /**
     * Legacy flat-layout fallback for firmware still stored directly under tftproot.
     */
    private function scanLegacyFlatFirmwareForModel($model, array $extensions) {
        $legacyDirs = array(
            rtrim($this->sccppath['tftp_firmware_path'] ?? '', '/'),
            rtrim($this->sccppath['tftp_path'] ?? '', '/'),
        );
        $files = array();
        foreach (array_unique(array_filter($legacyDirs)) as $legacyDir) {
            if (!is_dir($legacyDir)) {
                continue;
            }
            $found = $this->filterFirmwareLoadNamesForModel(
                $this->findFirmwareBasenamesInDirectory($legacyDir, $extensions),
                $model
            );
            if (!empty($found)) {
                $files = array_merge($files, $found);
            }
        }
        return $files;
    }

    /**
     * Candidate directories for model-specific firmware.
     */
    private function getFirmwareSearchDirectories($model) {
        $tftpRoot = rtrim($this->sccppath['tftp_path'] ?? '', '/');
        $dirs = array(
            $this->getPrimaryFirmwareDirectoryForModel($model),
            "{$tftpRoot}/firmware/{$model}",
            "{$tftpRoot}/{$model}",
        );
        return array_values(array_unique(array_filter($dirs)));
    }

    /**
     * Keep only load image names that belong to the given phone model.
     */
    private function filterFirmwareLoadNamesForModel(array $loadNames, $model) {
        $patterns = $this->getModelFirmwareNamePatterns($model);
        if (empty($patterns)) {
            return $loadNames;
        }
        $filtered = array();
        foreach ($loadNames as $name) {
            foreach ($patterns as $pattern) {
                if (stripos($name, $pattern) === 0) {
                    $filtered[] = $name;
                    break;
                }
            }
        }
        return $filtered;
    }

    /**
     * Derive filename prefixes for a Cisco model (e.g. 7975 -> SCCP75., term75.).
     */
    private function getModelFirmwareNamePatterns($model) {
        $patterns = array();
        if (preg_match('/(\d{2,4})/', (string) $model, $matches)) {
            $digits = $matches[1];
            $short = strlen($digits) > 2 ? substr($digits, -2) : $digits;
            $patterns[] = 'SCCP' . $short . '.';
            $patterns[] = 'term' . $short . '.';
            $patterns[] = 'CP' . $digits;
            $patterns[] = 'P00';
        }
        $modelInfo = $this->getSccpModelInformation('byid', false, 'all', array('model' => $model));
        if (!empty($modelInfo[0]['loadimage'])) {
            $patterns[] = $modelInfo[0]['loadimage'];
        }
        return array_values(array_unique($patterns));
    }

    /**
     * Check whether a firmware file exists for the given model.
     */
    public function validateFirmwareFile($model, $loadimage) {
        $loadimage = $this->normalizeFirmwareValue($loadimage);
        if ($loadimage === '') {
            return array('valid' => true, 'message' => '');
        }

        $catalog = $this->getFirmwareCatalogForModel($model);
        if (in_array($loadimage, $catalog['files'], true)) {
            return array('valid' => true, 'message' => '');
        }
        if ($this->firmwareFileExistsForModel($model, $loadimage)) {
            return array('valid' => true, 'message' => '');
        }

        return array(
            'valid' => false,
            'message' => sprintf(_('Firmware file not found on TFTP server: %s'), $loadimage),
        );
    }

    /**
     * Resolve firmware assignment for a device from a model=>firmware map.
     */
    private function resolveFirmwareAssignmentForDevice(array $deviceRow, array $firmwareMap) {
        $model = $deviceRow['type'] ?? '';
        if (!array_key_exists($model, $firmwareMap)) {
            return null;
        }
        return $this->normalizeFirmwareValue($firmwareMap[$model]);
    }

    /**
     * Build preview rows for firmware assignment.
     */
    public function previewFirmwareAssignment(array $sepIds, array $firmwareMap) {
        $rows = array();
        $errors = array();
        $warnings = array();

        foreach ($sepIds as $sepId) {
            $sepId = $this->normalizeSccpSepId($sepId);
            if (!$this->isValidNativeSccpSepId($sepId)) {
                $errors[] = sprintf(_('Invalid SCCP device id: %s'), $sepId);
                continue;
            }

            $device = $this->dbinterface->getSccpDeviceTableData('get_sccpdevice_byid', array('id' => $sepId));
            if (empty($device) || empty($device['name'])) {
                $errors[] = sprintf(_('Device %s not found in database.'), $sepId);
                continue;
            }
            if ($this->isFirmwareSipDeviceRow($device)) {
                $errors[] = sprintf(_('Device %s is a SIP device and cannot receive SCCP firmware assignments.'), $sepId);
                continue;
            }

            if (!array_key_exists($device['type'], $firmwareMap)) {
                $errors[] = sprintf(_('No firmware selected for model %s (device %s).'), $device['type'], $sepId);
                continue;
            }

            $currentAssigned = $this->resolveDeviceLoadImage($device);
            $newOverride = $this->resolveFirmwareAssignmentForDevice($device, $firmwareMap);
            $previewDevice = $device;
            $previewDevice['imageversion'] = ($newOverride === '') ? null : $newOverride;
            $newAssigned = $this->resolveDeviceLoadImage($previewDevice);
            $validation = $this->validateFirmwareFile($device['type'], $newAssigned);

            $row = array(
                'name' => $sepId,
                'type' => $device['type'],
                'description' => $device['description'] ?? '',
                'current_override' => $this->normalizeFirmwareValue($device['imageversion'] ?? ''),
                'current_assigned' => $currentAssigned,
                'new_override' => $newOverride,
                'new_assigned' => $newAssigned,
                'valid' => $validation['valid'],
                'message' => $validation['message'],
            );
            $rows[] = $row;

            if (!$validation['valid']) {
                $warnings[] = sprintf(_('%s: %s'), $sepId, $validation['message']);
            }
            if ($currentAssigned === $newAssigned && $row['current_override'] === $row['new_override']) {
                $warnings[] = sprintf(_('%s: firmware assignment unchanged.'), $sepId);
            }
        }

        return array(
            'status' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'rows' => $rows,
        );
    }

    /**
     * Assign firmware to one or more devices and optionally provision them.
     */
    public function assignFirmwareToDevices(array $sepIds, array $firmwareMap, $triggerReset = true) {
        $preview = $this->previewFirmwareAssignment($sepIds, $firmwareMap);
        if (!$preview['status']) {
            return array(
                'status' => false,
                'message' => implode(' ', $preview['errors']),
                'errors' => $preview['errors'],
                'results' => array(),
            );
        }

        $results = array();
        foreach ($preview['rows'] as $row) {
            $sepId = $row['name'];
            $imageValue = ($row['new_override'] === '') ? $this->val_null : $row['new_override'];
            $this->dbinterface->write(
                'sccpdevice',
                array('name' => $sepId, 'imageversion' => $imageValue),
                'update',
                'name'
            );

            $provision = $this->provisionFirmwareDevices(array($sepId), $triggerReset);
            $deviceResult = array(
                'name' => $sepId,
                'assigned' => $row['new_assigned'],
                'xml' => $provision['results'][$sepId]['xml'] ?? false,
                'reset' => $provision['results'][$sepId]['reset'] ?? null,
            );
            if (!empty($provision['results'][$sepId]['error'])) {
                $deviceResult['error'] = $provision['results'][$sepId]['error'];
            }
            $results[$sepId] = $deviceResult;
        }

        $message = $triggerReset
            ? sprintf(_('Firmware assigned to %d device(s). SCCP reset sent.'), count($results))
            : sprintf(_('Firmware assigned to %d device(s). XML updated, no reset sent.'), count($results));

        return array(
            'status' => true,
            'message' => $message,
            'warnings' => $preview['warnings'],
            'results' => $results,
            'table_reload' => true,
        );
    }

    /**
     * Regenerate device XML and optionally send sccp reset via AMI.
     */
    public function provisionFirmwareDevices(array $sepIds, $triggerReset = true) {
        $results = array();
        foreach ($sepIds as $sepId) {
            $sepId = $this->normalizeSccpSepId($sepId);
            if (!$this->isValidNativeSccpSepId($sepId)) {
                $results[$sepId] = array('xml' => false, 'error' => _('Invalid device id'));
                continue;
            }

            $xmlOk = $this->createSccpDeviceXML($sepId);
            $resetResult = null;
            if ($triggerReset && $xmlOk !== false) {
                $resetResult = $this->aminterface->sccpDeviceReset($sepId, 'reset');
            }
            $results[$sepId] = array(
                'xml' => ($xmlOk !== false),
                'reset' => $resetResult,
            );
        }
        return array('results' => $results);
    }

    /**
     * Parse loaded firmware version from chan-sccp device info.
     */
    public function getLoadedFirmwareFromDeviceInfo(array $deviceInfo) {
        foreach (array('Image Version', 'ImageVersion', 'imageversion', 'loadedimageversion') as $key) {
            if (!empty($deviceInfo[$key])) {
                return trim((string) $deviceInfo[$key]);
            }
        }
        return '';
    }

    /**
     * Compare assigned load image with firmware reported by the phone.
     */
    public function firmwareVersionsMatch($assigned, $loaded) {
        $assigned = trim((string) $assigned);
        $loaded = trim((string) $loaded);
        if ($assigned === '' || $loaded === '') {
            return false;
        }
        if ($assigned === $loaded) {
            return true;
        }
        $normalize = function ($value) {
            $value = strtolower(basename($value));
            return preg_replace('/\.loads$/', '', $value);
        };
        $assignedNorm = $normalize($assigned);
        $loadedNorm = $normalize($loaded);
        if ($assignedNorm === $loadedNorm) {
            return true;
        }
        return (strpos($assignedNorm, $loadedNorm) !== false || strpos($loadedNorm, $assignedNorm) !== false);
    }

    /**
     * Derive firmware status from assigned and loaded values.
     */
    public function resolveFirmwareStatus($assigned, $loaded) {
        $loaded = trim((string) $loaded);
        if ($loaded === '') {
            return 'unknown';
        }
        $assigned = trim((string) $assigned);
        if ($assigned === '') {
            return 'unknown';
        }
        if ($this->firmwareVersionsMatch($assigned, $loaded)) {
            return 'ok';
        }
        return 'pending';
    }

    /**
     * Fetch loaded firmware and status for online grid devices (lazy load).
     */
    public function getPhoneGridFirmwareStatus(array $devices) {
        $activeDevices = $this->aminterface->sccp_get_active_device();
        $results = array();
        foreach ($devices as $device) {
            $sepId = $device['name'] ?? '';
            if ($sepId === '') {
                continue;
            }
            $assigned = $device['firmware_assigned'] ?? '';
            if (empty($activeDevices[$sepId])) {
                $results[$sepId] = array(
                    'firmware_loaded' => '',
                    'firmware_status' => 'offline',
                );
                continue;
            }
            $deviceInfo = $this->aminterface->sccp_getdevice_info($sepId);
            $loaded = $this->getLoadedFirmwareFromDeviceInfo($deviceInfo);
            $results[$sepId] = array(
                'firmware_loaded' => $loaded,
                'firmware_status' => $this->resolveFirmwareStatus($assigned, $loaded),
            );
        }
        return array('status' => true, 'devices' => $results);
    }

    /**
     * Handle AJAX lazy firmware status request for phone grid.
     */
    public function handleGetPhoneFirmwareStatusRequest($request) {
        $devices = $request['devices'] ?? '';
        if (is_string($devices)) {
            $devices = json_decode($devices, true);
        }
        if (!is_array($devices) || empty($devices)) {
            return array('status' => false, 'message' => _('No devices provided.'));
        }
        return $this->getPhoneGridFirmwareStatus($devices);
    }

    /**
     * Enrich phone grid rows with firmware assignment information.
     * Loaded firmware is fetched asynchronously after the grid loads.
     */
    public function enrichPhoneGridFirmwareData(array &$deviceRows, array $activeDevices = null) {
        if ($activeDevices === null) {
            $activeDevices = $this->aminterface->sccp_get_active_device();
        }
        $modelLoadImages = array();
        $allModels = $this->getSccpModelInformation('all', false);
        foreach ($allModels as $modelRow) {
            if (!empty($modelRow['model']) && !empty($modelRow['loadimage'])) {
                $modelLoadImages[$modelRow['model']] = $modelRow['loadimage'];
            }
        }
        foreach ($deviceRows as &$row) {
            if (($row['type'] ?? '') === '' || strpos($row['type'], 'sip') !== false) {
                continue;
            }
            if (empty($row['model_loadimage']) && !empty($row['type']) && isset($modelLoadImages[$row['type']])) {
                $row['model_loadimage'] = $modelLoadImages[$row['type']];
            }
            $row['firmware_assigned'] = $this->resolveDeviceLoadImage($row);
            $row['firmware_loaded'] = '';
            $sepId = $row['name'] ?? '';
            if (!empty($activeDevices[$sepId])) {
                $row['firmware_status'] = 'loading';
            } else {
                $row['firmware_status'] = 'offline';
            }
        }
        unset($row);
    }

    /**
     * Handle AJAX firmware catalog request.
     */
    public function handleGetFirmwareCatalogRequest($request) {
        $model = $request['model'] ?? '';
        if ($model === '') {
            return array('status' => false, 'message' => _('Model is required.'));
        }
        $catalog = $this->getFirmwareCatalogForModel($model);
        return array('status' => true, 'catalog' => $catalog);
    }

    /**
     * Handle AJAX firmware preview request.
     */
    public function handlePreviewFirmwareAssignRequest($request) {
        $sepIds = $this->extractFirmwareDeviceIds($request);
        $firmwareMap = $this->parseFirmwareMap($request['firmware_map'] ?? '');
        if (empty($sepIds)) {
            return array('status' => false, 'message' => _('No devices selected.'));
        }
        if (empty($firmwareMap)) {
            return array('status' => false, 'message' => _('No firmware mapping provided.'));
        }
        return $this->previewFirmwareAssignment($sepIds, $firmwareMap);
    }

    /**
     * Handle AJAX firmware assign request.
     */
    public function handleAssignFirmwareRequest($request) {
        $sepIds = $this->extractFirmwareDeviceIds($request);
        $firmwareMap = $this->parseFirmwareMap($request['firmware_map'] ?? '');
        $triggerReset = !isset($request['trigger_restart']) || $request['trigger_restart'] !== '0';
        if (empty($sepIds)) {
            return array('status' => false, 'message' => _('No devices selected.'));
        }
        if (empty($firmwareMap)) {
            return array('status' => false, 'message' => _('No firmware mapping provided.'));
        }
        return $this->assignFirmwareToDevices($sepIds, $firmwareMap, $triggerReset);
    }

    private function extractFirmwareDeviceIds($request) {
        $sepIds = array();
        if (!empty($request['idn']) && is_array($request['idn'])) {
            $sepIds = $request['idn'];
        } elseif (!empty($request['idn'])) {
            $sepIds = array($request['idn']);
        }
        return $sepIds;
    }

    private function parseFirmwareMap($rawMap) {
        if (is_array($rawMap)) {
            return $rawMap;
        }
        if ($rawMap === '' || $rawMap === null) {
            return array();
        }
        $decoded = json_decode($rawMap, true);
        return is_array($decoded) ? $decoded : array();
    }

    private function isFirmwareSipDeviceRow(array $deviceRow) {
        if (empty($deviceRow['type'])) {
            return (strpos($deviceRow['name'] ?? '', 'sip') !== false);
        }
        return (strpos($deviceRow['type'], 'sip') !== false);
    }
}
