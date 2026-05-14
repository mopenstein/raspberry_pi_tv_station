# Station Management Card System
This directory houses the modular Manage Card architecture for the TV Station server dashboard. It allows for the dynamic injection of management tools, system monitors, and configuration interfaces without modifying core system files.

## How it Works
The system utilizes a PHP loader that scans this directory using glob(). It automatically identifies, validates, and renders any class that adheres to the established protocol.

### Requirements for Auto-Loading
Filename Consistency: The filename must match the class name exactly (e.g., ThemeManager.php must contain class ThemeManager).
Interface Implementation: Every class must implement the ManageCard interface.
Automatic Injection: If the class defines setSettings() or setMysqli(), the loader will automatically pass the global configuration or database object to the card before rendering.

## The Interface
All modules must implement the ManageCard interface to ensure the dashboard can correctly parse and display the card data.
```
interface ManageCard {
    public function name();   // Returns (string): The title displayed in the card header.
    public function links();  // Returns (array): A list of navigation links/buttons for the footer.
    public function html();   // Returns (string): The main body content or form logic.
}
```

### Supported Dynamic Hooks
 
```
setSettings($json)
Injection
Receives the global settings.json array.

setMysqli($db)
Injection
Receives the active $mysqli database connection.

priority()
Optional
Returns an int. Higher numbers sort to the top (Default: 0).
```

## Card Template
Copy this boilerplate to create a new management module:
``` 
<?php

class NewTool implements ManageCard {
    private $settings;

    public function setSettings($json) {
        $this->settings = $json;
    }

    public function priority() {
        return 50; 
    }

    public function name() {
        return "My Custom Tool";
    }

    public function html() {
        return "<p>Content for the management card goes here.</p>";
    }

    public function links() {
        return [
            [
                "label" => "Open Tool",
                "url"   => "/tools/my-tool.php",
                "target"=> "_self"
            ]
        ];
    }
}
```
