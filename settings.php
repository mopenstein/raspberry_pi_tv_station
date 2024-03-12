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

<h3>name:</h3>
<ul>
	<li>An array of paths to directorys that contain either video files or sub-directories that contain video files.</li>
	<li>special words: %D[NUM]% - sets the "drive" (ie base path) based on the the locations where <em>NUM</em> is the index corresponding to what was set in the "<a href="#drive">drive</a>" setting.</li>
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
	<li>video = selects random video from a directory consisting only of video files ignoring everything else</li>
	<li>video-show = selects random "show" from a directory consisting only of video files trying to balance the "shows" based on total play count</li>
	<li>balanced-video = selects a random video from a single directory balanced around total video play count. If more than a single directory is supplied, one will be chosen based on a weighted play count.</li>
	<li>ordered-show = selects random "show" from from a directory filled with sub-directories containing video files for that "show" and also selects the next video file in ascending order based on what was previously selected</li>
	<li>ordered-video = selects random video from from a directory filled with video files and also selects the next video file in ascending order based on what was previously selected from this directory</li>
	<li>commercial = selects random video from a directory consisting only of video files ignoring everything else identifying itself as a "commercial"</li>
</ul>

<em>Whole integers must be treated as numbers and floats must be treated as strings. This is a json requirement.</em>
</body>
</html>