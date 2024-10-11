<?php



function json_error_to_string($id) {
	switch ($id) {
	  case JSON_ERROR_NONE:
		return "No errors";
		break;
	  case JSON_ERROR_DEPTH:
		return "Maximum stack depth exceeded";
		break;
	  case JSON_ERROR_STATE_MISMATCH:
		return "Invalid or malformed JSON";
		break;
	  case JSON_ERROR_CTRL_CHAR:
		return "Control character error";
		break;
	  case JSON_ERROR_SYNTAX:
		return "Syntax error";
		break;
	  case JSON_ERROR_UTF8:
		return "Malformed UTF-8 characters";
		break;
	  default:
		return "Unknown error";
		break;
	}
	return "Unknown error";
}


function json_validator($data=NULL) {
  if (!empty($data)) {
		@json_decode($data);
		return [(json_last_error() === JSON_ERROR_NONE), json_error_to_string(json_last_error())];
	}
	return [false, null];
}

$settings_file = "/home/pi/Desktop/settings.json";


?>
<html>
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Settings Editor</title>
<script type="text/javascript">
function validate_json(json_str) {
	try {
		return JSON.parse(json_str);
	}
	catch (e) {
		return null;
	}
}
</script>
<style>
	input {
		margin:2px;
	}
</style>
</head>
<body style="white-space:nowrap;">
<?php

if(isset($_POST["settings"]) && isset($_POST["save"])) {
	$json_valid = json_validator($_POST["settings"]);
	if(!$json_valid[0]) {
		die('<div style="background:red;color:white;margin:10px;padding:10px;">JSON VALIDATION ERROR :: THE SUBMITTED SETTINGS ARE NOT VALID! <br /><br />Error: '.$json_valid[1].' - <a href="https://duckduckgo.com/?q=json+validator&ia=answer">Validate</a></div>');
	} else {
		file_put_contents($settings_file, $_POST["settings"]);
		header("Location: settings.php?saved=yes\n\n");
		die();
	}
}

$json_data = file_get_contents($settings_file);
$json_valid = json_validator($json_data);
if(!$json_valid[0]) {
	echo '<div style="background:red;color:white;margin:10px;padding:10px;">JSON VALIDATION ERROR :: CHECK \'settings.json\' FILE! <br /><br />Error: '.$json_valid[1].' - <a href="https://duckduckgo.com/?q=json+validator&ia=answer">Validate</a></div>';
}

?>
<form method="POST">
Editing: <?php echo $settings_file; ?><br />
<input type="submit" name="save" value="Save" /><?php if(isset($_GET["saved"])) { echo '<span style="color:green;" id="saved-span">Saved!</span><script type="text/javascript">function hide_saved_msg() { document.getElementById("saved-span").style.display="none"; } setTimeout(hide_saved_msg, 2000);</script>'; } ?><br />
<textarea id="editor" name="settings" style="width:95%;height:75%;" wrap="off" spellcheck="false" nowrap>
<?php echo $json_data; ?>
</textarea>
</form>
<div id="new-slot">
</div>

<script type="text/javascript">
//from http://jsfiddle.net/rainecc/n6aRj/1/ and http://jsfiddle.net/felixc/o2ptfd5z/9/
var myInput = document.getElementById("editor");

    if(myInput.addEventListener ) {
        myInput.addEventListener('keydown',this.keyHandler,false);
    } else if(myInput.attachEvent ) {
        myInput.attachEvent('onkeydown',this.keyHandler); /* damn IE hack */
    }

	function insertAtCursor(myField, myValue) {
		//IE support
		if (document.selection) {
			myField.focus();
			sel = document.selection.createRange();
			sel.text = myValue;
		}
		//MOZILLA and others
		else if (myField.selectionStart || myField.selectionStart == '0') {
			var startPos = myField.selectionStart;
			var endPos = myField.selectionEnd;
			myField.value = myField.value.substring(0, startPos)
				+ myValue
				+ myField.value.substring(endPos, myField.value.length);
			myField.selectionStart = startPos + myValue.length;
			myField.selectionEnd = startPos + myValue.length;
		} else {
			myField.value += myValue;
		}
	}

    function keyHandler(e) {
        var TABKEY = 9;
        if(e.keyCode == TABKEY) {
            insertAtCursor(myInput, "\t");
            if(e.preventDefault) {
                e.preventDefault();
            }
            return false;
        }
    }
