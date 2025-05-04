<?php

require_once("settings.class.inc");

$Settings = new Settings();
$json_settings = $Settings->load("/home/pi/Desktop/settings.json");
	if($json_settings[0]==null) {
		die('<div style="background:red;color:white;margin:10px;padding:10px;">JSON VALIDATION ERROR :: CHECK \'settings.json\' FILE! <br /><br />Error: '.$json_valid[1].' - <a href="https://duckduckgo.com/?q=json+validator&ia=answer">Validate</a></div>');		
	}
$json_settings = $json_settings[0];

$database_info = $json_settings["web-ui"]["database_info"];
$drive_loc = $json_settings["drive"];

require_once("db_manage_ext.inc");
$db_manage_ext = new DBMANAGEEXT();
$db_manage_ext->load($database_info["host"], $database_info["username"], $database_info["password"], $database_info["database_name"]);

$mysqli = new mysqli($database_info["host"], $database_info["username"], $database_info["password"], $database_info["database_name"]);

// Database connection parameters
try {

    // Query to retrieve all rows from the 'played' table
    $res = $mysqli->query("SELECT name FROM played");
    
    // Process each row
    while ($row = $res->fetch_assoc()) {
        $name = $row['name'];
        foreach($drive_loc as $drive)
        {
            $name = str_replace($drive, "", $name);
        }
        // Use regex to match text between slashes
        if (preg_match_all('/\/([^\/]+)/', $name, $matches)) {
            $max = count($matches[1]);
            for($i=0;$i<$max-1;$i++) {
                $subDir = $matches[1][$i]; // Normalize to lowercase for consistency
                if (isset($stringCounts[$subDir])) {
                    $stringCounts[$subDir]++;
                } else {
                    $stringCounts[$subDir] = 1;
                }
            }
        }
    }

    // Sort by frequency in descending order
    arsort($stringCounts);

    // Output the results

    // Open the file in write mode ('w' truncates the file if it exists, or creates a new one)
    $fileHandle = fopen("db_manage.txt", "w");

    // Check if the file is successfully opened
    if ($fileHandle) {
        echo "Most Common Strings Between Slashes:\n<br>";
        foreach ($stringCounts as $string => $count) {
            echo "$string: ($count)<br>\n";
            fwrite($fileHandle, $string . PHP_EOL); // Add a newline after each line
        }
        fclose($fileHandle);
    } else {

    }

} catch (Exception $e) {
    // Handle connection errors
    die($e);
}
?>
