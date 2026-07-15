<?php
/**
 * Shortcodes für die verschiedenen Formulartypen.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CFH_Shortcode {

    public function register(): void {
        foreach ( CFH_Form_Definitions::get_shortcode_map() as $shortcode => $form_type ) {
            add_shortcode(
                $shortcode,
                static function () use ( $form_type ): string {
                    return ( new self() )->render( $form_type );
                }
            );
        }
    }

    public function render( string $form_type = CFH_Form_Definitions::TYPE_WINDOW ): string {
        $form              = CFH_Form_Definitions::get_form( $form_type );
        $steps             = $form['steps'];
        $total             = count( $steps );
        $instance          = wp_unique_id( 'cfh-form-' );
        $inline_error_json = wp_json_encode( CFH_Form_Definitions::get_error_messages( $form_type ) );
        $popup_error_json  = wp_json_encode( CFH_Form_Definitions::get_popup_error_messages() );

        ob_start();
        ?>
        <div
            class="cfh-form-container"
            data-cfh-form="1"
            data-form-type="<?php echo esc_attr( $form_type ); ?>"
            data-instance="<?php echo esc_attr( $instance ); ?>"
            data-inline-error-messages="<?php echo esc_attr( $inline_error_json ?: '{}' ); ?>"
            data-popup-error-messages="<?php echo esc_attr( $popup_error_json ?: '{}' ); ?>"
        >
            <p class="cfh-intro"><?php echo esc_html( $form['intro'] ); ?></p>

            <form
                class="cfh-multi-step-form"
                action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                method="post"
                novalidate
            >
                <input type="hidden" name="action" value="cfh_submit">
                <input type="hidden" name="cfh_nonce" value="<?php echo esc_attr( wp_create_nonce( 'cfh_submit' ) ); ?>">
                <input type="hidden" name="cfh_form_type" value="<?php echo esc_attr( $form_type ); ?>">
                <input type="hidden" name="cfh_form_url" value="<?php echo esc_url( get_permalink() ); ?>">
                <?php $this->render_tracking_fields(); ?>

                <div class="cfh-hp-field" aria-hidden="true">
                    <label for="<?php echo esc_attr( $instance . '-hp' ); ?>">Website leer lassen</label>
                    <input type="text" id="<?php echo esc_attr( $instance . '-hp' ); ?>" name="cfh_hp_name" value="" autocomplete="off" tabindex="-1">
                </div>

                <div class="cfh-error-msg" role="alert" aria-live="polite"></div>

                <?php foreach ( $steps as $index => $step ) : ?>
                    <div class="cfh-step<?php echo 0 === $index ? ' active' : ''; ?>" data-step="<?php echo esc_attr( (string) ( $index + 1 ) ); ?>">
                        <h2><?php echo esc_html( $step['title'] ); ?></h2>
                        <?php
                        if ( $index === $total - 1 ) {
                            $this->render_summary( $steps, $index );
                        }
                        ?>
                        <?php $this->render_fields( $step['fields'], $instance ); ?>
                        <?php $this->render_navigation( $index, $total ); ?>
                    </div>
                <?php endforeach; ?>

                <div class="cfh-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) round( 100 / max( 1, $total ) ) ); ?>">
                    <div class="cfh-progress-fill"></div>
                </div>
                <p class="cfh-step-counter">Schritt 1 von <?php echo esc_html( (string) $total ); ?></p>
            </form>

            <div class="cfh-modal" hidden>
                <div class="cfh-modal__overlay" data-cfh-modal-close></div>
                <div
                    class="cfh-modal__dialog"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="<?php echo esc_attr( $instance ); ?>-modal-title"
                    aria-describedby="<?php echo esc_attr( $instance ); ?>-modal-message"
                >
                    <button type="button" class="cfh-modal__close" data-cfh-modal-close aria-label="Pop-up schließen">&times;</button>
                    <h3 class="cfh-modal__title" id="<?php echo esc_attr( $instance ); ?>-modal-title">Fehler beim Senden</h3>
                    <p class="cfh-modal__message" id="<?php echo esc_attr( $instance ); ?>-modal-message"></p>
                    <button type="button" class="cfh-btn cfh-btn--submit cfh-modal__button" data-cfh-modal-close>Schließen</button>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array<int,array<string,mixed>> $fields
     */
    private function render_fields( array $fields, string $instance ): void {
        foreach ( $fields as $field ) {
            if ( $field['type'] === 'radio_group' ) {
                $this->render_radio_group( $field );
                continue;
            }

            if ( $field['type'] === 'checkbox' ) {
                $this->render_checkbox( $field, $instance );
                continue;
            }

            $this->render_input( $field, $instance );
        }
    }

    /**
     * @param array<string,mixed> $field
     */
    private function render_radio_group( array $field ): void {
        ?>
        <p class="cfh-group-label"><?php echo esc_html( $field['group_label'] ); ?></p>
        <div class="cfh-btn-group" role="group" aria-label="<?php echo esc_attr( $field['group_label'] ); ?>">
            <?php foreach ( $field['options'] as $value => $label ) : ?>
                <?php
                $icon = '';
                if ( ! empty( $field['icons'] ) && is_array( $field['icons'] ) ) {
                    $icon = (string) ( $field['icons'][ $value ] ?? '' );
                }
                ?>
                <label class="cfh-option-label">
                    <input
                        type="radio"
                        name="<?php echo esc_attr( $field['name'] ); ?>"
                        value="<?php echo esc_attr( $value ); ?>"
                        data-cfh-label="<?php echo esc_attr( $label ); ?>"
                        <?php checked( ! empty( $field['default'] ) && $field['default'] === $value ); ?>
                        <?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>
                    >
                    <span class="cfh-option-content">
                        <?php echo $this->render_icon( $icon ); ?>
                        <span class="cfh-option-text"><?php echo esc_html( $label ); ?></span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
        <?php if ( ! empty( $field['help'] ) ) : ?>
            <small><?php echo esc_html( $field['help'] ); ?></small>
        <?php endif; ?>
        <div class="cfh-field-error" data-cfh-error-for="<?php echo esc_attr( $field['name'] ); ?>" aria-live="polite"></div>
        <?php
    }

    /**
     * @param array<string,mixed> $field
     */
    private function render_input( array $field, string $instance ): void {
        $id       = $instance . '-' . $field['name'];
        $has_icon = ! empty( $field['icon'] );
        ?>
        <div class="cfh-form-group<?php echo $has_icon ? ' cfh-form-group--with-icon' : ''; ?>">
            <label for="<?php echo esc_attr( $id ); ?>">
                <?php echo esc_html( $field['label'] ); ?>
                <?php
                if ( ! empty( $field['label_suffix'] ) ) {
                    echo ' ' . wp_kses_post( $field['label_suffix'] );
                }
                ?>
            </label>
            <?php if ( $has_icon ) : ?>
                <div class="cfh-input-wrap">
                    <?php echo $this->render_icon( (string) $field['icon'] ); ?>
            <?php endif; ?>
            <input
                type="<?php echo esc_attr( $field['type'] ); ?>"
                id="<?php echo esc_attr( $id ); ?>"
                name="<?php echo esc_attr( $field['name'] ); ?>"
                <?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>
                <?php echo ! empty( $field['placeholder'] ) ? 'placeholder="' . esc_attr( $field['placeholder'] ) . '"' : ''; ?>
                <?php echo ! empty( $field['pattern'] ) ? 'pattern="' . esc_attr( $field['pattern'] ) . '"' : ''; ?>
                <?php echo ! empty( $field['inputmode'] ) ? 'inputmode="' . esc_attr( $field['inputmode'] ) . '"' : ''; ?>
                <?php echo ! empty( $field['maxlength'] ) ? 'maxlength="' . esc_attr( $field['maxlength'] ) . '"' : ''; ?>
                <?php echo ! empty( $field['autocomplete'] ) ? 'autocomplete="' . esc_attr( $field['autocomplete'] ) . '"' : ''; ?>
            >
            <?php if ( $has_icon ) : ?>
                </div>
            <?php endif; ?>
            <?php if ( ! empty( $field['help'] ) ) : ?>
                <small><?php echo esc_html( $field['help'] ); ?></small>
            <?php endif; ?>
            <div class="cfh-field-error" data-cfh-error-for="<?php echo esc_attr( $field['name'] ); ?>" aria-live="polite"></div>
        </div>
        <?php
    }

    /**
     * @param array<string,mixed> $field
     */
    private function render_checkbox( array $field, string $instance ): void {
        $id = $instance . '-' . $field['name'];
        ?>
        <div class="cfh-form-group cfh-gdpr">
            <label class="cfh-checkbox-label" for="<?php echo esc_attr( $id ); ?>">
                <?php echo $this->render_icon( (string) ( $field['icon'] ?? '' ), 'cfh-icon cfh-checkbox-icon' ); ?>
                <input
                    type="checkbox"
                    id="<?php echo esc_attr( $id ); ?>"
                    name="<?php echo esc_attr( $field['name'] ); ?>"
                    value="1"
                    <?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>
                >
                <span><?php echo wp_kses_post( $field['label'] ); ?></span>
            </label>
            <div class="cfh-field-error" data-cfh-error-for="<?php echo esc_attr( $field['name'] ); ?>" aria-live="polite"></div>
        </div>
        <?php
    }

    private function render_tracking_fields(): void {
        $fields = array( 'landingPage', 'referrer', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid' );

        foreach ( $fields as $field ) {
            ?>
            <input type="hidden" name="<?php echo esc_attr( $field ); ?>" value="" data-cfh-tracking="<?php echo esc_attr( $field ); ?>">
            <?php
        }
    }

    /**
     * @param array<int,array<string,mixed>> $steps
     */
    private function render_summary( array $steps, int $current_index ): void {
        $rows = array();

        for ( $i = 0; $i < $current_index; $i++ ) {
            foreach ( $steps[ $i ]['fields'] as $field ) {
                if ( empty( $field['name'] ) || $field['type'] === 'checkbox' ) {
                    continue;
                }

                $label = $field['group_label'] ?? $field['label'] ?? $field['name'];
                $label = preg_replace( '/\s+wählen$/', '', (string) $label );
                $rows[] = array(
                    'name'  => (string) $field['name'],
                    'label' => (string) $label,
                );
            }
        }

        if ( empty( $rows ) ) {
            return;
        }
        ?>
        <div class="cfh-summary" data-cfh-summary>
            <h3>Ihre Angaben</h3>
            <dl>
                <?php foreach ( $rows as $row ) : ?>
                    <div class="cfh-summary__row" data-cfh-summary-row="<?php echo esc_attr( $row['name'] ); ?>">
                        <dt><?php echo esc_html( $row['label'] ); ?></dt>
                        <dd data-cfh-summary-value="<?php echo esc_attr( $row['name'] ); ?>">-</dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
        <?php
    }

    private function render_icon( string $name, string $class = 'cfh-icon' ): string {
        $icons = array(
            'badge-euro'      => '<circle cx="12" cy="12" r="10"></circle><path d="M15 8.5a4 4 0 0 0-6 3.5 4 4 0 0 0 6 3.5"></path><path d="M7 10h5"></path><path d="M7 14h5"></path>',
            'briefcase'       => '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path><rect width="20" height="14" x="2" y="6" rx="2"></rect>',
            'building'        => '<rect width="16" height="20" x="4" y="2" rx="2" ry="2"></rect><path d="M9 22v-4h6v4"></path><path d="M8 6h.01"></path><path d="M16 6h.01"></path><path d="M12 6h.01"></path><path d="M12 10h.01"></path><path d="M12 14h.01"></path><path d="M16 10h.01"></path><path d="M16 14h.01"></path><path d="M8 10h.01"></path><path d="M8 14h.01"></path>',
            'clock'           => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>',
            'clipboard-check' => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><path d="m9 14 2 2 4-4"></path>',
            'door-open'       => '<path d="M13 4h3a2 2 0 0 1 2 2v14"></path><path d="M2 20h20"></path><path d="M13 20V4a2 2 0 0 0-2.75-1.85L5 4v16"></path><path d="M10 12h.01"></path>',
            'flame'           => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07 1.28-2 2.5-2 4a4 4 0 0 0 8 0c0-1.54-.75-2.83-1.5-4C13.4 7.28 13 5.5 13 3c-2 1.5-4 4-4 7"></path>',
            'hammer'          => '<path d="m15 12-8.5 8.5a2.12 2.12 0 1 1-3-3L12 9"></path><path d="M17.64 15 22 10.64"></path><path d="m20.91 11.7-1.25-1.25c-.6-.6-.93-1.41-.93-2.26V7L16 4.27h-1.19c-.85 0-1.66-.34-2.26-.94L11.3 2.09a.5.5 0 0 0-.7 0L8.5 4.18a.5.5 0 0 0 0 .71L10 6.38"></path>',
            'handshake'       => '<path d="m11 17 2 2a1 1 0 1 0 3-3"></path><path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l.47.28a2 2 0 0 0 1.42.25L21 4"></path><path d="m21 3 1 11h-2"></path><path d="M3 3 2 14l6.5 6.5a1 1 0 1 0 3-3"></path><path d="M3 4h8"></path>',
            'home'            => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline>',
            'key'             => '<circle cx="7.5" cy="15.5" r="5.5"></circle><path d="m21 2-9.6 9.6"></path><path d="m15.5 7.5 3 3L22 7l-3-3"></path>',
            'landmark'        => '<line x1="3" x2="21" y1="22" y2="22"></line><line x1="6" x2="6" y1="18" y2="11"></line><line x1="10" x2="10" y1="18" y2="11"></line><line x1="14" x2="14" y1="18" y2="11"></line><line x1="18" x2="18" y1="18" y2="11"></line><polygon points="12 2 20 7 4 7"></polygon>',
            'layers'          => '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.91a1 1 0 0 0 0-1.83z"></path><path d="m22 12.5-9.17 4.18a2 2 0 0 1-1.66 0L2 12.5"></path><path d="m22 17.5-9.17 4.18a2 2 0 0 1-1.66 0L2 17.5"></path>',
            'mail'            => '<rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-10 5L2 7"></path>',
            'map-pin'         => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle>',
            'message-circle'  => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path>',
            'panel-top'       => '<rect width="18" height="18" x="3" y="3" rx="2"></rect><path d="M3 9h18"></path>',
            'phone'           => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.35 1.9.66 2.81a2 2 0 0 1-.45 2.11L8.05 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.31 1.85.53 2.81.66A2 2 0 0 1 22 16.92z"></path>',
            'shield-check'    => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.5 3.8 17 5 19 5a1 1 0 0 1 1 1z"></path><path d="m9 12 2 2 4-4"></path>',
            'sparkles'        => '<path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"></path>',
            'sun'             => '<circle cx="12" cy="12" r="4"></circle><path d="M12 2v2"></path><path d="M12 20v2"></path><path d="m4.93 4.93 1.41 1.41"></path><path d="m17.66 17.66 1.41 1.41"></path><path d="M2 12h2"></path><path d="M20 12h2"></path><path d="m6.34 17.66-1.41 1.41"></path><path d="m19.07 4.93-1.41 1.41"></path>',
            'user-round'      => '<circle cx="12" cy="8" r="5"></circle><path d="M20 21a8 8 0 0 0-16 0"></path>',
        );

        if ( ! isset( $icons[ $name ] ) ) {
            return '';
        }

        return sprintf(
            '<svg class="%s" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
            esc_attr( $class ),
            $icons[ $name ]
        );
    }

    private function render_navigation( int $index, int $total ): void {
        $is_first = 0 === $index;
        $is_last  = $index === $total - 1;
        ?>
        <div class="cfh-nav">
            <?php if ( ! $is_first ) : ?>
                <button type="button" class="cfh-btn cfh-btn--prev">&larr; Zurück</button>
            <?php endif; ?>

            <?php if ( $is_last ) : ?>
                <button type="submit" class="cfh-btn cfh-btn--submit">Kostenlose Ersteinschätzung anfragen</button>
            <?php else : ?>
                <button type="button" class="cfh-btn cfh-btn--next">Weiter</button>
            <?php endif; ?>
        </div>
        <?php
    }
}
