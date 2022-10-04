<?php

//icon images from https://all-free-download.com/free-icon/download/24x24_free_application_icons_icons_pack_120732_download.html

error_reporting(E_ALL);
ini_set("display_errors", 1);

//######################### drive

$drive_loc="/media/pi/ssd";
$settings_file="/home/pi/Desktop/settings.json";
$settings = "";

if(!file_exists($settings_file)) {
	die("settings file doesn't exist");
}

//######################### drive

function scan_dir($start, $root=0) {
	$ret = "";
	$files2 = scandir($start);
	foreach($files2 as $f) {
		if(strpos($f, ".")===false)	{
			$tmp = substr($start,$root);
			//echo "$start $f " . substr($tmp,0,1) . " " . substr($tmp,1) . "<br />";
			//if(substr($tmp,0,1)=="/") $tmp = substr($tmp,1);
			$ret .= '<option>' . $tmp.($tmp!="" ? "/" : "").$f.'</option>';
			$ret .= scan_dir("$start/$f",$root);
		}
	}
	return $ret;
}

function load_settings() {
	global $settings_file;
	global $settings;
	$settings = json_decode(file_get_contents($settings_file), true);
}


function save_settings() {
	global $settings_file;
	global $settings;
	file_put_contents($settings_file, json_encode($settings));
}

echo '
<html>
<head>
<title>TV Station Programming</title>
<style>
	td {
		overflow:auto;
	}
