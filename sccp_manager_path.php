<?php
/**
 * Resolve the sccp_manager module directory from the running copy,
 * not from AMPWEBROOT (which may point at an outdated install).
 */
if (!function_exists('sccp_manager_module_dir')) {
    function sccp_manager_module_dir() {
        static $moduleDir = null;
        if ($moduleDir !== null) {
            return $moduleDir;
        }
        if (defined('SCCP_MANAGER_MODULE_DIR')) {
            $moduleDir = SCCP_MANAGER_MODULE_DIR;
            return $moduleDir;
        }
        $moduleDir = dirname(__FILE__);
        return $moduleDir;
    }
}

if (!function_exists('sccp_manager_path')) {
    function sccp_manager_path($relativePath = '') {
        $base = rtrim(sccp_manager_module_dir(), '/');
        $relativePath = ltrim((string) $relativePath, '/');
        return $relativePath === '' ? $base : $base . '/' . $relativePath;
    }
}
