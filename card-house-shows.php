<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function chs_shows_activate() {
    chs_register_post_type();
    if ( ! wp_next_scheduled( CHS_CRON_HOOK ) ) {
        wp_schedule_event( time() + 120, 'hourly', CHS_CRON_HOOK );
    }
}
function chs_shows_deactivate() {
    $timestamp = wp_next_scheduled( CHS_CRON_HOOK );
    if ( $timestamp ) { wp_unschedule_event( $timestamp, CHS_CRON_HOOK ); }
}
add_action( 'init', 'chs_register_post_type' );
function chs_register_post_type() {
    register_post_type( 'chs_show', array(
        'labels' => array(
            'name' => 'Shows', 'singular_name' => 'Show',
            'add_new_item' => 'Add Show', 'edit_item' => 'Edit Show',
            'all_items' => 'All Shows',
        ),
        'public' => true,
        'has_archive' => false,
        'rewrite' => array( 'slug' => 'show', 'with_front' => false ),
        'menu_icon' => 'dashicons-tickets-alt',
        'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
        'show_in_rest' => true,
    ) );
}

add_action( 'admin_menu', 'chs_admin_menu' );
function chs_admin_menu() {
    add_options_page( 'Card House Shows', 'Card House Shows', 'manage_options', 'card-house-shows', 'chs_settings_page' );
}
add_action( 'admin_init', 'chs_register_settings' );
function chs_register_settings() {
    register_setting( 'chs_settings_group', 'chs_ics_url', array(
        'type' => 'string',
        'sanitize_callback' => 'chs_sanitize_ics_url',
        'default' => '',
    ) );
}
function chs_sanitize_ics_url( $value ) {
    $value = trim( (string) $value );
    if ( $value === '' ) { return ''; }
    // Permit only HTTPS calendar feeds. Google public ICS feeds use HTTPS.
    if ( ! wp_http_validate_url( $value ) || strtolower( (string) wp_parse_url( $value, PHP_URL_SCHEME ) ) !== 'https' ) {
        add_settings_error( 'chs_ics_url', 'chs_bad_url', 'Enter a valid HTTPS iCalendar (.ics) feed URL.' );
        return get_option( 'chs_ics_url', '' );
    }
    return esc_url_raw( $value );
}
function chs_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $notice = '';
    if ( isset( $_POST['chs_sync_now'] ) && check_admin_referer( 'chs_sync_now_action', 'chs_sync_now_nonce' ) ) {
        $result = chs_sync_calendar_events();
        $notice = is_wp_error( $result ) ? $result->get_error_message() : sprintf( 'Sync complete. %d event(s) imported or updated.', (int) $result );
    }
    ?>
    <div class="wrap">
      <h1>Card House Shows</h1>
      <?php if ( $notice !== '' ) : ?><div class="notice notice-info"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
      <p>Paste the public Google Calendar address in iCal format. Sync runs hourly and can also be started manually below.</p>
      <form method="post" action="options.php">
        <?php settings_fields( 'chs_settings_group' ); ?>
        <table class="form-table"><tr>
          <th scope="row"><label for="chs_ics_url">Google Calendar ICS feed URL</label></th>
          <td><input name="chs_ics_url" id="chs_ics_url" type="url" class="regular-text" value="<?php echo esc_attr( get_option( 'chs_ics_url', '' ) ); ?>" placeholder="https://calendar.google.com/calendar/ical/..." />
          <p class="description">Use the Public address in iCal format, not the secret address. The feed must be publicly accessible.</p></td>
        </tr></table>
        <?php submit_button( 'Save Calendar Feed' ); ?>
      </form>
      <hr />
      <form method="post">
        <?php wp_nonce_field( 'chs_sync_now_action', 'chs_sync_now_nonce' ); ?>
        <input type="hidden" name="chs_sync_now" value="1" />
        <?php submit_button( 'Sync Now', 'secondary' ); ?>
      </form>
      <h2>Page shortcodes</h2>
      <p><code>[card_house_shows]</code> displays upcoming shows.</p>
      <p><code>[card_house_shows_carousel]</code> displays upcoming shows in a carousel, grouping events with the same title.</p>
      <p><code>[card_house_shows_archive]</code> displays past shows.</p>
      <p>Optional: <code>[card_house_shows limit="12"]</code>, <code>[card_house_shows_archive limit="24"]</code>, or <code>[card_house_shows_carousel limit="6"]</code>.</p>
      <p>Configure the Education and Community Events feeds under <a href="<?php echo esc_url( admin_url( 'options-general.php?page=chs-additional-calendars' ) ); ?>">Settings &rarr; Additional Show Calendars</a>.</p>
    </div>
    <?php
}
add_action( CHS_CRON_HOOK, 'chs_sync_calendar_events' );

