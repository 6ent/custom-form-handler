# Custom Form Handler

WordPress-Plugin für Multi-Step-Lead-Formulare mit:

- Fenster-Anfragen
- Energieberatung / BAFA / KfW
- HTML-E-Mail-Versand
- n8n-Webhook
- GitHub-basierte Plugin-Updates

## Funktionen

- Multi-Step-Formulare ohne jQuery
- Serverseitige Validierung
- Nonce-Schutz und Honeypot
- Rate-Limit pro IP
- Inline-Fehler für Feldvalidierung
- Pop-up-Fehler bei technischen Absendeproblemen
- GitHub-Release-Workflow für Updates

## Shortcodes

```text
[fenster_lead_form]
[custom_form_shortcode]
[energiefoerderung_form]
```

## Plugin-Einstellungen

Unter `Einstellungen > Form Handler` können konfiguriert werden:

- Empfänger-E-Mail
- E-Mail-Betreff
- Absendername
- Danke-Seite
- Fehler-Seite (Fallback)
- n8n Webhook URL
- n8n Webhook Secret
- GitHub Repository

## Automatische Updates

Das Plugin ist auf GitHub-Releases vorbereitet.

- Repository: `6ent/custom-form-handler`
- Release-Asset: `custom-form-handler.zip`

Sobald ein neuer GitHub-Release mit höherer Version existiert, sollte WordPress ein Plugin-Update anzeigen.

## Entwicklung

Lokale Syntax-Pruefung:

```bash
find . -name '*.php' -exec php -l {} \;
```

Lokales Deployment nach XAMPP:

```bash
./scripts/deploy-to-xampp.sh --dry-run
./scripts/deploy-to-xampp.sh
```

Automatisches Deployment nach jedem lokalen Commit aktivieren:

```bash
./scripts/install-git-hooks.sh
```

Danach fuehrt Git bei jedem `git commit` automatisch `scripts/deploy-to-xampp.sh` aus und synchronisiert das Plugin nach:

```text
/Applications/XAMPP/xamppfiles/htdocs/wordpress/wp-content/plugins/custom-form-handler
```

Optional kann das Ziel ueber `CFH_XAMPP_PLUGIN_DIR` oder `--target-dir` ueberschrieben werden.

Änderungen pushen:

```bash
git add .
git commit -m "Ihre Änderung"
git push origin main
```

## Release-Workflow

Der GitHub-Workflow kann:

- die Patch-Version automatisch erhöhen
- einen Tag erzeugen
- `custom-form-handler.zip` bauen
- einen GitHub-Release erstellen

Details stehen in [RELEASING.md](./RELEASING.md).
