# CrowdWork CardHouseTheater Plugin

A standalone WordPress plugin that gives CrowdWork classes and show cards an autumnal editorial presentation using expressive Bodoni Moda display lettering, handwritten Caveat accents, clear DM Sans labels, and Card House Theater's plum, warm paper, coral, and gold palette. Web fonts load from Google Fonts, with local serif and sans-serif fallbacks.

## Install

1. In WordPress, go to **Plugins → Add New Plugin → Upload Plugin**.
2. Upload `crowdwork-cardhousetheater-plugin.zip`.
3. Activate **CrowdWork CardHouseTheater Plugin**.
4. Choose a shortcode from the list below and add it to the page using a Shortcode block or your page builder's shortcode element.

## Shortcodes

| Shortcode | Displays |
|---|---|
| `[crowdwork_calendar]` | CrowdWork classes calendar with the theatre-inspired heading and styles. |
| `[crowdwork_cards]` | CrowdWork shows in cards view with matching styles. |

Use only one CrowdWork shortcode per page because CrowdWork's embed uses page-wide element IDs.

### Shortcode options

Both shortcodes accept the following optional attributes. Defaults preserve each shortcode's standard content type and view.

| Attribute | Values | Default / notes |
|---|---|---|
| `type` | `shows`, `classes`, `all` | `all` for both shortcodes. |
| `view` | `calendar`, `cards` | Calendar: `calendar`; cards: `cards`. |
| `category` | Exact public tag name | Omit to show all tags. Tag names are case-sensitive. |
| `category_bar` | `true`, `false` | `false`. A category bar lets visitors filter by public tag; when enabled, it takes precedence over `category`. |
| `start_date` | `YYYY-MM-DD` | Omit to start from the current date/month. Applies to calendar views. |
| `start_dow` | `0` through `6` | Omit for Monday; `0` is Sunday and `6` is Saturday. Applies to calendar views. |
| `days` | `1`, `3`, `7` | Omit for no rolling day limit. |
| `all_dates` | `true`, `false` | `false`. In cards view, `true` shows every upcoming date for recurring events. |
| `links` | `on`, `off` | `on`. `off` prevents the widget from updating the page URL as visitors filter or open events. |

For example, show all event types in a calendar beginning in May 2026, with weeks starting Sunday and a seven-day rolling range:

```text
[crowdwork_calendar type="all" view="calendar" start_date="2026-05-01" start_dow="0" days="7"]
```

Or show all event types as cards filtered to an exact public tag, including all recurring dates:

```text
[crowdwork_cards category="Beginner" all_dates="true"]
```

For a visitor-selectable tag filter, use `category_bar="true"` instead of `category`.

## How it works

- Loads CrowdWork's existing `https://crowdwork.com/embed.js?v=6` embed with theatre `cardhousetheater`, type `classes`, and view `calendar`.
- Provides `[crowdwork_calendar]` for the classes calendar and `[crowdwork_cards]` for the shows cards view. Both accept the documented widget options.
- Styles the generated FullCalendar interface, event details modal, and surrounding section without changing CrowdWork event data, booking links, or checkout.
- Styles the generated show cards and modal, and loads the plugin stylesheet only on pages containing one of the plugin's shortcodes; embed selectors are scoped so other calendars and pages are unaffected.

The WordPress page needs to allow JavaScript in the shortcode output. Use a standard Shortcode block or the page builder's shortcode element.

## Optional GitHub deployment

The repository includes a separate GitHub Actions workflow for deploying this plugin to GreenGeeks over SSH. It copies only this plugin's files to `wp-content/plugins/crowdwork-cardhousetheater-plugin`; it does not activate the plugin or edit WordPress page content. The workflow is disabled until `WP_CROWDWORK_DEPLOY_ENABLED` is set to `true`.

Before enabling it, add these repository variables in **Settings → Secrets and variables → Actions → Variables**:

| Variable | Value |
|---|---|
| `WP_CROWDWORK_SSH_HOST` | SSH hostname shown by GreenGeeks |
| `WP_CROWDWORK_SSH_PORT` | SSH port; use `22` if GreenGeeks specifies no other port |
| `WP_CROWDWORK_SSH_USER` | SSH username |
| `WP_CROWDWORK_SSH_KNOWN_HOSTS` | Verified SSH host-key line(s); confirm the fingerprint with GreenGeeks |
| `WP_CROWDWORK_WORDPRESS_ROOT` | Absolute server path containing `wp-load.php` |
| `WP_CROWDWORK_DEPLOY_ENABLED` | Set to `true` only after testing the SSH configuration |

Add a dedicated SSH private key as the `WP_CROWDWORK_SSH_KEY` repository secret and authorize its public key for the GreenGeeks account. Never store the private key in the repository or send it in chat. Keep SSH host-key checking enabled.

After setup, select **Actions → Deploy CrowdWork CardHouseTheater plugin → Run workflow** to test a deployment. Later changes under this plugin's folder on `main` deploy automatically. To show a styled embed on the site, activate the plugin and replace the old script embed with `[crowdwork_calendar]` or `[crowdwork_cards]` in the desired page.

## Limitations

- Use only one CrowdWork shortcode per page because CrowdWork's embed uses page-wide element IDs.
- CrowdWork's embed script and calendar data remain hosted by CrowdWork; the plugin cannot change their availability or privacy behavior.
- No local build tools or dependencies are required.
