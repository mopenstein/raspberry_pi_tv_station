<?php

######################################## json settings
require_once("settings.class.inc");

$Settings = new Settings();
$json_settings = $Settings->load("/home/pi/Desktop/settings.json");
	if($json_settings[0]==null) die("Settings Error: " . $json_settings[1]);
$json_settings = $json_settings[0];
$root = $json_settings["drive"];
######################################## /json settings

if(isset($_GET["check"])) {
	$directory = $_GET["check"];


	if (is_dir($directory)) {
		$baseUrl = "http://127.0.0.1/?short=1&getshowname=";
		// Get all files in the directory
		$files = array_diff(scandir($directory), array('.', '..'));
		$last_name = null;
		foreach ($files as $file) {
			// Construct the URL
			if(pathinfo($file, PATHINFO_EXTENSION)=="commercials") continue;
			$url = $baseUrl . urlencode($file);

			// Initialize a cURL session
			$ch = curl_init($url);

			// Set cURL options
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HEADER, false);

			// Execute the cURL session
			$response = curl_exec($ch);

			// Get the HTTP status code
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

			// Close the cURL session
			curl_close($ch);
			if($response!=$last_name && $last_name!=null) {
				echo "<span style=\"opacity: 0.5;\">Short Name:</span> <b style=\"color:".['#FF5733', '#33FF57', '#3357FF', '#F1C40F', '#8E44AD', '#E74C3C', '#2ECC71', '#3498DB'][array_rand(['#FF5733', '#33FF57', '#3357FF', '#F1C40F', '#8E44AD', '#E74C3C', '#2ECC71', '#3498DB'])].";\">$response</b> <span style=\"opacity: 0.5;\">Checked against: $file</span><br>\n";
			} else {
				echo "<span style=\"opacity: 0.5;\">Short Name:</span> <b>$response</b> <span style=\"opacity: 0.5;\">Checked against: $file</span><br>\n";
			}
			$last_name=$response;
		}
	} else {
		die("The directory does not exist.");
	}
	die();
}

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