</script>

<h1>Base settings:</h1>
<h2><em>Details concerning base settings</em></h2>
	<h3>version:</h3>
	<ul>
		<li><i>float&gt;</i> the version number to which the main script compares ensuring compatibility</li>
	</ul>
	<h3>name:</h3>
	<ul>
		<li><i>string&gt;</i> used to identify the script in the web front end</li>
	</ul>
	<h3>drive:</h3>
	<ul>
		<li><i>[ string&gt;, array&gt; ]</i> list of strings containing locations of programming content. <i>(will automatically be converted to %D[index]% keyword)</i></li>
	</ul>
	<h3>insert_commercials:</h3>
	<ul>
		<li><i>boolean&gt;</i> setting to true will attempt to insert commercials into programming, false will disable this practice</li>
	</ul>
	<h3>commercials_per_break:</h3>
	<ul>
		<li><i>string&gt;</i> OR <i>whole integer&gt;</i> can be set to <b>'auto'</b> to allow the script to insert commercials based on lengths of video or a <b>whole integer</b> to force a set number of commercials to be played per break</li>
	</ul>
	<h3>time_test:</h3>
	<ul>
		<li><i>string&gt;</i> python date and time in string format which will force the script to program based on that time. (example: "Jul 18 2023 11:59AM")</li>
	</ul>
	<h3>report_data:</h3>
	<ul>
		<li><i>boolean&gt;</i> if set to false, no out going internet requests will be permitted (not even local requests).</li>
	</ul>
	<h3>player_settings:</h3>
	<ul>
		<li><i>[ string&gt;, array&gt; ]</i> list of arguments/commands to be sent to OMXplayer upon playing a video</li>
	</ul>
<h1>times/commercial_times:</h1>
<h2><em>Details concerning programming</em></h2>
<ul>
	<h3>name:</h3>
	<ul>
		<li>An array of paths to directorys that contain either video files or sub-directories that contain video files.</li>
		<li>special words: %D[NUM]% - sets the "drive" (ie base path) based on the the locations where <em>NUM</em> is the index corresponding to what was set in the "<a href="#drive">drive</a>" setting.</li>
	</ul>
	<h3>between:</h3>
	<ul>
		<li>An array of seconds (total seconds from 01 Jan 00:00) <i>OR</i> &lt;datetime&gt; <i>(%b %d %Y %I:%M%p)</i> during which the content can be played. (cannot be mixed must be seconds or datetime but never both)</li>
	</ul>
	<h3>dayOfWeek:</h3>
	<ul>
		<li>An array of days of the week on which the content can be played.</li>
		<li>special words: monday, tuesday, wednesday,thursday,friday,saturday,sunday and the wild cards all,any,*.</li>
		<li>setting as <em>null</em> is the same as using the wild card special words</li>
	</ul>
	<h3>month:</h3>
	<ul>
		<li>An array of numbers that correspond to the months of the year on which the content can be played. example: 1 - for the month of january</li>
		<li>special words: wild cards all,any,*.</li>
		<li>setting as <em>null</em> is the same as using the wild card special words</li>
	</ul>
	<h3>type:</h3>
	<ul>
		<li><i>&lt;string&gt;</i> - type of video to be played (defines how the script will organize, search for, and playback video)</li>
		<br />
		<ul>
			<li>video = selects random video from a directory consisting only of video files ignoring everything else</li>
			<li>video-show = selects random "show" from a directory consisting only of video files trying to balance the "shows" based on total play count</li>
			<li>balanced-video = selects a random video from a single directory balanced around total video play count. If more than a single directory is supplied, one will be chosen based on a weighted play count.</li>
			<li>ordered-show = selects random "show" from from a directory filled with sub-directories containing video files for that "show" and also selects the next video file in ascending order based on what was previously selected</li>
			<li>ordered-video = selects random video from from a directory filled with video files and also selects the next video file in ascending order based on what was previously selected from this directory</li>
			<li>commercial = selects random video from a directory consisting only of video files ignoring everything else identifying itself as a "commercial"</li>
		</ul>
	</ul>
	<h3>start:</h3>
	<ul>
		<li><i>[&lt;integer&gt;Hour, &lt;integer&gt;Minute]</i> defines the Hour and Minute for which the programmed video will be played</li>
	</ul>
	<h3>end:</h3>
	<ul>
		<li><i>[&lt;integer&gt;Hour, &lt;integer&gt;Minute]</i> defines the Hour and Minute for which the programmed video will cease being played</li>
	</ul>
	<h3>channel</h3>
	<ul>
		<li><i>&lt;string&gt;</i> when set video from only this 'channel' will be played</li>
	</ul>
