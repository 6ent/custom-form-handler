<?php
/**
 * GitHub-Release-Updater für Custom Form Handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CFH_Github_Updater {

    private const CACHE_KEY          = 'cfh_github_release_data';
    private const CACHE_TTL          = HOUR_IN_SECONDS;
    private const RELEASE_ASSET_NAME = 'custom-form-handler.zip';

    public function register(): void {
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
        add_filter( 'plugins_api', array( $this, 'filter_plugin_info' ), 10, 3 );
        add_action( 'admin_notices', array( $this, 'maybe_show_setup_notice' ) );
    }

    /**
     * @param stdClass $transient
     */
    public function inject_update( $transient ): object {
        if ( ! is_object( $transient ) ) {
            $transient = new stdClass();
        }

        if ( empty( $transient->checked ) || ! is_array( $transient->checked ) ) {
            return $transient;
        }

        $repository = $this->get_repository();
        if ( $repository === '' ) {
            return $transient;
        }

        $release = $this->get_latest_release( $repository );
        if ( $release === null ) {
            return $transient;
        }

        $plugin_basename = plugin_basename( CFH_PLUGIN_FILE );
        $current_version = $transient->checked[ $plugin_basename ] ?? CFH_VERSION;
        $new_version     = $this->normalize_version( $release['tag_name'] ?? '' );
        $package_url     = $this->get_release_asset_url( $release );

        if ( $new_version === '' || $package_url === '' || version_compare( $new_version, $current_version, '<=' ) ) {
            return $transient;
        }

        $transient->response[ $plugin_basename ] = (object) array(
            'slug'        => dirname( $plugin_basename ),
            'plugin'      => $plugin_basename,
            'new_version' => $new_version,
            'package'     => $package_url,
            'url'         => $release['html_url'] ?? 'https://github.com/' . $repository . '/releases',
            'tested'      => get_bloginfo( 'version' ),
            'requires'    => '5.8',
            'requires_php' => '8.1',
        );

        return $transient;
    }

    /**
     * @param array<string,mixed> $args
     */
    public function filter_plugin_info( false|object|array $result, string $action, object $args ): false|object|array {
        if ( $action !== 'plugin_information' || empty( $args->slug ) || $args->slug !== dirname( plugin_basename( CFH_PLUGIN_FILE ) ) ) {
            return $result;
        }

        $repository = $this->get_repository();
        if ( $repository === '' ) {
            return $result;
        }

        $release = $this->get_latest_release( $repository );
        if ( $release === null ) {
            return $result;
        }

        $new_version = $this->normalize_version( $release['tag_name'] ?? '' );
        $package_url = $this->get_release_asset_url( $release );
        $sections    = array(
            'description' => '<p>Custom Form Handler mit GitHub-Release-Updates.</p>',
            'changelog'   => wp_kses_post( wpautop( (string) ( $release['body'] ?? 'Kein Changelog hinterlegt.' ) ) ),
        );

        return (object) array(
            'name'          => 'Custom Form Handler',
            'slug'          => dirname( plugin_basename( CFH_PLUGIN_FILE ) ),
            'version'       => $new_version !== '' ? $new_version : CFH_VERSION,
            'author'        => '<a href="https://nrghaus.de">Gentrit Cerimi</a>',
            'homepage'      => $release['html_url'] ?? 'https://github.com/' . $repository,
            'download_link' => $package_url,
            'requires'      => '5.8',
            'requires_php'  => '8.1',
            'tested'        => get_bloginfo( 'version' ),
            'sections'      => $sections,
        );
    }

    public function maybe_show_setup_notice(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( $screen && $screen->id !== 'settings_page_cfh-settings' ) {
            return;
        }

        if ( $this->get_repository() !== '' ) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__( 'Custom Form Handler: Bitte prüfen Sie das GitHub-Repository in den Plugin-Einstellungen, falls Updates nicht erkannt werden.', 'custom-form-handler' );
        echo '</p></div>';
    }

    private function get_repository(): string {
        $settings = ( new CFH_Settings() )->get();
        $repo     = sanitize_text_field( $settings['github_repository'] ?? '' );

        if ( preg_match( '/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $repo ) ) {
            return $repo;
        }

        return CFH_GITHUB_REPOSITORY;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function get_latest_release( string $repository ): ?array {
        $cache_key = self::CACHE_KEY . '_' . md5( $repository );
        $cached    = get_site_transient( $cache_key );

        if ( is_array( $cached ) ) {
            return $cached;
        }

        $response = wp_remote_get(
            'https://api.github.com/repos/' . $repository . '/releases/latest',
            array(
                'timeout' => 10,
                'headers' => array(
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'Custom-Form-Handler/' . CFH_VERSION . '; ' . home_url(),
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $status_code = (int) wp_remote_retrieve_response_code( $response );
        if ( $status_code !== 200 ) {
            return null;
        }

        $data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) || ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
            return null;
        }

        set_site_transient( $cache_key, $data, self::CACHE_TTL );
        return $data;
    }

    /**
     * @param array<string,mixed> $release
     */
    private function get_release_asset_url( array $release ): string {
        if ( empty( $release['assets'] ) || ! is_array( $release['assets'] ) ) {
            return '';
        }

        foreach ( $release['assets'] as $asset ) {
            if ( ! is_array( $asset ) ) {
                continue;
            }

            if ( ( $asset['name'] ?? '' ) === self::RELEASE_ASSET_NAME ) {
                return esc_url_raw( (string) ( $asset['browser_download_url'] ?? '' ) );
            }
        }

        return '';
    }

    private function normalize_version( string $tag ): string {
        $tag = ltrim( trim( $tag ), 'vV' );
        return preg_match( '/^\d+\.\d+\.\d+$/', $tag ) ? $tag : '';
    }
}
