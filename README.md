# ActInformationBar - Shopware Plugin

A Shopware 6 plugin that displays a customizable, time-controlled information bar at the top of your storefront with dynamic messaging capabilities.

## Features

- Multiple information bars per sales channel, each with its own schedule
- A bar without a period runs permanently and is superseded by scheduled bars inside their window
- Messages maintainable per sales channel **and** language
- Dedicated admin pages under Content → Information bar: a list, a detail page per bar and a
  defaults page
- Time-controlled display with start and end dates, evaluated against a configurable timezone
- Rotating messages with smooth fade transitions (one line per rotation)
- Centered layout that keeps the button in place while the lines change
- Customizable appearance per bar (colors, fonts, padding)
- Colours are applied as CSS custom properties, so `theme:compile` is no longer needed after a
  colour change
- The page cache follows the bar: saving a bar refreshes the cached storefront pages, and a
  scheduled task refreshes them once a start or end date has passed
- Optional call-to-action button
- Full-width or container layout options
- Responsive design
- AJAX request awareness (no display on AJAX calls)
- Accessibility: hidden lines are excluded from screen readers, new-window links are announced as such, and the fade respects reduced-motion preferences
- Bars can be duplicated
- Optional countdown block per bar (target date, own text once expired, number/label colour,
  Theme or locally hosted Montserrat font)
- Inline formatting in messages (`<b>`, `<i>`, ...) and emojis
- "Create event bar" button in the countdown card that copies a bar as a starting point for a time-limited event
- Live preview of the bar on the detail page
- ACL privileges (`act_information_bar` with viewer/editor/creator/deleter roles)
- Multi-language admin interface (German & English)
- Compatible with Shopware 6.7

## Requirements

- Shopware 6.7 or higher
- PHP 8.4 or higher

## Installation

### Via Composer (recommended)

```bash
composer require actualizer/information-bar
bin/console plugin:refresh
bin/console plugin:install --activate ActInformationBar
bin/console cache:clear
```

### Manual

1. Download or clone this plugin into your `custom/plugins/` directory
2. Install and activate the plugin via CLI:
   ```bash
   bin/console plugin:refresh
   bin/console plugin:install --activate ActInformationBar
   bin/console cache:clear
   ```

### Updating

Nothing has to be re-entered. On update, all settings that used to live in the plugin
configuration (appearance, timing, texts) are copied into the bar itself. The storefront keeps
showing the same bar with the same texts, colors and schedule; everything is now maintained
under Content → Information bar, and the plugin configuration only shows an info banner.

If a shop overrode one of the plugin's Sass variables (`$act-information-bar-*`) in its own
theme, that override is superseded by the value configured on the bar from 1.5.0 onward. The
variables remain in place as fallbacks for empty values.

## Configuration

Everything is maintained under Content → Information bar. The plugin configuration
(Settings → System → Plugins → Actualize: Time-controlled Information Bar) only shows an info
banner; it no longer holds any settings.

1. Go to Admin Panel → Content → Information bar
2. The list shows every bar across all sales channels; use "Add bar" to create a new one or open
   an existing one
3. Pick the language via the language switch on the detail page to edit that language's texts
4. Edit the fields and save

Each bar belongs to one sales channel (or "All Sales Channels") and can have its own schedule
and appearance. A bar without a start and end date runs permanently; a bar with a schedule
supersedes the permanent bar of the same sales channel while its window is active and hands
display back to it afterwards. Among overlapping scheduled bars, the one with the later start
date wins. A bar can be duplicated from the list's context menu; the copy's start and end dates
are cleared so it does not immediately compete with the original.

When saving a bar, the detail page warns (without blocking the save) if the end date is not
after the start date, or if a start or end date falls on a day when the configured timezone's
clock changes.

### Configuration Options

#### Bars

