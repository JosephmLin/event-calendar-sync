# Card House Google Calendar Events

WordPress plugin for importing Card House Theater events from public Google Calendar iCalendar (ICS) feeds and displaying them with shortcodes.

## Requirements

- WordPress 5.8 or later (uses `wp_unique_id()`).
- PHP 7.0 or later.
- Public HTTPS ICS feed URLs for each calendar you want to sync.
- PHP CLI is useful for local linting, but is not needed to run the plugin in WordPress.

There are no Composer, npm, or build-step dependencies. This is a standalone WordPress plugin.

## Plugin files

- `card-house-google-calendar-events.php` — plugin entry point; describes the plugin to WordPress, loads the calendar modules, and coordinates activation/deactivation.
- `card-house-shows.php` — Shows-only module; registers the Shows post type, ICS feed settings/sync/parser, and row/archive/carousel shortcodes.
- `card-house-calendar-tools.php` — shared calendar infrastructure for additional calendars: post type registration, settings/sync page, ICS import, and shared row/carousel rendering.
- `card-house-education.php` — Education feed configuration and its two shortcode registrations. The underlying post type, feed option, cron hook, and URL slug retain their prior workshop identifiers to preserve existing imported data and links.
- `card-house-community-events.php` — Community Events feed configuration and its two shortcode registrations.

- `tools/sync-calendars.php` — CLI-only deployment helper that immediately syncs every calendar with a saved feed URL.

Keep all five plugin PHP modules in the same plugin directory. The entry point loads each calendar-specific module before the shared tools because the tools combine their calendar configuration.

## Install

1. Put the `card-house-shows` directory (containing `card-house-google-calendar-events.php` and the four companion PHP files above) into `wp-content/plugins/`.
2. Or make a ZIP whose root contains the `card-house-shows/` directory, then install it from **Plugins → Add New Plugin → Upload Plugin**.
3. Activate **Card House Google Calendar Events**.
4. Configure the feed URLs in WordPress settings and run the initial sync.

Do not omit the companion PHP files when copying or packaging the plugin; the main file requires them.

## Automatic deployment from GitHub

The included GitHub Actions workflow deploys PHP source changes pushed to `main` to `wp-content/plugins/card-house-shows` over SSH, then immediately syncs each calendar that has a saved feed URL. It does not activate the plugin or change its feed settings. The plugin must already be active, its feeds must be configured, and the GreenGeeks account must provide SSH and PHP CLI access.

Before enabling deployment, configure these repository variables under **Settings → Secrets and variables → Actions → Variables**:

| Variable | Value |
|---|---|
| `WP_DEPLOY_SSH_HOST` | SSH hostname from GreenGeeks |
| `WP_DEPLOY_SSH_PORT` | SSH port; use `22` if the host specifies no other port |
| `WP_DEPLOY_SSH_USER` | GreenGeeks SSH username |
| `WP_DEPLOY_SSH_KNOWN_HOSTS` | Verified SSH host-key line(s) for that host and port |
| `WP_DEPLOY_WORDPRESS_ROOT` | Absolute server path to the WordPress directory containing `wp-load.php` |
| `WP_DEPLOY_ENABLED` | Set to `true` only after all SSH settings below are ready |

Add `WP_DEPLOY_SSH_KEY` under the same page’s **Secrets** tab. It must contain the private key for a public key authorized on the GreenGeeks account. Do not put private keys in the repository or workflow variables.

Verify the host key with GreenGeeks before adding it to `WP_DEPLOY_SSH_KNOWN_HOSTS`; do not disable SSH host-key checking. The SSH account needs write permission to the plugin directory. The workflow checks that PHP CLI and the WordPress root are available before copying files. After setup, use **Actions → Deploy WordPress plugin → Run workflow** for the first deployment; later PHP changes pushed to `main` deploy automatically.

The post-deployment sync reports errors in the Actions run and exits unsuccessfully if a configured feed cannot be fetched/imported. Unconfigured feeds are skipped. The helper is CLI-only and exits without loading WordPress when requested over HTTP.

## Configure calendars

### Shows

Go to **Settings → Card House Shows**:

1. Enter the calendar's public address in iCal format (ICS), not its secret address.
2. Save the feed URL.
3. Click **Sync Now** to import immediately.

### Education and community events

Go to **Settings → Additional Show Calendars**:

1. Enter the public HTTPS ICS URL for Education and/or Community Events.
2. Save the feed URLs.
3. Use the individual **Sync Education** or **Sync Community Events** button for an immediate import.

Each feed syncs hourly through WordPress Cron. WP-Cron runs when the site receives requests, so an infrequently visited site may run scheduled syncs later than the exact hour. Sync buttons require the URLs to be saved first.

