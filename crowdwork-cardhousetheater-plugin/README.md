# CrowdWork CardHouseTheater Plugin

A standalone WordPress plugin that gives CrowdWork classes and show cards an autumnal editorial presentation using expressive Bodoni Moda display lettering, handwritten Caveat accents, clear DM Sans labels, and Card House Theater's plum, warm paper, coral, and gold palette. Web fonts load from Google Fonts, with local serif and sans-serif fallbacks.

## Install

1. In WordPress, go to **Plugins → Add New Plugin → Upload Plugin**.
2. Upload `crowdwork-cardhousetheater-plugin.zip`.
3. Activate **CrowdWork CardHouseTheater Plugin**.
4. Replace the CrowdWork script embed in the page with:

   ```text
   [crowdwork_calendar]
   ```

The shortcode renders the CrowdWork classes calendar with the theatre-inspired heading and scoped styles. The earlier `[crowdwork_cardhouse_classes]` shortcode remains available as a backwards-compatible alias.

For the CrowdWork shows cards embed, replace the script with:

```text
[crowdwork_cards]
```

This shortcode renders the shows in CrowdWork's cards view, wrapped in the same visual style. The earlier `[crowdwork_cardhouse_shows]` shortcode remains available as an alias.

## How it works

- Loads CrowdWork's existing `https://crowdwork.com/embed.js?v=6` embed with theatre `cardhousetheater`, type `classes`, and view `calendar`.
- Provides `[crowdwork_calendar]` for the classes calendar; `[crowdwork_cardhouse_classes]` remains as a backwards-compatible alias.
- Provides `[crowdwork_cards]` for the shows cards view (`data-type="shows"` and `data-view="cards"`); `[crowdwork_cardhouse_shows]` remains as a backwards-compatible alias.
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
