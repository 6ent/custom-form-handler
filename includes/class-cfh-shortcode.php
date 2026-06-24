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
        $form       = CFH_Form_Definitions::get_form( $form_type );
        $steps      = $form['steps'];
        $total      = count( $steps );
        $instance   = wp_unique_id( 'cfh-form-' );
        $error_json = wp_json_encode( CFH_Form_Definitions::get_error_messages( $form_type ) );

        ob_start();
        ?>
        <div
            class="cfh-form-container"
            data-cfh-form="1"
            data-form-type="<?php echo esc_attr( $form_type ); ?>"
            data-instance="<?php echo esc_attr( $instance ); ?>"
            data-error-messages="<?php echo esc_attr( $error_json ?: '{}' ); ?>"
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

                <div class="cfh-hp-field" aria-hidden="true">
                    <label for="<?php echo esc_attr( $instance . '-hp' ); ?>">Website leer lassen</label>
                    <input type="text" id="<?php echo esc_attr( $instance . '-hp' ); ?>" name="cfh_hp_name" value="" autocomplete="off" tabindex="-1">
                </div>

                <div class="cfh-error-msg" role="alert" aria-live="polite"></div>

                <?php foreach ( $steps as $index => $step ) : ?>
                    <div class="cfh-step<?php echo 0 === $index ? ' active' : ''; ?>" data-step="<?php echo esc_attr( (string) ( $index + 1 ) ); ?>">
                        <h2><?php echo esc_html( $step['title'] ); ?></h2>
                        <?php $this->render_fields( $step['fields'], $instance ); ?>
                        <?php $this->render_navigation( $index, $total ); ?>
                    </div>
                <?php endforeach; ?>

                <div class="cfh-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) round( 100 / max( 1, $total ) ) ); ?>">
                    <div class="cfh-progress-fill"></div>
                </div>
                <p class="cfh-step-counter">Schritt 1 von <?php echo esc_html( (string) $total ); ?></p>
            </form>
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
        <div class="cfh-btn-group" role="group" aria-label="<?php echo esc_attr( $field['group_label'] ); ?>">
            <?php foreach ( $field['options'] as $value => $label ) : ?>
                <label class="cfh-option-label">
                    <input
                        type="radio"
                        name="<?php echo esc_attr( $field['name'] ); ?>"
                        value="<?php echo esc_attr( $value ); ?>"
                        <?php checked( ! empty( $field['default'] ) && $field['default'] === $value ); ?>
                        <?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>
                    >
                    <span><?php echo esc_html( $label ); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <?php if ( ! empty( $field['help'] ) ) : ?>
            <small><?php echo esc_html( $field['help'] ); ?></small>
        <?php endif; ?>
        <?php
    }

    /**
     * @param array<string,mixed> $field
     */
    private function render_input( array $field, string $instance ): void {
        $id = $instance . '-' . $field['name'];
        ?>
        <div class="cfh-form-group">
            <label for="<?php echo esc_attr( $id ); ?>">
                <?php echo esc_html( $field['label'] ); ?>
                <?php
                if ( ! empty( $field['label_suffix'] ) ) {
                    echo ' ' . wp_kses_post( $field['label_suffix'] );
                }
                ?>
            </label>
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
            <?php if ( ! empty( $field['help'] ) ) : ?>
                <small><?php echo esc_html( $field['help'] ); ?></small>
            <?php endif; ?>
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
                <input
                    type="checkbox"
                    id="<?php echo esc_attr( $id ); ?>"
                    name="<?php echo esc_attr( $field['name'] ); ?>"
                    value="1"
                    <?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>
                >
                <span><?php echo wp_kses_post( $field['label'] ); ?></span>
            </label>
        </div>
        <?php
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
                <button type="submit" class="cfh-btn cfh-btn--submit">Anfrage senden</button>
            <?php else : ?>
                <button type="button" class="cfh-btn cfh-btn--next">Weiter &rarr;</button>
            <?php endif; ?>
        </div>
        <?php
    }
}
