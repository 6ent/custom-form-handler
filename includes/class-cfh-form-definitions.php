<?php
/**
 * Gemeinsame Formular-Definitionen für Rendering, Validierung und Versand.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CFH_Form_Definitions {

    public const TYPE_WINDOW         = 'window';
    public const TYPE_ENERGY_FUNDING = 'energy_funding';

    /**
     * @return array<string,string>
     */
    public static function get_shortcode_map(): array {
        return array(
            'fenster_lead_form'     => self::TYPE_WINDOW,
            'custom_form_shortcode' => self::TYPE_WINDOW,
            'energiefoerderung_form' => self::TYPE_ENERGY_FUNDING,
        );
    }

    public static function is_supported_form_type( string $form_type ): bool {
        return in_array( $form_type, array_values( self::get_shortcode_map() ), true );
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_form( string $form_type ): array {
        $forms = self::get_forms();
        return $forms[ $form_type ] ?? $forms[ self::TYPE_WINDOW ];
    }

    /**
     * @return array<string,string>
     */
    public static function get_error_messages( string $form_type ): array {
        $common = array(
            'invalid_location' => 'Bitte geben Sie eine gültige 5-stellige PLZ ein.',
            'invalid_name'     => 'Bitte geben Sie Ihren vollständigen Namen an.',
            'invalid_email'    => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
            'invalid_phone'    => 'Die angegebene Telefonnummer ist ungültig.',
            'gdpr_missing'     => 'Bitte stimmen Sie der Datenschutzerklärung zu.',
            'invalid_contact_preference' => 'Bitte wählen Sie eine gültige Kontaktart aus.',
        );

        if ( $form_type === self::TYPE_ENERGY_FUNDING ) {
            return array_merge(
                $common,
                array(
                    'invalid_inquiry_type'     => 'Bitte wählen Sie aus, wofür Sie eine Anfrage stellen.',
                    'invalid_building_type'    => 'Bitte wählen Sie den Gebäudetyp aus.',
                    'invalid_ownership_status' => 'Bitte wählen Sie Ihren Eigentumsstatus aus.',
                    'invalid_project_type'     => 'Bitte wählen Sie die passende Maßnahme aus.',
                )
            );
        }

        return array_merge(
            $common,
            array(
                'invalid_material' => 'Bitte wählen Sie ein Fenstermaterial aus.',
                'invalid_property' => 'Bitte wählen Sie einen Immobilientyp aus.',
                'invalid_count'    => 'Bitte wählen Sie eine Fensteranzahl aus.',
            )
        );
    }

    /**
     * @return string[]
     */
    public static function get_inline_error_codes(): array {
        return array(
            'invalid_material',
            'invalid_property',
            'invalid_count',
            'invalid_location',
            'invalid_name',
            'invalid_email',
            'invalid_phone',
            'gdpr_missing',
            'invalid_contact_preference',
            'invalid_inquiry_type',
            'invalid_building_type',
            'invalid_ownership_status',
            'invalid_project_type',
        );
    }

    /**
     * @return array<string,string>
     */
    public static function get_popup_error_messages(): array {
        return array(
            'mail_failed'       => 'Ihre Anfrage konnte gerade nicht gesendet werden. Bitte versuchen Sie es in wenigen Minuten erneut.',
            'rate_limit'        => 'Sie haben gerade mehrere Anfragen gesendet. Bitte warten Sie kurz und versuchen Sie es dann erneut.',
            'security'          => 'Die Anfrage konnte nicht verarbeitet werden. Bitte laden Sie die Seite neu und versuchen Sie es erneut.',
            'method'            => 'Die Anfrage konnte nicht verarbeitet werden. Bitte versuchen Sie es erneut.',
            'invalid_form_type' => 'Die Anfrage konnte nicht verarbeitet werden. Bitte laden Sie die Seite neu und versuchen Sie es erneut.',
            'unknown'           => 'Es ist ein unerwarteter Fehler aufgetreten. Bitte versuchen Sie es später erneut.',
        );
    }

    public static function is_inline_error_code( string $code ): bool {
        return in_array( $code, self::get_inline_error_codes(), true );
    }

    public static function is_popup_error_code( string $code ): bool {
        return array_key_exists( $code, self::get_popup_error_messages() );
    }

    public static function get_default_subject( string $form_type ): string {
        if ( $form_type === self::TYPE_ENERGY_FUNDING ) {
            return 'Neue Energieberatungs-/Förderanfrage';
        }

        return 'Neuer Fenster-Lead';
    }

    public static function get_default_from_name( string $form_type ): string {
        if ( $form_type === self::TYPE_ENERGY_FUNDING ) {
            return 'Energieberatungs-Lead';
        }

        return 'Fenster-Lead';
    }

    public static function get_email_title( string $form_type ): string {
        if ( $form_type === self::TYPE_ENERGY_FUNDING ) {
            return 'Neue Energieberatungs-/Förderanfrage';
        }

        return 'Neuer Fenster-Lead';
    }

    public static function get_email_intro( string $form_type ): string {
        if ( $form_type === self::TYPE_ENERGY_FUNDING ) {
            return 'Es wurde eine neue Anfrage zu Energieberatung, BAFA oder KfW über Ihre Website eingereicht.';
        }

        return 'Es wurde eine neue Anfrage über das Formular auf Ihrer Website eingereicht.';
    }

    public static function get_reply_subject( string $form_type ): string {
        if ( $form_type === self::TYPE_ENERGY_FUNDING ) {
            return 'Re: Ihre Energieberatungs-/Förderanfrage';
        }

        return 'Re: Ihre Fensteranfrage';
    }

    /**
     * @param array<string,string> $data
     * @return array<int,array{label:string,value:string}>
     */
    public static function get_email_detail_rows( string $form_type, array $data ): array {
        if ( $form_type === self::TYPE_ENERGY_FUNDING ) {
            return array(
                array(
                    'label' => 'Anfrageart',
                    'value' => self::get_display_value( $form_type, 'inquiryType', $data['inquiryType'] ?? '' ),
                ),
                array(
                    'label' => 'Gebäudetyp',
                    'value' => self::get_display_value( $form_type, 'buildingType', $data['buildingType'] ?? '' ),
                ),
                array(
                    'label' => 'Eigentumsstatus',
                    'value' => self::get_display_value( $form_type, 'ownershipStatus', $data['ownershipStatus'] ?? '' ),
                ),
                array(
                    'label' => 'Projekt / Maßnahme',
                    'value' => self::get_display_value( $form_type, 'projectType', $data['projectType'] ?? '' ),
                ),
                array(
                    'label' => 'Projektstandort (PLZ)',
                    'value' => $data['location'] ?? '',
                ),
            );
        }

        return array(
            array(
                'label' => 'Fenstermaterial',
                'value' => self::get_display_value( $form_type, 'windowMaterial', $data['windowMaterial'] ?? '' ),
            ),
            array(
                'label' => 'Immobilientyp',
                'value' => self::get_display_value( $form_type, 'propertyType', $data['propertyType'] ?? '' ),
            ),
            array(
                'label' => 'Anzahl Fenster',
                'value' => self::get_display_value( $form_type, 'windowCount', $data['windowCount'] ?? '' ),
            ),
            array(
                'label' => 'Projektstandort (PLZ)',
                'value' => $data['location'] ?? '',
            ),
        );
    }

    public static function get_display_value( string $form_type, string $field_name, string $value ): string {
        $fields = self::get_field_map( $form_type );

        if ( ! isset( $fields[ $field_name ]['options'][ $value ] ) ) {
            return $value;
        }

        return $fields[ $field_name ]['options'][ $value ];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function get_field_map( string $form_type ): array {
        $fields = array();
        foreach ( self::get_form( $form_type )['steps'] as $step ) {
            foreach ( $step['fields'] as $field ) {
                $fields[ $field['name'] ] = $field;
            }
        }

        return $fields;
    }

    /**
     * @return array<string,mixed>
     */
    private static function get_forms(): array {
        return array(
            self::TYPE_WINDOW => array(
                'intro' => 'Beantworten Sie kurz ein paar Fragen. Wir melden uns mit einer kostenlosen Ersteinschätzung zu Ihrem Vorhaben.',
                'steps' => array(
                    array(
                        'title'  => 'Schritt 1 von 5: Welche Art von Fenster möchten Sie?',
                        'fields' => array(
                            array(
                                'type'        => 'radio_group',
                                'name'        => 'windowMaterial',
                                'group_label' => 'Fenstermaterial wählen',
                                'required'    => true,
                                'help'        => 'Wählen Sie das bevorzugte Material oder bitten Sie um Beratung.',
                                'options'     => array(
                                    'kunststoff' => 'Kunststoff',
                                    'aluminium'  => 'Aluminium',
                                    'holz'       => 'Holz',
                                    'beratung'   => 'Weiß nicht / Beratung erwünscht',
                                ),
                            ),
                        ),
                    ),
                    array(
                        'title'  => 'Schritt 2 von 5: Art der Immobilie',
                        'fields' => array(
                            array(
                                'type'        => 'radio_group',
                                'name'        => 'propertyType',
                                'group_label' => 'Immobilientyp wählen',
                                'required'    => true,
                                'help'        => 'Bitte wählen Sie den Immobilientyp.',
                                'options'     => array(
                                    'house'      => 'Haus',
                                    'apartment'  => 'Wohnung',
                                    'commercial' => 'Gewerbe',
                                ),
                            ),
                        ),
                    ),
                    array(
                        'title'  => 'Schritt 3 von 5: Anzahl der Fenster',
                        'fields' => array(
                            array(
                                'type'        => 'radio_group',
                                'name'        => 'windowCount',
                                'group_label' => 'Fensteranzahl wählen',
                                'required'    => true,
                                'help'        => 'Bitte wählen Sie die Anzahl benötigter Fenster.',
                                'options'     => array(
                                    '0-10'  => '0-10',
                                    '10-20' => '10-20',
                                    '20+'   => '20+',
                                ),
                            ),
                        ),
                    ),
                    array(
                        'title'  => 'Schritt 4 von 5: Standort des Projekts',
                        'fields' => array(
                            array(
                                'type'         => 'text',
                                'name'         => 'location',
                                'label'        => 'Postleitzahl *',
                                'required'     => true,
                                'placeholder'  => 'z. B. 10115',
                                'pattern'      => '^[0-9]{5}$',
                                'inputmode'    => 'numeric',
                                'maxlength'    => '5',
                                'autocomplete' => 'postal-code',
                                'help'         => 'Bitte geben Sie eine 5-stellige deutsche PLZ an.',
                            ),
                        ),
                    ),
                    array(
                        'title'  => 'Schritt 5 von 5: Ihre Kontaktdaten',
                        'fields' => self::get_contact_fields( false ),
                    ),
                ),
            ),
            self::TYPE_ENERGY_FUNDING => array(
                'intro' => 'Beantworten Sie kurz ein paar Fragen. Wir prüfen, welche Beratung oder Förderung zu Ihrem Vorhaben passt.',
                'steps' => array(
                    array(
                        'title'  => 'Schritt 1 von 5: Wofür interessieren Sie sich?',
                        'fields' => array(
                            array(
                                'type'        => 'radio_group',
                                'name'        => 'inquiryType',
                                'group_label' => 'Anfrageart wählen',
                                'required'    => true,
                                'help'        => 'Wählen Sie aus, ob Sie Energieberatung, BAFA, KfW oder eine kombinierte Anfrage stellen möchten.',
                                'options'     => array(
                                    'energieberatung' => 'Energieberatung',
                                    'bafa'            => 'Förderanfrage BAFA',
                                    'kfw'             => 'Förderanfrage KfW',
                                    'kombiniert'      => 'Beratung plus Förderung',
                                ),
                                'icons'       => array(
                                    'energieberatung' => 'clipboard-check',
                                    'bafa'            => 'badge-euro',
                                    'kfw'             => 'landmark',
                                    'kombiniert'      => 'sparkles',
                                ),
                            ),
                        ),
                    ),
                    array(
                        'title'  => 'Schritt 2 von 5: Um welchen Gebäudetyp geht es?',
                        'fields' => array(
                            array(
                                'type'        => 'radio_group',
                                'name'        => 'buildingType',
                                'group_label' => 'Gebäudetyp wählen',
                                'required'    => true,
                                'help'        => 'Bitte wählen Sie den passenden Gebäudetyp aus.',
                                'options'     => array(
                                    'einfamilienhaus' => 'Einfamilienhaus',
                                    'mehrfamilienhaus' => 'Mehrfamilienhaus',
                                    'wohnung'         => 'Wohnung',
                                    'gewerbe'         => 'Gewerbe',
                                ),
                                'icons'       => array(
                                    'einfamilienhaus' => 'home',
                                    'mehrfamilienhaus' => 'building',
                                    'wohnung'         => 'door-open',
                                    'gewerbe'         => 'briefcase',
                                ),
                            ),
                        ),
                    ),
                    array(
                        'title'  => 'Schritt 3 von 5: In welcher Rolle stellen Sie die Anfrage?',
                        'fields' => array(
                            array(
                                'type'        => 'radio_group',
                                'name'        => 'ownershipStatus',
                                'group_label' => 'Eigentumsstatus wählen',
                                'required'    => true,
                                'help'        => 'Damit wir die Förderfähigkeit besser einordnen können, brauchen wir Ihren Bezug zur Immobilie.',
                                'options'     => array(
                                    'eigentuemer'       => 'Eigentümer/in',
                                    'kaeufer'           => 'Käufer/in',
                                    'verwaltung'        => 'Verwaltung / Unternehmen',
                                    'mieter_sonstiges'  => 'Mieter/in oder Sonstiges',
                                ),
                                'icons'       => array(
                                    'eigentuemer'      => 'key',
                                    'kaeufer'          => 'handshake',
                                    'verwaltung'       => 'briefcase',
                                    'mieter_sonstiges' => 'user-round',
                                ),
                            ),
                        ),
                    ),
                    array(
                        'title'  => 'Schritt 4 von 5: Welche Maßnahme planen Sie?',
                        'fields' => array(
                            array(
                                'type'        => 'radio_group',
                                'name'        => 'projectType',
                                'group_label' => 'Projektart wählen',
                                'required'    => true,
                                'help'        => 'Wählen Sie die Maßnahme, für die Sie Beratung oder Förderung benötigen.',
                                'options'     => array(
                                    'sanierung'          => 'Sanierung gesamt',
                                    'heizung'            => 'Heizung / Wärmepumpe',
                                    'daemmung'           => 'Dämmung',
                                    'fenster'            => 'Fenster / Türen',
                                    'erneuerbare'        => 'Photovoltaik',
                                    'beratung_allgemein' => 'Allgemeine Beratung',
                                ),
                                'icons'       => array(
                                    'sanierung'          => 'hammer',
                                    'heizung'            => 'flame',
                                    'daemmung'           => 'layers',
                                    'fenster'            => 'panel-top',
                                    'erneuerbare'        => 'sun',
                                    'beratung_allgemein' => 'message-circle',
                                ),
                            ),
                        ),
                    ),
                    array(
                        'title'  => 'Schritt 5 von 5: Ihre Kontaktdaten',
                        'fields' => self::get_contact_fields( true, true ),
                    ),
                ),
            ),
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function get_contact_fields( bool $include_location, bool $include_icons = false ): array {
        $fields = array();

        if ( $include_location ) {
            $fields[] = array(
                'type'         => 'text',
                'name'         => 'location',
                'label'        => 'Postleitzahl *',
                'required'     => true,
                'placeholder'  => 'z. B. 10115',
                'pattern'      => '^[0-9]{5}$',
                'inputmode'    => 'numeric',
                'maxlength'    => '5',
                'autocomplete' => 'postal-code',
                'icon'         => $include_icons ? 'map-pin' : '',
            );
        }

        return array_merge(
            $fields,
            array(
            array(
                'type'         => 'text',
                'name'         => 'name',
                'label'        => 'Vorname und Nachname *',
                'required'     => true,
                'placeholder'  => 'Max Mustermann',
                'autocomplete' => 'name',
                'icon'         => $include_icons ? 'user-round' : '',
            ),
            array(
                'type'         => 'email',
                'name'         => 'email',
                'label'        => 'E-Mail-Adresse *',
                'required'     => true,
                'placeholder'  => 'beispiel@domain.de',
                'autocomplete' => 'email',
                'icon'         => $include_icons ? 'mail' : '',
            ),
            array(
                'type'         => 'tel',
                'name'         => 'phone',
                'label'        => 'Telefonnummer',
                'label_suffix' => '<span class="cfh-optional">(optional)</span>',
                'placeholder'  => '+49 123 4567890',
                'pattern'      => '\+?[\d\s\-]{6,20}',
                'autocomplete' => 'tel',
                'icon'         => $include_icons ? 'phone' : '',
            ),
            array(
                'type'        => 'radio_group',
                'name'        => 'contactPreference',
                'group_label' => 'Wie möchten Sie kontaktiert werden?',
                'required'    => false,
                'help'        => 'Optional - wählen Sie, was für Sie am bequemsten ist.',
                'options'     => array(
                    'phone' => 'Telefon',
                    'email' => 'E-Mail',
                    'any'   => 'Egal',
                ),
                'icons'       => $include_icons ? array(
                    'phone' => 'phone',
                    'email' => 'mail',
                    'any'   => 'message-circle',
                ) : array(),
            ),
            array(
                'type'         => 'text',
                'name'         => 'preferredContactTime',
                'label'        => 'Wann passen wir Sie am besten?',
                'label_suffix' => '<span class="cfh-optional">(optional)</span>',
                'placeholder'  => 'z. B. vormittags oder ab 17 Uhr',
                'maxlength'    => '80',
                'autocomplete' => 'off',
                'icon'         => $include_icons ? 'clock' : '',
            ),
            array(
                'type'     => 'checkbox',
                'name'     => 'gdpr_consent',
                'label'    => sprintf(
                    'Ich habe die <a href="%s" target="_blank" rel="noopener">Datenschutzerklärung</a> gelesen und stimme der Verarbeitung meiner Daten zu. *',
                    esc_url( get_privacy_policy_url() )
                ),
                'required' => true,
                'icon'     => $include_icons ? 'shield-check' : '',
            ),
            )
        );
    }
}
