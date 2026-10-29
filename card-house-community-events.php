<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function chs_community_calendar_config() {
    return array(
        'post_type' => 'chs_community_event',
        'option' => 'chs_community_ics_url',
        'cron' => 'chs_sync_community_calendar',
        'name' => 'Community Events',
        'singular' => 'Community Event',
        'slug' => 'community-event',
        'icon' => 'dashicons-groups',
    );
}

function chs_community_events_shortcode( $atts ) {
    return chs_render_additional_calendar( 'community', $atts, false );
}

function chs_community_events_carousel_shortcode( $atts ) {
    return chs_render_additional_calendar( 'community', $atts, true );
}

add_shortcode( 'card_house_community_events', 'chs_community_events_shortcode' );
add_shortcode( 'card_house_community_events_carousel', 'chs_community_events_carousel_shortcode' );
