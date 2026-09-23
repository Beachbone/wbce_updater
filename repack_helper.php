<?php
/**
 * WBCE Update-Assistent - ZIP Repack Helper
 *
 * Intelligente ZIP-Umpackfunktion für GitHub Releases
 *
 * @category    module
 * @package     wbce_updater
 * @version     1.0.2
 * @author      WBCE Community
 * @copyright   2026 WBCE Community
 * @license     MIT License
 */

/**
 * Findet automatisch den WBCE-Unterordner im GitHub ZIP
 *
 * @param ZipArchive $zip Geöffnetes ZIP-Archiv
 * @return string|false Pfad zum WBCE-Ordner oder false
 */
function findWbceFolder($zip) {
    $foundPaths = [];

    // Alle Einträge durchsuchen
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $path = $stat['name'];

        // Suche nach typischen WBCE-Dateien im Root.
        // Diese Pfade existieren garantiert im wbce/-Wurzelverzeichnis und
        // sind spezifisch genug, um nicht versehentlich in Modulen zu treffen.
        // Hinweis: 'config.php' NICHT verwenden – existiert nicht im Release-ZIP
        // (nur config.php.new), aber in Modulen wie ckeditor/filemanager.
        $wbceMarkers = [
            'framework/class.admin.php',
            'framework/class.wb.php',
            'admin/admintools/tool.php',
            'install/index.php',
        ];

        foreach ($wbceMarkers as $marker) {
            if (substr($path, -strlen($marker)) === $marker) {
                // Extrahiere den Basis-Pfad (alles vor dem Marker)
                $basePath = substr($path, 0, strrpos($path, $marker));
                $foundPaths[] = $basePath;
            }
        }
    }

    if (empty($foundPaths)) {
        return false;
    }

    // Zähle welcher Pfad am häufigsten vorkommt
    $pathCounts = array_count_values($foundPaths);
    arsort($pathCounts);

    // Der häufigste Pfad ist wahrscheinlich der richtige
    return key($pathCounts);
}

/**
 * Alternative: Suche nach einem Ordner mit einem bestimmten Namen
 *
 * @param ZipArchive $zip Geöffnetes ZIP-Archiv
 * @param string $folderName Name des zu suchenden Ordners (z.B. 'wbce')
 * @return string|false Vollständiger Pfad zum Ordner
 */
function findFolderByName($zip, $folderName = 'wbce') {
    $candidates = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $path = $stat['name'];

        // Suche nach Ordnern, die den Namen enthalten
        if (preg_match('#/?' . preg_quote($folderName, '#') . '/#', $path)) {
            // Extrahiere den Pfad bis einschließlich des gesuchten Ordners
            $pos = strpos($path, $folderName . '/');
            if ($pos !== false) {
                $candidate = substr($path, 0, $pos + strlen($folderName) + 1);
                $candidates[] = $candidate;
            }
        }
    }

    if (empty($candidates)) {
        return false;
    }

    // Nehme den kürzesten Pfad (wahrscheinlich der richtige)
    usort($candidates, function($a, $b) {
        return strlen($a) - strlen($b);
    });

    return $candidates[0];
}

/**
 * Erweiterte ZIP-Umpack-Funktion mit automatischer Pfad-Erkennung
 *
 * @param string $sourceZip Quell-ZIP-Datei
 * @param string $targetZip Ziel-ZIP-Datei
 * @param string|null $subPath Optionaler Unterordner-Pfad (null = auto-detect)
 * @param string $targetFolderName Name des zu suchenden Ordners bei Auto-Detect
 * @return array ['success' => bool, 'message' => string, 'found_path' => string]
 */
