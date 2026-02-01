# WBCE Updater

Ein Update-Assistent für WBCE CMS mit PHP-Kompatibilitätsprüfung und Checksummen-Validierung.

## Features

### 🔒 Sicherheit
- **SHA256 Checksummen-Validierung**: Überprüft Downloads auf Integrität
- **Timing-Attack-Schutz**: Sichere Hash-Vergleiche mit `hash_equals()`
- **HTTPS-Erzwingung**: Nur verschlüsselte Verbindungen
- **SSL-Verifikation**: Zertifikatsprüfung für externe Requests

### ✅ PHP-Kompatibilitätsprüfung
- Prüft PHP-Version vor dem Update
- EOL-Warnungen für veraltete PHP-Versionen
- Lädt Requirements von URL mit Fallback auf lokale Datei
- 1-Stunden-Cache für Performance
- Visuelle Badges im Frontend (✓ kompatibel / ⚠ inkompatibel)
- Bestätigungsdialog bei Inkompatibilität

### 📦 Update-Funktionen
- GitHub Release Integration
- Digest-Feld Unterstützung (GitHub API)
- Automatische ZIP-Umpackung
- Wartungsmodus-Integration
- Manueller Upload mit Checksummen-Anzeige
- Backup-Integration (Backup Plus)

## Installation

1. Modul in `/modules/wbce_updater/` kopieren
2. Im WBCE Backend unter "Addons" → "Module" installieren
3. Über "Admin-Tools" → "WBCE Update-Assistent" aufrufen

## Lizenz

MIT License

## Autoren

WBCE Community
