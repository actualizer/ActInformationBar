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
- Optional call-to-action button
- Full-width or container layout options
- Responsive design
- AJAX request awareness (no display on AJAX calls)
- Accessibility: hidden lines are excluded from screen readers, new-window links are announced as such, and the fade respects reduced-motion preferences
- Bars can be duplicated
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
- **Message**: The text to display, one line per rotation (plain text - HTML is not rendered)
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
- **Button Background Color**, **Button Background Color (Hover)**: Button background color

Texts are translatable per language: if a language has no text of its own, the plugin falls
back to the default language of the sales channel, then to the system default language, and
only then to the global record — in that order. An empty message in one language therefore
never makes the bar disappear. The message decides: the first record and language with a
non-empty message supplies all four texts. Filling in only a button text while leaving the
message of that language empty means the whole language is skipped and the button text is
never shown. Appearance and schedule are not translatable — they belong to the bar, not to a
language.

#### Defaults

The defaults page (Content → Information bar → Defaults) holds the values that are
copied into a bar when it is created — the same appearance fields listed above, plus the
timezone. It does not affect existing bars; changing a default only changes what a newly
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
- `GenericPageLoadedEvent` - Injects information bar data into page

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
Start Date: 2024-11-29 00:00:00
End Date: 2024-11-29 23:59:59
Show Button: Yes
Button Text: "Shop Now"
Button URL: /sale
```

### Maintenance Notice
```
Message: "Scheduled maintenance on Sunday 2am-4am"
Background: #ffc107
Text Color: #000000
Start Date: 2024-03-01 00:00:00
End Date: 2024-03-03 04:00:00
```

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
- Messages are plain text; HTML markup is not rendered
- Very long messages may impact performance
- Date/time evaluated against the timezone configured on the defaults page; if unset, it falls
  back to the server timezone and only uses `UTC` as a last resort
- **A colour change is not instant on a live shop.** The bar sits in the storefront's main
  document cache, not in the separately cached header fragment, and saving a bar does not
  invalidate it. The new colour appears once the HTTP cache TTL expires, or right away after
  `bin/console cache:pool:clear cache.http`. No `theme:compile` is needed either way — that is
  the point of serving colours as CSS custom properties.
- **Daylight saving transitions need care.** On a spring forward day one wall clock hour does
  not exist (02:00–02:59 in `Europe/Berlin`), and a start or end time inside it is silently
  moved forward by an hour. A window spanning exactly that missing hour collapses to zero
  length and the bar never appears. On an autumn day the same hour exists twice and is
  ambiguous. The detail page warns at save time when a date falls on such a day, and separately
  when the end date is not after the start date; both warnings are advisory and saving
  proceeds. Schedule around a transition by picking a time outside the affected hour.

## Support

For issues and feature requests, please use the GitHub issue tracker.

## License

This plugin is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Credits

Developed by Actualize

---

Made with ❤️ for the Shopware Community
