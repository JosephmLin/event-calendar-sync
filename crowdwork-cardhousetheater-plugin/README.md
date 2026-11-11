# CrowdWork CardHouseTheater Plugin

A standalone WordPress plugin that gives the CrowdWork Classes calendar an autumnal editorial presentation using expressive Bodoni Moda display lettering, handwritten Caveat accents, clear DM Sans labels, and Card House Theater's plum, warm paper, coral, and gold palette. Web fonts load from Google Fonts, with local serif and sans-serif fallbacks.

## Install

1. In WordPress, go to **Plugins → Add New Plugin → Upload Plugin**.
2. Upload `crowdwork-cardhousetheater-plugin.zip`.
3. Activate **CrowdWork CardHouseTheater Plugin**.
4. Replace the CrowdWork script embed in the page with:

   ```text
   [crowdwork_cardhouse_classes]
   ```

The shortcode renders the CrowdWork classes calendar with the theatre-inspired heading and scoped styles.

## How it works

- Loads CrowdWork's existing `https://crowdwork.com/embed.js?v=6` embed with theatre `cardhousetheater`, type `classes`, and view `calendar`.
- Styles the generated FullCalendar interface, event details modal, and surrounding section without changing CrowdWork event data, booking links, or checkout.
- Loads the plugin stylesheet on the front end; all selectors are scoped to this shortcode's wrapper so other calendars and pages are unaffected.

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

After setup, select **Actions → Deploy CrowdWork CardHouseTheater plugin → Run workflow** to test a deployment. Later changes under this plugin's folder on `main` deploy automatically. To show the styled calendar on the site, activate the plugin and replace the old script embed with `[crowdwork_cardhouse_classes]` in the desired page.

## Limitations

- The shortcode is intended to be used once per page because CrowdWork's embed uses page-wide element IDs.
- CrowdWork's embed script and calendar data remain hosted by CrowdWork; the plugin cannot change their availability or privacy behavior.
- No local build tools or dependencies are required.
