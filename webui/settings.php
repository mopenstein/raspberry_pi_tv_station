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
		<li><i>&lt;float&gt;</i> the version number to which the main script compares ensuring compatibility</li>
	</ul>
	<h3>name:</h3>
	<ul>
		<li><i>&lt;string&gt;</i> used to identify the script in the web front end</li>
	</ul>
	<h3>drive:</h3>
	<ul>
		<li><i>[ &lt;string&gt;, &lt;array&gt; ]</i> list of strings containing locations of programming content. <i>(will automatically be converted to %D[index]% keyword)</i></li>
	</ul>
	<h3>insert_commercials:</h3>
	<ul>
		<li><i>&lt;boolean&gt;</i> setting to true will attempt to insert commercials into programming, false will disable this practice</li>
	</ul>
	<h3>commercials_fill_time_multiplier:</h3>
	<ul>
		<li><i>&lt;number&gt;</i> multiplier in the timeout logic that is used when generating commercials to fill remaining time.</li>
	</ul>
	<h3>commercials_offset_time</h3>
	<ul>
		<li><i>&lt;whole number&gt;</i> number of seconds to offset the amount of time calculated to fill the remaining time of a video and the half/top of the hour. Positive numbers will add time, negative will subtract time. Use positive if your shows are starting too early and negative if starting too late.</li>
	</ul>
	<h3>commercials_per_break:</h3>
	<ul>
		<li><i>&lt;string&gt;</i> OR <i>whole integer&gt;</i> can be set to <b>'auto'</b> to allow the script to insert commercials based on lengths of video or a <b>whole integer</b> to force a set number of commercials to be played per break</li>
	</ul>
	<h3>time_test:</h3>
	<ul>
		<li><i>&lt;string&gt;</i> python date and time in string format which will force the script to program based on that time. (example: "Jul 18 2023 11:59AM")</li>
	</ul>
	<h3>report_data:</h3>
	<ul>
		<li><i>&lt;boolean&gt;</i> if set to false, no out going internet requests will be permitted (not even local requests).</li>
	</ul>
	<h3>player_settings:</h3>
	<ul>
		<li><i>[ &lt;string&gt;, &lt;array&gt; ]</i> list of arguments/commands to be sent to OMXplayer upon playing a video</li>
	</ul>
