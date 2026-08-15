# ActInformationBar - Shopware Plugin

A Shopware 6 plugin that displays a customizable, time-controlled information bar at the top of your storefront with dynamic messaging capabilities.

## Features

- Messages maintainable per sales channel **and** language
- Dedicated admin page under Content → Information bar with a language and sales channel switch
- Time-controlled display with start and end dates
- Rotating messages with smooth fade transitions (one line per rotation)
- Centered layout that keeps the button in place while the lines change
- Customizable appearance (colors, fonts, padding)
- Optional call-to-action button
- Full-width or container layout options
- Responsive design
- AJAX request awareness (no display on AJAX calls)
- Accessibility: hidden lines are excluded from screen readers, new-window links are announced as such, and the fade respects reduced-motion preferences
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

### Updating from 1.3.x

Nothing has to be re-entered. On update the plugin creates its own tables and copies the
existing message and button texts out of the plugin configuration into them. The storefront
keeps showing the same texts; they are now editable under Content → Information bar.

## Configuration

Settings live in two places, because texts are translatable and appearance is not.

### Texts — Content → Information bar

1. Go to Admin Panel → Content → Information bar
2. Pick the sales channel (or leave it on "All Sales Channels") and the language
3. Edit the texts and save

This page maintains message, button text, button URL and button title. Each combination of
sales channel and language holds its own texts.

The page also carries the appearance settings of the selected sales channel. They are the very
same values as in the plugin configuration — editing them in either place writes to the same
setting. Because the page has a language switch, it is worth being explicit: switching the
language does **not** change these values, and a banner on the card says so. They sit here in
preparation for the planned 1.5.0, which is to allow several bars per sales channel; appearance
then becomes a property of the individual bar and belongs next to its texts. Until then, use
whichever of the two places you prefer.

### Appearance — Extensions → Config

1. Go to Admin Panel → Settings → System → Plugins
2. Find "Actualize: Time-controlled Information Bar" and click on the three dots
3. Click "Config" to access plugin settings

Colors, spacing, timing and the button target are stored per sales channel and are shared by
all its languages.

### Configuration Options

#### Texts (Content → Information bar)
- **Message**: The text to display, one line per rotation (plain text - HTML is not rendered)
- **Button Text**: Text displayed on button
- **Button URL**: Link destination
- **Button Title**: Tooltip text on hover

If a language has no text of its own, the plugin falls back to the default language of the
sales channel, then to the system default language, and only then to the global record — in
that order. An empty message in one language therefore never makes the bar disappear.

The message decides: the first record and language with a non-empty message supplies all four
texts. Filling in only a button text while leaving the message of that language empty means
the whole language is skipped and the button text is never shown.

The remaining groups belong to the plugin configuration and are shared by all languages of
the sales channel.

#### General Settings
- **Active**: Enable/disable the information bar
- **Full Width**: Display bar across full browser width or within container

#### Message Settings
- **Display Duration**: How long each message iteration displays (in seconds)
- **Font Size**: Text size in pixels (default: 14px)

#### Timing Control
- **Start Date**: When to start showing the bar (optional)
- **End Date**: When to stop showing the bar (optional)
- Leave both empty for permanent display

#### Styling
- **Text Color**: Message text color (hex value)
- **Background Color**: Bar background color (hex value)
- **Padding Top**: Top padding in pixels (default: 15px)
- **Padding Bottom**: Bottom padding in pixels (default: 15px)

#### Call-to-Action Button
- **Show Button**: Enable/disable CTA button (the button is only rendered when both text and URL are set — both are maintained under Content → Information bar)
- **Button Target**: Link target (_self, _blank). With `_blank` the link carries `rel="noopener noreferrer"` and a visually hidden "opens in a new window" hint for screen readers
- **Button Text Color**: Button text color
- **Button Text Color (Hover)**: Button text color on hover
- **Button Border Color**: Button border color
- **Button Border Color (Hover)**: Button border color on hover
- **Button Border Width**: Border thickness in pixels
- **Button Background Color**: Button background color
- **Button Background Color (Hover)**: Button background color on hover

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
- Texts: `act_information_bar` with translations in `act_information_bar_translation`, one
  record per sales channel plus a global record used as fallback
- Appearance: Shopware's `system_config`, per sales channel

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
- Verify date settings in plugin configuration
- Test with different message lengths
- Monitor animation performance in DevTools

## Usage Examples

The examples list message and button texts together with the appearance values they go with.
Texts are maintained under Content → Information bar, everything else in the plugin configuration.

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

- One information bar per sales channel
- **Appearance is not translatable.** Colors, spacing and timing are compiled into the theme
  stylesheet, which Shopware builds per theme and sales channel, not per language. Every
  language of a sales channel therefore shares one appearance; only the texts differ. The
  appearance fields on the Content → Information bar page are unaffected by its language
  switch for that reason. They are placed there for the planned 1.5.0, where several bars per
  sales channel are to get their own appearance.
- Messages are plain text; HTML markup is not rendered
- Very long messages may impact performance
- Date/time based on server timezone

## Support

For issues and feature requests, please use the GitHub issue tracker.

## License

This plugin is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Credits

Developed by Actualize

---

Made with ❤️ for the Shopware Community
