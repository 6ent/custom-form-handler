<?php
/**
 * HTML-E-Mail versenden für Custom Form Handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CFH_Mailer {

    /**
     * @param array<string,string> $data
     * @param array<string,mixed>  $settings
     */
    public function send( array $data, array $settings ): bool {
        $to = $this->resolve_recipient_email( $settings );
        if ( $to === '' ) {
            error_log( '[CFH] Kein gültiger Empfänger und keine gültige WordPress-Admin-E-Mail verfügbar.' );
            return false;
        }

        $subject = $this->build_subject( $data['formType'] ?? CFH_Form_Definitions::TYPE_WINDOW, $settings );
        $headers = $this->build_headers( $data, $settings );
        $body    = $this->build_html_body( $data );

        $sent = wp_mail( $to, $subject, $body, $headers );

        if ( ! $sent ) {
            error_log( '[CFH] wp_mail() fehlgeschlagen für Empfänger: ' . $to );
        }

        return $sent;
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function resolve_recipient_email( array $settings ): string {
        $configured_email = sanitize_email( $settings['recipient_email'] ?? '' );
        if ( is_email( $configured_email ) ) {
            return $configured_email;
        }

        $admin_email = sanitize_email( get_option( 'admin_email' ) );
        if ( is_email( $admin_email ) ) {
            error_log( '[CFH] Ungültiger Lead-Empfänger konfiguriert; WordPress-Admin-E-Mail wird als Fallback verwendet.' );
            return $admin_email;
        }

        return '';
    }

    /** @return string[] */
    private function build_headers( array $data, array $settings ): array {
        $form_type         = $data['formType'] ?? CFH_Form_Definitions::TYPE_WINDOW;
        $from_name_setting = sanitize_text_field( $settings['from_name'] ?? '' );
        $from_name         = $this->clean_header_text( $this->resolve_from_name( $form_type, $from_name_setting ) );
        $from_email        = $this->resolve_from_email();
        $reply_to_name     = $this->clean_header_text( $data['name'] ?? '' );
        $reply_to_email    = sanitize_email( $data['email'] ?? '' );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            "From: {$from_name} <{$from_email}>",
        );

        if ( is_email( $reply_to_email ) ) {
            $headers[] = "Reply-To: {$reply_to_name} <{$reply_to_email}>";
        }

        return $headers;
    }

    private function resolve_from_email(): string {
        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        if ( is_string( $host ) && $host !== '' ) {
            $domain_email = sanitize_email( 'wordpress@' . preg_replace( '/^www\./', '', $host ) );
            if ( is_email( $domain_email ) ) {
                return $domain_email;
            }
        }

        $admin_email = sanitize_email( get_option( 'admin_email' ) );
        if ( is_email( $admin_email ) ) {
            return $admin_email;
        }

        return 'wordpress@localhost.localdomain';
    }

    private function clean_header_text( string $value ): string {
        return trim( preg_replace( '/[\r\n]+/', ' ', $value ) ?? '' );
    }

    /**
     * @param array<string,string> $data
     */
    private function build_html_body( array $data ): string {
        $form_type      = $data['formType'] ?? CFH_Form_Definitions::TYPE_WINDOW;
        $email_title    = esc_html( CFH_Form_Definitions::get_email_title( $form_type ) );
        $email_intro    = esc_html( CFH_Form_Definitions::get_email_intro( $form_type ) );
        $detail_rows    = array_map(
            static function ( array $row ): array {
                return array(
                    'label' => esc_html( $row['label'] ),
                    'value' => esc_html( $row['value'] ),
                );
            },
            CFH_Form_Definitions::get_email_detail_rows( $form_type, $data )
        );
        $name           = esc_html( $data['name'] );
        $email          = esc_html( $data['email'] );
        $phone          = esc_html( $data['phone'] ?: '-' );
        $tracking_rows  = $this->build_tracking_rows( $data );
        $submitted      = esc_html( wp_date( 'd.m.Y H:i', time() ) );
        $reply_subject  = rawurlencode( CFH_Form_Definitions::get_reply_subject( $form_type ) );

        ob_start();
        require CFH_PLUGIN_DIR . 'templates/email.php';
        return (string) ob_get_clean();
    }

    /**
     * @param array<string,string> $data
     * @return array<int,array{label:string,value:string}>
     */
    private function build_tracking_rows( array $data ): array {
        $labels = array(
            'landingPage'  => 'Landingpage',
            'referrer'     => 'Referrer',
            'utm_source'   => 'UTM Source',
            'utm_medium'   => 'UTM Medium',
            'utm_campaign' => 'UTM Campaign',
            'utm_term'     => 'UTM Term',
            'utm_content'  => 'UTM Content',
            'gclid'        => 'Google Click ID',
            'fbclid'       => 'Facebook Click ID',
        );
        $rows = array();

        foreach ( $labels as $key => $label ) {
            $value = trim( $data[ $key ] ?? '' );
            if ( $value === '' ) {
                continue;
            }

            $rows[] = array(
                'label' => esc_html( $label ),
                'value' => esc_html( $value ),
            );
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function build_subject( string $form_type, array $settings ): string {
        $base_subject = sanitize_text_field( $settings['email_subject'] ?: CFH_Form_Definitions::get_default_subject( $form_type ) );

        if ( $form_type === CFH_Form_Definitions::TYPE_ENERGY_FUNDING ) {
            return $base_subject === CFH_Form_Definitions::get_default_subject( CFH_Form_Definitions::TYPE_WINDOW )
                ? CFH_Form_Definitions::get_default_subject( $form_type )
                : $base_subject . ' | Energieberatung / BAFA / KfW';
        }

        return $base_subject;
    }

    private function resolve_from_name( string $form_type, string $from_name_setting ): string {
        if ( $from_name_setting === '' ) {
            return CFH_Form_Definitions::get_default_from_name( $form_type );
        }

        if (
            $form_type === CFH_Form_Definitions::TYPE_ENERGY_FUNDING &&
            $from_name_setting === CFH_Form_Definitions::get_default_from_name( CFH_Form_Definitions::TYPE_WINDOW )
        ) {
            return CFH_Form_Definitions::get_default_from_name( $form_type );
        }

        return $from_name_setting;
    }
}