</ul>
<h2><em>Details concerning references</em></h2>
<ul>
Settings can refer to variables defined within itself. Should aid with content that is repeated at certain time, especially if those times might change in the future.<br>
<br>
Format: $ref/var_name/index/or/key<br>
<br>
Must begin with "$ref/" and the variable must be defined before being referenced!<br>
<br>
<li>
	Example:<br>
		<pre>
	"vars": {
			"cartoons": { "am": { "start": [4, 0], "end": [10, 45] }, "pm": { "start": [15, 0], "end": [16, 45] } },
			}

	"times": [{
			"name": ["%D[1]%/cartoons/am"],
			"start": "$ref/cartoons/am/start",
			"end": "$ref/cartoons/am/end",
			"type": "video"
		},
		{
			"name": ["%D[1]%/cartoons/pm"],
			"start": "$ref/cartoons/pm/start",
			"end": "$ref/cartoons/pm/end",
			"type": "video"
		}]
		</pre>
	</li>
</ul>
<br><br>
<h3><em>Whole integers must be treated as numbers and floats must be treated as strings. This is a json requirement.</em></h3>
<br />
<br />
<h1>Between maker:</h1>
    <label for="datetime1">Select First Date and Time:</label>
    <input type="datetime-local" id="datetime1" min="2024-01-01T00:00" max="2024-12-31T23:59"> (must be before second date)
    <br><br>
    <label for="datetime2">Select Second Date and Time:</label>
    <input type="datetime-local" id="datetime2" min="2024-01-01T00:00" max="2024-12-31T23:59"> (must be after first date)
    <br><br>
    <button onclick="calculateDifference(0)">Generate Seconds Setting</button> <button onclick="calculateDifference(1)">Generate Seconds Array</button><br>
	<button onclick="calculateDifference(2)">Generate DateTime Setting</button> <button onclick="calculateDifference(3)">Generate DateTime Array</button><br>
	<br>
	Result: <input type="text" id="betweenResult" size="50">
    <p id="result"></p>

    <script>
		function formatDate(date) {
			month = date.toLocaleString('en-US',     {month: 'short' });
			day  =  date.toLocaleDateString('en-US', {day: '2-digit'});
			// year =  date.toLocaleDateString('en-US', {year: 'numeric'}); // year isn't needed
			time =  date.toLocaleDateString('en-US', {year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true}).toString().substr(-8).replace(/\s/g, "")
			
			return month + ' ' + day + ' ' + time;
		}
		
        function calculateDifference(type) {
            const datetime1 = new Date(document.getElementById('datetime1').value);
            const datetime2 = new Date(document.getElementById('datetime2').value);
            const startOfYear = new Date('2024-01-01T00:00:00');

            if (isNaN(datetime1) || isNaN(datetime2)) {
                document.getElementById('result').value = 'Please select both dates and times.';
                return;
            }

            const diff1 = (datetime1 - startOfYear) / 1000;
            const diff2 = (datetime2 - startOfYear) / 1000;
			
            if(!type) {
				document.getElementById('betweenResult').value = `"between": [ [${diff1}, ${diff2}] ]`;
			} else if(type==1) {
				document.getElementById('betweenResult').value = `[${diff1}, ${diff2}]` ;
			} else if(type==2) {
				document.getElementById('betweenResult').value = '"between": [ ["' + formatDate(datetime1) + '", "' + formatDate(datetime2) + '"] ]';
			} else if(type==3) {
				document.getElementById('betweenResult').value = '["' + formatDate(datetime1) + '", "' + formatDate(datetime2) + '"] ]';
			}
        }
    </script>

</body>
</html>