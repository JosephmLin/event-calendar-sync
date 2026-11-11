<?php
/**
 * Plugin Name: CrowdWork CardHouseTheater Plugin
 * Description: Adds an artistic Card House Theater presentation for CrowdWork classes and shows.
 * Version: 1.2.0
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
        && ! has_shortcode( $post->post_content, 'crowdwork_cardhouse_shows' )
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
        '1.2.0'
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

add_shortcode( 'crowdwork_cardhouse_shows', 'cwcw_render_shows_cards' );
function cwcw_render_shows_cards() {
    $heading_id = wp_unique_id( 'cwcw-shows-heading-' );
    ob_start();
    ?>
    <section class="cwcw-classes cwcw-shows" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
      <header class="cwcw-classes-header">
        <p class="cwcw-classes-eyebrow">Card House Theater <span aria-hidden="true">/</span> Tacoma, WA</p>
        <h2 id="<?php echo esc_attr( $heading_id ); ?>">Come see what’s on stage.</h2>
        <p class="cwcw-classes-intro">Find your next favorite night out at Card House Theater.</p>
        <span class="cwcw-classes-star" aria-hidden="true">✳</span>
      </header>
      <div class="cwcw-classes-calendar cwcw-shows-cards">
        <script id="fw_script" src="https://crowdwork.com/embed.js?v=6" data-theatre="cardhousetheater" data-type="shows" data-view="cards"></script>
      </div>
    </section>
    <?php
    return ob_get_clean();
}

add_filter( 'body_class', 'cwcw_classes_body_class' );
function cwcw_classes_body_class( $classes ) {
    if ( ! is_singular() ) { return $classes; }
    $post = get_post();
    if ( ! $post ) { return $classes; }

    $has_classes = has_shortcode( $post->post_content, 'crowdwork_cardhouse_classes' );
    $has_shows = has_shortcode( $post->post_content, 'crowdwork_cardhouse_shows' );
    if ( $has_classes || $has_shows ) {
        $classes[] = 'cwcw-has-crowdwork-embed';
    }
    if ( $has_classes ) {
        $classes[] = 'cwcw-has-classes-calendar';
    }
    return $classes;
}
