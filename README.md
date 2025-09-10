# ActInformationBar - Shopware Plugin

A Shopware 6 plugin that displays a customizable, time-controlled information bar at the top of your storefront with dynamic messaging capabilities.

## Version Compatibility

| Plugin Version | Shopware Version | Branch | Download |
|----------------|------------------|---------|-----------|
| 1.1.x | 6.7.1+ | main | [Latest Release](https://github.com/actualizer/ActInformationBar/releases/latest) |
| 1.0.x | 6.5.0 - 6.6.x | [shopware-6.6](https://github.com/actualizer/ActInformationBar/tree/shopware-6.6) | [v1.0.3](https://github.com/actualizer/ActInformationBar/releases/tag/v1.0.3) |

## Features

- ✅ Time-controlled display with start and end dates
- ✅ Animated scrolling text for dynamic messaging
- ✅ Customizable appearance (colors, fonts, padding)
- ✅ Optional call-to-action button
- ✅ Full-width or container layout options
- ✅ Responsive design
- ✅ AJAX request awareness (no display on AJAX calls)
- ✅ Multi-language support (German & English)
- ✅ Compatible with Shopware 6.7.1+

## Requirements

- Shopware 6.7.1 or higher
- PHP 8.2 or higher (8.3 compatible)

## Installation

1. Download or clone this plugin into your `custom/plugins/` directory
2. Install and activate the plugin via CLI:
   ```bash
   bin/console plugin:refresh
   bin/console plugin:install --activate ActInformationBar
   bin/console cache:clear
   ```

## Configuration

1. Go to Admin Panel → Settings → System → Plugins
2. Find "Actualize: Time-controlled Information Bar" and click on the three dots
3. Click "Config" to access plugin settings

### Configuration Options

#### General Settings
- **Active**: Enable/disable the information bar
- **Full Width**: Display bar across full browser width or within container

#### Message Settings
- **Message**: The text to display (supports HTML for links)
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
- **Show Button**: Enable/disable CTA button
- **Button Text**: Text displayed on button
- **Button URL**: Link destination
- **Button Target**: Link target (_self, _blank, etc.)
- **Button Title**: Tooltip text on hover
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

### Animation Features
- **Scrolling Text**: Messages scroll horizontally when longer than container
- **Smooth Transitions**: CSS-based animations for smooth movement
- **Responsive Behavior**: Adapts to different screen sizes
- **Performance Optimized**: Uses requestAnimationFrame for smooth animations

### Integration Points
- Subscribes to `GenericPageLoadedEvent`
- Adds extension data to page object
- Template extends base storefront layout
- CSS and JavaScript loaded conditionally

## Technical Details

### Events Used
- `GenericPageLoadedEvent` - Injects information bar data into page

### Template Structure
- Extends `@Storefront/storefront/base.html.twig`
- Includes custom template for bar rendering
- Conditional display based on configuration

### JavaScript Features
- Dynamic text animation calculation
- Viewport-aware animation speed
- Automatic restart on completion
- Touch-friendly on mobile devices

## File Structure

```
ActInformationBar/
├── composer.json
├── README.md
├── LICENSE
├── src/
│   ├── ActInformationBar.php
│   ├── Resources/
│   │   ├── config/
│   │   │   ├── config.xml
│   │   │   └── services.xml
│   │   ├── snippet/
│   │   │   ├── de_DE/
│   │   │   │   └── config.de-DE.json
│   │   │   └── en_GB/
│   │   │       └── config.en-GB.json
│   │   ├── public/
│   │   │   └── storefront/
│   │   │       ├── css/
│   │   │       │   └── information-bar.css
│   │   │       └── js/
│   │   │           └── information-bar.js
│   │   └── views/
│   │       └── storefront/
│   │           ├── base.html.twig
│   │           └── layout/
│   │               └── header/
│   │                   └── act-information-bar.html.twig
│   └── Subscriber/
│       └── InformationBarSubscriber.php
```

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

### Multiple Languages with Link
```
Message: "New products available! <a href='/new'>View collection</a>"
Font Size: 16
Padding Top: 20
Padding Bottom: 20
```

## Styling Tips

### High Contrast
- Dark backgrounds with white text for maximum visibility
- Use hex colors for precise control
- Test on different devices for readability

### Animation Speed
- Shorter messages (1-3 seconds duration)
- Longer messages (5-10 seconds for full scroll)
- Adjust based on message importance

### Responsive Design
- Bar automatically adjusts to screen width
- Text remains readable on mobile devices
- Button scales appropriately

## Compatibility

- **Shopware Version**: 6.7.1+
- **PHP Version**: 8.2+ (8.3 compatible)
- **Browser Support**: All modern browsers (Chrome, Firefox, Safari, Edge)
- **Mobile**: Fully responsive and touch-friendly
- **Theme Compatibility**: Works with all Shopware themes

## Known Limitations

- One information bar per shop
- HTML in messages should be used carefully
- Very long messages may impact performance
- Date/time based on server timezone

## Support

For issues and feature requests, please use the GitHub issue tracker.

## License

This plugin is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

Made with ❤️ for the Shopware Community
