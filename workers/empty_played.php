<?php

/*

	Name: empty_played.php
	Description:

		This script is designed to empty the "played" database table.
		
*/

	// This handles JSON, DB Connection, and $mysqli setup
	require_once("db_helper.inc");

	$res = $mysqli->query("TRUNCATE TABLE `played`") or die($this->mysqlix->error);

?>