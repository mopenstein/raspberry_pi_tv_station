<?php
/*
    Name: trim_by_days.php

	Description:

		This script trims a specified database table by keeping only entries from the most recent specified number of days.
		The "target" parameter specifies which table to trim (played, errors, commercials).
		The "days" parameter specifies how many recent days to keep.
	
	Usage: ?target=played&days=2
*/

	// This handles JSON, DB Connection, and $mysqli setup
	require_once("db_helper.inc");

    set_time_limit(60);

    // 1. Whitelist allowed tables to prevent SQL injection
    $allowedTables = ['played', 'errors', 'commercials'];
    $targetTable = isset($_GET['target']) && in_array($_GET['target'], $allowedTables) ? $_GET['target'] : null;
	
	if (!$targetTable) {
		die("0|Invalid target table specified. Allowed values are: " . implode(", ", $allowedTables));
	}

    // 2. Get the number of days to keep
    $daysToKeep = isset($_GET['days']) && is_numeric($_GET['days']) ? (int)$_GET['days'] : 2;
    if ($daysToKeep < 1) $daysToKeep = 1;

    // 3. Calculate the "midnight" cutoff
    $dayOffset = $daysToKeep - 1;
    $cutoffString = "midnight -$dayOffset days";
    $cutoffTimestamp = strtotime($cutoffString);

    // 4. Perform the delete
    // We manually insert the table name from our safe whitelist
    $deleteSql = "DELETE FROM `$targetTable` WHERE played < ?";
    
    $stmt = $mysqli->prepare($deleteSql);
    $stmt->bind_param("i", $cutoffTimestamp);
    
    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        $readableCutoff = date("Y-m-d H:i:s", $cutoffTimestamp);
        die("1|Trim complete for table '$targetTable'. Kept $daysToKeep days. Deleted everything before $readableCutoff ($affected rows removed).");
    } else {
        die("0|Database error on table '$targetTable': " . $mysqli->error);
    }
?>