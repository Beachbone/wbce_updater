<?php
/**
 * WBCE Update-Assistent - Main Interface
 *
 * Hauptoberfläche für den WBCE Update-Assistenten
 *
 * @category    module
 * @package     wbce_updater
 * @version     0.9.11
 * @author      WBCE Community
 * @copyright   2025 WBCE Community
 * @license     MIT License
 *
 * WICHTIG: Diese Datei wird vom WBCE Admin-Tools Framework eingebunden.
 * Folgendes ist bereits verfügbar:
 * - $admin (Admin-Objekt, Header bereits ausgegeben)
 * - $modulePath, $languagePath, $returnUrl, $toolDir, $toolName
 * - config.php, framework-Klassen, Sprachdateien
 */

// Prevent direct file access - must be included by the framework
if (count(get_included_files()) == 1) {
    header("Location: ../index.php", true, 301);
    exit;
}

// Load module language file (framework loads core languages, but not module-specific)
$langFile = (file_exists(__DIR__ . '/languages/' . LANGUAGE . '.php'))
    ? __DIR__ . '/languages/' . LANGUAGE . '.php'
    : __DIR__ . '/languages/EN.php';
require_once $langFile;

// Get current WBCE version from database
// $database is globally available through the framework
global $database;
if (!isset($database) || !$database) {
    require_once WB_PATH . '/framework/class.database.php';
    $database = new database();
}

// Security: Use parameterized query (escape values even though they're hardcoded)
$setting_name = $database->escapeString('wbce_version');
$result = $database->query("SELECT value FROM " . TABLE_PREFIX . "settings WHERE name='" . $setting_name . "'");
if ($result && $result->numRows() > 0) {
    $row = $result->fetchRow(MYSQLI_ASSOC);
    $current_version = $row['value'];
} else {
    $current_version = 'Unknown';
}

// Check if Backup Plus is installed (using escaped values)
$addon_dir = $database->escapeString('backup_plus');
$addon_type = $database->escapeString('module');
$result = $database->query("SELECT * FROM " . TABLE_PREFIX . "addons WHERE directory='" . $addon_dir . "' AND type='" . $addon_type . "'");
$backup_plus_installed = ($result && $result->numRows() > 0);

// Add custom CSS
?>
<link rel="stylesheet" href="<?php echo WB_URL; ?>/modules/wbce_updater/css/backend.css">

