<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function chs_additional_calendar_config() {
    return array(
        'education' => chs_education_calendar_config(),
        'community' => chs_community_calendar_config(),
    );
}

add_action( 'init', 'chs_register_additional_calendar_post_types' );
add_action( 'init', 'chs_additional_calendars_schedule_events', 20 );
function chs_register_additional_calendar_post_types() {
    foreach ( chs_additional_calendar_config() as $config ) {
        register_post_type( $config['post_type'], array(
            'labels' => array(
                'name' => $config['name'],
                'singular_name' => $config['singular'],
                'all_items' => $config['name'],
            ),
            'public' => true,
            'has_archive' => false,
            'rewrite' => array( 'slug' => $config['slug'], 'with_front' => false ),
            'menu_icon' => $config['icon'],
            'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
            'show_in_rest' => true,
        ) );
    }
}

function chs_additional_calendars_schedule_events() {
    foreach ( chs_additional_calendar_config() as $config ) {
        if ( ! wp_next_scheduled( $config['cron'] ) ) {
            wp_schedule_event( time() + 120, 'hourly', $config['cron'] );
        }
    }
}

function chs_additional_calendars_activate() {
    chs_register_additional_calendar_post_types();
    chs_additional_calendars_schedule_events();
}

function chs_additional_calendars_deactivate() {
    foreach ( chs_additional_calendar_config() as $config ) {
        $timestamp = wp_next_scheduled( $config['cron'] );
        if ( $timestamp ) { wp_unschedule_event( $timestamp, $config['cron'] ); }
    }
}

add_action( 'admin_init', 'chs_register_additional_calendar_settings' );
function chs_register_additional_calendar_settings() {
    register_setting( 'chs_additional_calendar_settings', 'chs_workshops_ics_url', array(
        'type' => 'string',
        'sanitize_callback' => 'chs_sanitize_workshops_ics_url',
        'default' => '',
    ) );
    register_setting( 'chs_additional_calendar_settings', 'chs_community_ics_url', array(
        'type' => 'string',
        'sanitize_callback' => 'chs_sanitize_community_ics_url',
        'default' => '',
    ) );
}

function chs_sanitize_workshops_ics_url( $value ) {
    return chs_sanitize_additional_ics_url( $value, 'chs_workshops_ics_url' );
}

function chs_sanitize_community_ics_url( $value ) {
    return chs_sanitize_additional_ics_url( $value, 'chs_community_ics_url' );
}

function chs_sanitize_additional_ics_url( $value, $option ) {
    $value = trim( (string) $value );
    if ( $value === '' ) { return ''; }
    if ( ! wp_http_validate_url( $value ) || strtolower( (string) wp_parse_url( $value, PHP_URL_SCHEME ) ) !== 'https' ) {
        add_settings_error( $option, 'chs_bad_url', 'Enter a valid HTTPS iCalendar (.ics) feed URL.' );
        return get_option( $option, '' );
    }
    return esc_url_raw( $value );
}

add_action( 'admin_menu', 'chs_additional_calendars_admin_menu' );
function chs_additional_calendars_admin_menu() {
    add_options_page( 'Additional Show Calendars', 'Additional Show Calendars', 'manage_options', 'chs-additional-calendars', 'chs_additional_calendars_settings_page' );
}

