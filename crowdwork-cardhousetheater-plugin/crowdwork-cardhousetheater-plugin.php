<?php
/**
 * Plugin Name: CrowdWork CardHouseTheater Plugin
 * Description: Adds an artistic Card House Theater presentation for the CrowdWork Classes calendar.
 * Version: 1.1.0
 * Author: Card House Theater
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_enqueue_scripts', 'cwcw_enqueue_styles' );
function cwcw_enqueue_styles() {
    if ( ! is_singular() ) { return; }
    $post = get_post();
    if ( ! $post || ! has_shortcode( $post->post_content, 'crowdwork_cardhouse_classes' ) ) { return; }

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
        '1.1.0'
    );
}

add_shortcode( 'crowdwork_cardhouse_classes', 'cwcw_render_classes_calendar' );
function cwcw_render_classes_calendar() {
    $heading_id = wp_unique_id( 'cwcw-classes-heading-' );
    ob_start();
    ?>
    <section class="cwcw-classes" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
      <header class="cwcw-classes-header">
        <p class="cwcw-classes-eyebrow">Card House Theater <span aria-hidden="true">/</span> Tacoma, WA</p>
        <h2 id="<?php echo esc_attr( $heading_id ); ?>">Find your next scene.</h2>
        <p class="cwcw-classes-intro">Explore improv classes and make a little room for the unexpected.</p>
        <span class="cwcw-classes-star" aria-hidden="true">✳</span>
      </header>
      <div class="cwcw-classes-calendar">
        <script id="fw_script" src="https://crowdwork.com/embed.js?v=6" data-theatre="cardhousetheater" data-type="classes" data-view="calendar"></script>
      </div>
    </section>
    <?php
    return ob_get_clean();
}

add_filter( 'body_class', 'cwcw_classes_body_class' );
function cwcw_classes_body_class( $classes ) {
    if ( ! is_singular() ) { return $classes; }
    $post = get_post();
    if ( $post && has_shortcode( $post->post_content, 'crowdwork_cardhouse_classes' ) ) {
        $classes[] = 'cwcw-has-classes-calendar';
    }
    return $classes;
}
