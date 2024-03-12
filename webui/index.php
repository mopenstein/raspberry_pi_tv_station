<?php
/*
# Do not expose your Raspberry Pi directly to the internet via port forwarding or DMZ.
# This software is designed for local network use only.
# Opening it up to the web will ruin your day.


		{
			"name": ["%D[2]%/commercials/january/any", "%D[2]%/commercials/february/any", "%D[2]%/commercials/march/any", "%D[2]%/commercials/april/any", "%D[2]%/commercials/may/any", "%D[2]%/commercials/june/any",
				 "%D[2]%/commercials/july/any", "%D[2]%/commercials/august/any", "%D[2]%/commercials/september/any", "%D[2]%/commercials/october/any", "%D[2]%/commercials/november/any", "%D[2]%/commercials/december/any"],
			"month": [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
			"prefer-folder": "yes",
			"chance": ".125"
		},


*/

error_reporting(E_ALL);
ini_set("display_errors", 1);


//######################### settings
require_once("settings.class.inc");

$Settings = new Settings();
$json_settings = $Settings->load("/home/pi/Desktop/settings.json");
	if($json_settings[0]==null) {
		die('<div style="background:red;color:white;margin:10px;padding:10px;">JSON VALIDATION ERROR :: CHECK \'settings.json\' FILE! <br /><br />Error: '.$json_valid[1].' - <a href="https://duckduckgo.com/?q=json+validator&ia=answer">Validate</a></div>');		
	}
$json_settings = $json_settings[0];

$drive_loc = $json_settings["drive"];

$database_info = $json_settings["web-ui"]["database_info"];

$channels = $json_settings["channels"]["names"];
$channel_file = $json_settings["channels"]["file"];

//######################### /settings

require_once("db_manage_ext.inc");
$db_manage_ext = new DBMANAGEEXT();
$db_manage_ext->load($database_info["host"], $database_info["username"], $database_info["password"], $database_info["database_name"]);

$mysqli = new mysqli($database_info["host"], $database_info["username"], $database_info["password"], $database_info["database_name"]);


if(isset($_GET["insert"]) && isset($_GET["file"]) && isset($_GET["times"])) {

	for($i=0;$i<$_GET["times"] * 1;$i++) {
		$mysqli->real_query("INSERT INTO played (short_name, name, played) VALUES ('" . $_GET["insert"] . "', '" . $_GET["file"] . "', 0)");
		echo ($i+1) . ") INSERT INTO played (short_name, name, played) VALUES ('" . $_GET["insert"] . "', '" . $_GET["file"] . "', 0)<br />\n";
	}
	
	die($_GET["insert"] . " - " . $_GET["file"] . " - " . $_GET["times"]);
}

if(isset($_GET["showstats"]) && is_numeric($_GET["id"])) {
	$res = $mysqli->query("SELECT * FROM played WHERE short_name=\"".addslashes($_GET["showstats"])."\"") or die($mysqli->error);
	$cnt = array();
	echo "{$_GET["showstats"]}{$_GET["id"]}\n";
	while ($brow = $res->fetch_assoc()) {
		$show = preg_replace(["/_NA_/", "/%T.(.*)\)%/"], ["", ""], pathinfo($brow["name"])["filename"]);
		echo date('M d, Y h:i A', $brow["played"]) . " @ $show<br />\n";
		/*
		if(array_key_exists($show, $cnt)) {
			$cnt[$show]++;
		} else {
			$cnt[$show]=1;
		}
		*/
	}
	
	//foreach($cnt as $k => $v) {
	//	echo "$k - $v time(s) <br />\n";
	//}
	die("");
}

if(isset($_GET["flag_video"])) {
	if(is_numeric($_GET["flag_video"])) {
		$res = $mysqli->query("UPDATE played SET flag=1 WHERE id=".addslashes($_GET["flag_video"])) or die($mysqli->error);
		die("video" . $_GET["flag_video"]."|1");
	}
}

if(isset($_GET["unflag_video"])) {
	if(is_numeric($_GET["unflag_video"])) {
		$res = $mysqli->query("UPDATE played SET flag=0 WHERE id=".addslashes($_GET["unflag_video"])) or die($mysqli->error);
		die("video" . $_GET["unflag_video"]."|0");
	}
}

if(isset($_GET["flag_comm"])) {
	if(is_numeric($_GET["flag_comm"])) {
		$res = $mysqli->query("UPDATE commercials SET flag=1 WHERE id=".addslashes($_GET["flag_comm"])) or die($mysqli->error);
		die("comm" . $_GET["flag_comm"]."|1");
	}
}

if(isset($_GET["unflag_comm"])) {
	if(is_numeric($_GET["unflag_comm"])) {
		$res = $mysqli->query("UPDATE commercials SET flag=0 WHERE id=".addslashes($_GET["unflag_comm"])) or die($mysqli->error);
		die("comm" . $_GET["unflag_comm"]."|0");
	}
}

