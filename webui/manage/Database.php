<?php

class Database implements ManageCard {
    private $name = 'Database Card';
	private $links = [];
	private $html = [];
	private $db_management_file = "db_manage.txt";

	private function loadLinks() {
		// Load links from a file or database
		$this->links = [
			[
				"label" => "phpMyAdmin",
				"url" 	=> "/phpmyadmin",
				"style" => null,
				"action" => null
			],
			[
				"label" => "Backup Shows DB (sql/gzip)",
				"url" 	=> "/?backup=now",
				"style" => "color:green;",
				"action" => "return confirm('This will create a backup of the shows database. Are you sure?')"
			],
			[
				"label" => "Trim Shows Played Log",
				"url" 	=> "/?db_manage_ext=1&amp;action=trim_shows",
				"style" => "color:red;",
				"action" => "return confirm('This will permanently delete all show data older than 10 days. Are you sure?')"
			],
			[
				"label" => "Empty Shows Played Log",
				"url" 	=> "/?db_manage_ext=1&amp;action=trim_shows_empty",
				"style" => "color:red;",
				"action" => "return confirm('This will permanently delete all shows data. Are you sure?')"
			],
			[
				"label" => "Trim Commercials Log",
				"url" 	=> "/?db_manage_ext=1&amp;action=trim_commercials",
				"style" => "color:red;",
				"action" => "return confirm('This will permanently delete all commercials data older than 10 days. Are you sure?')"
			],
			[
				"label" => "Empty Commercials Log",
				"url" 	=> "/?db_manage_ext=1&amp;action=trim_commercials_empty",
				"style" => "color:red;",
				"action" => "return confirm('This will permanently delete all commercials data. Are you sure?')"
			],
			[
				"label" => "Trim Messages Log",
				"url" 	=> "/?db_manage_ext=1&amp;action=trim_errors",
				"style" => "color:red;",
				"action" => "return confirm('This will permanently delete all error data older than 10 days. Are you sure?')"
			],
			[
				"label" => "Empty Messages Log",
				"url" 	=> "/?db_manage_ext=1&amp;action=trim_errors_empty",
				"style" => "color:red;",
				"action" => "return confirm('This will permanently delete all messages. Are you sure?')"
			]
		];

		        // Read the file lines into an array
        if (file_exists($this->db_management_file)) { // Check if the file exists
            $lines = file($this->db_management_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); // Optional flags to trim newlines and skip empty lines

            for($i=0;$i<count($lines);$i++)
            {
                $l = $lines[$i];

				$this->links[] = [
					"label" => "Delete ".$l." videos",
					"url" 	=> "/?db_manage_ext=1&amp;action=trim_data".$i,
					"style" => "color:red;",
					"action" => "return confirm('This will permanently delete all entries with /".$l."/ in the path. Are you sure?')"
				];
            }
		}
	}

	private function loadHtml() {
		// Load HTML content from a file or database
		$this->html = '
		<div style="margin:5px; padding:5px; border:1px solid #CCCCCCaa; margin-top:10px;">
			Insert show into database:<br>
			<form method="GET">
			<ul>
			Short Name: <input type="text" name="insert" /><br>
			File Name: <input type="text" name="file" /> <small>*must be full path to file</small><br>
			How many times? <input type="number" name="times" value="10" /><br>
			<input type="submit" />
			</form>
			</ul><br>
            <i>Want to <a href="db_management_build.php">rebuild db_management?</a></i><br>
		</div>
		';
	}

	public function __construct() {
		// You can initialize any properties or perform setup tasks here
		// I will load other links here
		$this->loadLinks();
		$this->loadHtml();
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