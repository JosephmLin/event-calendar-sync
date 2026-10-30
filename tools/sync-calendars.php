<?php
if ( PHP_SAPI !== 'cli' ) {
    if ( function_exists( 'http_response_code' ) ) { http_response_code( 404 ); }
    exit;
}

if ( $argc < 2 ) {
    fwrite( STDERR, "Usage: php tools/sync-calendars.php <wordpress-root>\n" );
    exit( 2 );
}

$wordpress_root = realpath( $argv[1] );
$bootstrap = $wordpress_root ? $wordpress_root . DIRECTORY_SEPARATOR . 'wp-load.php' : '';
if ( ! $wordpress_root || ! is_readable( $bootstrap ) ) {
    fwrite( STDERR, "Could not find a readable wp-load.php in the supplied WordPress root.\n" );
    exit( 2 );
}

if ( ! defined( 'WP_USE_THEMES' ) ) { define( 'WP_USE_THEMES', false ); }
require $bootstrap;

if ( ! function_exists( 'chs_sync_calendar_events' ) || ! function_exists( 'chs_additional_calendar_config' ) ) {
    fwrite( STDERR, "The Card House Google Calendar Events plugin must be active before syncing.\n" );
    exit( 1 );
}

$calendars = array();
if ( trim( (string) get_option( 'chs_ics_url', '' ) ) !== '' ) {
    $calendars[] = array(
        'name' => 'Shows',
        'sync' => 'chs_sync_calendar_events',
    );
}

foreach ( chs_additional_calendar_config() as $calendar => $config ) {
    if ( trim( (string) get_option( $config['option'], '' ) ) === '' ) { continue; }
    $calendars[] = array(
        'name' => $config['name'],
        'sync' => function () use ( $calendar ) {
            return chs_sync_additional_calendar( $calendar );
        },
    );
}

if ( empty( $calendars ) ) {
    fwrite( STDERR, "No calendar feed URLs are configured; nothing to sync.\n" );
    exit( 1 );
}

$failed = false;
foreach ( $calendars as $calendar ) {
    $result = is_callable( $calendar['sync'] ) ? call_user_func( $calendar['sync'] ) : false;
    if ( is_wp_error( $result ) ) {
        fwrite( STDERR, $calendar['name'] . ' sync failed: ' . $result->get_error_message() . "\n" );
        $failed = true;
        continue;
    }
    if ( $result === false ) {
        fwrite( STDERR, $calendar['name'] . " sync failed unexpectedly.\n" );
        $failed = true;
        continue;
    }
    echo sprintf( "%s sync complete: %d event(s) imported or updated.\n", $calendar['name'], (int) $result );
}

exit( $failed ? 1 : 0 );
