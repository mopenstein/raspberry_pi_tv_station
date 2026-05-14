<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);


//######################### settings
require_once("settings.class.inc");

$Settings = new Settings();
$json_response = $Settings->load("/home/pi/Desktop/settings.json");

// 1. Validation Logic
if (($json_settings = $json_response[0]) === null) {
	$error_msg = htmlspecialchars($json_response[1] ?? 'Unknown Error');
	die("<div style='background:red;color:white;margin:10px;padding:10px;'>
		JSON VALIDATION ERROR :: CHECK 'settings.json' FILE! <br /><br />
		Error: $error_msg - <a href='https://duckduckgo.com/?q=json+validator'>Validate</a>
	</div>");
}

// 2. Data Extraction with Null Coalescing
$drive_loc     = $json_settings["drive"] ?? '';
$database_info = $json_settings["web-ui"]["database_info"] ?? [];
$settings_name = $json_settings["name"] ?? "";

// 3. Channel Mapping
$channels      = $json_settings["channels"]["names"] ?? [];
$channel_file  = $json_settings["channels"]["file"] ?? '';

//######################### /settings

require_once("db_manage_extx.inc");
$db_manage_ext = new DBMANAGEEXT();
$db_manage_ext->load($database_info["host"], $database_info["username"], $database_info["password"], $database_info["database_name"]);
$mysqli = new mysqli($database_info["host"], $database_info["username"], $database_info["password"], $database_info["database_name"]);

if(isset($_GET["work"])) {
	if(!isset($json_settings["workers"])) die("0|workers not defined in settings file");
	$worker_scripts = $json_settings["workers"];
	foreach($worker_scripts as $script) {
		if(file_exists($script)) {
			require_once($script);
		} else {
			die("0|worker script not found: $script");
		}
	}
	die("1|successfully ran workers");
}

if(isset($_GET["test_settings"])) {
	header("Location: /test_settings.php");
	exit();
}

function getCurrentChannel() {
	global $channel_file;
	if(!file_exists($channel_file)) return null;
	$cchannel = file_get_contents($channel_file);
	return $cchannel;
}

if(isset($_GET["backup"])) {
	$result = exec('sudo mysqldump --extended-insert=FALSE shows played | gzip > shows.sql.gz');
	while(!file_exists('shows.sql.gz')) {
		sleep(1);
	}
	//header('Content-type: text/plain');
	header("Cache-Control: public");
	header("Content-Description: File Transfer");
	header("Content-Disposition: attachment; filename=backup".date("Y-m-d.H.i") .".sql.gz");
	header("Content-Type: application/zip");
	header("Content-Transfer-Encoding: binary");
	readfile('shows.sql.gz');
	unlink('shows.sql.gz');
	die();
}

if(isset($_GET["get_commercials"]) && isset($_GET["showId"])) {
	$fullPath = realpath($_GET["get_commercials"] . ".commercials");
	// verify path is valid and within allowed drives
	if(!file_exists($fullPath) || !is_numeric($_GET["showId"])) {
		die("Commercials file not found.");	
	}
	$commercials = file_get_contents($fullPath);
	if($commercials === false) {
		die("Failed to read commercials file.");
	}
	$lines = explode("\n", $commercials);
	$ret = "";
	foreach($lines as $line) {
		if(is_numeric(trim($line))) {
			$hhmmss = gmdate("H:i:s", trim($line));
			$ret .= $hhmmss . "\n";
		}
	}
	die($ret . "|" . $_GET["showId"]);
}

if(isset($_GET["insert"]) && isset($_GET["file"]) && isset($_GET["times"])) {
	if(!file_exists(urldecode($_GET["file"]))) die("File does not exist. Confirm path and try again. ".$_GET["file"]);
	for($i=0;$i<urldecode($_GET["times"]) * 1;$i++) {
		$mysqli->real_query("INSERT INTO played (short_name, name, played) VALUES ('" . $mysqli->real_escape_string(urldecode($_GET["insert"])) . "', '" . $mysqli->real_escape_string(urldecode($_GET["file"])) . "', 0)");
		echo ($i+1) . ") INSERT INTO played (short_name, name, played) VALUES ('" . $mysqli->real_escape_string(urldecode($_GET["insert"])) . "', '" . $mysqli->real_escape_string(urldecode($_GET["file"])) . "', 0)<br />\n";
	}
	
	die(urldecode($_GET["insert"]) . " - " . urldecode($_GET["file"]) . " - " . urldecode($_GET["times"]));
}

if(isset($_GET["showstats"])) {
	if(isset($_GET["id"])) {
		if(!is_numeric($_GET["id"])) die("Invalid ID");
	}
	$res = $mysqli->query("SELECT * FROM played WHERE short_name=\"".addslashes($_GET["showstats"])."\"") or die($mysqli->error);
	$cnt = array();

	while ($brow = $res->fetch_assoc()) {
		$show = preg_replace(["/_NA_/", "/%T.(.*)\)%/"], ["", ""], pathinfo($brow["name"])["filename"]);
		echo date('M d, Y h:i A', $brow["played"]) . " @ $show\n";
	}
	
	die("|". $_GET["id"]);
}

if(isset($_GET["flag_video"]) && isset($_GET["id"])) {
	if(is_numeric($_GET["flag_video"])) {
		$res = $mysqli->query("UPDATE played SET flag=1 WHERE id=".addslashes($_GET["flag_video"])) or die($mysqli->error);
		die("flag|".$_GET["id"]."|". $_GET["flag_video"]);
	}
}

if(isset($_GET["unflag_video"]) && isset($_GET["id"])) {
	if(is_numeric($_GET["unflag_video"])) {
		$res = $mysqli->query("UPDATE played SET flag=0 WHERE id=".addslashes($_GET["unflag_video"])) or die($mysqli->error);
		die("unflag|".$_GET["id"]."|". $_GET["unflag_video"]);
	}
}

if(isset($_GET["flag_comm"]) && isset($_GET["id"])) {
	if(is_numeric($_GET["flag_comm"])) {
		$res = $mysqli->query("UPDATE commercials SET flag=1 WHERE id=".addslashes($_GET["flag_comm"])) or die($mysqli->error);
		die("flagc|".$_GET["id"]."|". $_GET["flag_comm"]);
	}
}

if(isset($_GET["unflag_comm"]) && isset($_GET["id"])) {
	if(is_numeric($_GET["unflag_comm"])) {
		$res = $mysqli->query("UPDATE commercials SET flag=0 WHERE id=".addslashes($_GET["unflag_comm"])) or die($mysqli->error);
		die("unflagc|".$_GET["id"]."|". $_GET["unflag_comm"]);
	}
}

function isDriveAllowed(string $path, array $drives): bool {
	foreach ($drives as $drive) {
		$resolvedDrive = realpath($drive);
		if ($resolvedDrive && strpos($path, $resolvedDrive) === 0) {
			return true;
		}
	}
	return false;
}

if (isset($_GET['rename_video'], $_GET['to'])) {
	$oldInput = urldecode($_GET['rename_video']);
	$newInput = urldecode($_GET['to']);

	$oldFile = realpath($oldInput);
	$newDir  = realpath(dirname($newInput));
	$newFile = $newDir ? $newDir . DIRECTORY_SEPARATOR . basename($newInput) : false;

	if (!$oldFile || !file_exists($oldFile)) {
		exit("Error: Source file does not exist.\n\n" . htmlspecialchars($oldInput));
	}

	if (!$newFile) {
		exit("Error: Destination path is invalid.\n\n" . htmlspecialchars($newInput));
	}

	if (file_exists($newFile)) {
		exit("Error: Destination file already exists. Choose a different name.");
	}

	if (!isDriveAllowed($oldFile, $json_settings['drive'])) {
		exit("Error: Source file is outside of allowed drive(s).");
	}

	if (!isDriveAllowed($newFile, $json_settings['drive'])) {
		exit("Error: Destination path is outside of allowed drive(s).");
	}

	if (!rename($oldFile, $newFile)) {
		exit("Error: Failed to rename file. Check permissions and paths.");
	}

	echo "Renamed:\n\n" . htmlspecialchars($oldInput) . "\n\nto\n\n" . htmlspecialchars($newInput) . "<br />\n";
	exit;
}

if(isset($_GET["channel"]) && count($_GET)==1) {
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


if(isset($_GET["reboot"])) {
	header("Location: /\n\n");
	exec('sudo reboot');
}

if(isset($_GET["start"])) {
	header("Location: /\n\n");
	exec('python /home/pi/Desktop/_rnd80s.py');
}


if(isset($_GET["skip"])) {
	
	$last_played = "_not_found_";
	$result = $mysqli->query("SELECT * FROM played ORDER BY id DESC") or die($mysqli->error);

	if ($result->num_rows > 0) {
		$last_played = $result->fetch_assoc()["name"];
	}
	
	$mysqli->real_query("INSERT INTO errors (name, played) VALUES ('SKIPPED|File currently playing was skipped|".addslashes($last_played)."', ". time() . ")");

	$result = exec('sudo ./kill.sh');
	header("Location: /?skipped=yes\n\n");
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

function getTvShowName($filename, $showList) {
	/*
	Find the best matching show name from the provided show list based on the filename.
	*/
	if ($showList == null || count($showList) <= 0) return basename($filename);

	// Strip off any %T(...) suffix
	if (strrpos($filename, "%T(") !== false) {
		$filename = substr($filename, 0, strrpos($filename, "%T("));
	}
	$filename = basename($filename);

	// Normalize filename for matching
	$cleanFilename = strtolower(preg_replace("~[_\W\s]~", '', $filename));

	$bestMatch = '';
	$bestLength = 0;

	foreach ($showList as $entries) {
		foreach ($entries as $entry) {
			$parts = explode("=", $entry, 2);
			$rawName = $parts[0];
			$normalized = strtolower(preg_replace("~[_\W\s]~", '', $rawName));

			if (!$normalized) continue;

			if (strpos($cleanFilename, $normalized) !== false) {
				if (strlen($normalized) > $bestLength) {
					$bestMatch = (count($parts) == 1 ? $entry : $parts[1]);
					$bestLength = strlen($normalized);
				}
			}
		}
	}

	return $bestMatch ?: $filename;
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
	if($showList==null) return "none";
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


function getShowNames($url, $force, $chan=null) {
	if($chan==null) { //default channel
		$chan = "";
	} else {
		$chan = "." . $chan;
	}

	$murl = md5($url) . $chan . ".cache";
	
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
	$sched_link = $json_settings["web-ui"]["tv_schedule_link"];
	if(!$sched_link) die("no schedule link defined");
	$wasted = parseCSV(getShowNames($sched_link.(substr($sched_link,-1)!="/" ? "/" : "").'export?format=csv', true), true);
	die();
}

$parsedShows = null;
$sched_link = $json_settings["web-ui"]["tv_schedule_link"];
if($sched_link) {
	$unparsedCSV = getShowNames($sched_link.(substr($sched_link,-1)!="/" ? "/" : "").'export?format=csv', false);
	$parsedShows = parseCSV($unparsedCSV);
}

function getPathFromShortName($shortname) {
	global $mysqli;
	$res = $mysqli->query("SELECT name FROM `played` WHERE short_name='".addslashes($shortname)."' LIMIT 1") or die($mysqli->error);
	$brow = $res->fetch_assoc();
	if(!$brow) return null;
	return dirname($brow["name"]);
}


/**
 * Checks the play count of every show in a given category. If all shows 
 * meet or exceed the $limit, the oldest entry for EACH show is deleted
 * to reset the cycle and maintain the cap.
 *
 * @param string $sname The name of the show type/category (e.g., "reruns").
 * @param string $csv A CSV string mapping categories to short show names.
 * @param int $limit The maximum number of entries allowed (e.g., 5).
 * @return bool True if a reset and deletion occurred, false otherwise.
 */
function cleanupAndResetCategory(string $sname, string $csv, int $limit): bool
{
    global $mysqli;

    // 1. Identify Target Shows (Using the same logic as your scheduler)
    $csvRows = array_map('str_getcsv', explode("\r\n", $csv));
    if (empty($csvRows)) return false;
    
    $categoryKeys = array_shift($csvRows); 
    $targetShows = [];

    foreach ($csvRows as $row) {
        foreach ($row as $index => $shortName) {
            if (!empty($shortName) && isset($categoryKeys[$index]) && $categoryKeys[$index] === $sname) {
                $targetShows[] = preg_replace('/^[^=]+=/', '', $shortName);
            }
        }
    }
    if (empty($targetShows)) return false;

    // 2. Count Entries for Each Show
    $placeholders = implode(',', array_fill(0, count($targetShows), '?'));
    
    $sql_count = "
        SELECT 
            short_name, 
            COUNT(id) AS play_count
        FROM played
        WHERE short_name IN ($placeholders)
        GROUP BY short_name
    ";

    $stmt_count = $mysqli->prepare($sql_count);
    $types = str_repeat('s', count($targetShows));
    $stmt_count->bind_param($types, ...$targetShows);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();

    $showsAtCapCount = 0;
    $totalTargetShows = count($targetShows);

    while ($row = $result_count->fetch_assoc()) {
        if ($row['play_count'] >= $limit) {
            $showsAtCapCount++;
        } else {
            // If any single show is below the limit, the reset condition is NOT met.
            $stmt_count->close();
            return false;
        }
    }
    $stmt_count->close();

    // Crucial Check: Ensure all shows in the category are present in the DB 
    // and meet the cap. (This handles shows that might not have any entries yet.)
    if ($showsAtCapCount !== $totalTargetShows) {
        return false;
    }

    // 3. Reset Condition Met: Delete the oldest entry for every show
    
    // Deletion must be done in a loop, one show at a time, to find the specific 
    // oldest entry (MIN(played)) for that short_name.
    $resetOccurred = false;
    
    // Begin transaction for safety (optional but recommended)
    // $mysqli->begin_transaction();

    foreach ($targetShows as $show_name) {
        // Find and delete the SINGLE oldest entry for the current show.
        $sql_delete = "
            DELETE FROM played 
            WHERE id = (
                SELECT id 
                FROM (
                    SELECT id 
                    FROM played 
                    WHERE short_name = ? 
                    ORDER BY played ASC 
                    LIMIT 1
                ) AS oldest_entry
            )
        ";
        
        // NOTE: The subquery structure (FROM (SELECT) AS temp) is necessary in MySQL 
        // to prevent "You can't specify target table 'played' for update in FROM clause" error.

        $stmt_delete = $mysqli->prepare($sql_delete);
        $stmt_delete->bind_param("s", $show_name);
        
        if ($stmt_delete->execute() && $mysqli->affected_rows > 0) {
            $resetOccurred = true;
        }
        $stmt_delete->close();
    }
    
    // Commit transaction if used
    // $mysqli->commit(); 
    
    return $resetOccurred;
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
	$dir = urldecode($_GET["dir"]);
	$getavailable = urldecode($_GET["getavailable"]);
	if(!is_dir($dir)) die('0');
	$shortname=getTvShowName($getavailable, $parsedShows);
	$showType=getShowType($shortname, $parsedShows);
	if(isset($_GET["h"])) {
		
		function highlightDiff($string1, $string2) {
			for($i=0;$i<strlen($string1);$i++) {
				if(substr($string1,$i,1) != substr($string2,$i,1)) break;
			}
			return "<b>" . substr($string2,0,$i) . "</b><i>" . substr($string2,$i) . "</i>";
		}
		
		$avh = getAvailableShows($showType, $dir, $unparsedCSV);
		echo "<h1>$shortname $showType</h1>\n";
		$lshow = "";
		foreach($avh as $show) {
			if($lshow=="") {
				echo "<i>$show</i><br />\n";
			} else {
				echo highlightDiff($lshow,$show) . "<br />\n";
			}
			$lshow=$show;
		}
		die();
	} else {
		die(implode("\n", (getAvailableShows($showType, $dir, $unparsedCSV))));
	}
}


if(isset($_GET["getshowname"])) {

	if(strpos(strtolower($_GET["getshowname"]), '/commercials/') > 0) {
		//a commercial is being payed, ignore 
		die("commercial|0");
	}

	$shortname=getTvShowName($_GET["getshowname"], $parsedShows);
	if(isset($_GET["short"])) die($shortname);
	
	$showType=getShowType($shortname, $parsedShows);
	
	
	
	$row=0;
	$remote_diff_set = false;

	$time_diff = 0;
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

	function getVideoFiles($dir, $exts = ['mp4', 'avi', 'webm', 'mpeg', 'm4v', 'mkv', 'mov', 'flv', 'wmv']) {
		$dir = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		// Build the brace pattern: {mp4,MP4,avi,AVI,...}
		$all_cases = [];
		foreach ($exts as $e) {
			$all_cases[] = strtolower($e);
			$all_cases[] = strtoupper($e);
		}
		
		$pattern = $dir . '*.' . '{' . implode(',', array_unique($all_cases)) . '}';
		
		// Returns an array of paths or false on error
		$files = glob($pattern, GLOB_BRACE);

		return $files ?: [];
	}

	// 1. Clean the input once
	$nv = urldecode($_GET["get_next_episode"] ?? '');
	$clean_nv = rtrim($nv, "/");

	// 2. Determine directory (Prioritize the path itself if it's a dir, else its parent)
	$dir_name = is_dir($clean_nv) ? $clean_nv : dirname($clean_nv);

	if (!is_dir($dir_name)) {
		die("|0|directory does not exist|$dir_name|$nv|1");
	}

	// 3. Secure SQL Query
	$escaped_dir = $mysqli->real_escape_string($dir_name);
	$sql = "SELECT * FROM played WHERE name LIKE '$escaped_dir%' ORDER BY played DESC LIMIT 1";
	$res = $mysqli->query($sql) or die($mysqli->error);

	// 4. Debugging & Metadata
	if (isset($_GET["dump"]) || isset($_GET["h"])) {
		$shortname = getTvShowName($nv, $parsedShows);
		if (isset($_GET["dump"])) {
			echo "Looking up episodes for: <i>$dir_name</i> <b>$shortname</b> SQL: $sql<br />";
		}
	}

	// 5. Dynamic Filter
	$filter = ['mp4', 'mkv', 'avi', 'mpeg', 'mpg', 'mov', 'webm', 'm4v', 'flv', 'wmv'];
	if (!empty($_GET["filter"])) {
		$filter = explode(",", $_GET["filter"]);
	}

	// 6. File Retrieval
	$files = getVideoFiles($dir_name . '/', $filter);

	if (empty($files)) {
		die("|0|no files exist matching " . implode(",", $filter) . "|$dir_name|$nv|3");
	}

	// check "random video on first play" setting
	if (mysqli_num_rows($res) == 0) {
		// 1. Start with the global default
		$should_random = $json_settings["random video on first play"] ?? false;

		// 2. Override ONLY if ?random= exists in the URL
		if (isset($_GET['random'])) {
			$should_random = ($_GET['random'] === '1');
		}

		// 3. Final Execution
		if ($should_random && !empty($files)) {
			$rand_index = array_rand($files);
			die($files[$rand_index] . "|1|play random episode per override/settings");
		}

		// 4. Fallback/Error Handling
		if (!empty($files[0])) {
			die($files[0] . "|1|play first episode");
		}

		die("error|0|No episodes found");
	}

	$row = $res->fetch_row();
	$last_played = $row[2] ?? '';

	// 1. Validation: Is the record still valid?
	if (!file_exists($last_played)) {
		die("|0|last played file does not exist (deleted?)|$nv");
	}

	// 2. Find the current position in our file list
	$current_index = array_search($last_played, $files);

	if ($current_index === false) {
		// Last played file isn't in the current folder/filter results
		die("|0|last played file no longer matches current filters|$nv");
	}

	// 3. Determine Next Index (Increment or Loop back to 0)
	$next_index = $current_index + 1;
	if ($next_index >= count($files)) {
		$next_index = 0; // Loop back to the beginning
	}

	$next_file = $files[$next_index];

	// 4. Output based on request type
	if (isset($_GET["h"])) {
		$bn = basename($next_file);
		$dn = dirname($next_file);
		die("<h1>$shortname</h1>\nNext: $bn<br />\nPath: $dn");
	}

	die("$next_file|1|next episode");
}

function logPlayback($mysqli, $current_video, $parsedShows, $channel = null, $debug = false, $timestamp_override = null) {

	if ($channel === null) {
		$channel = getCurrentChannel(); // fallback to file-based channel
	}

	if ($channel !== null) {
		$channel = formatChannelName(trim($channel));
	}

	$show_name = getTvShowName($current_video, $parsedShows);
	$timestamp = ($timestamp_override !== null) ? $timestamp_override : time();

	if ($debug) {
		echo "Show Name: '$show_name' <br>\n";
		echo "Channel: '" . ($channel === null ? "null" : $channel) . "' <br>\n";
		echo "Timestamp: $timestamp <br>\n";
	}

	$stmt_insert = $mysqli->prepare("
		INSERT INTO played (short_name, name, played, channel)
		VALUES (?, ?, ?, ?)
	");
	$stmt_insert->bind_param("ssis", $show_name, $current_video, $timestamp, $channel);
	$stmt_insert->execute();
	$stmt_insert->close();
}

function addMessageToDatabase($message) {
	global $mysqli;

	// 1. If it's an UPTIME tick, clear old ones first
	if (strpos($message, 'UPTIME|') === 0) {
		$mysqli->query("DELETE FROM `errors` WHERE `name` LIKE 'UPTIME|%'");
	}

	// 2. Log the message (Common to both cases)
	$escaped = $mysqli->real_escape_string($message);
	$time = time();

	$mysqli->query("INSERT INTO errors (name, played) VALUES ('$escaped', $time)");
}

function handleRandomVideoByCount($episode, $filter) {
	global $mysqli;
	$max_attempts = 50;
	$attempts = 0;

	if (isset($_GET["dump"])) {
		echo "episode: $episode\nfilter: $filter\n";
	}

	do {
		// 1. Fetch video data
		$return = $filter ? getRandomVideoByCount($episode, $filter) : getRandomVideoByCount($episode);
		
		if (isset($_GET["dump"])) {
			echo str_repeat('$', 40) . "\n";
			var_dump($return);
		}

		// Use list() instead of [] for PHP 7.0 compatibility
		list($file, $status, $msg, $dir) = $return;

		// 2. Immediate exit on failure status
		if (!$file || $status === 0) {
			return "$file|$status|$msg|$dir";
		}

		// 3. Validation and Cleanup
		$exists = file_exists($file);
		if (!$exists) {
			$escaped = $mysqli->real_escape_string($file);
			$mysqli->query("DELETE FROM played WHERE name='$escaped'");
			$attempts++;
		}

	} while (!$exists && $attempts < $max_attempts);

	// 4. Final Output
	if (!$exists) {
		return "|0|max attempts reached, no valid file found|$dir";
	}

	return "$file|$status|$msg|$dir";
}

function getRandomVideoByCount($directory, $filter = "mp4,mkv,avi,mpeg,mpg,mov,webm,m4v,flv,wmv", $equalize = true) {
	global $mysqli;
	global $parsedShows;

	// 1. Clean Path and Basic Validation
	$dir_name = rtrim($directory, '/') . '/';
	if (!is_dir($dir_name)) {
		return [$directory, 0, "video directory does not exist", $dir_name];
	}

	// 2. Scan Filesystem
	$files = glob($dir_name . "*.{" . $filter . "}", GLOB_BRACE);
	if (empty($files)) {
		return [$directory, 0, "no files exist in directory", $dir_name];
	}

	// 3. Database Lookup & ghost scrubbing
	$escaped_dir = $mysqli->real_escape_string($dir_name);
	$sql = "SELECT name FROM played WHERE name LIKE '$escaped_dir%'";
	$res = $mysqli->query($sql) or die($mysqli->error);

	$videos_played = array_fill_keys($files, 0);

	while ($brow = $res->fetch_assoc()) {
		$name = $brow["name"];
		if (isset($videos_played[$name])) {
			$videos_played[$name]++;
		} else {
			// If file is in DB but not in our glob, check if it's a ghost to scrub
			if (!file_exists($name)) {
				$escaped_ghost = $mysqli->real_escape_string($name);
				$mysqli->query("DELETE FROM played WHERE name='$escaped_ghost'");
				if (isset($_GET["dump"])) echo "Scrubbed ghost file: $name<br />\n";
			}
		}
	}

	if (empty($videos_played)) {
		return [$files[array_rand($files)], 1, "fallback to random (empty map)", $dir_name];
	}

	// 4. Determine Min/Max Play Counts
	$counts = array_values($videos_played);
	$min = min($counts);
	$max = max($counts);

	// 5. Create pool of least-played videos
	$pool = [];
	foreach ($videos_played as $f => $c) {
		if ($c === $min) $pool[] = $f;
	}

	// 6. Prevent immediate repeat if all have same play count
	if ($min === $max && count($pool) > 1) {
		$recent_sql = "SELECT name FROM played WHERE name LIKE '$escaped_dir%' ORDER BY played DESC LIMIT 1";
		$recent_res = $mysqli->query($recent_sql);
		if ($recent_res && $row = $recent_res->fetch_assoc()) {
			$last_played = $row['name'];
			// Remove from pool if it's there
			$key = array_search($last_played, $pool);
			if ($key !== false) {
				unset($pool[$key]);
				$pool = array_values($pool); // Re-index
				if (isset($_GET["dump"])) echo "Excluded last played: $last_played<br />\n";
			}
		}
	}

	// 7. Pick random from least-played pool
	$selected = $pool[array_rand($pool)];

	// 8. Equalization Logic
	if ($equalize) {
		$predicted = $videos_played[$selected] + 1;
		if ($predicted < $max) {
			$diff = $max - $predicted;
			$escaped_sel = $mysqli->real_escape_string($selected);
			for ($i = 0; $i < $diff; $i++) {
				logPlayback($mysqli, $escaped_sel, $parsedShows, null, isset($_GET["dump"]), 0);
			}
			addMessageToDatabase("Equalized:|'$selected'|Added:|$diff dummy plays");
		}
	}

	return [$selected, 1, "random video from least played pool", $dir_name];
}

if (isset($_GET["get_next_rnd_episode"])) {
	$episode = urldecode($_GET["get_next_rnd_episode"]);
	$filter = isset($_GET["filter"]) ? urldecode($_GET["filter"]) : null;

	$equalize = $json_settings["equalize playcount"] ?? false;
	die(handleRandomVideoByCount($episode, $filter, $equalize));
}

if (isset($_GET["get_next_rnd_episode_from_dir"])) {
	$dirs = [];
	$i = 1;

	// 1. Collect and Weight Directories
	while (isset($_GET["f$i"])) {
		$dir_name = rtrim(urldecode($_GET["f$i"]), '/') . '/';
		
		if (file_exists($dir_name)) {
			$escaped_dir = $mysqli->real_escape_string($dir_name);
			$sql = "SELECT COUNT(*) AS t FROM played WHERE name LIKE '$escaped_dir%'";
			$res = $mysqli->query($sql) or die($mysqli->error);
			$brow = $res->fetch_assoc();

			$play_count = (int)$brow["t"];
			$file_count = iterator_count(new FilesystemIterator($dir_name, FilesystemIterator::SKIP_DOTS));
			
			// Prevent division by zero if directory is empty
			$weighted = $file_count > 0 ? ($play_count / $file_count) : 999999;
			$dirs[] = [$weighted, $dir_name];
		}
		$i++;
	}

	if (empty($dirs)) {
		die("|0|no valid directories provided");
	}

	// 2. Find the lowest weight
	$weights = array_column($dirs, 0);
	$low = min($weights);

	// 3. Filter to only include directories matching the lowest weight
	$pool = array_filter($dirs, function($d) use ($low) {
		return $d[0] <= $low;
	});

	if (isset($_GET["dump"])) {
		echo "Low Weight: $low<br>\nPool Size: " . count($pool) . "<br>\n";
		var_dump($pool);
	}

	// 4. Pick random from the filtered pool
	$selected_dir = $pool[array_rand($pool)][1];
	
	exit(handleRandomVideoByCount($selected_dir, null));
}


$mntcont = strlen($drive_loc[0]);

function formatChannelName($ch) {
	if ($ch !== "" && $ch !== "default" && strlen($ch) <= 100 && preg_match('/^[\w\-]+$/', $ch)) {
		return $ch;
	}
	return null;
}


if (isset($_GET["current_video"])) {
	$current_video = urldecode($_GET["current_video"]);
	$channel       = $_GET["channel"] ?? null;
	$debug         = isset($_GET["d"]);

	logPlayback($mysqli, $current_video, $parsedShows, $channel, $debug);
	
	exit("1");
}

if(isset($_GET["error"])) {
	addMessageToDatabase(urldecode($_GET["error"]));	
	exit(1);
}

if (isset($_GET["current_comm"])) {
	$name      = $_GET["current_comm"];
	$timestamp = time();
	$channel   = isset($_GET["channel"]) ? formatChannelName(trim($_GET["channel"])) : null;

	// Use prepared statement (Safe from injection, no addslashes needed)
	$stmt = $mysqli->prepare("INSERT INTO commercials (name, played, channel) VALUES (?, ?, ?)");
	$stmt->bind_param("sis", $name, $timestamp, $channel);
	$stmt->execute();
	$stmt->close();
	
	die("1");
}

?>