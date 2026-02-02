<?php
/**
 * WBCE Update-Assistent - Upgrade Script
 *
 * Wird beim Upgrade des Moduls ausgeführt
 * Kann zukünftig für Migrations-Aufgaben verwendet werden
 *
 * @category    module
 * @package     wbce_updater
 * @version     0.9.11
 * @author      WBCE Community
 * @copyright   2025 WBCE Community
 * @license     MIT License
 */

// prevent this file from being accessed directly
if (!defined('WB_PATH')) {
    exit('Direct access to this file is not allowed');
}

// Get current module version from database
global $database;
if (!isset($database) || !$database) {
    require_once WB_PATH . '/framework/class.database.php';
    $database = new database();
}

$result = $database->query(
    "SELECT version FROM " . TABLE_PREFIX . "addons
     WHERE directory='wbce_updater' AND type='module'"
);

if ($result && $result->numRows() > 0) {
    $row = $result->fetchRow(MYSQLI_ASSOC);
    $old_version = $row['version'];

    // Version-specific upgrade tasks
    // Example: if (version_compare($old_version, '0.9.0', '<')) { /* upgrade code */ }

    // Currently no specific upgrade tasks needed
    // This file is prepared for future upgrades
}

// Clean old cache on upgrade
$cache_file = WB_PATH . '/temp/.wbce_releases_cache.json';
if (file_exists($cache_file)) {
    @unlink($cache_file);
}
