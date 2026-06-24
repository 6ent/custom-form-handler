# Releasing Custom Form Handler

## 1. Repository vorbereiten

- Das öffentliche Repository ist `6ent/custom-form-handler`.
- Prüfen Sie in WordPress unter `Einstellungen > Form Handler`, dass beim Feld `GitHub Repository` derselbe Wert gesetzt ist.

## 2. Erstes Push-Setup

```bash
git remote add origin <REPOSITORY_URL>
git branch -M main
git add .
git commit -m "Initial release setup"
git push -u origin main
```

## 3. Neue Version veröffentlichen

- Committen und pushen Sie Ihre Änderungen nach `main`.
- Die Patch-Version in `custom-form-handler.php` wird dann automatisch erhöht.
- GitHub erstellt automatisch Commit, Tag, ZIP und Release.

```bash
git add .
git commit -m "Ihre Änderung"
git push origin main
```

## 4. Automatisches Ergebnis

- GitHub Actions erhöht automatisch die Patch-Version, z. B. von `2.4.2` auf `2.4.3`.
- GitHub Actions erstellt automatisch einen Tag im Format `vX.Y.Z`.
- GitHub Actions baut automatisch `custom-form-handler.zip`.
- Das ZIP wird an den GitHub Release angehängt.
- WordPress erkennt die neue Version im Plugin-Admin und kann sie direkt installieren.
