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
                'intro' => 'Bitte füllen Sie alle Felder sorgfältig aus. Pflichtfelder sind mit einem * markiert.',
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
                'intro' => 'Bitte füllen Sie alle Felder sorgfältig aus. So können wir Ihre Anfrage zu Energieberatung und Förderung schnell einordnen.',
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
                                    'erneuerbare'        => 'Photovoltaik / Erneuerbare',
                                    'beratung_allgemein' => 'Allgemeine Beratung',
                                ),
                            ),
                        ),
                    ),
                    array(
                        'title'  => 'Schritt 5 von 5: Ihre Kontaktdaten',
                        'fields' => self::get_contact_fields( true ),
                    ),
                ),
            ),
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function get_contact_fields( bool $include_location ): array {
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
            ),
            array(
                'type'         => 'email',
                'name'         => 'email',
                'label'        => 'E-Mail-Adresse *',
                'required'     => true,
                'placeholder'  => 'beispiel@domain.de',
                'autocomplete' => 'email',
            ),
            array(
                'type'         => 'tel',
                'name'         => 'phone',
                'label'        => 'Telefonnummer',
                'label_suffix' => '<span class="cfh-optional">(optional)</span>',
                'placeholder'  => '+49 123 4567890',
                'pattern'      => '\+?[\d\s\-]{6,20}',
                'autocomplete' => 'tel',
            ),
            array(
                'type'     => 'checkbox',
                'name'     => 'gdpr_consent',
                'label'    => sprintf(
                    'Ich habe die <a href="%s" target="_blank" rel="noopener">Datenschutzerklärung</a> gelesen und stimme der Verarbeitung meiner Daten zu. *',
                    esc_url( get_privacy_policy_url() )
                ),
                'required' => true,
            ),
            )
        );
    }
}