- **Name**: Internal name shown in the list, not displayed in the storefront
- **Active**: Enable/disable the bar
- **Sales Channel**: The sales channel the bar applies to, or "All Sales Channels"
- **Start Date / End Date**: The bar's schedule, evaluated against the configured timezone (see
  Defaults below). Leave both empty for permanent display
- **Message**: The text to display, one line per rotation. A small set of formatting tags and
  emojis is allowed (see Formatting below)
- **Button Text**, **Button URL**, **Button Title**: Optional call-to-action button; it is only
  rendered when both text and URL are set
- **Full Width**: Display bar across full browser width or within container
- **Display Duration**: How long each message iteration displays (in seconds)
- **Text Color**, **Background Color**: Bar colors (hex values)
- **Padding Top**, **Padding Bottom**: Padding in pixels
- **Font Size**: Text size in pixels
- **Show Button**: Enable/disable the CTA button
- **Button Target**: Link target (_self, _blank). With `_blank` the link carries `rel="noopener noreferrer"` and a visually hidden "opens in a new window" hint for screen readers
- **Button Text Color**, **Button Text Color (Hover)**: Button text color
- **Button Border Color**, **Button Border Color (Hover)**: Button border color
- **Button Border Width**: Border thickness in pixels
- **Button corner radius**: CSS length, e.g. `4px`, or `999px` for a pill shape. Empty = the
  theme's button radius
- **Button Background Color**, **Button Background Color (Hover)**: Button background color
- **Countdown target**: Date and time the countdown counts down to, evaluated in the configured
  timezone. Empty = no countdown (see Countdown below)
- **Text after expiry**: Shown instead of the countdown once the target is reached. Empty = the
  countdown block disappears. Same formatting rules as the message
- **Digit colour**, **Label colour**: Colors of the digits and of the labels and colons. Empty = text color (labels slightly dimmed)
- **Countdown font**: Theme font or Montserrat (shipped with the plugin, no external requests)

Texts are translatable per language: if a language has no text of its own, the plugin falls
back to the default language of the sales channel, then to the system default language, and
only then to the global record — in that order. An empty message in one language therefore
never makes the bar disappear. The message decides: the first record and language with a
non-empty message supplies all translatable texts (message, expired text, button text, URL and
title). Filling in only a button text while leaving the message of that language empty means
the whole language is skipped and the button text is never shown. Appearance, schedule and the
countdown target are not translatable — they belong to the bar, not to a language.

#### Formatting

Messages and the expired text may contain the tags `<b> <strong> <i> <em> <u> <s> <mark>`,
without attributes. Everything else is removed on save; the admin warns before saving. A lone
`<` or `>` stays as it is. Emojis (including combined ones) are allowed. Button text and
button title are plain text: tags are stripped there.

Known limit: size notations such as "<S> bis <XL>" look like tags (`<S>` becomes strikethrough,
`<XL>` is removed). Write "(S) bis (XL)" instead.

#### Countdown

The countdown fields live in their own card on the detail page. The countdown block sits next
to the rotating text and shows days, hours, minutes and seconds (the days cell is hidden below
one day). The text stays mandatory: a bar without a message is not shown, even with a
countdown target.

The card warns if the target lies before the start date, if no end date is set, or if the
message is empty. Set the end date to the countdown target, otherwise the bar comes back with
an expired countdown once a later bar ends.

"Create event bar" at the end of the countdown card copies the saved bar into a new one that
starts at the countdown target, with no end date and no countdown target. It is disabled while
there are unsaved changes. Set the end date in the copy right away — without it the event bar
runs indefinitely.

The live preview on the detail page is an approximation of the storefront rendering; the
final look depends on the theme.

#### Defaults

The defaults page (Content → Information bar → Defaults) holds the values that are
copied into a bar when it is created — the same appearance fields listed above (including
button corner radius, digit and label colour and countdown font, but not the countdown target
or the text after expiry), plus the timezone. It does not affect existing bars; changing a default only changes what a newly
created bar starts out with.

