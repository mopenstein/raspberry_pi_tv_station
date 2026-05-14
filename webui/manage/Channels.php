<?php

class Channels implements ManageCard {
    private $name = 'Channels';
	private $links = [];
	private $html = null;
	private $settings = null;

	private function loadLinks() {
		// Load links from a file or database
		if ($this->settings == null) {
			return $this->links;
		}

		$channels = $this->settings["channels"]["names"] ?? [];
		$curr_channel = $_GET["channel"] ?? null;
		
		foreach ($channels as $c) {
			$cc = $c;
			if ($c == null || $c == "") $c = "default";
				$style = null;
			if ($curr_channel == $cc) {
				$style = "color:green;font-weight:bold;";
			}
			$this->links[] = [
				'label'        => $c,
				'url'         => "/?channel=" . urlencode($c),
				'style'       => $style,
				'action'      => null,
				'target'      => null
			];
		}
	}

	public function __construct() {
		// You can initialize any properties or perform setup tasks here
		// I will load other links here
		$this->loadLinks();
	}

	public function setSettings($json_settings) {
		$this->settings = $json_settings;
		// Reload links to update any that depend on settings
		$this->loadLinks();
	}		

	public function priority() {
        return 200; // Set a priority for ordering (higher number means higher priority)
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