The additional calendars use separate post types (`chs_workshop` and `chs_community_event`) and separate event UID lookups. Each calendar can contain an event with the same UID without colliding with another calendar. The Education calendar retains the `chs_workshop` post type, `chs_workshops_ics_url` option, existing rewrite slug, and hourly cron hook for compatibility; only its user-facing name and new shortcode names are Education.

## Shortcodes

### Shows

| Shortcode | Output |
|---|---|
| `[card_house_shows]` | Upcoming shows in alternating image/text rows. |
| `[card_house_shows_archive]` | Past shows in rows. |
| `[card_house_shows_carousel]` | Upcoming show carousel; entries with the same normalized title are grouped into one slide with all their upcoming dates. |

### Education

| Shortcode | Output |
|---|---|
| `[card_house_education]` | Upcoming education events in alternating rows. |
| `[card_house_education_carousel]` | Upcoming carousel grouped by normalized event title, listing each date. |

The previous `[card_house_workshops]` and `[card_house_workshops_carousel]` shortcodes remain available as aliases.

### Community Events

| Shortcode | Output |
|---|---|
| `[card_house_community_events]` | Upcoming community events in alternating rows. |
| `[card_house_community_events_carousel]` | Upcoming carousel grouped by normalized event title, listing each date. |

All shortcodes accept `limit`, capped at 100. For row views, the limit is the number of events. For carousel views, it is the number of distinct titles/slides, after grouping.

Examples:

```text
[card_house_shows limit="12"]
[card_house_shows_archive limit="24"]
[card_house_shows_carousel limit="6"]
[card_house_education]
[card_house_education_carousel limit="6"]
[card_house_community_events]
[card_house_community_events_carousel limit="6"]
```

For backward compatibility, `[card_house_workshops]` and `[card_house_workshops_carousel]` remain aliases for the Education shortcodes.

Carousel rows are responsive: they show up to three items at wide widths, two at medium widths, and one on narrow screens. Carousel CSS and navigation JavaScript are emitted by the shortcode.

## ICS event data

The importer reads common iCalendar fields:

- `UID` — stable identifier used to update an existing imported event instead of creating a duplicate.
- `SUMMARY` — event title.
- `DTSTART` and `DTEND` — required start and end times. UTC, `TZID`, and date-only values are supported; stored display times use the WordPress site timezone.
- `LOCATION` — event location.
- `DESCRIPTION` — event content. Lines such as `TICKETS_URL: https://...` and `IMAGE_URL: https://...` are extracted as metadata and removed from the rendered description.
- `URL` and image `ATTACH` — optional ticket/event URL and image URL.

Events must have a UID, start, end, and summary to be imported. The feeds must respond successfully and contain a valid `BEGIN:VCALENDAR` document. Only HTTPS URLs are accepted by the feed settings.

Imported event metadata keys are `_chs_uid`, `_chs_start`, `_chs_end`, `_chs_ticket_url`, `_chs_image_url`, and `_chs_location`.

## Development and validation

The PHP source is the implementation source of truth. Preserve the separation between calendar-specific config/shortcode files and shared feed/rendering behavior:

- Keep Shows-only behavior in `card-house-shows.php`; keep Education and Community Events configuration/shortcodes in their corresponding calendar files.
- To add another calendar, create a calendar-specific file with its configuration and shortcode registrations, load it from `card-house-google-calendar-events.php`, then add its settings and post type to the shared calendar tools.
- Keep shared import logic aligned with `chs_parse_ics()`, `chs_ics_datetime()`, `chs_description_url()`, and `chs_clean_description()` in `card-house-shows.php`.
- Avoid duplicating import/render logic between calendar modules.
- Increment both the plugin header `Version` and `CHS_VERSION` in `card-house-google-calendar-events.php` when preparing a plugin release.
- Keep all companion files in the same folder and update this README when shortcodes, feed behavior, or packaging changes.

No automated test suite is currently included. With PHP CLI installed, lint every plugin PHP file from the plugin directory:

```powershell
php -l .\card-house-google-calendar-events.php
php -l .\card-house-shows.php
php -l .\card-house-calendar-tools.php
php -l .\card-house-education.php
php -l .\card-house-community-events.php
```

On Windows, install PHP CLI with WinGet if needed:

```powershell
winget install --id PHP.PHP.8.4 --exact
```

Open a new terminal after installation so the updated PATH is loaded.

## Data and lifecycle notes

- Deactivating the plugin unschedules its hourly sync events and flushes rewrite rules.
- Deactivation does not delete imported posts, settings, or event metadata.
- Re-activating the plugin schedules the syncs again.
- There is no uninstall routine that deletes stored data.
