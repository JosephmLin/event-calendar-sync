<?php
/**
 * Plugin Name: Card House Google Calendar Events
 * Description: Imports events from public Google Calendar iCalendar feeds for shows, education, and community events.
 * Version: 1.1.4
 * Author: Card House Theater
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'CHS_VERSION', '1.1.4' );
define( 'CHS_CRON_HOOK', 'chs_sync_calendar' );

require_once __DIR__ . '/card-house-shows.php';
require_once __DIR__ . '/card-house-education.php';
require_once __DIR__ . '/card-house-community-events.php';
require_once __DIR__ . '/card-house-calendar-tools.php';

register_activation_hook( __FILE__, 'chs_activate' );
register_deactivation_hook( __FILE__, 'chs_deactivate' );

function chs_activate() {
    chs_shows_activate();
    chs_additional_calendars_activate();
    flush_rewrite_rules();
}

function chs_deactivate() {
    chs_shows_deactivate();
    chs_additional_calendars_deactivate();
    flush_rewrite_rules();
}
