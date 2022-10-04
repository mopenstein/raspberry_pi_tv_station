<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);

######################################## json settings
require_once("settings.class.inc");

$Settings = new Settings();
$json_settings = $Settings->load("/home/pi/Desktop/settings.json");
	if($json_settings[0]==null) die("Settings Error: " . $json_settings[1]);
$json_settings = $json_settings[0];
$drive_loc = $json_settings["drive"];
######################################## /json settings


if(isset($_GET["type"])) {
	if($_GET["type"]=="delete" && isset($_GET["in"])) {
		if(!file_exists($_GET["in"])) die("File does not exist: ".$_GET["in"]);
		unlink($_GET["in"]);
		die("_del" . $_GET["in"]);
	}

	if($_GET["type"]=="move" && isset($_GET["in"]) && isset($_GET["in"])) {
		if(!file_exists($_GET["in"])) die("Filedoes not exist: ".$_GET["in"]);
		rename($_GET["in"], $_GET["out"]);
		die("_mov" . $_GET["in"] . " ---> " . $_GET["out"]);
	}
	
	if($_GET["type"]=="edit" && isset($_GET["in"]) && isset($_GET["out"]) && is_numeric($_GET["start"]) && is_numeric($_GET["end"])) {
		if(!file_exists($_GET["in"])) die("Video does not exist: ".$_GET["in"]);
		echo "_vid";
		set_time_limit(0);
		
		//$in = "/media/pi/ssd/temp/comm_NA_%T(11)%.mp4";
		$in = $_GET["in"];
		//$out = "/media/pi/ssd/temp/out.mp4";
		$out = $_GET["out"];

		$float_start = $_GET["start"];
		
		$float_end = $_GET["end"];

		$command = "-y -i \"$in\" -ss $float_start -to $float_end -async 1 -strict -2 \"$out\" 2>&1";

		ob_implicit_flush(true);ob_end_flush();
		
		$descriptorspec = array(
		   0 => array("pipe", "r"),   // stdin is a pipe that the child will read from
		   1 => array("pipe", "w"),   // stdout is a pipe that the child will write to
		   2 => array("pipe", "w")    // stderr is a pipe that the child will write to
		);
		flush();
		$process = proc_open("ffmpeg $command", $descriptorspec, $pipes, realpath('./'), array());
		if (is_resource($process)) {
			while ($s = fgets($pipes[1])) {
				echo "$s<br />";
				flush();
			}
		}
		proc_close($process);
		

		sleep(0.3);

		die("<br />Done! $out</br />");
	}


	if($_GET["type"]=="screenshot" && isset($_GET["in"]) && is_numeric($_GET["start"])) {
		if(!file_exists($_GET["in"])) die("Video does not exist: ".$_GET["in"]);
		set_time_limit(90);
		
		$out = $drive_loc[0] . "/temp/out.jpg";
		$command = "-y -ss ".$_GET["start"]." -i \"{$_GET["in"]}\" -vframes 1 -q:v 2 \"$out\" 2>&1";
		shell_exec("ffmpeg $command");

		sleep(0.1);

		header('Content-Type: image/jpeg');
		readfile($out);

	   die();
	}
}



function scan_dir($start, $root=0) {
	$ret = "";
	$files2 = scandir($start);
	foreach($files2 as $f) {
		if(strpos($f, ".")===false)	{
			$ret .= '<option>' . substr($start,$root).'/'.$f.'</option>';
			$ret .= scan_dir("$start/$f",$root);
		}
	}
	return $ret;
}

function scan_dir_files($start, $root=0) {
	$ret = "";
	$files2 = scandir($start);
	foreach($files2 as $f) {
		if($f == '.' || $f == '..') continue;
		if(is_file($start . "/" . $f)) {
			$ret .= '<option>'.$f.'</option>';
		}
	}
	return $ret;
}

if(isset($_GET["dir"])) {
	if(is_dir($_GET["dir"])) {
		die("_dir" . scan_dir_files($_GET["dir"]));
	}
}

?>

<html>
<head>
<title></title>
<script>

var currrent_position = 0.0;
var ajax_lock = false;

