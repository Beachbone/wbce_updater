# wbce_update_unzip.php - Funktionsanalyse

## Aktuelle Implementierung:
```php
1. PHP-Version Check: Hard-coded ≥ 8.1.0
2. ZIP öffnen: wbceup.zip
3. Entpacken nach: Root-Verzeichnis
4. Redirect: install/update.php
5. Cleanup: Script + ZIP löschen
```

## Problem:
- Script von URL wird IMMER neueste Version geladen
- Für WBCE 1.5.x wird auch Script mit PHP 8.1 Requirement geladen!
- Inkompatibel mit älteren WBCE-Versionen

## Beispiel-Szenario:
```
Update: WBCE 1.5.3 → 1.6.0
Script: Neuestes (für 1.6.5+) mit PHP ≥ 8.1
Server: PHP 8.0
Ergebnis: ❌ Script blockiert, obwohl 1.6.0 noch PHP 8.0 unterstützt!
```
