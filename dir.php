<?php

######################################## json settings
require_once("settings.class.inc");

$Settings = new Settings();
$json_settings = $Settings->load("/home/pi/Desktop/settings.json");
	if($json_settings[0]==null) die("Settings Error: " . $json_settings[1]);
$json_settings = $json_settings[0];
$root = $json_settings["drive"];
######################################## /json settings

if(isset($_GET["f"]) && isset($_GET["delete"])) {
	if($_GET["delete"]=="delete") {
		$file = $_GET["f"];
		if(file_exists($file)) {
			unlink($file);
			die("1|deleted");
		} else {
			die("file no exist");
		}
	}
}

if(isset($_GET["f"]) && isset($_GET["set"])) {
	$file = $_GET["f"];
	
	if(file_exists($file)) {
		$amPMeither = "";
		if($_GET["set"]==0) {
			$amPMeither = "%AM%";
		} elseif($_GET["set"]==1) {
			$amPMeither = "%PM%";
		} elseif($_GET["set"]==2) {
			$amPMeither = "%ANY%";
		} else {
			die("set wrong");
		}
		
		$newfile = pathinfo($file, PATHINFO_DIRNAME) . "/" . pathinfo($file, PATHINFO_FILENAME) . "$amPMeither." . pathinfo($file, PATHINFO_EXTENSION);
		$worked = rename($file, $newfile);
		die( $worked . "|" . $newfile );
	} else {
		die("file no exist");
	}
}

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
<script type="text/javascript">
function showPlayer() {
	document.getElementById("vidplayer").style.display="block";
}

function playVideo(video) {
	console.log(video);
	document.getElementById("vidplayer").src=video;
	document.getElementById("vidplayer").play();
}

function ajax(url) {
	var xhr = new XMLHttpRequest();
	xhr.open("GET", url, true);
	// function execute after request is successful 
	xhr.onreadystatechange = function () {
		if (this.readyState == 4 && this.status == 200) {
			console.log(this.responseText);
			if(this.responseText.substring(0,1)!="1") {
				alert(this.responseText);
			}
		}
	}
	// Sending our request 
	xhr.send();
}

function setAMPM(file, amPM) {
	console.log(file, amPM);
	ajax("dir.php?f="+file+"&set="+amPM);
	
}


function delVideo(that, file) {
	console.log("delete", file);
	ajax("dir.php?f="+file+"&delete=delete");
	that.parentNode.remove();
}

document.onkeydown = function(evt) {
    evt = evt || window.event;
	console.log(evt);
    if (evt.key=="d") {
        video = document.getElementById("vidplayer");
		video.currentTime += 5;
    }
	if (evt.key=="a") {
        video = document.getElementById("vidplayer");
		video.currentTime -= 5;
    }
};

</script>
</head>
<body style="white-space:nowrap;">
	<div style="float:right; width:650;"><button onlick="this.parentElement.remove();">X</button>
		<video width="640" height="480" controls id="vidplayer" style="position:fixed; float:right; z-index:1000;">
		</video>
	</div>
</div>
';

if ($path) echo '<div><a href="?file='.urlencode(substr(dirname($path), strlen($root) + 1)).'" style="position:relative;top:-5px;font-size:20px;text-decoration:none;"><img src="images/return.png" /></a></div>';
	
foreach ($root as $r) {
	echo "\n<!-- $r$path".'/*' . "--!>\n";
	$ic=1;
	$dafiles = glob("$r$path".'/*');
	$count = count($dafiles);
	echo $count . ' items<br />';
	foreach ($dafiles as $file) {
		$file = realpath($file);
		$link = substr($file, strlen($r) + 1);
		if(strpos(strtolower($file), ".commercials")===false) {
			if(is_dir($file)) {
				echo '<div><img src="images/folder.png" /> <a href="?file='.urlencode($link).'" style="position:relative;top:-5px;font-size:20px;text-decoration:none;">'.basename($file).'</a></div>';
			} elseif(strpos(strtolower($file), ".avi")==true || strpos(strtolower($file), ".mp4")==true || strpos(strtolower($file), ".mkv")==true || strpos(strtolower($file), ".mov")==true || strpos(strtolower($file), ".mpg")==true || strpos(strtolower($file), ".m4v")==true || strpos(strtolower($file), ".flv")==true || strpos(strtolower($file), ".mpeg")==true) {
				echo '<div><small style="font-size:16px;">'.$ic.'.</small> <img src="images/video.png" /> <a href="/?video='.urlencode($file).'&return='.urlencode($_SERVER['REQUEST_URI']).'" style="position:relative;top:-5px;font-size:20px;text-decoration:none;">'.basename($file).'</a> <a href="javascript:null;" onclick="playVideo(\'/?video='.urlencode($file).'\');this.style.backgroundColor=\'green\';">+</a>';
					
					if(strpos($file, "%AM%")==0 && strpos($file, "%PM%")==0 && strpos($file, "%ANY%")==0) {
						echo ' <a href="javascript:null;" onclick="setAMPM(\''.urlencode($file).'\',0);this.style.backgroundColor=\'red\'; window.scrollBy(0, 56);" style="font-size:33%;">AM</a> <a href="javascript:null;" onclick="setAMPM(\''.urlencode($file).'\',1);this.style.backgroundColor=\'red\'; window.scrollBy(0, 56);" style="font-size:33%;">PM</a> <a href="javascript:null;" onclick="setAMPM(\''.urlencode($file).'\',2);this.style.backgroundColor=\'red\'; window.scrollBy(0, 56);" style="font-size:33%;">Either</a>';
					}
					
				echo ' <a href="javascript:null;" onclick="if(confirm(\'Delete cannot be undone. Are you sure?\')) { delVideo(this, \''.urlencode($file).'\'); }" style="font-size:33%;">[ X ]</a></div>
';
				$ic++;
			}
		}
	}
}

?>

</body>
</html>