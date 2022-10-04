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
</body>
</html>