function chs_additional_calendars_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $notice = '';
    $result = null;
    if ( isset( $_POST['chs_sync_additional_calendar'] ) && check_admin_referer( 'chs_sync_additional_calendar_action', 'chs_sync_additional_calendar_nonce' ) ) {
        $calendar = sanitize_key( wp_unslash( $_POST['chs_sync_additional_calendar'] ) );
        if ( isset( chs_additional_calendar_config()[ $calendar ] ) ) {
            $result = chs_sync_additional_calendar( $calendar );
            $notice = is_wp_error( $result ) ? $result->get_error_message() : sprintf( 'Sync complete. %d event(s) imported or updated.', (int) $result );
        } else {
            $notice = 'Unknown calendar selected.';
        }
    }
    ?>
    <div class="wrap">
      <h1>Additional Show Calendars</h1>
      <?php settings_errors(); ?>
      <?php if ( $notice !== '' ) : ?><div class="notice <?php echo is_wp_error( $result ) || $result === null ? 'notice-error' : 'notice-success'; ?>"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
      <p>Set a separate public Google Calendar iCalendar feed for each calendar. Feeds sync hourly; use Sync Now to import immediately.</p>
      <form method="post" action="options.php">
        <?php settings_fields( 'chs_additional_calendar_settings' ); ?>
        <table class="form-table">
          <tr>
            <th scope="row"><label for="chs_workshops_ics_url">Education ICS feed URL</label></th>
            <td><input name="chs_workshops_ics_url" id="chs_workshops_ics_url" type="url" class="regular-text" value="<?php echo esc_attr( get_option( 'chs_workshops_ics_url', '' ) ); ?>" placeholder="https://calendar.google.com/calendar/ical/..." /></td>
          </tr>
          <tr>
            <th scope="row"><label for="chs_community_ics_url">Community Events ICS feed URL</label></th>
            <td><input name="chs_community_ics_url" id="chs_community_ics_url" type="url" class="regular-text" value="<?php echo esc_attr( get_option( 'chs_community_ics_url', '' ) ); ?>" placeholder="https://calendar.google.com/calendar/ical/..." /></td>
          </tr>
        </table>
        <?php submit_button( 'Save Calendar Feeds' ); ?>
      </form>
      <h2>Sync calendars</h2>
      <?php foreach ( chs_additional_calendar_config() as $calendar => $config ) : ?>
        <form method="post" style="display:inline-block;margin:0 12px 12px 0">
          <?php wp_nonce_field( 'chs_sync_additional_calendar_action', 'chs_sync_additional_calendar_nonce' ); ?>
          <input type="hidden" name="chs_sync_additional_calendar" value="<?php echo esc_attr( $calendar ); ?>" />
          <?php submit_button( 'Sync ' . $config['name'], 'secondary', 'submit', false ); ?>
        </form>
      <?php endforeach; ?>
      <h2>Shortcodes</h2>
      <p><code>[card_house_education]</code> and <code>[card_house_education_carousel]</code></p>
      <p>The previous <code>[card_house_workshops]</code> and <code>[card_house_workshops_carousel]</code> shortcodes remain available as aliases.</p>
      <p><code>[card_house_community_events]</code> and <code>[card_house_community_events_carousel]</code></p>
      <p>Use the <code>limit</code> attribute to set the number of events or distinct titles, for example <code>[card_house_education_carousel limit="6"]</code>.</p>
    </div>
    <?php
}

foreach ( chs_additional_calendar_config() as $calendar => $config ) {
    add_action( $config['cron'], function () use ( $calendar ) {
        chs_sync_additional_calendar( $calendar );
    } );
}

