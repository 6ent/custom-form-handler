<?php
/**
 * Formulardaten validieren, bereinigen und verarbeiten.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CFH_Form_Handler {

    /** Maximale Einreichungen pro IP pro Stunde */
    private const RATE_LIMIT      = 5;
    private const RATE_WINDOW     = HOUR_IN_SECONDS;
    private const RATE_KEY_PREFIX = 'cfh_rate_';

    public function register(): void {
        add_action( 'admin_post_nopriv_cfh_submit', array( $this, 'handle' ) );
        add_action( 'admin_post_cfh_submit', array( $this, 'handle' ) );
    }

    public function handle(): void {
        $settings = ( new CFH_Settings() )->get();

        if (
            ! isset( $_POST['cfh_nonce'] ) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfh_nonce'] ) ), 'cfh_submit' )
        ) {
            error_log( '[CFH] Nonce-Überprüfung fehlgeschlagen.' );
            $this->redirect_error( $settings, 'security' );
        }

        if ( $this->is_rate_limited() ) {
            error_log( '[CFH] Rate Limit überschritten für IP: ' . $this->get_client_ip() );
            $this->redirect_error( $settings, 'rate_limit' );
        }

        if ( ! empty( $_POST['cfh_hp_name'] ) ) {
            $this->redirect_success( $settings );
        }

        if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
            $this->redirect_error( $settings, 'method' );
        }

        $data = $this->sanitize_input();

        if ( is_wp_error( $data ) ) {
            error_log( '[CFH] Validierungsfehler: ' . $data->get_error_message() );
            $this->redirect_error( $settings, $data->get_error_code(), $this->get_form_type() );
        }

        $this->increment_rate_counter();

        $mail_sent = ( new CFH_Mailer() )->send( $data, $settings );

        ( new CFH_Webhook() )->trigger( $data, $settings );

        if ( $mail_sent ) {
            $this->redirect_success( $settings );
        }

        $this->redirect_error( $settings, 'mail_failed', $data['formType'] );
    }

    private function get_client_ip(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
    }

    private function rate_transient_key(): string {
        return self::RATE_KEY_PREFIX . md5( $this->get_client_ip() );
    }

    private function is_rate_limited(): bool {
        $count = (int) get_transient( $this->rate_transient_key() );
        return $count >= self::RATE_LIMIT;
    }

    private function increment_rate_counter(): void {
        $key   = $this->rate_transient_key();
        $count = (int) get_transient( $key );
        set_transient( $key, $count + 1, self::RATE_WINDOW );
    }

    /**
     * @return array<string,string>|WP_Error
     */
    private function sanitize_input(): array|WP_Error {
        $form_type = $this->get_form_type();

        if ( ! CFH_Form_Definitions::is_supported_form_type( $form_type ) ) {
            return new WP_Error( 'invalid_form_type', 'Unbekannter Formulartyp.' );
        }

        $common = $this->sanitize_common_fields();
        if ( is_wp_error( $common ) ) {
            return $common;
        }

        if ( $form_type === CFH_Form_Definitions::TYPE_ENERGY_FUNDING ) {
            return $this->sanitize_energy_form( $common );
        }

        return $this->sanitize_window_form( $common );
    }

    /**
     * @return array<string,string>|WP_Error
     */
    private function sanitize_window_form( array $common ): array|WP_Error {
        $allowed_materials = array( 'kunststoff', 'aluminium', 'holz', 'beratung' );
        $allowed_types     = array( 'house', 'apartment', 'commercial' );
        $allowed_counts    = array( '0-10', '10-20', '20+' );

        $window_material = sanitize_text_field( wp_unslash( $_POST['windowMaterial'] ?? '' ) );
        $property_type   = sanitize_text_field( wp_unslash( $_POST['propertyType'] ?? '' ) );
        $window_count    = sanitize_text_field( wp_unslash( $_POST['windowCount'] ?? '' ) );

        if ( ! in_array( $window_material, $allowed_materials, true ) ) {
            return new WP_Error( 'invalid_material', 'Ungültiges Fenstermaterial.' );
        }

        if ( ! in_array( $property_type, $allowed_types, true ) ) {
            return new WP_Error( 'invalid_property', 'Ungültiger Immobilientyp.' );
        }

        if ( ! in_array( $window_count, $allowed_counts, true ) ) {
            return new WP_Error( 'invalid_count', 'Ungültige Fensteranzahl.' );
        }

        return array_merge(
            $common,
            array(
                'windowMaterial' => $window_material,
                'propertyType'   => $property_type,
                'windowCount'    => $window_count,
            )
        );
    }

    /**
     * @return array<string,string>|WP_Error
     */
    private function sanitize_energy_form( array $common ): array|WP_Error {
        $allowed_inquiry_types = array( 'energieberatung', 'bafa', 'kfw', 'kombiniert' );
        $allowed_building_types = array( 'einfamilienhaus', 'mehrfamilienhaus', 'wohnung', 'gewerbe' );
        $allowed_ownership_statuses = array( 'eigentuemer', 'kaeufer', 'verwaltung', 'mieter_sonstiges' );
        $allowed_project_types = array( 'sanierung', 'heizung', 'daemmung', 'fenster', 'erneuerbare', 'beratung_allgemein' );

        $inquiry_type     = sanitize_text_field( wp_unslash( $_POST['inquiryType'] ?? '' ) );
        $building_type    = sanitize_text_field( wp_unslash( $_POST['buildingType'] ?? '' ) );
        $ownership_status = sanitize_text_field( wp_unslash( $_POST['ownershipStatus'] ?? '' ) );
        $project_type     = sanitize_text_field( wp_unslash( $_POST['projectType'] ?? '' ) );

        if ( ! in_array( $inquiry_type, $allowed_inquiry_types, true ) ) {
            return new WP_Error( 'invalid_inquiry_type', 'Ungültige Anfrageart.' );
        }

        if ( ! in_array( $building_type, $allowed_building_types, true ) ) {
            return new WP_Error( 'invalid_building_type', 'Ungültiger Gebäudetyp.' );
        }

        if ( ! in_array( $ownership_status, $allowed_ownership_statuses, true ) ) {
            return new WP_Error( 'invalid_ownership_status', 'Ungültiger Eigentumsstatus.' );
        }

        if ( ! in_array( $project_type, $allowed_project_types, true ) ) {
            return new WP_Error( 'invalid_project_type', 'Ungültige Maßnahme.' );
        }

        return array_merge(
            $common,
            array(
                'inquiryType'     => $inquiry_type,
                'buildingType'    => $building_type,
                'ownershipStatus' => $ownership_status,
                'projectType'     => $project_type,
            )
        );
    }

    /**
     * @return array<string,string>|WP_Error
     */
    private function sanitize_common_fields(): array|WP_Error {
        $location               = sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) );
        $name                   = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
        $email                  = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $phone                  = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
        $contact_preference     = sanitize_key( wp_unslash( $_POST['contactPreference'] ?? '' ) );
        $preferred_contact_time = substr( sanitize_text_field( wp_unslash( $_POST['preferredContactTime'] ?? '' ) ), 0, 80 );
        $gdpr                   = isset( $_POST['gdpr_consent'] ) ? '1' : '0';
        $allowed_contact_preferences = array( '', 'phone', 'email', 'any' );

        if ( ! preg_match( '/^\d{5}$/', $location ) ) {
            return new WP_Error( 'invalid_location', 'Ungültige PLZ.' );
        }

        if ( empty( $name ) ) {
            return new WP_Error( 'invalid_name', 'Name fehlt.' );
        }

        if ( ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', 'Ungültige E-Mail-Adresse.' );
        }

        if ( ! empty( $phone ) && ! preg_match( '/^\+?[\d\s\-]{6,20}$/', $phone ) ) {
            return new WP_Error( 'invalid_phone', 'Ungültige Telefonnummer.' );
        }

        if ( ! in_array( $contact_preference, $allowed_contact_preferences, true ) ) {
            return new WP_Error( 'invalid_contact_preference', 'Ungültige Kontaktpräferenz.' );
        }

        if ( $gdpr !== '1' ) {
            return new WP_Error( 'gdpr_missing', 'Datenschutzzustimmung fehlt.' );
        }

        return array_merge(
            array(
                'formType'             => $this->get_form_type(),
                'location'             => $location,
                'name'                 => $name,
                'email'                => $email,
                'phone'                => $phone,
                'contactPreference'    => $contact_preference,
                'preferredContactTime' => $preferred_contact_time,
                'gdpr'                 => $gdpr,
            ),
            $this->sanitize_tracking_fields()
        );
    }

    /**
     * @return array<string,string>
     */
    private function sanitize_tracking_fields(): array {
        $url_fields  = array( 'landingPage', 'referrer' );
        $text_fields = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid' );
        $tracking    = array();

        foreach ( $url_fields as $field ) {
            $tracking[ $field ] = esc_url_raw( wp_unslash( $_POST[ $field ] ?? '' ) );
        }

        foreach ( $text_fields as $field ) {
            $tracking[ $field ] = substr( sanitize_text_field( wp_unslash( $_POST[ $field ] ?? '' ) ), 0, 255 );
        }

        return $tracking;
    }

    private function get_form_type(): string {
        return sanitize_key( wp_unslash( $_POST['cfh_form_type'] ?? CFH_Form_Definitions::TYPE_WINDOW ) );
    }

    private function redirect_success( array $settings ): never {
        $path = $settings['success_url'] ?: '/danke/';
        wp_safe_redirect( home_url( $path ) );
        exit;
    }

    private function redirect_error( array $settings, string $code = 'unknown', string $form_type = '' ): never {
        $form_url = isset( $_POST['cfh_form_url'] )
            ? esc_url_raw( wp_unslash( $_POST['cfh_form_url'] ) )
            : '';

        if ( $form_url !== '' && str_starts_with( $form_url, home_url() ) ) {
            $url = add_query_arg(
                array(
                    'cfh_error'     => rawurlencode( $code ),
                    'cfh_form_type' => rawurlencode( $form_type ?: $this->get_form_type() ),
                ),
                $form_url
            );
            wp_safe_redirect( $url );
            exit;
        }

        $path     = $settings['error_url'] ?: '/error/';
        $base_url = home_url( $path );
        $url      = add_query_arg(
            array(
                'cfh_error'     => rawurlencode( $code ),
                'cfh_form_type' => rawurlencode( $form_type ?: $this->get_form_type() ),
            ),
            $base_url
        );
        wp_safe_redirect( $url );
        exit;
    }
}
