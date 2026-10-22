<?php

# MetaData
# Name: Empty Table
# Description: Completely clears all entries from the specified database table.
# Param: target | dropdown | errors | played,errors,commercials | Target Table
# EndMetaData

/*
    Name: empty_table.php

	Description:

		This script is designed to completely empty a specified database table.
		The "target" parameter specifies which table to truncate (played, errors, commercials).
	
	Usage: ?target=errors
*/

	// This handles JSON, DB Connection, and $mysqli setup
	require_once("db_helper.inc");

    // 1. Whitelist allowed tables to prevent SQL injection
    $allowedTables = ['played', 'errors', 'commercials'];$targetTable = isset($_GET['target']) && in_array($_GET['target'], $allowedTables) ?$_GET['target'] : null;
	
	if (!$targetTable) {
		die("0|Invalid target table specified. Allowed values are: " . implode(", ", $allowedTables));
	}

    // 2. Perform the truncate
    // We manually insert the table name from our safe whitelist[cite: 8, 9, 10]
    $truncateSql = "TRUNCATE TABLE `$targetTable`";
    
    if ($mysqli->query($truncateSql)) {
        die("1|Successfully emptied the '$targetTable' table.");
    } else {
        die("0|Database error on table '$targetTable': " . $mysqli->error);
    }

?>