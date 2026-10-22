# Pi Station WebUI

A lightweight, decoupled PHP management interface for the station. This system provides real-time hardware monitoring, broadcast logging, and modular station control through a responsive dashboard.

## System Architecture

The WebUI follows a **Data-Driven View Object** pattern, ensuring that system logic and hardware interfacing are completely separated from the visual presentation.

### 1. The Core Controller (`index.php`)
The controller acts as the central engine of the interface:
* **Data Aggregation**: Collects hardware metrics directly from the Linux filesystem (temperature, load, disk space).
* **Database Interfacing**: Fetches and processes "Played" logs and "Commercial" archives from MySQL/MariaDB.
* **View Preparation**: Packages all retrieved data into a single `$View` array, which is then injected into the active template.
* **Modular Loading**: Dynamically discovers and initializes management modules from the `/manage` directory.

### 2. Templating & Themes (`/templates`)
The UI is swappable and defined in the `settings.json` file. Each template consumes the `$View` object to render the dashboard.

### 3. Management Cards (`/manage`)
Station functionality is extended through modular PHP classes that implement the `ManageCard` interface.
* **Plug-and-Play**: Adding a new `.php` class to the `/manage` folder automatically adds a new control tile to the "Manage" tab.

