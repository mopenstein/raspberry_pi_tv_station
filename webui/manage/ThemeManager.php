<?php

class ThemeManager implements ManageCard {
    private $name = 'UI Theme Manager';
    private $links = [];
    private $html = '';
    private $settings_file = "/home/pi/Desktop/settings.json";
    private $template_dir = "templates";
    private $updateProcessed = false;

    public function __construct() {
        $this->processUpdate();
        $this->loadThemeData();
    }

    private function loadThemeData() {
        // Scan for directories in the template folder
        $themes = array_filter(glob($this->template_dir . '/*.php'));
        if (empty($themes)) {
            $this->html = '<div style="color:red; margin:5px; padding:10px;">No themes found in the templates directory.</div>';
            return;
        }
        $this->html = '<div style="margin:5px; padding:10px;">';
        $this->html .= '<strong>Switch Web-UI Theme</strong><br><br>';
        if($this->updateProcessed) {
            $this->html .= '
            <div style="color:green; margin-bottom:10px;">Theme updated successfully! Page will automatically <a href="#" onclick="location.reload();">reload</a>.</div>
            <script>
                setTimeout(function() {
                    location.reload();
                }, 1000); // Reload after 3 seconds 
            </script>
            ';
        }
        $this->html .= '<form method="POST" style="display:flex; gap:10px; align-items:center;">';
        $this->html .= '<select name="new_theme" style="padding:4px;">';

        foreach ($themes as $path) {
            $folder = basename($path);
            $this->html .= '<option value="' . pathinfo($folder, PATHINFO_FILENAME) . '">' . pathinfo($folder, PATHINFO_FILENAME) . '</option>';
        }

        $this->html .= '</select>';
        $this->html .= '<input type="submit" value="Apply Theme" style="padding:4px 10px;" class="btn" />';
        $this->html .= '</form></div>';
    }

    private function processUpdate() {
        if (isset($_POST["new_theme"])) {
            $newTheme = $_POST['new_theme'];
            
            // Validate input to ensure it corresponds to a real directory
            if (is_file($this->template_dir . '/' . $newTheme . '.php')) {
                $content = file_get_contents($this->settings_file);
                
                // Regex to find the theme key and replace its value while preserving formatting
                // Matches "theme": "anything" and replaces with "theme": "new_theme"
                $pattern = '/("theme"\s*:\s*")([^"]*)(")/';
                $replacement = '$1' . $newTheme . '$3';
                
                $newContent = preg_replace($pattern, $replacement, $content);
                
                if ($newContent !== null) {
                    $this->updateProcessed = true;
                    file_put_contents($this->settings_file, $newContent);
                }
            }
        }
    }

    public function name() { return $this->name; }
    public function links() { return $this->links; }
    public function html() { return $this->html; }
}

?>