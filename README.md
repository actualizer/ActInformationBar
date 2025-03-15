# ActInformationBar

Ein Shopware 6 Plugin zur Anzeige einer zeitgesteuerten Informationsleiste mit anpassbarer Nachricht.

## Funktionen

- Anzeige einer Informationsleiste auf allen Seiten des Storefronts
- Anpassbare Nachricht in der Informationsleiste
- Festlegung von Start- und Enddatum/Uhrzeit für die Anzeige der Informationsleiste
- Anpassbare Textfarbe und Hintergrundfarbe
- Einstellbare Innenabstände (Padding) für optimale Darstellung auf allen Geräten
- Anpassbare Schriftgröße
- Optionaler Button mit anpassbaren Stileigenschaften
- Responsives Design für optimale Darstellung auf Desktop und Mobilgeräten

## Installation

1. Klonen Sie dieses Repository in Ihr `custom/plugins`-Verzeichnis.
2. Führen Sie `composer install` im Plugin-Verzeichnis aus, um die erforderlichen Abhängigkeiten zu installieren.
3. Führen Sie `bin/console plugin:refresh` im Shopware-Stammverzeichnis aus, um das Plugin zu registrieren.
4. Führen Sie `bin/console plugin:install --activate ActInformationBar` aus, um das Plugin zu installieren und zu aktivieren.

## Konfiguration

Nach der Installation und Aktivierung des Plugins können Sie es im Shopware-Backend unter "Einstellungen" > "Plugins" > "ActInformationBar" konfigurieren.

Folgende Konfigurationsoptionen sind verfügbar:

### Allgemeine Einstellungen
- **Aktiv**: Aktiviert oder deaktiviert die Informationsleiste.
- **Volle Breite**: Legt fest, ob die Informationsleiste die volle Breite des Bildschirms einnehmen soll.
- **Nachricht**: Die anzuzeigende Nachricht in der Informationsleiste (mehrere Zeilen möglich).
- **Anzeigedauer pro Zeile**: Die Dauer in Sekunden, für die jede Zeile angezeigt wird.
- **Startdatum**: Das Datum und die Uhrzeit, ab wann die Informationsleiste angezeigt werden soll.
- **Enddatum**: Das Datum und die Uhrzeit, bis wann die Informationsleiste angezeigt werden soll.

### Styling-Optionen
- **Textfarbe**: Die Farbe des Textes in der Informationsleiste.
- **Hintergrundfarbe**: Die Hintergrundfarbe der Informationsleiste.
- **Innenabstand oben**: Der obere Innenabstand der Informationsleiste in Pixeln (5-50px).
- **Innenabstand unten**: Der untere Innenabstand der Informationsleiste in Pixeln (5-50px).
- **Schriftgröße**: Die Größe des Textes in der Informationsleiste in Pixeln (10-30px).

### Button-Einstellungen
- **Button anzeigen**: Aktiviert oder deaktiviert die Anzeige eines Buttons in der Informationsleiste.
- **Button Text**: Der Text, der auf dem Button angezeigt wird.
- **Button URL**: Die URL, zu der der Button führt.
- **Button Target**: Legt fest, ob der Link im selben Fenster oder in einem neuen Fenster geöffnet wird.
- **Button Title**: Der Title-Attributwert des Buttons (für Tooltip).
- **Button Textfarbe**: Die Farbe des Textes auf dem Button.
- **Button Textfarbe (Hover)**: Die Farbe des Textes auf dem Button beim Überfahren mit der Maus.
- **Button Rahmenfarbe**: Die Farbe des Rahmens um den Button.
- **Button Rahmenfarbe (Hover)**: Die Farbe des Rahmens um den Button beim Überfahren mit der Maus.
- **Button Rahmendicke**: Die Dicke des Rahmens um den Button in Pixeln (1-4px).
- **Button Hintergrundfarbe**: Die Hintergrundfarbe des Buttons.
- **Button Hintergrundfarbe (Hover)**: Die Hintergrundfarbe des Buttons beim Überfahren mit der Maus.

## Hinweis zur Theme-Kompilierung

Nach jeder Änderung an den Farben oder Styling-Optionen muss das Theme neu kompiliert werden, damit die Änderungen wirksam werden. Verwenden Sie dazu den folgenden Befehl im Shopware-Stammverzeichnis:

```bash
bin/console theme:compile
```

## Verwendung

Nach der Konfiguration des Plugins wird die Informationsleiste automatisch auf allen Seiten des Storefronts während des konfigurierten Zeitraums angezeigt.

## Mitwirken

Wenn Sie zur Entwicklung dieses Plugins beitragen möchten, können Sie gerne Pull Requests einreichen oder Issues auf dem GitHub-Repository öffnen.

## Lizenz

Dieses Plugin wird unter der [MIT-Lizenz](https://opensource.org/licenses/MIT) veröffentlicht.

## Autor

ActInformationBar wurde entwickelt von [Ralph Geldmacher](https://www.actualize.de).