The timezone setting is the shop's timezone for evaluating start and end dates, independent of
the sales channel and independent of any individual admin user's profile timezone. If left
unset, it falls back to the server timezone (PHP's `date.timezone` setting, then the `TZ`
environment variable), and only uses `UTC` if neither yields a valid identifier.

## How it works

### Display Logic
1. **Time Control**: Bar displays only within configured date range
2. **AJAX Awareness**: Automatically hidden during AJAX requests
3. **Page Integration**: Injected at the top of the page body
4. **Extension System**: Uses Shopware's extension system for clean integration

### Message Rotation
- **One line at a time**: Each line of the message is shown in turn, looping continuously
- **Smooth Transitions**: CSS opacity fade between lines, skipped when the visitor prefers reduced motion
- **Stable Layout**: All lines are rendered stacked in a single grid cell, so the bar keeps the width of the longest line and the button does not move while the lines change
- **Responsive Behavior**: The button wraps below the message on narrow viewports

### Integration Points
- Subscribes to `GenericPageLoadedEvent`
- Adds extension data to page object
- Template extends base storefront layout
- CSS and JavaScript loaded conditionally

## Technical Details

### Events Used
- `GenericPageLoadedEvent` - Injects information bar data into page and tags the page with the
  HTTP cache tag `act-information-bar`
- `EntityWrittenContainerEvent` - Invalidates that tag when a bar or one of its texts is written
  or deleted
- Scheduled task `act_information_bar.schedule_cache` (every 5 minutes) - Invalidates the tag
  when the start or end date of an active bar has passed since the previous run

### Data Storage
- `act_information_bar` with translations in `act_information_bar_translation`, one row per bar
  plus its texts per language
- Appearance and schedule are columns on the bar itself, not `system_config`

### Template Structure
- Extends `@Storefront/storefront/base.html.twig`
- Includes custom template for bar rendering
- Conditional display based on configuration

### JavaScript Features
- Renders all message lines once and only toggles their visibility via CSS classes
- Hidden lines carry `aria-hidden` so screen readers announce the visible line only
- Continuous loop with configurable display duration

## Development

### Building/Testing
After making changes:
```bash
bin/console cache:clear
bin/console theme:compile
```

### Debugging
- Check browser console for JavaScript errors
- Verify date settings on the bar
- Test with different message lengths
- Monitor animation performance in DevTools

## Usage Examples

The examples list message and button texts together with the appearance values they go with.
All of it is maintained on a bar under Content → Information bar.

### Simple Announcement
```
Message: "Free shipping on orders over €50!"
Full Width: Yes
Background: #28a745
Text Color: #ffffff
```

### Time-Limited Sale
```
Message: "Black Friday Sale - 30% off everything!"
Start Date: 2026-11-27 00:00:00
End Date: 2026-11-27 23:59:59
Show Button: Yes
Button Text: "Shop Now"
Button URL: /sale
```

### Maintenance Notice
```
Message: "Scheduled maintenance on Sunday 2am-4am"
Background: #ffc107
Text Color: #000000
Start Date: 2026-02-27 00:00:00
End Date: 2026-03-01 04:00:00
```

### Black Friday with Countdown
Two bars: the first counts down to the start of the sale, the second runs during the sale.

Bar 1 (countdown):
```
Message: "<b>Black Friday</b> starts in"
Countdown Target: 2026-11-27 00:00:00
Text after expiry: "Now live!"
Start Date: 2026-11-20 00:00:00
End Date: 2026-11-27 00:00:00
Show Button: Yes
Button Text: "Wishlist"
Button URL: /wishlist
```

Bar 2 (event), created with "Create event bar" in the countdown card of bar 1:
```
Message: "<b>Black Friday</b> - <mark>30 %</mark> off everything!"
Start Date: 2026-11-27 00:00:00
End Date: 2026-11-30 23:59:59
Show Button: Yes
Button Text: "Shop Now"
Button URL: /sale
```
Bar 2 starts later than bar 1, so it takes over at the countdown target. "Now live!" bridges
the time until the page cache is refreshed (at most 5 minutes).