<div class="wbce-updater-container">
        <h1><?php echo htmlspecialchars($LANG['TOOL_NAME']); ?></h1>
        <p class="version-display"><strong><?php echo htmlspecialchars($LANG['CURRENT_VERSION']); ?>:</strong> <?php echo htmlspecialchars($current_version); ?></p>

        <!-- Backup Warning Section -->
        <div class="backup-section">
            <h3 class="section-title-backup"><?php echo defined('WEBSITE_TITLE') ? WEBSITE_TITLE : 'WBCE CMS'; ?> - Verwaltung</h3>
            <div class="backup-warning">
                <?php if ($backup_plus_installed): ?>
                    <button type="button" class="btn-backup" onclick="openBackupPlus()">
                        🔄 <?php echo $LANG['BACKUP_BUTTON']; ?>
                    </button>
                <?php else: ?>
                    <div class="alert-warning">
                        <p><strong><?php echo $LANG['BACKUP_PLUS_MISSING']; ?></strong></p>
                        <a href="https://addons.wbce.org/pages/addons.php?do=item&item=159"
                           target="_blank"
                           class="btn-secondary">
                            📦 <?php echo $LANG['INSTALL_BACKUP_PLUS']; ?>
                        </a>
                    </div>
                <?php endif; ?>

                <div class="checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="backup_confirmed" onchange="enableUpdateButton()">
                        <span><?php echo $LANG['BACKUP_CONFIRMED']; ?></span>
                    </label>

                    <label class="checkbox-label">
                        <input type="checkbox" id="enable_maintenance">
                        <span><?php echo $LANG['MAINTENANCE_MODE']; ?></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Updates Section -->
        <div class="updates-section">
            <h3 class="section-title-gray"><?php echo $LANG['AVAILABLE_UPDATES']; ?></h3>

            <div class="button-row">
                <button type="button" class="btn-primary" onclick="loadAvailableUpdates()">
                    🔍 <?php echo $LANG['CHECK_UPDATES']; ?>
                </button>

                <button type="button" class="btn-secondary" onclick="jumpToUpload()">
                    📤 <?php echo $LANG['JUMP_TO_UPLOAD']; ?>
                </button>
            </div>

            <div id="loading" class="loading-indicator" style="display:none;">
                <p><?php echo $LANG['LOADING']; ?>...</p>
            </div>

            <div id="updates-container"></div>
        </div>

        <!-- Manual Upload Section -->
        <div id="manual-upload-section" class="updates-section" style="margin-top: 30px;">
            <h3 class="section-title-gray"><?php echo $LANG['MANUAL_UPLOAD_TITLE']; ?></h3>

            <p style="margin-bottom: 15px; color: #666;">
                <?php echo $LANG['MANUAL_UPLOAD_DESCRIPTION']; ?>
            </p>

            <form id="upload-form" method="post" action="<?php echo WB_URL; ?>/modules/wbce_updater/upload.php" enctype="multipart/form-data" onsubmit="return handleUploadSubmit(event);">
                <?php echo $admin->getFTAN(); ?>

                <div class="upload-controls">
                    <input type="file"
                           name="zip_file"
                           id="zip_file"
                           accept=".zip,application/zip"
                           class="file-input"
                           onchange="enableUploadButton()">

                    <button type="submit"
                            id="upload-button"
                            class="btn-primary"
                            disabled>
                        📤 <?php echo $LANG['UPLOAD_AND_PREPARE']; ?>
                    </button>
                </div>

                <input type="hidden" name="backup_confirmed_upload" id="form_backup_confirmed_upload">
                <input type="hidden" name="enable_maintenance_upload" id="form_enable_maintenance_upload">

                <div class="info-box" style="margin-top: 15px; font-size: 13px;">
                    <p style="margin: 0 0 8px 0;">
                        <strong><?php echo $LANG['UPLOAD_NOTE']; ?></strong>
                    </p>
                    <?php
                    // Get max upload size
                    $upload_max = ini_get('upload_max_filesize');
                    $post_max = ini_get('post_max_size');
                    $max_size = min($upload_max, $post_max);

                    // Convert to bytes for comparison
                    function parse_size($size) {
                        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
                        $size = preg_replace('/[^0-9\.]/', '', $size);
                        if ($unit) {
                            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
                        }
                        return round($size);
                    }

                    $max_bytes = parse_size($max_size);
                    $post_max_bytes = parse_size($post_max);
                    $min_required_bytes = 12 * 1024 * 1024; // 12 MB

                    echo '<p style="margin: 0; color: #666;">';
                    echo $LANG['MAX_UPLOAD_SIZE'] . ': <strong>' . $max_size . '</strong><br>';
                    echo 'post_max_size: <strong>' . $post_max . '</strong>';
                    echo '</p>';

                    if ($max_bytes < $min_required_bytes) {
                        // Show warning if less than 12MB
                        echo '<div class="alert-warning" style="margin: 10px 0; padding: 12px; background: #fff3cd; border: 2px solid #f39c12; border-radius: 4px;">';
                        echo '<p style="margin: 0; color: #856404; font-weight: bold;">⚠️ ' . $LANG['UPLOAD_SIZE_WARNING'] . '</p>';
                        echo '<p style="margin: 5px 0 0 0; color: #856404;">';
                        echo '(' . $LANG['RECOMMENDED'] . ': 12M+)';
                        echo '</p>';
                        echo '</div>';
                    }
                    ?>

                    <script>
                    // Store PHP limits in JavaScript
                    window.phpUploadMaxBytes = <?php echo $max_bytes; ?>;
                    window.phpPostMaxBytes = <?php echo $post_max_bytes; ?>;
                    </script>
                </div>
            </form>
        </div>

        <!-- Update Form (hidden) -->
        <form id="update-form" method="post" action="<?php echo WB_URL; ?>/modules/wbce_updater/download.php" style="display:none;">
            <?php echo $admin->getFTAN(); ?>
            <input type="hidden" name="download_url" id="form_download_url">
            <input type="hidden" name="target_version" id="form_target_version">
            <input type="hidden" name="checksum" id="form_checksum">
            <input type="hidden" name="backup_confirmed" id="form_backup_confirmed">
            <input type="hidden" name="enable_maintenance" id="form_enable_maintenance">
        </form>

    <script>
        // Current version for comparison
        const currentVersion = '<?php echo $current_version; ?>';

        // CSRF Token for AJAX requests
        const ftanToken = '<?php echo $admin->getFTAN_value(); ?>';

        // Security: Pre-escape language strings for JavaScript context
        const LANG = {
            ERROR_LOADING_UPDATES: <?php echo json_encode($LANG['ERROR_LOADING_UPDATES']); ?>,
            GITHUB_TIMEOUT_HINT: <?php echo json_encode($LANG['GITHUB_TIMEOUT_HINT']); ?>,
            NO_UPDATES_AVAILABLE: <?php echo json_encode($LANG['NO_UPDATES_AVAILABLE']); ?>,
            UP_TO_DATE: <?php echo json_encode($LANG['UP_TO_DATE']); ?>,
            HIDE_ADDITIONAL_UPDATES: <?php echo json_encode($LANG['HIDE_ADDITIONAL_UPDATES']); ?>,
            SHOW_ADDITIONAL_UPDATES: <?php echo json_encode($LANG['SHOW_ADDITIONAL_UPDATES']); ?>,
            HIDDEN_UPDATES: <?php echo json_encode($LANG['HIDDEN_UPDATES']); ?>,
            RECOMMENDED_UPDATE: <?php echo json_encode($LANG['RECOMMENDED_UPDATE']); ?>,
            OTHER_UPDATES: <?php echo json_encode($LANG['OTHER_UPDATES']); ?>,
            RISK_PATCH: <?php echo json_encode($LANG['RISK_PATCH']); ?>,
            RISK_MINOR: <?php echo json_encode($LANG['RISK_MINOR']); ?>,
            RISK_MAJOR: <?php echo json_encode($LANG['RISK_MAJOR']); ?>,
            PHP_COMPATIBLE: <?php echo json_encode($LANG['PHP_COMPATIBLE']); ?>,
            PHP_INCOMPATIBLE: <?php echo json_encode($LANG['PHP_INCOMPATIBLE']); ?>,
            PHP_EOL_WARNING: <?php echo json_encode($LANG['PHP_EOL_WARNING']); ?>,
            RELEASED: <?php echo json_encode($LANG['RELEASED']); ?>,
            VIEW_DETAILS: <?php echo json_encode($LANG['VIEW_DETAILS']); ?>,
            DOWNLOAD_PREPARE: <?php echo json_encode($LANG['DOWNLOAD_PREPARE']); ?>,
            ERROR_BACKUP_NOT_CONFIRMED: <?php echo json_encode($LANG['ERROR_BACKUP_NOT_CONFIRMED']); ?>,
            ERROR_NO_FILE_UPLOADED: <?php echo json_encode($LANG['ERROR_NO_FILE_UPLOADED']); ?>,
            LOADING: <?php echo json_encode($LANG['LOADING']); ?>,
            CONFIRM_MINOR_UPDATE: <?php echo json_encode($LANG['CONFIRM_MINOR_UPDATE']); ?>,
            CONFIRM_MAJOR_UPDATE: <?php echo json_encode($LANG['CONFIRM_MAJOR_UPDATE']); ?>
        };

        /**
         * Opens Backup Plus in new window
         */
        function openBackupPlus() {
            const backupWindow = window.open(
                '<?php echo ADMIN_URL; ?>/admintools/tool.php?tool=backup_plus',
                '_blank',
                'width=1000,height=800'
            );

            if (backupWindow) {
                // Optional: Ask after some time if backup is complete
                setTimeout(function() {
                    if (confirm(<?php echo json_encode($LANG['BACKUP_COMPLETE_QUESTION']); ?>)) {
                        document.getElementById('backup_confirmed').checked = true;
                        enableUpdateButton();
                    }
                }, 5000);
            }
        }

        /**
         * Enable/disable update buttons based on backup confirmation
         */
        function enableUpdateButton() {
            const checkbox = document.getElementById('backup_confirmed');
            const buttons = document.querySelectorAll('.download-button');

            buttons.forEach(button => {
                button.disabled = !checkbox.checked;
            });

            // Also update upload button state
            const fileInput = document.getElementById('zip_file');
            const uploadButton = document.getElementById('upload-button');
            if (fileInput && uploadButton) {
                uploadButton.disabled = !(checkbox.checked && fileInput.files.length > 0);
            }
        }

        /**
         * Load available updates from GitHub
         */
        function loadAvailableUpdates() {
            const container = document.getElementById('updates-container');
            const loading = document.getElementById('loading');

            loading.style.display = 'block';
            container.innerHTML = '';

            fetch('<?php echo WB_URL; ?>/modules/wbce_updater/check_version.php', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                credentials: 'same-origin', // Include session cookies
                body: 'ftan=' + encodeURIComponent(ftanToken)
            })
            .then(response => {
                // First check if response is ok
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error('Server error (' + response.status + '): ' + (text || 'Unknown error'));
                    });
                }
                // Get text first to check for empty response
                return response.text();
            })
            .then(text => {
                // Check if response is empty
                if (!text || text.trim() === '') {
                    throw new Error('Empty response from server');
                }
                // Try to parse JSON
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON:', text.substring(0, 500));
                    throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                }
            })
            .then(data => {
                loading.style.display = 'none';

                if (data.error) {
                    // Check if error is a timeout/gateway error
                    let errorHtml = '<div class="error-box">' +
                        LANG.ERROR_LOADING_UPDATES + ': ' +
                        escapeHtml(data.error);

                    if (data.error.includes('Timeout') || data.error.includes('Gateway')) {
                        errorHtml += '<br><br><em>' + LANG.GITHUB_TIMEOUT_HINT + '</em>';
                    }

                    errorHtml += '</div>';
                    container.innerHTML = errorHtml;
                    return;
                }

                // Show cache info if cached data is used
                let cacheInfo = '';
                if (data.cached && data.cache_age) {
                    const minutes = Math.floor(data.cache_age / 60);
                    const ageStr = minutes < 1 ? '< 1 Min.' : minutes + ' Min.';
                    cacheInfo = '<div class="info-box" style="margin-bottom: 15px;">' +
                        '<?php echo str_replace('%s', '\' + ageStr + \'', $LANG['CACHED_DATA_INFO']); ?>' +
                        '</div>';
                }

                if (data.updates && data.updates.length > 0) {
                    container.innerHTML = cacheInfo;
                    displayUpdates(data.updates);
                } else {
                    container.innerHTML = cacheInfo + '<div class="info-box">' +
                        LANG.NO_UPDATES_AVAILABLE + '</div>';
                }
            })
            .catch(error => {
                loading.style.display = 'none';
                container.innerHTML = '<div class="error-box">' +
                    LANG.ERROR_LOADING_UPDATES + ': ' +
                    escapeHtml(error.message) + '</div>';
                console.error('Error:', error);
            });
        }

        /**
         * Mark updates according to display rules:
         * - Same minor version: Show all patch updates
         * - Next minor version (+1): Show only highest patch
         * - Higher minor versions (+2 or more): Show only first (lowest) version
         * - Major version changes: Show only first version
         *
         * Returns all updates with 'hidden' property set accordingly
         */
        function markUpdatesVisibility(updates) {
            const current = parseVersion(currentVersion);
            const result = [];

            // Group updates by major.minor
            const grouped = {};
            updates.forEach(update => {
                if (!isNewerVersion(currentVersion, update.version)) {
                    return; // Skip older or same versions
                }

                const v = parseVersion(update.version);
                const key = v.major + '.' + v.minor;

                if (!grouped[key]) {
                    grouped[key] = [];
                }
                grouped[key].push({
                    ...update,
                    parsed: v,
                    hidden: false // Default: visible
                });
            });

            // Sort each group by patch version
            Object.keys(grouped).forEach(key => {
                grouped[key].sort((a, b) => a.parsed.patch - b.parsed.patch);
            });

            // Process each group according to rules
            Object.keys(grouped).forEach(key => {
                const group = grouped[key];
                if (group.length === 0) return;

                const v = group[0].parsed;
                const majorDiff = v.major - current.major;
                const minorDiff = v.minor - current.minor;

                if (majorDiff === 0 && minorDiff === 0) {
                    // Same minor version: Show ALL patch updates
                    group.forEach(update => {
                        update.hidden = false;
                        result.push(update);
                    });
                } else if (majorDiff === 0 && minorDiff === 1) {
                    // Next minor version (+1): Show only HIGHEST patch, hide others
                    const highestIndex = group.length - 1;
                    group.forEach((update, index) => {
                        update.hidden = (index !== highestIndex);
                        result.push(update);
                    });
                } else {
                    // Higher minor (+2 or more) or different major: Show only LOWEST, hide others
                    group.forEach((update, index) => {
                        update.hidden = (index !== 0);
                        result.push(update);
                    });
                }
            });

            // Sort all updates by version (ascending)
            result.sort((a, b) => {
                if (a.parsed.major !== b.parsed.major) return a.parsed.major - b.parsed.major;
                if (a.parsed.minor !== b.parsed.minor) return a.parsed.minor - b.parsed.minor;
                return a.parsed.patch - b.parsed.patch;
            });

            return result;
        }

        /**
         * Parse version string into object
         */
        function parseVersion(versionStr) {
            const parts = versionStr.split('.').map(Number);
            return {
                major: parts[0] || 0,
                minor: parts[1] || 0,
                patch: parts[2] || 0
            };
        }

        /**
         * Toggle visibility of hidden updates
         */
        function toggleHiddenUpdates() {
            const container = document.getElementById('updates-container');
            const hiddenItems = container.querySelectorAll('.update-hidden');
            const toggleBtns = container.querySelectorAll('.toggle-hidden-btn');
            const isCurrentlyHidden = hiddenItems.length > 0 && hiddenItems[0].style.display === 'none';

            hiddenItems.forEach(item => {
                item.style.display = isCurrentlyHidden ? 'block' : 'none';
            });

            toggleBtns.forEach(btn => {
                btn.textContent = isCurrentlyHidden
                    ? LANG.HIDE_ADDITIONAL_UPDATES
                    : LANG.SHOW_ADDITIONAL_UPDATES;
            });
        }

        /**
         * Check PHP compatibility for all updates
         */
        async function checkPhpCompatibilityForUpdates(updates) {
            const promises = updates.map(async (update) => {
                try {
                    const response = await fetch('<?php echo WB_URL; ?>/modules/wbce_updater/check_compatibility.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        credentials: 'same-origin',
                        body: 'ftan=' + encodeURIComponent(ftanToken) + '&version=' + encodeURIComponent(update.version)
                    });
                    if (response.ok) {
                        const data = await response.json();
                        update.phpCompatibility = data;
                    }
                } catch (error) {
                    console.error('PHP compatibility check failed for version ' + update.version, error);
                }
            });
            await Promise.all(promises);
        }

        /**
         * Display available updates
         */
        async function displayUpdates(updates) {
            const container = document.getElementById('updates-container');
            container.innerHTML = '';

            // Mark updates with visibility according to rules
            const allUpdates = markUpdatesVisibility(updates);

            // Check PHP compatibility for all updates
            await checkPhpCompatibilityForUpdates(allUpdates);

            // Count hidden updates
            const hiddenCount = allUpdates.filter(u => u.hidden).length;

            // Separate recommended and other updates
            let recommendedUpdate = null;
            const otherUpdates = [];

            allUpdates.forEach(update => {
                const riskLevel = calculateRiskLevel(currentVersion, update.version);
                update.riskLevel = riskLevel;

                // First visible patch update is recommended
                if (riskLevel === 'patch' && recommendedUpdate === null && !update.hidden) {
                    recommendedUpdate = update;
                } else {
                    otherUpdates.push(update);
                }
            });

            // Display recommended update
            if (recommendedUpdate) {
                const recSection = document.createElement('div');
                recSection.className = 'recommended-section';
                recSection.innerHTML = '<h3 class="section-title-green">' + LANG.RECOMMENDED_UPDATE + '</h3>';
                recSection.appendChild(createUpdateCard(recommendedUpdate, true, false));
                container.appendChild(recSection);
            }

            // Display other updates section
            if (otherUpdates.length > 0) {
                const otherSection = document.createElement('div');
                otherSection.className = 'other-updates-section';
                otherSection.innerHTML = '<h3 class="section-title-gray">' + LANG.OTHER_UPDATES + '</h3>';

                // Add toggle button at the top if there are hidden updates
                if (hiddenCount > 0) {
                    const toggleBtnTop = document.createElement('button');
                    toggleBtnTop.type = 'button';
                    toggleBtnTop.className = 'btn-toggle toggle-hidden-btn';
                    toggleBtnTop.textContent = LANG.SHOW_ADDITIONAL_UPDATES;
                    toggleBtnTop.onclick = toggleHiddenUpdates;
                    otherSection.appendChild(toggleBtnTop);

                    const countInfo = document.createElement('span');
                    countInfo.className = 'hidden-count';
                    countInfo.textContent = ' (' + hiddenCount + ' ' + LANG.HIDDEN_UPDATES + ')';
                    otherSection.appendChild(countInfo);
                }

                otherUpdates.forEach(update => {
                    otherSection.appendChild(createUpdateCard(update, false, update.hidden));
                });

                // Add toggle button at the bottom if there are hidden updates
                if (hiddenCount > 0) {
                    const toggleBtnBottom = document.createElement('button');
                    toggleBtnBottom.type = 'button';
                    toggleBtnBottom.className = 'btn-toggle toggle-hidden-btn';
                    toggleBtnBottom.textContent = LANG.SHOW_ADDITIONAL_UPDATES;
                    toggleBtnBottom.onclick = toggleHiddenUpdates;
                    otherSection.appendChild(toggleBtnBottom);
                }

                container.appendChild(otherSection);
            }

            // Check if no updates were found
            if (!recommendedUpdate && otherUpdates.length === 0) {
                container.innerHTML = '<div class="info-box">' +
                    '<strong>✓ ' + LANG.NO_UPDATES_AVAILABLE + '</strong><br>' +
                    LANG.UP_TO_DATE +
                    '</div>';
            }

            // Enable/disable buttons based on backup checkbox
            enableUpdateButton();
        }

        /**
         * Create update card element
         */
        function createUpdateCard(update, isRecommended, isHidden = false) {
            const div = document.createElement('div');
            div.className = 'update-item risk-' + update.riskLevel + (isHidden ? ' update-hidden' : '');

            // Hide element initially if marked as hidden
            if (isHidden) {
                div.style.display = 'none';
            }

            const riskLabels = {
                'patch': LANG.RISK_PATCH,
                'minor': LANG.RISK_MINOR,
                'major': LANG.RISK_MAJOR
            };

            const riskIcons = {
                'patch': '✓',
                'minor': '⚠️',
                'major': '🛑'
            };

            // Build compatibility badge HTML
            let compatibilityBadgeHtml = '';
            if (update.phpCompatibility) {
                if (update.phpCompatibility.compatible) {
                    compatibilityBadgeHtml = '<span class="badge badge-success">✓ ' + LANG.PHP_COMPATIBLE + '</span>';
                    if (update.phpCompatibility.php_eol && update.phpCompatibility.php_eol.is_eol) {
                        compatibilityBadgeHtml += ' <span class="badge badge-warning">⚠ ' + LANG.PHP_EOL_WARNING + '</span>';
                    }
                } else {
                    compatibilityBadgeHtml = '<span class="badge badge-danger">⚠ ' + LANG.PHP_INCOMPATIBLE + '</span>';
                }
            }

            // Sanitize riskLevel to prevent XSS (only allow known values)
            const safeRiskLevel = ['patch', 'minor', 'major'].includes(update.riskLevel) ? update.riskLevel : 'patch';

            div.innerHTML = `
                <div class="update-header">
                    <div class="update-version-title">
                        <h4>${escapeHtml(currentVersion)} → ${escapeHtml(update.version)}</h4>
                        <span class="badge badge-${safeRiskLevel}">${riskIcons[safeRiskLevel]} ${riskLabels[safeRiskLevel]}</span>
                        ${compatibilityBadgeHtml}
                    </div>
                </div>
                <div class="update-content">
                    <h5 class="update-name">${escapeHtml(update.name)}</h5>
                    <p class="update-date">${LANG.RELEASED}: ${formatDate(update.published_at)}</p>
                    ${update.body ? '<div class="update-description">' + escapeHtml(update.body.substring(0, 250)) + ' ...</div>' : ''}
                    ${update.html_url ? '<p class="update-link"><a href="' + escapeHtml(update.html_url) + '" target="_blank">' + LANG.VIEW_DETAILS + '</a></p>' : ''}
                </div>
                <div class="update-actions">
                    <button type="button"
                            class="btn-download download-button"
                            data-checksum="${escapeHtml(update.checksum || '')}"
                            onclick="prepareUpdate('${escapeHtml(update.download_url)}', '${escapeHtml(update.version)}', '${safeRiskLevel}', '${escapeHtml(update.checksum || '')}')"
                            disabled>
                        📥 ${LANG.DOWNLOAD_PREPARE}
                    </button>
                </div>
            `;

            return div;
        }

        /**
         * Check if target version is newer than current version
         */
        function isNewerVersion(current, target) {
            const c = current.split('.').map(Number);
            const t = target.split('.').map(Number);

            // Ensure we have at least 3 parts (major.minor.patch)
            while (c.length < 3) c.push(0);
            while (t.length < 3) t.push(0);

            // Compare major version
            if (t[0] > c[0]) return true;
            if (t[0] < c[0]) return false;

            // Major is same, compare minor version
            if (t[1] > c[1]) return true;
            if (t[1] < c[1]) return false;

            // Major and minor are same, compare patch version
            if (t[2] > c[2]) return true;

            // Same version or older
            return false;
        }

        /**
         * Calculate risk level based on version comparison
         *
         * Risikostufen:
         * - major (rot): Major-Version unterschiedlich ODER Minor-Differenz > 1
         * - minor (gelb): Minor-Differenz == 1
         * - patch (grün): Nur Patch-Version unterschiedlich
         */
        function calculateRiskLevel(current, target) {
            const c = current.split('.').map(Number);
            const t = target.split('.').map(Number);

            // Ensure we have at least 3 parts
            while (c.length < 3) c.push(0);
            while (t.length < 3) t.push(0);

            // Major version change (1.x → 2.x) = major risk
            if (c[0] !== t[0]) return 'major';

            // Minor version difference
            const minorDiff = t[1] - c[1];

            // Minor-Sprung > 1 (z.B. 1.5 → 1.7) = major risk (rot)
            if (minorDiff > 1) return 'major';

            // Minor-Sprung == 1 (z.B. 1.5 → 1.6) = minor risk (gelb)
            if (minorDiff === 1) return 'minor';

            // Patch version change (1.6.3 → 1.6.5) = patch risk (grün)
            return 'patch';
        }

        /**
         * Prepare update by submitting form
         */
        async function prepareUpdate(downloadUrl, targetVersion, riskLevel, checksum) {
            // Check PHP compatibility
            let phpCompatible = true;
            try {
                const response = await fetch('<?php echo WB_URL; ?>/modules/wbce_updater/check_compatibility.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    credentials: 'same-origin',
                    body: 'ftan=' + encodeURIComponent(ftanToken) + '&version=' + encodeURIComponent(targetVersion)
                });
                if (response.ok) {
                    const data = await response.json();
                    if (!data.compatible) {
                        phpCompatible = false;
                        const phpMin = data.details.php_min || '?';
                        const phpMax = data.details.php_max || '?';
                        const phpRecommended = data.details.php_recommended || phpMin;
                        const phpCurrent = data.details.php_current || '<?php echo PHP_VERSION; ?>';

                        const confirmMsg = <?php echo json_encode($LANG['CONFIRM_PHP_INCOMPATIBLE']); ?>
                            .replace('%s', phpCurrent)
                            .replace('%s', targetVersion)
                            .replace('%s', phpMin)
                            .replace('%s', phpMax)
                            .replace('%s', phpRecommended);

                        if (!confirm(confirmMsg)) {
                            return;
                        }
                    }
                }
            } catch (error) {
                console.error('PHP compatibility check failed:', error);
            }

            // Extra confirmation for minor and major updates
            if (riskLevel === 'minor') {
                if (!confirm(<?php echo json_encode($LANG['CONFIRM_MINOR_UPDATE']); ?>)) {
                    return;
                }
            } else if (riskLevel === 'major') {
                if (!confirm(<?php echo json_encode($LANG['CONFIRM_MAJOR_UPDATE']); ?>)) {
                    return;
                }
            }

            // Final confirmation
            if (!confirm(<?php echo json_encode($LANG['CONFIRM_DOWNLOAD']); ?>)) {
                return;
            }

            // Fill form and submit
            document.getElementById('form_download_url').value = downloadUrl;
            document.getElementById('form_target_version').value = targetVersion;
            document.getElementById('form_checksum').value = checksum || '';
            document.getElementById('form_backup_confirmed').value =
                document.getElementById('backup_confirmed').checked ? '1' : '0';
            document.getElementById('form_enable_maintenance').value =
                document.getElementById('enable_maintenance').checked ? '1' : '0';

            document.getElementById('update-form').submit();
        }

        /**
         * Format date string
         */
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('<?php echo LANGUAGE; ?>', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        /**
         * Escape HTML
         */
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * Jump to manual upload section
         */
        function jumpToUpload() {
            const uploadSection = document.getElementById('manual-upload-section');
            if (uploadSection) {
                uploadSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        /**
         * Enable upload button when file is selected
         */
        function enableUploadButton() {
            const fileInput = document.getElementById('zip_file');
            const uploadButton = document.getElementById('upload-button');
            const backupCheckbox = document.getElementById('backup_confirmed');

            // Enable upload button only if file is selected AND backup is confirmed
            if (fileInput.files.length > 0 && backupCheckbox.checked) {
                uploadButton.disabled = false;
            } else {
                uploadButton.disabled = true;
            }
        }

        /**
         * Handle upload form submission
         */
        function handleUploadSubmit(event) {
            const backupCheckbox = document.getElementById('backup_confirmed');
            const fileInput = document.getElementById('zip_file');

            // Check if backup is confirmed
            if (!backupCheckbox.checked) {
                alert(LANG.ERROR_BACKUP_NOT_CONFIRMED);
                event.preventDefault();
                return false;
            }

            // Check if file is selected
            if (fileInput.files.length === 0) {
                alert(LANG.ERROR_NO_FILE_UPLOADED);
                event.preventDefault();
                return false;
            }

            // Check file size against PHP limits
            const file = fileInput.files[0];
            const fileSize = file.size;

            if (fileSize > window.phpUploadMaxBytes) {
                alert('Datei ist zu groß!\n\nDateigröße: ' + (fileSize / 1024 / 1024).toFixed(2) + ' MB\nMaximum: ' + (window.phpUploadMaxBytes / 1024 / 1024).toFixed(2) + ' MB\n\nBitte erhöhen Sie upload_max_filesize in der php.ini');
                event.preventDefault();
                return false;
            }

            if (fileSize > (window.phpPostMaxBytes - 1048576)) {
                alert('Datei ist zu groß für POST!\n\nDateigröße: ' + (fileSize / 1024 / 1024).toFixed(2) + ' MB\nPOST Maximum: ' + (window.phpPostMaxBytes / 1024 / 1024).toFixed(2) + ' MB\n\nBitte erhöhen Sie post_max_size in der php.ini');
                event.preventDefault();
                return false;
            }

            // Set hidden form fields from checkboxes
            document.getElementById('form_backup_confirmed_upload').value =
                backupCheckbox.checked ? '1' : '0';
            document.getElementById('form_enable_maintenance_upload').value =
                document.getElementById('enable_maintenance').checked ? '1' : '0';

            // Confirm and submit
            if (!confirm(<?php echo json_encode($LANG['CONFIRM_DOWNLOAD']); ?>)) {
                event.preventDefault();
                return false;
            }

            document.getElementById('upload-button').disabled = true;
            document.getElementById('upload-button').textContent = '⏳ ' + LANG.LOADING + '...';

            return true;
        }

        // Auto-load updates on page load
        window.addEventListener('DOMContentLoaded', function() {
            loadAvailableUpdates();
        });
    </script>
</div> <!-- end wbce-updater-container -->
<?php
// Footer wird vom Framework ausgegeben - hier NICHT $admin->print_footer() aufrufen!
