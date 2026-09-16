<?php

class Channels implements ManageCard {
    private $name = 'Channels';
    private $links = [];
    private $html = null;
    private $settings = null;

    private function loadLinks() {
        // Reset links array to prevent appending duplicate entries on reload
        $this->links = [];

        if ($this->settings == null) {
            return $this->links;
        }

        $channels = $this->settings["channels"]["names"] ?? [];
        $curr_channel = $_GET["channel"] ?? null;
        
        foreach ($channels as $c) {
            $raw_val = $c;
            $display_name = (empty($c)) ? "default" : $c;
            $style = null;

            if ($curr_channel === $raw_val || ($curr_channel === null && empty($raw_val))) {
                $style = "color:green;font-weight:bold;";
            }

            $this->links[] = [
                'label'   => $display_name,
                'url'     => "/?channel=" . urlencode($display_name),
                'style'   => $style,
                'action'  => null,
                'target'  => null
            ];
        }
    }

    public function __construct() {
        $this->loadLinks();
    }

    public function setSettings($json_settings) {
        $this->settings = $json_settings;
        $this->loadLinks();
    }       

    public function priority() {
        return 200;
    }
    
    public function name() {
        return $this->name;
    }

    public function links() {
        return $this->links;
    }

    public function html() {
        return $this->html;
    }
}
?>