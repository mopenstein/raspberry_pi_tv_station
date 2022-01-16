<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);

if(isset($_GET["folder"])) {
	$folder = $_GET["folder"];
}

echo '
<table>
	<tr>
		<td style="width:30%;">';

if ($handle = opendir('/media/pi/ssd/commercials/'.$folder.'/')) {

    while (false !== ($entry = readdir($handle))) {

        if ($entry != "." && $entry != "..") {

			echo '<li><a href="/?video=commercials/' . $folder . '/' . $entry . '" target="player">' . $entry . "</a> ".' <a href="/?delete=commercials/' . $folder . '/' . $entry . '">[delete]</a></li>';

        }
    }

    closedir($handle);
}

echo '	
		</td>
		<td style="width:70%; background-color:black;" valign="top">
<iframe name="player" id="player" style="width:65%;height:700px; position: fixed;"></iframe>
		</td>
	</tr>
</table>';

?>