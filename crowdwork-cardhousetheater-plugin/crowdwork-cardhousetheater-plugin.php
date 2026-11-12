<?php
/**
 * Plugin Name: CrowdWork CardHouseTheater Plugin
 * Description: Adds an artistic Card House Theater presentation for CrowdWork classes and shows.
 * Version: 1.3.0
 * Author: Card House Theater
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_enqueue_scripts', 'cwcw_enqueue_styles' );
function cwcw_enqueue_styles() {
    if ( ! is_singular() ) { return; }
    $post = get_post();
    if ( ! $post || (
        ! has_shortcode( $post->post_content, 'crowdwork_cardhouse_classes' )
        && ! has_shortcode( $post->post_content, 'crowdwork_calendar' )
        && ! has_shortcode( $post->post_content, 'crowdwork_cardhouse_shows' )
        && ! has_shortcode( $post->post_content, 'crowdwork_cards' )
    ) ) { return; }

    wp_enqueue_style(
        'crowdwork-cardhousetheater-fonts',
        'https://fonts.googleapis.com/css2?family=Bodoni+Moda:opsz,wght@6..96,400;6..96,500;6..96,600;6..96,700&family=Caveat:wght@500;600;700&family=DM+Sans:wght@400;500;600;700&display=swap',
        array(),
        null
    );
    wp_enqueue_style(
        'crowdwork-cardhousetheater',
        plugin_dir_url( __FILE__ ) . 'assets/css/crowdwork-cardhousetheater.css',
        array( 'crowdwork-cardhousetheater-fonts' ),
        '1.3.0'
    );
}

add_shortcode( 'crowdwork_cardhouse_classes', 'cwcw_render_classes_calendar' );
add_shortcode( 'crowdwork_calendar', 'cwcw_render_classes_calendar' );
function cwcw_render_classes_calendar( $atts = array() ) {
    return cwcw_render_widget(
        $atts,
        'classes',
        'calendar',
        'Find your next scene.',
        'Explore improv classes and make a little room for the unexpected.',
        'cwcw-classes-heading-'
    );
}

add_shortcode( 'crowdwork_cardhouse_shows', 'cwcw_render_shows_cards' );
add_shortcode( 'crowdwork_cards', 'cwcw_render_shows_cards' );
function cwcw_render_shows_cards( $atts = array() ) {
    return cwcw_render_widget(
        $atts,
        'shows',
        'cards',
        'Come see what’s on stage.',
        'Find your next favorite night out at Card House Theater.',
        'cwcw-shows-heading-',
        true
    );
}

function cwcw_render_widget( $atts, $default_type, $default_view, $heading, $intro, $heading_prefix, $is_shows = false ) {
    $atts = shortcode_atts(
        array(
            'type'         => $default_type,
            'view'         => $default_view,
            'category'     => '',
            'category_bar' => '',
            'start_date'   => '',
            'start_dow'    => '',
            'days'         => '',
            'all_dates'    => '',
            'links'        => '',
        ),
        is_array( $atts ) ? $atts : array()
    );

    $type = strtolower( trim( (string) $atts['type'] ) );
    if ( ! in_array( $type, array( 'shows', 'classes', 'all' ), true ) ) {
        return cwcw_shortcode_error( 'The type attribute must be "shows", "classes", or "all".' );
    }

    $view = strtolower( trim( (string) $atts['view'] ) );
    if ( ! in_array( $view, array( 'calendar', 'cards' ), true ) ) {
        return cwcw_shortcode_error( 'The view attribute must be "calendar" or "cards".' );
    }

    $start_date = trim( (string) $atts['start_date'] );
    if ( '' !== $start_date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) ) {
        return cwcw_shortcode_error( 'The start_date attribute must use YYYY-MM-DD format.' );
    }
    if ( '' !== $start_date ) {
        $date_parts = array_map( 'intval', explode( '-', $start_date ) );
        if ( ! checkdate( $date_parts[1], $date_parts[2], $date_parts[0] ) ) {
            return cwcw_shortcode_error( 'The start_date attribute must be a valid calendar date.' );
        }
    }

    $start_dow = trim( (string) $atts['start_dow'] );
    if ( '' !== $start_dow && ! preg_match( '/^[0-6]$/', $start_dow ) ) {
        return cwcw_shortcode_error( 'The start_dow attribute must be a number from 0 (Sunday) to 6 (Saturday).' );
    }

    $days = trim( (string) $atts['days'] );
    if ( '' !== $days && ! in_array( $days, array( '1', '3', '7' ), true ) ) {
        return cwcw_shortcode_error( 'The days attribute must be 1, 3, or 7.' );
    }

    $links = strtolower( trim( (string) $atts['links'] ) );
    if ( '' !== $links && ! in_array( $links, array( 'on', 'off' ), true ) ) {
        return cwcw_shortcode_error( 'The links attribute must be "on" or "off".' );
    }

    $category_bar = cwcw_parse_shortcode_boolean( $atts['category_bar'] );
    if ( is_wp_error( $category_bar ) ) {
        return cwcw_shortcode_error( 'The category_bar attribute must be true or false.' );
    }
    $all_dates = cwcw_parse_shortcode_boolean( $atts['all_dates'] );
    if ( is_wp_error( $all_dates ) ) {
        return cwcw_shortcode_error( 'The all_dates attribute must be true or false.' );
    }

    $category = sanitize_text_field( (string) $atts['category'] );
    $heading_id = wp_unique_id( $heading_prefix );

    $script_attributes = array(
        'data-theatre="cardhousetheater"',
        'data-type="' . esc_attr( $type ) . '"',
        'data-view="' . esc_attr( $view ) . '"',
    );
    if ( '' !== $category ) {
        $script_attributes[] = 'data-category="' . esc_attr( $category ) . '"';
    }
    if ( $category_bar ) {
        $script_attributes[] = 'data-category-bar="true"';
    }
    if ( '' !== $start_date ) {
        $script_attributes[] = 'data-start-date="' . esc_attr( $start_date ) . '"';
    }
    if ( '' !== $start_dow ) {
        $script_attributes[] = 'data-start-dow="' . esc_attr( $start_dow ) . '"';
    }
    if ( '' !== $days ) {
        $script_attributes[] = 'data-days="' . esc_attr( $days ) . '"';
    }
    if ( $all_dates ) {
        $script_attributes[] = 'data-all-dates="true"';
    }
    if ( 'off' === $links ) {
        $script_attributes[] = 'data-links="off"';
    }

    $section_class = 'cwcw-classes';
    if ( $is_shows ) {
        $section_class .= ' cwcw-shows';
    }
    if ( 'cards' === $view ) {
        $section_class .= ' cwcw-cards';
    }

    ob_start();
    ?>
    <section class="<?php echo esc_attr( $section_class ); ?>" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
      <header class="cwcw-classes-header">
        <p class="cwcw-classes-eyebrow">Card House Theater <span aria-hidden="true">/</span> Tacoma, WA</p>
        <h2 id="<?php echo esc_attr( $heading_id ); ?>"><?php echo esc_html( $heading ); ?></h2>
        <p class="cwcw-classes-intro"><?php echo esc_html( $intro ); ?></p>
        <span class="cwcw-classes-star" aria-hidden="true">✳</span>
      </header>
      <div class="cwcw-classes-calendar<?php echo ( 'cards' === $view ) ? ' cwcw-cards' : ''; ?>">
        <script id="fw_script" src="https://crowdwork.com/embed.js?v=6" <?php echo implode( ' ', $script_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>></script>
      </div>
    </section>
    <?php
    return ob_get_clean();
}

function cwcw_parse_shortcode_boolean( $value ) {
    $value = strtolower( trim( (string) $value ) );
    if ( in_array( $value, array( '', '0', 'false', 'no', 'off' ), true ) ) {
        return false;
    }
    if ( in_array( $value, array( '1', 'true', 'yes', 'on' ), true ) ) {
        return true;
    }
    return new WP_Error( 'cwcw_invalid_boolean', 'Expected a true or false value.' );
}

function cwcw_shortcode_error( $message ) {
    return '<p class="cwcw-shortcode-error">' . esc_html( $message ) . '</p>';
}

add_filter( 'body_class', 'cwcw_classes_body_class' );
function cwcw_classes_body_class( $classes ) {
    if ( ! is_singular() ) { return $classes; }
    $post = get_post();
    if ( ! $post ) { return $classes; }

    $has_classes = has_shortcode( $post->post_content, 'crowdwork_cardhouse_classes' )
        || has_shortcode( $post->post_content, 'crowdwork_calendar' );
    $has_shows = has_shortcode( $post->post_content, 'crowdwork_cardhouse_shows' )
        || has_shortcode( $post->post_content, 'crowdwork_cards' );
    if ( $has_classes || $has_shows ) {
        $classes[] = 'cwcw-has-crowdwork-embed';
    }
    if ( $has_classes ) {
        $classes[] = 'cwcw-has-classes-calendar';
    }
    return $classes;
}
