#!/bin/bash

echo "=== WBCE Updater - GitHub Push Script ==="
echo ""
echo "Schritt 1: Repository auf GitHub erstellen"
echo "=========================================="
echo ""
echo "Bitte öffnen Sie in Ihrem Browser:"
echo "  https://github.com/new"
echo ""
echo "Einstellungen:"
echo "  Repository name: wbce_updater"
echo "  Description: WBCE CMS Update Assistant with PHP compatibility check"
echo "  Visibility: Public"
echo "  [ ] NICHT initialisieren mit README, .gitignore oder License!"
echo ""
echo "Drücken Sie Enter, wenn das Repository erstellt ist..."
read

echo ""
echo "Schritt 2: Remote hinzufügen und pushen"
echo "======================================="
echo ""

# Git-Benutzer auf Beachbone setzen
git config user.name "Beachbone"
git config user.email "J.Auerbach@b50.de"

# Remote hinzufügen (mit SSH - funktioniert bereits!)
echo "Füge Remote hinzu..."
git remote add origin git@github.com:Beachbone/wbce_updater.git 2>/dev/null || \
git remote set-url origin git@github.com:Beachbone/wbce_updater.git

echo "Remote konfiguriert:"
git remote -v

echo ""
echo "Push zum GitHub..."
git push -u origin main

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Erfolgreich gepusht!"
    echo ""
    echo "Repository ist erreichbar unter:"
    echo "  https://github.com/Beachbone/wbce_updater"
    echo ""
else
    echo ""
    echo "❌ Push fehlgeschlagen!"
    echo ""
    echo "Mögliche Probleme:"
    echo "  1. Repository auf GitHub noch nicht erstellt"
    echo "  2. Repository-Name stimmt nicht (muss 'wbce_updater' sein)"
    echo "  3. Repository gehört nicht zu 'Beachbone'"
    echo ""
    echo "Wenn Repository anders heißt oder anderem User gehört:"
    echo "  git remote set-url origin git@github.com:USERNAME/REPONAME.git"
fi
