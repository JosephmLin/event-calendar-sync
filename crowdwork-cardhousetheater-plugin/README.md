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

## Limitations

- The shortcode is intended to be used once per page because CrowdWork's embed uses page-wide element IDs.
- CrowdWork's embed script and calendar data remain hosted by CrowdWork; the plugin cannot change their availability or privacy behavior.
- No local build tools or dependencies are required.
