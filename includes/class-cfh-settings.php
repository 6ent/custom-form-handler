<?php
/**
 * Settings registration and admin menu for Custom Form Handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CFH_Settings {

    /** @var array<string,mixed>|null In-memory cache nach erstem get_option()-Aufruf */
    private static ?array $cache = null;

    /** @var array<string,mixed> */
    private array $defaults = array(
        'recipient_email'  => '',
        'email_subject'    => 'Neuer Fenster-Lead',
        'success_url'      => '/danke/',
        'error_url'        => '/error/',
        'n8n_webhook_url'  => '',
        'n8n_webhook_secret' => '',
        'from_name'        => 'Fenster-Lead',
        'github_repository' => '',
    );

    public function register(): void {
        add_action( 'admin_menu',    array( $this, 'add_menu' ) );
        add_action( 'admin_init',    array( $this, 'register_settings' ) );
    }

    public function add_menu(): void {
        add_options_page(
            'Custom Form Handler',
            'Form Handler',
            'manage_options',
            'cfh-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings(): void {
        register_setting(
            CFH_OPTION_GROUP,
            CFH_OPTION_KEY,
            array(
                'sanitize_callback' => array( $this, 'sanitize' ),
                'default'           => $this->defaults,
            )
        );

        add_settings_section( 'cfh_main', 'Allgemeine Einstellungen', '__return_false', 'cfh-settings' );

        $fields = array(
            array( 'recipient_email',   'Empfänger E-Mail *',     'email' ),
            array( 'email_subject',     'E-Mail Betreff',          'text' ),
            array( 'from_name',         'Absendername',            'text' ),
            array( 'success_url',       'Danke-Seite (Pfad)',       'text' ),
            array( 'error_url',         'Fehler-Seite (Pfad)',      'text' ),
            array( 'n8n_webhook_url',   'n8n Webhook URL',          'url' ),
            array( 'n8n_webhook_secret','n8n Webhook Secret',       'text' ),
            array( 'github_repository', 'GitHub Repository',        'text' ),
        );

        foreach ( $fields as $field ) {
            [ $id, $label, $type ] = $field;
            add_settings_field(
                'cfh_' . $id,
                $label,
                array( $this, 'render_field' ),
                'cfh-settings',
                'cfh_main',
                array( 'id' => $id, 'type' => $type )
            );
        }
    }

    public function render_field( array $args ): void {
        $options = $this->get();
        $id      = esc_attr( $args['id'] );
        $type    = esc_attr( $args['type'] );
        $value   = esc_attr( $options[ $id ] ?? '' );
        echo "<input type=\"{$type}\" id=\"cfh_{$id}\" name=\"" . CFH_OPTION_KEY . "[{$id}]\" value=\"{$value}\" class=\"regular-text\" />";
    }

    /** @return array<string,mixed> */
    public function get(): array {
        if ( self::$cache !== null ) {
            return self::$cache;
        }
        $saved        = get_option( CFH_OPTION_KEY, array() );
        self::$cache  = wp_parse_args( $saved, $this->defaults );
        return self::$cache;
    }

    /**
     * Cache leeren – wird nach dem Speichern der Settings aufgerufen,
     * damit der nächste get()-Aufruf frische Daten aus der DB liest.
     */
    public static function flush_cache(): void {
        self::$cache = null;
    }

    /** @param mixed $input */
    public function sanitize( $input ): array {
        if ( ! is_array( $input ) ) {
            return $this->defaults;
        }
        // Cache nach dem Speichern leeren, damit get() frische Werte liest
        self::flush_cache();
        return array(
            'recipient_email'    => sanitize_email( $input['recipient_email'] ?? '' ),
            'email_subject'      => sanitize_text_field( $input['email_subject'] ?? '' ),
            'from_name'          => sanitize_text_field( $input['from_name'] ?? '' ),
            'success_url'        => sanitize_text_field( $input['success_url'] ?? '' ),
            'error_url'          => sanitize_text_field( $input['error_url'] ?? '' ),
            'n8n_webhook_url'    => esc_url_raw( $input['n8n_webhook_url'] ?? '' ),
            'n8n_webhook_secret' => sanitize_text_field( $input['n8n_webhook_secret'] ?? '' ),
            'github_repository'  => sanitize_text_field( $input['github_repository'] ?? '' ),
        );
    }

    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once CFH_PLUGIN_DIR . 'templates/settings-page.php';
    }
}
