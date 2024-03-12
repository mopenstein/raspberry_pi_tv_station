<?php
if(isset($_GET["video"])) {
	$video = $_GET["video"];
	if(!file_exists($video)) die("Video file does not exist");
	$comm = $_GET["video"] . ".commercials";
	if(!file_exists($comm)) die("Commercials file does not exist");

	foreach(file($comm) as $line) {
		echo gmdate("H:i:s", $line) . " - $line <br />\n";
	}
	
	die();

}

die("No video supplied");

?>