function repackZip($sourceZip, $targetZip, $subPath = null, $targetFolderName = 'wbce') {
    $zip = new ZipArchive();
    $newZip = new ZipArchive();
    $result = [
        'success' => false,
        'message' => '',
        'found_path' => ''
    ];

    if ($zip->open($sourceZip) !== TRUE) {
        $result['message'] = 'Konnte Quell-ZIP nicht öffnen: ' . $sourceZip;
        return $result;
    }

    // Auto-Detect: Finde den richtigen Pfad
    if ($subPath === null) {
        // Methode 1: Suche nach typischen WBCE-Dateien
        $detectedPath = findWbceFolder($zip);

        // Methode 2 (Fallback): Suche nach Ordnername
        if (!$detectedPath) {
            $detectedPath = findFolderByName($zip, $targetFolderName);
        }

        if (!$detectedPath) {
            $zip->close();
            $result['message'] = 'WBCE-Ordner konnte nicht automatisch gefunden werden';
            return $result;
        }

        $subPath = $detectedPath;
        $result['found_path'] = $subPath;
    }

    // Pfad normalisieren (muss mit / enden, falls nicht leer)
    $subPath = rtrim($subPath, '/');
    if ($subPath !== '') {
        $subPath .= '/';
    }

    if ($newZip->open($targetZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        $zip->close();
        $result['message'] = 'Konnte Ziel-ZIP nicht erstellen: ' . $targetZip;
        return $result;
    }

    $filesAdded = 0;
    $errors = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $fullPath = $stat['name'];

        // Prüfen, ob die Datei im gewünschten Unterordner liegt
        if ($subPath === '' || strpos($fullPath, $subPath) === 0) {
            // Neuen Pfad berechnen (den Präfix abschneiden)
            $relativePath = $subPath === '' ? $fullPath : substr($fullPath, strlen($subPath));

            // Security: Check for path traversal attempts
            if (strpos($relativePath, '..') !== false) {
                $errors[] = 'Sicherheitswarnung: Ungültiger Pfad erkannt: ' . $relativePath;
                continue;
            }

            // Nur hinzufügen, wenn es kein leerer Ordnername ist
            if ($relativePath !== false && $relativePath !== "" && substr($relativePath, -1) !== '/') {
                $content = $zip->getFromIndex($i);

                if ($content === false) {
                    $errors[] = 'Konnte Datei nicht lesen: ' . $fullPath;
                    continue;
                }

                if ($newZip->addFromString($relativePath, $content)) {
                    $filesAdded++;
                } else {
                    $errors[] = 'Konnte Datei nicht hinzufügen: ' . $relativePath;
                }
            }
        }
    }

    $newZip->close();
    $zip->close();

    if ($filesAdded === 0) {
        $result['message'] = 'Keine Dateien gefunden im Pfad: ' . $subPath;
        @unlink($targetZip); // Leeres ZIP löschen
        return $result;
    }

    $result['success'] = true;
    $result['message'] = $filesAdded . ' Dateien erfolgreich umgepackt' .
                         ($subPath !== '' ? ' aus ' . $subPath : '');

    if (!empty($errors)) {
        $result['message'] .= ' (' . count($errors) . ' Fehler)';
        $result['errors'] = $errors;
    }

    return $result;
}

/**
 * Detects an individually renamed admin directory and renames the
 * "admin/" folder in the already packed update ZIP accordingly.
 *
 * WBCE release packages always contain an "admin/" folder. Without this
 * adjustment, updating with a renamed admin directory would just create a
 * new, unused "admin/" folder and leave the actually active directory unpatched.
 *
 * @param string $zipPath Path to the already packed wbceup.zip (modified in-place)
 * @return array ['renamed' => int, 'admin_dir' => string]
 */
function adjustAdminFolderName($zipPath) {
    $result = ['renamed' => 0, 'admin_dir' => 'admin'];

    if (!defined('WB_URL') || !defined('ADMIN_URL')) {
        return $result;
    }

    // Derive the physical name of the active admin directory from ADMIN_URL
    $adminDirName = trim(str_replace(rtrim(WB_URL, '/'), '', ADMIN_URL), '/');

    // Only act if clearly detected, different from the default, and safe
    // (no path separators, dots, etc.)
    if ($adminDirName === '' || $adminDirName === 'admin' || !preg_match('/^[A-Za-z0-9_-]+$/', $adminDirName)) {
        return $result;
    }

    $result['admin_dir'] = $adminDirName;

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== TRUE) {
        return $result;
    }

    $oldPrefix = 'admin/';
    $newPrefix = $adminDirName . '/';
    $renamed = 0;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if ($name !== false && strpos($name, $oldPrefix) === 0) {
            $newName = $newPrefix . substr($name, strlen($oldPrefix));
            if ($zip->renameIndex($i, $newName)) {
                $renamed++;
            }
        }
    }

    $zip->close();
    $result['renamed'] = $renamed;

    return $result;
}