function chs_sync_calendar_events() {
    $url = trim( (string) get_option( 'chs_ics_url', '' ) );
    if ( $url === '' ) {
        return new WP_Error( 'chs_no_feed', 'Save a Google Calendar ICS feed URL first.' );
    }
    $response = wp_safe_remote_get( $url, array( 'timeout' => 20, 'redirection' => 3, 'headers' => array( 'Accept' => 'text/calendar, text/plain;q=0.9, */*;q=0.8' ) ) );
    if ( is_wp_error( $response ) ) { return new WP_Error( 'chs_fetch_failed', 'Could not fetch the calendar: ' . $response->get_error_message() ); }
    $status = (int) wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    if ( $status < 200 || $status >= 300 || stripos( $body, 'BEGIN:VCALENDAR' ) === false ) {
        return new WP_Error( 'chs_invalid_feed', 'The feed did not return a valid iCalendar file. Check that the public ICS URL is correct.' );
    }
    $events = chs_parse_ics( $body );
    if ( empty( $events ) ) { return new WP_Error( 'chs_no_events', 'No events were found in the calendar feed. Check the calendar has events and try again.' ); }

    $count = 0;
    foreach ( $events as $event ) {
        if ( empty( $event['uid'] ) || empty( $event['start'] ) || empty( $event['end'] ) || empty( $event['summary'] ) ) { continue; }
        $existing = get_posts( array(
            'post_type' => 'chs_show', 'post_status' => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_chs_uid', 'meta_value' => $event['uid'],
        ) );
        $description = $event['description'];
        $ticket_url = ! empty( $event['url'] ) ? esc_url_raw( $event['url'] ) : '';
        $image_url = ! empty( $event['image_url'] ) ? esc_url_raw( $event['image_url'] ) : '';
        $description_ticket_url = chs_description_url( $description, 'TICKETS_URL' );
        $description_image_url = chs_description_url( $description, 'IMAGE_URL' );
        if ( $description_ticket_url ) { $ticket_url = $description_ticket_url; }
        if ( $description_image_url ) { $image_url = $description_image_url; }
        $description = chs_clean_description( $description );
        $content = wp_kses_post( nl2br( esc_html( $description ) ) );
        $post_data = array(
            'post_type' => 'chs_show',
            'post_status' => 'publish',
            'post_title' => sanitize_text_field( $event['summary'] ),
            'post_content' => $content,
            'post_excerpt' => wp_trim_words( wp_strip_all_tags( $description ), 28 ),
        );
        if ( ! empty( $existing ) ) {
            $post_data['ID'] = (int) $existing[0];
            $post_id = wp_update_post( wp_slash( $post_data ), true );
        } else {
            $post_id = wp_insert_post( wp_slash( $post_data ), true );
        }
        if ( is_wp_error( $post_id ) || ! $post_id ) { continue; }
        update_post_meta( $post_id, '_chs_uid', sanitize_text_field( $event['uid'] ) );
        update_post_meta( $post_id, '_chs_start', $event['start'] );
        update_post_meta( $post_id, '_chs_end', $event['end'] );
        update_post_meta( $post_id, '_chs_ticket_url', $ticket_url );
        update_post_meta( $post_id, '_chs_image_url', $image_url );
        update_post_meta( $post_id, '_chs_location', sanitize_text_field( $event['location'] ) );
        $count++;
    }
    update_option( 'chs_last_sync', current_time( 'mysql' ) );
    return $count;
}

