<?php
/**
 * Widget de WordPress para MakIA Reservas
 */

// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Widget de reservas MakIA
 */
class MakIA_Booking_Widget extends WP_Widget {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'makia_booking_widget',
            __( 'MakIA Reservas', 'makia-client' ),
            array(
                'description' => __( 'Formulario de reservas MakIA', 'makia-client' ),
                'classname'   => 'makia-booking-widget',
            )
        );
    }

    /**
     * Frontend del widget
     */
    public function widget( $args, $instance ) {
        $client = makia_client();

        if ( ! $client->is_configured() ) {
            return;
        }

        $title = ! empty( $instance['title'] ) ? $instance['title'] : '';
        $theme = ! empty( $instance['theme'] ) ? $instance['theme'] : get_option( 'makia_widget_theme', 'light' );
        $color = ! empty( $instance['color'] ) ? $instance['color'] : get_option( 'makia_primary_color', '#4f46e5' );

        echo $args['before_widget'];

        if ( $title ) {
            echo $args['before_title'] . esc_html( apply_filters( 'widget_title', $title ) ) . $args['after_title'];
        }

        printf(
            '<div id="makia-booking-%s" data-restaurant="%s" data-theme="%s" data-primary-color="%s"></div>',
            esc_attr( $this->id ),
            esc_attr( get_option( 'makia_organization' ) ),
            esc_attr( $theme ),
            esc_attr( $color )
        );

        echo $args['after_widget'];
    }

    /**
     * Formulario de configuración
     */
    public function form( $instance ) {
        $title = ! empty( $instance['title'] ) ? $instance['title'] : '';
        $theme = ! empty( $instance['theme'] ) ? $instance['theme'] : 'light';
        $color = ! empty( $instance['color'] ) ? $instance['color'] : '#4f46e5';
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
                <?php esc_html_e( 'Título:', 'makia-client' ); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
                   type="text" value="<?php echo esc_attr( $title ); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'theme' ) ); ?>">
                <?php esc_html_e( 'Tema:', 'makia-client' ); ?>
            </label>
            <select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'theme' ) ); ?>"
                    name="<?php echo esc_attr( $this->get_field_name( 'theme' ) ); ?>">
                <option value="light" <?php selected( $theme, 'light' ); ?>>
                    <?php esc_html_e( 'Claro', 'makia-client' ); ?>
                </option>
                <option value="dark" <?php selected( $theme, 'dark' ); ?>>
                    <?php esc_html_e( 'Oscuro', 'makia-client' ); ?>
                </option>
                <option value="auto" <?php selected( $theme, 'auto' ); ?>>
                    <?php esc_html_e( 'Automático', 'makia-client' ); ?>
                </option>
            </select>
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'color' ) ); ?>">
                <?php esc_html_e( 'Color primario:', 'makia-client' ); ?>
            </label>
            <input type="color" id="<?php echo esc_attr( $this->get_field_id( 'color' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'color' ) ); ?>"
                   value="<?php echo esc_attr( $color ); ?>">
        </p>
        <?php
    }

    /**
     * Guardar configuración
     */
    public function update( $new_instance, $old_instance ) {
        $instance = array();
        $instance['title'] = ! empty( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
        $instance['theme'] = ! empty( $new_instance['theme'] ) ? sanitize_text_field( $new_instance['theme'] ) : 'light';
        $instance['color'] = ! empty( $new_instance['color'] ) ? sanitize_hex_color( $new_instance['color'] ) : '#4f46e5';
        return $instance;
    }
}
