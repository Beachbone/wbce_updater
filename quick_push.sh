#!/bin/bash
# Schnell-Push ohne Interaktion (falls Repository bereits existiert)

echo "=== Quick Push zu GitHub ==="

# Git-Benutzer setzen
git config user.name "Beachbone"
git config user.email "J.Auerbach@b50.de"

# Remote setzen
git remote set-url origin git@github.com:Beachbone/wbce_updater.git 2>/dev/null || \
git remote add origin git@github.com:Beachbone/wbce_updater.git

# Push
echo "Pushe zu GitHub..."
git push -u origin main

echo ""
echo "✅ Fertig! Repository: https://github.com/Beachbone/wbce_updater"