function chs_description_url( $description, $label ) {
    $label = preg_quote( $label, '/' );
    $lines = preg_split( '/\r\n|\r|\n/', $description );
    foreach ( $lines as $line ) {
        if ( ! preg_match( '/^[ \t]*' . $label . '[ \t]*:[ \t]*(.*?)[ \t]*$/i', $line, $matches ) ) { continue; }
        $value = trim( $matches[1] );
        if ( preg_match( '/^\[[^\]]*\]\((https?:\/\/[^)\s]+)\)$/i', $value, $url_matches ) ) {
            $url = $url_matches[1];
        } elseif ( preg_match( '~https?://[^\s<>()]+~i', $value, $url_matches ) ) {
            $url = trim( $url_matches[0], " \t\r\n.,;" );
        } else {
            continue;
        }
        break;
    }
    if ( empty( $url ) ) { return ''; }
    $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
    if ( $host === 'google.com' || $host === 'www.google.com' ) {
        $path = (string) wp_parse_url( $url, PHP_URL_PATH );
        $query = (string) wp_parse_url( $url, PHP_URL_QUERY );
        if ( $path === '/url' && $query !== '' ) {
            parse_str( $query, $query_args );
            if ( ! empty( $query_args['q'] ) && preg_match( '~^https?://~i', $query_args['q'] ) ) { $url = $query_args['q']; }
        }
    }
    return esc_url_raw( $url );
}

function chs_clean_description( $description ) {
    $lines = preg_split( '/\r\n|\r|\n/', $description );
    $lines = array_filter( $lines, function ( $line ) {
        return ! preg_match( '/^[ \t]*(?:TICKETS_URL|IMAGE_URL)[ \t]*:/i', $line );
    } );
    return trim( implode( "\n", $lines ) );
}

/** Parse common Google Calendar ICS event fields, including TZID and UTC timestamps. */
function chs_parse_ics( $ics ) {
    $ics = str_replace( array( "\r\n", "\r" ), "\n", $ics );
    // Unfold iCalendar continuation lines.
    $ics = preg_replace( "/\n[ \t]/", '', $ics );
    $lines = explode( "\n", $ics );
    $events = array();
    $current = null;
    foreach ( $lines as $line ) {
        if ( strtoupper( trim( $line ) ) === 'BEGIN:VEVENT' ) { $current = array( 'uid' => '', 'summary' => '', 'description' => '', 'location' => '', 'start' => '', 'end' => '', 'url' => '', 'image_url' => '' ); continue; }
        if ( strtoupper( trim( $line ) ) === 'END:VEVENT' ) {
            if ( is_array( $current ) ) { $events[] = $current; }
            $current = null; continue;
        }
        if ( ! is_array( $current ) || strpos( $line, ':' ) === false ) { continue; }
        list( $property, $value ) = explode( ':', $line, 2 );
        $parts = explode( ';', $property );
        $name = strtoupper( array_shift( $parts ) );
        $params = array();
        foreach ( $parts as $part ) {
            if ( strpos( $part, '=' ) !== false ) { list( $k, $v ) = explode( '=', $part, 2 ); $params[strtoupper( $k )] = trim( $v, '"' ); }
        }
        $value = chs_ics_unescape( trim( $value ) );
        if ( $name === 'UID' ) { $current['uid'] = $value; }
        elseif ( $name === 'SUMMARY' ) { $current['summary'] = $value; }
        elseif ( $name === 'DESCRIPTION' ) { $current['description'] = $value; }
        elseif ( $name === 'LOCATION' ) { $current['location'] = $value; }
        elseif ( $name === 'URL' && preg_match( '~^https?://~i', $value ) ) { $current['url'] = $value; }
        elseif ( $name === 'ATTACH' && isset( $params['FMTTYPE'] ) && stripos( $params['FMTTYPE'], 'image/' ) === 0 && preg_match( '~^https?://~i', $value ) ) { $current['image_url'] = $value; }
        elseif ( $name === 'DTSTART' ) { $current['start'] = chs_ics_datetime( $value, $params ); }
        elseif ( $name === 'DTEND' ) { $current['end'] = chs_ics_datetime( $value, $params ); }
    }
    return $events;
}
function chs_ics_unescape( $value ) {
    return str_replace( array( '\\n', '\\N', '\\,', '\\;', '\\\\' ), array( "\n", "\n", ',', ';', '\\' ), $value );
}
function chs_ics_datetime( $value, $params ) {
    try {
        $site_tz = wp_timezone();
        if ( isset( $params['VALUE'] ) && strtoupper( $params['VALUE'] ) === 'DATE' ) {
            $dt = new DateTimeImmutable( substr( $value, 0, 8 ) . ' 00:00:00', $site_tz );
        } elseif ( substr( $value, -1 ) === 'Z' ) {
            $dt = new DateTimeImmutable( substr( $value, 0, -1 ), new DateTimeZone( 'UTC' ) );
        } else {
            $tz = isset( $params['TZID'] ) ? $params['TZID'] : $site_tz->getName();
            try { $zone = new DateTimeZone( $tz ); } catch ( Exception $e ) { $zone = $site_tz; }
            $dt = new DateTimeImmutable( $value, $zone );
        }
        return $dt->setTimezone( $site_tz )->format( 'Y-m-d H:i:s' );
    } catch ( Exception $e ) { return ''; }
}