if(isset($_GET["f"]) && isset($_GET["movie"])) {
	if($_GET["movie"]!="") {
		$file = $_GET["f"];
		if(file_exists($file)) {
			rename($file, dirname($file) . '/../movie_trailers/' . $_GET["movie"] . "." . pathinfo($file, PATHINFO_EXTENSION));
			die("1|movie trailer set");
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
counterStarted = false;
function startCounter(elementId) {
	if (counterStarted) return;
	counterStarted = true;

	let seconds = 0;
	const element = document.getElementById(elementId);

	function formatTime(sec) {
		let hours = Math.floor(sec / 3600);
		let minutes = Math.floor((sec % 3600) / 60);
		let seconds = sec % 60;

		return `${String(hours).padStart(2, "0")}:${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
	}

	function updateCounter() {
		seconds++;
		element.textContent = formatTime(seconds);
	}

	setInterval(updateCounter, 1000);
}

function showPlayer() {
	document.getElementById("vidplayer").style.display="block";
}

currVideo = null;
currID = null;
function playVideo(video, id) {
	console.log(video);
	currVideo = video;
	currID = id;
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

function movieVideo(that, file) {
	console.log("movie", file);
	name = prompt("Enter movie name (without extension):", "");
	ajax("dir.php?f="+file+"&movie="+encodeURIComponent(name));
	that.parentNode.remove();
}


lastKey = null;
lastKeyTime = 0;
document.onkeydown = function(evt) {
    evt = evt || window.event;
	if (new Date().getTime() - lastKeyTime < 250) skipTime = 5; else skipTime = 2;
	lastKeyTime = new Date().getTime();
    if (evt.key=="d") {
        video = document.getElementById("vidplayer");
		video.currentTime += skipTime;
    } else if (evt.key=="a") {
        video = document.getElementById("vidplayer");
		video.currentTime -= skipTime;
	} else if (lastKey==null && (evt.key=="q" || evt.key=="w" || evt.key=="e" || evt.key=="x" || evt.key=="z")) {
		lastKey = evt.key;
    } else if (evt.key=="q" && lastKey=="q") { //double tape to set AM
		document.getElementById(\'am\' + currID).click();
		lastKey = null;
		setTimeout(function() { document.getElementById(\'plus\' + (currID+1)).click(); }, 750);
	} else if (evt.key=="w" && lastKey=="w") { //double tape to set PM
		document.getElementById(\'pm\' + currID).click();
		lastKey = null;
		setTimeout(function() { document.getElementById(\'plus\' + (currID+1)).click(); }, 750);
	} else if (evt.key=="e" && lastKey=="e") { //double tape to set ANY
		document.getElementById(\'any\' + currID).click();
		lastKey = null;
		setTimeout(function() { document.getElementById(\'plus\' + (currID+1)).click(); }, 750);
	} else if (evt.key=="x" && lastKey=="x") { //double tape to set ANY
		document.getElementById(\'del\' + currID).click();
		lastKey = null;
		setTimeout(function() { document.getElementById(\'plus\' + (currID+1)).click(); }, 750);
	} else if (evt.key=="z" && lastKey=="z") { //double tape to set ANY
		document.getElementById(\'movie\' + currID).click();
		lastKey = null;
		setTimeout(function() { document.getElementById(\'plus\' + (currID+1)).click(); }, 750);
	} else if (evt.key=="r") { //double tape to skip to next video
	 	window.scrollBy(0, 54);
		setTimeout(function() { document.getElementById(\'plus\' + (currID+1)).click(); }, 250);
	} else {
		lastKey = null;
	}
	startCounter("counter");
	console.log(lastKey);
};

</script>
</head>
<body style="white-space:nowrap;">
	<div style="float:right; width:650;"><button onlick="this.parentElement.remove();">X</button>
		<video width="640" height="480" controls id="vidplayer" style="position:fixed; float:right; z-index:1000;">
		</video><p id="counter" style="position:fixed; background-color:#fff; float:right; z-index:1000; font-size:10px;" id="counter">00:00:00</p>
		
	</div>
</div>Click plus to play video; A = go back 5 seconds; D = ahead 5 seconds; Double tap Q = set current video AM; WW = PM; EE = Either; XX = Delete video; ZZ = Movie Trailer<br>
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
				echo '<div><img src="images/folder.png" /> <a href="?file='.urlencode($link).'" style="position:relative;top:-5px;font-size:20px;text-decoration:none;">'.basename($file).'</a> <sup><a href="?check='.urlencode($r."/".$link).'" style="font-size:50%;" title="check this folder against show names">#</a></sup></div>';
			} elseif(strpos(strtolower($file), ".avi")==true || strpos(strtolower($file), ".mp4")==true || strpos(strtolower($file), ".mkv")==true || strpos(strtolower($file), ".mov")==true || strpos(strtolower($file), ".mpg")==true || strpos(strtolower($file), ".m4v")==true || strpos(strtolower($file), ".flv")==true || strpos(strtolower($file), ".mpeg")==true) {
				echo '<div><small style="font-size:16px;">'.$ic.'.</small> <img src="images/video.png" /> <a href="/?video='.urlencode($file).'&return='.urlencode($_SERVER['REQUEST_URI']).'" style="position:relative;top:-5px;font-size:20px;text-decoration:none;'.(strpos($file, "_NA_")==0 ? 'background:#aff;' : '').(strpos($file, "%T(")==0 ? 'color:#faf;' : '').'">'.basename($file).'</a> <a href="javascript:null;" onclick="playVideo(\'/?video='.urlencode($file).'\', '.$ic.');this.style.backgroundColor=\'green\';" id="plus'.$ic.'">+</a>';
					
					if(strpos($file, "%AM%")==0 && strpos($file, "%PM%")==0 && strpos($file, "%ANY%")==0) {
						echo ' <a href="javascript:null;" onclick="setAMPM(\''.urlencode($file).'\',0);this.style.backgroundColor=\'red\'; window.scrollBy(0, 54);" style="font-size:33%;" id="am'.$ic.'">AM</a> <a href="javascript:null;" onclick="setAMPM(\''.urlencode($file).'\',1);this.style.backgroundColor=\'red\'; window.scrollBy(0, 54);" style="font-size:33%;" id="pm'.$ic.'">PM</a> <a href="javascript:null;" onclick="setAMPM(\''.urlencode($file).'\',2);this.style.backgroundColor=\'red\'; window.scrollBy(0, 54);" style="font-size:33%;" id="any'.$ic.'">Either</a> <a href="javascript:null;" onclick="movieVideo(this, \''.urlencode($file).'\');this.style.backgroundColor=\'red\'; window.scrollBy(0, 54);" style="font-size:33%;" id="movie'.$ic.'">Movie</a>';
					}
					
				echo ' <a href="javascript:null;" onclick="if(confirm(\'Delete cannot be undone. Are you sure?\')) { delVideo(this, \''.urlencode($file).'\'); }" style="font-size:33%;"  id="del'.$ic.'">[ X ]</a></div>
';
				$ic++;
			}
		}
	}
}

?>

<script type="text/javascript">

function mobileCheck() {
  let check = false;
  (function(a){if(/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i.test(a)||/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(a.substr(0,4))) check = true;})(navigator.userAgent||navigator.vendor||window.opera);
  return check;
};

if (mobileCheck()) {
	document.getElementById('vidplayer').style.display='none';
}

</script>
</body>
</html>