<h1><em>Details concerning programming</em></h1>
<ul>
	<h3>name:</h3>
	<ul>
		<li>An array of paths to directorys that contain either video files or sub-directories that contain video files.</li>
		<li>special words: %D[NUM]% - sets the "drive" (ie base path) based on the the locations where <em>NUM</em> is the index corresponding to what was set in the "<a href="#drive">drive</a>" setting.</li>
	</ul>
	<h3>between:</h3>
	<ul>
		<li>An array of &lt;dates&gt; <i>(%b %d)</i> and &lt;times&gt; <i>(%I:%M%p)</i> during which the content can be played.</li>
		<ul>
			<li>Example: "between": { "dates": [ ["Mar 01", "Apr 01"] ], "times": [ ["08:00AM", "10:00PM"] ] } // returns true if date is between Mar 01 and Apr 01 and time of days is between 8am and 10pm</li>
		</ul>
		<li>special keywords:</li>
		<ul>
			<li>"%HOUR%": current hour of the day in 12 hour format</li>
			<li>"%AMPM%": current AM/PM</li>
			<li>"%MIN%": current minute of the hour</li>
			<li>"%MONTH%": current month of the year in 3 letter format</li>
			<li>"%DAY%": current day of the month</li>
			<li>"%YEAR%": current year</li>
		</ul>
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
	<h3>min-length</h3>
	<ul>
		<li><i>&lt;integer&gt;</i> minimum length of video in seconds</li>
	</ul>
	<h3>max-length</h3>
	<ul>
		<li><i>&lt;integer&gt;</i> maximum length of video in seconds</li>
	</ul>
	<h3>prefer-folder</h3>
	<ul>
		<li><i>&lt;boolean&gt;</i> when set, the script will make its selection from the supplied list of folders rather than select a video from a combined list of all videos from all folders</li>
	</ul>
	<h3>note</h3>
	<ul>
		<li><i>&lt;string&gt;</i> text that has no effect other than for the settings programmer</li>
	</ul>
	<h3>set-tag</h3>
	<em>* note: filename tags override programming tags *</em>
	<ul>
		<li><i>&lt;string&gt;</i> sets the current tag used for commercials block programming</li>
	</ul>
	<h3>minimum time between repeats</h3>
	<ul>
		<li><i>&lt;integer&gt;</i> minimum time in seconds before the same video can be played again</li>
	</ul>
	<h3>weighted</h3>
	<ul>
		<li><i>&lt;list of integers&gt;</i> the weight of the video. The higher the number the more likely it will be played <i>(must be used in conjuction with "prefer-folder" setting and works only with video types set to 'commercial')</i></li>
		<li>Example: 
			<ul>
				<pre>
	"name": ["%D[1]%/folder1", "%D[1]%/folder2", "%D[1]%/folder3,"],
	"prefer-folder": "yes",
	"weighted": [ 25, 50, 25 ] // a video from folder 2 will be selected 50% of the time, folder 1 and 3 will be selected 25% of the time
				</pre>
			</ul>
		</li>
	</ul>
	<h3>tag</h3>
	<ul>
		<li><i>&lt;string&gt;</i> allow commercials to be played specifically for a video with a certain tag</li>
		<li>Example: "tag": "cartoons" // if a video is tagged in its filename as @cartoons@, this setting will be trigger</li>
	</ul>
	<h3>special</h3>
	<ul>
		<li><i>&lt;string&gt;</i> special keywords corresponding with holidays</li>
		<li>Special words: <i>can be modified with +/- days (see examples below)</i></li>
			<ul>
				<li><i>thanksgiving</i> - returns True if it is currently Thanksgiving day in the USA
				<li><i>xmas</i> - returns True beginning the day after Thanksgiving up until Dec 25th
				<li><i>easter</i> - returns True if it is Easter day. 
				<li><i>mothers day</i> - returns True if it is Mother's day. 
				<li><i>fathers day</i> - returns True if it is Father's day. 
			</ul>
			<li>Example: "special": "easter-10" // returns True if it is between 10 days before Easter day and Easter day itself</li>
			<li>Example: "special": "thanksgiving+5" // returns True if current date is within 5 days after Thanksgiving</li>
			<li>Example: "special": "xmas" // returns True if it's after Thanksgiving day and Christmas day or earlier</li>
	</ul>
	<h3>chance</h3>
	<ul>
		<li><i>&lt;float&gt;</i> mechanism to determines the likelihood of content being shown based on time-sensitive expressions. <em>percentage represented by decimal from 0 - 1. (e.g., .5 would be a 50% chance)</em></li>
		<li>Special words:</li>
			<ul>
				<li><i>weekday</i> - current day of the week (Monday = 0 / Sunday = 6)</li>
				<li><i>maxdays</i> - total number of days in the current month</li>
				<li><i>year</i> - current year (e.g., 2025)</li>
				<li><i>day</i> - current day of the month (1-31)</li>
				<li><i>month</i> - current month of the year (1 = January, 12 = December)</li>
				<li><i>hour</i> - current hour of the day (0-23)</li>
				<li><i>minute</i> - current minute of the hour (0-59)</li>
				<li><i>sin</i> - sine function; useful for creating smooth, cyclical patterns</li>
				<li><i>cos</i> - cosine function; similar to sine but offset in phase</li>
				<li><i>abs</i> - absolute value; returns the non-negative value of a number</li>
				<li><i>min</i> - returns the smaller of two values</li>
				<li><i>max</i> - returns the larger of two values</li>
				<li><i>round</i> - rounds a number to the nearest whole number</li>
				<li><i>floor</i> - rounds a number down to the nearest whole number</li>
				<li><i>ceil</i> - rounds a number up to the nearest whole number</li>
				<li><i>log</i> - natural logarithm (base e); useful for slow-growth curves</li>
				<li><i>exp</i> - exponential function (e^x); useful for rapid-growth curves</li>
				<li><i>pi</i> - the mathematical constant π (≈ 3.14159)</li>
				<li><i>e</i> - the mathematical constant e (≈ 2.71828)</li>
				<li><i>scale</i> - converts a whole number to a 0.0-1.0 scale by dividing by 100</li>
				<li><i>clamp</i> - restricts a value to the 0.0-1.0 range</li>
			</ul>
			<br>
			<h3>Examples:</h3>
			<ul>
				<li>"chance": "scale(day / maxdays)" // the chance that this program will trigger increases everyday from 0% to 100% as the days tick away in the month</li>
				<li>"chance": "clamp((4 - abs(hour - 8)) / 3.0 * (weekday in [5,6]) * .8)" // this example of chance starts ramping up at 5 AM, peaks around 8 AM, and fades by 12 PM on Saturday and Sundays only</li>
			</ul>
	</ul>
</ul>
<h1><em>Details concerning references</em></h1>
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
</body>
</html>