function chs_shortcode_upcoming( $atts ) { return chs_render_shows( $atts, false ); }
function chs_shortcode_archive( $atts ) { return chs_render_shows( $atts, true ); }
function chs_shortcode_carousel( $atts ) { return chs_render_carousel( $atts ); }
add_shortcode( 'card_house_shows', 'chs_shortcode_upcoming' );
add_shortcode( 'card_house_shows_archive', 'chs_shortcode_archive' );
add_shortcode( 'card_house_shows_carousel', 'chs_shortcode_carousel' );

function chs_render_carousel( $atts ) {
    $atts = shortcode_atts( array( 'limit' => 12 ), $atts, 'card_house_shows_carousel' );
    $limit = max( 1, min( 100, absint( $atts['limit'] ) ) );
    $now = current_datetime();
    $query = new WP_Query( array(
        'post_type' => 'chs_show',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_key' => '_chs_start',
        'orderby' => 'meta_value',
        'order' => 'ASC',
        'meta_query' => array(
            array( 'key' => '_chs_end', 'value' => $now->format( 'Y-m-d H:i:s' ), 'compare' => '>', 'type' => 'DATETIME' ),
        ),
        'no_found_rows' => true,
    ) );

    $groups = array();
    foreach ( $query->posts as $show_post ) {
        $show_id = $show_post->ID;
        $title = get_the_title( $show_id );
        $key = strtolower( trim( preg_replace( '/\s+/', ' ', $title ) ) );
        if ( ! isset( $groups[ $key ] ) ) {
            $image = get_post_meta( $show_id, '_chs_image_url', true );
            if ( ! $image && has_post_thumbnail( $show_id ) ) { $image = get_the_post_thumbnail_url( $show_id, 'large' ); }
            $groups[ $key ] = array(
                'id' => $show_id,
                'title' => $title,
                'image' => $image,
                'ticket' => get_post_meta( $show_id, '_chs_ticket_url', true ),
                'location' => get_post_meta( $show_id, '_chs_location', true ),
                'dates' => array(),
                'month' => '',
            );
        }
        $start_raw = get_post_meta( $show_id, '_chs_start', true );
        $start = $start_raw ? DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $start_raw, wp_timezone() ) : false;
        if ( $start ) {
            $timestamp = $start->getTimestamp();
            $date_key = $start->format( 'Y-m-d H:i:s' );
            if ( ! isset( $groups[ $key ]['dates'][ $date_key ] ) ) {
                $groups[ $key ]['dates'][ $date_key ] = wp_date( 'D, M j · g:i a', $timestamp, wp_timezone() );
            }
            if ( $groups[ $key ]['month'] === '' ) {
                $groups[ $key ]['month'] = wp_date( 'M', $timestamp, wp_timezone() );
            }
        }
        if ( $groups[ $key ]['image'] === '' ) {
            $image = get_post_meta( $show_id, '_chs_image_url', true );
            if ( ! $image && has_post_thumbnail( $show_id ) ) { $image = get_the_post_thumbnail_url( $show_id, 'large' ); }
            if ( $image ) { $groups[ $key ]['image'] = $image; }
        }
        if ( $groups[ $key ]['ticket'] === '' ) {
            $groups[ $key ]['ticket'] = get_post_meta( $show_id, '_chs_ticket_url', true );
        }
        if ( $groups[ $key ]['location'] === '' ) {
            $groups[ $key ]['location'] = get_post_meta( $show_id, '_chs_location', true );
        }
    }
    $groups = array_slice( array_values( $groups ), 0, $limit );
    $carousel_id = wp_unique_id( 'chs-carousel-' );
    $track_id = $carousel_id . '-track';
    ob_start();
    ?>
    <section id="<?php echo esc_attr( $carousel_id ); ?>" class="chs-carousel" aria-label="Upcoming shows">
      <h2 class="chs-carousel-heading">Now Playing</h2>
      <?php if ( ! empty( $groups ) ) : ?>
        <div class="chs-carousel-viewport">
          <div id="<?php echo esc_attr( $track_id ); ?>" class="chs-carousel-track">
            <?php foreach ( $groups as $group ) : ?>
              <article class="chs-carousel-item">
                <?php if ( $group['month'] !== '' ) : ?><p class="chs-carousel-month"><?php echo esc_html( $group['month'] ); ?></p><?php endif; ?>
                <?php if ( $group['image'] ) : ?>
                  <a class="chs-carousel-image-link" href="<?php echo esc_url( get_permalink( $group['id'] ) ); ?>">
                    <img class="chs-carousel-image" src="<?php echo esc_url( $group['image'] ); ?>" alt="<?php echo esc_attr( $group['title'] ); ?>" loading="lazy" />
                  </a>
                <?php else : ?>
                  <a class="chs-carousel-image-link chs-carousel-image-placeholder" href="<?php echo esc_url( get_permalink( $group['id'] ) ); ?>" aria-label="<?php echo esc_attr( $group['title'] ); ?>"></a>
                <?php endif; ?>
                <h3 class="chs-carousel-title"><a href="<?php echo esc_url( get_permalink( $group['id'] ) ); ?>"><?php echo esc_html( $group['title'] ); ?></a></h3>
                <?php if ( ! empty( $group['dates'] ) ) : ?>
                  <p class="chs-carousel-dates">
                    <?php foreach ( $group['dates'] as $date ) : ?><span><?php echo esc_html( $date ); ?></span><?php endforeach; ?>
                  </p>
                <?php endif; ?>
                <?php if ( $group['location'] ) : ?><p class="chs-carousel-location"><?php echo esc_html( $group['location'] ); ?></p><?php endif; ?>
                <?php if ( $group['ticket'] ) : ?>
                  <a class="chs-carousel-ticket" href="<?php echo esc_url( $group['ticket'] ); ?>" target="_blank" rel="noopener noreferrer">Buy Tickets <span aria-hidden="true">&raquo;</span></a>
                <?php else : ?>
                  <a class="chs-carousel-ticket" href="<?php echo esc_url( get_permalink( $group['id'] ) ); ?>">Show Details <span aria-hidden="true">&raquo;</span></a>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="chs-carousel-controls">
          <button class="chs-carousel-arrow chs-carousel-previous" type="button" aria-label="Previous shows" aria-controls="<?php echo esc_attr( $track_id ); ?>">&lsaquo;</button>
          <div class="chs-carousel-dots" aria-label="Choose a group of shows"></div>
          <button class="chs-carousel-arrow chs-carousel-next" type="button" aria-label="Next shows" aria-controls="<?php echo esc_attr( $track_id ); ?>">&rsaquo;</button>
        </div>
      <?php else : ?>
        <p class="chs-carousel-empty">New shows are coming soon. Check back for upcoming performances!</p>
      <?php endif; ?>
    </section>
    <style>
      #<?php echo esc_attr( $carousel_id ); ?>{position:relative;box-sizing:border-box;margin:1.5rem 0;padding:24px 64px 20px;background:#d3e2ca;color:#171b16}
      #<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-heading{margin:0 0 24px;color:#528a43;font-size:1.5rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
      #<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-viewport{overflow-x:auto;scrollbar-width:none;scroll-snap-type:x mandatory;scroll-behavior:smooth}
      #<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-viewport::-webkit-scrollbar{display:none}
      #<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-track{display:flex;gap:24px}
      #<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-item{flex:0 0 calc((100% - 48px)/3);min-width:0;box-sizing:border-box;padding:0 24px 8px;border-left:1px solid #67905e;scroll-snap-align:start}
      #<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-month{margin:0 0 12px;color:#151a14;font-size:2rem;font-weight:800;line-height:1;text-transform:uppercase}
      #<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-image-link{display:block;aspect-ratio:3/4;margin-bottom:14px;background:#c1d3b9;overflow:hidden}
      #<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-image{display:block;width:100%;height:100%;object-fit:cover}
      #<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-title{margin:0 0 6px;font-size:1.15rem;font-weight:800;line-height:1.35;color:#171b16}
      #<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-title a{color:inherit;text-decoration:none}
      #<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-dates{display:flex;flex-direction:column;gap:3px;margin:0 0 6px;color:#171b16;font-size:1rem;line-height:1.4}
      #<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-location{margin:0 0 6px;color:#171b16;font-size:1rem;line-height:1.4}
      #<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-ticket{display:flex;justify-content:center;align-items:center;gap:10px;margin-top:16px;padding:10px 16px;border-radius:999px;background:#65a844;color:#fff;text-align:center;text-decoration:none;text-transform:uppercase;font-weight:800;letter-spacing:.04em}
      #<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-ticket:hover{background:#4f8d35;color:#fff}
      #<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-controls{display:flex;justify-content:center;align-items:center;gap:12px;margin-top:10px}
      #<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-controls[hidden]{display:none}
      #<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-arrow{position:absolute;top:50%;z-index:1;display:flex;justify-content:center;align-items:center;width:42px;height:42px;padding:0;border:0;border-radius:50%;background:#286a25;color:#fff;font-size:2rem;line-height:1;cursor:pointer;transform:translateY(-50%)}
      #<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-previous{left:12px}
      #<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-next{right:12px}
      #<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-arrow:disabled{opacity:.45;cursor:default}
      #<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-dots{display:flex;justify-content:center;gap:10px}
      #<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-dot{width:10px;height:10px;padding:0;border:0;border-radius:50%;background:#9dbb91;cursor:pointer}
      #<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-dot[aria-current="true"]{background:#286a25}
      #<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-empty{color:#171b16}
      @media (max-width:900px){#<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-item{flex-basis:calc((100% - 24px)/2)}}
      @media (max-width:600px){#<?php echo esc_attr( $carousel_id ); ?>{padding:20px 48px}#<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-item{flex-basis:100%;padding-right:12px;padding-left:16px}#<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-arrow{width:36px;height:36px}#<?php echo esc_attr( $carousel_id ); ?> .chs-carousel-heading{font-size:1.25rem}}
    </style>
    <script>
      (function(){
        var root=document.getElementById('<?php echo esc_js( $carousel_id ); ?>');
        if(!root||root.dataset.chsInitialized){return;}
        root.dataset.chsInitialized='true';
        var viewport=root.querySelector('.chs-carousel-viewport');
        var track=root.querySelector('.chs-carousel-track');
        var cards=Array.prototype.slice.call(track.children);
        var previous=root.querySelector('.chs-carousel-previous');
        var next=root.querySelector('.chs-carousel-next');
        var dots=root.querySelector('.chs-carousel-dots');
        var perPage=1;
        var pageCount=1;
        function getPerPage(){
          var gap=parseFloat(window.getComputedStyle(track).columnGap)||0;
          var cardWidth=cards[0].getBoundingClientRect().width;
          return Math.max(1,Math.floor((viewport.clientWidth+gap)/(cardWidth+gap)));
        }
        function update(){
          var page=Math.min(pageCount-1,Math.round(viewport.scrollLeft/viewport.clientWidth));
          Array.prototype.forEach.call(dots.children,function(dot,index){
            dot.setAttribute('aria-current',index===page?'true':'false');
          });
          previous.disabled=viewport.scrollLeft<=2;
          next.disabled=viewport.scrollLeft+viewport.clientWidth>=viewport.scrollWidth-2;
        }
        function setup(){
          perPage=getPerPage();
          pageCount=Math.ceil(cards.length/perPage);
          dots.textContent='';
          for(var page=0;page<pageCount;page++){
            var dot=document.createElement('button');
            dot.type='button';
            dot.className='chs-carousel-dot';
            dot.setAttribute('aria-label','Show group '+(page+1)+' of '+pageCount);
            dot.addEventListener('click',function(index){
              return function(){viewport.scrollTo({left:Math.min(cards[index*perPage].offsetLeft-track.offsetLeft,viewport.scrollWidth-viewport.clientWidth),behavior:'smooth'});};
            }(page));
            dots.appendChild(dot);
          }
          var hasOverflow=viewport.scrollWidth>viewport.clientWidth+2;
          root.querySelector('.chs-carousel-controls').hidden=!hasOverflow;
          update();
        }
        previous.addEventListener('click',function(){viewport.scrollBy({left:-viewport.clientWidth,behavior:'smooth'});});
        next.addEventListener('click',function(){viewport.scrollBy({left:viewport.clientWidth,behavior:'smooth'});});
        viewport.addEventListener('scroll',update,{passive:true});
        window.addEventListener('resize',setup);
        setup();
      })();
    </script>
    <?php
    return ob_get_clean();
}

function chs_render_shows( $atts, $archive ) {
    $atts = shortcode_atts( array( 'limit' => $archive ? 24 : 12 ), $atts, $archive ? 'card_house_shows_archive' : 'card_house_shows' );
    $limit = max( 1, min( 100, absint( $atts['limit'] ) ) );
    $now_dt = current_datetime();
    $now = $now_dt->getTimestamp();
    $meta_query = array(
        array( 'key' => '_chs_end', 'value' => $now_dt->format( 'Y-m-d H:i:s' ), 'compare' => $archive ? '<=' : '>', 'type' => 'DATETIME' ),
    );
    $query = new WP_Query( array(
        'post_type' => 'chs_show', 'post_status' => 'publish', 'posts_per_page' => $limit,
        'meta_key' => '_chs_start', 'orderby' => 'meta_value', 'order' => $archive ? 'DESC' : 'ASC',
        'meta_query' => $meta_query, 'no_found_rows' => true,
    ) );
    ob_start();
    $show_index = 0;
    ?>
    <div class="chs-show-grid">
      <?php if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post();
        $image_side = $show_index % 2 === 0 ? 'right' : 'left';
        $show_index++;
        $id = get_the_ID();
        $start_raw = get_post_meta( $id, '_chs_start', true );
        $end_raw = get_post_meta( $id, '_chs_end', true );
        $start = $start_raw ? DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $start_raw, wp_timezone() ) : false;
        $ticket = get_post_meta( $id, '_chs_ticket_url', true );
        $image = get_post_meta( $id, '_chs_image_url', true );
        $location = get_post_meta( $id, '_chs_location', true );
        if ( ! $image && has_post_thumbnail( $id ) ) { $image = get_the_post_thumbnail_url( $id, 'large' ); }
      ?>
        <article class="chs-show-row chs-show-image-<?php echo esc_attr( $image_side ); ?>">
          <div class="chs-show-body">
            <?php if ( $start ) : ?><p class="chs-show-date"><?php echo esc_html( wp_date( 'D, M j, Y · g:i a', $start->getTimestamp(), wp_timezone() ) ); ?></p><?php endif; ?>
            <h3 class="chs-show-title"><a href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a></h3>
            <?php if ( $location ) : ?><p class="chs-show-location"><?php echo esc_html( $location ); ?></p><?php endif; ?>
            <div class="chs-show-description"><?php echo wp_kses_post( get_post_field( 'post_content', $id ) ); ?></div>
            <div class="chs-show-actions">
              <a class="chs-show-details" href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php echo $archive ? 'Show details' : 'Details'; ?></a>
              <?php if ( $ticket && ! $archive ) : ?><a class="chs-show-ticket" href="<?php echo esc_url( $ticket ); ?>" target="_blank" rel="noopener noreferrer">Get Tickets</a><?php endif; ?>
            </div>
          </div>
          <?php if ( $image ) : ?><a class="chs-show-image-link" href="<?php echo esc_url( get_permalink( $id ) ); ?>"><img class="chs-show-image" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( get_the_title( $id ) ); ?>" loading="lazy" /></a><?php else : ?><div class="chs-show-image-placeholder" aria-hidden="true"></div><?php endif; ?>
        </article>
      <?php endwhile; wp_reset_postdata(); else : ?>
        <p class="chs-no-shows"><?php echo $archive ? 'Past shows will appear here.' : 'New shows are coming soon. Check back for upcoming performances!'; ?></p>
      <?php endif; ?>
    </div>
    <style>
      .chs-show-grid{display:flex;flex-direction:column;gap:32px;margin:1.5rem 0}
      .chs-show-grid .chs-show-row{display:flex;align-items:stretch;width:100%;background:transparent;box-sizing:border-box}
      .chs-show-grid .chs-show-body{flex:0 0 50%;box-sizing:border-box;padding:48px;display:flex;flex-direction:column;align-items:flex-start;justify-content:center;color:#fff!important}
      .chs-show-grid .chs-show-image-link,.chs-show-grid .chs-show-image-placeholder{position:relative;display:block;flex:0 0 50%;min-height:420px;aspect-ratio:.78;background:#f1f1f1;overflow:hidden}
      .chs-show-grid .chs-show-image-left .chs-show-image-link,.chs-show-grid .chs-show-image-left .chs-show-image-placeholder{order:-1}
      .chs-show-grid .chs-show-image{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block}
      .chs-show-grid .chs-show-date{font-size:1.2rem;font-weight:800;line-height:1.4;letter-spacing:.02em;margin:0 0 16px;color:#ffbb70!important}
      .chs-show-grid .chs-show-title{margin:0 0 16px;font-size:2.2rem;font-weight:700;line-height:1.2;color:#fff!important}
      .chs-show-grid .chs-show-title a{color:#fff!important;text-decoration:none}
      .chs-show-grid .chs-show-location{margin:0 0 20px;font-size:1.15rem;font-weight:700;line-height:1.5;color:#fff!important;opacity:1}
      .chs-show-grid .chs-show-description,.chs-show-grid .chs-show-description p{font-size:1rem;line-height:1.6;color:#f4f5fb!important}
      .chs-show-grid .chs-show-actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:24px;padding-top:0}
      .chs-show-grid .chs-show-ticket{display:inline-block;background:#8a3b68;color:#fff!important;padding:12px 20px;border-radius:7px;text-decoration:none;font-weight:700}
      .chs-show-grid .chs-show-details{color:#fff!important;text-decoration:underline}
      .chs-no-shows{grid-column:1/-1}
      @media (max-width:700px){.chs-show-grid .chs-show-row{flex-direction:column}.chs-show-grid .chs-show-body,.chs-show-grid .chs-show-image-link,.chs-show-grid .chs-show-image-placeholder{flex-basis:auto;width:100%}.chs-show-grid .chs-show-image-link,.chs-show-grid .chs-show-image-placeholder{order:-1;min-height:0;aspect-ratio:.78}.chs-show-grid .chs-show-body{padding:28px 24px}}
    </style>
    <?php
    return ob_get_clean();
}
