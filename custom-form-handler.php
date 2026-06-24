<?php
/**
 * Plugin Name:  Custom Form Handler
 * Plugin URI:   https://nrghaus.de
 * Description:  Verarbeitet Multi-Step-Lead-Formulare für Fenster und Energieberatung/Förderanfragen mit CSRF-Schutz, Honeypot, DSGVO-Checkbox, HTML-E-Mail, n8n-Webhook-Weiterleitung und Admin-Einstellungen.
 * Version:      2.4.3
 * Author:       Gentrit Cerimi
 * Requires PHP: 8.1
 * Requires at least: 5.8
 * Update URI:   https://nrghaus.de/plugins/custom-form-handler
 * License:      GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CFH_VERSION',     '2.4.3' );
define( 'CFH_PLUGIN_FILE', __FILE__ );
define( 'CFH_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'CFH_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'CFH_OPTION_GROUP', 'cfh_settings_group' );
define( 'CFH_OPTION_KEY',   'cfh_settings' );
define( 'CFH_GITHUB_REPOSITORY', '6ent/custom-form-handler' );

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

require_once CFH_PLUGIN_DIR . 'includes/class-cfh-settings.php';
require_once CFH_PLUGIN_DIR . 'includes/class-cfh-form-definitions.php';
require_once CFH_PLUGIN_DIR . 'includes/class-cfh-mailer.php';
require_once CFH_PLUGIN_DIR . 'includes/class-cfh-webhook.php';
require_once CFH_PLUGIN_DIR . 'includes/class-cfh-form-handler.php';
require_once CFH_PLUGIN_DIR . 'includes/class-cfh-github-updater.php';
require_once CFH_PLUGIN_DIR . 'includes/class-cfh-shortcode.php';

add_action( 'plugins_loaded', array( 'CFH_Plugin', 'get_instance' ) );

// ---------------------------------------------------------------------------
// Main plugin class
// ---------------------------------------------------------------------------

final class CFH_Plugin {

    private static ?CFH_Plugin $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Admin
        ( new CFH_Settings() )->register();

        // Front-end: form submission
        ( new CFH_Form_Handler() )->register();

        // Shortcode
        ( new CFH_Shortcode() )->register();

        // Enqueue front-end assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        if ( is_admin() ) {
            ( new CFH_Github_Updater() )->register();
        }
    }

    public function enqueue_assets(): void {
        // Assets immer laden – der JS-Guard (if (!wrap) return) verhindert
        // jede Ausführung auf Seiten ohne Formular. Eine serverseitige Prüfung
        // ist bei Elementor/Beaver/Divi unzuverlässig, da der Shortcode in
        // Page-Builder-Metadaten steckt und nicht in post_content steht.
        wp_enqueue_style(
            'cfh-form',
            CFH_PLUGIN_URL . 'assets/css/form.css',
            array(),
            CFH_VERSION
        );
        wp_enqueue_script(
            'cfh-form',
            CFH_PLUGIN_URL . 'assets/js/form.js',
            array(),
            CFH_VERSION,
            true
        );
    }

    private function current_page_has_shortcode(): bool {
        global $post;
        if ( ! ( $post instanceof WP_Post ) ) {
            return false;
        }

        $shortcodes = array_keys( CFH_Form_Definitions::get_shortcode_map() );

        // 1. Standard WordPress post_content check
        foreach ( $shortcodes as $tag ) {
            if ( has_shortcode( $post->post_content, $tag ) ) {
                return true;
            }
        }

        // 2. Elementor stores content in _elementor_data post meta as a JSON string.
        //    A raw string search is sufficient – no need to decode the full JSON.
        $elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
        if ( is_string( $elementor_data ) && $elementor_data !== '' ) {
            foreach ( $shortcodes as $tag ) {
                if ( str_contains( $elementor_data, $tag ) ) {
                    return true;
                }
            }
        }

        // 3. Generic fallback: check all post meta values for any page-builder
        //    that stores rendered content in meta (e.g. Divi, Beaver Builder).
        $meta_keys = array( '_fl_builder_data', 'et_pb_use_builder' );
        foreach ( $meta_keys as $key ) {
            $meta_value = get_post_meta( $post->ID, $key, true );
            if ( is_string( $meta_value ) && $meta_value !== '' ) {
                foreach ( $shortcodes as $tag ) {
                    if ( str_contains( $meta_value, $tag ) ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}

// ---------------------------------------------------------------------------
// Activation hook – nothing to install (no DB), just flush rewrite rules.
// ---------------------------------------------------------------------------

register_activation_hook( __FILE__, static function (): void {
    flush_rewrite_rules();
} );