/**
 * Determines which currently active templates/themes are WBCE standard
 * templates and therefore should be protected from being overwritten on update.
 *
 * Extracted into its own pure function (instead of inline in execute_update.php)
 * so it can be unit-tested without a full WBCE bootstrap.
 *
 * @param string|null $activeTemplate Value of DEFAULT_TEMPLATE, or null
 * @param string|null $activeTheme Value of DEFAULT_THEME, or null
 * @param string $standardTemplatesCsv Comma-separated list (WBCE_UPDATER_STANDARD_TEMPLATES)
 * @return array List of folder names to protect (0, 1 or 2 entries)
 */
function getProtectedTemplateFolders($activeTemplate, $activeTheme, $standardTemplatesCsv) {
    $standardTemplates = array_map('trim', explode(',', $standardTemplatesCsv));
    $protected = [];

    if ($activeTemplate !== null && $activeTemplate !== '' && in_array($activeTemplate, $standardTemplates, true)) {
        $protected[] = $activeTemplate;
    }

    if ($activeTheme !== null && $activeTheme !== '' && $activeTheme !== $activeTemplate
        && in_array($activeTheme, $standardTemplates, true)) {
        $protected[] = $activeTheme;
    }

    return $protected;
}

/**
 * Checks whether a ZIP entry name lies inside one of the protected template folders.
 *
 * @param string $filename Entry name in the ZIP (e.g. "templates/wbcetik/index.php")
 * @param array $protectedTemplateFolders Result of getProtectedTemplateFolders()
 * @return bool
 */
function isProtectedTemplateFile($filename, array $protectedTemplateFolders) {
    foreach ($protectedTemplateFolders as $protectedFolder) {
        if (strpos($filename, 'templates/' . $protectedFolder . '/') === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Debug-Funktion: Zeigt die Struktur eines ZIPs
 *
 * @param string $zipPath Pfad zur ZIP-Datei
 * @param int $maxDepth Maximale Verzeichnistiefe (0 = nur erste Ebene)
 * @return array Liste der Einträge
 */
function debugZipStructure($zipPath, $maxDepth = 2) {
    $zip = new ZipArchive();
    $structure = [];

    if ($zip->open($zipPath) !== TRUE) {
        return ['error' => 'ZIP konnte nicht geöffnet werden'];
    }

    for ($i = 0; $i < min($zip->numFiles, 100); $i++) { // Max 100 Einträge zur Sicherheit
        $stat = $zip->statIndex($i);
        $path = $stat['name'];
        $depth = substr_count($path, '/');

        if ($maxDepth === 0 || $depth <= $maxDepth) {
            $structure[] = [
                'path' => $path,
                'size' => $stat['size'],
                'depth' => $depth
            ];
        }
    }

    $zip->close();
    return $structure;
}

// Beispielaufruf mit Auto-Detection:
/*
$result = repackZip(
    'github-download.zip',  // Quell-ZIP
    'wbceup.zip',          // Ziel-ZIP
    null,                  // Auto-detect
    'wbce'                 // Suche nach 'wbce' Ordner
);

if ($result['success']) {
    echo $result['message'];
    echo "\nGefundener Pfad: " . $result['found_path'];
} else {
    echo "Fehler: " . $result['message'];
}
*/

// Beispielaufruf mit festem Pfad (alte Methode):
/*
repackZip(
    'pack.zip',
    'result.zip',
    'WBCE_CMS-1.6.5/wbce/'  // Fester Pfad
);
*/
?>