function ajax(url) {
	if(ajax_lock) return;
	ajax_lock=true;
	var xhr = new XMLHttpRequest();
	xhr.open("GET", url, true);
	// function execute after request is successful 
	xhr.onreadystatechange = function () {
		ajax_lock = false;
		if (this.readyState == 4 && this.status == 200) {
			resp = this.responseText;
			tar = resp.substring(0,4);
			if(tar=="_dir") {
				document.getElementById('filesel').innerHTML = resp.substring(4)
			} else if(tar=="_vid") {
				console.log("replace!");
				dir = document.getElementById('dirsel').value;
				file = document.getElementById('filesel').value;
				url = '?type=delete&in=' + dir + '/' + file;
				console.log(url);
				goDotsClear();
				ajax(url);
			} else if(tar=="_del") {
				console.log("move!");
				dir = document.getElementById('dirsel').value;
				file = document.getElementById('filesel').value;
				a = file.indexOf('%T(');
				b = file.indexOf(')%', a+1);
				url = '?type=move&in=out.mp4&out=' + dir + '/' + file.substring(0, a+3) + Math.ceil((document.getElementById('endpos').innerHTML*1) - (document.getElementById('startpos').innerHTML*1)) + file.substring(b);
				goDotsClear();
				console.log(url);
				ajax(url);
			} else if(tar=="_mov") {
				console.log("done!");
				goDotsClear();
				clearInterval(tmrDots);
				document.getElementById('modal').style.display = 'none';
			} else {
				console.log(resp);
			}			
		}
	}
	// Sending our request 
	xhr.send();
}

function dirsel_change(obj) {
	ajax("?dir=" + obj.value);
}

function filesel_change(obj, file) {
	if(obj) {
		dir = document.getElementById('dirsel').value;
		file = dir + '/' + obj.value;
		document.getElementById('vidplayer').setAttribute('src', 'http://tv.station/?video=' + file);
	} else {
		console.log(file);
		document.getElementById('vidplayer').setAttribute('src', 'http://tv.station/?video=' + file);
	}
	
	a = file.indexOf('%T(');
	b = file.indexOf(')%', a+1);
	
	len = file.substring(a+3,b);
	document.getElementById('vidpos').max = len*1;
	document.getElementById('vidpos').min = 0;
	document.getElementById('vidpos').value = Math.floor((len*1)/2);
	document.getElementById('vidpos_micro').value = 0;
	
	update_position();
	
	console.log("screenshot", '?type=screenshot&in=' + file + '&start=' + current_position);
	document.getElementById('preview').src = '?type=screenshot&in=' + file + '&start=' + current_position
}

function vidpos_change(obj) {
	update_position();
	console.log(document.getElementById('filesel').value);

	dir = document.getElementById('dirsel').value;
	file = document.getElementById('filesel').value;
	a = file.indexOf('%T(');
	b = file.indexOf(')%', a+1);
	document.getElementById('vidpos_micro').value = 0;
	
	//len = file.substring(a+3,b);
	console.log("vidpos screenshot", '?type=screenshot&in=' + dir + '/' + file + '&start=' + current_position);
	document.getElementById('preview').src = '?type=screenshot&in=' + dir + '/' + file + '&start=' + current_position;

}

function update_position() {
	console.log('upos', document.getElementById('vidpos').value, document.getElementById('vidpos_micro').value);
	current_position = (document.getElementById('vidpos').value + '.' + document.getElementById('vidpos_micro').value) * 1;
	
	var date = new Date(null);
	date.setSeconds(current_position);
	document.getElementById('position').innerHTML = date.toISOString().substr(11, 8) + "." + document.getElementById('vidpos_micro').value;
	console.log(current_position);
}

function vidpos_input(obj) {
	update_position();
}

function vidpos_micro_change(obj) {
	update_position();
	console.log(document.getElementById('filesel').value);

	dir = document.getElementById('dirsel').value;
	file = document.getElementById('filesel').value;
	a = file.indexOf('%T(');
	b = file.indexOf(')%', a+1);
	
	//len = file.substring(a+3,b);
	console.log("vidpos screenshot", '?type=screenshot&in=' + dir + '/' + file + '&start=' + current_position);
	document.getElementById('preview').src = '?type=screenshot&in=' + dir + '/' + file + '&start=' + current_position;
}