function chs_sync_additional_calendar( $calendar ) {
    $config = chs_additional_calendar_config();
    if ( ! isset( $config[ $calendar ] ) ) {
        return new WP_Error( 'chs_unknown_calendar', 'Unknown calendar selected.' );
    }
    $config = $config[ $calendar ];
    $url = trim( (string) get_option( $config['option'], '' ) );
    if ( $url === '' ) {
        return new WP_Error( 'chs_no_feed', 'Save the ' . $config['name'] . ' ICS feed URL first.' );
    }
    $response = wp_safe_remote_get( $url, array( 'timeout' => 20, 'redirection' => 3, 'headers' => array( 'Accept' => 'text/calendar, text/plain;q=0.9, */*;q=0.8' ) ) );
    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'chs_fetch_failed', 'Could not fetch the calendar: ' . $response->get_error_message() );
    }
    $status = (int) wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    if ( $status < 200 || $status >= 300 || stripos( $body, 'BEGIN:VCALENDAR' ) === false ) {
        return new WP_Error( 'chs_invalid_feed', 'The feed did not return a valid iCalendar file. Check that the public ICS URL is correct.' );
    }
    $events = chs_parse_ics( $body );
    if ( empty( $events ) ) {
        return new WP_Error( 'chs_no_events', 'No events were found in the calendar feed. Check the calendar has events and try again.' );
    }

    $count = 0;
    foreach ( $events as $event ) {
        if ( empty( $event['uid'] ) || empty( $event['start'] ) || empty( $event['end'] ) || empty( $event['summary'] ) ) { continue; }
        $existing = get_posts( array(
            'post_type' => $config['post_type'],
            'post_status' => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => '_chs_uid',
            'meta_value' => $event['uid'],
        ) );
        $description = chs_clean_description( $event['description'] );
        $ticket_url = ! empty( $event['url'] ) ? esc_url_raw( $event['url'] ) : '';
        $image_url = ! empty( $event['image_url'] ) ? esc_url_raw( $event['image_url'] ) : '';
        $description_ticket_url = chs_description_url( $event['description'], 'TICKETS_URL' );
        $description_image_url = chs_description_url( $event['description'], 'IMAGE_URL' );
        if ( $description_ticket_url ) { $ticket_url = $description_ticket_url; }
        if ( $description_image_url ) { $image_url = $description_image_url; }
        $post_data = array(
            'post_type' => $config['post_type'],
            'post_status' => 'publish',
            'post_title' => sanitize_text_field( $event['summary'] ),
            'post_content' => wp_kses_post( nl2br( esc_html( $description ) ) ),
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
    update_option( 'chs_last_sync_' . $calendar, current_time( 'mysql' ) );
    return $count;
}

function chs_render_additional_calendar( $calendar, $atts, $carousel ) {
    $config = chs_additional_calendar_config()[ $calendar ];
    $shortcode = $calendar === 'community' ? 'card_house_community_events' : 'card_house_education';
    if ( $carousel ) { $shortcode .= '_carousel'; }
    $atts = shortcode_atts( array( 'limit' => 12 ), $atts, $shortcode );
    $limit = max( 1, min( 100, absint( $atts['limit'] ) ) );
    $now = current_datetime();
    $query = new WP_Query( array(
        'post_type' => $config['post_type'],
        'post_status' => 'publish',
        'posts_per_page' => $carousel ? -1 : $limit,
        'meta_key' => '_chs_start',
        'orderby' => 'meta_value',
        'order' => 'ASC',
        'meta_query' => array(
            array( 'key' => '_chs_end', 'value' => $now->format( 'Y-m-d H:i:s' ), 'compare' => '>', 'type' => 'DATETIME' ),
        ),
        'no_found_rows' => true,
    ) );
    $events = array();
    foreach ( $query->posts as $post ) {
        $id = $post->ID;
        $start_raw = get_post_meta( $id, '_chs_start', true );
        $start = $start_raw ? DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $start_raw, wp_timezone() ) : false;
        if ( ! $start ) { continue; }
        $image = get_post_meta( $id, '_chs_image_url', true );
        if ( ! $image && has_post_thumbnail( $id ) ) { $image = get_the_post_thumbnail_url( $id, 'large' ); }
        $event = array(
            'id' => $id,
            'title' => get_the_title( $id ),
            'image' => $image,
            'ticket' => get_post_meta( $id, '_chs_ticket_url', true ),
            'location' => get_post_meta( $id, '_chs_location', true ),
            'content' => get_post_field( 'post_content', $id ),
            'start' => $start,
        );
        if ( ! $carousel ) {
            $events[] = $event;
            continue;
        }
        $key = strtolower( trim( preg_replace( '/\s+/', ' ', $event['title'] ) ) );
        if ( ! isset( $events[ $key ] ) ) {
            $events[ $key ] = $event;
            $events[ $key ]['dates'] = array();
        }
        $date_key = $start->format( 'Y-m-d H:i:s' );
        $events[ $key ]['dates'][ $date_key ] = $start;
        if ( ! $events[ $key ]['image'] && $image ) { $events[ $key ]['image'] = $image; }
        if ( ! $events[ $key ]['ticket'] && $event['ticket'] ) { $events[ $key ]['ticket'] = $event['ticket']; }
        if ( ! $events[ $key ]['location'] && $event['location'] ) { $events[ $key ]['location'] = $event['location']; }
    }
    $events = array_slice( array_values( $events ), 0, $limit );
    $instance_id = wp_unique_id( $carousel ? 'chs-calendar-carousel-' : 'chs-calendar-list-' );
    $row_index = 0;
    ob_start();
    ?>
    <div id="<?php echo esc_attr( $instance_id ); ?>" class="<?php echo $carousel ? 'chs-calendar-carousel' : 'chs-calendar-list'; ?>">
      <?php if ( ! empty( $events ) ) : ?>
        <?php if ( $carousel ) : ?>
          <h2 class="chs-calendar-heading"><?php echo esc_html( $config['name'] ); ?></h2>
          <div class="chs-calendar-viewport"><div class="chs-calendar-track">
        <?php endif; ?>
        <?php foreach ( $events as $event ) :
          $date_list = $carousel ? $event['dates'] : array( $event['start']->format( 'Y-m-d H:i:s' ) => $event['start'] );
          $image_side = $row_index % 2 === 0 ? 'right' : 'left';
          if ( ! $carousel ) { $row_index++; }
          $first_date = reset( $date_list );
        ?>
          <article class="<?php echo $carousel ? 'chs-calendar-slide' : 'chs-calendar-row chs-calendar-image-' . esc_attr( $image_side ); ?>">
            <?php if ( $event['image'] ) : ?><a class="chs-calendar-image-link" href="<?php echo esc_url( get_permalink( $event['id'] ) ); ?>"><img src="<?php echo esc_url( $event['image'] ); ?>" alt="<?php echo esc_attr( $event['title'] ); ?>" loading="lazy" /></a>
            <?php else : ?><div class="chs-calendar-image-link chs-calendar-image-placeholder" aria-hidden="true"></div><?php endif; ?>
            <div class="chs-calendar-body">
              <?php if ( $carousel ) : ?><p class="chs-calendar-month"><?php echo esc_html( wp_date( 'M', $first_date->getTimestamp(), wp_timezone() ) ); ?></p><?php endif; ?>
              <?php if ( ! $carousel ) : ?><p class="chs-calendar-date"><?php endif; ?>
              <?php if ( ! $carousel ) : ?><?php echo esc_html( wp_date( 'D, M j, Y · g:i a', $first_date->getTimestamp(), wp_timezone() ) ); ?></p><?php endif; ?>
              <h3 class="chs-calendar-title"><a href="<?php echo esc_url( get_permalink( $event['id'] ) ); ?>"><?php echo esc_html( $event['title'] ); ?></a></h3>
              <?php if ( $carousel ) : ?><div class="chs-calendar-dates"><?php foreach ( $date_list as $date ) : ?><span><?php echo esc_html( wp_date( 'D, M j · g:i a', $date->getTimestamp(), wp_timezone() ) ); ?></span><?php endforeach; ?></div>
              <?php else : ?>
                <?php if ( $event['location'] ) : ?><p class="chs-calendar-location"><?php echo esc_html( $event['location'] ); ?></p><?php endif; ?>
                <div class="chs-calendar-description"><?php echo wp_kses_post( $event['content'] ); ?></div>
              <?php endif; ?>
              <div class="chs-calendar-actions">
                <a class="chs-calendar-details" href="<?php echo esc_url( get_permalink( $event['id'] ) ); ?>">Details</a>
                <?php if ( $event['ticket'] ) : ?><a class="chs-calendar-ticket" href="<?php echo esc_url( $event['ticket'] ); ?>" target="_blank" rel="noopener noreferrer">Get Tickets</a><?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
        <?php if ( $carousel ) : ?>
          </div></div>
          <?php if ( count( $events ) > 1 ) : ?><div class="chs-calendar-controls"><button type="button" class="chs-calendar-previous" aria-label="Previous events">&lsaquo;</button><div class="chs-calendar-dots"></div><button type="button" class="chs-calendar-next" aria-label="Next events">&rsaquo;</button></div><?php endif; ?>
        <?php endif; ?>
      <?php else : ?>
        <p><?php echo esc_html( 'No upcoming ' . strtolower( $config['name'] ) . ' are scheduled.' ); ?></p>
      <?php endif; ?>
    </div>
    <style>
      #<?php echo esc_attr( $instance_id ); ?>{margin:1.5rem 0}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-row{display:flex;align-items:stretch;width:100%;margin-bottom:32px}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-image-link{position:relative;display:block;flex:0 0 50%;min-height:320px;aspect-ratio:3/2;overflow:hidden}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-image-link img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-image-left .chs-calendar-image-link{order:-1}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-body{flex:0 0 50%;box-sizing:border-box;padding:32px 40px;color:#fff}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-date{font-size:1.2rem;font-weight:800;color:#ffbb70}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-title{font-size:1.8rem;line-height:1.2}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-title a{color:inherit;text-decoration:none}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-location{font-size:1.1rem;font-weight:700}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-description{line-height:1.6}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:18px}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-ticket{display:inline-block;background:#8a3b68;color:#fff;padding:10px 16px;border-radius:7px;text-decoration:none;font-weight:700}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-details{color:#fff}
      #<?php echo esc_attr( $instance_id ); ?>.chs-calendar-carousel{position:relative;padding:24px 56px;background:#d3e2ca;color:#171b16}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-heading{color:#528a43;text-transform:uppercase}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-viewport{overflow-x:auto;scrollbar-width:none;scroll-snap-type:x mandatory;scroll-behavior:smooth}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-viewport::-webkit-scrollbar{display:none}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-track{display:flex;gap:24px}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-slide{flex:0 0 calc((100% - 48px)/3);min-width:0;padding:0 20px 8px;border-left:1px solid #67905e;scroll-snap-align:start}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-slide .chs-calendar-image-link{width:100%;min-height:0;aspect-ratio:3/4;margin-bottom:12px;background:#c1d3b9}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-month{font-size:2rem;font-weight:800;text-transform:uppercase}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-dates{display:flex;flex-direction:column;gap:4px}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-controls{display:flex;justify-content:center;align-items:center;gap:12px;margin-top:12px}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-controls[hidden]{display:none}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-controls button{border:0;border-radius:50%;width:36px;height:36px;background:#286a25;color:#fff;font-size:1.5rem;cursor:pointer}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-dot{width:10px;height:10px;padding:0;border:0;border-radius:50%;background:#9dbb91}
      #<?php echo esc_attr( $instance_id ); ?> .chs-calendar-dot[aria-current="true"]{background:#286a25}
      #<?php echo esc_attr( $instance_id ); ?>.chs-calendar-carousel .chs-calendar-body{padding:0;color:#171b16}
      #<?php echo esc_attr( $instance_id ); ?>.chs-calendar-carousel .chs-calendar-title{font-size:1.15rem}
      #<?php echo esc_attr( $instance_id ); ?>.chs-calendar-carousel .chs-calendar-title a{color:#171b16}
      #<?php echo esc_attr( $instance_id ); ?>.chs-calendar-carousel .chs-calendar-actions{margin-top:14px}
      #<?php echo esc_attr( $instance_id ); ?>.chs-calendar-carousel .chs-calendar-details{color:#171b16}
      #<?php echo esc_attr( $instance_id ); ?>.chs-calendar-carousel .chs-calendar-ticket{width:100%;box-sizing:border-box;text-align:center;background:#65a844;border-radius:999px}
      @media(max-width:900px){#<?php echo esc_attr( $instance_id ); ?> .chs-calendar-slide{flex-basis:calc((100% - 24px)/2)}}
      @media(max-width:650px){#<?php echo esc_attr( $instance_id ); ?> .chs-calendar-row{flex-direction:column}#<?php echo esc_attr( $instance_id ); ?> .chs-calendar-row .chs-calendar-image-link{order:-1;width:100%;flex-basis:auto}#<?php echo esc_attr( $instance_id ); ?> .chs-calendar-body{flex-basis:auto;padding:24px 12px}#<?php echo esc_attr( $instance_id ); ?> .chs-calendar-slide{flex-basis:100%}}
    </style>
    <?php if ( $carousel && count( $events ) > 1 ) : ?>
    <script>
      (function(){var root=document.getElementById('<?php echo esc_js( $instance_id ); ?>');if(!root){return;}var viewport=root.querySelector('.chs-calendar-viewport'),track=root.querySelector('.chs-calendar-track'),cards=Array.prototype.slice.call(track.children),dots=root.querySelector('.chs-calendar-dots'),controls=root.querySelector('.chs-calendar-controls'),prev=root.querySelector('.chs-calendar-previous'),next=root.querySelector('.chs-calendar-next'),perPage=1;function update(){var page=Math.min(dots.children.length-1,Math.round(viewport.scrollLeft/viewport.clientWidth));Array.prototype.forEach.call(dots.children,function(dot,index){dot.setAttribute('aria-current',index===page?'true':'false');});prev.disabled=viewport.scrollLeft<=2;next.disabled=viewport.scrollLeft+viewport.clientWidth>=viewport.scrollWidth-2;}function setup(){var gap=parseFloat(window.getComputedStyle(track).columnGap)||0;perPage=Math.max(1,Math.floor((viewport.clientWidth+gap)/(cards[0].getBoundingClientRect().width+gap)));dots.textContent='';var pageCount=Math.ceil(cards.length/perPage);for(var i=0;i<pageCount;i++){var dot=document.createElement('button');dot.type='button';dot.className='chs-calendar-dot';dot.setAttribute('aria-label','Show group '+(i+1));dot.addEventListener('click',function(index){return function(){viewport.scrollTo({left:Math.min(cards[index*perPage].offsetLeft-track.offsetLeft,viewport.scrollWidth-viewport.clientWidth),behavior:'smooth'});};}(i));dots.appendChild(dot);}controls.hidden=viewport.scrollWidth<=viewport.clientWidth+2;update();}prev.addEventListener('click',function(){viewport.scrollBy({left:-viewport.clientWidth,behavior:'smooth'});});next.addEventListener('click',function(){viewport.scrollBy({left:viewport.clientWidth,behavior:'smooth'});});viewport.addEventListener('scroll',update,{passive:true});window.addEventListener('resize',setup);setup();})();
    </script>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}
