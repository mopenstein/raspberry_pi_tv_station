# Templates

This directory contains the layout and theme files for the web interface. The system uses a decoupled architecture where `index.php` acts as a controller, fetching hardware and broadcast data before passing it to the active template.

## Architecture

The system utilizes a **View Object** pattern. All data required for rendering is aggregated into a single associative array named `$View`, allowing for rapid theme swapping by modifying the `theme` value in the `settings.json` configuration.

### The $View Object Structure
Templates consume the following data subsets:
* **`$View['sys']`**: Real-time Raspberry Pi hardware metrics including temperature, CPU load, uptime, and disk space.
* **`$View['nav']`**: Navigation links for date traversal and channel selection dropdowns.
* **`$View['data']['shows']`**: Log of recently played content including duration, show type, and dynamic hex colors.
* **`$View['data']['commercials']`**: Detailed advertisement logs featuring category-specific emojis and folder path metadata.
* **`$View['data']['messages']`**: Grouped system logs and error messages with repeat detection logic.
* **`$View['data']['manage']`**: Dynamic content for modular management cards loaded from the `/manage` directory.

## Usage
To implement a new theme:
1. Create a new `.php` file in this directory.
2. Iterate through the `$View['data']` subsets using standard PHP loops.
3. Update `settings.json` to reflect the new filename (e.g., `"theme": "classic"`).
