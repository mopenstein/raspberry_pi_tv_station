<?php

/*

	Name: empty_errors.php
	Description:

		This script is designed to empty the "errors" database table.

*/

	// This handles JSON, DB Connection, and $mysqli setup
	require_once("db_helper.inc");

	$res = $mysqli->query("TRUNCATE TABLE `errors`") or die($this->mysqlix->error);

?>