if(isset($_GET["channel"])) {
	if(!isset($channel_file)) die("channel file not defined in settings file");
	
	foreach($channels as $c) {
		if($c==null || $c=="") $c = "default";
		if($_GET["channel"]==$c) {
			if($c=="default") $c="";
			file_put_contents($channel_file, $c);
			$result = exec('sudo ./kill.sh');
			die("Channel set to: ".$c);
			break;
		}
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
	if(file_exists($_GET["video"])) {
		include("videostream.inc");
		header('Content-type: video/mp4');
		$stream = new VideoStream($_GET["video"]);
		$stream->start();
		die();
	} else {
		die("file not found");
	}
}

if(isset($_GET["delete"])) {
	if(file_exists($_GET["delete"])) {
		unlink($_GET["delete"]);
		die("video deleted");
	} else {
		die("file not found");
	}
}

if(isset($_GET["db_manage_ext"])) {
	$dbexe = $db_manage_ext->handle($_GET);
	if($dbexe) {
		die($dbexe);
	} else {
		header("location: /\n\n");
		die();
	}
}

if(isset($_GET["trim_commercials"])) {
	if($_GET["trim_commercials"]=="empty") {
		$res = $mysqli->query("TRUNCATE TABLE `commercials`") or die($mysqli->error);
	} else {
		$res = $mysqli->query("DELETE FROM commercials WHERE played<=" . (time()-864000) . " ORDER BY id DESC") or die($mysqli->error);
	}
	header("location: /\n\n");
	die();
}

if(isset($_GET["trim_errors"])) {
	if($_GET["trim_errors"]=="uptime") {
		$res = $mysqli->query("DELETE FROM `errors` WHERE substring(`name`, 1, 6) = 'UPTIME' AND played <= " . (time()-86400) . " ORDER BY id DESC") or die($mysqli->error);
	} elseif($_GET["trim_errors"]=="empty") {
		$res = $mysqli->query("TRUNCATE TABLE `errors`") or die($mysqli->error);
	} else {
		$res = $mysqli->query("DELETE FROM errors WHERE played<=" . (time()-864000) . " ORDER BY id DESC") or die($mysqli->error);
	}
	header("location: /\n\n");
	die();
}

if(isset($_GET["getshowname2"])) {

	
	//var_dump($a);

		die("");
	
}

function checkShowPlayAmount($sname, $csv) {
	global $mysqli;
	if(isset($_GET["test"])) echo "$sname\n";
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
		if(isset($_GET["test"])) echo $brow["short_name"] ." = ". $brow["value_occurrence"] ."\n";
	}
	
	$selected = null;
	//populate each type with its shows and how many times it has been played
	//and whilst doing so, set the current type so we can compare the played times against only the same type of shows
	for($i=1;$i<count($csv);$i++) {
		for($j=0;$j<count($csv[$i]);$j++) {
			$conv_short_name = $csv[$i][$j];
			if($conv_short_name!="") {
				
				if(strpos("S".$conv_short_name, "=")>0) {
					$conv_short_name = substr($conv_short_name, strpos($conv_short_name, "=") + 1);
				}
				
				if(isset($shows[$conv_short_name])) $cnt = $shows[$conv_short_name]; else $cnt = 0;
				

				array_push($narr[$keys[$j]], [$conv_short_name, $cnt]);
				if($conv_short_name == $sname) {
					$selected = $keys[$j];
				}
			}
		}
	}
	if(isset($_GET["test"])) echo "///////////////////////////////narr\n";
	if(isset($_GET["test"])) var_dump($narr);
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

	if(isset($_GET["test"])) echo "///////////////////////////////list\n";
	if(isset($_GET["test"])) var_dump($list);
	if(isset($_GET["test"])) echo $lowest."\n";
	if(isset($_GET["test"])) echo $highest."\n";

	$exit=true;

	//check to make sure not every show has been played an equal amount of times
	//by seeing if the highest play count is the same for each show
	for($i=0;$i<count($list);$i++) {
		//echo($list[$i][0] . ", " . $list[$i][1] . ", " . $highest . "\n");
		if($list[$i][1] != $highest) { 
			$exit=false;
			break;
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

function getTvShowName_OLD($filename, $showList) {
	if(strrpos($filename, "%T(")===false) {
		$filename = basename($filename);
	} else {
		$filename = basename(substr($filename,0,strrpos($filename, "%T(")));
	}
	foreach($showList as $k=>$v) {
		for($i=0;$i<count($v);$i++) {
			if(strpos("a".strtolower(preg_replace("~[_\W\s]~", '', $filename)), strtolower(preg_replace("~[_\W\s]~", '', basename($v[$i])))) > 0) {
				return $v[$i];
			}
		}
	}
	return $filename;
}

function getTvShowName($filename, $showList) {
	if(strrpos($filename, "%T(")===false) {
		$filename = basename($filename);
	} else {
		$filename = basename(substr($filename,0,strrpos($filename, "%T(")));
	}
	foreach($showList as $k=>$v) {
		for($i=0;$i<count($v);$i++) {
			$names = explode("=",$v[$i],2);
			$name = strtolower(preg_replace("~[_\W\s]~", '', $names[0]));
			if(!$name) continue;
			if(strpos("a".strtolower(preg_replace("~[_\W\s]~", '', $filename)), $name) > 0) {
				return (count($names)==1 ? $v[$i] : $names[1]);
			}
		}
	}
	return $filename;
}

function parseCSV($csv, $verbose = null) {
    $csv = array_map('str_getcsv', explode("\r\n", $csv));
	$narr = [];
	$keys = [];

	//create an array of types (reruns, primetime, cartoons, etc)
	//and also an array of keys for each type's name
	for($i=0;$i<count($csv[0]);$i++) {
		$keys[$i] = $csv[0][$i];
		$narr[$keys[$i]] = [];
		if($verbose!=null) echo "Created category <i>{$keys[$i]}</i><br />\n";
	}

	for($i=1;$i<count($csv);$i++) {
		for($j=0;$j<count($csv[$i]);$j++) {
			if(preg_replace('/[^\da-z]/i', '', $csv[$i][$j])!="") {
				array_push($narr[$keys[$j]], $csv[$i][$j]);
				if($verbose!=null) echo "Added <b>'{$csv[$i][$j]}'</b> to <i>{$keys[$j]}</i> <small>verified against: '" . preg_replace('/[^\da-z]/i', '', $csv[$i][$j]) . "'</small><br />\n";
			} else {
				//
				//file_get_contents("http://127.0.0.1/?error=parseCSV|PHP|check%20your%20spreadsheet%20it%20contains%20an%20all%20white%20spaced%20entry");
				if($csv[$i][$j]!="") echo "<h1 style=\"color:red;\">Check your spreadsheet. It contains an all white space entry</h1>";
			}
		}
	}
	return $narr;
}


function getShowType($sname, $showList) {
	foreach($showList as $k=>$v) {
		for($i=0;$i<count($v);$i++) {
			$names = explode("=",$v[$i],2);
			if(count($names)>1) {
				if($sname==$names[1]) return $k;
			}
			if($sname==$names[0]) return $k;
		}
	}
	
	return "none";
}


function getShowNames($url, $force) {
	$murl = md5($url) . ".cache";
	
	if(!$force && file_exists($murl)) {
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


if(isset($_GET["clear_cache"])) {
	$wasted = parseCSV(getShowNames('https://docs.google.com/spreadsheets/d/1QADkcJlcQRP1PPGCcgFtUiBjNtF-gjDE1SO4lcrBosk/export?format=csv&id=1QADkcJlcQRP1PPGCcgFtUiBjNtF-gjDE1SO4lcrBosk&gid=0', true), true);
	die();
}

$unparsedCSV = getShowNames('https://docs.google.com/spreadsheets/d/1QADkcJlcQRP1PPGCcgFtUiBjNtF-gjDE1SO4lcrBosk/export?format=csv&id=1QADkcJlcQRP1PPGCcgFtUiBjNtF-gjDE1SO4lcrBosk&gid=0', false);
$parsedShows = parseCSV($unparsedCSV);

function getPathFromShortName($shortname) {
	global $mysqli;
	$res = $mysqli->query("SELECT name FROM `played` WHERE short_name='".addslashes($shortname)."' LIMIT 1") or die($mysqli->error);
	$brow = $res->fetch_assoc();
	if(!$brow) return null;
	return dirname($brow["name"]);
}

function getAvailableShows($sname, $sdir, $csv) {
	global $mysqli;
	
	$dirs_in_dir = $directories = glob($sdir . '/*' , GLOB_ONLYDIR);
	if(isset($_GET["test"])) var_dump($dirs_in_dir);
	
	if(isset($_GET["test"])) echo "$sname\n";
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
		if(isset($_GET["test"])) var_dump($brow);
	}

	$selected = null;
	//populate each type with its shows and how many times it has been played
	//and whilst doing so, set the current type so we can compare the played times against only the same type of shows
	for($i=1;$i<count($csv);$i++) {
		for($j=0;$j<count($csv[$i]);$j++) {
			$conv_short_name = $csv[$i][$j];
			if($conv_short_name!="") {
				
				if(strpos("S".$conv_short_name, "=")>0) {
					$conv_short_name = substr($conv_short_name, strpos($conv_short_name, "=") + 1);
				}
				
				if(isset($shows[$conv_short_name])) $cnt = $shows[$conv_short_name]; else $cnt = 0;
				
				array_push($narr[$keys[$j]], [$conv_short_name, $cnt]);
			}
		}
	}
	
	$selected = $sname;
	
	if(isset($_GET["testx"])) echo "///////////////////////////////narr\n";
	if(isset($_GET["testx"])) var_dump($narr);
	if(isset($_GET["testx"])) echo "///////////////////////////////selected\n";
	if(isset($_GET["testx"])) var_dump($selected);
	$highest = -1;
	$lowest = 999999;
	$lowshow = "";
	$list = [];
	if($selected!=null) {
		if(!in_array($selected, array_keys($narr))) die('0');
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

	for($i=0;$i<count($list);$i++) {
		if(!isset($list[$i][2])) {
			$dirshort = getPathFromShortName($list[$i][0]);
			if($dirshort) { //show has been played before
				$list[$i][2] = $dirshort;
			} else { //show hasn't been played before
				$list[$i][2] = "";
			}
		}
	}


	//sometimes every show hasn't been played yet
	//so we to compare the results versus the actual directory structure
	//if a show hasn't been played yet, we need to add it to the list as being played 0 times

	foreach($dirs_in_dir as $d) {
		$d = str_replace("//", "/", $d);
		
		$skip = true;
		for($i=0;$i<count($list);$i++) {
			if($d == $list[$i][2]) {
				$skip = true;
				break;
			} else {
				$skip = false;
			}
		}
		
		if(isset($_GET["test"])) echo "$d & " . ($skip ? "true" : "false") . "\n";
		
		if(!$skip) {
			array_push($list, [-1, "test", $d]);
			$lowest=0;
		}
	}

	if(isset($_GET["test"])) echo "///////////////////////////////list\n";
	if(isset($_GET["test"])) var_dump($list);
	if(isset($_GET["test"])) echo $lowest."\n";
	if(isset($_GET["test"])) echo $highest."\n";

	$exit=true;
	$retlist = [];
	//check to make sure not every show has been played an equal amount of times
	//by seeing if the highest play count is the same for each show
	for($i=0;$i<count($list);$i++) {
		array_push($retlist, $list[$i][2]);
		if($list[$i][1] != $highest && $highest!=$lowest) { 
			$exit = false;
		}
	}


	if($exit) {
		return array_filter($retlist);
	}

	//see if the current show is in the list of highest shows
	//if it is, it shouldn't be played
	$retlist = [];
	for($i=0;$i<count($list);$i++) {
		if(isset($_GET["test"])) {
			echo $highest . " - " . $list[$i][2] . " - " . $list[$i][0] . " - " . $list[$i][1] . "\n";
		}
		if($list[$i][1] != $highest && $list[$i][1]!=-1) { 
			array_push($retlist, $list[$i][2]);
		}
	}

	//play the current show
	return array_filter($retlist);
}


if(isset($_GET["getavailable"]) && isset($_GET["dir"])) {
	if(!is_dir($_GET["dir"])) die('0');
	$shortname=getTvShowName($_GET["getavailable"], $parsedShows);
	$showType=getShowType($shortname, $parsedShows);
	if(isset($_GET["h"])) {
		die("<h1>$shortname $showType</h1>\n" . implode("<br />\n", (getAvailableShows($showType, $_GET["dir"], $unparsedCSV))));
	} else {
		die(implode("\n", (getAvailableShows($showType, $_GET["dir"], $unparsedCSV))));
	}
}


if(isset($_GET["getshowname"])) {

	if(strpos(strtolower($_GET["getshowname"]), '/commercials/') > 0) {
		//a commercial is being payed, ignore 
		die("commercial|0");
	}

	$shortname=getTvShowName($_GET["getshowname"], $parsedShows);
	$showType=getShowType($shortname, $parsedShows);
	
	$row=0;
	$remote_diff_set = false;

	$time_diff = 7200;
	$row = 0;
	//xmas time, double the time difference
	if(isset($_GET["min_time"])) {
		if(is_numeric($_GET["min_time"])) {
			if($_GET["min_time"]*1!=0) {
				$time_diff = $_GET["min_time"]*1;
				$remote_diff_set = true;
			}
		}
	}
		
	$res = $mysqli->query("SELECT played FROM played WHERE short_name='" . addslashes($shortname) . "' AND played>=" . (time()-$time_diff) ." AND played<=" . (time()-1) . "  LIMIT 1") or die($mysqli->error);
	if(isset($_GET["test"])) echo "SELECT played FROM played WHERE short_name='" . addslashes($shortname) . "' AND played>=" . (time()-$time_diff) ." AND played<=" . (time()-1) . "  LIMIT 1\n\n";
	$row = $res->fetch_row()[0]*1;
	if(isset($_GET["test"])) var_dump($row);
	
	//if(isset($_GET["test"])) var_dump(checkShowPlayAmount($shortname, $unparsedCSV));
	
	if($remote_diff_set && $row>0) { // a custom time difference has been set, so we shouldn't play this video as it was last played with the custom time
		die("$shortname|$row|$showType|remote diff triggered|played within the last $time_diff seconds");
	}
	
	if(checkShowPlayAmount($shortname, $unparsedCSV)) { // we should then check if it is one of the top shows of that type
		if(isset($_GET["test"])) echo "not played too much\n";
		//it is not, so we should play something else
		die("$shortname|0|$showType|has not been played too much|$row|$remote_diff_set");
	} else {
		if(isset($_GET["test"])) echo "has not been played too much\n";
		//show is NOT one of the top of its category
		// just in case the show has been played to recently, we need to override that because there might not be anything else available to play
		die("$shortname|".time()."|$showType|has been played enough|$row|$remote_diff_set");
	}		
	
	die("$shortname|$row|$showType");
}

if(isset($_GET["get_next_episode"])) {
	$nv = addslashes($_GET["get_next_episode"]);
	$shortname = getTvShowName($nv, $parsedShows);
	$dir_name = dirname($nv);
	$sql = "SELECT * FROM played WHERE LEFT(name, ".strlen($dir_name).") = '".$dir_name."' ORDER BY played DESC LIMIT 1";
	$res = $mysqli->query($sql) or die($mysqli->error);

	if(isset($_GET["dump"])) {
		echo "Looking up episodes that played for: <i>".$dir_name."</i> <b>$shortname</b> SQL: ";
		echo "$sql\n<br />";
	}
	
	if(is_dir($dir_name) == false) {
		die("|0|video directory does not exist|$dir_name|$nv");
	}
	
	if(mysqli_num_rows($res)==0) {
		$files = glob($dir_name.'/*');
		die($files[0]."|1|play first episode since no episodes of this show has been played yet");
		//die("|0|No recently played episodes found|$nv|$dir_name|".$files[0]);
	}
	$row = $res->fetch_row();
	//var_dump($row);
	if(file_exists($row[2]) == false) { //last played file does not exist
		if(isset($_GET["dump"])) echo "Last played File Does Not Exist! " . $row[2] . "<br />\n";
		die("|0|last played file does not exist. was it deleted?|$nv");
	} else {
		if(isset($_GET["dump"])) echo "Last played: " . $row[2] . "<br />\n";
	}
	if(is_dir(dirname($row[2])) == false) {
		die("|0|directory does not exist|".$row[2]."|$nv");
	}
	
	
	$files = glob(dirname($row[2]).'/*');
	if(isset($_GET["dump"])) var_dump($files);
	$n=1;
	for($i = 0;$i<count($files);$i++) {
		if($files[$i] == $row[2]) {
			$n=1;
			if($i+$n>=count($files)) {
				//echo "every episode has aired, go back to the beginning.<br />";
				$i=0;
				$n=0;
			} else {
				if(strpos(strtolower($files[$i+1]), ".commercials") !== false) {
					//echo "Next file is a commercials file, go to the file after that.<br />";
					$n++;
				}
				if($i+$n>=count($files)) {
					//echo "every episode has aired, go back to the beginning.<br />";
					$i=0;
					$n=0;
				}
			}
			//echo "Next episode: ". $files[$i+$n] . "<br />";
			if(file_exists($files[$i+$n])) {
				if(isset($_GET["h"])) {
					die("<h1>$shortname</h1>\nNext File: " . basename($files[$i+$n]) . "<br />\n<br />\nPath: " . dirname($files[$i+$n]) .  "<br />\n");
				} else {
					die($files[$i+$n]."|1|next episode");
				}
			} else {
				die("|0|file does not exist ".$files[$i+$n]);
			}
			break;
		}
	}
	die("|0|unknown error|$nv");
}

function getRandomVideoByCount($directory) {
	global $mysqli;
	$dir_name = addslashes($directory);
	
	if(substr($dir_name, -1)!="/") $dir_name.="/";
	if(substr($dir_name, -1)!="/") $dir_name.="/";
	if(isset($_GET["dump"])) var_dump($dir_name);
	
	$sql = "SELECT * FROM played WHERE LEFT(name, ".strlen($dir_name).") = '".$dir_name."'";
	$res = $mysqli->query($sql) or die($mysqli->error);

	if(is_dir($dir_name) == false) {
		die("|0|video directory does not exist|$dir_name");
	}

	$files = glob($dir_name."*.{mp4,mkv,avi,mpeg,mpg,mov,webm,m4v,flv,wmv}", GLOB_BRACE);
	
	if(count($files)==0) {
		die($directory."|0|no files exist in directory|$dir_name");
	}
	
	if(mysqli_num_rows($res)==0) {
		die($files[array_rand($files)]."|1|play any random episode since no episodes of this show has been played yet|$dir_name");
	}
	
	$videos_played = [];
	
	foreach($files as $v) {
		$videos_played[$v] = 0;
	}
	
	while ($brow = $res->fetch_assoc()) {
		if(isset($videos_played[$brow["name"]])) {
			$videos_played[$brow["name"]]++;
		} else {
			$videos_played[$brow["name"]]=1;
		}
	}
	
	arsort($videos_played);
	$max = 0;
	$min = 9999999;
	foreach($videos_played as $k => $v)	{
		if($v > $max) $max = $v;
		if($v < $min) $min = $v;
	}

	if(isset($_GET["dump"])) var_dump($max, $min);
	
	if($min != $max) {
		foreach($videos_played as $k => $v)
		{
			if($v > $min) unset($videos_played[$k]);
		}
	}
	
	if(isset($_GET["dump"])) var_dump($videos_played);
	
	return array_keys($videos_played)[array_rand(array_keys($videos_played))];

}

if(isset($_GET["get_next_rnd_episode_from_dir"])) {
	$tmp="";
	$dirs = [];
	for($i=1;$i<100;$i++) {
		if(isset($_GET["f$i"])) {
			$dir_name = $_GET["f$i"];
			if(substr($dir_name, -1)!="/") $dir_name.="/";
			if(file_exists($dir_name)) {
				$sql = "SELECT COUNT(short_name) AS t FROM played WHERE LEFT(name, ".strlen($dir_name).") = '".$dir_name."'";
				
				$res = $mysqli->query($sql) or die($mysqli->error);
				$brow = $res->fetch_assoc();
				
				$total_play_count = $brow["t"]*1;
				$total_file_count = iterator_count(new FilesystemIterator($dir_name, FilesystemIterator::SKIP_DOTS));
				$weighted = $total_play_count / $total_file_count;
				$dirs[($i-1)] = [$weighted, $dir_name];
			}
		} else {
			break;
		}
	}
	
	if(isset($_GET["dump"])) var_dump($dirs);
	
	$high = -1; $low = 999999;
	for($i=0;$i<count($dirs);$i++) {
		if($dirs[$i][0]>$high) $high=$dirs[$i][0];
		if($dirs[$i][0]<$low) $low=$dirs[$i][0];
	}
	
	
	if(isset($_GET["dump"])) {
		var_dump($high);
		var_dump($low);
	}
	$c=count($dirs);
	for($i=0;$i<$c;$i++) {
		if(isset($_GET["dump"])) {
			echo $dirs[$i][1] . " - " . $dirs[$i][0] . " - " . $low . " - " . ($dirs[$i][0]>$low) . "\n";
		}
		if($dirs[$i][0]>$low) unset($dirs[$i]);
	}
	
	if(isset($_GET["dump"])) var_dump($dirs);
	
	die(getRandomVideoByCount($dirs[array_rand($dirs)][1]) . "|1|random video from random directory selected");
}

if(isset($_GET["get_next_rnd_episode"])) {
	die(getRandomVideoByCount($_GET["get_next_rnd_episode"]) . "|1|random video selected");
}


if(isset($_GET["getshowtestname"])) {

//if day = sunday and time > 5am and time < 10am

	if(strpos($_GET["getshowtestname"], '/commercials/') > 0) {
		//a commercial is being payed, ignore 
		die("commercial|0");//."|".$brow["value_occurrence"]);
	}

	$dayofweek = date("l",time());
	$hourofday = date("G",time());

	//$thanksgiving = strpos("=".$_GET["getshowname"], "thanksgiving");

	$shortname=getTvShowName2($_GET["getshowtestname"], $parsedShows);
	$showType=getShowType($shortname, $parsedShows);
	

	die("$shortname||$showType");//."|".$brow["value_occurrence"]);
}


$mntcont = strlen($drive_loc[0]);

if(isset($_GET["current_video"])) {
	if(strpos($_GET["current_video"], '/commercials/') > 0) {
		die(1);
	}
	$mysqli->real_query("INSERT INTO played (short_name, name, played) VALUES ('" . addslashes(getTvShowName($_GET["current_video"], $parsedShows)) . "', '" . addslashes($_GET["current_video"]) . "', ". time() . ")");
	
	die(1);
}

if(isset($_GET["error"])) {
	$erex = explode("|", $_GET["error"]);
	if($erex[0]=="UPTIME") {
		//only save the most recent uptime tick, otherwise the database will become bloated
		//remove all previous uptime reports
		$res = $mysqli->query("DELETE FROM `errors` WHERE substring(`name`, 1, 6) = 'UPTIME'") or die($mysqli->error);
		//add the most recent
		$mysqli->real_query("INSERT INTO errors (name, played) VALUES ('" . addslashes($_GET["error"]) . "', ". time() . ")");
	} else {
		$mysqli->real_query("INSERT INTO errors (name, played) VALUES ('" . addslashes($_GET["error"]) . "', ". time() . ")");
	}
	
	die(1);
}

if(isset($_GET["current_comm"])) {
	$mysqli->real_query("INSERT INTO commercials (name, played) VALUES ('" . addslashes($_GET["current_comm"]) . "', ". time() . ")");
	
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

function invertColor($hex) {
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) !== 6) {
        return '#000000';
    }
    $new = '';
    for ($i = 0; $i < 3; $i++) {
        $rgbDigits = 255 - hexdec(substr($hex, (2 * $i), 2));
        $hexDigits = ($rgbDigits < 0) ? 0 : dechex($rgbDigits);
        $new .= (strlen($hexDigits) < 2) ? '0' . $hexDigits : $hexDigits;
    }
    return '#' . $new;
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
			//console.log(this);
			if(this.responseURL.indexOf("unflag")>-1) {
				document.getElementById(this.responseText.split("|")[0]).style.display = "none";
				var btn = document.getElementById("unflag_" + this.responseText.split("|")[0]);
				console.log(btn);
				if(btn!=null) {
					btn.value = "flag";
					btn.onclick=function() { alert("test"); };
				}
				console.log(btn);
			} else if(this.responseURL.indexOf("showstats")>-1) {
				document.getElementById(this.responseText.split(/\n/)[0]).innerHTML = this.responseText.substring(this.responseText.indexOf("\n")+1);
			} else {
				alert(this.responseText);
			}
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
body {
	background-color:#222;
	color"#fff;
}

/* Style the tab */
.tab {
  overflow: hidden;
  border: 1px solid #ccc;
  background-color: #444;
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
  color:#fff;
}

/* Change background color of buttons on hover */
.tab button:hover {
  background-color: #ddd;
  color: #000;
}

/* Create an active/current tablink class */
.tab button.active {
  background-color: #ccc;
  color: #000;
}

/* Style the tab content */
.tabcontent {
  display: none;
  padding: 6px 12px;
  border: 1px solid #ccc;
  border-top: none;
  color:#fff;
}

a {
	color:#aaf;
}

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

$curr_channel = file_get_contents($channel_file);

echo '
<div class="tab">
	<button class="tablinks"><a href="/?date=' . urlencode(date('m/d/y', strtotime($the_date) - 86400)) . '">&lt;&lt;&lt;</a> | <a href="/">'.date("h:i A \o\\n ", time()) . $days[$td] . date(" m/d/Y", time()).'</a></button>
	<button class="tablinks">Current Channel: '.($curr_channel == "" ? "default" : $curr_channel).'</button>
	<button class="tablinks">Free Space: 
	<span style="background-color:#'.chr(97).chr(97).chr(97).'">Root SD ' . floor( disk_free_space("/.") / ( 1024 * 1024 * 1024 ) ) . 'GB</span>
	';
for($i = 0;$i<count($drive_loc);$i++) {
	echo '<span style="background-color:#'.chr(98+$i).chr(98+$i).chr(98+$i).'">Disk ' . ($i+1) . ' ' . floor( disk_free_space( $drive_loc[$i] ) / ( 1024 * 1024 * 1024 ) ) . 'GB</span> ';
}
 
 

echo '</button>
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
  
  
$showTypeColors = ["Reruns"=>"F8FFA2","Thanksgiving"=>"DAA520", "Cartoons"=>"A2FFEF","Specials"=>"FFA2A2","Primetime"=>"dd8888","Gameshows"=>"88AAFF","Movies"=>"A2FFAC", "Christmas"=>"A2B9FF","sum_Monday"=>"D5A2FF","sum_Tuesday"=>"F5A2DF","sum_Wednesday"=>"A5F2DF","sum_Thursday"=>"D5F2AF","sum_Friday"=>"F5F2FF","sum_Saturday"=>"F5D2AF","sum_Sunday"=>"A5D2FF","win_Monday"=>"D5A2FF","win_Tuesday"=>"F5A2DF","win_Wednesday"=>"A5F2DF","win_Thursday"=>"D5F2AF","win_Friday"=>"F5F2FF","win_Saturday"=>"F5D2AF","win_Sunday"=>"A5D2FF","90s shows"=>"C5C2CF","90s cartoons"=>"CCC2CC","none"=>"fff"];

if(array_key_exists("web-ui", $json_settings)) {
	if(array_key_exists("show_type_colors", $json_settings["web-ui"])) {
		//load show colors from settings file
		$showTypeColors = $json_settings["web-ui"]["show_type_colors"];
	}
}

function getShowTypeColor($type) {
	global $showTypeColors;
	if(array_key_exists($type, $showTypeColors)) {
		return $showTypeColors[$type];
	} else {
		return "ffffff";
	}
}

function getCommercialTypeColor($type) {
	global $json_settings;
	if(array_key_exists($type, $json_settings["web-ui"]["commmercial_type_colors"])) {
		return $json_settings["web-ui"]["commmercial_type_colors"][$type];
	} else {
		return "ffffff";
	}
}

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
	
	//$showTypes = explode("_", $showType);
	//if(count($showTypes)>1) $showType=$showTypes[1];
	echo '<tr style="background:#'.getShowTypeColor($showType).';"><td align=center>' . date("h:i A", $row["played"]) . '</td><td style="padding-left:20px;"><a href="/?video=' . $row["name"] . '" style="color:' . invertColor(getShowTypeColor($showType)) . ';" title="'.strtolower(preg_replace("~[_\W\s]~", '', basename($row["name"]))).'">' . $row["short_name"] . '</a> ' . $len . ' <a href="/commercials-times.php?video=' . $row["name"] . '" style="color:' . invertColor(getShowTypeColor($showType)) . ';">&copy;</a></td><td align=center>'.$showType.'</td><td align="center">' . ($row["flag"]=="0" ? '<input type="button" value="flag" id="flag_video'.$row["id"].'" onclick="flagVideo('.$row["id"].');">' : '<input type="button" value="unflag" id="unflag_video'.$row["id"].'" onclick="unflagVideo('.$row["id"].');">') . '</td></tr>';
	$shows_cnt++;
}


echo '</table><br />
<br />
' . $shows_cnt . ' shows aired today.<br />

</div>

<div id="Commercials" class="tabcontent">
  <h3>Commercials</h3>
<table border=1 cellspacing=1 callpadding=8 width="25%">
<tr style="background:lightgray;"><td align=center>Time</td><td align=center>Category</td><td style="padding-left:20px;">Commercial</td><td width="100">Options</td><td align="center">Flag</td></tr>
';

$comms_cnt = 0;
$bit = 1;

//echo strtotime('today 00:01');
//echo strtotime('today 23:59');

$res = $mysqli->query("SELECT * FROM commercials WHERE played>=" . strtotime($the_date . ' 00:00') . " AND played<=" . strtotime($the_date . ' 23:59') . " ORDER BY id DESC") or die($mysqli->error);
$comm_months = [];


while ($row = $res->fetch_assoc()) {
	//if(date("w", $row["played"])!=$td) break;
	$rname = $row["name"];
	foreach($drive_loc as $d) {
		if(substr($row["name"], 0, strlen($d)) == $d) $rname = substr($row["name"], strlen($d));
	}
	
	
	
	$splits = explode('/', $rname);
	$month_offset = 2;
	$comm_type=0;
	
	if(strpos($row["name"], "%AM%")>0) $comm_type=1;
	if(strpos($row["name"], "%PM%")>0) $comm_type=2;
	if(strpos($row["name"], "%ANY%")>0) $comm_type=3;
	
	if($splits[count($splits)-$month_offset]=="any" || $splits[count($splits)-$month_offset]=="am" || $splits[count($splits)-$month_offset]=="pm") {
		$month_offset = 3;
	}
	
	if(array_key_exists($splits[count($splits)-$month_offset], $comm_months)==false) { 
		$comm_months[$splits[count($splits)-$month_offset]]=[1, 0, 0, 0];
		$comm_months[$splits[count($splits)-$month_offset]][$comm_type]=1;
	} else { 
		$comm_months[$splits[count($splits)-$month_offset]][0]++;
		$comm_months[$splits[count($splits)-$month_offset]][$comm_type]++;
	}
	
	$a = strpos(basename($row["name"]), "%T(");
	$b = strpos(basename($row["name"]), ")%", $a+1);
	$len = "";
	if($a>-1 && $b>-1) {
		$len = gmdate("H:i:s", (int)substr(basename($row["name"]), $a + 3, $b-$a - 3));
	}

	echo '<tr style="background-color: #'.getCommercialTypeColor($splits[count($splits)-$month_offset]).'; color:white; text-shadow: 1px 1px 1px rgba(0,0,0,0.44);"><td align=center>' . date("h:i&\\nb\\sp;A", $row["played"]) . '</td><td align=center><span href="/commercials.php?folder=' . $splits[count($splits)-2] . '" style="color:white; text-shadow: 1px 1px 1px rgba(0,0,0,0.44);">' . $splits[count($splits)-$month_offset] . '</span></td><td style="padding-left:20px;"><a href="/?video=' . $row["name"] . '" style="color:white; text-shadow: 1px 1px 1px rgba(0,0,0,0.44);">' . basename($row["name"]) . '</a> ' . $len . '</td><td><a href="/videoeditor.php?file=' . $row["name"] . '"><img src="images/video_edit.png" /></a> <a href="/?delete=' . $row["name"] . '" onclick="return confirm(\'Deleting video is permanent.\n\nAre you sure?\')"><img src="images/video_delete.png" /></a></td><td align="center">' . ($row["flag"]=="0" ? '<input type="button" value="flag" id="flag_comm'.$row["id"].'" onclick="flagCommercial('.$row["id"].');">' : '<input type="button" value="unflag" id="unflag_comm'.$row["id"].'" onclick="unflagCommercial('.$row["id"].');">') . '</td></tr>';
	$comms_cnt++;
}

echo '</table><br />
<br />
';

foreach($comm_months as $k=>$v) {
	
	echo '<span style="background-color: #'.getCommercialTypeColor($k).'; color:'.invertColor(getCommercialTypeColor($k)).'">'.$v[0]." commercials from $k [AM " . $v[1] . ", PM " . $v[2] . ", ANY " . $v[3] . "] " . " (" . round(($v[0] / $comms_cnt)*100,1) . "%)</span><br />\n";

}

echo '
<br />' . $comms_cnt . ' commercials today.<br />
</div>

<div id="Errors" class="tabcontent">
  <h3>Errors</h3>
  <ol>';

$res = $mysqli->query("SELECT * FROM errors WHERE name NOT LIKE '%UPTIME%' ORDER BY id DESC LIMIT 1500") or die($mysqli->error);
$odate = "";
$change = false;
$GEN_COMM_LIST = 0;
while ($row = $res->fetch_assoc()) {
	$rows = explode("|", $row["name"]);
	if($rows[0] != "GEN_COMM_LIST") {
		$date = date("m/d/Y", $row["played"]);
		$time = date("h:i A", $row["played"]);
		if($odate != $date) {
			echo "</ol><h2>$date</h2><ol>";
			$odate = $date;
			$change=true;
		}	
		if($GEN_COMM_LIST!=0 && $change) {
			echo "</ol><i>Timeouts while generating commercials: $GEN_COMM_LIST</i><ol>";
			$GEN_COMM_LIST = 0;
			$change = false;
		}
		echo "<li><div>$time: " . $rows[0] . '<br /><ul>';
		for($i=1;$i<count($rows);$i++) {
			if($rows[$i] == "SOURCE") {
				echo '<li>' . $rows[$i] . "</li>\r\n";
				
				$res_source = $mysqli->query("SELECT id FROM `errors` WHERE `name` LIKE '%". addslashes($rows[$i+1]) . "%'") or die($mysqli->error);
				echo '<li><em>' . $rows[$i+1] . "</em> (Num Source Errors: " . mysqli_num_rows($res_source) . ")</li>\r\n";
				
				
				
				$i++;
			} else {
				echo '<li>' . $rows[$i] . "</li>\r\n";
			}
		}

		echo '</ul></div></li>';
	} else {
		$GEN_COMM_LIST++;
	}
}

echo '
</ol><br />
</div>
<div id="Settings" class="tabcontent">
<h3>Settings</h3>
<p>
';

echo '
<ul>
	<h3>Channels</h3>
	<ul>';
	
if(count($channels)>0) {
	foreach($channels as $c) {
		$cc = $c;
		if($c==null || $c=="") $c = "default";
		echo '	<li style="padding:4px;"><a href="/?channel='.urlencode($c).'" style="padding:4px;'.($cc==$curr_channel ? ' background-color:lightgreen;' : '').'">'.$c.'</a></li>
	';
	}
} else { echo '</No Channels Defined in Settings</li>'; }

	
echo '
	</ul>
	<h3>Station Programming</h3>
	<ul>
		<li style="padding:4px;"><a href="/?clear_cache=now">Update Show Names Cache</a></li>
		<li style="padding:4px;"><a href="'.$json_settings["web-ui"]["tv_schedule_link"].'" target="_new">TV Schedule</a></li>
		<li style="padding:4px;"><a href="/programming.php">Programming</a></li>	
		<li style="padding:4px;"><a href="/settings.php">Settings Editor</a></li>
	</ul>
	
	<h3>File Management</h3>
	<ul>
		<li style="padding:4px;"><a href="/dir.php">Browse Videos</a></li>
		<li style="padding:4px;"><a href="/videoeditor.php">Video Editor</a></li>
		<li style="padding:4px;"><a href="/youtube-dl.php">Youtube-DL Interface</a></li>
	</ul>
	
	<h3>System</h3>
	<ul>
		<li style="padding:4px;"><a href="/?reboot=now" style="color:red;" onclick="return confirm(\'Are you sure?\')">Reboot</a></li>
	</ul>
	<h3>Database Management</h3>
	<ul>
		<li style="padding:4px;"><a href="/phpmyadmin">phpMyAdmin</a></li>
';

echo $db_manage_ext->announce();


echo '
	</ul>
	<h3>Flagged Videos</h3>
	<br />
	<ul>
';

$flagged = false;

$res = $mysqli->query("SELECT * FROM played WHERE flag!=0 ORDER BY id DESC") or die($mysqli->error);

while ($row = $res->fetch_assoc()) {
	if(!$row) break;
	echo '<li><a href="/?video='.$row["name"].'" target="_new">'.$row["name"].'</a> | <input type="button" value="unflag" onclick="unflagVideo('.$row["id"].');"></li>';
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
	echo '<li id="comm'.$row["id"].'"><a href="/?video='.$row["name"].'" target="_new">'.$row["name"].'</a> | <input type="button" value="unflag" onclick="unflagCommercial('.$row["id"].');"></li>';
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
<tr style="background:lightgray;"><td></td><td>Type</td><td>Show Name</td><td>Count</td></tr>
  ';
 
function getShowPathFromFile($fname) {
	$splits = explode("/", $fname);
	$path = "";
	for($i=0;$i<count($splits)-2;$i++) {
		$path.=$splits[$i] . "/";
	}
	return [ $splits[count($splits)-1], $path ];
}
 
$res = $mysqli->query("SELECT *, COUNT(`short_name`) AS `value_occurrence` FROM `played` GROUP BY `short_name` ORDER BY `value_occurrence` DESC") or die($mysqli->error);

$arrTypes = [];
$arrCount = [];
$num=1;
while ($row = $res->fetch_assoc()) {
	$showType = getShowType($row["short_name"], $parsedShows);
	if(isset($arrTypes[$showType])) {
		$arrCount[$showType] += ($row["value_occurrence"]*1);
		$arrTypes[$showType]++;
	} else {
		$arrCount[$showType] = ($row["value_occurrence"]*1);
		$arrTypes[$showType] = 1;
	}
	$gavail = getShowPathFromFile($row["name"]);
	echo '<tr style="background:#'.getShowTypeColor($showType).';"><td>' . $num . '</td><td>' . $showType . ' <a href="/?getavailable=' . urlencode($gavail[0]) . '&dir=' . urlencode($gavail[1]) . '&h=1">#</a> <a href="/?get_next_episode=' . urlencode($row["name"]) . '&h=1">$</a> <a href="/?get_next_rnd_episode=' . urlencode(dirname($row["name"])) . '&h=1" alt="'.$row["name"].'">%</a></td><td> <a href="#" onclick="ajax(\'?showstats='.addslashes(urlencode($row["short_name"])).'&id='.$row["id"].'\'); document.getElementById(\'' . addslashes($row["short_name"]) . $row["id"] . '\').style.display = \'block\'; return false;">[+]</a> ' . $row["short_name"] . '<div id="' . $row["short_name"] . $row["id"] . '" style="display:none; max-width:500px;overflow:scroll;white-space: nowrap;"></div></td><td align="center">' . $row["value_occurrence"] . '</td></tr>';
	$num++;
}

echo '</table><br />
<br />
<table border=1 cellspacing=1 callpadding=8 width="25%">
<tr style="background:lightgray;"><td>Type</td><td>Shows</td><td>Count</td></tr>
  ';

arsort($arrTypes);
foreach($arrTypes as $k=>$v) {
	echo '<tr style="background:#'.getShowTypeColor($k).';"><td>' . $k . '</td><td align="center">' . $v . '</td><td align="center">' . $arrCount[$k] . '</td></tr>';
}
  
  echo '</table>
</div>

';


echo '

</body>
</html>';

?>