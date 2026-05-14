<?php

class FilesSystem implements ManageCard {
    private $name = 'Files and System';
	private $links = [];
	private $html = null;

	private function loadLinks() {
		// Load links from a file or database
		$this->links = [
			[
				"label" => "Browse Files",
				"url" 	=> "/dir.php",
				"style" => null,
				"action" => null
			],
			[
				"label" => "Video Editor",
				"url" 	=> "/videoeditor.php",
				"style" => null,
				"action" => null
			],
			[
				"label" => "Reboot",
				"url" 	=> "/?reboot=now",
				"style" => "color:red;",
				"action" => "return confirm('This will reboot the system. Are you sure?')"
			]
		];
	}

	public function __construct() {
		// You can initialize any properties or perform setup tasks here
		// I will load other links here
		$this->loadLinks();
	}

	public function priority() {
        return 100;
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