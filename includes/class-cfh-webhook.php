<?php
/**
 * Nicht-blockierender n8n-Webhook-Versand für Custom Form Handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CFH_Webhook {

    /**
     * Sendet die Lead-Daten asynchron an den konfigurierten n8n-Webhook.
     *
     * @param array<string,string> $data     Bereinigte Formulardaten
     * @param array<string,mixed>  $settings Plugin-Einstellungen
     */
    public function trigger( array $data, array $settings ): void {
        $url = esc_url_raw( $settings['n8n_webhook_url'] ?? '' );

        if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return; // Kein Webhook konfiguriert – still überspringen
        }

        $payload = wp_json_encode(
            array_merge(
                $data,
                array(
                    'source'     => home_url(),
                    'submitted'  => gmdate( 'c' ),
                    'plugin'     => 'custom-form-handler',
                    'version'    => CFH_VERSION,
                )
            )
        );

        // Nicht-blockierend: sehr kurzes Timeout, Antwort wird ignoriert
        $headers = array(
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Source'     => 'wordpress-cfh',
        );

        // Optionaler Webhook-Secret als Authentifizierungs-Header
        $secret = trim( $settings['n8n_webhook_secret'] ?? '' );
        if ( $secret !== '' ) {
            $headers['X-Webhook-Secret'] = $secret;
        }

        wp_remote_post(
            $url,
            array(
                'method'      => 'POST',
                'timeout'     => 3,
                'redirection' => 0,
                'blocking'    => false,
                'headers'     => $headers,
                'body'        => $payload,
            )
        );
    }
}