function vidpos_micro_input(obj) {
	update_position();
}

function goDots() {
	document.getElementById('modal_tick').innerHTML = document.getElementById('modal_tick').innerHTML + '.';
}

function goDotsClear() {
	document.getElementById('modal_tick').innerHTML = '.';
}

var tmrDots;

function goEdit() {
	document.getElementById('modal').style.display = 'block';
	tmrDots = setInterval(goDots, 500);

	dir = document.getElementById('dirsel').value;
	file = document.getElementById('filesel').value;
	
	url = '?type=edit&in=' + dir + '/' + file + '&out=out.mp4&start=' + document.getElementById('startpos').innerHTML + '&end=' + document.getElementById('endpos').innerHTML;
	console.log(url);
	ajax(url);
	console.log('finished');
}

</script>
</head>
<body>
<?php

echo '<select id="dirsel" onchange="dirsel_change(this);">';
for($i=0;$i<count($drive_loc);$i++) {
	echo scan_dir($drive_loc[$i], 0);
}
echo '</select><br /><br />';


//scan_dir_files($drive_loc . "cartoons/blackstar", strlen($drive_loc . "cartoons/blackstar/"));

?>
<select id="filesel" onchange="filesel_change(this);" style="width:400px" size=10></select>
<br />
<br />
Current Position: <span id="position">0</span>
<input type="range" min="0" max="100" value="50" step="1" style="width:100%;" id="vidpos" onchange="vidpos_change(this);" oninput="vidpos_input(this);"><br />
<input type="range" min="0" max="9" value="0" step="1" style="width:100%;" id="vidpos_micro" onchange="vidpos_micro_change(this);" oninput="vidpos_micro_input(this);"><br />
<br />
Start Position: <span id="startpos">0</span> | End Position: <span id="endpos">0</span><br />
<input type="button" value="Set Start Position" onclick="document.getElementById('startpos').innerHTML = current_position;" /> <input type="button" value="Set End Position" onclick="document.getElementById('endpos').innerHTML = current_position;" /><br /><br />
<input type="button" value="Edit REPLACE" onclick="goEdit();" /><br />
<input type="button" value="Edit NEW" onclick="goEditNew();" /><br />
<br />
<img id = "preview" width=640 height=480 /><br />
<br />
<video id="vidplayer"src="http://tv.station/?video=/commercials/february/WPWRandWFLDonFebruary18th,1986_19_464_NA_%T(30)%.mp4" controls width=640 height=480>
  <source src="movie.mp4" type="video/mp4">
</video>
<div style="position:absolute;left:0px;top:0px;width:100%;height:100%;max-height:100%;background-color:#ccc;display:none;" id="modal"><center><h1>Video is being converted. This window will vanish when it's finished.</h1><div id="modal_tick" style="font-size:200%;">.</div></center></div>

<?php
if(isset($_GET["file"])) {

echo '
<script type="text/javascript">

var gblTimerWait = null;



window.onload = function() {

var gblFilename = "' . basename($_GET["file"]) . '";
var gblDir = "' .  dirname($_GET["file"]) . '";

for(i=0;i<document.getElementById("dirsel").options.length;i++) {
	if(document.getElementById("dirsel").options[i].value==gblDir) {
		document.getElementById("dirsel").options[i].selected = true;
		break;
	}
}

dirsel_change(document.getElementById("dirsel"));

gblTimerWait = setInterval(function() {
	if(document.getElementById("filesel").options.length==0) return true;
	for(i=0;i<document.getElementById("filesel").options.length;i++) {
		console.log(document.getElementById("filesel").options[i].value + " == " + gblFilename);
		if(document.getElementById("filesel").options[i].value==gblFilename) {
			document.getElementById("filesel").options[i].selected = true;
			break;
		}
	}

	filesel_change(document.getElementById("filesel"));
	clearInterval(gblTimerWait);
	return false;
}, 1000)

}


</script>
';



}
?>
</body>
</html>