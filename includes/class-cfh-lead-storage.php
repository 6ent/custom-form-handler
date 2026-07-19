<?php
/**
 * Lokale Speicherung eingegangener Leads.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CFH_Lead_Storage {

    public const POST_TYPE = 'cfh_lead';

    public function register(): void {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_boxes' ) );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
    }

    public function register_post_type(): void {
        register_post_type(
            self::POST_TYPE,
            array(
                'labels' => array(
                    'name'               => 'Gespeicherte Leads',
                    'singular_name'      => 'Gespeicherter Lead',
                    'menu_name'          => 'Gespeicherte Leads',
                    'add_new_item'       => 'Lead hinzufügen',
                    'edit_item'          => 'Lead ansehen',
                    'new_item'           => 'Neuer Lead',
                    'view_item'          => 'Lead ansehen',
                    'search_items'       => 'Leads suchen',
                    'not_found'          => 'Keine Leads gefunden',
                    'not_found_in_trash' => 'Keine Leads im Papierkorb gefunden',
                ),
                'public'              => false,
                'show_ui'             => true,
                'show_in_menu'        => 'options-general.php',
                'exclude_from_search' => true,
                'supports'            => array( 'title' ),
                'capabilities'        => array(
                    'edit_post'          => 'manage_options',
                    'read_post'          => 'manage_options',
                    'delete_post'        => 'manage_options',
                    'edit_posts'         => 'manage_options',
                    'edit_others_posts'  => 'manage_options',
                    'delete_posts'       => 'manage_options',
                    'publish_posts'      => 'manage_options',
                    'read_private_posts' => 'manage_options',
                    'create_posts'       => 'do_not_allow',
                ),
                'map_meta_cap'        => false,
            )
        );
    }

    /**
     * @param array<string,string> $data
     * @return int|WP_Error
     */
    public function store( array $data ): int|WP_Error {
        $form_type = $data['formType'] ?? CFH_Form_Definitions::TYPE_WINDOW;
        $name      = trim( $data['name'] ?? '' );
        $email     = trim( $data['email'] ?? '' );
        $title     = sprintf(
            '%1$s - %2$s - %3$s',
            CFH_Form_Definitions::get_email_title( $form_type ),
            $name !== '' ? $name : $email,
            wp_date( 'd.m.Y H:i' )
        );

        $lead_id = wp_insert_post(
            array(
                'post_type'   => self::POST_TYPE,
                'post_status' => 'private',
                'post_title'  => sanitize_text_field( $title ),
            ),
            true
        );

        if ( is_wp_error( $lead_id ) ) {
            return $lead_id;
        }

        update_post_meta( $lead_id, '_cfh_lead_data', $data );
        update_post_meta( $lead_id, '_cfh_form_type', sanitize_key( $form_type ) );
        update_post_meta( $lead_id, '_cfh_mail_status', 'pending' );
        update_post_meta( $lead_id, '_cfh_webhook_status', 'pending' );
        update_post_meta( $lead_id, '_cfh_created_at', gmdate( 'c' ) );

        return $lead_id;
    }

    public function mark_mail_status( int $lead_id, string $status ): void {
        if ( $lead_id <= 0 ) {
            return;
        }

        update_post_meta( $lead_id, '_cfh_mail_status', sanitize_key( $status ) );
    }

    public function mark_webhook_status( int $lead_id, string $status ): void {
        if ( $lead_id <= 0 ) {
            return;
        }

        update_post_meta( $lead_id, '_cfh_webhook_status', sanitize_key( $status ) );
    }

    public function add_meta_boxes(): void {
        add_meta_box(
            'cfh_lead_details',
            'Lead-Daten',
            array( $this, 'render_details_meta_box' ),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_details_meta_box( WP_Post $post ): void {
        $data           = get_post_meta( $post->ID, '_cfh_lead_data', true );
        $data           = is_array( $data ) ? $data : array();
        $form_type      = sanitize_key( $data['formType'] ?? CFH_Form_Definitions::TYPE_WINDOW );
        $mail_status    = (string) get_post_meta( $post->ID, '_cfh_mail_status', true );
        $webhook_status = (string) get_post_meta( $post->ID, '_cfh_webhook_status', true );
        $detail_rows    = CFH_Form_Definitions::get_email_detail_rows( $form_type, $data );
        ?>
        <table class="widefat striped">
            <tbody>
                <tr><th scope="row">Status E-Mail</th><td><?php echo esc_html( $this->status_label( $mail_status ) ); ?></td></tr>
                <tr><th scope="row">Status Webhook</th><td><?php echo esc_html( $this->status_label( $webhook_status ) ); ?></td></tr>
                <tr><th scope="row">Name</th><td><?php echo esc_html( $data['name'] ?? '' ); ?></td></tr>
                <tr><th scope="row">E-Mail</th><td><?php echo esc_html( $data['email'] ?? '' ); ?></td></tr>
                <tr><th scope="row">Telefon</th><td><?php echo esc_html( $data['phone'] ?? '-' ); ?></td></tr>
                <?php foreach ( $detail_rows as $row ) : ?>
                    <tr><th scope="row"><?php echo esc_html( $row['label'] ); ?></th><td><?php echo esc_html( $row['value'] ); ?></td></tr>
                <?php endforeach; ?>
                <tr><th scope="row">Landingpage</th><td><?php echo esc_html( $data['landingPage'] ?? '' ); ?></td></tr>
                <tr><th scope="row">Referrer</th><td><?php echo esc_html( $data['referrer'] ?? '' ); ?></td></tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * @param array<string,string> $columns
     * @return array<string,string>
     */
    public function columns( array $columns ): array {
        return array(
            'cb'                 => $columns['cb'] ?? '',
            'title'              => 'Lead',
            'cfh_contact'        => 'Kontakt',
            'cfh_form_type'      => 'Formular',
            'cfh_mail_status'    => 'E-Mail',
            'cfh_webhook_status' => 'Webhook',
            'date'               => 'Datum',
        );
    }

    public function render_column( string $column, int $post_id ): void {
        $data = get_post_meta( $post_id, '_cfh_lead_data', true );
        $data = is_array( $data ) ? $data : array();

        if ( $column === 'cfh_contact' ) {
            echo esc_html( $data['name'] ?? '' );
            if ( ! empty( $data['email'] ) ) {
                echo '<br><a href="mailto:' . esc_attr( $data['email'] ) . '">' . esc_html( $data['email'] ) . '</a>';
            }
            if ( ! empty( $data['phone'] ) ) {
                echo '<br>' . esc_html( $data['phone'] );
            }
            return;
        }

        if ( $column === 'cfh_form_type' ) {
            echo esc_html( $this->form_type_label( (string) get_post_meta( $post_id, '_cfh_form_type', true ) ) );
            return;
        }

        if ( $column === 'cfh_mail_status' ) {
            echo esc_html( $this->status_label( (string) get_post_meta( $post_id, '_cfh_mail_status', true ) ) );
            return;
        }

        if ( $column === 'cfh_webhook_status' ) {
            echo esc_html( $this->status_label( (string) get_post_meta( $post_id, '_cfh_webhook_status', true ) ) );
        }
    }

    private function form_type_label( string $form_type ): string {
        if ( $form_type === CFH_Form_Definitions::TYPE_ENERGY_FUNDING ) {
            return 'Energieberatung / Förderung';
        }

        return 'Fenster';
    }

    private function status_label( string $status ): string {
        return match ( $status ) {
            'pending' => 'Ausstehend',
            'sent' => 'Gesendet',
            'failed' => 'Fehlgeschlagen',
            'triggered' => 'Ausgelöst',
            'skipped' => 'Nicht konfiguriert',
            default => $status !== '' ? $status : '-',
        };
    }
}
