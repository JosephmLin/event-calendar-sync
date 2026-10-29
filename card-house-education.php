<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function chs_education_calendar_config() {
    return array(
        'post_type' => 'chs_workshop',
        'option' => 'chs_workshops_ics_url',
        'cron' => 'chs_sync_workshops_calendar',
        'name' => 'Education',
        'singular' => 'Education Event',
        'slug' => 'workshop',
        'icon' => 'dashicons-welcome-learn-more',
    );
}

function chs_education_shortcode( $atts ) {
    return chs_render_additional_calendar( 'education', $atts, false );
}

function chs_education_carousel_shortcode( $atts ) {
    return chs_render_additional_calendar( 'education', $atts, true );
}

add_shortcode( 'card_house_education', 'chs_education_shortcode' );
add_shortcode( 'card_house_education_carousel', 'chs_education_carousel_shortcode' );
add_shortcode( 'card_house_workshops', 'chs_education_shortcode' );
add_shortcode( 'card_house_workshops_carousel', 'chs_education_carousel_shortcode' );
