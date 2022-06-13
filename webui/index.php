<?php
/*
# Do not expose your Raspberry Pi directly to the internet via port forwarding or DMZ.
# This software is designed for local network use only.
# Opening it up to the web will ruin your day.
*/

error_reporting(E_ALL);
ini_set("display_errors", 1);

//######################### drive

//$drive_loc="/media/pi/750gb/";
$drive_loc="/media/pi/ssd/";

//######################### drive

if(isset($_GET["youtube_download"])) {

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
	console.log(\'/?youtubedl=\' + url + \'&dir=\' + document.getElementById(\'dirsel\').value);
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
echo scan_dir($drive_loc, strlen($drive_loc));

echo '</select> <input type="button" onclick="get_video();" value="Get Video" /><br />
<a href="/?youtubedl=1&upgrade=1" target="ytdl_frame">Upgrade</a><br />
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
die();
}

if(isset($_GET["youtubedl"])) {

ob_implicit_flush(true);ob_end_flush();

	if(isset($_GET["upgrade"])) {
		#$cmd = "pip install -U youtube-dl";
		$cmd = "pip install -U yt-dlp";
	} else {
		#$cmd = "youtube-dl --newline -o \"" . $drive_loc . addslashes($_GET["dir"]) . "/%(title)s.%(ext)s\" ".$_GET["youtubedl"];
		$cmd = "yt-dlp --newline -f 'bestvideo+bestaudio' --merge-output-format mkv -o \"" . $drive_loc . addslashes($_GET["dir"]) . "/%(title)s.%(ext)s\" ".$_GET["youtubedl"];
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

$mysqli = new mysqli("localhost", "pi", "raspberry", "shows");

if(isset($_GET["flag_video"])) {
	if(is_numeric($_GET["flag_video"])) {
		$res = $mysqli->query("UPDATE played SET flag=1 WHERE id=".$_GET["flag_video"]) or die($mysqli->error);
		die("video" . $_GET["flag_video"]."|1");
	}
}

if(isset($_GET["unflag_video"])) {
	if(is_numeric($_GET["unflag_video"])) {
		$res = $mysqli->query("UPDATE played SET flag=0 WHERE id=".$_GET["unflag_video"]) or die($mysqli->error);
		die("video" . $_GET["unflag_video"]."|0");
	}
}

if(isset($_GET["flag_comm"])) {
	if(is_numeric($_GET["flag_comm"])) {
		$res = $mysqli->query("UPDATE commercials SET flag=1 WHERE id=".$_GET["flag_comm"]) or die($mysqli->error);
		die("comm" . $_GET["flag_comm"]."|1");
	}
}

if(isset($_GET["unflag_comm"])) {
	if(is_numeric($_GET["unflag_comm"])) {
		$res = $mysqli->query("UPDATE commercials SET flag=0 WHERE id=".$_GET["unflag_comm"]) or die($mysqli->error);
		die("comm" . $_GET["unflag_comm"]."|0");
	}
}

#user wants to play a certain video
if(isset($_GET["play"])) {

	file_put_contents('/home/pi/Desktop/next.video', $_GET["play"]);
	echo file_get_contents('/home/pi/Desktop/next.video');
	$result = exec('sudo chmod 0777 /home/pi/Desktop/next.video');
	echo $result;
	$result = exec('sudo ./kill.sh');
	echo $result;
	die('playing ' + $_GET["play"]);
}

if(isset($_GET["reboot"])) {
    header("Location: /\n\n");
	exec('sudo reboot');
}

if(isset($_GET["start"])) {
    header("Location: /\n\n");
	exec('python /home/pi/Desktop/_rnd80s.py');
}


if(isset($_GET["skip"])) {
    //header("Location: /\n\n");
	$result = exec('sudo ./kill.sh');
	die($result);
}

if(isset($_GET["restart"])) {
    //header("Location: /\n\n");
	$result = exec('nohup bash ./restart.sh > /dev/null 2>&1 &');
	die($result);
}

if(isset($_GET["video"])) {
	include("videostream.inc");
	header('Content-type: video/mp4');
	$stream = new VideoStream($drive_loc . $_GET["video"]);
	$stream->start();
	die();
}

if(isset($_GET["delete"])) {
	unlink($drive_loc.$_GET["delete"]);
	die("video deleted");
}


function getShowNames($url, $force) {
	$murl = md5($url) . ".cache";
	
	if(!$force) {
		return file_get_contents($murl);
	}
	
	try {
		$str = file_get_contents($url);
		file_put_contents($murl, $str);
		return $str;
	} catch(Exception $e) {
		return file_get_contents($murl);
	}
}

$csv = getShowNames('https://docs.google.com/spreadsheets/d/1QADkcJlcQRP1PPGCcgFtUiBjNtF-gjDE1SO4lcrBosk/export?format=csv&id=1QADkcJlcQRP1PPGCcgFtUiBjNtF-gjDE1SO4lcrBosk&gid=0', false);
$acsv = str_getcsv(str_replace("\r\n", ",", $csv));


if(isset($_GET["clear_cache"])) {
	die(getShowNames('https://docs.google.com/spreadsheets/d/1QADkcJlcQRP1PPGCcgFtUiBjNtF-gjDE1SO4lcrBosk/export?format=csv&id=1QADkcJlcQRP1PPGCcgFtUiBjNtF-gjDE1SO4lcrBosk&gid=0', true));
}

if(isset($_GET["trim_commercials"])) {
	$res = $mysqli->query("DELETE FROM commercials WHERE played<=" . (time()-864000) . " ORDER BY id DESC") or die($mysqli->error);
	header("location: /\n\n");
	die();
}

if(isset($_GET["trim_errors"])) {
	$res = $mysqli->query("DELETE FROM errors WHERE played<=" . (time()-864000) . " ORDER BY id DESC") or die($mysqli->error);
	header("location: /\n\n");
	die();
}

function getTvShowName($filename) {
	global $acsv;
	$afilename = strtolower(preg_replace("~[_\W\s]~", '', basename($filename)));
	for($i=13;$i<count($acsv);$i++) {
		if($acsv[$i]!="") {
			$tmp = strtolower(preg_replace("~[_\W\s]~", '', $acsv[$i]));
			if(strpos("a" . $afilename, $tmp)>0) return $acsv[$i];
		}
	}
	return $filename;
}

function parseCSV($csv) {
    $csv = array_map('str_getcsv', explode("\r\n", $csv));
	$narr = [];
	$keys = [];

	//create an array of types (reruns, primetime, cartoons, etc)
	//and also an array of keys for each type's name
	for($i=0;$i<count($csv[0]);$i++) {
		$narr[$csv[0][$i]] = [];
		$keys[$i] = $csv[0][$i];
	}

	for($i=1;$i<count($csv);$i++) {
		for($j=0;$j<count($csv[$i]);$j++) {
			if($csv[$i][$j]!="") {
				if(isset($shows[$csv[$i][$j]])) $cnt = $shows[$csv[$i][$j]]; else $cnt = 0;
				array_push($narr[$keys[$j]], $csv[$i][$j]);
			}
		}
	}
	
	return $narr;
}


function getShowType($sname, $csv) {

	foreach($csv as $k=>$v) {
		for($i=0;$i<count($v);$i++) {
			if($sname==$v[$i]) return $k;
		}
	}
	
	return "none";

}


$parsedShows = parseCSV($csv);

if(isset($_GET["getshowname2"])) {

	
	//var_dump($a);

		die("");
	
}

function checkShowPlayAmount($sname, $csv) {
	global $mysqli;

    $csv = array_map('str_getcsv', explode("\r\n", $csv));
	$narr = [];
	$keys = [];
	
	//create an array of types (reruns, primetime, cartoons, etc)
	//and also an array of keys for each type's name
	for($i=0;$i<count($csv[0]);$i++) {
		$narr[$csv[0][$i]] = [];
		$keys[$i] = $csv[0][$i];
	}

	$res = $mysqli->query("SELECT *, COUNT(`short_name`) AS `value_occurrence` FROM `played` GROUP BY `short_name` ORDER BY `value_occurrence` DESC") or die($mysqli->error);
	$shows = [];
	//load each show that has been played and their play count
	while ($brow = $res->fetch_assoc()) {
		$shows[$brow["short_name"]] = $brow["value_occurrence"]*1;
	}

	$selected = null;
	//populate each type with its shows and how many times it has been played
	//and whilst doing so, set the current type so we can compare the played times against only the same type of shows
	for($i=1;$i<count($csv);$i++) {
		for($j=0;$j<count($csv[$i]);$j++) {
			if($csv[$i][$j]!="") {
				if(isset($shows[$csv[$i][$j]])) $cnt = $shows[$csv[$i][$j]]; else $cnt = 0;
				array_push($narr[$keys[$j]], [$csv[$i][$j], $cnt]);
				if($csv[$i][$j] == $sname) {
					$selected = $keys[$j];
				}
			}
		}
	}

	$highest = -1;
	$lowest = 999999;
	$lowshow = "";
	$list = [];
	if($selected!=null) {
	//shift the most played shows to the top
	//and everything else to the bottom
		for($i=0;$i<count($narr[$selected]);$i++) {
			//if($i>6) break;
			if($narr[$selected][$i][1] >= $highest) {
				$highest = $narr[$selected][$i][1];
				array_unshift($list, $narr[$selected][$i]);
			} else {
				array_push($list, $narr[$selected][$i]);
			}
			
			if($narr[$selected][$i][1] < $lowest) {
				$lowest = $narr[$selected][$i][1];
				$lowshow = $narr[$selected][$i][0];
			}
		}
	}

	//echo "$lowest $lowshow <br />\n";

	//test the first few only
	/*
	for($i=0;$i<100;$i++) {
		if(isset($list[$i])) {
			if($i>6) { 
				unset($list[$i]);
			}
		} else {
			break;
		}
	}

	
	*/

	//var_dump($list);

	$exit=true;

	//check to make sure not every show has been played an equal amount of times
	//by seeing if the highest play count is the same for each show
	for($i=0;$i<count($list);$i++) {
		//echo($list[$i][0] . ", " . $list[$i][1] . ", " . $highest . "\n");
		if($list[$i][1] != $highest) { 
			$exit=false;
		}
	}
	
	//if each show has been played the same amount of times, then play whichever show this one is because it doesn't matter.
	if($exit) return true;

	//see if the current show is in the list of highest shows
	//if it is, it shouldn't be played
	for($i=0;$i<count($list);$i++) {
		if($list[$i][1] != $lowest) { 
			if($sname==$list[$i][0]) {
				return false;
			}
		}
	}

	//play the current show
	return true;
}

if(isset($_GET["getshowname"])) {

//if day = sunday and time > 5am and time < 10am

	if(strpos($_GET["getshowname"], '/commercials/') > 0) {
		//a commercial is being payed, ignore 
		die("commercial|0");//."|".$brow["value_occurrence"]);
	}

	$dayofweek = date("l",time());
	$hourofday = date("G",time());

	//$thanksgiving = strpos("=".$_GET["getshowname"], "thanksgiving");

	$shortname=getTvShowName($_GET["getshowname"]);
	$showType=getShowType($shortname, $parsedShows);
	
	$row=0;
	$brow=0;

		// || $showType=="Christmas"
	if($showType=="Specials" || $showType=="Movies" || $showType=="Primetime" || $showType=="Gameshows" ) {
		$row=0;
	} else { //not sunday morning
		$time_diff = 7200;
		$row = 0;
		//xmas time, double the time difference
		if(date('n')==12) $time_diff = $time_diff*2;
		$res = $mysqli->query("SELECT played FROM played WHERE short_name='" . addslashes($shortname) . "' AND played>=" . (time()-$time_diff) ." AND played<=" . (time()-1) . "  LIMIT 1") or die($mysqli->error);
		//echo "SELECT played FROM played WHERE short_name='" . addslashes($shortname) . "' AND played>=" . (time()-$time_diff) ." AND played<=" . (time()-1) . "  LIMIT 1\n\n";
		//var_dump($res);
		$row = $res->fetch_row()[0]*1;
		
		//echo "\n\n-" . getTvShowName($_GET["getshowname"]) . "-" . checkShowPlayAmount(getTvShowName($_GET["getshowname"]), $csv) . "-\n\n";
		if(checkShowPlayAmount(getTvShowName($_GET["getshowname"]), $csv)) {
			//play the show
			$row=0;
		} else {
			//show is one of the top of its category, play something else
			if($row==0) {
				$row=time();
			}
		}		
	}
	
	die("$shortname|$row|$showType");//."|".$brow["value_occurrence"]);
}

$mntcont = strlen($drive_loc);

if(isset($_GET["current_video"])) {
	if(strpos($_GET["current_video"], '/commercials/') > 0) {
		die(1);
	}
	$mysqli->real_query("INSERT INTO played (short_name, name, played) VALUES ('" . addslashes(getTvShowName($_GET["current_video"])) . "', '" . addslashes(substr($_GET["current_video"], $mntcont)) . "', ". time() . ")");
	
	die(1);
}

if(isset($_GET["error"])) {
	$mysqli->real_query("INSERT INTO errors (name, played) VALUES ('" . addslashes($_GET["error"]) . "', ". time() . ")");
	
	die(1);
}

if(isset($_GET["current_comm"])) {
	$mysqli->real_query("INSERT INTO commercials (name, played) VALUES ('" . addslashes(substr($_GET["current_comm"], $mntcont)) . "', ". time() . ")");
	
	die(1);
}


$days=array("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");
$td = date("w", time());

	$f = fopen("/sys/class/thermal/thermal_zone0/temp","r");
	$int_temp = fgets($f);
	fclose($f);

function getUptime() {
	global $mysqli;
	$res = $mysqli->query("SELECT name FROM errors WHERE name LIKE '%UPTIME%' ORDER BY id DESC LIMIT 1") or die($mysqli->error);
	$row = $res->fetch_assoc();
	if($row) {
		return gmdate("H:i:s", (int)explode("|", $row["name"])[1]);
	} else {
		return "00:00:00";
	}
}


echo '
<html>
<head>
<link href="data:image/x-icon;base64,AAABAAEAEBAAAAEACABoBQAAFgAAACgAAAAQAAAAIAAAAAEACAAAAAAAAAEAAAAAAAAAAAAAAAEAAAAAAAAAAAAA2tnYAI+MigB/fXsASdG7AEtJSQAkMLsAZGFgAPf39wCvst0AMKTpACg2zABWVFQAN656ABwppwB6d3UAX11cAIZBZwDT2/YAsnWiALiRQgDh4N8AlpORAJ1PeQCQR28AdzdbALR3pQCUl7gAd+j3ACiIWgAysvUA0c/OAHCM8gBN2MMAheD9AKGjyAAumeEAAqvJAGhmZQBDQUIAvLq4ANHZ9QAAw+EAx55IAHFvbQCzt+MA1KhNAEaOhADm5eQAkujwAFdVVQBDxagAesDVAInj/wBgXl0AMazwAN6wUABGREQAL5ZnADu4gwBRT04ASsD5AADW8ACYTHUAlvX+ACw52QBMy7kAT01MAHOP9QDz05MALY/XADOkcgCLc0IAdnRyAEpOYwCTkI4APz4+AO/PjwABt9UAIXdNAJGOjAAAzeoAqazUAEJAQQCkgjkAER+QAI/x+wAuPOMAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAATExMTExMTExMTExMAAAAU1MDVUYlT09IMlNTD1MAADlJGw4kTh0dVBk5OTk5AAAFHyMGCio6OhQRNgUoBQAAPAFSCzdRR0crGBA8PDwAADIVCUEePg0NLj8MMjIyAAAQMC1XPRwzOzgXQxAQEAAAAggpICJWBARNEwcmJiYAAFAnEkQ1QCEhRRpQUFAsAABLS0xKNDFCL0xLS0tLSwAAABYWFhYWFhYWFhYWFgAAAAAAAAAAFgAAFgAAAAAAAAAAAAAAABYAABYAAAAAAAAAAAAAABYAAAAAFgAAAAAAAAAAFhYWAAAAABYWFgAAAP//AADAAwAAgAEAAIABAACAAQAAgAEAAIABAACAAQAAgAEAAIABAACAAQAAwAMAAP2/AAD9vwAA+98AAOPHAAA=" rel="icon" type="image/x-icon" />
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TV Station</title>
<script type="text/javascript">
function openCity(cityName) {
  // Declare all variables
  var i, tabcontent, tablinks;

  // Get all elements with class="tabcontent" and hide them
  tabcontent = document.getElementsByClassName("tabcontent");
  for (i = 0; i < tabcontent.length; i++) {
    tabcontent[i].style.display = "none";
  }

  // Get all elements with class="tablinks" and remove the class "active"
  tablinks = document.getElementsByClassName("tablinks");
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].className = tablinks[i].className.replace(" active", "");
  }

  // Show the current tab, and add an "active" class to the button that opened the tab
  document.getElementById(cityName).style.display = "block";
  document.getElementById("btn" + cityName).className += " active";
  
	hash = cityName
	var node = document.getElementById(hash);
	node.id = "";
	document.location.hash = hash;
	node.id = hash;
}


window.onload = function() {
	if(window.location.hash) {
		openCity(window.location.hash.substr(1));
	}
}

function trimCommercials() {
	if(prompt("This will permanently delete all commercial data older than 10 days ago. Are you sure?")) {
	
	}
}

function ajax(url) {
	var xhr = new XMLHttpRequest();
	xhr.open("GET", url, true);
	// function execute after request is successful 
	xhr.onreadystatechange = function () {
		if (this.readyState == 4 && this.status == 200) {
			alert(this.responseText);
		}
	}
	// Sending our request 
	xhr.send();
}

function flagVideo(id) {
	ajax("/?flag_video="+id);
}

function flagCommercial(id) {
	ajax("/?flag_comm="+id);
}

function unflagVideo(id) {
	ajax("/?unflag_video="+id);
}

function unflagCommercial(id) {
	ajax("/?unflag_comm="+id);
}

</script>
<style type="text/css">
/* Style the tab */
.tab {
  overflow: hidden;
  border: 1px solid #ccc;
  background-color: #f1f1f1;
}

/* Style the buttons that are used to open the tab content */
.tab button {
  background-color: inherit;
  float: left;
  border: none;
  outline: none;
  cursor: pointer;
  padding: 14px 16px;
  transition: 0.3s;
}

/* Change background color of buttons on hover */
.tab button:hover {
  background-color: #ddd;
}

/* Create an active/current tablink class */
.tab button.active {
  background-color: #ccc;
}

/* Style the tab content */
.tabcontent {
  display: none;
  padding: 6px 12px;
  border: 1px solid #ccc;
  border-top: none;
}

.clr-january { background-color:LemonChiffon; }
.clr-february { background-color:DarkOrange; }
.clr-march { background-color:DeepPink; }
.clr-april { background-color:Gold; }
.clr-may { background-color:LightSalmon; }
.clr-june { background-color:SandyBrown; }
.clr-july { background-color:Brown; }
.clr-august { background-color:Thistle; }
.clr-september { background-color:YellowGreen; }
.clr-october { background-color:Peru; }
.clr-november { background-color:LightGray; }
.clr-december {	background-color:LightSeaGreen; }
.clr-local { background-color:CornflowerBlue; }

</style>
</head>
<body>
';
$the_date = "today";

if(isset($_GET["date"])) {
	$tmp_date = DateTime::createFromFormat('m/d/Y', $_GET["date"]);
	$tmp_date_errors = DateTime::getLastErrors();
 
	if ($tmp_date_errors["warning_count"] + $tmp_date_errors["error_count"]==0) $the_date = $tmp_date->format('m/d/y');
}

echo '
<div class="tab">
	<button class="tablinks"><a href="/?date=' . urlencode(date('m/d/y', strtotime($the_date) - 86400)) . '">&lt;&lt;&lt;</a> | <a href="/">'.date("h:i A \o\\n ", time()) . $days[$td] . date(" m/d/Y", time()).'</a></button>
	<button class="tablinks">Free Space: ' . floor( disk_free_space( $drive_loc ) / ( 1024 * 1024 * 1024 ) ) . 'GB</button>
	<button class="tablinks">Load: ' . (sys_getloadavg()[0]*100) . '%</button>
	<button class="tablinks">Uptime: ' . getUptime() . '</button>
	<button class="tablinks">Temp: ' . (round((($int_temp/1000) * (9/5))) + 32) . '<sup>&deg;</sup></button>
	<button class="tablinks" style="background-color:lightblue;"><a href="/?skip=now" style="color:white;">Skip >></a></button>
</div>

<div class="tab">
  <button id="btnShows" class="tablinks active" onclick="openCity(\'Shows\')">Shows</button>
  <button id="btnCommercials" class="tablinks" onclick="openCity(\'Commercials\')">Commercials</button>
  <button id="btnErrors" class="tablinks" onclick="openCity(\'Errors\')">Errors</button>
  <button id="btnSettings" class="tablinks" onclick="openCity(\'Settings\')">Settings</button>
  <button id="btnStats" class="tablinks" onclick="openCity(\'Stats\')">Stats</button>
</div>

<!-- Tab content -->
<div id="Shows" class="tabcontent" style="display:block;">
  <h3>' . $the_date . '\'s Shows</h3>
<table border=1 cellspacing=1 callpadding=8 width="40%">
<tr style="background:lightgray;"><td align=center>Time</td><td style="padding-left:20px;">Show Name</td><td align=center>Type</td><td>Flag</td></tr>
  ';
  
  
$showTypeColors = ["Reruns"=>"F8FFA2","Thanksiving"=>"D68B00", "Cartoons"=>"A2FFEF","Specials"=>"FFA2A2","Primetime"=>"dd8888","Gameshows"=>"88AAFF","Movies"=>"A2FFAC", "Christmas"=>"A2B9FF","Monday"=>"D5A2FF","Tuesday"=>"F5A2DF","Wednesday"=>"A5F2DF","Thursday"=>"D5F2AF","Friday"=>"F5F2FF","Saturday"=>"F5D2AF","Sunday"=>"A5D2FF","none"=>"fff"];

$shows_cnt = 0;



$res = $mysqli->query("SELECT * FROM played WHERE played>=" . strtotime($the_date .' 00:00') . " AND played<=" . strtotime($the_date . ' 23:59') . "  ORDER BY id DESC") or die($mysqli->error);

while ($row = $res->fetch_assoc()) {
	if(!$row) break;
	$showType = getShowType($row["short_name"], $parsedShows);

	$a = strpos($row["name"], "%T(");
	$b = strpos($row["name"], ")%", $a+1);
	$len = "";
	if($a>-1 && $b>-1) {
		$len = gmdate("H:i:s", (int)substr($row["name"], $a + 3, $b-$a - 3));
	}

	echo '<tr style="background:#'.$showTypeColors[$showType].';"><td align=center>' . date("h:i A", $row["played"]) . '</td><td style="padding-left:20px;"><a href="/?video=' . $row["name"] . '" title="'.strtolower(preg_replace("~[_\W\s]~", '', basename($row["name"]))).'">' . $row["short_name"] . '</a> ' . $len . ' </td><td align=center>'.$showType.'</td><td align="center">' . ($row["flag"]=="0" ? '<input type="button" value="flag" onclick="flagVideo('.$row["id"].');">' : '<input type="button" value="unflag" onclick="unflagVideo('.$row["id"].');">') . '</td></tr>';
	$shows_cnt++;
}


echo '</table><br />
<br />
' . $shows_cnt . ' shows aired today.<br />

</div>

<div id="Commercials" class="tabcontent">
  <h3>Commercials</h3>
<table border=1 cellspacing=1 callpadding=8 width="25%">
<tr style="background:lightgray;"><td align=center>Time</td><td align=center>Month</td><td style="padding-left:20px;">Commercial</td><td>Delete</td><td align="center">Flag</td></tr>
';

$comms_cnt = 0;
$bit = 1;

//echo strtotime('today 00:01');
//echo strtotime('today 23:59');

$res = $mysqli->query("SELECT * FROM commercials WHERE played>=" . strtotime($the_date . ' 00:00') . " AND played<=" . strtotime($the_date . ' 23:59') . " ORDER BY id DESC") or die($mysqli->error);
$comm_months = [];


while ($row = $res->fetch_assoc()) {
	//if(date("w", $row["played"])!=$td) break;
	$splits = explode('/', $row["name"]);
	if(array_key_exists($splits[1], $comm_months)==false) { $comm_months[$splits[1]]=1; } else { $comm_months[$splits[1]]++; }
	$a = strpos($splits[2], "%T(");
	$b = strpos($splits[2], ")%", $a+1);
	$len = "";
	if($a>-1 && $b>-1) {
		$len = gmdate("H:i:s", (int)substr($splits[2], $a + 3, $b-$a - 3));
	}
	echo '<tr class="clr-'.$splits[1].'"><td align=center>' . date("h:i&\\nb\\sp;A", $row["played"]) . '</td><td align=center><a href="/commercials.php?folder=' . $splits[1] . '">' . $splits[1] . '</a></td><td style="padding-left:20px;"><a href="/?video=' . $row["name"] . '">' . $splits[2] . '</a> ' . $len . '</td><td><a href="/?delete=' . $row["name"] . '">[delete]</a></td><td align="center">' . ($row["flag"]=="0" ? '<input type="button" value="flag" onclick="flagCommercial('.$row["id"].');">' : '<input type="button" value="unflag" onclick="unflagCommercial('.$row["id"].');">') . '</td></tr>';
	$comms_cnt++;
}

echo '</table><br />
<br />
';

foreach($comm_months as $k=>$v) {
	
	echo '<span class="clr-'.$k.'">'."$v commercials from $k (" . round(($v / $comms_cnt)*100,1) . "%)</span><br />\n";

}

echo '
<br />' . $comms_cnt . ' commercials today.<br />
</div>

<div id="Errors" class="tabcontent">
  <h3>Errors</h3>
  ';

$res = $mysqli->query("SELECT * FROM errors WHERE name NOT LIKE '%UPTIME%' ORDER BY id DESC LIMIT 50") or die($mysqli->error);
$odate = "";
while ($row = $res->fetch_assoc()) {
	$date = date("m/d/Y", $row["played"]);
	$time = date("h:i A", $row["played"]);
	if($odate != $date) {
		echo "</ol><h2>$date</h2><ol>";
		$odate = $date;
	}
	$rows = explode("|", $row["name"]);
	echo "<li><div>$time: " . $rows[0] . '<br /><ul>';
	for($i=1;$i<count($rows);$i++) {
		echo '<li>' . $rows[$i] . "</li>\r\n";
	}
	echo '</ul></div></li>';
}

echo '
</ol>
</div>
<div id="Settings" class="tabcontent">
<h3>Settings</h3>
<p>
';

echo '
<ul>
	<li><a href="/?clear_cache=now">Clear Show Names Cache</a></li>
	<li><a href="/?trim_commercials=now" style="color:red;" onclick="return confirm(\'This will permanently delete all commercials data older than 10 days. Are you sure?\')">Trim Commercials Log</a></li>
	<li><a href="/?trim_errors=now" style="color:red;" onclick="return confirm(\'This will permanently delete all error data older than 10 days. Are you sure?\')">Trim Errors Log</a></li>
	<br />
	<li><a href="https://docs.google.com/spreadsheets/d/1QADkcJlcQRP1PPGCcgFtUiBjNtF-gjDE1SO4lcrBosk/" target="_new">TV Schedule</a></li>
	<li><a href="/?youtube_download=1">Youtube-DL Interface</a></li>
	<li><a href="/phpmyadmin">phpMyAdmin</a></li>
	<li><a href="/dir.php">Browse Videos</a></li>
	<li><a href="/programming.php">Programming</a></li>
	<li><a href="/videoeditor.php">Video Editor</a></li>
	<br />
	<li><a href="/?reboot=now" style="color:red;" onclick="return confirm(\'Are you sure?\')">Reboot</a></li>
	<br />
	<h3>Flagged Videos</h3>
	<br />
	<ul>
';

$flagged = false;

$res = $mysqli->query("SELECT * FROM played WHERE flag!=0 ORDER BY id DESC") or die($mysqli->error);

while ($row = $res->fetch_assoc()) {
	if(!$row) break;
	echo '<li>'.$row["name"].' | <input type="button" value="unflag" onclick="unflagVideo('.$row["id"].');"></li>';
	$shows_cnt++;
	$flagged = true;
}

if(!$flagged) echo 'Nothing to see here';

echo '
	</ul>
	<h3>Flagged Commercials</h3>
	<br />
	<ul>
';

$flagged = false;

$res = $mysqli->query("SELECT * FROM commercials WHERE flag!=0 ORDER BY id DESC") or die($mysqli->error);

while ($row = $res->fetch_assoc()) {
	if(!$row) break;
	echo '<li>'.$row["name"].' | <input type="button" value="unflag" onclick="unflagCommercial('.$row["id"].');"></li>';
	$shows_cnt++;
	$flagged = true;
}

if(!$flagged) echo 'Nothing to see here';

echo '
	</ul>
</ul>
';



echo '
</p>
</div>

';

echo '
<div id="Stats" class="tabcontent">
<table border=1 cellspacing=1 callpadding=8 width="25%">
<tr style="background:lightgray;"><td>Type</td><td>Show Name</td><td>Count</td></tr>
  ';
  
$res = $mysqli->query("SELECT *, COUNT(`short_name`) AS `value_occurrence` FROM `played` GROUP BY `short_name` ORDER BY `value_occurrence` DESC") or die($mysqli->error);

$arrTypes = [];
$arrCount = [];
while ($row = $res->fetch_assoc()) {
	$showType = getShowType($row["short_name"], $parsedShows);
	if(isset($arrTypes[$showType])) {
		$arrCount[$showType] += ($row["value_occurrence"]*1);
		$arrTypes[$showType]++;
	} else {
		$arrCount[$showType] = ($row["value_occurrence"]*1);
		$arrTypes[$showType] = 1;
	}
	echo '<tr style="background:#'.$showTypeColors[$showType].';"><td>' . $showType . '</td><td>' . $row["short_name"] . '</td><td align="center">' . $row["value_occurrence"] . '</td></tr>';
}

echo '</table><br />
<br />
<table border=1 cellspacing=1 callpadding=8 width="25%">
<tr style="background:lightgray;"><td>Type</td><td>Shows</td><td>Count</td></tr>
  ';

arsort($arrTypes);
foreach($arrTypes as $k=>$v) {
	echo '<tr style="background:#'.$showTypeColors[$k].';"><td>' . $k . '</td><td align="center">' . $v . '</td><td align="center">' . $arrCount[$k] . '</td></tr>';
}
  
  echo '</table>
</div>

';


echo '

</body>
</html>';

?>