### Multiple Rotating Lines
```
Message:
Free shipping on orders over €50!
New autumn collection available now
Customer service: Mon-Fri 9am-5pm
Display Duration: 4
Show Button: Yes
Button Text: "Learn more"
```
Each line is shown in turn. The bar reserves the width of the longest line, so the
button keeps its position while the lines rotate.

## Styling Tips

### High Contrast
- Dark backgrounds with white text for maximum visibility
- Use hex colors for precise control
- Test on different devices for readability

### Display Duration
- Short lines (1-3 seconds)
- Longer lines (5-10 seconds so they can be read completely)
- Adjust based on message importance

### Responsive Design
- Bar automatically adjusts to screen width
- Text remains readable on mobile devices
- Button scales appropriately

## Compatibility

- **Shopware Version**: 6.7
- **PHP Version**: 8.4+
- **Browser Support**: All modern browsers (Chrome, Firefox, Safari, Edge)
- **Mobile**: Fully responsive and touch-friendly
- **Theme Compatibility**: Works with all Shopware themes

## Known Limitations

- **Appearance is not translatable.** Every language of a bar therefore shares one appearance;
  only the texts differ. The appearance fields on the detail page are unaffected by the
  language switch for that reason.
- Messages allow only a few inline formatting tags (see Formatting); other markup is removed on save
- Very long messages may impact performance
- Date/time evaluated against the timezone configured on the defaults page; if unset, it falls
  back to the server timezone and only uses `UTC` as a last resort
- **Start and end dates take effect with a delay of up to about five minutes on a live shop.**
  Cached pages only notice a passed start or end date when the scheduled task
  `act_information_bar.schedule_cache` runs, so the shop's scheduled tasks and message queue
  have to be running. Changes saved on a bar are visible right away.
- **The bar is part of every storefront page.** Saving a bar, or a passing start or end date,
  therefore refreshes the cached pages of all sales channels at once.
- **Daylight saving transitions need care.** On a spring forward day one wall clock hour does
  not exist (02:00–02:59 in `Europe/Berlin`), and a start or end time inside it is silently
  moved forward by an hour. A window spanning exactly that missing hour collapses to zero
  length and the bar never appears. On an autumn day the same hour exists twice and is
  ambiguous. The detail page warns at save time when a date falls on such a day, and separately
  when the end date is not after the start date; both warnings are advisory and saving
  proceeds. Schedule around a transition by picking a time outside the affected hour.

## Changelog

### 1.7.1

- README: the configuration options now list the countdown fields and the button corner radius,
  and the Black Friday example shows the countdown bar and the event bar.

### 1.7.0

- Countdown, inline formatting, emojis, button corner radius, event bars and live preview (see above).
- Message lines are now rendered in Twig. Check template overrides of
  `act-information-bar.html.twig`.
- On update, a one-time cleanup removes all tags that are not allowed from stored texts (the text
  in between stays); allowed tags in old data now render as formatting. A fresh install runs the
  same cleanup.
- Theme developers: `data-message` is removed from the bar and the JS no longer builds the lines.
  An old override of `act-information-bar.html.twig` that relies on it shows an empty bar; adapt
  it to `actInformationBar.lines`. `actInformationBar.message` is the cleaned database value and
  may contain the inline tags (it is not escaped for output; use `lines`).
- With a countdown the bar becomes taller. Check customer themes with a sticky header.
- List "Duplicate" now really resets the schedule of the copy.
- Smart-bar buttons now have the standard admin size.

## Support

For issues and feature requests, please use the GitHub issue tracker.

## License

Licensed under MIT, except the Montserrat font files in `src/Resources/public/fonts/`, which are licensed under the SIL Open Font License 1.1 (see `OFL.txt` there). See the [LICENSE](LICENSE) file for the MIT text.

## Credits

Developed by Actualize

---

Made with ❤️ for the Shopware Community