</style>
<script type="text/javascript">
var json_settings = '.file_get_contents($settings_file).';
function addToList(obj) {
	console.log(obj.parentElement.childNodes)
	var opt = document.createElement(\'option\');
		opt.text = obj.parentElement.childNodes[3].value;
		opt.value = obj.parentElement.childNodes[3].value; 
	obj.nextSibling.nextSibling.nextSibling.nextSibling.add(opt)
}

function removeFromList(obj) {
	var list = obj.nextSibling.nextSibling.nextSibling;
	list[list.selectedIndex].remove();
}

function findPrevious(elm) {
	//from https://stackoverflow.com/questions/2943140/how-to-swap-html-elements-in-javascript
   do {
       elm = elm.previousSibling;
   } while (elm && elm.nodeType != 1);
   return elm;
}

function findNext(elm) {
   do {
       elm = elm.nextSibling;
   } while (elm && elm.nodeType != 1);
   return elm;
}

function swapUp(elm) {
	//from https://stackoverflow.com/questions/2943140/how-to-swap-html-elements-in-javascript
    var previous = findPrevious(elm);
    if (previous) {
        elm.parentNode.insertBefore(elm, previous);
    }
}

function insertAfter(newNode, existingNode) {
	//from https://www.javascripttutorial.net/javascript-dom/javascript-insertafter/
    existingNode.parentNode.insertBefore(newNode, existingNode.nextSibling);
}

function swapDown(elm) {
    var previous = findNext(elm);
    if (previous) {
		insertAfter(elm, previous);
    }
}

function editItem(elm) {
	var els = elm.parentNode.parentNode.getElementsByTagName(\'td\');
	if(!els) return;


	// 1 = name
	var days = els[1].innerHTML.split(",");
	document.getElementById(\'directories\').options.length = 0;
	for(var i=0;i<days.length;i++) {
		var opt = document.createElement(\'option\');
			opt.text = days[i].trim();
			opt.value = days[i].trim(); 
		document.getElementById(\'directories\').add(opt)
	}
	
	// 2 = dayOfWeek
	days = els[2].innerHTML.split(",");
	var vdays = [];
	for(var i=0;i<days.length;i++) {
		vdays[days[i].trim()] = 1;
	}
	console.log(days);
	console.log(vdays);
	document.getElementById(\'dayOfWeek_monday\').checked = (days[0]=="all" || vdays["monday"] ? true : false);
	document.getElementById(\'dayOfWeek_tuesday\').checked = (days[0]=="all" || vdays["tuesday"] ? true : false);
	document.getElementById(\'dayOfWeek_wednesday\').checked = (days[0]=="all" || vdays["wednesday"] ? true : false);
	document.getElementById(\'dayOfWeek_thursday\').checked = (days[0]=="all" || vdays["thursday"] ? true : false);
	document.getElementById(\'dayOfWeek_friday\').checked = (days[0]=="all" || vdays["friday"] ? true : false);
	document.getElementById(\'dayOfWeek_saturday\').checked = (days[0]=="all" || vdays["saturday"] ? true : false);
	document.getElementById(\'dayOfWeek_sunday\').checked = (days[0]=="all" || vdays["sunday"] ? true : false);
	
	// 3 = start time
	if(els[3].innerHTML!="N/A") {
		days = els[3].innerHTML.split(",");
		document.getElementById(\'start_time\').value = days[0].trim().padStart(2,"0") + ":" + days[1].trim().padStart(2,"0");
	} else {
		document.getElementById(\'start_time\').value = "00:00";
	}
	
	// 4 = end time
	if(els[4].innerHTML!="N/A") {
		days = els[4].innerHTML.split(",");
		document.getElementById(\'end_time\').value = days[0].trim().padStart(2,"0") + ":" + days[1].trim().padStart(2,"0");
	} else {
		document.getElementById(\'end_time\').value = "23:59";
	}
	
	// 5 = months
	var months = ["", "january", "february", "march", "april", "may", "june", "july", "august", "september", "october", "november", "december"];
	if(els[5].innerHTML!="N/A") {
		days = els[5].innerHTML.split(",");
		document.getElementById(\'months\').options.length = 0;
		for(var i=0;i<days.length;i++) {
			var opt = document.createElement(\'option\');
				opt.text = months[days[i].trim()*1];
				opt.value = months[days[i].trim()*1]; 
			document.getElementById(\'months\').add(opt)
		}
	} else {
		document.getElementById(\'months\').options.length=0;
	}
	
	// 6 = days
	if(els[6].innerHTML!="N/A") {
		days = els[6].innerHTML.split(",");
		document.getElementById(\'date\').options.length = 0;
		for(var i=0;i<days.length;i++) {
			var opt = document.createElement(\'option\');
				opt.text = days[i].trim();
				opt.value = days[i].trim(); 
			document.getElementById(\'date\').add(opt)
		}
	} else {
		document.getElementById(\'date\').options.length=0;
	}
	
	// 7 = special
	if(els[7].innerHTML!="N/A") {
		days = els[7].innerHTML;
		vdays = document.getElementById(\'specials\');
		
		for(var i=0;i<vdays.options.length;i++) {
			var opt = vdays.options[i].value;
			console.log(opt, days);
			if(opt == days) {
				vdays.selectedIndex = i;
				break;
			}
		}
	} else {
		document.getElementById(\'specials\').selectedIndex = 0;
	}
	
	// 8 = chance
	if(els[8].innerHTML!="N/A") {
		days = els[8].innerHTML * 1;
		document.getElementById(\'chances\').selectedIndex = 100 - (100 * days);
	} else {
		document.getElementById(\'chances\').selectedIndex = 0;
	}
}

</script>
</head>
<body style="background-color:#efefef;border:outset;margin:2px;padding:20px;">
<table>
<tr>
<td valign="top">
Directory: <br />
<select id="directory">
'.scan_dir($drive_loc, strlen($drive_loc)+1).'
</select><br />
<button onclick="addToList(this);">Add</button><button onclick="removeFromList(this);">Remove</button><br />
<select name="directories" id="directories" size="5" style="width:100%;" ondblclick="console.log(window.prompt(\'sometext\',\'defaultText\'));"> 

</select>
<td valign="top">
<td>
<input type="checkbox" name="dayOfWeek" id="dayOfWeek_monday" value="monday" /> Monday<br />
<input type="checkbox" name="dayOfWeek" id="dayOfWeek_tuesday" value="tuesday" /> Tuesday<br />
<input type="checkbox" name="dayOfWeek" id="dayOfWeek_wednesday" value="wednesday" /> Wednesday<br />
<input type="checkbox" name="dayOfWeek" id="dayOfWeek_thursday" value="thursday" /> Thursday<br />
<input type="checkbox" name="dayOfWeek" id="dayOfWeek_friday" value="friday" /> Friday<br />
<input type="checkbox" name="dayOfWeek" id="dayOfWeek_saturday" value="saturday" /> Saturday<br />
<input type="checkbox" name="dayOfWeek" id="dayOfWeek_sunday" value="sunday" /> Sunday
</td>
<td valign="top">
Start Time: <input type="time" name="start_time" id="start_time" /><br />

End Time: <input type="time" name="end_time" id="end_time" />
</td>
<td valign="top">
Month: <br />
<select id="month">
<option>january</option>
<option>february</option>
<option>march</option>
<option>april</option>
<option>may</option>
<option>june</option>
<option>july</option>
<option>august</option>
<option>september</option>
<option>october</option>
<option>november</option>
<option>december</option>
</select><br />
<button onclick="addToList(this);">Add</button><button onclick="removeFromList(this);">Remove</button><br />
<select name="months" id="months" size="5" style="width:100%;"> 

</select
</td>
<td valign="top">
Day of Month: <br />
<select id="dayOfMonth">
';

for($i=1;$i<32;$i++) { 
	echo "<option>$i</option>";
}

echo '
</select><br />
<button onclick="addToList(this);">Add</button><button onclick="removeFromList(this);">Remove</button><br />
<select name="date" id="date" size="5" style="width:100%;"> 

</select
</td>
<td valign="top">
Special Times: <br />
<select name="special" id="specials">
<option default>None</option>
<option>thanksgiving</option>
<option>xmas</option>
<option>easter</option>
</select>
</td>
<td valign="top">
Chance: <select name="chance" id="chances"><option default>100</option>';

for($i=99;$i>0;$i--) { 
	echo "<option>$i</option>";
}

echo '</select> <b>%</b>
</td>
</tr>
</table>
';


load_settings();


function formatProgramming($key, $arr) {
	$ret = null;
	if(array_key_exists($key, $arr)) {
		if($arr[$key]!=null) {
			if(is_array($arr[$key])) {
				$ret = "";
				foreach($arr[$key] as $k => $v) {
					$ret .= "$v, ";
				}
				if($ret!="") $ret = substr($ret,0,strlen($ret)-2);
			} else {
				$ret .= $arr[$key];
			}
		}
	}
	return $ret;
}

echo '<h2>Video Programming</h2>
<table cellpadding=6 cellspacing=2>
<tr style="background-color:#eee;"><td></td><td style="width:33%;">Name</td><td style="width:15%;">Days of the Week</td><td>Start Time</td><td>End Time</td><td style="width:7%;">Months</td><td>Days of Month</td><td>Specials</td><td>Channel</td><td>Chance?</td><td style="width:50px;">Pos</td><td></td></tr>
';
for($i=0;$i<count($settings['times']);$i++) {
	
	echo '<tr style="background-color:#' . ($i % 2 == 0 ? "ddd" : "eee") . '">';

	echo '<td> <img src="images/edit.png" style="width:18px;height:18px;" onclick="editItem(this);" /> </td>';

	$check = formatProgramming('name', $settings['times'][$i]);
	if($check!=Null) echo "<td>$check</td>"; else echo "<td>N/A</td>";
	
	$check = formatProgramming('dayOfWeek', $settings['times'][$i]);
	if($check!=Null) echo "<td>$check</td>"; else echo "<td>N/A</td>";
	
	$check = formatProgramming('start', $settings['times'][$i]);
	if($check!=Null) echo "<td>$check</td>"; else echo "<td>N/A</td>";

	$check = formatProgramming('end', $settings['times'][$i]);
	if($check!=Null) echo "<td>$check</td>"; else echo "<td>N/A</td>";

	$check = formatProgramming('month', $settings['times'][$i]);
	if($check!=Null) echo "<td>$check</td>"; else echo "<td>N/A</td>";
	
	$check = formatProgramming('date', $settings['times'][$i]);
	if($check!=Null) echo "<td>$check</td>"; else echo "<td>N/A</td>";

	$check = formatProgramming('special', $settings['times'][$i]);
	if($check!=Null) echo "<td>$check</td>"; else echo "<td>N/A</td>";
	
	$check = formatProgramming('channel', $settings['times'][$i]);
	if($check!=Null) echo "<td>$check</td>"; else echo "<td>N/A</td>";
	
	$check = formatProgramming('chance', $settings['times'][$i]);
	if($check!=Null) echo "<td>$check</td>"; else echo "<td>N/A</td>";
	
	echo '<td> ' . ($i>0?'<img src="images/up.png" style="width:18px;height:18px;" onclick="swapUp(this.parentNode.parentNode);" />' : '&nbsp;&nbsp;&nbsp;&nbsp;') . ' ' .($i<count($settings['times'])-1?'<img src="images/down.png" style="width:18px;height:18px;" onclick="swapDown(this.parentNode.parentNode);" />' : '') .' </td><td> <img src="images/delete.png" style="width:18px;height:18px;" /> </td>';
	
	echo '</tr>';
	if($i % 10 == 1) {
		echo '<tr style="background-color:#f9f9f9; color:#eee;"><td></td><td style="width:33%;">Name</td><td style="width:15%;">Days of the Week</td><td>Start Time</td><td>End Time</td><td style="width:7%;">Months</td><td>Days of Month</td><td>Specials</td><td>Channel</td><td>Chance?</td><td style="width:50px;">Pos</td><td></td></tr>';
	}
}

echo '</table>';


echo '<h2>Commercial Programming</h2>
<table cellpadding=6 cellspacing=2>
<tr style="background-color:#eee;"><td></td><td style="width:33%;">Name</td><td style="width:15%;">Days of the Week</td><td>Start Time</td><td>End Time</td><td style="width:7%;">Months</td><td>Days of Month</td><td>Specials</td><td>Chance?</td><td style="width:50px;">Pos</td><td></td></tr>
';
for($i=0;$i<count($settings['commercial_times']);$i++) {
	
	echo '<tr style="background-color:#' . ($i % 2 == 0 ? "ddd" : "eee") . '">';

	echo '<td> <img src="images/edit.png" style="width:18px;height:18px;" onclick="editItem(this);" /> </td>';

	$check = formatProgramming('name', $settings['commercial_times'][$i]);
	if($check!=Null) echo "<td>$check</td>"; else echo "<td>N/A</td>";
	
	$check = formatProgramming('dayOfWeek', $settings['commercial_times'][$i]);
	if($check!=Null) echo "<td>$check</td>"; else echo "<td>N/A</td>";
	
	$check = formatProgramming('start', $settings['commercial_times'][$i]);
	if($check!=Null) echo "<td>$check</td>"; else echo "<td>N/A</td>";

	$check = formatProgramming('end', $settings['commercial_times'][$i]);
	if($check!=Null) echo "<td>$check</td>"; else echo "<td>N/A</td>";

	$check = formatProgramming('month', $settings['commercial_times'][$i]);
	if($check!=Null) echo "<td>$check</td>"; else echo "<td>N/A</td>";
	
	$check = formatProgramming('date', $settings['commercial_times'][$i]);
	if($check!=Null) echo "<td>$check</td>"; else echo "<td>N/A</td>";

	$check = formatProgramming('special', $settings['commercial_times'][$i]);
	if($check!=Null) echo "<td>$check</td>"; else echo "<td>N/A</td>";
	
	$check = formatProgramming('chance', $settings['commercial_times'][$i]);
	if($check!=Null) echo "<td>$check</td>"; else echo "<td>N/A</td>";
	
	echo '<td> ' . ($i>0?'<img src="images/up.png" style="width:18px;height:18px;" onclick="swapUp(this.parentNode.parentNode);" />' : '&nbsp;&nbsp;&nbsp;&nbsp;') . ' ' .($i<count($settings['times'])-1?'<img src="images/down.png" style="width:18px;height:18px;" onclick="swapDown(this.parentNode.parentNode);" />' : '') .' </td><td> <img src="images/delete.png" style="width:18px;height:18px;" /> </td>';
	
	echo '</tr>';

}

echo '</table>';


echo '
</body>
</html>
';





?>