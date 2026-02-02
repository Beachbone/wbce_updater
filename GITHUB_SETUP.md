# GitHub Repository Setup - WBCE Updater

## Schritt 1: Repository auf GitHub erstellen

1. Öffne https://github.com/new
2. Repository-Name: `wbce_updater`
3. Beschreibung: `WBCE CMS Update Assistant with PHP compatibility check and checksum validation`
4. Sichtbarkeit: **Public** (empfohlen für Open Source)
5. **NICHT** initialisieren mit README, .gitignore oder License (bereits vorhanden!)
6. Klicke "Create repository"

## Schritt 2: Remote hinzufügen und pushen

GitHub zeigt dir dann Anweisungen. Nutze diese Befehle:

```bash
# Remote-Repository hinzufügen (ersetze USERNAME mit deinem GitHub-Namen)
git remote add origin https://github.com/USERNAME/wbce_updater.git

# Oder mit SSH (empfohlen wenn SSH-Key eingerichtet):
git remote add origin git@github.com:USERNAME/wbce_updater.git

# Zum main Branch pushen
git push -u origin main
```

## Schritt 3: Repository-Settings (optional)

### Topics hinzufügen
Füge folgende Topics hinzu für bessere Auffindbarkeit:
- `wbce`
- `cms`
- `updater`
- `php`
- `security`
- `checksum-validation`
- `compatibility-check`

### About hinzufügen
- Description: `WBCE CMS Update Assistant with PHP compatibility check and SHA256 checksum validation`
- Website: `https://wbce.org`

### GitHub Pages aktivieren (optional)
- Settings → Pages
- Source: Deploy from branch `main` → `/` (root)

## Schritt 4: Release erstellen (optional)

1. Gehe zu "Releases" → "Create a new release"
2. Tag: `v1.0.0`
3. Release title: `WBCE Updater v1.0.0 - Initial Release`
4. Beschreibung: Kopiere aus README.md oder verwende:

```markdown
## Features

### 🔒 Sicherheit
- SHA256 Checksummen-Validierung
- Timing-Attack-Schutz
- HTTPS-Erzwingung
- SSL-Verifikation

### ✅ PHP-Kompatibilitätsprüfung
- Prüft PHP-Version vor dem Update
- EOL-Warnungen für veraltete PHP-Versionen
- Visuelle Badges im Frontend

### 📦 Update-Funktionen
- GitHub Release Integration
- Digest-Feld Unterstützung
- Wartungsmodus-Integration
- Manueller Upload mit Checksummen-Anzeige

## Installation

1. Download ZIP
2. Entpacken nach `/modules/wbce_updater/`
3. Im WBCE Backend unter "Addons" → "Module" installieren
```

5. Publish release

## Schritt 5: Verifizierung

```bash
# Prüfe ob Push erfolgreich war
git remote -v
git log --oneline

# Öffne GitHub Repository
# Sollte jetzt alle Dateien enthalten
```

## Alternative: GitHub CLI

Falls `gh` CLI installiert ist:

```bash
# Repository erstellen und pushen (alles in einem Befehl)
gh repo create wbce_updater --public --source=. --remote=origin --push

# Release erstellen
gh release create v1.0.0 --title "WBCE Updater v1.0.0" --notes-file README.md
```

## Troubleshooting

### "remote origin already exists"
```bash
git remote remove origin
# Dann erneut: git remote add origin ...
```

### Authentication failed
```bash
# Mit Personal Access Token authentifizieren
# GitHub Settings → Developer settings → Personal access tokens
# Oder SSH-Key einrichten
```

### Push rejected
```bash
# Falls jemand bereits Änderungen gemacht hat
git pull origin main --rebase
git push origin main
```
