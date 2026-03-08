<?php

/*
	Name: trim_played_by_most_recent.php

	Description:

		This script trims the "played" database table by keeping only the most recent entries for each unique subdirectory.
		The "count" parameter specifies how many of the most recent entries to keep for each unique subdirectory.

	Usage: ?count=5
*/

	// This handles JSON, DB Connection, and $mysqli setup
	require_once("db_helper.inc");

    set_time_limit(0);

    // 1. Get the count from the URL, default to 5 if not provided or not a number
    $keepCount = isset($_GET['count']) && is_numeric($_GET['count']) ? (int)$_GET['count'] : 5;

    // Safety check: ensure we aren't trying to keep 0 or negative
    if ($keepCount < 1) $keepCount = 1;

    $allFilesSql = "SELECT DISTINCT name FROM played";
    $result = $mysqli->query($allFilesSql);
    
    $uniqueFolders = [];
    while ($row = $result->fetch_assoc()) {
        $folder = dirname($row['name']);
        if (!in_array($folder, $uniqueFolders)) {
            $uniqueFolders[] = $folder;
        }
    }

    foreach ($uniqueFolders as $folder) {
        $folderPattern = $folder . "/%";
        
        // 2. Use the dynamic $keepCount in the LIMIT clause
        // Note: LIMIT in MySQL doesn't accept placeholders in all versions, 
        // but since we cast $keepCount to (int) above, it is safe to concatenate.
        $latestSql = "SELECT id FROM played WHERE name LIKE ? ORDER BY played DESC LIMIT " . $keepCount;
        $stmt = $mysqli->prepare($latestSql);
        $stmt->bind_param("s", $folderPattern);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $keepIds = [];
        while ($row = $res->fetch_assoc()) {
            $keepIds[] = $row['id'];
        }
        $stmt->close();

        if (count($keepIds) > 0) {
            $placeholders = implode(',', array_fill(0, count($keepIds), '?'));
            $deleteSql = "DELETE FROM played WHERE name LIKE ? AND id NOT IN ($placeholders)";
            
            $delStmt = $mysqli->prepare($deleteSql);
            $types = "s" . str_repeat("i", count($keepIds));
            $params = array_merge([$folderPattern], $keepIds);
            
            $delStmt->bind_param($types, ...$params);
            $delStmt->execute();
            $delStmt->close();
        }
    }

	die("1|Trim complete. Kept $keepCount most recent entries for each unique subdirectory in the 'played' table.");

?>