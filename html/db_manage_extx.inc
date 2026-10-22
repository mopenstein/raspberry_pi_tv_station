<?php

class DBMANAGEEXT {

	private $mysqlix;
	
    private $db_management_file = "db_manage.txt";
	
	public function load($database_info_host, $database_info_username, $database_info_password, $database_info_database_name) {
		$this->mysqlix = new mysqli($database_info_host, $database_info_username, $database_info_password, $database_info_database_name);
	}

	public function handle($args) {	
		switch($args["action"]) {
			case "trim_shows_empty":
				$res = $this->mysqlix->query("TRUNCATE TABLE `played`") or die($this->mysqlix->error);
				return; //"empty";
			break;
			case "trim_shows":
				$res = $this->mysqlix->query("DELETE FROM played WHERE played<=" . (time()-864000) . " ORDER BY id DESC") or die($this->mysqlix->error);
				return; //"trim";
			break;
			case "trim_commercials_empty":
				$res = $this->mysqlix->query("TRUNCATE TABLE `commercials`") or die($this->mysqlix->error);
				return; //"com empty";
			break;
			case "trim_commercials":
				$res = $this->mysqlix->query("DELETE FROM commercials WHERE played<=" . (time()-864000) . " ORDER BY id DESC") or die($this->mysqlix->error);
				return; //"com trim";
			break;
			case "trim_errors_empty":
				$res = $this->mysqlix->query("TRUNCATE TABLE `errors`") or die($this->mysqlix->error);
				return; //"er empty";
			break;
			case "trim_errors":
				$res = $this->mysqlix->query("DELETE FROM errors WHERE played<=" . (time()-864000) . " ORDER BY id DESC") or die($this->mysqlix->error);
				return; //"er trim";
			break;
			case "trim_test_now":
				$res = $this->mysqlix->query("SELECT name FROM `played` WHERE INSTR(name, '/primetime/') = 0 AND INSTR(name, '/cartoons/') = 0 AND INSTR(name, '/reruns/') = 0") or die($this->mysqlix->error);
				while ($brow = $res->fetch_assoc()) {
					var_dump($brow);
				}
				return;
			break;
            default:
                if(substr($args["action"], 0, 9)=="trim_data") {
                    if(!is_numeric(substr($args["action"], 9))) return "Must be a number";
                    if (file_exists($this->db_management_file)) { // Check if the file exists
                        $lines = file($this->db_management_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); // Optional flags to trim newlines and skip empty lines
                        if(substr($args["action"], 9)*1>=count($lines) || substr($args["action"], 9)*1<0) return "Number is too high or too low";

                        $res = $this->mysqlix->query("DELETE FROM `played` WHERE INSTR(name, '/".$lines[substr($args["action"], 9)*1]."/')") or die($this->mysqlix->error);

                        header('location: /?db='.$lines[substr($args["action"], 9)*1]."\n\n");
				        return;
                    } else {
                        return 'The db_manage.txt file does not exist. You must create it using <a href="db_management_build.php">db_management_build.php</a>.';
                    }
                } else if (substr($args["action"], 0, 9) == "trim_last") {
                    if(!is_numeric(substr($args["action"], 9))) return "Must be a number";
                    if (file_exists($this->db_management_file)) { // Check if the file exists
                        $lines = file($this->db_management_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); // Optional flags to trim newlines and skip empty lines
                        if(substr($args["action"], 9)*1>=count($lines) || substr($args["action"], 9)*1<0) return "Number is too high or too low";

                        $res = $this->mysqlix->query("DELETE FROM played WHERE INSTR(name, '/".$lines[substr($args["action"], 9)*1]."/') AND id NOT IN (SELECT id FROM (SELECT id FROM played WHERE INSTR(name, '/".$lines[substr($args["action"], 9)*1]."/') ORDER BY played DESC LIMIT 1) AS subquery);") or die($this->mysqlix->error);

                        header('location: /?db='.$lines[substr($args["action"], 9)*1]."\n\n");
				        return;
                    } else {
                        return 'The db_manage.txt file does not exist. You must create it using <a href="db_management_build.php">db_management_build.php</a>.';
                    }
				} else if (substr($args["action"], 0, 9) == "trim_all_") {
                    if(!is_numeric(substr($args["action"], 9))) return "Must be a number";
                    if (file_exists($this->db_management_file)) { // Check if the file exists
                        $lines = file($this->db_management_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); // Optional flags to trim newlines and skip empty lines
                        if(substr($args["action"], 9)*1>=count($lines) || substr($args["action"], 9)*1<0) return "Number is too high or too low";

                        //$res = $this->mysqlix->query("DELETE FROM played WHERE INSTR(name, '/".$lines[substr($args["action"], 9)*1]."/') AND id NOT IN (SELECT id FROM (SELECT id FROM played WHERE INSTR(name, '/".$lines[substr($args["action"], 9)*1]."/') ORDER BY played DESC LIMIT 1) AS subquery);") or die($this->mysqlix->error);
						// Find the most recent entry in the folder
						$folder = $lines[substr($args["action"], 9)*1];
						$subdirsSql = "SELECT DISTINCT SUBSTRING_INDEX(name, '/', -2) AS subdir FROM played WHERE name LIKE ?";
						$stmt = $this->mysqlix->prepare($subdirsSql);
						$searchTerm = "%/$folder/%";
						$stmt->bind_param("s", $searchTerm);
						$stmt->execute();
						$result = $stmt->get_result();
						
						$subdirs = [];
						while ($row = $result->fetch_assoc()) {
							if(!in_array("/" . pathinfo($row['subdir'], PATHINFO_DIRNAME) . "/", $subdirs)) {
								// Add the subdirectory to the list if it is not already present
								$subdirs[] =  "/" . pathinfo($row['subdir'], PATHINFO_DIRNAME) . "/";
								echo "Found subdirectory: " . "/" . pathinfo($row['subdir'], PATHINFO_DIRNAME) . "/" . "\n";
							}
							
						}
						$stmt->close();

						foreach ($subdirs as $subdir) {
							// Step 2: Find the most recent entry for each subdirectory
							$latestSql = "SELECT id FROM played WHERE name LIKE ? AND name LIKE ? ORDER BY played DESC LIMIT 1";
							echo "Processing subdirectory: $subdir\n";
							
							$stmt = $this->mysqlix->prepare($latestSql);
							$subdirPattern = "%$subdir%";
							$stmt->bind_param("ss", $searchTerm, $subdirPattern);
							$stmt->execute();
							$result = $stmt->get_result();
							$row = $result->fetch_assoc();
							$latestId = $row['id'] ?? null;
							$stmt->close();
							
							if ($latestId) {
								// Step 3: Delete all other entries in that subdirectory
								echo "Keeping latest entry with ID: $latestId for subdirectory: $subdir\n";
								$deleteSql = "DELETE FROM played WHERE name LIKE ? AND name LIKE ? AND id <> ?";
								$deleteStmt = $this->mysqlix->prepare($deleteSql);
								$deleteStmt->bind_param("ssi", $searchTerm, $subdirPattern, $latestId);
								$deleteStmt->execute();
								$deleteStmt->close();
						
								echo "Deleted previous entries for '$subdir', keeping only the most recent.\n";
							} else {
								echo "No matching entries found for '$subdir'.\n";
							}
						}


						die();
                        header('location: /?db='.$lines[substr($args["action"], 9)*1]."\n\n");
				        return;
                    } else {
                        return 'The db_manage.txt file does not exist. You must create it using <a href="db_management_build.php">db_management_build.php</a>.';
                    }
				}
			break;
		}
		return "Error Managing Database!";
	}
}

?>