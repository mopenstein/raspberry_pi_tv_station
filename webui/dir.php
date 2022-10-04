<?php

######################################## json settings
require_once("settings.class.inc");

$Settings = new Settings();
$json_settings = $Settings->load("/home/pi/Desktop/settings.json");
	if($json_settings[0]==null) die("Settings Error: " . $json_settings[1]);
$json_settings = $json_settings[0];
$root = $json_settings["drive"];
######################################## /json settings
 
if(isset($_GET["play"])) {

	
	file_put_contents("/home/pi/Desktop/next.video",$root."/".$_GET["play"]);
	
	echo file_get_contents('/home/pi/Desktop/next.video');
	$result = exec('sudo chmod 777 /home/pi/Desktop/next.video');
	echo "<br />\nresult: $result<br />\n";
	//$result = exec('sudo ./kill.sh');
	$result = exec('sudo pkill omxplayer');
	echo "<br />\nresult: $result<br />\n";
	die('playing ' . $root."/".$_GET["play"]);
	if(isset($_GET["return"])) {
		header("Location: " . $_GET["return"] . "\n\n");
		die();
	} else {
		die('playing ' . $root."/".$_GET["play"]);
	}
	
}

function is_in_dir($file, $directory, $recursive = true, $limit = 1000) {
    $directory = realpath($directory);
	echo "dir: $directory\n";
    $parent = $file;
	echo "par: $parent\n";
    $i = 0;
    while ($parent) {
        if ($directory == $parent) return true;
        if ($parent == dirname($parent) || !$recursive) break;
        $parent = dirname($parent);
    }
    return false;
}

$path = null;
if (isset($_GET['file'])) {
    $path = "/".$_GET['file'];
	
}

if (is_file($root.$path)) {
    readfile($root.$path);
    return;
}

echo '
<html>
<head>
<title>Browse Videos '.$path.'</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
	div {
		font-size:300%;
		vertical-align:middle;
	}
	
	img {
		width:30px;
		height:30px;
	}
</style>
</head>
<body style="white-space:nowrap;">
';

if ($path) echo '<div><a href="?file='.urlencode(substr(dirname($path), strlen($root) + 1)).'" style="position:relative;top:-5px;font-size:20px;text-decoration:none;"><img src="images/return.png" /></a></div>';
foreach ($root as $r) {
	echo "\n<!-- $r$path".'/*' . "--!>\n";
	foreach (glob("$r$path".'/*') as $file) {
		$file = realpath($file);
		$link = substr($file, strlen($r) + 1);
		if(strpos(strtolower($file), ".commercials")===false) {
			if(is_dir($file)) {
				echo '<div><img src="images/folder.png" /> <a href="?file='.urlencode($link).'" style="position:relative;top:-5px;font-size:20px;text-decoration:none;">'.basename($file).'</a></div>';
			} elseif(strpos(strtolower($file), ".avi")==true || strpos(strtolower($file), ".mp4")==true || strpos(strtolower($file), ".mkv")==true || strpos(strtolower($file), ".mov")==true || strpos(strtolower($file), ".mpg")==true || strpos(strtolower($file), ".m4v")==true || strpos(strtolower($file), ".flv")==true || strpos(strtolower($file), ".mpeg")==true) {
				echo '<div><img src="images/video.png" /> <a href="/?video='.urlencode($file).'&return='.urlencode($_SERVER['REQUEST_URI']).'" style="position:relative;top:-5px;font-size:20px;text-decoration:none;">'.basename($file).'</a></div>';
			}
		}
	}
}

?>

</body>
</html>