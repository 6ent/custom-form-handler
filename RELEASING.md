# Releasing Custom Form Handler

## 1. Repository vorbereiten

- Erstellen Sie auf GitHub ein neues öffentliches Repository.
- Kopieren Sie die Repository-Adresse.
- Tragen Sie in WordPress unter `Einstellungen > Form Handler` das Repository im Format `owner/repo` ein.

## 2. Erstes Push-Setup

```bash
git remote add origin <REPOSITORY_URL>
git branch -M main
git add .
git commit -m "Initial release setup"
git push -u origin main
```

## 3. Neue Version veröffentlichen

- Erhöhen Sie die Version in `custom-form-handler.php`.
- Committen und pushen Sie Ihre Änderungen.
- Erstellen Sie einen Git-Tag im Format `vX.Y.Z`.

```bash
git add .
git commit -m "Release vX.Y.Z"
git push origin main
git tag vX.Y.Z
git push origin vX.Y.Z
```

## 4. Automatisches Ergebnis

- GitHub Actions baut automatisch `custom-form-handler.zip`.
- Das ZIP wird an den GitHub Release angehängt.
- WordPress erkennt die neue Version im Plugin-Admin und kann sie direkt installieren.
