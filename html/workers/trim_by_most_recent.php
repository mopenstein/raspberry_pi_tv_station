<?php

# MetaData
# Name: Trim by Most Recent
# Description: Trims a specified database table by keeping only the most recent entries for each unique subdirectory.
# Param: target | dropdown | played | played,commercials,errors | Target Table
# Param: count | number | 5 | 1,100 | Keep Last X Entries
# Param: skip_paths | text | | | Skip Paths (separated by ;)
# EndMetaData

/*
	Name: trim_by_most_recent.php

	Description:

		This script trims a specified database table by keeping only the most recent entries for each unique subdirectory.
		The "target" parameter specifies which table to trim (played, errors, commercials).
		The "count" parameter specifies how many of the most recent entries to keep for each unique subdirectory.

	Usage: ?target=played&count=5
*/

	// This handles JSON, DB Connection, and $mysqli setup
	require_once("db_helper.inc");

    set_time_limit(0);

    // 1. Whitelist allowed tables to prevent SQL injection
    $allowedTables = ['played', 'errors', 'commercials'];$targetTable = isset($_GET['target']) && in_array($_GET['target'], $allowedTables) ?$_GET['target'] : 'played';

    // 2. Get the count from the URL, default to 5 if not provided or not a number
    $keepCount = isset($_GET['count']) && is_numeric($_GET['count']) ? (int)$_GET['count'] : 5;
    // Safety check: ensure we aren't trying to keep 0 or negative
    if ($keepCount < 1)$keepCount = 1;

	// 3. Parse skip_paths into an array using a semicolon
	$skipPaths = isset($_GET['skip_paths']) ? explode(';', $_GET['skip_paths']) : [];
	// Filter out empty strings in case of trailing semicolons
	$skipPaths = array_filter($skipPaths);

    // Use backticks for the dynamic table name
    $allFilesSql = "SELECT DISTINCT name FROM `$targetTable`";
    $result =$mysqli->query($allFilesSql);$uniqueFolders = [];
    if ($result) {
        while ($row =$result->fetch_assoc()) {
            $folder = dirname($row['name']);
            if (!in_array($folder,$uniqueFolders)) {
                $uniqueFolders[] =$folder;
            }
        }
    }

    foreach ($uniqueFolders as $folder) {$skipThis = false;
		foreach ($skipPaths as$skip) {
			if (strpos($folder, $skip) !== false) {$skipThis = true;
				break;
			}
		}

		// Skip pruning for this folder if a match was found
		if ($skipThis) {
			continue;
		}

        $folderPattern =$folder . "/%";
        
        // Use the dynamic $keepCount in the LIMIT clause$latestSql = "SELECT id FROM `$targetTable` WHERE name LIKE ? ORDER BY played DESC LIMIT " . $keepCount;
        $stmt = $mysqli->prepare($latestSql);
        $stmt->bind_param("s", $folderPattern);
        $stmt->execute();$res = $stmt->get_result();$keepIds = [];
        while ($row =$res->fetch_assoc()) {
            $keepIds[] =$row['id'];
        }
        $stmt->close();

        if (count($keepIds) > 0) {$placeholders = implode(',', array_fill(0, count($keepIds), '?'));$deleteSql = "DELETE FROM `$targetTable` WHERE name LIKE ? AND id NOT IN ($placeholders)";
            
            $delStmt = $mysqli->prepare($deleteSql);
            $types = "s" . str_repeat("i", count($keepIds));
            $params = array_merge([$folderPattern], $keepIds);$delStmt->bind_param($types, ...$params);
            $delStmt->execute();$delStmt->close();
        }
    }

	die("1|Trim complete. Kept $keepCount most recent entries for each unique subdirectory in the '$targetTable' table.");

?>