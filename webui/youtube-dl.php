<?php

######################################## json settings
require_once("settings.class.inc");

$Settings = new Settings();
$json_settings = $Settings->load("/home/pi/Desktop/settings.json");
	if($json_settings[0]==null) die("Settings Error: " . $json_settings[1]);
$json_settings = $json_settings[0];
$drive_loc = $json_settings["drive"];
######################################## /json settings


if(isset($_GET["youtubedl"])) {

ob_implicit_flush(true);ob_end_flush();

	if(isset($_GET["upgrade"])) {
		#$cmd = "pip install -U youtube-dl";
		#$cmd = "pip install -U yt-dlp";
		$cmd = $json_settings["youtube-dl"]["update_command"];
	} else {
		#$cmd = "youtube-dl --newline -o \"" . $drive_loc . addslashes($_GET["dir"]) . "/%(title)s.%(ext)s\" ".$_GET["youtubedl"];
		#$cmd = "yt-dlp --newline -f 'bestvideo+bestaudio' --merge-output-format mkv -o \"" . addslashes($_GET["dir"]) . "/%(title)s.%(ext)s\" ".$_GET["youtubedl"];
		$cmd = $json_settings["youtube-dl"]["command"] . " --newline -f 'bestvideo+bestaudio' --merge-output-format mkv -o \"" . addslashes($_GET["dir"]) . "/%(title)s.%(ext)s\" ".$_GET["youtubedl"];
	}

$descriptorspec = array(
   0 => array("pipe", "r"),   // stdin is a pipe that the child will read from
   1 => array("pipe", "w"),   // stdout is a pipe that the child will write to
   2 => array("pipe", "w")    // stderr is a pipe that the child will write to
);
flush();
echo '<div style="color: white;font: 1.1rem Inconsolata, monospace;text-shadow: 0 0 5px #C8C8C8;">
<div style="padding:4px;">' . date("h:i:s a") . ' Initializing interface youtube-dl....</div>
<div style="padding:4px;">' . date("h:i:s a") . ' Command: '.$cmd.'</div>';
	$process = proc_open($cmd, $descriptorspec, $pipes, realpath('./'), array());
	if (is_resource($process)) {
		while ($s = fgets($pipes[1])) {
        echo '<div style="padding:4px;">' . date("h:i:s a") . " " . str_replace("[download]", '<span style="color:yellow;">[download]</span>', $s) . "</div>";

		echo '
<script>
var scrollingElement = (document.scrollingElement || document.body);
scrollingElement.scrollTop = scrollingElement.scrollHeight;
</script>
';

			flush();
		}
	}
	proc_close($process);
	
	echo '</div>';
	
	/*
	$output="";
	$return_var="";
	exec(." 2>&1", $output, $return_var);
	var_dump($output);
	var_dump($return_var);
	*/
	die("DONE!\n\n");
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

echo '
<html>
<head>
<title>youtube-dl interface</title>
<style>
#grid {
  content:"";
  background: repeating-linear-gradient(#10101070,#00000070 1px,transparent 1px,transparent 2px);
  
  position: absolute;
  top: 0;
  left: 0;
  width: 200px;
  height: 200px;
}
</style>
<script type="text/javascript">
function get_video() {
	var url = document.getElementById(\'vidurl\').value;
	console.log(\'?youtubedl=\' + url + \'&dir=\' + document.getElementById(\'dirsel\').value);
	document.getElementById(\'ytdl_frame\').src=\'/?youtubedl=\' + url + \'&dir=\' + document.getElementById(\'dirsel\').value;
}

function drawLines() {
	var scrollBarWidth = document.getElementById("ytdl_frame").offsetWidth - document.getElementById("ytdl_frame").clientWidth;
	var scrollBarHeight = document.getElementById("ytdl_frame").offsetHeight - document.getElementById("ytdl_frame").clientHeight;

	document.getElementById("grid").style.left = document.getElementById("ytdl_frame").offsetLeft+2;
	document.getElementById("grid").style.top = document.getElementById("ytdl_frame").offsetTop+2;
	
	document.getElementById("grid").style.width = document.getElementById("ytdl_frame").offsetWidth-scrollBarWidth-4;
	document.getElementById("grid").style.height = document.getElementById("ytdl_frame").offsetHeight-scrollBarHeight-4;
}

window.onload = function() { drawLines(); }

window.onresize = function() { drawLines(); }

</script>
</head>
<body style="background-color:#cc9;border:outset;margin:2px;padding:20px;">
<iframe id="ytdl_frame" name="ytdl_frame" style="height:90%;width:100%;background-color: black;background-image: radial-gradient(rgba(0, 150, 0, 0.75), black 120%);" src=""></iframe>
<div id="grid"></div>
<br />
<br />
<div style="width:100%;"><span id="power_light" style="float:right;font-size:125%;color:#6f0;display:block;border:inset;padding:0;" flipbit=1>&#9646;</span>
Video URL: <input type="text" id="vidurl" onclick="this.select();" /> <select id="dirsel">';
echo '<option>/</option>';
for($i = 0;$i<count($drive_loc);$i++) {
	echo scan_dir($drive_loc[$i], 0);
}
echo '</select> <input type="button" onclick="get_video();" value="Get Video" /><br />
<a href="?youtubedl=1&upgrade=1" target="ytdl_frame">Upgrade</a><br />
</div>
<script>
function blinkPower() {
	if(document.getElementById("power_light").getAttribute("flipbit")=="1") {
		document.getElementById("power_light").style.color="#666";
		document.getElementById("power_light").setAttribute("flipbit", 0)
	} else {
		document.getElementById("power_light").style.color="#6f0";
		document.getElementById("power_light").setAttribute("flipbit", 1)
	}
	setTimeout(blinkPower,1000);
}
setTimeout(blinkPower,1000);
</script>
</body>
</html>
';

?>