<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

$settings = ( new CFH_Settings() )->get();
?>
<div class="wrap">
    <h1>Custom Form Handler &mdash; Einstellungen</h1>

    <?php settings_errors( CFH_OPTION_GROUP ); ?>

    <form method="post" action="options.php">
        <?php
        settings_fields( CFH_OPTION_GROUP );
        do_settings_sections( 'cfh-settings' );
        submit_button( 'Einstellungen speichern' );
        ?>
    </form>

    <p style="margin-top:10px;color:#666;font-size:13px;max-width:720px;">
        Für automatische Plugin-Updates über GitHub tragen Sie beim Feld <strong>GitHub Repository</strong>
        Ihr öffentliches Repository im Format <code>owner/repo</code> ein, z. B.
        <code>6ent/custom-form-handler</code>. Das Release-ZIP muss im GitHub-Release
        <code>custom-form-handler.zip</code> heißen.
    </p>

    <hr>
    <h2>Shortcode</h2>
    <p>Fügen Sie ein Formular auf einer beliebigen Seite oder in einem Widget ein:</p>
    <code>[fenster_lead_form]</code>
    <br><br>
    <code>[energiefoerderung_form]</code>
    <p style="margin-top:8px;color:#666;font-size:13px;">
        Rückwärtskompatibel: <code>[custom_form_shortcode]</code> funktioniert ebenfalls.
    </p>

    <hr>
    <h2>n8n Webhook &mdash; gesendete Felder</h2>
    <p>Folgende JSON-Felder werden an den Webhook übertragen:</p>
    <table class="widefat" style="max-width:600px;">
        <thead><tr><th>Feld</th><th>Beschreibung</th></tr></thead>
        <tbody>
            <tr><td><code>formType</code></td><td>Formulartyp (<code>window</code> oder <code>energy_funding</code>)</td></tr>
            <tr><td><code>windowMaterial</code></td><td>Fenstermaterial (kunststoff / aluminium / holz / beratung)</td></tr>
            <tr><td><code>propertyType</code></td><td>Immobilientyp (house / apartment / commercial)</td></tr>
            <tr><td><code>windowCount</code></td><td>Fensteranzahl (0-10 / 10-20 / 20+)</td></tr>
            <tr><td><code>buildingType</code></td><td>Gebäudetyp (einfamilienhaus / mehrfamilienhaus / wohnung / gewerbe)</td></tr>
            <tr><td><code>ownershipStatus</code></td><td>Eigentumsstatus (eigentuemer / kaeufer / verwaltung / mieter_sonstiges)</td></tr>
            <tr><td><code>projectType</code></td><td>Maßnahme (sanierung / heizung / daemmung / fenster / erneuerbare / beratung_allgemein)</td></tr>
            <tr><td><code>location</code></td><td>PLZ (5 Ziffern)</td></tr>
            <tr><td><code>name</code></td><td>Vor- und Nachname</td></tr>
            <tr><td><code>email</code></td><td>E-Mail-Adresse</td></tr>
            <tr><td><code>phone</code></td><td>Telefonnummer (leer wenn nicht angegeben)</td></tr>
            <tr><td><code>gdpr</code></td><td>DSGVO-Zustimmung (immer "1")</td></tr>
            <tr><td><code>source</code></td><td>Website-URL</td></tr>
            <tr><td><code>submitted</code></td><td>ISO-8601-Zeitstempel</td></tr>
        </tbody>
    </table>
    <p style="margin-top:10px;color:#666;font-size:13px;">
        Das <strong>Webhook Secret</strong> wird als HTTP-Header <code>X-Webhook-Secret</code> gesendet.
        In n8n kann dieser Header unter <em>Header Auth</em> validiert werden.
    </p>

    <hr>
    <h2>Fehleranzeige &mdash; verfügbare Query-Parameter</h2>
    <p>
        Formularfehler werden jetzt bevorzugt direkt auf der Formularseite angezeigt.
        Validierungsfehler erscheinen inline im Formular, technische Absendeprobleme als Pop-up.
        Die konfigurierte Fehlerseite dient nur noch als Fallback, falls keine gültige Formular-URL
        übergeben werden kann.
    </p>
    <p>Beispiel für einen Rücksprung zur Formularseite mit Fehlercode:<br>
       <code><?php echo esc_html( home_url( '/beispiel-formular/?cfh_error=rate_limit&cfh_form_type=window' ) ); ?></code>
    </p>
    <table class="widefat" style="max-width:600px;">
        <thead><tr><th>Wert</th><th>Bedeutung</th></tr></thead>
        <tbody>
            <tr><td><code>security</code></td><td>Technischer Fehler, erscheint als Pop-up</td></tr>
            <tr><td><code>rate_limit</code></td><td>Zu viele Einreichungen von dieser IP, erscheint als Pop-up</td></tr>
            <tr><td><code>mail_failed</code></td><td>E-Mail konnte nicht gesendet werden, erscheint als Pop-up</td></tr>
            <tr><td><code>invalid_material</code></td><td>Ungültiges Fenstermaterial, erscheint inline</td></tr>
            <tr><td><code>invalid_property</code></td><td>Ungültiger Immobilientyp, erscheint inline</td></tr>
            <tr><td><code>invalid_count</code></td><td>Ungültige Fensteranzahl, erscheint inline</td></tr>
            <tr><td><code>invalid_location</code></td><td>Ungültige PLZ, erscheint inline</td></tr>
            <tr><td><code>invalid_name</code></td><td>Name fehlt, erscheint inline</td></tr>
            <tr><td><code>invalid_email</code></td><td>Ungültige E-Mail-Adresse, erscheint inline</td></tr>
            <tr><td><code>invalid_phone</code></td><td>Ungültige Telefonnummer, erscheint inline</td></tr>
            <tr><td><code>gdpr_missing</code></td><td>DSGVO-Zustimmung fehlt, erscheint inline</td></tr>
            <tr><td><code>invalid_building_type</code></td><td>Ungültiger Gebäudetyp, erscheint inline</td></tr>
            <tr><td><code>invalid_ownership_status</code></td><td>Ungültiger Eigentumsstatus, erscheint inline</td></tr>
            <tr><td><code>invalid_project_type</code></td><td>Ungültige Maßnahme, erscheint inline</td></tr>
        </tbody>
    </table>
</div>
